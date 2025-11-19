<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

// Canonical identity
$student_id   = (int)($_SESSION['user_id'] ?? ($_SESSION['student_id'] ?? 0));
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_email= $_SESSION['student_email'] ?? ($_SESSION['student_email'] ?? 'guest@example.com');
$class        = $_SESSION['class'] ?? 'Grade 10';

// Prepare receipt no and date
$receipt_no    = '#FEE' . date('Y') . str_pad($student_id ?: 0, 4, '0', STR_PAD_LEFT);
$payment_date  = date('F j, Y');

$fee_details = [];
$total_paid  = 0.00;

if ($student_id) {
    try {
        $sql = "SELECT description, amount 
                FROM fees 
                WHERE student_id = :student_id";
        $params = [':student_id' => $student_id];

        if ($class) {
            $sql .= " AND class_name = :class_name";
            $params[':class_name'] = $class;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fee_details[] = $row;
            $total_paid   += (float)$row['amount'];
        }
    } catch (PDOException $e) {
        die("Error fetching fee data: " . $e->getMessage());
    }
}

$payment_status = $total_paid > 0 ? 'Paid' : 'Pending';
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
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($student_email) ?></div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <div>
          <h2>Fee Details</h2>
          <p>Welcome, <?= htmlspecialchars($student_name) ?>!</p>
        </div>
      </header>

      <section class="fee-status-container">
        <div class="fee-status-header">
          <h2>Fee Receipt</h2>
          <p>Your School Name Here</p>
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
            <?php if (count($fee_details) > 0): ?>
              <?php foreach ($fee_details as $fee): ?>
                <tr>
                  <td><?= htmlspecialchars($fee['description']) ?></td>
                  <td>Rs. <?= number_format($fee['amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr>
                <td><strong>Total Paid</strong></td>
                <td><strong>Rs. <?= number_format($total_paid, 2) ?></strong></td>
              </tr>
            <?php else: ?>
              <tr><td colspan="2">No fee records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div style="text-align:center;">
          <span class="fee-status-badge"><?= htmlspecialchars($payment_status) ?></span>
        </div>

        <div class="fee-status-footer">
          <?= $payment_status === 'Paid'
                    ? 'Thank you for your payment!'
                    : 'No payment recorded yet. Please contact the account section.' ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
