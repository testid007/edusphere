<?php
session_start();
$student_name = $_SESSION['student_name'] ?? 'Ram Baban';  
$class = 'Grade 10'; 
$receipt_no = '#FEE2025001';
$payment_date = date('F j, Y'); 

$fee_details = [
  ['description' => 'Tuition Fee', 'amount' => 500.00],
  ['description' => 'Library Fee', 'amount' => 50.00],
  ['description' => 'Lab Fee', 'amount' => 30.00],
];
$total_paid = 580.00;
$payment_status = 'Paid'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Fee Details</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
        <a href="fees.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($_SESSION['student_email'] ?? 'student@example.com') ?></div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <h2>Fee Details</h2>
        <p>Welcome, <?= htmlspecialchars($student_name) ?>!</p>
      </header>

      <section class="fee-status-container">
        <div class="fee-status-header">
          <h2>Fee Receipt</h2>
          <p>School Name or Logo Here</p>
        </div>

        <div class="student-info">
          <div class="info-row">
            <div class="label">Student Name:</div>
            <div class="value"><?= htmlspecialchars($student_name) ?></div>
          </div>
          <div class="info-row">
            <div class="label">Class:</div>
            <div class="value"><?= htmlspecialchars($class) ?></div>
          </div>
          <div class="info-row">
            <div class="label">Receipt No:</div>
            <div class="value"><?= htmlspecialchars($receipt_no) ?></div>
          </div>
          <div class="info-row">
            <div class="label">Date:</div>
            <div class="value"><?= htmlspecialchars($payment_date) ?></div>
          </div>
        </div>

        <table class="fee-details">
          <thead>
            <tr>
              <th>Description</th>
              <th>Amount (NPR)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fee_details as $fee): ?>
              <tr>
                <td><?= htmlspecialchars($fee['description']) ?></td>
                <td>Rs.<?= number_format($fee['amount'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td>Total Paid</td>
              <td>Rs. <?= number_format($total_paid, 2) ?></td>
            </tr>
          </tbody>
        </table>

        <div style="text-align:center;">
          <span class="fee-status-badge"><?= htmlspecialchars($payment_status) ?></span>
        </div>

        <div class="fee-status-footer">
          Thank you for your payment!
        </div>
      </section>
    </main>
  </div>
</body>
</html>
