<?php
session_start();
require_once '../../includes/db.php';

// Make sure the parent is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    die('<p style="color:red;">Access denied. Please log in as a parent.</p>');
}

$parent_id = $_SESSION['user_id'];

// Find the child student_id for this parent from the parents table
try {
    $stmt = $conn->prepare("SELECT student_id FROM parents WHERE user_id = :parent_id LIMIT 1");
    $stmt->execute([':parent_id' => $parent_id]);
    $childRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$childRow) {
        die('<p style="color:red;">No child linked to this parent account.</p>');
    }
    $child_student_id = $childRow['student_id'];
} catch (PDOException $e) {
    die('<p style="color:red;">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Fetch latest grades for each subject in 'Exam' or 'Assignment' category
try {
    $stmt = $conn->prepare("
        SELECT title AS subject, score
        FROM grades
        WHERE student_id = :student_id AND category IN ('Exam', 'Assignment')
        ORDER BY subject, date_added DESC
    ");
    $stmt->execute([':student_id' => $child_student_id]);
    $gradeRows = $stmt->fetchAll();

    // Only keep the latest score per subject
    $latestScores = [];
    foreach ($gradeRows as $row) {
        $subject = $row['subject'];
        if (!isset($latestScores[$subject])) {
            $latestScores[$subject] = floatval($row['score']);
        }
    }
} catch (PDOException $e) {
    echo '<p style="color:red;">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    $latestScores = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Child Academic Performance</title>
<style>
  .performance-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
  }
  .performance-table th, .performance-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
  }
  .performance-table th {
    background-color: #4caf50;
    color: white;
    text-align: left;
  }
</style>
</head>
<body>

<h3>Your child’s recent academic scores:</h3>

<table class="performance-table">
  <thead>
    <tr><th>Subject</th><th>Score (%)</th></tr>
  </thead>
  <tbody>
    <?php if (empty($latestScores)): ?>
      <tr><td colspan="2">No scores available.</td></tr>
    <?php else: ?>
      <?php foreach ($latestScores as $subject => $score): ?>
        <tr>
          <td><?= htmlspecialchars($subject) ?></td>
          <td><?= htmlspecialchars($score) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>