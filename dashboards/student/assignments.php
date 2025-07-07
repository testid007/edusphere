<?php
session_start();
$student_name = $_SESSION['student_name'] ?? 'Student';

// Example assignment data - replace with your DB fetch
$assignments = [
    ['title' => 'Math Homework 1', 'due_date' => '2025-07-15', 'status' => 'Pending'],
    ['title' => 'Science Project', 'due_date' => '2025-07-20', 'status' => 'Submitted'],
    ['title' => 'English Essay', 'due_date' => '2025-07-25', 'status' => 'Pending'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Assignments</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>

      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="assignments.php" class="active"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($_SESSION['student_email'] ?? 'student@example.com') ?></div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <h2>My Assignments</h2>
        <p>Welcome, <?= htmlspecialchars($student_name) ?>!</p>
      </header>

      <section class="content">
        <table class="performance-table">
          <thead>
            <tr>
              <th>Assignment Title</th>
              <th>Due Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignments as $assignment): ?>
              <tr>
                <td><?= htmlspecialchars($assignment['title']) ?></td>
                <td><?= htmlspecialchars($assignment['due_date']) ?></td>
                <td><?= htmlspecialchars($assignment['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
