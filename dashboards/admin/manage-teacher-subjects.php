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
    $teacherId   = $_POST['teacher_id'] ?? null;
    $subjectIds  = $_POST['subject_ids'] ?? [];

    if ($teacherId && is_array($subjectIds) && count($subjectIds) > 0) {
        if ($scheduleManager->assignSubjectsToTeacher((int)$teacherId, $subjectIds)) {
            $success = "Subjects assigned successfully.";
        } else {
            $error = "Failed to assign subjects.";
        }
    } else {
        $error = "Please select a teacher and at least one subject.";
    }
}

// Data for form
$teachers = $scheduleManager->getAllTeachers();
$subjects = $scheduleManager->getAllSubjects();
$teachersWithSubjects = $scheduleManager->getTeachersWithSubjects();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Teacher Subjects</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css">

  <!-- Page-scoped styling using global theme variables -->
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

    .subject-shell {
      max-width: 1100px;
      margin: 0 auto;
    }

    .subject-header {
      margin-bottom: 18px;
    }

    .subject-header-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 4px;
      color: var(--text-main);
    }

    .subject-header-subtitle {
      margin: 0;
      font-size: 0.95rem;
      color: var(--text-muted);
    }

    /* Card look aligned with theme */
    .subject-card {
      background: var(--bg-main);
      border-radius: 16px;
      border: 1px solid var(--border-soft);
      box-shadow: var(--shadow-card);
      padding: 18px 20px;
      margin-bottom: 18px;
    }

    .subject-card + .subject-card {
      margin-top: 8px;
    }

    /* Alerts */
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

    /* Checkboxes */
    .form-check-input {
      cursor: pointer;
    }

    .form-check-input:checked {
      background-color: var(--accent);
      border-color: var(--accent-strong);
    }

    .form-check-label {
      font-size: 0.9rem;
      color: var(--text-main);
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
    .subject-card table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .subject-card thead th {
      padding: 8px 10px;
      border-bottom: 1px solid var(--border-soft);
      text-align: left;
      font-weight: 600;
      color: var(--text-muted);
      background: var(--bg-sidebar);
    }

    .subject-card tbody td {
      padding: 8px 10px;
      border-bottom: 1px solid #f3f4f6;
      color: var(--text-main);
    }

    .subject-card tbody tr:nth-child(odd) {
      background-color: #f9fafb;
    }

    .subject-card tbody tr:hover {
      background-color: var(--accent-soft);
    }

    .subject-empty {
      margin: 6px 0 0;
      font-size: 0.9rem;
      color: var(--text-soft);
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 16px;
      }
      .subject-card {
        padding: 14px 12px;
      }
    }
  </style>
</head>
<body>
  <div class="main-content">
    <div class="subject-shell">

      <header class="subject-header">
        <h2 class="subject-header-title">Assign Subjects to Teacher</h2>
        <p class="subject-header-subtitle">
          Link each teacher with the subjects they are allowed to teach for timetable and workload logic.
        </p>
      </header>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Assign Subjects Form -->
      <div class="card mb-4 subject-card">
        <div class="card-body">
          <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="mb-3">
              <label for="teacher_id" class="form-label">Select Teacher</label>
              <select name="teacher_id" id="teacher_id" class="form-select" required>
                <option value="">-- Choose a Teacher --</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= (int)$t['id'] ?>">
                    <?= htmlspecialchars($t['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label d-block">Assign Subjects</label>
              <div class="row">
                <?php foreach ($subjects as $subject): ?>
                  <div class="col-md-3 mb-2">
                    <div class="form-check">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        id="sub_<?= (int)$subject['id'] ?>"
                        name="subject_ids[]"
                        value="<?= (int)$subject['id'] ?>">
                      <label class="form-check-label" for="sub_<?= (int)$subject['id'] ?>">
                        <?= htmlspecialchars($subject['name']) ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Assignments</button>
          </form>
        </div>
      </div>

      <!-- Current Teacher–Subject Mappings -->
      <div class="card subject-card">
        <div class="card-body">
          <h5 style="margin: 0 0 10px; font-size: 1rem;">Current Teacher–Subject Mappings</h5>
          <?php if (!empty($teachersWithSubjects)): ?>
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Teacher</th>
                  <th>Subjects</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teachersWithSubjects as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['subjects'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="subject-empty">No subject assignments found yet.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
