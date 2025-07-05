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
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
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
        <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../teacher/manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="../teacher/gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="../teacher/attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Teacher" />
        <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
         <div class="email"><?= htmlspecialchars($teacher_email) ?></div>

        <!-- Settings & Logout -->
        <div class="profile-actions">
          <div class="dropdown">
            <i class="fas fa-cog" id="settingsToggle"></i>
            <div class="settings-dropdown" id="settingsMenu">
              <label>
                <input type="checkbox" id="darkModeToggle" />
                Dark Mode
              </label>
              <label>
                Language:
                <select id="languageSelect">
                  <option value="en">English</option>
                  <option value="np">Nepali</option>
                </select>
              </label>
            </div>
          </div>
          <a href="../auth/logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <div>
          <h2>Teacher Dashboard</h2>
          <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
        </div>
        <div class="actions">
          <div class="notification">
            <i class="fas fa-bell" id="notificationBell"></i>
            <div class="notification-dropdown" id="notificationDropdown">
              <p><strong>Notifications</strong></p>
              <ul>
                <li>New assignment submissions.</li>
                <li>Upcoming meetings scheduled.</li>
              </ul>
            </div>
          </div>
      </header>

      <section class="cards">
        <div class="card">Class Summary</div>
        <div class="card">Assignments Overview</div>
        <div class="card">Student Attendance</div>
        <div class="card">Messages</div>
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
