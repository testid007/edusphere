<?php
session_start();
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo">
      </div>
      <nav class="nav">
        <a href="#" class="active" data-page="dashboard-content"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="#" data-page="manage-users"><i class="fas fa-users-cog"></i> Manage Users</a>
        <a href="#" data-page="create-fee"><i class="fas fa-file-invoice-dollar"></i> Create Fee</a>
        <a href="#" data-page="reports"><i class="fas fa-chart-bar"></i> View Reports</a>
        <a href="#" data-page="manage-schedule"><i class="fas fa-calendar-alt"></i> Manage Schedule</a>
        <a href="#" data-page="schedule-view"><i class="fas fa-eye"></i> Schedule View</a>
        <a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Admin">
        <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-actions">
          <div class="dropdown">
            <i class="fas fa-cog" id="settingsToggle"></i>
            <div class="settings-dropdown" id="settingsMenu">
              <label><input type="checkbox" id="darkModeToggle"> Dark Mode</label>
              <label>Language:
                <select id="languageSelect">
                  <option value="en">English</option>
                  <option value="np">Nepali</option>
                </select>
              </label>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="header">
        <div>
          <h2>Admin Dashboard</h2>
          <p>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</p>
        </div>
        <div class="actions">
          <div class="notification">
            <i class="fas fa-bell" id="notificationBell"></i>
            <div class="notification-dropdown" id="notificationDropdown">
              <p><strong>Notifications</strong></p>
              <ul>
                <li>System maintenance scheduled.</li>
                <li>New user registrations pending approval.</li>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <section class="content" id="dashboardContent">
        <!-- Dynamic content loaded here -->
      </section>
    </main>
  </div>

  <script>
    const links = document.querySelectorAll('.nav a[data-page]');
    const mainContent = document.getElementById('dashboardContent');

    function loadPage(page) {
      fetch(`../../dashboards/admin/${page}.php`)
        .then(res => res.ok ? res.text() : Promise.reject("Failed to load " + page))
        .then(html => {
          mainContent.innerHTML = html;
          if (page === 'reports') renderCharts();
        })
        .catch(err => {
          mainContent.innerHTML = `<p class='error'>Error loading content: ${err.message}</p>`;
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
      loadPage('dashboard-content');
      if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeToggle').checked = true;
      }
    });

    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    bell.addEventListener('click', () => dropdown.classList.toggle('show'));

    const settingsToggle = document.getElementById('settingsToggle');
    const settingsMenu = document.getElementById('settingsMenu');
    settingsToggle.addEventListener('click', () => settingsMenu.classList.toggle('show'));

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

    function renderCharts() {
      const barCtx = document.getElementById('barChart');
      if (barCtx) {
        new Chart(barCtx, {
          type: 'bar',
          data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
              label: 'Fees Collected',
              data: [5000, 7000, 6000, 8000, 7500],
              backgroundColor: '#4caf50'
            }]
          },
          options: { responsive: true }
        });
      }

      const pieCtx = document.getElementById('pieChart');
      if (pieCtx) {
        new Chart(pieCtx, {
          type: 'pie',
          data: {
            labels: ['Science', 'Commerce', 'Arts'],
            datasets: [{
              data: [120, 90, 60],
              backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56']
            }]
          },
          options: { responsive: true }
        });
      }
    }
  </script>
</body>
</html>
