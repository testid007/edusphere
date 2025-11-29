<?php
// dashboards/student/events.php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

$student_name  = $_SESSION['student_name']  ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';
$student_class = $_SESSION['class'] ?? 'Unknown';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager   = new EventManager($conn);
$userId         = (int)$_SESSION['user_id'];

// Upcoming and past events for this user
$upcomingEvents = $eventManager->getUpcomingEventsForUser($userId, 100);
$pastEvents     = $eventManager->getPastEventsForUser($userId, 100);

// Simple stats for header cards
$upcomingCount          = count($upcomingEvents);
$markedInterestedCount  = 0;
$participationHistory   = 0;

foreach ($upcomingEvents as $e) {
    if (in_array($e['participation_status'], ['interested', 'registered', 'participated'], true)) {
        $markedInterestedCount++;
    }
}
foreach ($pastEvents as $e) {
    if (in_array($e['participation_status'], ['registered', 'participated'], true)) {
        $participationHistory++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Events | EduSphere</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    :root {
      --bg-page: #f5eee9;
      --bg-shell: #fdfcfb;
      --bg-sidebar: #fdf5ec;
      --bg-main: #ffffff;

      --accent: #f59e0b;
      --accent-soft: #fff5e5;

      --text-main: #111827;
      --text-muted: #6b7280;

      --border-soft: #f3e5d7;
      --shadow-card: 0 14px 34px rgba(15,23,42,0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
    }

    /* APP SHELL */
    .app-shell {
      width: 100%;
      display: grid;
      grid-template-columns: 260px 1fr;
      min-height: 100vh;
      background: var(--bg-shell);
    }

    /* SIDEBAR */
    .sidebar {
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border-soft);
      padding: 28px 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 28px;
    }

    .logo img { height: 40px; }

    .logo span {
      font-weight: 700;
      font-size: 1.1rem;
      color: #1f2937;
      letter-spacing: 0.04em;
    }

    .nav {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 11px 14px;
      border-radius: 999px;
      color: #6b7280;
      font-size: 0.95rem;
      text-decoration: none;
      transition: background 0.15s ease-out, color 0.15s ease-out, transform 0.15s ease-out,
                  box-shadow 0.15s ease-out;
    }

    .nav a i {
      width: 20px;
      text-align: center;
      color: #9ca3af;
      font-size: 0.95rem;
    }

    .nav a.active {
      background: var(--accent-soft);
      color: #92400e;
      font-weight: 600;
      box-shadow: 0 10px 22px rgba(245, 158, 11, 0.35);
    }

    .nav a.active i { color: #f59e0b; }

    .nav a:hover {
      background: #ffeeda;
      color: #92400e;
      transform: translateX(3px);
    }

    .nav a.logout {
      margin-top: 10px;
      color: #b91c1c;
    }

    .sidebar-student-card {
      margin-top: 24px;
      padding: 14px 16px;
      border-radius: 20px;
      background: radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow: var(--shadow-card);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sidebar-student-card img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #fff;
    }

    .sidebar-student-card .name {
      font-size: 0.98rem;
      font-weight: 600;
      color: #78350f;
    }

    .sidebar-student-card .role {
      font-size: 0.8rem;
      color: #92400e;
    }

    /* MAIN */
    .main {
      padding: 24px 44px 36px;
      background: radial-gradient(circle at top left, #fff7e6 0, #ffffff 55%);
    }

    .main-inner { max-width: 1320px; margin: 0 auto; }

    .main-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }

    .main-header-left h2 {
      margin: 0;
      font-size: 1.8rem;
      font-weight: 700;
    }

    .main-header-left p {
      margin: 4px 0 0;
      color: var(--text-muted);
      font-size: 0.95rem;
    }

    .header-avatar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 14px;
      border-radius: 999px;
      background: #fff7ea;
      border: 1px solid #fed7aa;
      min-width: 190px;
    }

    .header-avatar img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
    }

    .header-avatar .name {
      font-size: 0.95rem;
      font-weight: 600;
      color: #78350f;
    }

    .header-avatar .role {
      font-size: 0.8rem;
      color: #c05621;
    }

    /* TOP STATS CARDS */
    .stats-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 18px;
    }

    .stat-card {
      background: var(--bg-main);
      border-radius: 16px;
      padding: 14px 16px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
    }

    .stat-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #a16207;
      margin-bottom: 4px;
    }

    .stat-value {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .stat-sub {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    /* EVENTS LAYOUT (upcoming + past) */
    .events-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.8fr) minmax(0, 1.2fr);
      gap: 22px;
      align-items: flex-start;
    }

    .panel {
      background: var(--bg-main);
      border-radius: 18px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
      padding: 16px 18px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 8px;
    }

    .panel-header h3 {
      margin: 0;
      font-size: 1rem;
    }

    .panel-header span {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .panel-note {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 6px;
    }

    /* TABLE – LESS CONGESTED CARD STYLE */
    .events-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 6px; /* space between rows */
      font-size: 0.88rem;
      margin-top: 6px;
    }

    .events-table thead th {
      background: #fff7ea;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-size: 0.8rem;
      color: #92400e;
      padding: 10px 12px;
      border: none;
      text-align: left;
    }

    .events-table tbody tr {
      background: #ffffff;
      box-shadow: 0 6px 16px rgba(15,23,42,0.06);
      border-radius: 12px;
    }

    .events-table tbody td {
      padding: 10px 12px;
      border-top: 1px solid #f5f5f5;
      border-bottom: 1px solid #f5f5f5;
      vertical-align: middle;
    }

    .events-table tbody tr td:first-child {
      border-top-left-radius: 12px;
      border-bottom-left-radius: 12px;
    }

    .events-table tbody tr td:last-child {
      border-top-right-radius: 12px;
      border-bottom-right-radius: 12px;
    }

    .events-empty {
      font-size: 0.86rem;
      color: var(--text-muted);
      padding: 8px 2px;
    }

    .category-pill {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 999px;
      background: #eff6ff;
      color: #1d4ed8;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .status-pill {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .status-interested { background:#dcfce7; color:#15803d; }
    .status-registered { background:#e0f2fe; color:#1d4ed8; }
    .status-participated { background:#ede9fe; color:#6d28d9; }
    .status-not-interested { background:#fee2e2; color:#b91c1c; }
    .status-missed { background:#fef9c3; color:#92400e; }

    .btn {
      padding: 6px 12px;
      border: none;
      border-radius: 999px;
      cursor: pointer;
      font-size: 0.8rem;
      margin-right: 4px;
    }

    .btn-interest {
      background: #22c55e;
      color: #fff;
    }

    .btn-not-interest {
      background: #ef4444;
      color: #fff;
    }

    .btn:hover {
      filter: brightness(1.03);
    }

    @media (max-width: 1100px) {
      .app-shell { grid-template-columns: 220px 1fr; }
      .events-layout { grid-template-columns: 1fr; }
      .stats-strip { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }

    @media (max-width: 800px) {
      .app-shell { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main { padding: 18px; }
      .stats-strip { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="EduSphere Logo" />
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="assignments.php"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fees</a>
        <a href="events.php" class="active"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="sidebar-student-card">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div>
          <div class="name"><?= htmlspecialchars($student_name) ?></div>
          <div class="role">Class <?= htmlspecialchars($student_class) ?> · EduSphere</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <!-- HEADER -->
      <div class="main-header">
        <div class="main-header-left">
          <h2>School Events</h2>
          <p>Mark events as interested to get better recommendations.</p>
        </div>
        <div class="main-header-right">
          <div class="header-avatar">
            <img src="../../assets/img/user.jpg" alt="Student" />
            <div>
              <div class="name"><?= htmlspecialchars($student_name) ?></div>
              <div class="role"><?= htmlspecialchars($student_email) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- TOP STATS -->
      <div class="stats-strip">
        <div class="stat-card">
          <div class="stat-label">Upcoming</div>
          <div class="stat-value"><?= $upcomingCount ?></div>
          <div class="stat-sub">Events scheduled for you</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Marked Interested</div>
          <div class="stat-value"><?= $markedInterestedCount ?></div>
          <div class="stat-sub">You plan to attend</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Participation History</div>
          <div class="stat-value"><?= $participationHistory ?></div>
          <div class="stat-sub">Events you already joined</div>
        </div>
      </div>

      <!-- EVENTS GRID -->
      <div class="events-layout">
        <!-- UPCOMING -->
        <section class="panel">
          <div class="panel-header">
            <h3>Upcoming Events</h3>
            <span>Choose your interests</span>
          </div>
          <p class="panel-note">
            Tip: Mark one event as <strong>Interested</strong> and similar category events are automatically marked interested.
          </p>

          <?php if (empty($upcomingEvents)): ?>
            <div class="events-empty">No upcoming events right now.</div>
          <?php else: ?>
            <table class="events-table">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Location</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($upcomingEvents as $e): ?>
                <?php $status = $e['participation_status']; ?>
                <tr>
                  <td><?= htmlspecialchars($e['title']) ?></td>
                  <td>
                    <span class="category-pill">
                      <?= htmlspecialchars($e['category_name']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($e['event_date']) ?></td>
                  <td><?= htmlspecialchars(($e['start_time'] ?? '') . ' ' . ($e['end_time'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($e['location'] ?? '') ?></td>
                  <td>
                    <?php if ($status === null): ?>
                      <button
                        class="btn btn-interest"
                        data-event-id="<?= (int)$e['id'] ?>"
                        data-status="interested">
                        Interested
                      </button>
                      <button
                        class="btn btn-not-interest"
                        data-event-id="<?= (int)$e['id'] ?>"
                        data-status="not_interested">
                        Not Interested
                      </button>
                    <?php else: ?>
                      <?php
                        $label = ucwords(str_replace('_', ' ', $status));
                        $class = 'status-pill ';
                        if ($status === 'interested')   $class .= 'status-interested';
                        elseif ($status === 'registered')   $class .= 'status-registered';
                        elseif ($status === 'participated') $class .= 'status-participated';
                        elseif ($status === 'not_interested') $class .= 'status-not-interested';
                      ?>
                      <span class="<?= $class ?>"><?= htmlspecialchars($label) ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <!-- PAST & HISTORY -->
        <aside class="panel">
          <div class="panel-header">
            <h3>Past & History</h3>
            <span>Events you missed or attended</span>
          </div>

          <?php if (empty($pastEvents)): ?>
            <div class="events-empty">No past or uninterested events recorded.</div>
          <?php else: ?>
            <table class="events-table">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($pastEvents as $e): ?>
                <?php
                  $status = $e['participation_status'];
                  if ($status === null) {
                      $statusLabel = 'Missed';
                      $statusClass = 'status-missed';
                  } elseif ($status === 'not_interested') {
                      $statusLabel = 'Not Interested';
                      $statusClass = 'status-not-interested';
                  } else {
                      $statusLabel = ucwords(str_replace('_', ' ', $status));
                      if (in_array($status, ['registered','participated'], true)) {
                          $statusClass = 'status-participated';
                      } else {
                          $statusClass = 'status-interested';
                      }
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($e['title']) ?></td>
                  <td><?= htmlspecialchars($e['event_date']) ?></td>
                  <td>
                    <span class="status-pill <?= $statusClass ?>">
                      <?= htmlspecialchars($statusLabel) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <p class="panel-note">
            Tip: Joining at least one event every month helps you build confidence, teamwork and can be useful for future scholarship or college applications.
          </p>
        </aside>
      </div>
    </div>
  </main>
</div>

<script>
  // Handle Interested / Not Interested buttons
  document.querySelectorAll('.btn[data-status]').forEach(btn => {
    btn.addEventListener('click', function () {
      const eventId = this.dataset.eventId;
      const status  = this.dataset.status;

      const msg = status === 'interested'
        ? 'Mark this event as INTERESTED?'
        : 'Mark this event as NOT INTERESTED?';

      if (!confirm(msg)) return;

      fetch('../../api/mark_event_participation.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          event_id: eventId,
          status: status
        })
      })
      .then(response => response.json())
      .then(data => {
        alert(data.message);
        if (data.success) {
          location.reload();
        }
      })
      .catch(err => {
        console.error(err);
        alert('Something went wrong. Please try again.');
      });
    });
  });
</script>
</body>
</html>
