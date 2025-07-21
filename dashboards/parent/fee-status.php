<?php
session_start();
require_once '../../includes/db.php'; // your PDO connection in $conn

// Use logged-in parent user id, or debug fallback
$parent_user_id = $_SESSION['user_id'] ?? 36;

try {
    // Get children of this parent
    $stmt = $conn->prepare("
        SELECT s.user_id, s.class, CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN parents p ON p.student_id = s.user_id
        WHERE p.user_id = :parent_id
    ");
    $stmt->execute([':parent_id' => $parent_user_id]);
    $children = $stmt->fetchAll();

    if (!$children) {
        throw new Exception("No children found for this parent.");
    }

    $child = $children[0]; // Use first child for demo or extend for multiple

    // Fetch fee details for this child
    $stmt = $conn->prepare("SELECT description, amount FROM fees WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $child['user_id']]);
    $feeDetails = $stmt->fetchAll();

    $totalPaid = 0;
    foreach ($feeDetails as $fee) {
        $totalPaid += (float)$fee['amount'];
    }

    $receiptNo = '#FEE2025001';
    $paymentDate = date('F j, Y');

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Parent - Child Fee Details</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    /* Your CSS here */
  </style>
</head>
<body>

<div class="fee-status-container">
  <div class="fee-status-header">
    <h2>Fee Receipt</h2>
    <p>School Name or Logo Here</p>
  </div>

  <div class="student-info">
    <div class="info-row"><div>Student Name:</div><div><?= htmlspecialchars($child['full_name']) ?></div></div>
    <div class="info-row"><div>Class:</div><div><?= htmlspecialchars($child['class']) ?></div></div>
    <div class="info-row"><div>Receipt No:</div><div><?= htmlspecialchars($receiptNo) ?></div></div>
    <div class="info-row"><div>Date:</div><div><?= htmlspecialchars($paymentDate) ?></div></div>
  </div>

  <table class="fee-details">
    <thead>
      <tr><th>Description</th><th>Amount (NPR)</th></tr>
    </thead>
    <tbody>
      <?php foreach ($feeDetails as $fee): ?>
      <tr>
        <td><?= htmlspecialchars($fee['description']) ?></td>
        <td>Rs. <?= number_format($fee['amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td><strong>Total Paid</strong></td>
        <td><strong>Rs. <?= number_format($totalPaid, 2) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <div style="text-align:center; margin-bottom: 16px;">
    <span class="fee-status-badge">Paid</span>
  </div>

  <div class="fee-status-footer">Thank you for your payment!</div>
</div>

</body>
</html>
