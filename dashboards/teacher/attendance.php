<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_name  = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

// ---------- Fetch class list (adjust column if needed) ----------
$classes = [];
try {
    // TODO: if your column is students.class instead of class_name, change here
    $stmt = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name IS NOT NULL AND class_name <> '' ORDER BY class_name");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $classes = [];
}

$selectedClass = $_GET['class'] ?? ($classes[0] ?? '');
$selectedDate  = $_GET['date']  ?? date('Y-m-d');

// ---------- Handle POST: save attendance ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedClass = $_POST['class'] ?? $selectedClass;
    $selectedDate  = $_POST['date']  ?? $selectedDate;
    $statuses      = $_POST['status'] ?? []; // status[student_id] = present/absent

    if ($selectedDate) {
        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            if ($studentId <= 0) continue;
            if ($status === 'present' || $status === 'absent') {
                // upsert
                $stmt = $conn->prepare("
                    INSERT INTO attendance (student_id, date, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $stmt->execute([$studentId, $selectedDate, $status]);
            } else {
                // if status is empty, optional: delete record
                $stmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ? AND date = ?");
                $stmt->execute([$studentId, $selectedDate]);
            }
        }
    }

    // redirect to avoid resubmit
    header('Location: attendance.php?class=' . urlencode($selectedClass) . '&date=' . urlencode($selectedDate));
    exit;
}

// ---------- Fetch students of selected class ----------
$students = [];
if ($selectedClass !== '') {
    // TODO: adjust students.class_name if your column name differs
    $stmt = $conn->prepare("
        SELECT s.user_id AS id, CONCAT(u.first_name, ' ', u.last_name) AS name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student' AND s.class_name = ?
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Fetch attendance status for selected date ----------
$attendanceStatus = []; // [student_id] => 'present'/'absent'
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

// ---------- Summary per student (overall) ----------
$summary = [];
if (!empty($students)) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT student_id,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END)  AS absent_days
        FROM attendance
        WHERE student_id IN ($placeholders)
        GROUP BY student_id
    ");
    $stmt->execute($ids);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $present = (int)$row['present_days'];
        $absent  = (int)$row['absent_days'];
        $total   = $present + $absent;
        $percent = $total > 0 ? round(($present / $total) * 100, 1) : null;
        $summary[(int)$row['student_id']] = [
            'present' => $present,
            'absent'  => $absent,
            'percent' => $percent,
        ];
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
    .filter-bar {
      display:flex;
      flex-wrap:wrap;
      gap:12px;
      align-items:center;
      margin-bottom:15px;
    }
    .filter-bar select,.filter-bar input[type="date"] {
      padding:6px 10px;
      border-radius:6px;
      border:1px solid #ccc;
    }
    table.att-table, table.summary-table {
      width:100%;
      border-collapse:collapse;
      background:#fff;
      border-radius:10px;
      overflow:hidden;
      margin-bottom:20px;
    }
    table.att-table th, table.att-table td,
    table.summary-table th, table.summary-table td {
      border:1px solid #e0e0e0;
      padding:8px 10px;
      font-size:0.9rem;
      text-align:left;
    }
    table.att-table th, table.summary-table th {
      background:#4caf50;
      color:#fff;
    }
    .btn-save {
      background:#4caf50;
      color:#fff;
      border:none;
      border-radius:8px;
      padding:8px 16px;
      cursor:pointer;
      font-weight:600;
    }
  </style>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php" class="active"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Teacher" />
        <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
        <div class="email"><?= htmlspecialchars($teacher_email) ?></div>
      </div>
    </aside>

    <main class="main">
      <header class="header">
        <h2>Attendance</h2>
        <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
      </header>

      <section class="table-container">
        <h3>Mark Attendance</h3>

        <form method="get" class="filter-bar">
          <label>
            Class:
            <select name="class" onchange="this.form.submit()">
              <?php if (empty($classes)): ?>
                <option value="">No classes</option>
              <?php else: ?>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>" <?= $c === $selectedClass ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
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
          <p>No students found for this class.</p>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="class" value="<?= htmlspecialchars($selectedClass) ?>" />
            <input type="hidden" name="date"  value="<?= htmlspecialchars($selectedDate) ?>" />

            <table class="att-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Status for <?= htmlspecialchars($selectedDate) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $stu): 
                  $id   = (int)$stu['id'];
                  $name = $stu['name'];
                  $status = $attendanceStatus[$id] ?? '';
                ?>
                  <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td>
                      <select name="status[<?= $id ?>]">
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>Not set</option>
                        <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent"  <?= $status === 'absent' ? 'selected' : '' ?>>Absent</option>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <button type="submit" class="btn-save">Save Attendance</button>
          </form>

          <h3>Attendance Summary (All Time)</h3>
          <table class="summary-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Days Present</th>
                <th>Days Absent</th>
                <th>Attendance %</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $stu):
                $id = (int)$stu['id'];
                $info = $summary[$id] ?? ['present'=>0,'absent'=>0,'percent'=>null];
              ?>
                <tr>
                  <td><?= htmlspecialchars($stu['name']) ?></td>
                  <td><?= $info['present'] ?></td>
                  <td><?= $info['absent'] ?></td>
                  <td><?= $info['percent'] !== null ? $info['percent'].'%' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
