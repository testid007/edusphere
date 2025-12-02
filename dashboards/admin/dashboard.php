<?php
session_start();

// Only admin access
$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$admin_id     = (int)($_SESSION['user_id'] ?? 0);
$admin_name   = $_SESSION['admin_name']  ?? 'Main';
$admin_email  = $_SESSION['admin_email'] ?? 'admin@example.com';
$admin_avatar = '../../assets/img/user.jpg';

/* -------------------- Header notifications (bell) -------------------- */
$notifications = [];
$notifCount    = 0;

try {
    // Upcoming events in next 7 days
    $stmt = $conn->query("
        SELECT title, event_date
        FROM events
        WHERE event_date >= CURDATE()
          AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY event_date ASC
        LIMIT 5
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'type'    => 'info',
            'icon'    => 'fa-calendar-day',
            'message' => 'Upcoming: \"' . $row['title'] . '\" on ' .
                         date('M j', strtotime($row['event_date'])),
            'time'    => 'Within 7 days',
        ];
    }

    // New user registrations in last 3 days
    $stmt = $conn->query("
        SELECT COUNT(*) FROM users
        WHERE created_at IS NOT NULL
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $newUsers = (int)$stmt->fetchColumn();
    if ($newUsers > 0) {
        $notifications[] = [
            'type'    => 'success',
            'icon'    => 'fa-user-plus',
            'message' => "$newUsers new user account(s) created in the last 3 days.",
            'time'    => 'Recent',
        ];
    }
} catch (Exception $e) {
    // silent fail
}

$notifCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | EduSphere</title>

  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <!-- Chart.js for dashboard & reports -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <!-- ========== SIDEBAR ========== -->
    <aside class="sidebar">
      <div>
        <div class="logo">
          <img src="../../assets/img/logo.png" alt="EduSphere Logo" />
          <span>EduSphere</span>
        </div>

        <nav class="nav">
          <a href="#" class="nav-link" data-page="dashboard-content">
            <i class="fas fa-home"></i> Overview
          </a>

          <a href="#" class="nav-link" data-page="manage-users">
            <i class="fas fa-users-cog"></i> Manage Users
          </a>

          <a href="#" class="nav-link" data-page="create-fee">
            <i class="fas fa-file-invoice-dollar"></i> Create Fee
          </a>

          <a href="fees.php" class="nav-link">
            <i class="fas fa-file-invoice-dollar"></i> Fees & Payments
          </a>

          <!-- FIX HERE: make View Reports a normal link, no data-page -->
          <a href="reports.php" class="nav-link">
            <i class="fas fa-chart-bar"></i> View Reports
          </a>
          <!-- END FIX -->

          <a href="#" class="nav-link" data-page="manage-class-teachers">
            <i class="fas fa-calendar-alt"></i> Manage Class Teachers
          </a>
          
          <a href="#" class="nav-link" data-page="manage-teacher-subjects">
            <i class="fas fa-calendar-alt"></i> Manage Teacher subjects
          </a>
          <a href="#" class="nav-link" data-page="manage-schedule">
            <i class="fas fa-calendar-alt"></i> Manage Schedule
          </a>
          <a href="#" class="nav-link" data-page="schedule-view">
            <i class="fas fa-eye"></i> Schedule View
          </a>

          <a href="manage-events.php" class="nav-link">
            <i class="fas fa-calendar-plus"></i> Manage Events
          </a>

          <a href="../../auth/logout.php" class="nav-link logout">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </nav>

        <div class="sidebar-teacher-card">
          <img src="<?php echo htmlspecialchars($admin_avatar); ?>" alt="Admin" />
          <div>
            <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
            <div class="role">Administrator · EduSphere</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- ========== MAIN SHELL ========== -->
    <main class="main">
      <div class="main-header">
        <div class="main-header-left">
          <h2>Admin Dashboard</h2>
          <p>Welcome, <?php echo htmlspecialchars($admin_name); ?> 👋</p>
        </div>

        <div class="main-header-right">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text"
                   id="adminSearchInput"
                   placeholder="Search users, classes or events..." />
          </div>

          <div class="notif-wrapper">
            <button class="icon-btn" id="notifToggle" type="button">
              <i class="fas fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="badge"><?php echo $notifCount; ?></span>
              <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
              <h4>Notifications</h4>
              <?php if ($notifCount === 0): ?>
                <div class="notif-empty">
                  You’re all caught up. No new alerts.
                </div>
              <?php else: ?>
                <ul class="notif-list">
                  <?php foreach ($notifications as $n): ?>
                    <?php $class = 'notif-' . $n['type']; ?>
                    <li class="<?php echo $class; ?>">
                      <div class="icon">
                        <i class="fas <?php echo htmlspecialchars($n['icon']); ?>"></i>
                      </div>
                      <div class="notif-text">
                        <p class="msg"><?php echo htmlspecialchars($n['message']); ?></p>
                        <span class="time"><?php echo htmlspecialchars($n['time']); ?></span>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>

          <div class="profile-wrapper">
            <button class="header-avatar" id="profileToggle" type="button">
              <img src="<?php echo htmlspecialchars($admin_avatar); ?>" alt="Admin" />
              <div>
                <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="role">Administrator</div>
              </div>
              <i class="fas fa-chevron-down"
                 style="font-size:0.7rem;margin-left:4px;"></i>
            </button>

            <div class="profile-dropdown" id="profileDropdown">
              <div class="profile-summary">
                <img src="<?php echo htmlspecialchars($admin_avatar); ?>" alt="Admin" />
                <div>
                  <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
                  <div class="email"><?php echo htmlspecialchars($admin_email); ?></div>
                </div>
              </div>
              <a href="profile.php"><i class="fas fa-user"></i> View / Edit Profile</a>
              <a href="change-password.php"><i class="fas fa-key"></i> Change Password</a>
              <a href="notification-settings.php"><i class="fas fa-bell"></i> Notification Settings</a>
              <a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
          </div>
        </div>
      </div>

      <section class="content" id="dashboardContent">
        <!-- filled by loadPage('dashboard-content') -->
      </section>
    </main>
  </div>

<script>
  const navLinks         = document.querySelectorAll('.nav-link[data-page]');
  const dashboardContent = document.getElementById('dashboardContent');

  /* ================== AJAX loader ================== */
  function loadPage(page) {
    if (!dashboardContent) return;
    dashboardContent.innerHTML = '<p>Loading...</p>';

    fetch(page + '.php', { cache: 'no-cache' })
      .then(response => {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status + ' while loading ' + page + '.php');
        }
        return response.text();
      })
      .then(html => {
        dashboardContent.innerHTML = html;

        if (page === 'dashboard-content') {
          initAdminDashboardQuickButtons();
          initAdminDashboardCharts();
        }

        if (page === 'reports') {
          if (typeof renderReportsCharts === 'function') {
            renderReportsCharts();
          } else if (typeof renderCharts === 'function') {
            renderCharts();
          }
        }

        if (page === 'schedule-view' && typeof initScheduleView === 'function') {
          initScheduleView();
        }
        if (page === 'manage-schedule' && typeof initManageSchedule === 'function') {
          initManageSchedule();
        }
        function initManageSchedule() {
  // All elements are inside dashboardContent, not the whole document
  const root          = dashboardContent;
  if (!root) return;

  const gradeSelect   = root.querySelector('#grade-select');
  const btnLoad       = root.querySelector('#btn-load');
  const btnAuto       = root.querySelector('#btn-auto-generate');
  const statusEl      = root.querySelector('#status');
  const displayEl     = root.querySelector('#schedule-display');

  function setStatus(text, type) {
    if (!statusEl) return;
    statusEl.className = 'small text-' + (type || 'muted');
    statusEl.textContent = text;
  }

  function loadSchedule() {
    const grade = gradeSelect ? gradeSelect.value : '';
    if (!grade) {
      setStatus('Please select a class first.', 'danger');
      if (displayEl) {
        displayEl.innerHTML =
          '<div class="alert alert-info">Select a class/grade.</div>';
      }
      return;
    }

    setStatus('Loading schedule...', 'muted');

    // from /edusphere/dashboards/admin/dashboard.php → ../../api/...
    fetch(`../../api/fetch_schedule.php?grade=${encodeURIComponent(grade)}`, {
      cache: 'no-cache'
    })
      .then(r => r.text())
      .then(html => {
        if (displayEl) displayEl.innerHTML = html;
        setStatus('Schedule loaded.', 'success');
      })
      .catch(err => {
        console.error('Error loading schedule', err);
        if (displayEl) {
          displayEl.innerHTML =
            '<div class="alert alert-danger">Error loading schedule.</div>';
        }
        setStatus('Failed to load schedule.', 'danger');
      });
  }

  function autoGenerate() {
    const grade = gradeSelect ? gradeSelect.value : '';
    if (!grade) {
      setStatus('Please select a class first.', 'danger');
      return;
    }

    setStatus('Generating schedule...', 'muted');
    if (btnAuto) btnAuto.disabled = true;

    fetch('../../api/auto_generate_schedule.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'grade=' + encodeURIComponent(grade)
    })
      .then(r => r.text())
      .then(text => {
        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error('JSON parse error from auto_generate:', text);
          setStatus('Server returned invalid JSON.', 'danger');
          return;
        }

        if (data.success) {
          setStatus(data.message || 'Schedule generated successfully!', 'success');
          loadSchedule();
        } else {
          setStatus(data.message || 'Failed to generate schedule.', 'danger');
        }
      })
      .catch(err => {
        console.error('Error while generating schedule', err);
        setStatus('Error while generating schedule.', 'danger');
      })
      .finally(() => {
        if (btnAuto) btnAuto.disabled = false;
      });
  }

  if (btnLoad)  btnLoad.addEventListener('click', loadSchedule);
  if (btnAuto)  btnAuto.addEventListener('click', autoGenerate);
  if (gradeSelect) gradeSelect.addEventListener('change', loadSchedule);
}

      })
      .catch(error => {
        console.error(error);
        dashboardContent.innerHTML =
          '<p class="error">Error loading <strong>' + page + '.php</strong>: ' +
          error.message + '</p>';
      });
  }

  // sidebar clicks (only for links with data-page!)
  navLinks.forEach(link => {
    const page = link.getAttribute('data-page');
    link.addEventListener('click', e => {
      e.preventDefault();
      navLinks.forEach(l => l.classList.remove('active'));
      link.classList.add('active');
      loadPage(page);
    });
  });

  function activateSidebarAndLoad(page) {
    navLinks.forEach(l => {
      if (l.getAttribute('data-page') === page) {
        l.classList.add('active');
      } else {
        l.classList.remove('active');
      }
    });
    loadPage(page);
  }

  function initAdminDashboardQuickButtons() {
    const btnUsers    = document.getElementById('btnQuickManageUsers');
    const btnFee      = document.getElementById('btnQuickCreateFee');
    const btnSchedule = document.getElementById('btnQuickManageSchedule');

    if (btnUsers) {
      btnUsers.onclick = () => activateSidebarAndLoad('manage-users');
    }
    if (btnFee) {
      btnFee.onclick = () => activateSidebarAndLoad('create-fee');
    }
    if (btnSchedule) {
      btnSchedule.onclick = () => activateSidebarAndLoad('manage-schedule');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadPage('dashboard-content');

    const isDark = localStorage.getItem('darkMode') === 'enabled';
    if (isDark) {
      document.body.classList.add('dark-mode');
      const toggle = document.getElementById('darkModeToggle');
      if (toggle) toggle.checked = true;
    }
  });

  /* ================== Notifications dropdown ================== */
  (function() {
    const toggle   = document.getElementById('notifToggle');
    const dropdown = document.getElementById('notifDropdown');
    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.classList.toggle('active');
    });

    document.addEventListener('click', function() {
      dropdown.classList.remove('active');
    });

    dropdown.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  })();

  /* ================== Profile dropdown ================== */
  (function() {
    const toggle   = document.getElementById('profileToggle');
    const dropdown = document.getElementById('profileDropdown');
    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.classList.toggle('active');
    });

    document.addEventListener('click', function() {
      dropdown.classList.remove('active');
    });

    dropdown.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  })();

  /* ================== Dark mode toggle ================== */
  const darkToggle = document.getElementById('darkModeToggle');
  if (darkToggle) {
    darkToggle.addEventListener('change', () => {
      if (darkToggle.checked) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'enabled');
      } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'disabled');
      }
    });
  }

  /* ================== Dashboard charts ================== */
  let adminRoleChart = null;
  let adminClassChart = null;

  function initAdminDashboardCharts() {
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js not available – charts skipped.');
      return;
    }

    const rolesInput  = document.getElementById('userRoleStatsJson');
    const classInput  = document.getElementById('classStatsJson');
    const roleCanvas  = document.getElementById('userDistributionChart');
    const classCanvas = document.getElementById('studentsPerClassChart');

    if (!rolesInput || !classInput || !roleCanvas || !classCanvas) {
      return;
    }

    let roleData = [];
    let classData = [];

    try {
      roleData  = JSON.parse(rolesInput.value || '[]');
      classData = JSON.parse(classInput.value || '[]');
    } catch (e) {
      console.error('Error parsing dashboard JSON', e);
      return;
    }

    const roleLabels = roleData.map(item => item.label);
    const roleCounts = roleData.map(item => item.count);

    const classLabels = classData.map(item => item.class_name);
    const classCounts = classData.map(item => item.count);

    if (adminRoleChart) adminRoleChart.destroy();
    if (adminClassChart) adminClassChart.destroy();

    const roleCtx  = roleCanvas.getContext('2d');
    const classCtx = classCanvas.getContext('2d');

    adminRoleChart = new Chart(roleCtx, {
      type: 'doughnut',
      data: {
        labels: roleLabels,
        datasets: [{
          data: roleCounts,
          backgroundColor: ['#f97316', '#22c55e', '#3b82f6', '#facc15']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        },
        cutout: '60%'
      }
    });

    adminClassChart = new Chart(classCtx, {
      type: 'bar',
      data: {
        labels: classLabels,
        datasets: [{
          label: 'Students',
          data: classCounts,
          backgroundColor: '#f97316'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
</script>
</body>
</html>
