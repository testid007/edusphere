<?php
// dashboards/student/assignments.php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$student_user_id = (int)$_SESSION['user_id'];
$student_name    = $_SESSION['student_name']  ?? ($_SESSION['first_name'] ?? 'Student');
$student_email   = $_SESSION['student_email'] ?? ($_SESSION['email'] ?? 'student@example.com');
$student_avatar  = '../../assets/img/user.jpg';

// --------- Resolve student class from students.class (numeric) ----------
$student_class_number = null;
try {
    $stmt = $conn->prepare("SELECT class FROM students WHERE user_id = ?");
    $stmt->execute([$student_user_id]);
    $student_class_number = $stmt->fetchColumn();
} catch (Exception $e) {
    $student_class_number = null;
}

if ($student_class_number === null || $student_class_number === '') {
    $student_class_label = 'Unknown';
} else {
    // You can change this mapping if you use 1–10 for classes differently
    $student_class_label = 'Class ' . (int)$student_class_number;
}

// ---------- Handle submission upload ----------
$submission_flash = '';

function moveUploadedSubmissionFile(): ?string {
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['submission_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > 10 * 1024 * 1024) { // 10 MB limit for submissions
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/submissions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fname = uniqid('sub_', true) . '.' . $ext;
    $dest  = $uploadDir . $fname;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'uploads/submissions/' . $fname;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);

    if ($assignment_id > 0) {
        $fileUrl = moveUploadedSubmissionFile();

        try {
            // Check if already submitted (by email)
            $check = $conn->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_email = ?");
            $check->execute([$assignment_id, $student_email]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                // update
                $sql = "UPDATE submissions 
                        SET submitted_at = NOW()";
                $params = [];
                if ($fileUrl !== null) {
                    $sql .= ", file_url = ?";
                    $params[] = $fileUrl;
                }
                $sql .= " WHERE id = ?";
                $params[] = $existingId;
                $upd = $conn->prepare($sql);
                $upd->execute($params);
            } else {
                // insert
                $ins = $conn->prepare("
                    INSERT INTO submissions (student_email, assignment_id, file_url, submitted_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $ins->execute([$student_email, $assignment_id, $fileUrl]);
            }

            $submission_flash = 'Assignment submitted successfully.';
        } catch (Exception $e) {
            $submission_flash = 'Error submitting assignment: ' . $e->getMessage();
        }
    } else {
        $submission_flash = 'Invalid assignment selected.';
    }
}

// ---------- Stats & list for this student's class ----------
$stats = [
    'total_assignments'    => 0,
    'upcoming_assignments' => 0,
    'overdue_assignments'  => 0,
    'submitted_assignments'=> 0,
];

$assignments = [];

function timeLeftForAssignment(?string $dueDate): string {
    if (empty($dueDate)) return 'No due date';

    try {
        $due = new DateTime($dueDate . ' 23:59:59');
        $now = new DateTime();

        if ($due < $now) {
            return 'Overdue';
        }

        $diff = $now->diff($due);
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' left';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' left';
        }
        return 'Due today';
    } catch (Exception $e) {
        return '';
    }
}

try {
    // Total for class (not archived)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM assignments WHERE class_name = ? AND is_archived = 0");
    $stmt->execute([$student_class_label]);
    $stats['total_assignments'] = (int)$stmt->fetchColumn();

    // Upcoming unsubmitted (within 7 days)
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM assignments a
        LEFT JOIN submissions s
          ON a.id = s.assignment_id AND s.student_email = ?
        WHERE a.class_name = ?
          AND a.is_archived = 0
          AND a.due_date >= CURDATE()
          AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND s.id IS NULL
    ");
    $stmt->execute([$student_email, $student_class_label]);
    $stats['upcoming_assignments'] = (int)$stmt->fetchColumn();

    // Overdue unsubmitted
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM assignments a
        LEFT JOIN submissions s
          ON a.id = s.assignment_id AND s.student_email = ?
        WHERE a.class_name = ?
          AND a.is_archived = 0
          AND a.due_date IS NOT NULL
          AND a.due_date < CURDATE()
          AND s.id IS NULL
    ");
    $stmt->execute([$student_email, $student_class_label]);
    $stats['overdue_assignments'] = (int)$stmt->fetchColumn();

    // Submitted count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM submissions WHERE student_email = ?");
    $stmt->execute([$student_email]);
    $stats['submitted_assignments'] = (int)$stmt->fetchColumn();

    // Full list for class, with per-student submission status
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.due_date,
            a.status AS assignment_status,
            a.class_name,
            a.file_url AS assignment_file,
            s.submitted_at,
            s.file_url AS submission_file
        FROM assignments a
        LEFT JOIN submissions s
           ON a.id = s.assignment_id AND s.student_email = :email
        WHERE a.class_name = :class_name
          AND a.is_archived = 0
        ORDER BY (a.due_date IS NULL), a.due_date ASC, a.id ASC
    ");
    $stmt->execute([
        ':email'      => $student_email,
        ':class_name' => $student_class_label
    ]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // swallow for now, keep empty arrays
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Assignments | EduSphere</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
      grid-template-columns:repeat(4,minmax(0,1fr));
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

    .filter-row{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      align-items:center;
      margin-bottom:10px;
    }
    .chip-group{
      display:flex;
      flex-wrap:wrap;
      gap:6px;
    }
    .chip{
      padding:4px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      font-size:0.8rem;
      background:#f9fafb;
      cursor:pointer;
    }
    .chip.active{
      background:var(--accent-soft);
      border-color:#fed7aa;
      color:#92400e;
      font-weight:600;
    }
    .search-input{
      flex:1;
      min-width:180px;
      padding:7px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      font-size:0.86rem;
      background:#f9fafb;
    }

    .assignment-list{
      display:flex;
      flex-direction:column;
      gap:10px;
    }
    .assignment-card{
      background:#fff;
      border-radius:14px;
      padding:12px 14px;
      box-shadow:0 8px 18px rgba(15,23,42,0.04);
      border:1px solid var(--border-soft);
      display:flex;
      flex-direction:column;
      gap:6px;
    }
    .assignment-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
    }
    .assignment-title{
      font-weight:600;
      font-size:1rem;
    }
    .assignment-meta{
      font-size:0.85rem;
      color:var(--text-muted);
    }
    .badge{
      display:inline-block;
      padding:2px 10px;
      border-radius:999px;
      font-size:0.75rem;
      font-weight:600;
      text-transform:uppercase;
      letter-spacing:0.04em;
    }
    .badge-open{background:#e8f5e9;color:#166534;}
    .badge-closed{background:#ffebee;color:#b91c1c;}
    .badge-submitted{background:#e0f2fe;color:#1d4ed8;}
    .time-left{
      font-weight:600;
      font-size:0.86rem;
    }
    .time-left.overdue{color:#b91c1c;}
    .time-left.today{color:#b45309;}

    .assignment-actions{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      font-size:0.85rem;
      margin-top:4px;
      align-items:center;
    }
    .link-btn{
      border:none;
      background:none;
      padding:0;
      color:#2563eb;
      cursor:pointer;
      text-decoration:none;
      font-weight:600;
    }
    .link-btn:hover{text-decoration:underline;}

    .submit-form{
      display:inline-flex;
      gap:6px;
      align-items:center;
      margin-top:4px;
      flex-wrap:wrap;
    }
    .submit-form input[type="file"]{
      font-size:.78rem;
    }
    .submit-form button{
      border:none;
      border-radius:999px;
      padding:4px 9px;
      font-size:.78rem;
      background:#22c55e;
      color:#fff;
      cursor:pointer;
    }
    .flash-msg{
      padding:8px 10px;
      border-radius:10px;
      margin-bottom:8px;
      font-size:.85rem;
    }
    .flash-ok{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;}
    .flash-err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}

    .empty-state{
      font-size:0.9rem;
      color:var(--text-muted);
      padding:6px 2px;
    }

    @media(max-width:1100px){
      .app-shell{grid-template-columns:220px 1fr;}
      .stats-row{grid-template-columns:repeat(2,minmax(0,1fr));}
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
        <a href="assignments.php" class="active"><i class="fas fa-book"></i> Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> Results</a>
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
          <h2>My Assignments</h2>
          <p>Class: <?= htmlspecialchars($student_class_label) ?> · Track what’s due and what’s done.</p>
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
          <div class="stat-label">Total</div>
          <div class="stat-value"><?= $stats['total_assignments'] ?></div>
          <div class="stat-sub">All assignments for your class</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Upcoming</div>
          <div class="stat-value"><?= $stats['upcoming_assignments'] ?></div>
          <div class="stat-sub">Due in next 7 days</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Overdue</div>
          <div class="stat-value"><?= $stats['overdue_assignments'] ?></div>
          <div class="stat-sub">Need attention</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Submitted</div>
          <div class="stat-value"><?= $stats['submitted_assignments'] ?></div>
          <div class="stat-sub">You’ve already turned these in</div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h4>Assignment List</h4>
          <span>Search, filter, and submit your work.</span>
        </div>

        <?php if ($submission_flash): ?>
          <div class="flash-msg <?= str_starts_with($submission_flash, 'Error') ? 'flash-err' : 'flash-ok' ?>">
            <?= htmlspecialchars($submission_flash) ?>
          </div>
        <?php endif; ?>

        <div class="filter-row">
          <div class="chip-group" id="statusChips">
            <button type="button" class="chip active" data-status="all">All</button>
            <button type="button" class="chip" data-status="open">Open</button>
            <button type="button" class="chip" data-status="submitted">Submitted</button>
            <button type="button" class="chip" data-status="overdue">Overdue</button>
          </div>
          <input type="text" id="searchInput" class="search-input" placeholder="Search by title..." />
        </div>

        <div class="assignment-list" id="assignmentList">
          <?php if (empty($assignments)): ?>
            <div class="empty-state">
              No assignments have been posted for your class yet.
            </div>
          <?php else: ?>
            <?php foreach ($assignments as $a): ?>
              <?php
                $timeLeft = timeLeftForAssignment($a['due_date']);
                $timeClass = '';
                if ($timeLeft === 'Overdue')      $timeClass = 'overdue';
                elseif ($timeLeft === 'Due today')$timeClass = 'today';

                $isSubmitted = !empty($a['submitted_at']);
                $cardStatus  = $isSubmitted ? 'submitted' : (($timeLeft === 'Overdue') ? 'overdue' : 'open');
              ?>
              <div class="assignment-card"
                   data-status="<?= htmlspecialchars($cardStatus) ?>"
                   data-title="<?= htmlspecialchars(strtolower($a['title'])) ?>">
                <div class="assignment-header">
                  <div>
                    <div class="assignment-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="assignment-meta">
                      Due: <?= $a['due_date'] ? htmlspecialchars($a['due_date']) : 'Not set' ?>
                      · Class: <?= htmlspecialchars($a['class_name']) ?>
                    </div>
                  </div>
                  <div>
                    <?php if ($isSubmitted): ?>
                      <span class="badge badge-submitted">Submitted</span>
                    <?php else: ?>
                      <span class="badge badge-<?= strtolower($a['assignment_status'] ?? 'open') === 'open' ? 'open' : 'closed' ?>">
                        <?= htmlspecialchars($a['assignment_status'] ?? 'Open') ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="assignment-meta">
                  <span class="time-left <?= $timeClass ?>">
                    <?= htmlspecialchars($timeLeft) ?>
                  </span>
                  <?php if (!empty($a['submitted_at'])): ?>
                    · Submitted on <?= htmlspecialchars(date('Y-m-d H:i', strtotime($a['submitted_at']))) ?>
                  <?php endif; ?>
                </div>

                <div class="assignment-actions">
                  <?php if (!empty($a['assignment_file'])): ?>
                    <a class="link-btn"
                       href="<?= htmlspecialchars($a['assignment_file']) ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                      <i class="fa-solid fa-download"></i> Download assignment
                    </a>
                  <?php endif; ?>

                  <?php if (!empty($a['submission_file'])): ?>
                    <a class="link-btn"
                       href="<?= htmlspecialchars($a['submission_file']) ?>"
                       target="_blank">
                      <i class="fa-solid fa-file"></i> View your file
                    </a>
                  <?php endif; ?>
                </div>

                <!-- SUBMISSION FORM -->
                <?php if (!$isSubmitted || empty($a['submission_file'])): ?>
                  <form class="submit-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                    <input type="file" name="submission_file" required>
                    <button type="submit" name="submit_assignment">
                      <i class="fa-solid fa-upload"></i> Submit
                    </button>
                  </form>
                <?php else: ?>
                  <div class="assignment-meta" style="font-size:.78rem;">
                    You can re-submit by uploading again – the latest file will replace the previous one.
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  const chips = document.querySelectorAll('#statusChips .chip');
  const searchInput = document.getElementById('searchInput');
  const cards = document.querySelectorAll('.assignment-card');

  function applyFilters() {
    const activeChip = document.querySelector('#statusChips .chip.active');
    const status = activeChip ? activeChip.dataset.status : 'all';
    const q = (searchInput.value || '').toLowerCase().trim();

    cards.forEach(card => {
      const cardStatus = card.dataset.status;
      const title = card.dataset.title || '';
      let visible = true;

      if (status !== 'all' && status !== cardStatus) visible = false;
      if (q && !title.includes(q)) visible = false;

      card.style.display = visible ? '' : 'none';
    });
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      applyFilters();
    });
  });

  searchInput.addEventListener('input', applyFilters);
</script>
</body>
</html>
