<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

$mysqli = new mysqli('localhost', 'root', '', 'edusphere');
if ($mysqli->connect_errno) {
    die('Failed to connect to MySQL: ' . $mysqli->connect_error);
}

$total_days = 31;
$month = 12;
$year = 2025;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_index']);
    $day = intval($_POST['day']);
    $action = $_POST['action'] ?? '';
    $date = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($day, 2, "0", STR_PAD_LEFT);

    if ($student_id && $day >= 1 && $day <= $total_days) {
        if ($action === 'mark_present') {
            $stmt = $mysqli->prepare("REPLACE INTO attendance (student_id, date, status) VALUES (?, ?, 'present')");
            $stmt->bind_param('is', $student_id, $date);
            $stmt->execute();
        } elseif ($action === 'mark_absent' || $action === 'delete_attendance') {
            $stmt = $mysqli->prepare("DELETE FROM attendance WHERE student_id = ? AND date = ?");
            $stmt->bind_param('is', $student_id, $date);
            $stmt->execute();
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo get_attendance_summary($mysqli, $month, $year, $total_days);
        exit;
    }
}

// --- Fetch Students ---
$students = [];
$res = $mysqli->query("
    SELECT s.user_id, u.first_name, u.last_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.role = 'Student'
    ORDER BY u.first_name, u.last_name
");
while ($row = $res->fetch_assoc()) {
    $students[$row['user_id']] = [
        'id' => $row['user_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'attendance' => []
    ];
}

$res = $mysqli->query("SELECT student_id, DAY(date) as day FROM attendance WHERE MONTH(date) = $month AND YEAR(date) = $year AND status = 'present'");
while ($row = $res->fetch_assoc()) {
    if (isset($students[$row['student_id']])) {
        $students[$row['student_id']]['attendance'][] = intval($row['day']);
    }
}

function get_attendance_summary($mysqli, $month, $year, $total_days) {
    $students = [];
    $res = $mysqli->query("
        SELECT s.user_id, u.first_name, u.last_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
        ORDER BY u.first_name, u.last_name
    ");
    while ($row = $res->fetch_assoc()) {
        $students[$row['user_id']] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'attendance' => []
        ];
    }

    $res = $mysqli->query("SELECT student_id, DAY(date) as day FROM attendance WHERE MONTH(date) = $month AND YEAR(date) = $year AND status = 'present'");
    while ($row = $res->fetch_assoc()) {
        if (isset($students[$row['student_id']])) {
            $students[$row['student_id']]['attendance'][] = intval($row['day']);
        }
    }

    ob_start();
    ?>
    <h3>Monthly Attendance Summary (December 2025)</h3>
    <table class="attendance-summary">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Total Days</th>
          <th>Days Present</th>
          <th>Days Absent</th>
          <th>Attendance %</th>
          <th>Present Days</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): 
          $present_days = count($student['attendance']);
          $absent_days = $total_days - $present_days;
          $attendance_percent = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;
        ?>
        <tr>
          <td><?= htmlspecialchars($student['name']) ?></td>
          <td><?= $total_days ?></td>
          <td><?= $present_days ?></td>
          <td><?= $absent_days ?></td>
          <td><?= $attendance_percent ?>%</td>
          <td><?= implode(', ', $student['attendance']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
    return ob_get_clean();
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
        <a href="communication.php"><i class="fas fa-comments"></i> Communication</a>
        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
        <h3>Mark/Update Attendance</h3>
        <form id="attendanceForm" method="post" style="margin-bottom: 1em;">
          <label>
            Student:
            <select name="student_index" required>
              <?php foreach ($students as $id => $student): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($student['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Day (1-<?= $total_days ?>):
            <input type="number" name="day" min="1" max="<?= $total_days ?>" required>
          </label>
          <button type="submit" value="mark_present">Mark Present</button>
          <button type="submit" value="mark_absent">Mark Absent</button>
          <button type="submit" value="delete_attendance" onclick="return confirm('Delete attendance for this day?')">Delete</button>
        </form>

        <div id="attendanceSummary">
          <?= get_attendance_summary($mysqli, $month, $year, $total_days); ?>
        </div>
      </section>
    </main>
  </div>

  <script>
    let clickedValue = '';

    document.querySelectorAll('#attendanceForm button').forEach(button => {
      button.addEventListener('click', function () {
        clickedValue = this.value;
      });
    });

    document.getElementById('attendanceForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      formData.append('action', clickedValue);

      fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.text())
      .then(html => {
        document.getElementById('attendanceSummary').innerHTML = html;
      });
    });
  </script>
</body>
</html>
