<?php
session_start();

// Only admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

/* -------------------- User counts for cards -------------------- */
$totalUsers    = 0;
$totalStudents = 0;
$totalTeachers = 0;
$totalParents  = 0;

try {
    $stmt = $conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
    $roleCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($roleCounts as $row) {
        $role  = strtolower($row['role']);
        $count = (int)$row['c'];

        $totalUsers += $count;

        if ($role === 'student') {
            $totalStudents = $count;
        } elseif ($role === 'teacher') {
            $totalTeachers = $count;
        } elseif ($role === 'parent') {
            $totalParents = $count;
        }
    }
} catch (Exception $e) {
    // On error: keep zeros
}

/* -------------------- Upcoming events (card + notifications) -------------------- */
$upcomingEventList  = [];   // for card
$upcomingEventCount = 0;
$notifications      = [];
$hasNewStuff        = false;

try {
    // Get next 5 upcoming events (from today onwards)
    $stmt = $conn->prepare("
        SELECT id, title, event_date
        FROM events
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = new DateTime('today');

    foreach ($rows as $row) {
        $eventDate = new DateTime($row['event_date']);
        $interval  = $today->diff($eventDate);
        $daysDiff  = (int)$interval->format('%r%a'); // signed days (>= 0 here)

        // Human readable time left
        if ($daysDiff === 0) {
            $timeLeft = 'today';
        } elseif ($daysDiff === 1) {
            $timeLeft = 'tomorrow';
        } else {
            $timeLeft = "in {$daysDiff} days";
        }

        $formattedDate = $eventDate->format('M d, Y');

        $upcomingEventList[] = [
            'title'          => $row['title'],
            'formatted_date' => $formattedDate,
            'time_left'      => $timeLeft,
        ];

        // Notifications: separate message if it's tomorrow
        if ($daysDiff === 1) {
            $notifications[] = "Reminder: \"{$row['title']}\" is tomorrow ({$formattedDate}).";
        } else {
            $notifications[] = "Upcoming event: \"{$row['title']}\" on {$formattedDate} ({$timeLeft}).";
        }
    }

    $upcomingEventCount = count($upcomingEventList);

    // New registrations in last 3 days
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $stmt->execute();
    $newRegs = (int)$stmt->fetchColumn();

    if ($newRegs > 0) {
        $notifications[] = "{$newRegs} new user(s) registered in the last 3 days.";
    }

    $hasNewStuff = count($notifications) > 0;
} catch (Exception $e) {
    // Optionally log / ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard</title>

  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <!-- Chart.js for reports page -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
      </div>

      <nav class="nav">
        <!-- Dashboard is the main page now (no AJAX) -->
        <a href="dashboard.php" class="nav-link active">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <a href="#" class="nav-link" data-page="manage-users">
          <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="#" class="nav-link" data-page="create-fee">
          <i class="fas fa-file-invoice-dollar"></i> Create Fee
        </a>
        <a href="#" class="nav-link" data-page="reports">
          <i class="fas fa-chart-bar"></i> View Reports
        </a>
        <a href="#" class="nav-link" data-page="manage-schedule">
          <i class="fas fa-calendar-alt"></i> Manage Schedule
        </a>
        <a href="#" class="nav-link" data-page="schedule-view">
          <i class="fas fa-eye"></i> Schedule View
        </a>

        <!-- Manage Events is full page, not AJAX -->
        <a href="manage-events.php" class="nav-link">
          <i class="fa-solid fa-calendar-plus"></i> Manage Events
        </a>

        <a href="../../auth/logout.php">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </nav>

      <!-- Admin Profile -->
      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Admin">
        <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-actions">
          <div class="dropdown" style="position: relative;">
            <i class="fas fa-cog" id="settingsToggle"></i>
            <div class="settings-dropdown" id="settingsMenu">
              <label>
                <input type="checkbox" id="darkModeToggle"> Dark Mode
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
        </div>
      </div>
    </aside>

    <!-- Main Area -->
    <main class="main">
      <header class="header">
        <div>
          <h2>Admin Dashboard</h2>
          <p>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</p>
        </div>
        <div class="actions">
          <button class="notification" type="button">
            <i class="fas fa-bell" id="notificationBell"></i>
            <?php if ($hasNewStuff): ?>
              <span class="notification-dot"></span>
            <?php endif; ?>
            <div class="notification-dropdown" id="notificationDropdown">
              <p><strong>Notifications</strong></p>
              <ul>
                <?php if (!empty($notifications)): ?>
                  <?php foreach ($notifications as $note): ?>
                    <li class="unread"><?php echo htmlspecialchars($note); ?></li>
                  <?php endforeach; ?>
                <?php else: ?>
                  <li class="empty">No new notifications.</li>
                <?php endif; ?>
              </ul>
            </div>
          </button>
        </div>
      </header>

      <!-- MAIN DASHBOARD CONTENT (cards) -->
      <section class="content" id="dashboardContent">
        <div class="cards">
          <!-- Total Users -->
          <div class="card">
            <div>
              <h3>Total Users</h3>
              <p><?php echo number_format($totalUsers); ?></p>
            </div>
          </div>

          <!-- Total Students -->
          <div class="card">
            <div>
              <h3>Total Students</h3>
              <p><?php echo number_format($totalStudents); ?></p>
            </div>
          </div>

          <!-- Total Teachers -->
          <div class="card">
            <div>
              <h3>Total Teachers</h3>
              <p><?php echo number_format($totalTeachers); ?></p>
            </div>
          </div>

          <!-- Total Parents -->
          <div class="card">
            <div>
              <h3>Total Parents</h3>
              <p><?php echo number_format($totalParents); ?></p>
            </div>
          </div>

          <!-- Upcoming Events card -->
          <div class="card">
            <div>
              <h3>Upcoming Events</h3>
              <p><?php echo number_format($upcomingEventCount); ?></p>

              <?php if ($upcomingEventCount > 0): ?>
                <ul style="margin-top:8px; padding-left:18px; font-size:0.9rem;">
                  <?php foreach (array_slice($upcomingEventList, 0, 3) as $ev): ?>
                    <li>
                      <strong><?php echo htmlspecialchars($ev['title']); ?></strong><br>
                      <span>
                        <?php echo htmlspecialchars($ev['formatted_date']); ?>
                        &nbsp;–&nbsp;
                        <?php echo htmlspecialchars($ev['time_left']); ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p style="font-size:0.9rem; margin-top:6px;">No upcoming events.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- When you click other nav items, their pages will load here via AJAX -->
      </section>
    </main>
  </div>

  <!-- JavaScript -->
  <script>
  const navLinks = document.querySelectorAll('.nav-link');
  const dashboardContent = document.getElementById('dashboardContent');

  // -------- AJAX loader for inner pages --------
  function loadPage(page) {
    // show a small loading text every time
    dashboardContent.innerHTML = '<p>Loading...</p>';

    fetch(`../../dashboards/admin/${page}.php`, { cache: 'no-cache' })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status} while loading ${page}.php`);
        }
        return response.text();
      })
      .then(html => {
        dashboardContent.innerHTML = html;

        // Page-specific inits
        if (page === 'reports') {
          renderCharts();
        } else if (page === 'schedule-view') {
          initScheduleView();   // ⬅️ new!
        }
      })
      .catch(error => {
        console.error(error);
        dashboardContent.innerHTML =
          `<p class="error">Error loading <strong>${page}.php</strong>: ${error.message}</p>`;
      });
  }

  // Click handling for sidebar links
  navLinks.forEach(link => {
    const page = link.getAttribute('data-page');
    if (!page) return; // skip links without data-page (Manage Events, Logout)

    link.addEventListener('click', e => {
      e.preventDefault();

      navLinks.forEach(l => l.classList.remove('active'));
      link.classList.add('active');

      loadPage(page);
    });
  });

  document.addEventListener('DOMContentLoaded', () => {
    // load default dashboard
    loadPage('dashboard-content');

    // Restore dark mode
    const isDark = localStorage.getItem('darkMode') === 'enabled';
    if (isDark) {
      document.body.classList.add('dark-mode');
      const darkToggle = document.getElementById('darkModeToggle');
      if (darkToggle) darkToggle.checked = true;
    }
  });

  // -------- Notification toggle --------
  const notificationBell = document.getElementById('notificationBell');
  const notificationDropdown = document.getElementById('notificationDropdown');
  if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', () => {
      notificationDropdown.classList.toggle('show');
    });
  }

  // -------- Settings dropdown --------
  const settingsToggle = document.getElementById('settingsToggle');
  const settingsMenu   = document.getElementById('settingsMenu');
  if (settingsToggle && settingsMenu) {
    settingsToggle.addEventListener('click', () => {
      settingsMenu.classList.toggle('show');
    });
  }

  // -------- Dark mode toggle --------
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

  // ====== Charts for Reports ======
  function renderCharts() {
    const statsEl = document.getElementById('userRoleStats');
    if (!statsEl) return;

    const student = parseInt(statsEl.dataset.student || '0', 10);
    const teacher = parseInt(statsEl.dataset.teacher || '0', 10);
    const parent  = parseInt(statsEl.dataset.parent  || '0', 10);
    const admin   = parseInt(statsEl.dataset.admin   || '0', 10);

    const barCtx = document.getElementById('barChart');
    if (barCtx) {
      new Chart(barCtx, {
        type: 'bar',
        data: {
          labels: ['Students', 'Teachers', 'Parents', 'Admins'],
          datasets: [{
            label: 'User count',
            data: [student, teacher, parent, admin],
            backgroundColor: '#4caf50'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, precision: 0, ticks: { stepSize: 1 } }
          }
        }
      });
    }

    const pieCtx = document.getElementById('pieChart');
    if (pieCtx) {
      new Chart(pieCtx, {
        type: 'pie',
        data: {
          labels: ['Students', 'Teachers', 'Parents', 'Admins'],
          datasets: [{
            data: [student, teacher, parent, admin],
            backgroundColor: ['#36a2eb', '#ff6384', '#ffcd56', '#4caf50']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }
  }

  // ====== NEW: Init Schedule View (class dropdown + print) ======
  function initScheduleView() {
    const classSelect   = document.getElementById('sv-class-select');
    const tableContainer = document.getElementById('sv-table-container');
    const printBtn      = document.getElementById('sv-print-btn');

    // Class change → reload table via AJAX (partial=1)
    if (classSelect && tableContainer) {
      classSelect.addEventListener('change', function () {
        const val = this.value || 1;
        tableContainer.innerHTML = '<p>Loading schedule...</p>';

        fetch(`../../dashboards/admin/schedule-view.php?class=${encodeURIComponent(val)}&partial=1`, {
          cache: 'no-cache'
        })
        .then(res => {
          if (!res.ok) throw new Error('Failed to load schedule');
          return res.text();
        })
        .then(html => {
          tableContainer.innerHTML = html;
        })
        .catch(err => {
          console.error(err);
          tableContainer.innerHTML =
            '<p class="sv-error">Error loading schedule. Please try again.</p>';
        });
      });
    }

    // Print / Save as PDF (browser print dialog)
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        window.print();
      });
    }
  }
</script>

</body>
</html>
