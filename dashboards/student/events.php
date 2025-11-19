<?php
// dashboards/student/events.php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

$student_name  = $_SESSION['student_name']  ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager = new EventManager($conn);

// Upcoming and past events for this user
$upcomingEvents = $eventManager->getUpcomingEventsForUser((int)$_SESSION['user_id'], 100);
$pastEvents     = $eventManager->getPastEventsForUser((int)$_SESSION['user_id'], 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Events</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .events-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .events-table th,
    .events-table td {
      padding: 8px 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    .events-table th {
      background: #f5f5f5;
    }
    .btn {
      padding: 6px 10px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 4px;
    }
    .btn-interest {
      background: #4caf50;
      color: #fff;
    }
    .btn-not-interest {
      background: #f44336;
      color: #fff;
    }
    .btn-label {
      padding: 6px 10px;
      border-radius: 4px;
      background: #ccc;
      border: none;
    }
  </style>
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
        <a href="assignments.php"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="events.php" class="active"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($student_email) ?></div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main">
      <header class="header">
        <div>
          <h2>School Events</h2>
          <p>Mark events as interested or not interested to get better recommendations.</p>
        </div>
      </header>

      <!-- 🔹 Upcoming Events -->
      <h3>Upcoming Events</h3>
      <?php if (empty($upcomingEvents)): ?>
        <p>No upcoming events right now.</p>
      <?php else: ?>
        <table class="events-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Date</th>
              <th>Time</th>
              <th>Location</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($upcomingEvents as $e): ?>
            <?php
              $status = $e['participation_status']; // null / interested / not_interested / registered / participated
            ?>
            <tr>
              <td><?= htmlspecialchars($e['title']) ?></td>
              <td><?= htmlspecialchars($e['category_name']) ?></td>
              <td><?= htmlspecialchars($e['event_date']) ?></td>
              <td><?= htmlspecialchars(($e['start_time'] ?? '') . ' ' . ($e['end_time'] ?? '')) ?></td>
              <td><?= htmlspecialchars($e['location'] ?? '') ?></td>
              <td>
                <?php if ($status === null): ?>
                  <!-- No decision yet: show both buttons -->
                  <button 
                    class="btn btn-interest" 
                    data-event-id="<?= $e['id'] ?>" 
                    data-status="interested">
                    Interested
                  </button>
                  <button 
                    class="btn btn-not-interest" 
                    data-event-id="<?= $e['id'] ?>" 
                    data-status="not_interested">
                    Not Interested
                  </button>
                <?php else: ?>
                  <!-- Already marked -> show label -->
                  <span class="btn-label">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status))) ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <hr>

      <!-- 🔹 Past / Missed / Not Interested Events -->
      <h3>Past & Uninterested Events</h3>
      <?php if (empty($pastEvents)): ?>
        <p>No past or uninterested events recorded.</p>
      <?php else: ?>
        <table class="events-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Date</th>
              <th>Time</th>
              <th>Location</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pastEvents as $e): ?>
            <?php
              $status = $e['participation_status'];
              if ($status === null) {
                  $statusLabel = 'Missed';
              } elseif ($status === 'not_interested') {
                  $statusLabel = 'Not Interested';
              } else {
                  $statusLabel = ucwords(str_replace('_', ' ', $status));
              }
            ?>
            <tr>
              <td><?= htmlspecialchars($e['title']) ?></td>
              <td><?= htmlspecialchars($e['category_name']) ?></td>
              <td><?= htmlspecialchars($e['event_date']) ?></td>
              <td><?= htmlspecialchars(($e['start_time'] ?? '') . ' ' . ($e['end_time'] ?? '')) ?></td>
              <td><?= htmlspecialchars($e['location'] ?? '') ?></td>
              <td><?= htmlspecialchars($statusLabel) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>

  <script>
    // Handle Interested / Not Interested buttons
    document.querySelectorAll('.btn[data-status]').forEach(btn => {
      btn.addEventListener('click', function () {
        const eventId = this.dataset.eventId;
        const status  = this.dataset.status;

        const msg = status === 'interested'
          ? 'Mark this event as INTERESTED?'
          : 'Mark this event as NOT INTERESTED?';

        if (!confirm(msg)) return;

        fetch('../../api/mark_event_participation.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            event_id: eventId,
            status: status
          })
        })
        .then(response => response.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            // Simplest UX: reload page so Upcoming / Past sections refresh correctly.
            location.reload();
          }
        })
        .catch(err => {
          console.error(err);
          alert('Something went wrong. Please try again.');
        });
      });
    });
  </script>
</body>
</html>
