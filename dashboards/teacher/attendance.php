<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

// For demonstration, mock attendance data for the current month (30 days)
$students = [
    [
        'name' => 'Ram Baban',
        'attendance' => [1, 2, 3, 5, 6, 7, 10, 12, 15, 16, 18, 20, 21, 23, 25, 26, 28, 29, 30] // days present
    ],
    [
        'name' => 'Sita Devi',
        'attendance' => [1, 3, 4, 5, 7, 8, 9, 12, 14, 17, 18, 20, 22, 24, 27, 29, 30]
    ],
];

// Month days count (simplified as 30 days)
$total_days = 30;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance</title>
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
        <!-- Attendance Summary -->
        <h3>Monthly Attendance Summary (July 2025)</h3>
        <table class="attendance-summary">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Total Days</th>
              <th>Days Present</th>
              <th>Days Absent</th>
              <th>Attendance %</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): 
              $present_days = count($student['attendance']);
              $absent_days = $total_days - $present_days;
              $attendance_percent = round(($present_days / $total_days) * 100, 2);
            ?>
            <tr>
              <td><?= htmlspecialchars($student['name']) ?></td>
              <td><?= $total_days ?></td>
              <td><?= $present_days ?></td>
              <td><?= $absent_days ?></td>
              <td><?= $attendance_percent ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
