<?php
session_start();
require_once '../../includes/db.php'; // Adjust path to your db.php

// Temporary debug - simulate logged-in parent and child student ID
$child_student_id = $_SESSION['child_student_id'] ?? 1;  // Change to real student ID in production

// Helper function to map letter grades to numeric values for averaging
function gradeToNumber($grade) {
    $map = [
        'A+' => 4.3, 'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0, 'F' => 0,
    ];
    return $map[strtoupper($grade)] ?? null;
}

try {
    // Fetch attendance percentage
    $stmt = $conn->prepare("SELECT SUM(present_days) AS present, SUM(total_days) AS total FROM attendance WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $child_student_id]);
    $attendanceData = $stmt->fetch();
    if ($attendanceData && $attendanceData['total'] > 0) {
        $attendance = round(($attendanceData['present'] / $attendanceData['total']) * 100, 2);
    } else {
        $attendance = 0;
    }

    // Calculate total fees
    $stmt = $conn->prepare("SELECT SUM(amount) as total_fee FROM fees WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $child_student_id]);
    $total_fee = $stmt->fetchColumn() ?? 0;

    // Calculate total paid
    $stmt = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM fee_payments WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $child_student_id]);
    $total_paid = $stmt->fetchColumn() ?? 0;

    // Calculate remaining fee
    $remaining_fee = max(0, $total_fee - $total_paid);

    // Fetch grades and compute average grade (numeric)
    $stmt = $conn->prepare("SELECT grade FROM grades WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $child_student_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $grade_sum = 0;
    $grade_count = 0;
    foreach ($grades as $grade) {
        $num = is_numeric($grade) ? floatval($grade) : gradeToNumber($grade);
        if ($num !== null) {
            $grade_sum += $num;
            $grade_count++;
        }
    }
    $avg_grade = $grade_count > 0 ? round($grade_sum / $grade_count, 2) : 'N/A';

} catch (PDOException $e) {
    // Handle DB errors
    $attendance = 0;
    $remaining_fee = 0;
    $avg_grade = 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Parent Dashboard</title>
<style>
  .cards {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
  margin-bottom: 32px;
}

.card {
  background: #fff;
  border-radius: 18px;
  padding: 24px 28px;
  box-shadow: 0 2px 16px rgba(60, 72, 88, 0.08);
  flex: 1 1 220px;
  min-width: 200px;
  max-width: 320px;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  border: 1px solid #f0f0f0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.card:hover {
  transform: translateY(-6px) scale(1.03);
  box-shadow: 0 6px 32px rgba(76, 175, 80, 0.13);
  border-color: #4caf50;
}

.card h3 {
  font-size: 17px;
  font-weight: 700;
  margin-bottom: 8px;
  color: #4caf50;
  letter-spacing: 0.01em;
}

.card p {
  font-size: 24px;
  font-weight: 600;
  color: #222;
  margin: 0;
}

</style>
</head>
<body>
  <div class="cards">
    <div class="card">
      <h3>Child Progress</h3>
      <p>80%</p> <!-- Static placeholder -->
    </div>
    <div class="card">
      <h3>Attendance Record</h3>
      <p><?= htmlspecialchars($attendance) ?>%</p>
    </div>
    <div class="card">
      <h3>Fee Payment Remaining</h3>
      <p>Rs <?= number_format($remaining_fee, 2) ?></p>
    </div>
    <div class="card">
      <h3>Student Result (Avg Grade)</h3>
      <p><?= htmlspecialchars($avg_grade) ?></p>
    </div>
  </div>
</body>
</html>
