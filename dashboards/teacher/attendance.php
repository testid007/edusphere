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

/* -------------------------------------------------
   1. LOAD CLASS LIST FROM students.class
   ------------------------------------------------- */
$classes = [];
try {
    $stmt = $conn->query("
        SELECT DISTINCT class 
        FROM students 
        WHERE class IS NOT NULL AND class <> '' 
        ORDER BY class
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $classes = [];
}

/** helper: label like "Class 10" */
function class_label($cls) {
    if ($cls === '' || $cls === null) return 'Not selected';
    return 'Class ' . $cls;
}

$selectedClass = $_GET['class'] ?? ($classes[0] ?? '');
$selectedDate  = $_GET['date']  ?? date('Y-m-d');

/* -------------------------------------------------
   2. HANDLE POST: SAVE ATTENDANCE
   ------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedClass = $_POST['class'] ?? $selectedClass;
    $selectedDate  = $_POST['date']  ?? $selectedDate;
    $statuses      = $_POST['status'] ?? [];

    if ($selectedDate) {
        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            if ($studentId <= 0) continue;

            if ($status === 'present' || $status === 'absent') {
                // NOTE: for ON DUPLICATE KEY to work efficiently,
                // make sure you have a UNIQUE KEY on (student_id, date)
                $stmt = $conn->prepare("
                    INSERT INTO attendance (student_id, date, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $stmt->execute([$studentId, $selectedDate, $status]);
            } else {
                $stmt = $conn->prepare("
                    DELETE FROM attendance 
                    WHERE student_id = ? AND date = ?
                ");
                $stmt->execute([$studentId, $selectedDate]);
            }
        }
    }

    header('Location: attendance.php?class=' . urlencode($selectedClass) . '&date=' . urlencode($selectedDate));
    exit;
}

/* -------------------------------------------------
   3. LOAD STUDENTS FOR THE SELECTED CLASS
   ------------------------------------------------- */
$students = [];
if ($selectedClass !== '') {
    $stmt = $conn->prepare("
        SELECT 
            s.user_id AS id,
            s.class   AS class,
            CONCAT(u.first_name, ' ', u.last_name) AS name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student' AND s.class = ?
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------------------------------------
   4. TODAY'S ATTENDANCE FOR SELECTED CLASS & DATE
   ------------------------------------------------- */
$attendanceStatus = [];
if (!empty($students) && $selectedDate) {
    $ids = array_column($students, 'id');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $params[] = $selectedDate;

        $stmt = $conn->prepare("
            SELECT student_id, status
            FROM attendance
            WHERE student_id IN ($placeholders) AND date = ?
        ");
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $attendanceStatus[(int)$row['student_id']] = $row['status'];
        }
    }
}

$presentToday = 0;
$absentToday  = 0;
foreach ($attendanceStatus as $st) {
    if ($st === 'present') $presentToday++;
    elseif ($st === 'absent') $absentToday++;
}
$totalToday = $presentToday + $absentToday;

/* -------------------------------------------------
   5. LONG-TERM SUMMARY FOR THIS CLASS
   ------------------------------------------------- */
$summary = [];
$classAverage = null;
$belowThresholdCount = 0;
$threshold = 75; // 75% warning line

if (!empty($students)) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT student_id,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_days
        FROM attendance
        WHERE student_id IN ($placeholders)
        GROUP BY student_id
    ");
    $stmt->execute($ids);

    $totalPct = 0;
    $withPct  = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $present = (int)$row['present_days'];
        $absent  = (int)$row['absent_days'];
        $total   = $present + $absent;
        $percent = $total > 0 ? round(($present / $total) * 100, 1) : null;

        if ($percent !== null) {
            $totalPct += $percent;
            $withPct++;
            if ($percent < $threshold) {
                $belowThresholdCount++;
            }
        }

        $summary[(int)$row['student_id']] = [
            'present' => $present,
            'absent'  => $absent,
            'percent' => $percent,
        ];
    }

    if ($withPct > 0) {
        $classAverage = round($totalPct / $withPct, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance | Teacher Dashboard</title>
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

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
    }

    .app-shell {
      width: 100%;
      display: grid;
      grid-template-columns: 250px 1fr;
      min-height: 100vh;
      background: var(--bg-shell);
    }

    /* SIDEBAR */
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
      display:flex; align-items:center; gap:10px;
      padding:9px 12px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.9rem;
      text-decoration:none;
      transition:background .15s,color .15s,transform .15s;
    }
    .nav a i { width:18px;text-align:center;color:#9ca3af; }
    .nav a.active{
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
    }
    .nav a.active i{color:#f59e0b;}
    .nav a:hover{
      background:#ffeeda;
      color:#92400e;
      transform:translateX(2px);
    }
    .nav a.logout{margin-top:8px;color:#b91c1c;}

    .sidebar-teacher-card{
      margin-top:20px;
      padding:12px 14px;
      border-radius:18px;
      background:linear-gradient(135deg,#ffe9cf,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:10px;
    }
    .sidebar-teacher-card img{
      width:40px;height:40px;border-radius:50%;
      object-fit:cover;border:2px solid #fff;
    }
    .sidebar-teacher-card .name{font-size:0.9rem;font-weight:600;color:#78350f;}
    .sidebar-teacher-card .role{font-size:0.78rem;color:#92400e;}

    /* MAIN */
    .main{
      padding:20px 40px 32px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner{max-width:1260px;margin:0 auto;}

    .page-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
    }
    .page-title-line{
      display:flex;
      align-items:center;
      gap:10px;
    }
    .page-title-icon{
      width:34px;height:34px;border-radius:999px;
      background:#fff3d6;
      display:flex;align-items:center;justify-content:center;
      color:#d97706;
      box-shadow:0 10px 20px rgba(250,204,21,0.35);
    }
    .page-header h2{margin:0;font-size:1.45rem;}
    .page-header p{margin:4px 0 0;font-size:0.9rem;color:var(--text-muted);}

    .page-header-clipart{
      display:flex;align-items:center;gap:12px;
    }
    .page-header-clipart img{
      height:60px;width:auto;
      animation:floaty 6s ease-in-out infinite;
    }
    .page-header-chip{
      padding:6px 10px;border-radius:999px;
      background:#fff7ea;font-size:0.8rem;color:#b45309;
    }
    @keyframes floaty{
      0%,100%{transform:translateY(0);}
      50%{transform:translateY(-6px);}
    }

    .content-grid{
      display:grid;
      grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1.1fr);
      gap:18px;
      align-items:flex-start;
    }

    .card{
      background:#fff;
      border-radius:20px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:16px 18px 18px;
    }

    .card-title{margin:0 0 6px;font-size:1rem;}
    .card-sub{margin:0 0 10px;font-size:0.85rem;color:var(--text-muted);}

    .summary-chips{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:12px;
      font-size:0.82rem;
    }
    .summary-chip{
      padding:6px 10px;
      border-radius:999px;
      background:#fff7ea;
      color:#b45309;
    }

    .filter-bar{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      font-size:0.86rem;
      margin-bottom:10px;
    }
    .filter-bar label{
      display:flex;
      align-items:center;
      gap:6px;
    }
    .filter-bar select,
    .filter-bar input[type="date"]{
      padding:6px 9px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:0.86rem;
    }

    .quick-actions{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:8px;
      font-size:0.82rem;
    }
    .quick-actions button{
      border:none;
      border-radius:999px;
      padding:6px 10px;
      cursor:pointer;
      background:#fee2e2;
      color:#b91c1c;
      font-size:0.8rem;
    }
    .quick-actions button.mark-present{
      background:#ecfdf3;
      color:#166534;
    }

    table.att-table,
    table.summary-table{
      width:100%;
      border-collapse:collapse;
      font-size:0.86rem;
      background:#fff;
      border-radius:14px;
      overflow:hidden;
      box-shadow:var(--shadow-card);
      margin-top:4px;
    }
    table.att-table th, table.att-table td,
    table.summary-table th, table.summary-table td{
      padding:8px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    table.att-table thead, table.summary-table thead{
      background:#fbbf24;
      color:#78350f;
    }
    table.att-table tbody tr:nth-child(even),
    table.summary-table tbody tr:nth-child(even){
      background:#fffaf0;
    }
    table.att-table tbody tr:hover,
    table.summary-table tbody tr:hover{
      background:#fff7e6;
    }

    .status-select{
      padding:6px 9px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:0.84rem;
    }

    .btn-save{
      margin-top:10px;
      border:none;
      border-radius:999px;
      padding:9px 18px;
      background:var(--accent);
      color:#fff;
      font-weight:600;
      font-size:0.9rem;
      cursor:pointer;
      box-shadow:0 10px 24px rgba(245,158,11,0.45);
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    .btn-save:hover{filter:brightness(1.03);}

    .tip-list{
      list-style:none;
      margin:0;
      padding:0;
      font-size:0.86rem;
      color:var(--text-muted);
    }
    .tip-list li{
      display:flex;
      gap:6px;
      padding:4px 0;
    }
    .tip-dot{
      width:8px;height:8px;border-radius:999px;
      background:var(--accent);margin-top:6px;
    }

    .progress-wrapper{
      display:flex;
      align-items:center;
      gap:6px;
      font-size:0.78rem;
      color:var(--text-muted);
    }
    .progress-bar{
      flex:1;
      height:7px;
      border-radius:999px;
      background:#f3e5d7;
      overflow:hidden;
    }
    .progress-fill{
      height:100%;
      border-radius:999px;
      background:linear-gradient(90deg,#f59e0b,#f97316);
      width:0;
      transition:width .4s ease-out;
    }

    .side-illustration{
      text-align:center;
      margin-bottom:10px;
    }
    .side-illustration img{
      max-width:160px;width:100%;
      animation:floaty 7s ease-in-out infinite;
    }
    .side-illustration p{
      font-size:0.82rem;
      color:var(--text-muted);
      margin-top:6px;
    }

    .class-average-bar{
      margin:8px 0 12px;
      font-size:0.8rem;
    }
    .class-average-bar .bar{
      height:8px;
      border-radius:999px;
      background:#f3e5d7;
      overflow:hidden;
      margin-top:4px;
    }
    .class-average-bar .fill{
      height:100%;
      border-radius:999px;
      background:linear-gradient(90deg,#22c55e,#16a34a);
      width:0;
      transition:width .4s ease-out;
    }

    @media(max-width:1100px){
      .app-shell{grid-template-columns:220px 1fr;}
      .content-grid{grid-template-columns:1fr;}
    }
    @media(max-width:800px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:16px;}
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
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php" class="active"><i class="fas fa-user-check"></i> Attendance</a>
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
        <div class="page-title-line">
          <div class="page-title-icon">
            <i class="fas fa-user-check"></i>
          </div>
          <div>
            <h2>Attendance</h2>
            <p>Select a class and date, then mark attendance—records feed into each student’s profile.</p>
          </div>
        </div>
        <div class="page-header-clipart">
          <span class="page-header-chip">Today’s snapshot updates as you mark ✅</span>
          <img src="../../assets/img/illustrations/attendance-illustration.png"
               alt="Attendance Illustration"
               onerror="this.style.display='none';" />
        </div>
      </header>

      <section class="content-grid">
        <!-- LEFT: main form -->
        <div class="card">
          <h3 class="card-title">Today’s Attendance</h3>
          <p class="card-sub">Choose a class and date, then quickly mark students present or absent.</p>

          <div class="summary-chips">
            <span class="summary-chip">
              Class: <strong><?= htmlspecialchars(class_label($selectedClass)) ?></strong>
            </span>
            <span class="summary-chip">
              Date: <strong><?= htmlspecialchars($selectedDate) ?></strong>
            </span>
            <span class="summary-chip">
              Total students: <strong><?= count($students) ?></strong>
            </span>
            <span class="summary-chip">
              Present today: <strong><?= $presentToday ?></strong>
            </span>
            <span class="summary-chip">
              Absent today: <strong><?= $absentToday ?></strong>
            </span>
          </div>

          <!-- class/date selector -->
          <form method="get" class="filter-bar">
            <label>
              Class:
              <select name="class" onchange="this.form.submit()">
                <?php if (empty($classes)): ?>
                  <option value="">No classes</option>
                <?php else: ?>
                  <option value="">Select class</option>
                  <?php foreach ($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= (string)$c === (string)$selectedClass ? 'selected' : '' ?>>
                      <?= htmlspecialchars('Class ' . $c) ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </label>
            <label>
              Date:
              <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()" />
            </label>
          </form>

          <?php if ($selectedClass === '' || empty($students)): ?>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:4px;">
              Start by selecting a class. Once students are enrolled in that class, you’ll see them here.
            </p>
          <?php else: ?>
            <form method="post" id="attendanceForm">
              <input type="hidden" name="class" value="<?= htmlspecialchars($selectedClass) ?>" />
              <input type="hidden" name="date"  value="<?= htmlspecialchars($selectedDate) ?>" />

              <div class="quick-actions">
                <span style="font-weight:600;color:#4b5563;">Quick actions:</span>
                <button type="button" class="mark-present" onclick="setAllStatus('present')">
                  <i class="fas fa-check-circle"></i> Mark all Present
                </button>
                <button type="button" onclick="setAllStatus('absent')">
                  <i class="fas fa-times-circle"></i> Mark all Absent
                </button>
                <button type="button" onclick="setAllStatus('')">
                  <i class="fas fa-eraser"></i> Clear All
                </button>
              </div>

              <table class="att-table">
                <thead>
                  <tr>
                    <th style="width:55%;">Student</th>
                    <th>Status for <?= htmlspecialchars($selectedDate) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($students as $stu):
                    $id     = (int)$stu['id'];
                    $name   = $stu['name'];
                    $status = $attendanceStatus[$id] ?? '';
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($name) ?></td>
                      <td>
                        <select name="status[<?= $id ?>]" class="status-select">
                          <option value="" <?= $status === '' ? 'selected' : '' ?>>Not set</option>
                          <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                          <option value="absent"  <?= $status === 'absent'  ? 'selected' : '' ?>>Absent</option>
                        </select>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Save Attendance
              </button>
            </form>
          <?php endif; ?>
        </div>

        <!-- RIGHT: insights -->
        <aside class="card">
          <div class="side-illustration">
            <img src="../../assets/img/illustrations/attendance-fun.png"
                 alt="Attendance Fun"
                 onerror="this.style.display='none';" />
            <p>Consistency is key – even a quick daily check keeps analytics meaningful.</p>
          </div>

          <h3 class="card-title">Attendance Insights</h3>
          <p class="card-sub">High-level patterns across all recorded days for this class.</p>

          <div class="class-average-bar">
            <div>
              Class average: 
              <strong><?= $classAverage !== null ? $classAverage . '%' : '—' ?></strong>
              <?php if ($classAverage !== null): ?>
                · Below <?= $threshold ?>%: <strong><?= $belowThresholdCount ?> student(s)</strong>
              <?php endif; ?>
            </div>
            <div class="bar">
              <div class="fill" id="classAverageFill"></div>
            </div>
          </div>

          <?php if (!empty($students)): ?>
            <table class="summary-table" id="summaryTable">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Days Present</th>
                  <th>Days Absent</th>
                  <th style="width:36%;">Attendance %</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $stu):
                  $id   = (int)$stu['id'];
                  $info = $summary[$id] ?? ['present'=>0,'absent'=>0,'percent'=>null];
                  $pct  = $info['percent'];
                ?>
                  <tr data-percent="<?= $pct !== null ? $pct : 0 ?>">
                    <td><?= htmlspecialchars($stu['name']) ?></td>
                    <td><?= $info['present'] ?></td>
                    <td><?= $info['absent'] ?></td>
                    <td>
                      <div class="progress-wrapper">
                        <div class="progress-bar">
                          <div class="progress-fill"></div>
                        </div>
                        <span><?= $pct !== null ? $pct.'%' : '—' ?></span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p style="font-size:0.86rem;color:var(--text-muted);margin:6px 0 12px;">
              Once you start recording attendance, you’ll see long-term trends here.
            </p>
          <?php endif; ?>

          <h4 style="margin:14px 0 6px;font-size:0.9rem;">Pro tips</h4>
          <ul class="tip-list">
            <li><span class="tip-dot"></span><span>Mark attendance right after roll call to avoid forgetting.</span></li>
            <li><span class="tip-dot"></span><span>Watch for students whose attendance dips below 75% and check-in early.</span></li>
            <li><span class="tip-dot"></span><span>Use the “Mark all present” shortcut and only adjust absentees.</span></li>
          </ul>
        </aside>
      </section>
    </div>
  </main>
</div>

<script>
  // Quick actions
  function setAllStatus(value) {
    document.querySelectorAll('.status-select').forEach(sel => {
      sel.value = value;
    });
  }

  // Animate per-student bars
  (function () {
    const rows = document.querySelectorAll('#summaryTable tbody tr');
    rows.forEach(row => {
      const pct = parseFloat(row.dataset.percent || '0');
      const fill = row.querySelector('.progress-fill');
      if (fill) {
        requestAnimationFrame(() => {
          fill.style.width = Math.min(Math.max(pct, 0), 100) + '%';
        });
      }
    });

    const avg = <?= $classAverage !== null ? (float)$classAverage : 0 ?>;
    const classFill = document.getElementById('classAverageFill');
    if (classFill) {
      requestAnimationFrame(() => {
        classFill.style.width = Math.min(Math.max(avg, 0), 100) + '%';
      });
    }
  })();
</script>
</body>
</html>
