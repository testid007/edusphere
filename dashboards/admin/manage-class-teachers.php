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
</head>
<body>
  <div class="main-content">
    <div class="container-fluid">
      <h2>Assign Class Teacher</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-body">
          <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
            <div class="col-md-3">
              <label for="class" class="form-label">Class</label>
              <select name="class" id="class" class="form-select" required>
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $cls): ?>
                  <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-5">
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
              <button type="submit" class="btn btn-primary">Save Class Teacher</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5>Current Class Teacher Assignments</h5>
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
            <p class="text-muted mb-0">No class teachers assigned yet.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
