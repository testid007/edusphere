<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    header('Location: /edusphere/auth/login.php');
    exit;
}

$parent_id    = (int) $_SESSION['user_id'];
$parent_name  = $_SESSION['parent_name']  ?? 'Parent';
$parent_email = $_SESSION['parent_email'] ?? 'parent@example.com';

$parent_avatar = '../../assets/img/user.jpg';

// try to get linked child
$child_name        = null;
$child_class       = null;
$child_student_id  = null;

// risk flags for child
$is_child_at_risk  = false;
$risk_reasons      = [];
$attendance_pct    = null;
$low_score_count   = 0;

try {
    $stmt = $conn->prepare("
        SELECT s.user_id AS student_id,
               s.class,
               CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM parents p
        JOIN students s ON p.student_id = s.user_id
        JOIN users u ON s.user_id = u.id
        WHERE p.user_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $child_name        = $row['full_name'];
        $child_class       = $row['class'];
        $child_student_id  = (int)$row['student_id'];
        $_SESSION['child_student_id'] = $child_student_id;
    }
} catch (Exception $e) {
    // silent; sub-pages handle
}

// --- SIMPLE CHILD RISK CALCULATION (attendance + grades) ---
if ($child_student_id) {
    try {
        // Attendance percentage
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present,
                COUNT(*) AS total
            FROM attendance
            WHERE student_id = :sid
        ");
        $stmt->execute([':sid' => $child_student_id]);
        $att = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($att && $att['total'] > 0) {
            $attendance_pct = round(($att['present'] / $att['total']) * 100, 1);
        } else {
            $attendance_pct = null;
        }

        // Low grade / low score count
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM grades 
            WHERE student_id = :sid 
              AND (grade = 'F' OR score < 40)
        ");
        $stmt->execute([':sid' => $child_student_id]);
        $low_score_count = (int)$stmt->fetchColumn();

        // Thresholds (you can tweak):
        // - attendance < 75%
        // - any very low scores
        if (($attendance_pct !== null && $attendance_pct < 75) || $low_score_count > 0) {
            $is_child_at_risk = true;

            if ($attendance_pct !== null && $attendance_pct < 75) {
                $risk_reasons[] = "Low attendance (" . $attendance_pct . "%)";
            }
            if ($low_score_count > 0) {
                $risk_reasons[] = $low_score_count . " low performance record" . ($low_score_count > 1 ? "s" : "");
            }
        }
    } catch (Exception $e) {
        // If anything fails, just don't break the dashboard
        $is_child_at_risk = false;
        $risk_reasons     = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Parent Dashboard | EduSphere</title>
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
      --shadow-card: 0 12px 30px rgba(15,23,42,0.06);
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:var(--bg-page);
      color:var(--text-main);
    }

    /* APP SHELL (same style family as student dashboard) */
    .app-shell {
      width:100%;
      display:grid;
      grid-template-columns:250px 1fr;
      min-height:100vh;
      background:var(--bg-shell);
    }
    .sidebar {
      background:var(--bg-sidebar);
      border-right:1px solid var(--border-soft);
      padding:24px 20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo {
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:24px;
    }
    .logo img { height:36px; }
    .logo span {
      font-weight:700;
      font-size:1.05rem;
      color:#1f2937;
      letter-spacing:0.04em;
    }

    .nav { display:flex;flex-direction:column;gap:6px; }
    .nav a {
      display:flex;
      align-items:center;
      gap:10px;
      padding:9px 12px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.9rem;
      text-decoration:none;
      transition:background .15s ease-out,color .15s ease-out,transform .15s ease-out;
    }
    .nav a i { width:18px;text-align:center;color:#9ca3af; }
    .nav a.active {
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
      box-shadow:0 10px 22px rgba(245,158,11,.35);
    }
    .nav a.active i { color:#f59e0b; }
    .nav a:hover {
      background:#ffeeda;
      color:#92400e;
      transform:translateX(2px);
    }
    .nav a.logout { margin-top:8px;color:#b91c1c; }

    .sidebar-parent-card {
      margin-top:20px;
      padding:12px 14px;
      border-radius:18px;
      background:linear-gradient(135deg,#e0f2fe,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:10px;
    }
    .sidebar-parent-card img {
      width:40px;
      height:40px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-parent-card .name {
      font-size:0.9rem;
      font-weight:600;
      color:#0f172a;
    }
    .sidebar-parent-card .email {
      font-size:0.78rem;
      color:#6b7280;
    }
    .sidebar-parent-card .child {
      font-size:0.78rem;
      color:#2563eb;
      margin-top:2px;
    }

    .main {
      padding:20px 40px 32px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner {
      max-width:1260px;
      margin:0 auto;
    }

    /* HEADER (match student style) */
    .main-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:16px;
    }
    .main-header-left h2 {
      margin:0;
      font-size:1.6rem;
    }
    .main-header-left p {
      margin:4px 0 0;
      color:var(--text-muted);
      font-size:0.9rem;
    }
    .main-header-right {
      display:flex;
      align-items:center;
      gap:10px;
    }

    .header-avatar {
      display:flex;
      align-items:center;
      gap:8px;
      padding:4px 10px;
      border-radius:999px;
      background:#e0f2fe;
      border:1px solid #bfdbfe;
      min-width:190px;
    }
    .header-avatar img {
      width:28px;
      height:28px;
      border-radius:50%;
      object-fit:cover;
    }
    .header-avatar .name {
      font-size:0.85rem;
      font-weight:600;
      color:#1d4ed8;
    }
    .header-avatar .role {
      font-size:0.75rem;
      color:#2563eb;
    }

    .icon-btn {
      position:relative;
      border:none;
      background:#f9fafb;
      border-radius:999px;
      width:36px;
      height:36px;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      border:1px solid #e5e7eb;
      transition:background .15s,transform .15s,box-shadow .15s;
    }
    .icon-btn i { color:#6b7280;font-size:0.95rem; }
    .icon-btn:hover {
      background:var(--accent-soft);
      box-shadow:0 10px 24px rgba(245,158,11,0.35);
      transform:translateY(-1px);
    }

    /* NOTIFICATION / NOTICE DROPDOWN */
.notif-wrapper {
  position: relative;
  display: inline-block;
}

.notif-dropdown {
  position: absolute;
  right: 0;
  top: 48px; /* fixed spacing under bell */
  width: 280px;
  max-height: 320px;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid var(--border-soft);
  box-shadow: 0 16px 32px rgba(0, 0, 0, 0.15);
  padding: 12px 14px;
  display: none;
  overflow-y: auto;
  z-index: 50;
  animation: fadeDown 0.18s ease-out;
}

.notif-dropdown.active {
  display: block;
}

.notif-dropdown h4 {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 600;
  color: #78350f;
  border-bottom: 1px dashed var(--border-soft);
  padding-bottom: 6px;
}

.notif-dropdown ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

.notif-dropdown li {
  padding: 8px 6px;
  font-size: 0.84rem;
  color: #334155;
  border-radius: 8px;
  transition: background 0.12s, color 0.12s;
}

.notif-dropdown li:hover {
  background: var(--accent-soft);
  color: #92400e;
  cursor: pointer;
}

/* empty state style */
.notif-empty {
  font-size: 0.82rem;
  color: #9ca3af;
  text-align: center;
  padding: 10px;
}

/* optional smooth opening animation */
@keyframes fadeDown {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* for overall fade appearance */
@keyframes fadeDown {
  from { opacity: 0; transform: translateY(-5px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* dropdown opening pop effect */
@keyframes fadeDown {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Custom scrollbar for overflow */
.notif-dropdown::-webkit-scrollbar {
  width: 6px;
}

.notif-dropdown::-webkit-scrollbar-thumb {
  background: var(--accent);
  border-radius: 999px;
}

.notif-dropdown::-webkit-scrollbar-track {
  background: #fff7e6;
}


    /* SETTINGS DROPDOWN (dark mode / language) */
    .settings-dropdown {
      position:absolute;
      right:0;
      top:40px;
      width:220px;
      background:#ffffff;
      border-radius:14px;
      box-shadow:0 14px 35px rgba(15,23,42,0.25);
      padding:8px 10px;
      border:1px solid var(--border-soft);
      display:none;
      z-index:20;
      font-size:0.85rem;
    }
    .settings-dropdown.show { display:block; }
    .settings-dropdown .dropdown-item {
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:6px 4px;
    }
    .settings-dropdown select {
      font-size:0.82rem;
      border-radius:999px;
      border:1px solid #e5e7eb;
      padding:3px 6px;
      background:#f9fafb;
    }

    /* CONTENT AREA WHERE PARTIALS LOAD */
    .content-section {
      margin-top:10px;
    }

    /* Generic section/card styles for loaded partials (dashboard-content, child-performance, fee-status, etc.) */
    .section {
      margin-top:4px;
    }
    .section-header {
      margin-bottom:12px;
    }
    .section-header h2 {
      margin:0;
      font-size:1.1rem;
    }
    .section-header p {
      margin:4px 0 0;
      font-size:0.86rem;
      color:var(--text-muted);
    }

    .cards-grid {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:14px;
    }

    .card {
      background:var(--bg-main);
      border-radius:16px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:12px 14px;
    }
    .stat-card .stat-label {
      font-size:0.8rem;
      text-transform:uppercase;
      letter-spacing:0.05em;
      color:#a16207;
      margin-bottom:4px;
    }
    .stat-card .stat-value {
      font-size:1.3rem;
      font-weight:700;
      margin-bottom:3px;
    }
    .stat-card .stat-hint {
      font-size:0.8rem;
      color:var(--text-muted);
    }
    .stat-card.clickable {
      cursor:pointer;
      transition:transform .12s, box-shadow .12s;
    }
    .stat-card.clickable:hover {
      transform:translateY(-2px);
      box-shadow:0 14px 30px rgba(15,23,42,0.12);
    }

    .data-table {
      width:100%;
      border-collapse:collapse;
      font-size:0.88rem;
    }
    .data-table th,
    .data-table td {
      padding:8px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    .data-table thead th {
      background:#fef3c7;
      color:#78350f;
      font-weight:600;
    }
    .data-table tbody tr:nth-child(even) {
      background:#fffaf0;
    }

    .fee-status-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .fee-status-header h3 {
      margin:0;
      font-size:1rem;
    }
    .fee-status-header .muted {
      margin:2px 0 0;
      font-size:0.82rem;
      color:var(--text-muted);
    }
    .badge {
      display:inline-block;
      padding:3px 10px;
      border-radius:999px;
      font-size:0.78rem;
      font-weight:600;
    }
    .badge-success {
      background:#ecfdf5;
      color:#15803d;
      border:1px solid #bbf7d0;
    }

    .grid-2 {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:8px 18px;
      margin:10px 0 14px;
    }
    .info-row {
      display:flex;
      justify-content:space-between;
      font-size:0.86rem;
    }
    .info-row span {
      color:var(--text-muted);
    }

    .fee-status-footer {
      margin-top:10px;
      font-size:0.84rem;
      color:#166534;
      text-align:center;
    }

    .table-total td {
      border-top:2px solid #f3e5d7;
    }

    .alert {
      padding:10px 12px;
      border-radius:10px;
      font-size:0.86rem;
      margin-top:8px;
    }
    .alert-error {
      background:#fef2f2;
      color:#b91c1c;
      border:1px solid #fecaca;
    }

    /* Dark mode for parent (simple toggle) */
    body.dark-mode {
      background:#020617;
      color:#e5e7eb;
    }
    body.dark-mode .app-shell {
      background:#020617;
    }
    body.dark-mode .main {
      background:radial-gradient(circle at top left,#0f172a,#020617);
    }
    body.dark-mode .sidebar {
      background:#020617;
      border-color:#111827;
    }
    body.dark-mode .nav a {
      color:#9ca3af;
    }
    body.dark-mode .nav a.active {
      background:#111827;
      color:#facc15;
    }
    body.dark-mode .card,
    body.dark-mode .panel {
      background:#020617;
      border-color:#111827;
      box-shadow:none;
    }
    body.dark-mode .data-table thead th {
      background:#111827;
      color:#facc15;
    }
    body.dark-mode .data-table tbody tr:nth-child(even) {
      background:#030712;
    }

    /* ========== Parent Overview + Updates (dashboard-content.php) ========== */

    /* generic section wrapper */
    .section {
      margin-bottom: 24px;
    }

    .section-header h2 {
      margin: 0 0 4px;
      font-size: 1.15rem;
      font-weight: 600;
    }

    .section-header p {
      margin: 0 0 10px;
      font-size: 0.86rem;
      color: #6b7280;
    }

    /* stat cards row */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }

    /* base card style (also used below) */
    .card {
      background: #ffffff;
      border-radius: 16px;
      border: 1px solid #f3e5d7;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
      padding: 14px 16px;
    }

    .stat-card {
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-height: 96px;
    }

    .stat-card.clickable {
      cursor: pointer;
    }

    .stat-card.clickable:hover {
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
      transform: translateY(-1px);
      transition: box-shadow 0.15s, transform 0.15s;
    }

    .stat-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #a16207;
      margin-bottom: 3px;
    }

    .stat-value {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 1px;
    }

    .stat-hint {
      font-size: 0.8rem;
      color: #6b7280;
    }

    /* updates grid: notices + timeline side by side */
    .updates-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr);
      gap: 16px;
    }

    /* card header rows (title + button / chips) */
    .card-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }

    .card-title {
      margin: 0;
      font-size: 0.98rem;
      font-weight: 600;
    }

    .card-sub {
      margin: 2px 0 0;
      font-size: 0.82rem;
      color: #6b7280;
    }

    /* chips (used for “View all notices” & timeline filter) */
    .chip-group {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .chip {
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      padding: 6px 11px;
      font-size: 0.82rem;
      color: #4b5563;
      cursor: pointer;
      white-space: nowrap;
    }

    .chip-sm {
      padding: 4px 9px;
      font-size: 0.78rem;
    }

    .chip:hover {
      background: #fff5e5;
      border-color: #fed7aa;
      color: #92400e;
    }

    .chip.active {
      background: #fff5e5;
      border-color: #fbbf24;
      color: #92400e;
      font-weight: 600;
    }

    /* notices list */
    .notice-list {
      list-style: none;
      padding: 0;
      margin: 4px 0 0;
    }

    .notice-list li {
      padding: 6px 2px;
      border-bottom: 1px dashed #f3e5d7;
    }

    .notice-list li:last-child {
      border-bottom: none;
    }

    .notice-title {
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 1px;
    }

    .notice-snippet {
      font-size: 0.8rem;
      color: #4b5563;
    }

    .notice-meta {
      font-size: 0.75rem;
      color: #9ca3af;
      margin-top: 2px;
    }

    /* shared empty row style */
    .empty-row {
      font-size: 0.85rem;
      color: #9ca3af;
      padding: 6px 2px;
    }

    /* timeline styles */
    .timeline {
      list-style: none;
      padding: 0;
      margin: 4px 0 0;
    }

    .timeline-item {
      display: flex;
      position: relative;
      padding: 8px 0 8px 0;
    }

    .timeline-item::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #fee2b3;
    }

    .timeline-dot {
      position: relative;
      z-index: 1;
      width: 10px;
      height: 10px;
      border-radius: 999px;
      border: 2px solid #fbbf24;
      background: #fff7e6;
      margin-right: 10px;
      margin-top: 4px;
    }

    .timeline-dot.grade {
      border-color: #22c55e;
      background: #ecfdf3;
    }

    .timeline-dot.event {
      border-color: #3b82f6;
      background: #eff6ff;
    }

    .timeline-content {
      font-size: 0.84rem;
    }

    .timeline-title {
      font-weight: 600;
      margin-bottom: 1px;
    }

    .timeline-meta {
      color: #4b5563;
    }

    .timeline-date {
      font-size: 0.75rem;
      color: #9ca3af;
      margin-top: 1px;
    }

    /* simple error alert used at top of content when something fails */
    .alert.alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 10px;
    }

    /* responsive tweaks */
    @media (max-width: 1100px) {
      .cards-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .updates-grid {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    @media (max-width: 800px) {
      .cards-grid {
        grid-template-columns: minmax(0, 1fr);
      }
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

      <div>
        <div class="sidebar-parent-card">
          <nav class="nav">
            <a href="#" class="nav-link active" data-page="dashboard-content">
              <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="#" class="nav-link" data-page="child-performance">
              <i class="fas fa-chart-line"></i><span>Child Performance</span>
            </a>
            <a href="#" class="nav-link" data-page="fee-status">
              <i class="fas fa-money-bill"></i><span>Fee Status</span>
            </a>
            <!-- NEW: View all notices -->
            <a href="#" class="nav-link" data-page="notices">
              <i class="fas fa-bullhorn"></i><span>Notices</span>
            </a>
            <a href="/edusphere/auth/logout.php" class="nav-logout">
              <i class="fas fa-sign-out-alt"></i>
              <span>Logout</span>
            </a>
          </nav>
        </div>
      </div>
    </div>

    <div>
      <div class="sidebar-parent-card">
        <img src="<?= htmlspecialchars($parent_avatar) ?>" alt="Parent" />
        <div>
          <div class="name"><?= htmlspecialchars($parent_name) ?></div>
          <div class="email"><?= htmlspecialchars($parent_email) ?></div>
          <?php if ($child_name): ?>
            <div class="child">
              Child: <?= htmlspecialchars($child_name) ?>
              <?= $child_class ? '· ' . htmlspecialchars($child_class) : '' ?>
            </div>
          <?php endif; ?>
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
          <h2>Parent Hub</h2>
          <p>Stay updated with your child’s academic journey.</p>
        </div>
        <div class="main-header-right">
          <div class="notif-wrapper">
            <button class="icon-btn" id="notificationBell" type="button" aria-label="Notifications">
              <i class="fas fa-bell"></i>
              <?php if ($is_child_at_risk && $child_name): ?>
                <span class="notif-badge" title="Child at academic risk">!</span>
              <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notificationDropdown">
              <h4>Notifications</h4>
              <ul>
                <?php if (!$child_student_id): ?>
                  <li>No child is currently linked with this parent account.</li>
                <?php else: ?>
                  <?php if ($is_child_at_risk): ?>
                    <li>
                      <strong><?= htmlspecialchars($child_name) ?></strong> is currently
                      <span style="color:#b91c1c;font-weight:600;">at academic risk</span>.
                      <?php if (!empty($risk_reasons)): ?>
                        <br/>
                        <small>
                          Reasons: <?= htmlspecialchars(implode(', ', $risk_reasons)) ?>.
                        </small>
                      <?php endif; ?>
                      <br/>
                      <small>View “Child Performance” for detailed breakdown and suggestions.</small>
                    </li>
                  <?php else: ?>
                    <li>
                      <strong><?= htmlspecialchars($child_name) ?></strong> is currently
                      <span style="color:#16a34a;font-weight:600;">on track</span>.
                      <br/>
                      <small>Keep checking regularly to stay updated.</small>
                    </li>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- Optional extra notifications (sample placeholders) -->
                <li>Child attendance updated.</li>
                <li>New message from class teacher.</li>
              </ul>
            </div>
          </div>

          <div style="position:relative;">
            <button class="icon-btn" id="settingsToggle" type="button" aria-label="Settings">
              <i class="fas fa-cog"></i>
            </button>
            <div class="settings-dropdown" id="settingsMenu">
              <label class="dropdown-item">
                <span>Dark Mode</span>
                <input type="checkbox" id="darkModeToggle" />
              </label>
              <label class="dropdown-item">
                <span>Language</span>
                <select id="languageSelect">
                  <option value="en">English</option>
                  <option value="np">नेपाली</option>
                </select>
              </label>
            </div>
          </div>

          <div class="header-avatar">
            <img src="<?= htmlspecialchars($parent_avatar) ?>" alt="Parent" />
            <div>
              <div class="name"><?= htmlspecialchars($parent_name) ?></div>
              <div class="role">Parent · EduSphere</div>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENT (AJAX LOADED) -->
      <section class="content-section" id="dashboardContent">
        <!-- dashboard-content.php, child-performance.php, fee-status.php, communication.php load here -->
      </section>
    </div>
  </main>
</div>

<script>
  const links       = document.querySelectorAll('.nav a[data-page]');
  const mainContent = document.getElementById('dashboardContent');

  function setActive(link) {
    links.forEach(l => l.classList.remove('active'));
    if (link) link.classList.add('active');
  }

  function initTimelineFilter() {
    // look for elements INSIDE the loaded partial
    const filterButtons = mainContent.querySelectorAll('#timelineFilter .chip');
    const items         = mainContent.querySelectorAll('#timelineList .timeline-item');

    if (!filterButtons.length || !items.length) return; // not on this page

    function applyTimelineFilter(type) {
      items.forEach(item => {
        const t = item.getAttribute('data-type');
        item.style.display = (type === 'all' || t === type) ? '' : 'none';
      });
    }

    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const type = btn.getAttribute('data-filter') || 'all';
        applyTimelineFilter(type);
      });
    });
  }

  function loadPage(page) {
    fetch(`../parent/${page}.php`, { cache: 'no-store' })
      .then(res => res.ok ? res.text() : Promise.reject(new Error("Failed to load " + page)))
      .then(html => {
        mainContent.innerHTML = html;
        // re-attach timeline filter if this page has it
        initTimelineFilter();
      })
      .catch(err => {
        mainContent.innerHTML =
          '<div class="alert alert-error">Error loading content: ' + err.message + '</div>';
      });
  }

  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const page = link.dataset.page;
      setActive(link);
      loadPage(page);
    });
  });

  // Card click inside loaded content -> open corresponding page
  mainContent.addEventListener('click', e => {
    const card = e.target.closest('[data-open-page]');
    if (!card) return;
    const page = card.getAttribute('data-open-page');
    const link = document.querySelector('.nav a[data-page="' + page + '"]');
    setActive(link);
    loadPage(page);
  });

  // Notifications
  const bell      = document.getElementById('notificationBell');
  const dropNotif = document.getElementById('notificationDropdown');
  if (bell && dropNotif) {
    bell.addEventListener('click', e => {
      e.stopPropagation();
      dropNotif.classList.toggle('active');
    });
    document.addEventListener('click', () => dropNotif.classList.remove('active'));
  }

  // Settings dropdown
  const settingsToggle = document.getElementById('settingsToggle');
  const settingsMenu   = document.getElementById('settingsMenu');
  if (settingsToggle && settingsMenu) {
    settingsToggle.addEventListener('click', e => {
      e.stopPropagation();
      settingsMenu.classList.toggle('show');
    });
    document.addEventListener('click', e => {
      if (!settingsMenu.contains(e.target) && !settingsToggle.contains(e.target)) {
        settingsMenu.classList.remove('show');
      }
    });
  }

  // Dark mode (persist per parent)
  const darkToggle = document.getElementById('darkModeToggle');
  function applyDarkModeFromStorage() {
    const mode = localStorage.getItem('parentDarkMode');
    if (mode === 'enabled') {
      document.body.classList.add('dark-mode');
      if (darkToggle) darkToggle.checked = true;
    } else {
      document.body.classList.remove('dark-mode');
      if (darkToggle) darkToggle.checked = false;
    }
  }
  applyDarkModeFromStorage();

  if (darkToggle) {
    darkToggle.addEventListener('change', () => {
      if (darkToggle.checked) {
        localStorage.setItem('parentDarkMode', 'enabled');
      } else {
        localStorage.setItem('parentDarkMode', 'disabled');
      }
      applyDarkModeFromStorage();
    });
  }

  // Initial load
  window.addEventListener('DOMContentLoaded', () => {
    loadPage('dashboard-content');
  });
</script>

</body>
</html>
