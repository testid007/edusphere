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

// ---------- STATIC CLASS OPTIONS (Nursery, LKG, UKG, Class 1–10) ----------
$classOptions = ['Nursery', 'LKG', 'UKG'];
for ($i = 1; $i <= 10; $i++) {
    $classOptions[] = "Class $i";
}

// ---------- HANDLE CREATE / UPDATE / ARCHIVE ----------
$flash = '';

function moveUploadedAssignmentFile(): ?string {
    if (!isset($_FILES['assignment_file']) || $_FILES['assignment_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['assignment_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fname = uniqid('assign_', true) . '.' . $ext;
    $dest  = $uploadDir . $fname;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'uploads/' . $fname; // relative URL for browser
}

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create') {
    $title     = trim($_POST['title'] ?? '');
    $due_date  = $_POST['due_date'] ?? '';
    $status    = $_POST['status'] ?? 'Open';
    $className = $_POST['class_name'] ?? '';

    if (
        $title !== '' &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date) &&
        in_array($status, ['Open', 'Closed'], true) &&
        $className !== ''
    ) {
        $fileUrl = moveUploadedAssignmentFile();

        $stmt = $conn->prepare("
            INSERT INTO assignments (title, due_date, status, is_archived, class_name, file_url, teacher_name, created_at)
            VALUES (?, ?, ?, 0, ?, ?, ?, NOW())
        ");
        if ($stmt->execute([$title, $due_date, $status, $className, $fileUrl, $teacher_name])) {
            $flash = 'Assignment created successfully.';
        } else {
            $flash = 'Error: Database error while creating assignment.';
        }
    } else {
        $flash = 'Error: Please fill all required fields correctly.';
    }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update') {
    $id        = (int)($_POST['id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $due_date  = $_POST['due_date'] ?? '';
    $status    = $_POST['status'] ?? 'Open';
    $className = $_POST['class_name'] ?? '';
    $existing  = $_POST['existing_file_url'] ?? '';

    if (
        $id > 0 &&
        $title !== '' &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date) &&
        in_array($status, ['Open', 'Closed'], true) &&
        $className !== ''
    ) {
        $newFile = moveUploadedAssignmentFile();
        $fileUrl = $newFile ?: $existing;

        $stmt = $conn->prepare("
            UPDATE assignments
            SET title = ?, due_date = ?, status = ?, class_name = ?, file_url = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$title, $due_date, $status, $className, $fileUrl, $id])) {
            $flash = 'Assignment updated successfully.';
        } else {
            $flash = 'Error: Database error while updating assignment.';
        }
    } else {
        $flash = 'Error: Please fill all required fields correctly for update.';
    }
}

// ARCHIVE (GET ?archive=ID)
if (isset($_GET['archive'])) {
    $id = (int)$_GET['archive'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE assignments SET is_archived = 1, status = 'Closed' WHERE id = ?");
        if ($stmt->execute([$id])) {
            $flash = 'Assignment archived. Students will no longer see it.';
        } else {
            $flash = 'Error: Failed to archive assignment.';
        }
    }
}

// LOAD CURRENT EDIT (GET ?edit=ID)
$editAssignment = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
        $stmt->execute([$id]);
        $editAssignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// LOAD ASSIGNMENTS + SUBMISSION COUNTS
$assignments = [];
try {
    $stmt = $conn->query("
        SELECT a.*,
               COUNT(s.id) AS submission_count
        FROM assignments a
        LEFT JOIN submissions s ON s.assignment_id = a.id
        WHERE a.is_archived = 0
        GROUP BY a.id
        ORDER BY a.due_date ASC, a.id ASC
    ");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $flash = 'Error loading assignments: ' . $e->getMessage();
}

// ---------- STATS & INSIGHTS ----------
$totalAssignments  = count($assignments);
$openAssignments   = 0;
$closedAssignments = 0;
$upcoming7         = 0;
$overdueOpen       = 0;

$today    = new DateTimeImmutable('today');
$weekAhead = $today->modify('+7 days');

$classCount = [];   // class_name => count
$nextDue    = null; // ['title' => ..., 'class_name' => ..., 'due_date' => DateTime]

foreach ($assignments as $a) {
    $status = $a['status'] ?? 'Open';
    if (strcasecmp($status, 'Open') === 0) {
        $openAssignments++;
    } else {
        $closedAssignments++;
    }

    $className = $a['class_name'] ?? 'Unknown';
    $classCount[$className] = ($classCount[$className] ?? 0) + 1;

    if (!empty($a['due_date'])) {
        try {
            $due = new DateTimeImmutable($a['due_date']);
        } catch (Exception $e) {
            $due = null;
        }

        if ($due) {
            // Upcoming in next 7 days (including today)
            if ($due >= $today && $due <= $weekAhead && strcasecmp($status, 'Open') === 0) {
                $upcoming7++;
            }

            // Overdue open
            if ($due < $today && strcasecmp($status, 'Open') === 0) {
                $overdueOpen++;
            }

            // Next due
            if (!$nextDue || $due < $nextDue['due']) {
                $nextDue = [
                    'title'      => $a['title'],
                    'class_name' => $className,
                    'due'        => $due
                ];
            }
        }
    }
}

// Class with most assignments
$topClassName  = null;
$topClassCount = 0;
foreach ($classCount as $cls => $cnt) {
    if ($cnt > $topClassCount) {
        $topClassCount = $cnt;
        $topClassName  = $cls;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Assignments | Teacher Dashboard</title>
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
      --accent-soft: #fff5e6;

      --text-main: #111827;
      --text-muted: #6b7280;

      --border-soft: #f3e5d7;
      --shadow-card: 0 14px 30px rgba(15,23,42,0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: radial-gradient(circle at top left,#fff7e5 0,#fefcf8 45%,#f5eee9 100%);
      color: var(--text-main);
    }

    /* ===== APP SHELL ===== */
    .app-shell {
      display: grid;
      grid-template-columns: 260px 1fr;
      min-height: 100vh;
      width: 100vw;
      max-width: 100%;
      overflow-x: hidden;
      background: var(--bg-shell);
    }

    /* ===== SIDEBAR ===== */
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
      margin-bottom: 26px;
    }

    .logo img { height: 40px; }

    .logo span {
      font-weight: 700;
      font-size: 1.1rem;
      color: #1f2937;
      letter-spacing: 0.05em;
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
      padding: 10px 14px;
      border-radius: 999px;
      color: #6b7280;
      font-size: 0.92rem;
      text-decoration: none;
      transition: background 0.15s ease-out, color 0.15s ease-out, transform 0.15s ease-out,
                  box-shadow 0.15s ease-out;
    }

    .nav a i {
      width: 20px;
      text-align: center;
      color: #9ca3af;
    }

    .nav a.active {
      background: var(--accent-soft);
      color: #92400e;
      font-weight: 600;
      box-shadow: 0 10px 22px rgba(245,158,11,0.35);
      transform: translateX(2px);
    }

    .nav a.active i { color: #f59e0b; }

    .nav a:hover {
      background: #ffeeda;
      color: #92400e;
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

    /* ===== MAIN AREA ===== */
    .main {
      width: 100%;
      max-width: none;
      margin: 0;
      padding: 26px 40px 40px;
      background: radial-gradient(circle at top left, #fff7e6 0, #ffffff 55%);
    }

    .main-inner {
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .page-header h2 {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 700;
    }

    .page-header p {
      margin: 4px 0 0;
      color: var(--text-muted);
      font-size: 0.93rem;
    }

    .page-chip {
      padding: 6px 14px;
      border-radius: 999px;
      background: #fff7ea;
      color: #b45309;
      font-size: 0.8rem;
      border: 1px solid #fed7aa;
    }

    /* ===== STATS ROW ===== */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }

    .stat-card {
      background: var(--bg-main);
      border-radius: 16px;
      padding: 10px 14px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
    }

    .stat-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #a16207;
      margin-bottom: 2px;
    }

    .stat-value {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 2px;
      color: var(--text-main);
    }

    .stat-sub {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* ===== LAYOUT WRAPPER ===== */
    .assignments-layout {
      width: 100%;
      display: grid;
      grid-template-columns: minmax(0, 1.1fr);
      gap: 18px;
    }

    /* ===== CARDS ===== */
    .card {
      width: 100%;
      max-width: 100%;
      background: var(--bg-main);
      border-radius: 20px;
      padding: 16px 18px 18px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--border-soft);
      transition: transform 0.16s ease-out, box-shadow 0.16s ease-out;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 40px rgba(15,23,42,0.12);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .card-header h3 {
      margin: 0;
      font-size: 1rem;
    }

    .card-header span {
      font-size: 0.82rem;
      color: var(--text-muted);
      text-align: right;
    }

    /* ===== FLASH MESSAGE ===== */
    .flash {
      margin-bottom: 10px;
      padding: 9px 12px;
      border-radius: 10px;
      font-size: 0.9rem;
    }

    .flash-ok {
      background: #ecfdf5;
      color: #f59e0b;;
      border: 1px solid #bbf7d0;
    }

    .flash-err {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    /* ===== FORM ===== */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px 12px;
      align-items: center;
    }

    .form-grid label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #a16207;
      grid-column: 1 / -1;
      margin-top: 6px;
    }

    .form-grid input[type="text"],
    .form-grid input[type="date"],
    .form-grid select,
    .form-grid input[type="file"] {
      width: 100%;
      padding: 7px 10px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      font-size: 0.9rem;
      background: #f9fafb;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }

    .form-grid input:focus,
    .form-grid select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft);
      background: #ffffff;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 999px;
      border: none;
      background: #f59e0b;;
      color: #fff;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 10px 22px #f59e0b;;
      margin-top: 6px;
    }

    .btn-primary i { font-size: 0.9rem; }

    .btn-primary:hover {
      filter: brightness(1.05);
      transform: translateY(-1px);
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 7px 12px;
      border-radius: 999px;
      border: none;
      background: #e5e7eb;
      color: #374151;
      font-size: 0.8rem;
      cursor: pointer;
      margin-left: 6px;
      text-decoration: none;
    }

    /* ===== TOOLBAR (FILTERS ABOVE TABLE) ===== */
    .assignment-toolbar {
      display: flex;
      gap: 8px;
      margin-bottom: 10px;
      align-items: center;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .toolbar-left {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .toolbar-right {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    .assignment-toolbar input,
    .assignment-toolbar select {
      padding: 7px 10px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      font-size: 0.85rem;
      background: #f9fafb;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }

    .assignment-toolbar input:focus,
    .assignment-toolbar select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft);
      background: #ffffff;
    }

    /* ===== TABLE ===== */
    table.assignments-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.86rem;
      background: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 20px #f59e0b;;
    }

    table.assignments-table thead {
      background: #ecfdf5;
    }

    table.assignments-table th,
    table.assignments-table td {
      padding: 8px 10px;
      border-bottom: 1px solid #f3e5d7;
      text-align: left;
    }

    table.assignments-table th {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #4b5563;
    }

    .row-overdue td {
      background: #fef2f2;
    }

    .status-pill {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 0.74rem;
      font-weight: 600;
    }

    .status-open { background: #ecfdf5; color: #f59e0b;; }
    .status-closed { background: #fef2f2; color: #b91c1c; }

    .badge-overdue {
      display: inline-block;
      margin-left: 4px;
      padding: 2px 6px;
      border-radius: 999px;
      font-size: 0.7rem;
      background: #fee2e2;
      color: #b91c1c;
      font-weight: 600;
    }

    .btn-small {
      padding: 4px 9px;
      border-radius: 999px;
      border: none;
      font-size: 0.78rem;
      cursor: pointer;
      color: #fff;
      text-decoration: none;
      display: inline-block;
    }

    .btn-edit {
      background: #3b82f6;
      margin-right: 4px;
    }

    .btn-archive { background: #f97316; }

    .btn-small:hover {
      filter: brightness(1.05);
      transform: translateY(-0.5px);
    }

    .link-btn,
    .link {
      color: #2563eb;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.84rem;
    }

    .link-btn:hover,
    .link:hover { text-decoration: underline; }

    /* ===== INSIGHTS ===== */
    .insights {
      margin-top: 12px;
      padding-top: 10px;
      border-top: 1px dashed var(--border-soft);
      font-size: 0.88rem;
      color: var(--text-muted);
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .insight-item strong {
      display: block;
      font-size: 0.82rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #a16207;
      margin-bottom: 4px;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1100px) {
      .app-shell {
        grid-template-columns: 220px 1fr;
      }
      .form-grid {
        grid-template-columns: repeat(2, minmax(0,1fr));
      }
      .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 800px) {
      .app-shell {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
      .main {
        padding: 18px 16px 28px;
      }
      .form-grid {
        grid-template-columns: 1fr;
      }
      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
      .stats-row {
        grid-template-columns: 1fr 1fr;
      }
      .insights {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="EduSphere">
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="manage-assignments.php" class="active"><i class="fas fa-book"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="sidebar-teacher-card">
        <img src="<?= htmlspecialchars($teacher_avatar) ?>" alt="Teacher">
        <div>
          <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
          <div class="role">Teacher · EduSphere</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="main-inner">
      <header class="page-header">
        <div>
          <h2>Manage Assignments</h2>
          <p>Create, update, and archive assignments for your classes.</p>
        </div>
        <div class="page-chip">
          When you post, all students in that class see it automatically ✅
        </div>
      </header>

      <!-- Stats row -->
      <section class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Total</div>
          <div class="stat-value"><?= $totalAssignments ?></div>
          <div class="stat-sub">Assignments created so far</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Open</div>
          <div class="stat-value"><?= $openAssignments ?></div>
          <div class="stat-sub">Currently visible to students</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Closed</div>
          <div class="stat-value"><?= $closedAssignments ?></div>
          <div class="stat-sub">Archived or marked complete</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Upcoming (7 days)</div>
          <div class="stat-value"><?= $upcoming7 ?></div>
          <div class="stat-sub">Open tasks due soon</div>
        </div>
      </section>

      <div class="assignments-layout">
        <!-- FORM CARD -->
        <section class="card">
          <div class="card-header">
            <h3><?= $editAssignment ? 'Edit Assignment' : 'Create New Assignment' ?></h3>
          </div>

          <?php if ($flash): ?>
            <?php
              $isError = str_starts_with($flash, 'Error');
            ?>
            <div class="flash <?= $isError ? 'flash-err' : 'flash-ok' ?>">
              <?= htmlspecialchars($flash) ?>
            </div>
          <?php endif; ?>

          <?php $formAction = $editAssignment ? 'update' : 'create'; ?>

          <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="form_action" value="<?= $formAction ?>">
            <?php if ($editAssignment): ?>
              <input type="hidden" name="id" value="<?= (int)$editAssignment['id'] ?>">
              <input type="hidden" name="existing_file_url" value="<?= htmlspecialchars($editAssignment['file_url'] ?? '') ?>">
            <?php endif; ?>

            <label>Assignment Title</label>
            <input type="text" name="title" required
                   value="<?= htmlspecialchars($editAssignment['title'] ?? '') ?>">

            <label>Due Date</label>
            <input type="date" name="due_date" required
                   value="<?= htmlspecialchars($editAssignment['due_date'] ?? '') ?>">

            <label>Status</label>
            <select name="status">
              <?php
              $curStatus = $editAssignment['status'] ?? 'Open';
              ?>
              <option value="Open"   <?= $curStatus === 'Open'   ? 'selected' : '' ?>>Open</option>
              <option value="Closed" <?= $curStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>

            <label>Class</label>
            <select name="class_name" required>
              <option value="">Select Class</option>
              <?php
              $curClass = $editAssignment['class_name'] ?? '';
              foreach ($classOptions as $cls):
              ?>
                <option value="<?= htmlspecialchars($cls) ?>" <?= $curClass === $cls ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cls) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label>Assignment File (PDF/Word, max 5MB)</label>
            <input type="file" name="assignment_file" accept=".pdf,.doc,.docx">

            <div style="grid-column:1/-1;margin-top:4px;">
              <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i>
                <span><?= $editAssignment ? 'Update Assignment' : 'Create Assignment' ?></span>
              </button>
              <?php if ($editAssignment): ?>
                <a href="manage-assignments.php" class="btn-secondary">
                  <i class="fas fa-times"></i> Cancel edit
                </a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <!-- LIST CARD -->
        <section class="card">
          <div class="card-header">
            <h3>Assignment List</h3>
            <span>
              Quick view of each assignment and how many students submitted.
            </span>
          </div>

          <div class="assignment-toolbar">
            <div class="toolbar-left">
              <input type="text" id="searchInput" placeholder="Search by title, class, or date…">
              <select id="filterStatus">
                <option value="">All Status</option>
                <option value="Open">Open</option>
                <option value="Closed">Closed</option>
              </select>
              <select id="filterClass">
                <option value="">All Classes</option>
                <?php foreach ($classOptions as $cls): ?>
                  <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="toolbar-right">
              <span id="visibleCount"><?= $totalAssignments ?> of <?= $totalAssignments ?> assignments shown</span>
            </div>
          </div>

          <?php if (empty($assignments)): ?>
            <p style="font-size:.9rem;color:var(--text-muted);margin:8px 2px;">
              No assignments have been created yet. Start by posting one for your first class.
            </p>
          <?php else: ?>
            <table id="assignmentsTable" class="assignments-table">
              <thead>
              <tr>
                <th>Title</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Class</th>
                <th>Submissions</th>
                <th>File</th>
                <th style="width:130px;">Actions</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($assignments as $a): ?>
                <?php
                  $statusText  = $a['status'] ?? 'Open';
                  $statusClass = (strcasecmp($statusText, 'Open') === 0) ? 'status-open' : 'status-closed';
                  $subCount    = (int)($a['submission_count'] ?? 0);

                  $rowClasses = [];
                  $isOverdue  = false;
                  if (!empty($a['due_date'])) {
                      try {
                          $due = new DateTimeImmutable($a['due_date']);
                          if ($due < $today && strcasecmp($statusText, 'Open') === 0) {
                              $isOverdue = true;
                              $rowClasses[] = 'row-overdue';
                          }
                      } catch (Exception $e) {
                          // ignore parse error
                      }
                  }
                ?>
                <tr
                  class="<?= implode(' ', $rowClasses) ?>"
                  data-title="<?= htmlspecialchars(strtolower($a['title'] ?? '')) ?>"
                  data-class="<?= htmlspecialchars($a['class_name'] ?? '') ?>"
                  data-status="<?= htmlspecialchars($statusText) ?>"
                  data-date="<?= htmlspecialchars($a['due_date'] ?? '') ?>"
                >
                  <td><?= htmlspecialchars($a['title']) ?></td>
                  <td><?= htmlspecialchars($a['due_date']) ?></td>
                  <td>
                    <span class="status-pill <?= $statusClass ?>">
                      <?= htmlspecialchars($statusText) ?>
                    </span>
                    <?php if ($isOverdue): ?>
                      <span class="badge-overdue">Overdue</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($a['class_name'] ?? '') ?></td>
                  <td><?= $subCount ?></td>
                  <td>
                    <?php if (!empty($a['file_url'])): ?>
                      <a class="link" href="<?= htmlspecialchars($a['file_url']) ?>" target="_blank">Download</a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="manage-assignments.php?edit=<?= (int)$a['id'] ?>" class="btn-small btn-edit">
                      Edit
                    </a>
                    <a href="manage-assignments.php?archive=<?= (int)$a['id'] ?>"
                       class="btn-small btn-archive"
                       onclick="return confirm('Archive this assignment? Students will no longer see it.');">
                      Archive
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <!-- Insights under table -->
            <div class="insights">
              <div class="insight-item">
                <strong>Next due</strong>
                <?php if ($nextDue): ?>
                  <?= htmlspecialchars($nextDue['title']) ?>
                  <?php if ($nextDue['class_name']): ?>
                    <br>Class: <?= htmlspecialchars($nextDue['class_name']) ?>
                  <?php endif; ?>
                  <br>Due: <?= $nextDue['due']->format('Y-m-d') ?>
                <?php else: ?>
                  No upcoming due dates.
                <?php endif; ?>
              </div>
              <div class="insight-item">
                <strong>Busiest class</strong>
                <?php if ($topClassName): ?>
                  <?= htmlspecialchars($topClassName) ?> (<?= $topClassCount ?> assignments)
                <?php else: ?>
                  Not enough data yet.
                <?php endif; ?>
              </div>
              <div class="insight-item">
                <strong>Overdue open tasks</strong>
                <?= $overdueOpen ?> assignment<?= $overdueOpen === 1 ? '' : 's' ?> need attention.
              </div>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>
</div>

<script>
  const searchInput  = document.getElementById('searchInput');
  const filterStatus = document.getElementById('filterStatus');
  const filterClass  = document.getElementById('filterClass');
  const table        = document.getElementById('assignmentsTable');
  const visibleCount = document.getElementById('visibleCount');

  let rows = [];
  if (table) {
    rows = Array.from(table.querySelectorAll('tbody tr'));
  }

  const totalAssignments = rows.length;

  function updateVisibleCount() {
    if (!visibleCount) return;
    const visible = rows.filter(r => r.style.display !== 'none').length;
    visibleCount.textContent = `${visible} of ${totalAssignments} assignments shown`;
  }

  function applyFilters() {
    const term   = (searchInput?.value || '').toLowerCase().trim();
    const status = filterStatus?.value || '';
    const cls    = filterClass?.value || '';

    rows.forEach(row => {
      const rTitle  = row.dataset.title || '';
      const rClass  = row.dataset.class || '';
      const rStatus = row.dataset.status || '';
      const rDate   = row.dataset.date   || '';

      const matchTerm   = !term || rTitle.includes(term) || rClass.toLowerCase().includes(term) || rDate.includes(term);
      const matchStatus = !status || rStatus === status;
      const matchClass  = !cls || rClass === cls;

      row.style.display = (matchTerm && matchStatus && matchClass) ? '' : 'none';
    });

    updateVisibleCount();
  }

  if (searchInput)  searchInput.addEventListener('input', applyFilters);
  if (filterStatus) filterStatus.addEventListener('change', applyFilters);
  if (filterClass)  filterClass.addEventListener('change', applyFilters);

  // initial count (in case filters are empty)
  updateVisibleCount();
</script>
</body>
</html>
