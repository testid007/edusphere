<?php
session_start();
$parent_name = $_SESSION['parent_name'] ?? 'Parent';
$parent_email = $_SESSION['parent_email'] ?? 'parent@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Parent Dashboard</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../assets/img/logo.png" alt="Logo" width="30">
      </div>

      <nav class="nav">
        <a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../parent/child-performance.php"><i class="fas fa-chart-line"></i> Child Performance</a>
        <a href="../parent/fee-status.php"><i class="fas fa-money-bill"></i> Fee Status</a>
        <a href="../parent/communication.php"><i class="fas fa-envelope"></i> Communicate with Teachers</a>
      </nav>

      <div class="profile">
        <img src="../assets/img/user.jpg" alt="Parent">
        <div class="name"><?= htmlspecialchars($parent_name) ?></div>
        <div class="email"><?= htmlspecialchars($parent_email) ?></div>

        <!-- Settings & Logout -->
        <div class="profile-actions">
          <div class="dropdown">
            <i class="fas fa-cog" id="settingsToggle"></i>
            <div class="settings-dropdown" id="settingsMenu">
              <label>
                <input type="checkbox" id="darkModeToggle">
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
          <h2>Property Dashboard</h2>
          <p>Welcome, <?= htmlspecialchars($parent_name) ?>!</p>
        </div>
        <div class="actions">
          <div class="notification">
            <i class="fas fa-bell" id="notificationBell"></i>
            <div class="notification-dropdown" id="notificationDropdown">
              <p><strong>Notifications</strong></p>
              <ul>
                <li>Child attendance updated.</li>
                <li>New message from teacher.</li>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <section class="cards">
        <div class="card"><h3>Child Progress</h3><p>80%</p></div>
        <div class="card"><h3>Attendance Record</h3><p>95%</p></div>
        <div class="card"><h3>Fee Payment</h3><p>$1,200</p></div>
        <div class="card"><h3>Teacher Messages</h3><p>4</p></div>
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

    // Dark mode
    const darkToggle = document.getElementById('darkModeToggle');
    darkToggle.addEventListener('change', () => {
      document.body.classList.toggle('dark-mode');
    });
  </script>
</body>
</html>
