<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$admin_name   = $_SESSION['admin_name']  ?? 'Main';
$admin_email  = $_SESSION['admin_email'] ?? 'admin@example.com';
$admin_avatar = '../../assets/img/user.jpg';

/* --------- BASIC STATS FOR REPORTS --------- */
$totalUsers = 0;
$roleCounts = [
    'student' => 0,
    'teacher' => 0,
    'parent'  => 0,
    'admin'   => 0,
];

try {
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM users");
    $totalUsers = (int)$stmt->fetchColumn();

    // Count per role
    $stmt = $conn->query("
        SELECT LOWER(role) AS role, COUNT(*) AS cnt
        FROM users
        GROUP BY LOWER(role)
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $r = $row['role'];
        $c = (int)$row['cnt'];
        if (isset($roleCounts[$r])) {
            $roleCounts[$r] = $c;
        }
    }
} catch (PDOException $e) {
    // If DB fails, leave defaults (0)
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports &amp; Analytics | EduSphere Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    :root{
      --bg-page:#f5eee9;
      --bg-shell:#fdfcfb;
      --bg-sidebar:#fdf5ec;
      --bg-main:#ffffff;
      --accent:#f59e0b;
      --accent-soft:#fff5e5;
      --text-main:#111827;
      --text-muted:#6b7280;
      --border-soft:#f3e5d7;
      --shadow-card:0 14px 34px rgba(15,23,42,0.08);
    }
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:var(--bg-page);
      color:var(--text-main);
    }
    .app-shell{
      width:100%;
      display:grid;
      grid-template-columns:260px 1fr;
      min-height:100vh;
      background:var(--bg-shell);
    }
    .sidebar{
      background:var(--bg-sidebar);
      border-right:1px solid var(--border-soft);
      padding:24px 20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:26px;
    }
    .logo img{height:40px;}
    .logo span{
      font-weight:700;
      font-size:1.15rem;
      color:#1f2937;
      letter-spacing:0.04em;
    }
    .nav{display:flex;flex-direction:column;gap:8px;}
    .nav a{
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 14px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.95rem;
      text-decoration:none;
      transition:background .15s,color .15s,transform .15s,box-shadow .15s;
    }
    .nav a i{
      width:20px;
      text-align:center;
      color:#9ca3af;
    }
    .nav a.active{
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
      box-shadow:0 10px 22px rgba(245,158,11,.35);
    }
    .nav a.active i{color:#f59e0b;}
    .nav a:hover{
      background:#ffeeda;
      color:#92400e;
      transform:translateX(3px);
    }
    .nav a.logout{margin-top:10px;color:#b91c1c;}

    .sidebar-admin-card{
      margin-top:24px;
      padding:14px 16px;
      border-radius:20px;
      background:radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:12px;
    }
    .sidebar-admin-card img{
      width:44px;
      height:44px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-admin-card .name{
      font-size:0.98rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-admin-card .role{
      font-size:0.8rem;
      color:#92400e;
    }

    .main{
      padding:24px 44px 36px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner{
      max-width:1000px;
      margin:0 auto;
    }
    .main-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
    }
    .main-header-left h2{
      margin:0;
      font-size:1.7rem;
      font-weight:700;
    }
    .main-header-left p{
      margin:4px 0 0;
      font-size:0.92rem;
      color:var(--text-muted);
    }
    .header-avatar{
      display:flex;
      align-items:center;
      gap:10px;
      padding:6px 14px;
      border-radius:999px;
      background:#fff7ea;
      border:1px solid #fed7aa;
      min-width:190px;
    }
    .header-avatar img{
      width:32px;
      height:32px;
      border-radius:50%;
      object-fit:cover;
    }
    .header-avatar .name{
      font-size:0.95rem;
      font-weight:600;
      color:#78350f;
    }
    .header-avatar .role{
      font-size:0.78rem;
      color:#c05621;
    }

    .card{
      background:#fff;
      border-radius:18px;
      padding:18px 22px 22px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      margin-bottom:16px;
    }
    .card-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      margin-bottom:14px;
    }
    .card-header h3{
      margin:0;
      font-size:1.05rem;
      font-weight:600;
    }
    .card-header p{
      margin:4px 0 0;
      font-size:0.85rem;
      color:var(--text-muted);
    }
    .card-badge{
      font-size:0.78rem;
      padding:4px 10px;
      border-radius:999px;
      background:#fff7e6;
      color:#92400e;
      border:1px solid #fed7aa;
    }

    .stats-grid{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:14px;
      margin-top:6px;
    }
    @media(max-width:900px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:18px;}
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media(max-width:600px){
      .stats-grid{grid-template-columns:1fr;}
    }

    .stat-card{
      border-radius:16px;
      padding:10px 12px;
      background:radial-gradient(circle at top left,#fff7e6,#ffffff);
      border:1px solid #f3e5d7;
    }
    .stat-label{
      font-size:0.8rem;
      color:#6b7280;
      margin-bottom:4px;
    }
    .stat-value{
      font-size:1.3rem;
      font-weight:700;
      color:#111827;
    }
    .stat-sub{
      font-size:0.75rem;
      color:#9ca3af;
      margin-top:2px;
    }

    .table-wrapper{
      margin-top:10px;
      overflow-x:auto;
    }
    table.simple{
      width:100%;
      border-collapse:collapse;
      font-size:0.85rem;
    }
    table.simple th,
    table.simple td{
      padding:7px 9px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
    }
    table.simple th{
      background:#f9fafb;
      font-weight:600;
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
        <a href="overview.php"><i class="fas fa-chart-pie"></i> Overview</a>
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="create-fee.php"><i class="fas fa-layer-group"></i> Create Fee</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fees &amp; Payments</a>
        <a href="reports.php" class="active"><i class="fas fa-chart-line"></i> View Reports</a>
        <a href="schedule.php"><i class="fas fa-calendar-alt"></i> Manage Schedule</a>
        <a href="schedule-view.php"><i class="fas fa-table"></i> Schedule View</a>
        <a href="events.php"><i class="fas fa-bullhorn"></i> Manage Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
    <div class="sidebar-admin-card">
      <img src="<?= h($admin_avatar) ?>" alt="Admin" />
      <div>
        <div class="name"><?= h($admin_name) ?></div>
        <div class="role">Administrator · EduSphere</div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <div class="main-header">
        <div class="main-header-left">
          <h2>Reports &amp; Analytics</h2>
          <p>Visual overview of user distribution across the system.</p>
        </div>
        <div class="header-avatar">
          <img src="<?= h($admin_avatar) ?>" alt="Admin" />
          <div>
            <div class="name"><?= h($admin_name) ?></div>
            <div class="role"><?= h($admin_email) ?></div>
          </div>
        </div>
      </div>

      <!-- SUMMARY STATS CARD -->
      <section class="card">
        <div class="card-header">
          <div>
            <h3>User Distribution Snapshot</h3>
            <p>Quick breakdown of active user accounts by role.</p>
          </div>
          <span class="card-badge">Admin &gt; Reports</span>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-sub">All roles combined</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Students</div>
            <div class="stat-value"><?= $roleCounts['student'] ?></div>
            <div class="stat-sub">Learner accounts</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Teachers</div>
            <div class="stat-value"><?= $roleCounts['teacher'] ?></div>
            <div class="stat-sub">Teacher accounts</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Parents</div>
            <div class="stat-value"><?= $roleCounts['parent'] ?></div>
            <div class="stat-sub">Linked parent logins</div>
          </div>
        </div>
      </section>

      <!-- TABLE CARD -->
      <section class="card">
        <div class="card-header">
          <div>
            <h3>Role-wise Counts</h3>
            <p>Exact numbers for each role, including admins.</p>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="simple">
            <thead>
              <tr>
                <th>Role</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Students</td>
                <td><?= $roleCounts['student'] ?></td>
              </tr>
              <tr>
                <td>Teachers</td>
                <td><?= $roleCounts['teacher'] ?></td>
              </tr>
              <tr>
                <td>Parents</td>
                <td><?= $roleCounts['parent'] ?></td>
              </tr>
              <tr>
                <td>Admins</td>
                <td><?= $roleCounts['admin'] ?></td>
              </tr>
              <tr>
                <td><strong>Total</strong></td>
                <td><strong><?= $totalUsers ?></strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</div>
</body>
</html>
