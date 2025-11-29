<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_id     = (int)($_SESSION['user_id'] ?? 0);
$teacher_name   = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email  = $_SESSION['teacher_email'] ?? 'teacher@example.com';
$teacher_avatar = '../../assets/img/user.jpg'; // static avatar for now

// ---------- DASHBOARD STATS ----------
$stats = [
    'total_assignments' => 0,
    'open_assignments'  => 0,
    'total_grades'      => 0,
    'total_students'    => 0,
    'attendance_avg'    => null,
];

try {
    $stmt = $conn->query("
        SELECT 
            COUNT(*) AS total_assignments,
            SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_assignments
        FROM assignments
    ");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total_assignments'] = (int)$row['total_assignments'];
        $stats['open_assignments']  = (int)$row['open_assignments'];
    }
} catch (Exception $e) {}

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM grades");
    $stats['total_grades'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT s.user_id)
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
    ");
    $stats['total_students'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    $stmt = $conn->query("
        SELECT 
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
          COUNT(*) AS total_records
        FROM attendance
    ");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['total_records'] > 0) {
            $stats['attendance_avg'] = round(
                ($row['present_days'] / $row['total_records']) * 100,
                1
            );
        }
    }
} catch (Exception $e) {}

// ---------- CLASS-WISE ATTENDANCE (for chart) ----------
$attendanceByClass = [];
try {
    $stmt = $conn->query("
        SELECT 
            s.class_name,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days,
            COUNT(*) AS total_records
        FROM attendance a
        JOIN students s ON a.student_id = s.user_id
        WHERE s.class_name IS NOT NULL AND s.class_name <> ''
        GROUP BY s.class_name
        HAVING total_records > 0
        ORDER BY s.class_name
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $present = (int)$row['present_days'];
        $total   = (int)$row['total_records'];
        if ($total > 0) {
            $attendanceByClass[] = [
                'label' => $row['class_name'],
                'value' => round(($present / $total) * 100, 1),
            ];
        }
    }
} catch (Exception $e) {}

// ---------- NOTIFICATIONS ----------
$notifications = [];
$today = date('Y-m-d');

// Upcoming assignments (next 7 days)
try {
    $stmt = $conn->query("
        SELECT title, due_date 
        FROM assignments 
        WHERE status = 'Open'
          AND due_date >= CURDATE()
          AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY due_date ASC
        LIMIT 5
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'type'    => 'info',
            'icon'    => 'fa-calendar-day',
            'message' => 'Upcoming: "' . $row['title'] . '" due on ' . date('M j', strtotime($row['due_date'])),
            'time'    => 'Within 7 days',
        ];
    }
} catch (Exception $e) {}

// Overdue assignments
try {
    $stmt = $conn->query("
        SELECT title, due_date 
        FROM assignments 
        WHERE status = 'Open'
          AND due_date < CURDATE()
        ORDER BY due_date ASC
        LIMIT 5
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'type'    => 'danger',
            'icon'    => 'fa-exclamation-circle',
            'message' => 'Overdue: "' . $row['title'] . '" was due on ' . date('M j', strtotime($row['due_date'])),
            'time'    => 'Overdue',
        ];
    }
} catch (Exception $e) {}

// Attendance completion check
try {
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT s.user_id)
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
    ");
    $totalStudents = (int)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = ?");
    $stmt->execute([$today]);
    $markedToday = (int)$stmt->fetchColumn();

    if ($totalStudents > 0 && $markedToday < $totalStudents) {
        $notifications[] = [
            'type'    => 'warning',
            'icon'    => 'fa-user-check',
            'message' => "Attendance incomplete: $markedToday / $totalStudents students marked for today.",
            'time'    => 'Today',
        ];
    }
} catch (Exception $e) {}

// Recent grades
try {
    $stmt = $conn->query("
        SELECT COUNT(*) FROM grades
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ");
    $newGrades = (int)$stmt->fetchColumn();
    if ($newGrades > 0) {
        $notifications[] = [
            'type'    => 'success',
            'icon'    => 'fa-book-open',
            'message' => "$newGrades grade entries added in the last 2 days.",
            'time'    => 'Recent',
        ];
    }
} catch (Exception $e) {}

$notifCount = count($notifications);

// ---------- UPCOMING ASSIGNMENTS ----------
$upcomingAssignments = [];
try {
    $stmt = $conn->query("
        SELECT title, due_date, class_name, status
        FROM assignments
        WHERE due_date >= CURDATE()
        ORDER BY due_date ASC
        LIMIT 4
    ");
    $upcomingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Teacher Dashboard | EduSphere</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
      font-size: 16px;
      line-height: 1.5;
      background: var(--bg-page);
      color: var(--text-main);
    }

    /* APP SHELL */
    .app-shell {
      width: 100%;
      max-width: none;
      margin: 0;
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

    .logo img {
      height: 40px;
    }

    .logo span {
      font-weight: 700;
      font-size: 1.15rem;
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

    .nav a.active i {
      color: #f59e0b;
    }

    .nav a:hover {
      background: #ffeeda;
      color: #92400e;
      transform: translateX(3px);
    }

    .nav a.logout {
      margin-top: 10px;
      color: #b91c1c;
    }

    .sidebar-teacher-card {
      margin-top: 24px;
      padding: 14px 16px;
      border-radius: 20px;
      background: radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow: var(--shadow-card);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sidebar-teacher-card img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #fff;
    }

    .sidebar-teacher-card .name {
      font-size: 0.98rem;
      font-weight: 600;
      color: #78350f;
    }

    .sidebar-teacher-card .role {
      font-size: 0.8rem;
      color: #92400e;
    }

    /* MAIN */
    .main {
      padding: 24px 44px 36px;
      background: radial-gradient(circle at top left, #fff7e6 0, #ffffff 55%);
      display: block;
    }

    .main-inner {
      max-width: 1320px;
      margin: 0 auto;
    }

    /* HEADER (full width) */
    .main-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .main-header-left h2 {
      margin: 0;
      font-size: 1.85rem;
      font-weight: 700;
      color: var(--text-main);
    }

    .main-header-left p {
      margin: 4px 0 0;
      color: var(--text-muted);
      font-size: 1rem;
    }

    .main-header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .search-box {
      display: flex;
      align-items: center;
      gap: 9px;
      background: #f9fafb;
      border-radius: 999px;
      padding: 8px 14px;
      border: 1px solid #e5e7eb;
      min-width: 260px;
      box-shadow: 0 8px 18px rgba(148, 163, 184, 0.15);
    }

    .search-box i {
      color: #9ca3af;
      font-size: 0.95rem;
    }

    .search-box input {
      border: none;
      outline: none;
      background: transparent;
      font-size: 0.95rem;
      flex: 1;
      color: var(--text-main);
    }

    .icon-btn {
      position: relative;
      border: none;
      background: #fdfaf5;
      border-radius: 999px;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: 1px solid #e5e7eb;
      transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
    }

    .icon-btn i {
      color: #6b7280;
      font-size: 1rem;
    }

    .icon-btn .badge {
      position: absolute;
      top: 4px;
      right: 4px;
      background: #ef4444;
      color: #fff;
      font-size: 0.7rem;
      padding: 2px 5px;
      border-radius: 999px;
      font-weight: 600;
    }

    .icon-btn:hover {
      background: var(--accent-soft);
      box-shadow: 0 12px 26px rgba(245, 158, 11, 0.45);
      transform: translateY(-1px);
    }

    /* PROFILE DROPDOWN */
    .profile-wrapper {
      position: relative;
    }

    .header-avatar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 14px;
      border-radius: 999px;
      background: #fff7ea;
      border: 1px solid #fed7aa;
      cursor: pointer;
      min-width: 180px;
      transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
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

    .header-avatar:hover {
      background: #ffe9c7;
      box-shadow: 0 12px 26px rgba(245, 158, 11, 0.45);
      transform: translateY(-1px);
    }

    .profile-dropdown {
      position: absolute;
      right: 0;
      top: 50px;
      width: 270px;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 18px 44px rgba(148, 119, 73, 0.3);
      padding: 10px 10px 8px;
      border: 1px solid var(--border-soft);
      z-index: 20;
      display: none;
    }

    .profile-dropdown.active { display: block; }

    .profile-summary {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 6px 10px;
      border-bottom: 1px solid #f3e5d7;
      margin-bottom: 6px;
    }

    .profile-summary img {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      object-fit: cover;
    }

    .profile-summary .name {
      font-size: 0.98rem;
      font-weight: 600;
      color: #78350f;
    }

    .profile-summary .email {
      font-size: 0.8rem;
      color: #9ca3af;
    }

    .profile-dropdown a {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 6px;
      border-radius: 10px;
      font-size: 0.9rem;
      color: #6b7280;
      text-decoration: none;
    }

    .profile-dropdown a i {
      width: 18px;
      text-align: center;
    }

    .profile-dropdown a:hover {
      background: #fff5e6;
      color: #92400e;
    }

    /* NOTIF DROPDOWN */
    .notif-wrapper { position: relative; }

    .notif-dropdown {
      position: absolute;
      right: 0;
      top: 48px;
      width: 290px;
      max-height: 340px;
      overflow: auto;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 16px 38px rgba(15, 23, 42, 0.35);
      padding: 10px 10px 8px;
      display: none;
      z-index: 15;
      border: 1px solid var(--border-soft);
    }

    .notif-dropdown.active { display: block; }

    .notif-dropdown h4 {
      margin: 2px 4px 8px;
      font-size: 1rem;
      color: var(--text-main);
    }

    .notif-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .notif-list li {
      display: flex;
      gap: 10px;
      padding: 7px 6px;
      border-radius: 12px;
      font-size: 0.9rem;
      align-items: flex-start;
    }

    .notif-list li + li { margin-top: 4px; }

    .notif-info .icon    { background: #eff6ff; color: #1d4ed8; }
    .notif-warning .icon { background: #fef3c7; color: #b45309; }
    .notif-danger .icon  { background: #fee2e2; color: #b91c1c; }
    .notif-success .icon { background: #dcfce7; color: #15803d; }

    .notif-list .icon {
      width: 24px;
      height: 24px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
    }

    .notif-text { flex: 1; }
    .notif-text .msg  { margin: 0 0 2px; color: var(--text-main); }
    .notif-text .time { font-size: 0.78rem; color: #9ca3af; }
    .notif-empty {
      padding: 10px 4px;
      font-size: 0.9rem;
      color: #9ca3af;
    }

    /* CONTENT GRID (LEFT + RIGHT COLUMNS) */
    .content-grid {
      margin-top: 14px;
      display: grid;
      grid-template-columns: minmax(0, 3.1fr) minmax(280px, 1.3fr);
      gap: 22px;
      align-items: flex-start;
    }

    .left-column {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .right-column {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    /* Cards general hover */
    .hero-card,
    .stat-card,
    .panel {
      transition: transform 0.16s ease-out, box-shadow 0.16s ease-out;
    }

    .hero-card:hover,
    .stat-card:hover,
    .panel:hover {
      transform: translateY(-3px);
      box-shadow: 0 18px 40px rgba(15,23,42,0.12);
    }

    /* HERO + CLIPART AREA */
    .hero-card {
      background: radial-gradient(circle at top right, #ffe6b0, #fff7ea);
      border-radius: 22px;
      padding: 26px 28px;
      box-shadow: var(--shadow-card);
      display: flex;
      justify-content: space-between;
      gap: 18px;
    }

    .hero-left h3 {
      margin: 0 0 6px;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--text-main);
    }

    .hero-left p {
      margin: 0 0 12px;
      font-size: 0.98rem;
      color: var(--text-muted);
    }

    .hero-metric {
      display: flex;
      align-items: flex-end;
      gap: 6px;
      margin-bottom: 10px;
      font-size: 0.98rem;
      color: var(--text-muted);
    }

    .hero-metric span.big {
      font-size: 2.1rem;
      font-weight: 700;
      color: #b45309;
    }

    .hero-btn {
      border: none;
      border-radius: 999px;
      background: var(--accent);
      color: #fff;
      padding: 10px 20px;
      font-size: 0.98rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 12px 28px rgba(180, 83, 9, 0.6);
    }

    .hero-btn:hover { filter: brightness(1.04); }

    .hero-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: space-between;
      gap: 10px;
      min-width: 170px;
    }

    /* fun “clipart style” */
    .hero-graphic {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }

    .hero-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: radial-gradient(circle at 30% 20%, #ffe9c7, #fbbf24);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 12px 26px rgba(180, 83, 9, 0.4);
    }

    .hero-circle i {
      font-size: 32px;
      color: #7c2d12;
    }

    .hero-graphic span {
      font-size: 0.8rem;
      color: #92400e;
      font-weight: 500;
    }

    .hero-pill {
      background: #fff;
      border-radius: 16px;
      padding: 11px 16px;
      font-size: 0.9rem;
      box-shadow: var(--shadow-card);
      color: var(--text-muted);
      border: 1px solid var(--border-soft);
      min-width: 155px;
      text-align: right;
    }

    .hero-pill strong { color: var(--text-main); }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
    }

    .stat-card {
      background: var(--bg-main);
      border-radius: 16px;
      padding: 16px 18px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
    }

    .stat-label {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #a16207;
      margin-bottom: 6px;
    }

    .stat-value {
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 2px;
      color: var(--text-main);
    }

    .stat-sub {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .panel {
      background: var(--bg-main);
      border-radius: 18px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
      padding: 18px 20px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .panel-header h4 {
      margin: 0;
      font-size: 1.05rem;
      color: var(--text-main);
    }

    .panel-header span {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    #attendanceChart {
      width: 100%;
      max-height: 250px;
    }

    .insights-list {
      list-style: none;
      margin: 0;
      padding: 0;
      font-size: 0.95rem;
      color: var(--text-muted);
    }

    .insights-list li {
      display: flex;
      align-items: flex-start;
      gap: 7px;
      padding: 5px 0;
    }

    .insights-dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      margin-top: 6px;
      background: var(--accent);
    }

    /* RIGHT COLUMN CONTENT */
    .upcoming-list {
      list-style: none;
      margin: 0;
      padding: 0;
      font-size: 0.92rem;
    }

    .upcoming-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 9px 0;
      border-bottom: 1px solid var(--border-soft);
    }

    .upcoming-item:last-child { border-bottom: none; }

    .up-title {
      font-weight: 600;
      margin-bottom: 3px;
      font-size: 0.96rem;
      color: var(--text-main);
    }

    .up-meta {
      font-size: 0.82rem;
      color: #9ca3af;
    }

    .up-tag {
      font-size: 0.8rem;
      padding: 3px 9px;
      border-radius: 999px;
      background: #fff7ea;
      color: #92400e;
      margin-bottom: 4px;
    }

    .up-time {
      font-size: 0.85rem;
      color: #6b7280;
      text-align: right;
    }

    .alerts-list {
      list-style: none;
      margin: 0;
      padding: 0;
      font-size: 0.9rem;
    }

    .alerts-list li {
      display: flex;
      gap: 9px;
      padding: 7px 0;
    }

    .alerts-badge {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      font-size: 0.78rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fee2e2;
      color: #b91c1c;
    }

    .alerts-text { color: var(--text-main); }

    .alerts-text small {
      display: block;
      font-size: 0.8rem;
      color: #9ca3af;
    }

    /* RESPONSIVE */
    @media (max-width: 1100px) {
      .app-shell { grid-template-columns: 230px 1fr; }
      .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .content-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 800px) {
      .app-shell { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main { padding: 18px; }
      .content-grid { grid-template-columns: 1fr; }
      .main-header { flex-direction: column; align-items: flex-start; gap: 10px; }
      .main-header-right { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
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
        </div>
        <nav class="nav">
          <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
          <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Assignments</a>
          <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
          <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
          <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="sidebar-teacher-card">
          <img src="<?= htmlspecialchars($teacher_avatar) ?>" alt="Teacher" />
          <div>
            <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
            <div class="role">Teacher · EduSphere</div>
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
            <h2>My Class Dashboard</h2>
            <p>Good to see you, <?= htmlspecialchars($teacher_name) ?> 👋</p>
          </div>
          <div class="main-header-right">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input type="text" placeholder="Search students or assignments..." />
            </div>

            <div class="notif-wrapper">
              <button class="icon-btn" id="notifToggle" type="button">
                <i class="fas fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                  <span class="badge"><?= $notifCount ?></span>
                <?php endif; ?>
              </button>
              <div class="notif-dropdown" id="notifDropdown">
                <h4>Notifications</h4>
                <?php if ($notifCount === 0): ?>
                  <div class="notif-empty">You're all caught up. No pending items.</div>
                <?php else: ?>
                  <ul class="notif-list">
                    <?php foreach ($notifications as $n): 
                      $class = 'notif-' . $n['type'];
                    ?>
                      <li class="<?= $class ?>">
                        <div class="icon"><i class="fas <?= htmlspecialchars($n['icon']) ?>"></i></div>
                        <div class="notif-text">
                          <p class="msg"><?= htmlspecialchars($n['message']) ?></p>
                          <span class="time"><?= htmlspecialchars($n['time']) ?></span>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>

            <div class="profile-wrapper">
              <button class="header-avatar" id="profileToggle" type="button">
                <img src="<?= htmlspecialchars($teacher_avatar) ?>" alt="Teacher" />
                <div>
                  <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
                  <div class="role">Teacher</div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:0.7rem;margin-left:4px;"></i>
              </button>
              <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-summary">
                  <img src="<?= htmlspecialchars($teacher_avatar) ?>" alt="Teacher" />
                  <div>
                    <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
                    <div class="email"><?= htmlspecialchars($teacher_email) ?></div>
                  </div>
                </div>
                <a href="profile.php"><i class="fas fa-user"></i> View / Edit Profile</a>
                <a href="change-password.php"><i class="fas fa-key"></i> Change Password</a>
                <a href="contact-settings.php"><i class="fas fa-phone"></i> Update Contact Details</a>
                <a href="notification-settings.php"><i class="fas fa-bell"></i> Notification Settings</a>
                <a href="/edusphere/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
              </div>
            </div>
          </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">
          <!-- LEFT COLUMN -->
          <section class="left-column">
            <div class="hero-card">
              <div class="hero-left">
                <h3>Today’s Teaching Overview</h3>
                <p>Your assignments, grades and attendance all in one place.</p>
                <div class="hero-metric">
                  <span class="big"><?= $stats['open_assignments'] ?></span>
                  <span>open assignments to review</span>
                </div>
                <button class="hero-btn" onclick="window.location.href='manage-assignments.php'">View Assignments</button>
              </div>
              <div class="hero-right">
  <div class="hero-graphic">
    <div class="hero-circle">
      <i class="fas fa-chalkboard-teacher"></i>
    </div>
    <span>Happy teaching, <?= htmlspecialchars($teacher_name) ?>!</span>
  </div>

  <div class="hero-pill">
    <strong><?= $stats['total_students'] ?></strong> students<br/>
    Attendance: <strong><?= $stats['attendance_avg'] !== null ? $stats['attendance_avg'].'%' : '—' ?></strong>
  </div>
</div>

              </div>
            </div>

            <div class="stats-row">
              <div class="stat-card">
                <div class="stat-label">Assignments</div>
                <div class="stat-value"><?= $stats['total_assignments'] ?></div>
                <div class="stat-sub">Open: <?= $stats['open_assignments'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Grades</div>
                <div class="stat-value"><?= $stats['total_grades'] ?></div>
                <div class="stat-sub">Entries in grade book</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Students</div>
                <div class="stat-value"><?= $stats['total_students'] ?></div>
                <div class="stat-sub">Total in the system</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Attendance</div>
                <div class="stat-value">
                  <?= $stats['attendance_avg'] !== null ? $stats['attendance_avg'].'%' : '—' ?>
                </div>
                <div class="stat-sub">Overall present ratio</div>
              </div>
            </div>

            <div class="panel">
              <div class="panel-header">
                <h4>Class-wise Attendance %</h4>
                <span>Across all classes</span>
              </div>
              <?php if (!empty($attendanceByClass)): ?>
                <canvas id="attendanceChart"></canvas>
              <?php else: ?>
                <p style="font-size:0.88rem;color:var(--text-muted);">
                  No attendance data available yet. Once you start marking attendance, you'll see analytics here.
                </p>
              <?php endif; ?>
            </div>

            <div class="panel">
              <div class="panel-header">
                <h4>Teaching Insights</h4>
              </div>
              <ul class="insights-list">
                <li><span class="insights-dot"></span>Record grades after each class to keep performance up to date.</li>
                <li><span class="insights-dot"></span>Use attendance trends to identify students needing extra support.</li>
                <li><span class="insights-dot"></span>Spread assignment due dates to avoid student overload.</li>
              </ul>
            </div>
          </section>

          <!-- RIGHT COLUMN -->
          <aside class="right-column">
            <div class="panel">
              <div class="panel-header">
                <h4>Upcoming Tasks</h4>
              </div>
              <?php if (empty($upcomingAssignments)): ?>
                <p style="font-size:0.85rem;color:var(--text-muted);">No upcoming assignments scheduled yet.</p>
              <?php else: ?>
                <ul class="upcoming-list">
                  <?php foreach ($upcomingAssignments as $a): ?>
                    <li class="upcoming-item">
                      <div>
                        <div class="up-title"><?= htmlspecialchars($a['title']) ?></div>
                        <?php if (!empty($a['class_name'])): ?>
                          <div class="up-tag"><?= htmlspecialchars($a['class_name']) ?></div>
                        <?php endif; ?>
                        <div class="up-meta">Status: <?= htmlspecialchars($a['status']) ?></div>
                      </div>
                      <div class="up-time">
                        <?= date('M j', strtotime($a['due_date'])) ?><br/>
                        <?= date('D', strtotime($a['due_date'])) ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <div class="panel">
              <div class="panel-header">
                <h4>Quick Alerts</h4>
              </div>
              <?php if ($notifCount === 0): ?>
                <p style="font-size:0.83rem;color:var(--text-muted);">No alerts for now. Enjoy your day!</p>
              <?php else: ?>
                <ul class="alerts-list">
                  <?php foreach (array_slice($notifications, 0, 3) as $n): ?>
                    <li>
                      <div class="alerts-badge"><i class="fas fa-bell"></i></div>
                      <div class="alerts-text">
                        <?= htmlspecialchars($n['message']) ?>
                        <small><?= htmlspecialchars($n['time']) ?></small>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </aside>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Notification dropdown
    (function() {
      const toggle   = document.getElementById('notifToggle');
      const dropdown = document.getElementById('notifDropdown');
      if (!toggle || !dropdown) return;

      toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('active');
      });

      document.addEventListener('click', function() {
        dropdown.classList.remove('active');
      });

      dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    })();

    // Profile dropdown
    (function() {
      const toggle   = document.getElementById('profileToggle');
      const dropdown = document.getElementById('profileDropdown');
      if (!toggle || !dropdown) return;

      toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('active');
      });

      document.addEventListener('click', function() {
        dropdown.classList.remove('active');
      });

      dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    })();

    // Attendance chart
    (function() {
      const data = <?=
        json_encode($attendanceByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      ?>;
      if (!data || !data.length) return;
      const canvas = document.getElementById('attendanceChart');
      if (!canvas) return;

      const labels = data.map(item => item.label);
      const values = data.map(item => item.value);

      new Chart(canvas, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Attendance %',
            data: values,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  return ctx.parsed.y + '%';
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
              ticks: {
                stepSize: 10,
                callback: value => value + '%'
              }
            }
          }
        }
      });
    })();
  </script>
</body>
</html>
