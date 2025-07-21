<?php
require_once __DIR__ . '/../../functions/ScheduleManager.php';
$scheduleManager = new ScheduleManager();

// Initialize success or error message
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherId = $_POST['teacher_id'] ?? null;
    $subjectIds = $_POST['subject_ids'] ?? [];

    // Basic validation
    if ($teacherId && !empty($subjectIds)) {
        // Save the selected subjects
        if ($scheduleManager->assignSubjectsToTeacher($teacherId, $subjectIds)) {
            $success = "✅ Subjects assigned successfully!";
        } else {
            $error = "❌ Failed to assign subjects.";
        }
    } else {
        $error = "❗ Please select a teacher and at least one subject.";
    }
}

// Get all teachers and all available subjects
$teachers = $scheduleManager->getAllTeachers();
$subjects = $scheduleManager->getAllSubjects();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Subjects to Teachers</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css"> <!-- Include your admin CSS -->
    <style>
        .alert-success { background: #d4edda; padding: 10px; color: #155724; margin-bottom: 15px; border-left: 5px solid green; }
        .alert-danger { background: #f8d7da; padding: 10px; color: #721c24; margin-bottom: 15px; border-left: 5px solid red; }
    </style>
</head>
<body>
    <div class="container">
        

        <!-- Success or error messages -->
        <?php if (!empty($success)): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Subject Assignment Form -->
        <form method="POST" class="card p-4">
            <!-- Teacher Dropdown -->
            <div class="mb-3">
                <label for="teacher_id">Select Teacher:</label>
                <select name="teacher_id" id="teacher_id" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= htmlspecialchars($teacher['id']) ?>">
                            <?= htmlspecialchars($teacher['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subjects Dropdown -->
            <div class="mb-3">
                <label for="subject_ids">Select Subjects:</label>
                <select name="subject_ids[]" id="subject_ids" multiple class="form-select" required>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars($subject['id']) ?>">
                            <?= htmlspecialchars($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>You can hold CTRL or CMD to select multiple subjects.</small>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary">Assign Subjects</button>
        </form>
    </div>
</body>
</html>
