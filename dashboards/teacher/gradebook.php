<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Grade Book</title>
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
        <a href="gradebook.php" class="active"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
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
        <h2>Grade Book</h2>
        <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
      </header>

      <section class="table-container">
        <h3>Student Grades</h3>
        <p>Review and update student grades below.</p>

        <table class="gradebook-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Assignment</th>
              <th>Score</th>
              <th>Grade</th>
              <th>Comments</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td data-label="Student Name">Ram Baban</td>
              <td data-label="Assignment">Math Homework 1</td>
              <td data-label="Score">45/50</td>
              <td data-label="Grade" class="grade">A-</td>
              <td data-label="Comments">Good work</td>
              <td data-label="Actions">
                <button class="btn-edit">Edit</button>
                <button class="btn-delete">Delete</button>
              </td>
            </tr>
            <!-- Add more rows dynamically -->
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
