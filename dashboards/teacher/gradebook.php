<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_name   = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email  = $_SESSION['teacher_email'] ?? 'teacher@example.com';
$teacher_avatar = '../../assets/img/user.jpg';


if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    try {
        if ($action === 'list') {
            $stmt = $conn->query("
                SELECT g.*,
                       CONCAT(u.first_name, ' ', u.last_name) AS student_name
                FROM grades g
                LEFT JOIN users u ON g.student_id = u.id
                ORDER BY g.date_added DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        if ($action === 'get' && isset($_GET['id'])) {
            $id   = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM grades WHERE id = ?");
            $stmt->execute([$id]);
            $grade = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => (bool)$grade,
                'data'    => $grade,
                'message' => $grade ? '' : 'Grade not found'
            ]);
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $category   = trim($_POST['category'] ?? '');
            $title      = trim($_POST['title'] ?? '');
            $score      = trim($_POST['score'] ?? '');
            $grade_val  = trim($_POST['grade'] ?? '');
            $comments   = trim($_POST['comments'] ?? '');

            $errors = [];
            if ($student_id <= 0) $errors[] = 'Invalid student ID';
            $allowed_cat = ['Assignment','Exam','Discipline','Classroom Activity'];
            if (!in_array($category, $allowed_cat, true)) $errors[] = 'Invalid category';
            if ($title === '')  $errors[] = 'Title is required';
            if ($score === '')  $errors[] = 'Score is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO grades (student_id, category, title, score, grade, comments)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade added successfully' : 'Failed to add grade'
                ]);
                exit;
            } else { // update
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing grade ID']);
                    exit;
                }
                $id   = (int)$_POST['id'];
                $stmt = $conn->prepare("
                    UPDATE grades
                    SET student_id = ?, category = ?, title = ?, score = ?, grade = ?, comments = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments, $id]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade updated successfully' : 'Failed to update grade'
                ]);
                exit;
            }
        }

        if ($action === 'delete' && isset($_POST['id'])) {
            $id   = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
            $ok   = $stmt->execute([$id]);
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Grade deleted successfully' : 'Failed to delete grade'
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

/* -------------------------------------------------------
   Helper: parse score string ("45/50", "72", "72.5/100")
   to a percentage (0–100) or null if not numeric
--------------------------------------------------------*/
function score_to_percent(?string $score): ?float {
    if ($score === null) return null;
    $s = trim($score);
    if ($s === '') return null;

    if (preg_match('/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/', $s, $m)) {
        $obt = (float)$m[1];
        if (!empty($m[2])) {
            $max = (float)$m[2];
            if ($max <= 0) return null;
            return ($obt / $max) * 100.0;
        }
        // No max given – assume already a percentage
        return $obt;
    }
    return null;
}

/* -------------------------------------------------------
   Small grade stats for header chips
--------------------------------------------------------*/
$gradeStats = [
    'total'       => 0,
    'assignment'  => 0,
    'exam'        => 0,
    'discipline'  => 0,
    'activity'    => 0,
];

try {
    $stmt = $conn->query("SELECT category, COUNT(*) AS c FROM grades GROUP BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $c = (int)$row['c'];
        $gradeStats['total'] += $c;
        switch ($row['category']) {
            case 'Assignment':         $gradeStats['assignment'] += $c; break;
            case 'Exam':               $gradeStats['exam']       += $c; break;
            case 'Discipline':         $gradeStats['discipline'] += $c; break;
            case 'Classroom Activity': $gradeStats['activity']   += $c; break;
        }
    }
} catch (Exception $e) {}

$examPercent = $gradeStats['total'] > 0
    ? round(($gradeStats['exam'] / $gradeStats['total']) * 100)
    : 0;

/* -------------------------------------------------------
   Trend data: average score % per day (last ~30 dates)
--------------------------------------------------------*/
$gradeTrend = [];
try {
    $scoreByDate  = [];
    $countByDate  = [];

    $stmt = $conn->query("
        SELECT score, DATE(date_added) AS d
        FROM grades
        ORDER BY date_added ASC
        LIMIT 200
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $percent = score_to_percent($row['score']);
        if ($percent === null) continue;
        $d = $row['d'];
        if (!isset($scoreByDate[$d])) {
            $scoreByDate[$d] = 0.0;
            $countByDate[$d] = 0;
        }
        $scoreByDate[$d] += $percent;
        $countByDate[$d] += 1;
    }

    foreach ($scoreByDate as $d => $sum) {
        $avg = $countByDate[$d] > 0 ? round($sum / $countByDate[$d], 1) : null;
        if ($avg !== null) {
            $gradeTrend[] = ['date' => $d, 'avg' => $avg];
        }
    }

    // keep only last 30 dates
    if (count($gradeTrend) > 30) {
        $gradeTrend = array_slice($gradeTrend, -30);
    }
} catch (Exception $e) {
    $gradeTrend = [];
}

/* -------------------------------------------------------
   At-risk students (attendance < 75% or avg score < 50%)
--------------------------------------------------------*/
$attendanceAgg = [];
$gradeAgg      = [];
$atRiskStudents = [];

try {
    // Attendance %
    $stmt = $conn->query("
        SELECT student_id,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_days
        FROM attendance
        GROUP BY student_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $present = (int)$row['present_days'];
        $absent  = (int)$row['absent_days'];
        $total   = $present + $absent;
        $percent = $total > 0 ? round(($present / $total) * 100, 1) : null;
        $attendanceAgg[(int)$row['student_id']] = $percent;
    }

    // Average grade %
    $stmt = $conn->query("SELECT student_id, score FROM grades");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['student_id'];
        $percent = score_to_percent($row['score']);
        if ($percent === null) continue;
        if (!isset($gradeAgg[$sid])) {
            $gradeAgg[$sid] = ['sum' => 0.0, 'count' => 0];
        }
        $gradeAgg[$sid]['sum']   += $percent;
        $gradeAgg[$sid]['count'] += 1;
    }

    foreach ($gradeAgg as $sid => $g) {
        $gradeAgg[$sid] = $g['count'] > 0 ? round($g['sum'] / $g['count'], 1) : null;
    }

    // All students (for names)
    $stmt = $conn->query("
        SELECT s.user_id AS id, CONCAT(u.first_name,' ',u.last_name) AS name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
        ORDER BY u.first_name, u.last_name
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id   = (int)$row['id'];
        $name = $row['name'];
        $att  = $attendanceAgg[$id] ?? null;
        $gr   = $gradeAgg[$id]      ?? null;

        $isAtRisk = false;
        if ($att !== null && $att < 75) $isAtRisk = true;
        if ($gr  !== null && $gr  < 50) $isAtRisk = true;

        if ($isAtRisk) {
            $atRiskStudents[] = [
                'name'       => $name,
                'attendance' => $att,
                'grade'      => $gr,
            ];
        }
    }

    // Sort by worst attendance / grade and limit to 8 chips
    usort($atRiskStudents, function($a, $b) {
        $aScore = ($a['attendance'] ?? 100) + ($a['grade'] ?? 100);
        $bScore = ($b['attendance'] ?? 100) + ($b['grade'] ?? 100);
        return $aScore <=> $bScore;
    });
    if (count($atRiskStudents) > 8) {
        $atRiskStudents = array_slice($atRiskStudents, 0, 8);
    }
} catch (Exception $e) {
    $atRiskStudents = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Grade Book | Teacher Dashboard</title>
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
      --shadow-card: 0 12px 30px rgba(15,23,42,0.06);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
    }

    /* Shell / Sidebar */
    .app-shell {
      width: 100%;
      display: grid;
      grid-template-columns: 250px 1fr;
      min-height: 100vh;
      background: var(--bg-shell);
    }
    .sidebar {
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border-soft);
      padding: 24px 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 24px;
    }
    .logo img { height: 36px; }
    .logo span {
      font-weight: 700;
      font-size: 1.05rem;
      color: #1f2937;
      letter-spacing: 0.04em;
    }
    .nav { display:flex; flex-direction:column; gap:6px; }
    .nav a {
      display:flex;
      align-items:center;
      gap:10px;
      padding:9px 12px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.9rem;
      text-decoration:none;
      transition:background 0.15s ease-out, color 0.15s ease-out, transform 0.15s ease-out;
    }
    .nav a i { width:18px; text-align:center; color:#9ca3af; }
    .nav a.active {
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
    }
    .nav a.active i { color:#f59e0b; }
    .nav a:hover {
      background:#ffeeda;
      color:#92400e;
      transform:translateX(2px);
    }
    .nav a.logout { margin-top:8px; color:#b91c1c; }

    .sidebar-teacher-card {
      margin-top:20px;
      padding:12px 14px;
      border-radius:18px;
      background:linear-gradient(135deg,#ffe9cf,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:10px;
    }
    .sidebar-teacher-card img {
      width:40px;
      height:40px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-teacher-card .name {
      font-size:0.9rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-teacher-card .role {
      font-size:0.78rem;
      color:#92400e;
    }

    /* Main */
    .main {
      padding:20px 40px 32px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner {
      max-width:1260px;
      margin:0 auto;
    }

    .page-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
    }
    .page-title-line {
      display:flex;
      align-items:center;
      gap:10px;
    }
    .page-title-icon {
      width:34px;
      height:34px;
      border-radius:999px;
      background:#fff3d6;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#d97706;
      box-shadow:0 10px 20px rgba(250,204,21,0.35);
    }
    .page-header h2 { margin:0; font-size:1.45rem; }
    .page-header p {
      margin:4px 0 0;
      font-size:0.9rem;
      color:var(--text-muted);
    }

    .page-header-clipart {
      display:flex;
      align-items:center;
      gap:12px;
    }
    .page-header-clipart img {
      height:60px;
      width:auto;
      animation:floaty 6s ease-in-out infinite;
    }
    .page-header-chip {
      padding:6px 10px;
      border-radius:999px;
      background:#fff7ea;
      font-size:0.8rem;
      color:#b45309;
    }
    @keyframes floaty {
      0%,100% { transform:translateY(0); }
      50% { transform:translateY(-6px); }
    }

    .content-grid {
      margin-top:6px;
      display:grid;
      grid-template-columns:minmax(0, 1.2fr) minmax(0, 1.6fr);
      gap:20px;
      align-items:flex-start;
    }

    .card {
      background:var(--bg-main);
      border-radius:20px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:18px 20px 20px;
    }
    .card-title {
      margin:0 0 8px;
      font-size:1rem;
    }
    .card-sub {
      margin:0 0 14px;
      font-size:0.85rem;
      color:var(--text-muted);
    }

    /* Left column (form + illustration) */
    .message {
      padding:10px 12px;
      border-radius:10px;
      font-size:0.86rem;
      margin-bottom:10px;
      display:none;
    }
    .message.success {
      background:#ecfdf3;
      color:#166534;
      border:1px solid #bbf7d0;
    }
    .message.error {
      background:#fef2f2;
      color:#b91c1c;
      border:1px solid #fecaca;
    }

    form.grade-form label {
      display:block;
      font-weight:600;
      margin-bottom:4px;
      color:#4b5563;
      font-size:0.86rem;
    }
    form.grade-form input[type="text"],
    form.grade-form input[type="number"],
    form.grade-form select,
    form.grade-form textarea {
      width:100%;
      padding:9px 11px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      background-color:#f9fafb;
      margin-bottom:10px;
      font-size:0.88rem;
    }
    form.grade-form input:focus,
    form.grade-form select:focus,
    form.grade-form textarea:focus {
      outline:none;
      border-color:#f59e0b;
      box-shadow:0 0 0 1px rgba(245,158,11,0.25);
    }

    .primary-btn {
      background:var(--accent);
      color:#fff;
      border:none;
      border-radius:999px;
      padding:9px 18px;
      font-weight:600;
      font-size:0.9rem;
      cursor:pointer;
      box-shadow:0 10px 24px rgba(245,158,11,0.45);
      display:inline-flex;
      align-items:center;
      gap:6px;
      margin-top:4px;
    }
    .primary-btn:hover { filter:brightness(1.03); }
    .secondary-btn {
      background:#f97373;
      color:#fff;
      border:none;
      border-radius:999px;
      padding:7px 14px;
      font-size:0.85rem;
      cursor:pointer;
      margin-left:8px;
    }

    .form-illustration {
      text-align:center;
      margin-top:8px;
    }
    .form-illustration img {
      max-width:160px;
      width:100%;
      animation:floaty 5s ease-in-out infinite;
    }
    .form-illustration p {
      font-size:0.8rem;
      color:var(--text-muted);
      margin-top:6px;
    }

    /* Right column wrapper */
    .right-column {
      display:flex;
      flex-direction:column;
      gap:16px;
    }

    .stats-row {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:10px;
    }
    .stat-chip {
      padding:6px 10px;
      border-radius:12px;
      font-size:0.78rem;
      background:#fff7ea;
      color:#92400e;
    }
    .stat-chip strong { color:#78350f; }

    .grade-toolbar {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin:8px 0 10px;
      font-size:0.86rem;
    }
    .grade-toolbar input,
    .grade-toolbar select {
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
    }

    table.gradebook-table {
      width:100%;
      border-collapse:collapse;
      margin-top:2px;
      background:#fff;
      border-radius:16px;
      overflow:hidden;
      box-shadow:var(--shadow-card);
      font-size:0.87rem;
    }
    .gradebook-table th,
    .gradebook-table td {
      padding:9px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    .gradebook-table thead th {
      background:#fbbf24;
      color:#78350f;
    }
    .gradebook-table tbody tr:nth-child(even) {
      background:#fffaf0;
    }
    .gradebook-table tbody tr:hover {
      background:#fff7e6;
    }

    .btn-edit,.btn-delete {
      border:none;
      border-radius:999px;
      padding:4px 9px;
      font-size:0.78rem;
      cursor:pointer;
      color:#fff;
    }
    .btn-edit { background:#22c55e; margin-right:3px; }
    .btn-delete { background:#f97373; }

    .chip {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:0.75rem;
      font-weight:600;
    }
    .chip-assignment { background:#e0f2fe;color:#0369a1; }
    .chip-exam       { background:#fee2e2;color:#b91c1c; }
    .chip-discipline { background:#fef3c7;color:#b45309; }
    .chip-classroom-activity { background:#dcfce7;color:#15803d; }

    /* Line chart canvas */
    #gradeTrendChart {
      width:100%;
      max-height:220px;
    }

    /* At-risk panel */
    .risk-chips {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-top:8px;
    }
    .risk-chip {
      padding:7px 10px;
      border-radius:999px;
      font-size:0.8rem;
      background:#fef2f2;
      color:#b91c1c;
      box-shadow:0 6px 14px rgba(248,113,113,0.35);
      display:flex;
      flex-direction:column;
      min-width:140px;
    }
    .risk-chip span.name {
      font-weight:600;
      margin-bottom:2px;
    }
    .risk-chip span.meta {
      font-size:0.75rem;
      color:#b91c1c;
    }

    @media (max-width:1100px) {
      .app-shell { grid-template-columns:220px 1fr; }
      .content-grid { grid-template-columns:1fr; }
    }
    @media (max-width:800px) {
      .app-shell { grid-template-columns:1fr; }
      .sidebar { display:none; }
      .main { padding:16px; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php" class="active"><i class="fas fa-book-open"></i> Grade Book</a>
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
      <header class="page-header">
        <div>
          <div class="page-title-line">
            <div class="page-title-icon"><i class="fas fa-book-open"></i></div>
            <div>
              <h2>Grade Book</h2>
              <p>Record and update student performance without losing the big picture.</p>
            </div>
          </div>
        </div>
        <div class="page-header-clipart">
          <span class="page-header-chip">
            Total records: <strong><?= $gradeStats['total'] ?></strong> · Exams: <strong><?= $examPercent ?>%</strong>
          </span>
          <img src="../../assets/img/illustrations/gradebook-illustration.png"
               alt="Grade Illustration" onerror="this.style.display='none';" />
        </div>
      </header>

      <section class="content-grid">
        <!-- LEFT COLUMN: FORM -->
        <div class="card">
          <div id="message" class="message"></div>
          <h3 class="card-title">Add / Edit Grade</h3>
          <p class="card-sub">Quickly log exam scores, assignments, and behaviour notes.</p>

          <form id="gradeForm" class="grade-form">
            <input type="hidden" id="grade-id" name="id" />

            <label for="student_id">Student ID</label>
            <input type="number" id="student_id" name="student_id" required placeholder="Enter Student ID" />

            <label for="category">Category</label>
            <select id="category" name="category" required>
              <option value="">Select Category</option>
              <option value="Assignment">Assignment</option>
              <option value="Exam">Exam</option>
              <option value="Discipline">Discipline</option>
              <option value="Classroom Activity">Classroom Activity</option>
            </select>

            <label for="title">Title</label>
            <input type="text" id="title" name="title" required placeholder="Enter title" />

            <label for="score">Score</label>
            <input type="text" id="score" name="score" required placeholder="Enter score e.g. 45/50" />

            <label for="grade">Grade</label>
            <input type="text" id="grade" name="grade" placeholder="Enter grade (optional)" />

            <label for="comments">Comments</label>
            <textarea id="comments" name="comments" rows="3" placeholder="Comments (optional)"></textarea>

            <button type="submit" id="submitBtn" class="primary-btn">
              <i class="fas fa-plus-circle"></i> <span>Add Grade</span>
            </button>
            <button type="button" id="cancelBtn" class="secondary-btn" style="display:none;">
              <i class="fas fa-times"></i> Cancel
            </button>
          </form>

          <div class="form-illustration">
            <img src="../../assets/img/illustrations/progress-animate.png"
                 alt="Progress Illustration" onerror="this.style.display='none';" />
            <p>Tip: log grades right after class while details are still fresh.</p>
          </div>
        </div>

        <!-- RIGHT COLUMN: TABLE + CHART + AT-RISK -->
        <div class="right-column">
          <!-- Grades & chart card -->
          <div class="card">
            <h3 class="card-title">Grades Overview</h3>
            <p class="card-sub">Search, filter, and visualize your class performance over time.</p>

            <div class="stats-row">
              <div class="stat-chip">
                Total <strong><?= $gradeStats['total'] ?></strong> records
              </div>
              <div class="stat-chip">
                Exams <strong><?= $gradeStats['exam'] ?></strong>
              </div>
              <div class="stat-chip">
                Assignments <strong><?= $gradeStats['assignment'] ?></strong>
              </div>
              <div class="stat-chip">
                Behaviour notes <strong><?= $gradeStats['discipline'] ?></strong>
              </div>
            </div>

            <!-- Line chart -->
            <canvas id="gradeTrendChart"></canvas>

            <div class="grade-toolbar">
              <input type="text" id="searchGrades" placeholder="Search by student, title, or category..." />
              <select id="filterCategory">
                <option value="">All Categories</option>
                <option value="Assignment">Assignment</option>
                <option value="Exam">Exam</option>
                <option value="Discipline">Discipline</option>
                <option value="Classroom Activity">Classroom Activity</option>
              </select>
            </div>

            <table class="gradebook-table" id="gradesTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Student</th>
                  <th>Category</th>
                  <th>Title</th>
                  <th>Score</th>
                  <th>Grade</th>
                  <th>Comments</th>
                  <th>Date Added</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <!-- At-risk panel -->
          <div class="card">
            <h3 class="card-title">At-Risk Students</h3>
            <p class="card-sub">Students with attendance below 75% or average grade below 50%.</p>

            <?php if (empty($atRiskStudents)): ?>
              <p style="font-size:0.86rem;color:var(--text-muted);margin-top:4px;">
                No students flagged as at-risk yet. 🎉
              </p>
            <?php else: ?>
              <div class="risk-chips">
                <?php foreach ($atRiskStudents as $s): ?>
                  <div class="risk-chip">
                    <span class="name"><?= htmlspecialchars($s['name']) ?></span>
                    <span class="meta">
                      Attendance:
                      <?= $s['attendance'] !== null ? htmlspecialchars($s['attendance']).'%' : 'N/A' ?>
                      · Grade:
                      <?= $s['grade'] !== null ? htmlspecialchars($s['grade']).'%' : 'N/A' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<script>
  const msgBox    = document.getElementById('message');
  const gradeForm = document.getElementById('gradeForm');
  const submitBtn = document.getElementById('submitBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const tbody     = document.querySelector('#gradesTable tbody');
  const searchInput   = document.getElementById('searchGrades');
  const filterCategory= document.getElementById('filterCategory');

  let editingId = null;

  function showMessage(text, type='success') {
    msgBox.textContent = text;
    msgBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
    msgBox.style.display = 'block';
    setTimeout(() => msgBox.style.display = 'none', 4000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[m]);
  }

  function clearForm() {
    editingId = null;
    gradeForm.reset();
    document.getElementById('grade-id').value = '';
    submitBtn.innerHTML = '<i class="fas fa-plus-circle"></i> <span>Add Grade</span>';
    cancelBtn.style.display = 'none';
  }

  function getCategoryChipClass(category) {
    if (!category) return '';
    const key = category.toLowerCase().replace(/\s+/g, '-');
    return 'chip-' + key;
  }

  function applyGradeFilters() {
    const term = searchInput.value.toLowerCase();
    const cat  = filterCategory.value;

    document.querySelectorAll('#gradesTable tbody tr').forEach(tr => {
      const student  = tr.children[1].textContent.toLowerCase();
      const category = tr.children[2].textContent.trim();
      const title    = tr.children[3].textContent.toLowerCase();
      const score    = tr.children[4].textContent.toLowerCase();

      const matchTerm = !term || student.includes(term) || title.includes(term) || score.includes(term);
      const matchCat  = !cat || category === cat;

      tr.style.display = (matchTerm && matchCat) ? '' : 'none';
    });
  }

  function loadGrades() {
    fetch('?action=list')
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          showMessage('Failed to load grades.', 'error');
          return;
        }
        tbody.innerHTML = '';
        if (!data.data || data.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9">No grades found.</td></tr>';
          return;
        }
        data.data.forEach(g => {
          const tr = document.createElement('tr');
          const chipClass = getCategoryChipClass(g.category);
          tr.innerHTML = `
            <td>${g.id}</td>
            <td>${escapeHtml(g.student_name || ('ID ' + g.student_id))}</td>
            <td><span class="chip ${chipClass}">${escapeHtml(g.category)}</span></td>
            <td>${escapeHtml(g.title)}</td>
            <td>${escapeHtml(g.score)}</td>
            <td>${escapeHtml(g.grade || '')}</td>
            <td>${escapeHtml(g.comments || '')}</td>
            <td>${escapeHtml(g.date_added || '')}</td>
            <td>
              <button class="btn-edit" data-id="${g.id}">Edit</button>
              <button class="btn-delete" data-id="${g.id}">Delete</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
        attachRowHandlers();
        applyGradeFilters();
      })
      .catch(() => showMessage('Error loading grades.', 'error'));
  }

  function attachRowHandlers() {
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        const id = btn.dataset.id;
        fetch(`?action=get&id=${id}`)
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              showMessage('Failed to load grade.', 'error');
              return;
            }
            editingId = id;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> <span>Update Grade</span>';
            cancelBtn.style.display = 'inline-flex';
            const g = data.data;
            document.getElementById('grade-id').value   = g.id;
            document.getElementById('student_id').value = g.student_id;
            document.getElementById('category').value   = g.category;
            document.getElementById('title').value      = g.title;
            document.getElementById('score').value      = g.score;
            document.getElementById('grade').value      = g.grade || '';
            document.getElementById('comments').value   = g.comments || '';
          });
      };
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        if (!confirm('Delete this grade?')) return;
        const id = btn.dataset.id;
        fetch('?action=delete', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showMessage(data.message || 'Deleted.', 'success');
            loadGrades();
            if (editingId === id) clearForm();
          } else {
            showMessage(data.message || 'Failed to delete.', 'error');
          }
        });
      };
    });
  }

  gradeForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(gradeForm));

    let action = 'create';
    if (editingId) {
      action = 'update';
      formData.append('id', editingId);
    }

    fetch(`?action=${action}`, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showMessage(data.message || 'Saved.', 'success');
        loadGrades();
        clearForm();
      } else {
        showMessage((data.errors && data.errors.join(', ')) || data.message || 'Failed to save.', 'error');
      }
    })
    .catch(() => showMessage('Error saving grade.', 'error'));
  });

  cancelBtn.addEventListener('click', clearForm);
  searchInput.addEventListener('input', applyGradeFilters);
  filterCategory.addEventListener('change', applyGradeFilters);

  loadGrades();

  // --------- Chart.js: average score over time ----------
  (function() {
    const trendData = <?= json_encode($gradeTrend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!trendData || !trendData.length) return;

    const labels = trendData.map(item => item.date);
    const values = trendData.map(item => item.avg);

    const canvas = document.getElementById('gradeTrendChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Average Score %',
          data: values,
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 3
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
