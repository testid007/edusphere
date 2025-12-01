<?php
// dashboards/admin/manage-events.php
session_start();

// Only logged-in admin/teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'teacher'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager = new EventManager($conn);

$message = '';
$messageType = 'success';

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && $eventManager->deleteEvent($id)) {
        $message = 'Event deleted successfully.';
        $messageType = 'success';
    } else {
        $message = 'Failed to delete event.';
        $messageType = 'error';
    }
}

// Handle create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $event_date  = $_POST['event_date'] ?? '';
    $start_time  = $_POST['start_time'] ?? null;
    $end_time    = $_POST['end_time'] ?? null;
    $location    = trim($_POST['location'] ?? '');
    $image_path  = null;

    if (!$title || !$category_id || !$event_date) {
        $message = 'Please fill in Title, Category and Event Date.';
        $messageType = 'error';
    } else {
        // Image upload (optional)
        if (!empty($_FILES['event_image']['name']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/img/events/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $destPath)) {
                $image_path = 'events/' . $filename; // relative to /assets/img
            } else {
                $message = 'Image upload failed. Please try again.';
                $messageType = 'error';
            }
        } else {
            // Keep old image when updating
            if ($action === 'update') {
                $image_path = $_POST['existing_image'] ?? null;
            }
        }

        if ($message === '') {
            $data = [
                'title'       => $title,
                'description' => $description,
                'category_id' => $category_id,
                'event_date'  => $event_date,
                'start_time'  => $start_time,
                'end_time'    => $end_time,
                'location'    => $location,
                'created_by'  => (int)$_SESSION['user_id'],
                'image_path'  => $image_path,
            ];

            if ($action === 'update') {
                $eventId = (int)($_POST['event_id'] ?? 0);
                if ($eventId > 0 && $eventManager->updateEvent($eventId, $data)) {
                    $message = 'Event updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update event.';
                    $messageType = 'error';
                }
            } else {
                if ($eventManager->createEvent($data)) {
                    $message = 'Event created successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create event.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Load categories
$catStmt = $conn->query("SELECT id, name FROM event_categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

// Editing?
$editingEvent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute([':id' => $editId]);
        $editingEvent = $stmt->fetch();
    }
}

// Upcoming events
$events = $eventManager->getUpcomingEvents(200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Events - EduSphere</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    /* Page-specific tweaks */
      .admin-events-layout {
    display: grid;
    grid-template-columns: minmax(0, 420px) minmax(0, 1fr);
    gap: 24px;
    align-items: flex-start;
    margin-top: 10px;
  }

  .card {
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    padding: 16px 18px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
  }

  /* FORCE them to behave like normal blocks (in case dashboard.css adds floats/widths) */
  .card-form,
  .card-table {
    width: 100%;
    max-width: 100%;
    position: relative;
    float: none;
    display: block;
  }

  .card h3 {
    margin-top: 0;
    margin-bottom: 12px;
  }

    .field {
      margin-bottom: 10px;
    }
    .field label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 4px;
    }
    .field input[type="text"],
    .field input[type="date"],
    .field input[type="time"],
    .field select,
    .field textarea {
      width: 100%;
      padding: 7px 9px;
      border-radius: 6px;
      border: 1px solid #d1d5db;
      font-size: 0.9rem;
    }
    .field textarea {
      resize: vertical;
      min-height: 60px;
    }
    .field-inline {
      display: flex;
      gap: 8px;
    }
    .field-inline > div { flex: 1; }

    .current-image img {
      width: 80px;
      border-radius: 6px;
      margin-top: 4px;
    }

    .form-actions {
      margin-top: 10px;
      display: flex;
      gap: 8px;
    }
    .btn {
      border-radius: 999px;
      padding: 6px 14px;
      font-size: 0.85rem;
      border: none;
      cursor: pointer;
    }
    .btn-primary {
      background: #16a34a;
      color: #fff;
    }
    .btn-secondary {
      background: #e5e7eb;
      color: #111827;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-danger {
      background: #ef4444;
      color: #fff;
    }
    .btn-small {
      padding: 4px 10px;
      font-size: 0.8rem;
      margin-bottom: 4px;
    }
    .alert {
      margin-bottom: 12px;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 0.85rem;
    }
    .alert-success {
      background: #ecfdf3;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    .alert-error {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    .events-table-wrapper {
      max-height: 480px;
      overflow: auto;
    }
    .events-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .events-table th,
    .events-table td {
      padding: 8px 8px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
    }
    .events-table th {
      background: #f9fafb;
    }
    .event-info {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .event-thumb {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      object-fit: cover;
      background: #e5e7eb;
    }
    .event-thumb.placeholder {
      background: #e5e7eb;
    }
    .event-title {
      font-weight: 600;
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50;
    }
    .modal-backdrop.show {
      display: flex;
    }
    .modal {
      background: #ffffff;
      border-radius: 12px;
      padding: 16px 18px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .modal-header h4 {
      margin: 0;
    }
    .modal-close {
      border: none;
      background: transparent;
      font-size: 1.2rem;
      cursor: pointer;
    }
    .modal-body p {
      margin: 4px 0;
      font-size: 0.9rem;
    }
    .modal-body img {
      max-width: 100%;
      border-radius: 8px;
      margin-bottom: 6px;
    }

    @  @media (max-width: 900px) {
    .admin-events-layout {
      grid-template-columns: 1fr;
    }
    .card-form {
      margin-bottom: 16px;
    }
  }
  </style>
</head>
<body>
<div class="container">
  <!-- Sidebar (same as Admin Dashboard) -->
  <aside class="sidebar">
    <div class="logo">
      <img src="../../assets/img/logo.png" alt="Logo" />
    </div>

    <nav class="nav">
      <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="dashboard.php?page=manage-users" class="nav-link"><i class="fas fa-users-cog"></i> Manage Users</a>
      <a href="dashboard.php?page=create-fee" class="nav-link"><i class="fas fa-file-invoice-dollar"></i> Create Fee</a>
      <a href="dashboard.php?page=reports" class="nav-link"><i class="fas fa-chart-bar"></i> View Reports</a>
      <a href="dashboard.php?page=manage-schedule" class="nav-link"><i class="fas fa-calendar-alt"></i> Manage Schedule</a>
      <a href="dashboard.php?page=schedule-view" class="nav-link"><i class="fas fa-eye"></i> Schedule View</a>
      <!-- Active link -->
      <a href="manage-events.php" class="nav-link active"><i class="fa-solid fa-calendar-plus"></i> Manage Events</a>
      <a href="../../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <!-- Admin Profile -->
    <div class="profile">
      <img src="../../assets/img/user.jpg" alt="Admin">
      <div class="name"><?= htmlspecialchars($admin_name) ?></div>
      <div class="profile-actions">
        <div class="dropdown">
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

  <!-- Main content -->
  <main class="main">
    <header class="header">
      <div>
        <h2>Admin Dashboard</h2>
        <p>Welcome, <?= htmlspecialchars($admin_name) ?>!</p>
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

    <!-- Manage Events content -->
    <section class="admin-events-wrapper">
      <h2>Manage Events</h2>
      <p class="subtitle">Create, update and manage upcoming school events.</p>

      <?php if ($message): ?>
        <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <div class="admin-events-layout">
        <!-- Left: Create/Edit form -->
        <div class="card card-form">
          <h3><?= $editingEvent ? 'Edit Event' : 'Create New Event' ?></h3>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editingEvent ? 'update' : 'create' ?>">
            <?php if ($editingEvent): ?>
              <input type="hidden" name="event_id" value="<?= (int)$editingEvent['id'] ?>">
              <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingEvent['image_path'] ?? '') ?>">
            <?php endif; ?>

            <div class="field">
              <label for="title">Title*</label>
              <input type="text" id="title" name="title" required
                     value="<?= htmlspecialchars($editingEvent['title'] ?? '') ?>">
            </div>

            <div class="field">
              <label for="description">Description</label>
              <textarea id="description" name="description"><?= htmlspecialchars($editingEvent['description'] ?? '') ?></textarea>
            </div>

            <div class="field">
              <label for="category_id">Category*</label>
              <select id="category_id" name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"
                    <?= isset($editingEvent['category_id']) && (int)$editingEvent['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="event_date">Event Date*</label>
              <input type="date" id="event_date" name="event_date" required
                     value="<?= htmlspecialchars($editingEvent['event_date'] ?? '') ?>">
            </div>

            <div class="field field-inline">
              <div>
                <label for="start_time">Start Time</label>
                <input type="time" id="start_time" name="start_time"
                       value="<?= htmlspecialchars($editingEvent['start_time'] ?? '') ?>">
              </div>
              <div>
                <label for="end_time">End Time</label>
                <input type="time" id="end_time" name="end_time"
                       value="<?= htmlspecialchars($editingEvent['end_time'] ?? '') ?>">
              </div>
            </div>

            <div class="field">
              <label for="location">Location</label>
              <input type="text" id="location" name="location"
                     value="<?= htmlspecialchars($editingEvent['location'] ?? '') ?>">
            </div>

            <div class="field">
              <label for="event_image">
                Event Image <?= $editingEvent && !empty($editingEvent['image_path']) ? '(leave empty to keep existing)' : '' ?>
              </label>
              <input type="file" id="event_image" name="event_image" accept="image/*">
              <?php if ($editingEvent && !empty($editingEvent['image_path'])): ?>
                <div class="current-image">
                  <img src="../../assets/img/<?= htmlspecialchars($editingEvent['image_path']) ?>" alt="Current image">
                </div>
              <?php endif; ?>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">
                <?= $editingEvent ? 'Update Event' : 'Create Event' ?>
              </button>
              <?php if ($editingEvent): ?>
                <a href="manage-events.php" class="btn btn-secondary">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Right: Upcoming events list -->
        <div class="card card-table">
          <h3>Upcoming Events</h3>

          <?php if (empty($events)): ?>
            <p>No upcoming events.</p>
          <?php else: ?>
            <div class="events-table-wrapper">
              <table class="events-table">
                <thead>
                  <tr>
                    <th>Event</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $e): ?>
                  <tr>
                    <td>
                      <div class="event-info">
                        <?php if (!empty($e['image_path'])): ?>
                          <img src="../../assets/img/<?= htmlspecialchars($e['image_path']) ?>" class="event-thumb" alt="">
                        <?php else: ?>
                          <div class="event-thumb placeholder"></div>
                        <?php endif; ?>
                        <span class="event-title"><?= htmlspecialchars($e['title']) ?></span>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($e['category_name']) ?></td>
                    <td><?= htmlspecialchars($e['event_date']) ?></td>
                    <td><?= htmlspecialchars(($e['start_time'] ?? '') . ' ' . ($e['end_time'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($e['location'] ?? '') ?></td>
                    <td>
                      <button
                        class="btn btn-small btn-secondary btn-view"
                        data-title="<?= htmlspecialchars($e['title']) ?>"
                        data-description="<?= htmlspecialchars($e['description'] ?? '') ?>"
                        data-category="<?= htmlspecialchars($e['category_name']) ?>"
                        data-date="<?= htmlspecialchars($e['event_date']) ?>"
                        data-time="<?= htmlspecialchars(($e['start_time'] ?? '') . ' ' . ($e['end_time'] ?? '')) ?>"
                        data-location="<?= htmlspecialchars($e['location'] ?? '') ?>"
                        data-image="<?= htmlspecialchars($e['image_path'] ?? '') ?>"
                      >
                        View
                      </button>
                      <a href="manage-events.php?edit=<?= (int)$e['id'] ?>" class="btn btn-small btn-secondary">Edit</a>
                      <a href="manage-events.php?delete=<?= (int)$e['id'] ?>"
                         class="btn btn-small btn-danger"
                         onclick="return confirm('Delete this event?');">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
  // Notification dropdown
  const bell = document.getElementById('notificationBell');
  const notifDropdown = document.getElementById('notificationDropdown');
  if (bell && notifDropdown) {
    bell.addEventListener('click', () => {
      notifDropdown.classList.toggle('show');
    });
  }

  // Settings dropdown
  const settingsToggle = document.getElementById('settingsToggle');
  const settingsMenu = document.getElementById('settingsMenu');
  if (settingsToggle && settingsMenu) {
    settingsToggle.addEventListener('click', () => {
      settingsMenu.classList.toggle('show');
    });
  }

  // Dark mode
  const darkToggle = document.getElementById('darkModeToggle');
  if (darkToggle) {
    if (localStorage.getItem('darkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkToggle.checked = true;
    }
    darkToggle.addEventListener('change', () => {
      document.body.classList.toggle('dark-mode');
      if (document.body.classList.contains('dark-mode')) {
        localStorage.setItem('darkMode', 'enabled');
      } else {
        localStorage.setItem('darkMode', 'disabled');
      }
    });
  }

  // View details modal
  const backdrop = document.getElementById('eventModalBackdrop');
  const modalBody = document.getElementById('modalBody');
  const closeBtn = document.getElementById('modalCloseBtn');

  document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
      const title = btn.dataset.title || '';
      const description = btn.dataset.description || '';
      const category = btn.dataset.category || '';
      const date = btn.dataset.date || '';
      const time = btn.dataset.time || '';
      const location = btn.dataset.location || '';
      const image = btn.dataset.image || '';

      document.getElementById('modalTitle').textContent = title;

      let html = '';
      if (image) {
        html += `<img src="../../assets/img/${image}" alt="Event image">`;
      }
      html += `<p><strong>Category:</strong> ${category}</p>`;
      html += `<p><strong>Date:</strong> ${date}</p>`;
      if (time.trim()) {
        html += `<p><strong>Time:</strong> ${time}</p>`;
      }
      if (location.trim()) {
        html += `<p><strong>Location:</strong> ${location}</p>`;
      }
      if (description.trim()) {
        html += `<p><strong>Description:</strong><br>${description}</p>`;
      }

      modalBody.innerHTML = html;
      backdrop.classList.add('show');
    });
  });

  closeBtn.addEventListener('click', () => {
    backdrop.classList.remove('show');
  });
  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) {
      backdrop.classList.remove('show');
    }
  });
</script>
</body>
</html>