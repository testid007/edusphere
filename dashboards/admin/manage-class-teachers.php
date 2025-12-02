<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || strtolower($role) !== 'admin') {
    die('Access denied. Admins only.');
}

require_once __DIR__ . '/../../functions/ScheduleManager.php';
$scheduleManager = new ScheduleManager();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class     = $_POST['class'] ?? '';
    $teacherId = $_POST['teacher_id'] ?? '';

    if ($class !== '' && $teacherId !== '') {
        if ($scheduleManager->assignClassTeacher($class, (int)$teacherId)) {
            $success = "Class teacher assigned successfully for Class {$class}.";
        } else {
            $error = "Failed to assign class teacher.";
        }
    } else {
        $error = "Please select both class and teacher.";
    }
}

$teachers = $scheduleManager->getAllTeachers();
$classTeachersMap = $scheduleManager->getClassTeachers();

// Define classes – you can extend for PG, Nursery, LKG, etc.
$classes = ['PG','Nursery','LKG','UKG','1','2','3','4','5','6','7','8','9','10'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Class Teachers</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css">

  <!-- Page-scoped styling using your global theme variables -->
  <style>
    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
    }

    .main-content {
      padding: 24px 32px;
      background: transparent;
    }

    .assign-shell {
      max-width: 1100px;
      margin: 0 auto;
    }

    .assign-header {
      margin-bottom: 18px;
    }

    .assign-header-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 4px;
      color: var(--text-main);
    }

    .assign-header-subtitle {
      margin: 0;
      font-size: 0.95rem;
      color: var(--text-muted);
    }

    /* Card look aligned with your theme */
    .assign-card {
      background: var(--bg-main);
      border-radius: 16px;
      border: 1px solid var(--border-soft);
      box-shadow: var(--shadow-card);
      padding: 18px 20px;
      margin-bottom: 18px;
    }

    .assign-card + .assign-card {
      margin-top: 8px;
    }

    /* Alerts themed */
    .alert {
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.9rem;
      margin-bottom: 14px;
    }

    .alert-success {
      background: #dcfce7;
      border: 1px solid #22c55e33;
      color: #166534;
    }

    .alert-danger {
      background: #fee2e2;
      border: 1px solid #ef444433;
      color: #991b1b;
    }

    /* Form controls */
    .form-label {
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--text-main);
      margin-bottom: 4px;
    }

    .form-select {
      border-radius: 10px;
      border: 1px solid var(--border-soft);
      padding: 8px 10px;
      font-size: 0.9rem;
      background-color: #fff;
    }

    .form-select:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 1px var(--accent-soft);
    }

    /* Button */
    .btn-primary {
      background: var(--accent);
      border-color: var(--accent-strong);
      border-radius: 999px;
      padding-inline: 18px;
      padding-block: 8px;
      font-weight: 500;
      font-size: 0.95rem;
      box-shadow: 0 10px 22px rgba(245, 158, 11, 0.25);
    }

    .btn-primary:hover,
    .btn-primary:focus {
      background: var(--accent-strong);
      border-color: var(--accent-strong);
    }

    /* Table styling */
    .assign-card table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .assign-card thead th {
      padding: 8px 10px;
      border-bottom: 1px solid var(--border-soft);
      text-align: left;
      font-weight: 600;
      color: var(--text-muted);
      background: var(--bg-sidebar);
    }

    .assign-card tbody td {
      padding: 8px 10px;
      border-bottom: 1px solid #f3f4f6;
      color: var(--text-main);
    }

    .assign-card tbody tr:nth-child(odd) {
      background-color: #f9fafb;
    }

    .assign-card tbody tr:hover {
      background-color: var(--accent-soft);
    }

    .assign-empty {
      margin: 6px 0 0;
      font-size: 0.9rem;
      color: var(--text-soft);
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 16px;
      }
      .assign-card {
        padding: 14px 12px;
      }
    }
  </style>
</head>
<body>
  <div class="main-content">
    <div class="assign-shell">

      <header class="assign-header">
        <h2 class="assign-header-title">Assign Class Teacher</h2>
        <p class="assign-header-subtitle">
          Map each class (PG–10) to a single homeroom/class teacher for timetable and attendance logic.
        </p>
      </header>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Assign Form -->
      <div class="assign-card">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
          <div class="col-md-3 col-sm-6">
            <label for="class" class="form-label">Class</label>
            <select name="class" id="class" class="form-select" required>
              <option value="">-- Select Class --</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-5 col-sm-6">
            <label for="teacher_id" class="form-label">Class Teacher</label>
            <select name="teacher_id" id="teacher_id" class="form-select" required>
              <option value="">-- Select Teacher --</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>">
                  <?= htmlspecialchars($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary ms-auto">
              Save Class Teacher
            </button>
          </div>
        </form>
      </div>

      <!-- Current Assignments -->
      <div class="assign-card">
        <h5 style="margin: 0 0 10px; font-size: 1rem;">Current Class Teacher Assignments</h5>

        <?php if (!empty($classTeachersMap)): ?>
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Class</th>
                <th>Teacher</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classTeachersMap as $class => $row): ?>
                <tr>
                  <td><?= htmlspecialchars($class) ?></td>
                  <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="assign-empty">No class teachers assigned yet.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>
</html>
