<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

$student_id    = (int)$_SESSION['user_id'];
$student_name  = $_SESSION['student_name']  ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager = new EventManager($conn);

// --------------------------------------------------
// Helper: get student class
// --------------------------------------------------
$student_class = $_SESSION['class'] ?? null;
if (!$student_class) {
    try {
        // TODO: adjust students.class_name if your column name differs
        $stmt = $conn->prepare("SELECT class_name FROM students WHERE user_id = ?");
        $stmt->execute([$student_id]);
        $student_class = $stmt->fetchColumn() ?: 'Unknown';
    } catch (Exception $e) {
        $student_class = 'Unknown';
    }
}

// --------------------------------------------------
// DASHBOARD STATS
// --------------------------------------------------
$stats = [
    'upcoming_assignments' => 0,
    'avg_percentage'       => null,
    'total_results'        => 0,
    'last_result_at'       => null,
    'total_fee_paid'       => 0.0,
    'fee_status'           => 'Pending',
    'attendance_pct'       => null,
    'unread_notices'       => 0,   // if you later add a notices table
];

// 1) Upcoming assignments for this student's class
try {
    // Uses teacher's assignments table
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM assignments
        WHERE class_name = ?
          AND status = 'Open'
          AND (due_date IS NULL OR due_date >= CURDATE())
    ");
    $stmt->execute([$student_class]);
    $stats['upcoming_assignments'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// 2) Result summary from grades table
try {
    // grades table used by teacher Grade Book
    $stmt = $conn->prepare("
        SELECT score, date_added
        FROM grades
        WHERE student_id = ?
        ORDER BY date_added DESC
    ");
    $stmt->execute([$student_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumPct   = 0.0;
    $cntPct   = 0;
    $lastDate = null;

    foreach ($rows as $row) {
        $score = trim($row['score'] ?? '');
        // Try to parse "45/50" style scores
        if (preg_match('/^\s*(\d+)\s*\/\s*(\d+)\s*$/', $score, $m) && (int)$m[2] > 0) {
            $pct = ((int)$m[1] / (int)$m[2]) * 100;
            $sumPct += $pct;
            $cntPct++;
        }
        if ($lastDate === null) {
            $lastDate = $row['date_added'] ?? null;
        }
    }

    if ($cntPct > 0) {
        $stats['avg_percentage'] = round($sumPct / $cntPct, 1);
        $stats['total_results']  = $cntPct;
        $stats['last_result_at'] = $lastDate;
    }
} catch (Exception $e) {}

// 3) Fee status from fees table (your existing fee.php logic)
try {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $total_paid = (float)$stmt->fetchColumn();
    $stats['total_fee_paid'] = $total_paid;
    $stats['fee_status']     = $total_paid > 0 ? 'Paid' : 'Pending';
} catch (Exception $e) {}

// 4) Attendance percentage from attendance table
try {
    $stmt = $conn->prepare("SELECT status FROM attendance WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $present = 0;
    $absent  = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'present') $present++;
        elseif ($r['status'] === 'absent') $absent++;
    }
    $total = $present + $absent;
    if ($total > 0) {
        $stats['attendance_pct'] = round(($present / $total) * 100, 1);
    }
} catch (Exception $e) {}

// 5) Unread notices (optional, if you create a notices table later)
try {
    // Example: adjust if/when you create a notices table
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM notices
        WHERE (student_id = ? OR student_id IS NULL)
          AND (is_read = 0 OR is_read IS NULL)
    ");
    $stmt->execute([$student_id]);
    $stats['unread_notices'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}


// --------------------------------------------------
// Recommended events with “time left”
// --------------------------------------------------
$recommendedEvents = $eventManager->getStudentRecommendedEvents($student_id, 5);

function computeTimeLeftLabel(?string $date, ?string $time): string
{
    if (empty($date)) return '';

    try {
        $datePart = $date;
        $timePart = $time ?: '00:00:00';
        $eventDT  = new DateTime("$datePart $timePart");
        $now      = new DateTime();

        if ($eventDT < $now) {
            return 'Already happened';
        }

        $diff = $now->diff($eventDT);
        if ($diff->days > 0) return 'in ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        if ($diff->h    > 0) return 'in ' . $diff->h    . ' hour' . ($diff->h    > 1 ? 's' : '');
        if ($diff->i    > 0) return 'in ' . $diff->i    . ' minute' . ($diff->i  > 1 ? 's' : '');
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

// --------------------------------------------------
// Build notification list for the bell
// --------------------------------------------------
$notifications = [];

// Upcoming assignments
if ($stats['upcoming_assignments'] > 0) {
    $notifications[] = [
        'type' => 'assignment',
        'text' => "You have {$stats['upcoming_assignments']} assignment(s) due soon for {$student_class}."
    ];
}

// Pending fees
if ($stats['fee_status'] === 'Pending') {
    $notifications[] = [
        'type' => 'fee',
        'text' => "Your fee status is pending. Please check Fee Details."
    ];
}

// Recent grade update (within last 7 days if we have a date)
if (!empty($stats['last_result_at'])) {
    try {
        $lastResult = new DateTime($stats['last_result_at']);
        $now        = new DateTime();
        if ($now->diff($lastResult)->days <= 7) {
            $notifications[] = [
                'type' => 'result',
                'text' => "New grade/assessment was updated recently. Check your results."
            ];
        }
    } catch (Exception $e) {}
}

// Attendance warning if below 75%
if ($stats['attendance_pct'] !== null && $stats['attendance_pct'] < 75) {
    $notifications[] = [
        'type' => 'attendance',
        'text' => "Your attendance is currently {$stats['attendance_pct']}%. Try to improve it."
    ];
}

// Upcoming event (take the soonest recommended future one)
foreach ($recommendedEvents as $ev) {
    if (!empty($ev['time_left_label']) && $ev['time_left_label'] !== 'Already happened') {
        $notifications[] = [
            'type' => 'event',
            'text' => "Upcoming event: {$ev['title']} ({$ev['time_left_label']})."
        ];
        break;
    }
}

// Unread notices
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

    /* Notification tags */
    .notif-tag {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:0.7rem;
      font-weight:600;
      margin-right:6px;
      text-transform:uppercase;
      letter-spacing:0.03em;
    }
    .notif-assignment { background:#e8f5e9; color:#2e7d32; }
    .notif-fee       { background:#ffebee; color:#c62828; }
    .notif-event     { background:#e3f2fd; color:#1565c0; }
    .notif-result    { background:#f3e5f5; color:#6a1b9a; }
    .notif-notice    { background:#fff8e1; color:#f9a825; }
    .notif-attendance{ background:#ede7f6; color:#4527a0; }

    /* Simple To-Do list */
    .todo-section {
      margin-top: 30px;
      max-width: 480px;
    }
    .todo-section h3 {
      margin-bottom: 10px;
    }
    .todo-input-row {
      display:flex;
      gap:8px;
      margin-bottom:10px;
    }
    .todo-input-row input {
      flex:1;
      padding:6px 10px;
      border-radius:6px;
      border:1px solid #ccc;
    }
    .todo-input-row button {
      padding:6px 12px;
      border:none;
      border-radius:6px;
      background:#4caf50;
      color:#fff;
      cursor:pointer;
      font-size:0.9rem;
    }
    .todo-list {
      list-style:none;
      padding:0;
      margin:0;
    }
    .todo-list li {
      display:flex;
      align-items:center;
      justify-content:space-between;
      background:#fff;
      padding:8px 10px;
      border-radius:6px;
      margin-bottom:6px;
      box-shadow:0 1px 3px rgba(0,0,0,0.05);
      font-size:0.9rem;
    }
    .todo-list li.completed span {
      text-decoration:line-through;
      color:#777;
    }
    .todo-list button {
      border:none;
      background:none;
      color:#e53935;
      cursor:pointer;
      font-size:0.8rem;
    }
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
          <p>Welcome, <?= htmlspecialchars($student_name) ?>! (<?= htmlspecialchars($student_class) ?>)</p>
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
                    <?php
                      $tagClass = 'notif-notice';
                      if ($n['type'] === 'assignment')  $tagClass = 'notif-assignment';
                      elseif ($n['type'] === 'fee')     $tagClass = 'notif-fee';
                      elseif ($n['type'] === 'event')   $tagClass = 'notif-event';
                      elseif ($n['type'] === 'result')  $tagClass = 'notif-result';
                      elseif ($n['type'] === 'attendance') $tagClass = 'notif-attendance';
                    ?>
                    <li>
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
          <div class="sub-text">Open tasks for your class</div>
        </div>

        <div class="card stat-card">
          <h3>Result Summary</h3>
          <?php if ($stats['avg_percentage'] !== null): ?>
            <div class="big-number"><?= $stats['avg_percentage'] ?>%</div>
            <div class="sub-text">Based on <?= $stats['total_results'] ?> graded item<?= $stats['total_results'] == 1 ? '' : 's' ?></div>
          <?php else: ?>
            <div class="big-number">—</div>
            <div class="sub-text">No grades calculated yet</div>
          <?php endif; ?>
        </div>

        <div class="card stat-card">
          <h3>Fee Status</h3>
          <div class="big-number">
            <?= $stats['fee_status'] === 'Paid'
                ? 'Rs ' . number_format($stats['total_fee_paid'], 2)
                : 'Pending' ?>
          </div>
          <div class="sub-text">
            <?= $stats['fee_status'] === 'Paid'
                ? 'Total paid so far'
                : 'No payment recorded yet' ?>
          </div>
        </div>

        <div class="card stat-card">
          <h3>Attendance</h3>
          <?php if ($stats['attendance_pct'] !== null): ?>
            <div class="big-number"><?= $stats['attendance_pct'] ?>%</div>
            <div class="sub-text">Overall attendance</div>
          <?php else: ?>
            <div class="big-number">—</div>
            <div class="sub-text">No attendance records</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- To-Do List -->
      <section class="todo-section">
        <h3><i class="fa-solid fa-list-check"></i> To-Do List</h3>
        <p style="font-size:0.85rem; color:#555; margin-bottom:6px;">
          Add tasks like “Finish Math assignment” or “Prepare for science quiz”. These are stored only on this browser.
        </p>
        <div class="todo-input-row">
          <input type="text" id="todoInput" placeholder="Add new task..." />
          <button id="addTodoBtn">Add</button>
        </div>
        <ul class="todo-list" id="todoList"></ul>
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
    // Notification dropdown
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    bell.addEventListener('click', () => {
      dropdown.classList.toggle('show');
      const expanded = bell.getAttribute('aria-expanded') === 'true';
      bell.setAttribute('aria-expanded', (!expanded).toString());
      dropdown.setAttribute('aria-hidden', expanded.toString());
    });
    document.addEventListener('click', (e) => {
      if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
        bell.setAttribute('aria-expanded', 'false');
        dropdown.setAttribute('aria-hidden', 'true');
      }
    });

    // Settings dropdown
    const settingsToggle = document.getElementById('settingsToggle');
    const settingsMenu   = document.getElementById('settingsMenu');
    settingsToggle.addEventListener('click', () => {
      settingsMenu.classList.toggle('show');
      const expanded = settingsToggle.getAttribute('aria-expanded') === 'true';
      settingsToggle.setAttribute('aria-expanded', (!expanded).toString());
      settingsMenu.setAttribute('aria-hidden', expanded.toString());
    });
    document.addEventListener('click', (e) => {
      if (!settingsToggle.contains(e.target) && !settingsMenu.contains(e.target)) {
        settingsMenu.classList.remove('show');
        settingsToggle.setAttribute('aria-expanded', 'false');
        settingsMenu.setAttribute('aria-hidden', 'true');
      }
    });

    // Dark mode toggle
    const darkToggle = document.getElementById('darkModeToggle');
    if (localStorage.getItem('studentDarkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkToggle.checked = true;
    }
    darkToggle.addEventListener('change', () => {
      document.body.classList.toggle('dark-mode');
      localStorage.setItem(
        'studentDarkMode',
        document.body.classList.contains('dark-mode') ? 'enabled' : 'disabled'
      );
    });

    // ---------------- To-Do list (localStorage only) ----------------
    const todoInput = document.getElementById('todoInput');
    const addTodoBtn= document.getElementById('addTodoBtn');
    const todoList  = document.getElementById('todoList');
    const storageKey= 'studentTodoList';

    function loadTodos() {
      const raw = localStorage.getItem(storageKey);
      let items = [];
      if (raw) {
        try { items = JSON.parse(raw) || []; } catch {}
      }
      todoList.innerHTML = '';
      items.forEach((item, index) => {
        const li = document.createElement('li');
        if (item.done) li.classList.add('completed');
        const span = document.createElement('span');
        span.textContent = item.text;
        span.addEventListener('click', () => toggleTodo(index));
        const delBtn = document.createElement('button');
        delBtn.textContent = '✕';
        delBtn.addEventListener('click', (e) => { e.stopPropagation(); deleteTodo(index); });
        li.appendChild(span);
        li.appendChild(delBtn);
        todoList.appendChild(li);
      });
    }
    function saveTodos(items) {
      localStorage.setItem(storageKey, JSON.stringify(items));
    }
    function getTodos() {
      try {
        const raw = localStorage.getItem(storageKey);
        return raw ? JSON.parse(raw) : [];
      } catch {
        return [];
      }
    }
    function addTodo(text) {
      const items = getTodos();
      items.push({ text, done:false });
      saveTodos(items);
      loadTodos();
    }
    function toggleTodo(idx) {
      const items = getTodos();
      if (!items[idx]) return;
      items[idx].done = !items[idx].done;
      saveTodos(items);
      loadTodos();
    }
    function deleteTodo(idx) {
      const items = getTodos();
      items.splice(idx,1);
      saveTodos(items);
      loadTodos();
    }

    addTodoBtn.addEventListener('click', () => {
      const text = todoInput.value.trim();
      if (!text) return;
      addTodo(text);
      todoInput.value = '';
      todoInput.focus();
    });
    todoInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addTodoBtn.click();
      }
    });

    loadTodos();
  </script>
</body>
</html>
