<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

$student_id    = (int)$_SESSION['user_id'];
$student_name  = $_SESSION['student_name']  ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager = new EventManager($conn);

// ---------- DASHBOARD STATS (adjust SQL to your schema) ----------

$stats = [
    'upcoming_assignments' => 0,
    'avg_percentage'       => null,
    'total_results'        => 0,
    'fee_due'              => null,
    'unpaid_invoices'      => 0,
    'unread_notices'       => 0,
];

try {
    // TODO: change table/column names to your assignment tables
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM assignments a
        LEFT JOIN assignment_submissions s 
               ON a.id = s.assignment_id AND s.student_id = ?
        WHERE a.due_date >= CURDATE()
          AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND (s.id IS NULL OR s.status <> 'submitted')
    ");
    $stmt->execute([$student_id]);
    $stats['upcoming_assignments'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    // TODO: change to your results/marks table
    $stmt = $conn->prepare("
        SELECT AVG(percentage) AS avg_percent, COUNT(*) AS total_rows,
               MAX(published_at) AS last_published_at
        FROM results
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['avg_percentage'] = $row['avg_percent'] !== null ? round($row['avg_percent'], 1) : null;
        $stats['total_results']  = (int)$row['total_rows'];
        $stats['last_result_at'] = $row['last_published_at'] ?? null;
    }
} catch (Exception $e) {}

try {
    // TODO: adapt to your fee tables
    $stmt = $conn->prepare("
        SELECT 
          COALESCE(SUM(CASE WHEN status = 'due'   THEN amount ELSE 0 END),0) AS due_amount,
          COALESCE(SUM(CASE WHEN status = 'paid'  THEN amount ELSE 0 END),0) AS paid_amount
        FROM student_fees
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['fee_due']         = max(0, $row['due_amount'] - $row['paid_amount']);
        $stats['unpaid_invoices'] = $row['due_amount'] > 0 ? 1 : 0; // tweak if you track per-invoice
    }
} catch (Exception $e) {}

try {
    // TODO: adapt to your notices/notifications table
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM notices
        WHERE (student_id = ? OR student_id IS NULL)
          AND (is_read = 0 OR is_read IS NULL)
    ");
    $stmt->execute([$student_id]);
    $stats['unread_notices'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}


// ---------- Recommended events with “time left” ----------

$recommendedEvents = $eventManager->getStudentRecommendedEvents($student_id, 5);

function computeTimeLeftLabel(?string $date, ?string $time): string
{
    if (empty($date)) {
        return '';
    }

    try {
        $datePart = $date;
        $timePart = $time ?: '00:00:00';
        $eventDT  = new DateTime("$datePart $timePart");
        $now      = new DateTime();

        if ($eventDT < $now) {
            return 'Already happened';
        }

        $diff = $now->diff($eventDT);

        if ($diff->days > 0) {
            return 'in ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return 'in ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return 'in ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        return 'in a few moments';
    } catch (Exception $e) {
        return '';
    }
}

// Attach label into each event
foreach ($recommendedEvents as &$ev) {
    $ev['time_left_label'] = computeTimeLeftLabel(
        $ev['event_date'] ?? null,
        $ev['start_time'] ?? null
    );
}
unset($ev);


// ---------- Build notification list for the bell ----------

$notifications = [];

// Upcoming assignments
if ($stats['upcoming_assignments'] > 0) {
    $notifications[] = [
        'type' => 'assignment',
        'text' => "You have {$stats['upcoming_assignments']} assignment(s) due in the next 7 days."
    ];
}

// Pending fees
if (!is_null($stats['fee_due']) && $stats['fee_due'] > 0) {
    $amount = number_format($stats['fee_due']);
    $notifications[] = [
        'type' => 'fee',
        'text' => "You have pending fees of Rs {$amount}."
    ];
}

// Recent result (example: published in last 7 days)
if (!empty($stats['last_result_at'])) {
    try {
        $lastResult = new DateTime($stats['last_result_at']);
        $now        = new DateTime();
        $diff       = $now->diff($lastResult);
        if ($diff->days <= 7) {
            $notifications[] = [
                'type' => 'result',
                'text' => "New result updated. Check your result summary."
            ];
        }
    } catch (Exception $e) {}
}

// Upcoming event (take the soonest recommended future one)
foreach ($recommendedEvents as $ev) {
    if (!empty($ev['time_left_label']) && $ev['time_left_label'] !== 'Already happened') {
        $notifications[] = [
            'type' => 'event',
            'text' => "Upcoming event: {$ev['title']} ({$ev['time_left_label']})."
        ];
        break; // only one is enough here
    }
}

// Unread notices (optional)
if ($stats['unread_notices'] > 0) {
    $notifications[] = [
        'type' => 'notice',
        'text' => "You have {$stats['unread_notices']} unread notice(s)."
    ];
}

$notificationCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .stat-card h3 {
      margin: 0 0 6px;
      font-size: 1rem;
      color: #4caf50;
    }
    .stat-card .big-number {
      font-size: 1.8rem;
      font-weight: 700;
      color: #111;
      margin-bottom: 4px;
    }
    .stat-card .sub-text {
      font-size: 0.85rem;
      color: #666;
    }

    .events-section {
      margin-top: 30px;
    }
    .events-section h3 {
      margin-bottom: 12px;
    }
    .event-card {
      background: #ffffff;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .event-card h4 {
      margin: 0 0 4px;
      font-size: 1rem;
    }
    .event-meta {
      font-size: 0.85rem;
      color: #555;
      line-height: 1.4;
    }
    .event-meta .time-left {
      font-weight: 600;
      color: #4caf50;
    }

    /* Small colored tag for notification types */
    .notif-tag {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 600;
      margin-right: 6px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .notif-assign  { background:#e8f5e9; color:#2e7d32; }
    .notif-fee     { background:#ffebee; color:#c62828; }
    .notif-event   { background:#e3f2fd; color:#1565c0; }
    .notif-result  { background:#f3e5f5; color:#6a1b9a; }
    .notif-notice  { background:#fff8e1; color:#f9a825; }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>

      <nav class="nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="assignments.php"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($student_email) ?></div>

        <div class="profile-actions">
          <div class="dropdown">
            <i class="fas fa-cog" id="settingsToggle" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false"></i>
            <div class="settings-dropdown" id="settingsMenu" role="menu" aria-hidden="true">
              <label>
                <input type="checkbox" id="darkModeToggle" />
                Dark Mode
              </label>
              <label>
                Language:
                <select id="languageSelect" aria-label="Select Language">
                  <option value="en">English</option>
                  <option value="np">Nepali</option>
                </select>
              </label>
            </div>
          </div>
          <a href="../../auth/logout.php" class="logout-icon" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <div>
          <h2>Student Dashboard</h2>
          <p>Welcome, <?= htmlspecialchars($student_name) ?>!</p>
        </div>
        <div class="actions">
          <div class="notification">
            <i class="fas fa-bell" id="notificationBell" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false"></i>
            <?php if ($notificationCount > 0): ?>
              <span class="notification-dot"></span>
            <?php endif; ?>
            <div class="notification-dropdown" id="notificationDropdown" role="menu" aria-hidden="true">
              <p><strong>Notifications</strong></p>
              <ul>
                <?php if ($notificationCount === 0): ?>
                  <li class="empty">No new notifications.</li>
                <?php else: ?>
                  <?php foreach ($notifications as $n): ?>
                    <li>
                      <?php
                        $tagClass = 'notif-notice';
                        if ($n['type'] === 'assignment') $tagClass = 'notif-assign';
                        elseif ($n['type'] === 'fee')   $tagClass = 'notif-fee';
                        elseif ($n['type'] === 'event') $tagClass = 'notif-event';
                        elseif ($n['type'] === 'result')$tagClass = 'notif-result';
                      ?>
                      <span class="notif-tag <?= $tagClass ?>">
                        <?= strtoupper($n['type']) ?>
                      </span>
                      <?= htmlspecialchars($n['text']) ?>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <!-- Top Stat Cards -->
      <section class="cards">
        <div class="card stat-card">
          <h3>Upcoming Assignments</h3>
          <div class="big-number"><?= $stats['upcoming_assignments'] ?></div>
          <div class="sub-text">Due in the next 7 days</div>
        </div>

        <div class="card stat-card">
          <h3>Result Summary</h3>
          <?php if ($stats['avg_percentage'] !== null): ?>
            <div class="big-number"><?= $stats['avg_percentage'] ?>%</div>
            <div class="sub-text">Based on <?= $stats['total_results'] ?> result<?= $stats['total_results'] == 1 ? '' : 's' ?></div>
          <?php else: ?>
            <div class="big-number">—</div>
            <div class="sub-text">No results published yet</div>
          <?php endif; ?>
        </div>

        <div class="card stat-card">
          <h3>Fee Status</h3>
          <?php if ($stats['fee_due'] !== null): ?>
            <div class="big-number">
              <?= $stats['fee_due'] > 0 ? 'Rs ' . number_format($stats['fee_due']) : 'Clear' ?>
            </div>
            <div class="sub-text">
              <?= $stats['fee_due'] > 0 ? 'Amount due' : 'No pending payments' ?>
            </div>
          <?php else: ?>
            <div class="big-number">—</div>
            <div class="sub-text">Fee records not available</div>
          <?php endif; ?>
        </div>

        <div class="card stat-card">
          <h3>Notices</h3>
          <div class="big-number"><?= $stats['unread_notices'] ?></div>
          <div class="sub-text">Unread notices</div>
        </div>
      </section>

      <!-- Recommended Events Section -->
      <section class="events-section">
        <h3><i class="fa-solid fa-calendar-check"></i> Recommended Events For You</h3>

        <?php if (empty($recommendedEvents)): ?>
          <p>No recommended events right now. Once you participate in some events, similar ones will appear here.</p>
        <?php else: ?>
          <?php foreach ($recommendedEvents as $ev): ?>
            <div class="event-card">
              <h4><?= htmlspecialchars($ev['title']) ?></h4>
              <div class="event-meta">
                <?= htmlspecialchars($ev['category_name'] ?? 'Event') ?>
                |
                <?= htmlspecialchars($ev['event_date']) ?>
                <?php if (!empty($ev['start_time'])): ?>
                  at <?= htmlspecialchars(substr($ev['start_time'], 0, 5)) ?>
                <?php endif; ?>
                <br>
                <?= htmlspecialchars($ev['location'] ?? '') ?>
                <?php if (!empty($ev['time_left_label'])): ?>
                  <br><span class="time-left"><?= htmlspecialchars($ev['time_left_label']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    // Notification toggle
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    bell.addEventListener('click', () => {
      dropdown.classList.toggle('show');
      const expanded = bell.getAttribute('aria-expanded') === 'true';
      bell.setAttribute('aria-expanded', !expanded);
      dropdown.setAttribute('aria-hidden', expanded);
    });

    document.addEventListener('click', (e) => {
      if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
        bell.setAttribute('aria-expanded', 'false');
        dropdown.setAttribute('aria-hidden', 'true');
      }
    });

    // Settings toggle
    const settingsToggle = document.getElementById('settingsToggle');
    const settingsMenu = document.getElementById('settingsMenu');
    settingsToggle.addEventListener('click', () => {
      settingsMenu.classList.toggle('show');
      const expanded = settingsToggle.getAttribute('aria-expanded') === 'true';
      settingsToggle.setAttribute('aria-expanded', !expanded);
      settingsMenu.setAttribute('aria-hidden', expanded);
    });

    document.addEventListener('click', (e) => {
      if (!settingsToggle.contains(e.target) && !settingsMenu.contains(e.target)) {
        settingsMenu.classList.remove('show');
        settingsToggle.setAttribute('aria-expanded', 'false');
        settingsMenu.setAttribute('aria-hidden', 'true');
      }
    });

    // Dark mode toggle with localStorage persistence
    const darkToggle = document.getElementById('darkModeToggle');
    if (localStorage.getItem('darkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkToggle.checked = true;
    }
    darkToggle.addEventListener('change', () => {
      document.body.classList.toggle('dark-mode');
      if (document.body.classList.contains('dark-mode')) {
        localStorage.setItem('darkMode', 'enabled');
      } else {
        localStorage.setItem('darkMode', 'disabled');
      }
    });
  </script>
</body>
</html>
