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
</head>
<body>
  <div class="main-content">
    <div class="container-fluid">
      <h2>Assign Subjects to Teacher</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="card mb-4">
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

      <div class="card">
        <div class="card-body">
          <h5>Current Teacher–Subject Mappings</h5>
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
            <p class="text-muted mb-0">No subject assignments found yet.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
