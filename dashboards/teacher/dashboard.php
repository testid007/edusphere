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
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/components/common.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/dashboards/teacher.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="container">
    <?php include '../components/sidebar_teacher.php'; ?>

    <main class="main">
      <?php include '../components/header.php'; ?>

      <section class="cards">
        <div class="card">
          <div>
            <h3>Class Summary</h3>
            <p>You teach <strong>5</strong> classes.</p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Assignments Overview</h3>
            <p>Total assignments: <strong>20</strong></p>
            <p>Pending: <strong>3</strong></p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Student Attendance</h3>
            <p>Average attendance: <strong>92%</strong></p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Messages</h3>
            <p>You have <strong>7</strong> unread messages.</p>
          </div>
        </div>
      </section>

    </main>
  </div>

  <script>
    // Notification toggle
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    bell.addEventListener('click', () => {
      dropdown.classList.toggle('show');
    });

    // Settings toggle
    const settingsToggle = document.getElementById('settingsToggle');
    const settingsMenu = document.getElementById('settingsMenu');
    settingsToggle.addEventListener('click', () => {
      settingsMenu.classList.toggle('show');
    });

    // Dark mode toggle
    const darkToggle = document.getElementById('darkModeToggle');
    darkToggle.addEventListener('change', () => {
      document.body.classList.toggle('dark-mode');
    });
  </script>
</body>
</html>
