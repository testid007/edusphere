<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$student_id      = (int)($_SESSION['user_id'] ?? ($_SESSION['student_id'] ?? 0));
$student_name    = $_SESSION['student_name']  ?? 'Student';
$student_email   = $_SESSION['student_email'] ?? 'student@example.com';
$student_avatar  = '../../assets/img/user.jpg';

// Allowed categories for filter dropdown
$allowed_categories = ['Assignment', 'Exam', 'Discipline', 'Classroom Activity'];
$selected_category  = $_GET['category'] ?? 'All';

// Base query
$sql = "SELECT category, title, score, grade, comments, date_added 
        FROM grades 
        WHERE student_id = :student_id";
$params = [':student_id' => $student_id];

if (in_array($selected_category, $allowed_categories, true)) {
    $sql .= " AND category = :category";
    $params[':category'] = $selected_category;
}
$sql .= " ORDER BY date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// stats + category averages
$stats = [
    'avg_score'      => null,
    'total_records'  => 0,
    'best_category'  => null,
    'weak_category'  => null,
];

try {
    $stmt = $conn->prepare("
        SELECT 
            AVG(score) AS avg_score,
            COUNT(*)   AS total_rows
        FROM grades
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['avg_score']     = $row['avg_score'] !== null ? round($row['avg_score'], 1) : null;
        $stats['total_records'] = (int)$row['total_rows'];
    }

    // per-category averages
    $stmt = $conn->prepare("
        SELECT category, AVG(score) AS avg_score
        FROM grades
        WHERE student_id = :sid
        GROUP BY category
    ");
    $stmt->execute([':sid' => $student_id]);
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $best = null;
    $weak = null;
    foreach ($cats as $c) {
        $score = (float)$c['avg_score'];
        if ($best === null || $score > $best['score']) {
            $best = ['name' => $c['category'], 'score' => $score];
        }
        if ($weak === null || $score < $weak['score']) {
            $weak = ['name' => $c['category'], 'score' => $score];
        }
    }
    if ($best) $stats['best_category'] = $best;
    if ($weak) $stats['weak_category'] = $weak;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Results | EduSphere</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    /* shell same as dashboard */
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
      --shadow-card:0 12px 30px rgba(15,23,42,0.06);
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
      padding:28px 22px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:28px;
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
      padding:11px 14px;
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
    .sidebar-student-card{
      margin-top:24px;
      padding:14px 16px;
      border-radius:20px;
      background:radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:12px;
    }
    .sidebar-student-card img{
      width:44px;
      height:44px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-student-card .name{
      font-size:0.98rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-student-card .role{
      font-size:0.8rem;
      color:#92400e;
    }

    .main{
      padding:24px 44px 36px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner{
      max-width:1320px;
      margin:0 auto;
    }
    .main-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:16px;
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
      min-width:180px;
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

    .stats-row{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:14px;
      margin-bottom:16px;
    }
    .stat-card{
      background:var(--bg-main);
      border-radius:14px;
      padding:12px 14px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
    }
    .stat-label{
      font-size:0.8rem;
      text-transform:uppercase;
      letter-spacing:0.05em;
      color:#a16207;
      margin-bottom:4px;
    }
    .stat-value{
      font-size:1.3rem;
      font-weight:700;
      margin-bottom:2px;
    }
    .stat-sub{
      font-size:0.8rem;
      color:var(--text-muted);
    }

    .layout-grid{
      display:grid;
      grid-template-columns:1.5fr 1.1fr;
      gap:18px;
      align-items:flex-start;
    }

    .panel{
      background:var(--bg-main);
      border-radius:16px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:14px 16px 16px;
    }
    .panel-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .panel-header h4{
      margin:0;
      font-size:0.98rem;
    }
    .panel-header span{
      font-size:0.8rem;
      color:var(--text-muted);
    }

    .filter-form{
      margin-bottom:10px;
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:center;
      font-size:0.88rem;
    }
    .filter-select{
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:0.86rem;
    }
    .filter-btn{
      background:#111827;
      color:#fff;
      border:none;
      border-radius:999px;
      padding:6px 12px;
      font-size:0.85rem;
      cursor:pointer;
      font-weight:600;
    }

    .table-container{
      width:100%;
      overflow-x:auto;
    }
    .performance-table{
      width:100%;
      border-collapse:collapse;
      font-size:0.9rem;
    }
    .performance-table th,
    .performance-table td{
      padding:8px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    .performance-table thead th{
      background:#fff7ea;
      font-size:0.85rem;
      text-transform:uppercase;
      letter-spacing:0.05em;
      color:#92400e;
    }

    #categoryChart{
      width:100%;
      max-height:250px;
    }

    @media(max-width:1100px){
      .app-shell{grid-template-columns:220px 1fr;}
      .stats-row{grid-template-columns:repeat(2,minmax(0,1fr));}
      .layout-grid{grid-template-columns:1fr;}
    }
    @media(max-width:800px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:18px;}
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
        <a href="assignments.php"><i class="fas fa-book"></i> Assignments</a>
        <a href="results.php" class="active"><i class="fas fa-graduation-cap"></i> Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fees</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
    <div class="sidebar-student-card">
      <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
      <div>
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="role">Student · EduSphere</div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <div class="main-header">
        <div class="main-header-left">
          <h2>My Results</h2>
          <p>Review your performance and identify strengths & improvements.</p>
        </div>
        <div class="header-avatar">
          <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
          <div>
            <div class="name"><?= htmlspecialchars($student_name) ?></div>
            <div class="role"><?= htmlspecialchars($student_email) ?></div>
          </div>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Overall Average</div>
          <div class="stat-value">
            <?= $stats['avg_score'] !== null ? $stats['avg_score'] : '—' ?>
          </div>
          <div class="stat-sub">
            Across <?= $stats['total_records'] ?> record<?= $stats['total_records'] == 1 ? '' : 's' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Strongest Area</div>
          <div class="stat-value">
            <?= $stats['best_category'] ? htmlspecialchars($stats['best_category']['name']) : '—' ?>
          </div>
          <div class="stat-sub">
            <?= $stats['best_category'] ? 'Avg ' . round($stats['best_category']['score'],1) . '%' : 'Not enough data yet' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Needs Attention</div>
          <div class="stat-value">
            <?= $stats['weak_category'] ? htmlspecialchars($stats['weak_category']['name']) : '—' ?>
          </div>
          <div class="stat-sub">
            <?= $stats['weak_category'] ? 'Avg ' . round($stats['weak_category']['score'],1) . '%' : 'Do well in all categories so far' ?>
          </div>
        </div>
      </div>

      <div class="layout-grid">
        <!-- TABLE PANEL -->
        <section class="panel">
          <div class="panel-header">
            <h4>Result Details</h4>
            <span>Filter by category and review comments</span>
          </div>
          <form method="GET" class="filter-form">
            <label for="category">Category:</label>
            <select name="category" id="category" class="filter-select">
              <option value="All" <?= $selected_category === 'All' ? 'selected' : '' ?>>All</option>
              <?php foreach ($allowed_categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $selected_category === $cat ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="filter-btn">Apply</button>
          </form>

          <div class="table-container">
            <table class="performance-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Title</th>
                  <th>Score</th>
                  <th>Grade</th>
                  <th>Comments</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($results)): ?>
                  <tr>
                    <td colspan="6" style="text-align:center;padding:10px;">
                      No results found for this filter.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($results as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars($row['category']) ?></td>
                      <td><?= htmlspecialchars($row['title']) ?></td>
                      <td><?= htmlspecialchars($row['score']) ?></td>
                      <td><?= htmlspecialchars($row['grade']) ?></td>
                      <td><?= htmlspecialchars($row['comments']) ?></td>
                      <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['date_added']))) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- CATEGORY CHART + TIPS -->
        <aside class="panel">
          <div class="panel-header">
            <h4>Category-wise Average</h4>
            <span>See where you perform better</span>
          </div>
          <canvas id="categoryChart"></canvas>

          <div style="margin-top:14px;font-size:0.86rem;color:var(--text-muted);">
            <strong>How to use this:</strong>
            <ul style="padding-left:18px;margin:6px 0 0;">
              <li>Subjects closer to 80–100% are your strong zones — keep revising to maintain them.</li>
              <li>Anything below 60% needs extra practice or discussion with your teacher.</li>
              <li>After each exam, quickly scan this panel to see if your overall trend is improving.</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </main>
</div>

<script>
  // Build category chart from PHP data
  (function() {
    const data = <?= json_encode($stats['best_category'] || $stats['weak_category'] ? null : null); ?>;
  })();

  (function() {
    const raw = <?= json_encode($stats['best_category'] || $stats['weak_category'] ? null : null); ?>;
  })();
</script>

<script>
  (function() {
    // We'll reconstruct averages again just for chart through PHP
    const averages = <?= json_encode(
      (function($conn,$student_id){
        try{
          $stmt = $conn->prepare("
              SELECT category, AVG(score) AS avg_score
              FROM grades
              WHERE student_id = :sid
              GROUP BY category
          ");
          $stmt->execute([':sid'=>$student_id]);
          return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(Exception $e){ return []; }
      })($conn,$student_id),
      JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    ); ?>;

    const canvas = document.getElementById('categoryChart');
    if (!canvas || !averages || !averages.length) return;

    const labels = averages.map(r => r.category);
    const values = averages.map(r => parseFloat(r.avg_score));

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Avg Score',
          data: values,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });
  })();
</script>
</body>
</html>
