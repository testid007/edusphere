<?php
session_start();
$parent_name = $_SESSION['parent_name'] ?? 'Parent';
$parent_email = $_SESSION['parent_email'] ?? 'parent@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Parent Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
      </div>

      <nav class="nav">
        <a href="#" class="active" data-page="dashboard-content"><i class="fas fa-home"></i> Dashboard</a>
        <a href="#" data-page="child-performance"><i class="fas fa-chart-line"></i> Child Performance</a>
        <a href="#" data-page="fee-status"><i class="fas fa-money-bill"></i> Fee Status</a>
        <a href="#" data-page="communication"><i class="fas fa-envelope"></i> Communicate with Teachers</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Parent" />
        <div class="name"><?= htmlspecialchars($parent_name) ?></div>
        <div class="email"><?= htmlspecialchars($parent_email) ?></div>

        <div class="profile-actions">
          <div class="dropdown">
            <i class="fas fa-cog" id="settingsToggle"></i>
            <div class="settings-dropdown" id="settingsMenu">
              <label>
                <input type="checkbox" id="darkModeToggle" /> Dark Mode
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

    <main class="main">
      <header class="header">
        <div>
          <h2>Parent Dashboard</h2>
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

      <section class="content" id="dashboardContent">
      </section>
    </main>
  </div>

  <script>
    const links = document.querySelectorAll('.nav a[data-page]');
    const mainContent = document.getElementById('dashboardContent');

    function loadPage(page) {
      fetch(`../parent/${page}.php`)
        .then(res => res.ok ? res.text() : Promise.reject(new Error("Failed to load " + page)))
        .then(html => {
          mainContent.innerHTML = html;
        })
        .catch(err => {
          mainContent.innerHTML = `<p class="error">Error loading content: ${err.message}</p>`;
        });
    }

    links.forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        links.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        loadPage(link.dataset.page);
      });
    });

    window.addEventListener('DOMContentLoaded', () => {
      loadPage('dashboard-content'); // Load dashboard-content.php by default

      if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeToggle').checked = true;
      }
    });

    // Notification toggle
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    bell.addEventListener('click', () => dropdown.classList.toggle('show'));

    // Settings toggle
    const settingsToggle = document.getElementById('settingsToggle');
    const settingsMenu = document.getElementById('settingsMenu');
    settingsToggle.addEventListener('click', () => settingsMenu.classList.toggle('show'));

    // Dark mode toggle
    const darkToggle = document.getElementById('darkModeToggle');
    darkToggle.addEventListener('change', () => {
      if (darkToggle.checked) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'enabled');
      } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'disabled');
      }
    });
  </script>
</body>
</html>
