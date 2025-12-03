<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$student_user_id = (int)($_SESSION['user_id'] ?? ($_SESSION['student_id'] ?? 0));
$student_name    = $_SESSION['student_name']  ?? 'Student';
$student_email   = $_SESSION['student_email'] ?? 'student@example.com';
$student_avatar  = '../../assets/img/user.jpg';

// We’ll resolve the class from DB (fallback to session)
$sessionClass = $_SESSION['class'] ?? null;
$studentClass = $sessionClass ?? 'Unknown';

$invoice        = null;
$components     = [];
$netAmount      = 0.0;
$paidAmount     = 0.0;
$balance        = 0.0;
$paymentStatus  = 'Due';
$receiptNo      = '';
$paymentDate    = '';
$noInvoiceMsg   = null;

try {
    if (!$student_user_id) {
        throw new Exception('Invalid student session.');
    }

    // 0) Fetch class from students table (for robustness)
    $stmt = $conn->prepare("
        SELECT class
        FROM students
        WHERE user_id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $student_user_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $studentClass = $row['class'] ?: $studentClass;
    }

    // 1) Latest invoice for this student (same logic as parent/fee-status.php)
    $stmt = $conn->prepare("
        SELECT 
            fi.id               AS invoice_id,
            fi.amount_due,
            fi.discount_amount,
            fi.created_at,
            fs.class_name,
            fs.due_month,
            fs.academic_year,
            COALESCE(SUM(fp.amount), 0) AS paid_amount
        FROM fee_invoices fi
        JOIN fee_structures fs ON fs.id = fi.fee_structure_id
        LEFT JOIN fee_payments fp ON fp.invoice_id = fi.id
        WHERE fi.student_id = :sid
        GROUP BY fi.id
        ORDER BY fs.due_month DESC, fi.id DESC
        LIMIT 1
    ");
    $stmt->execute([':sid' => $student_user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $noInvoiceMsg = 'No fee invoice has been generated for you yet.';
    } else {
        $invoiceId  = (int)$invoice['invoice_id'];
        $amountDue  = (float)$invoice['amount_due'];
        $discount   = (float)$invoice['discount_amount'];
        $paidAmount = (float)$invoice['paid_amount'];

        $netAmount  = max(0, $amountDue - $discount);
        $balance    = max(0, $netAmount - $paidAmount);

        if ($paidAmount >= $netAmount && $netAmount > 0) {
            $paymentStatus = 'Paid';
        } elseif ($paidAmount > 0 && $balance > 0) {
            $paymentStatus = 'Partially Paid';
        } else {
            $paymentStatus = 'Due';
        }

        // 2) Components for this invoice
        $stmt = $conn->prepare("
            SELECT fc.component_name, fc.amount
            FROM fee_invoices fi
            JOIN fee_structures fs ON fs.id = fi.fee_structure_id
            JOIN fee_components fc ON fc.fee_structure_id = fs.id
            WHERE fi.id = :iid
            ORDER BY fc.id
        ");
        $stmt->execute([':iid' => $invoiceId]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3) Latest payment (for receipt info)
        $stmt = $conn->prepare("
            SELECT receipt_no, payment_date, amount
            FROM fee_payments
            WHERE invoice_id = :iid
            ORDER BY payment_date DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':iid' => $invoiceId]);
        $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastPayment) {
            $receiptNo   = $lastPayment['receipt_no'];
            $paymentDate = date('F j, Y', strtotime($lastPayment['payment_date']));
        } else {
            $receiptNo   = '#INV' . date('Y') . sprintf('%04d', $invoiceId);
            $paymentDate = date('F j, Y', strtotime($invoice['created_at']));
        }
    }

} catch (Exception $e) {
    $noInvoiceMsg = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Fee Details | EduSphere</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    :root{
      --bg-page:#f5eee9;
      --bg-shell:#fdfcfb;
      --bg-sidebar:#fdf5ec;
      --bg-main:#ffffff;
      --accent:#f59e0b;
      --accent-soft:#fff5e5;
      --text-main:#111827;
      --text-muted:#6b7280;
      --border-soft:#f3e5d7;
      --shadow-card:0 12px 30px rgba(15,23,42,0.06);
    }
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:var(--bg-page);
      color:var(--text-main);
    }
    .app-shell{
      width:100%;
      display:grid;
      grid-template-columns:260px 1fr;
      min-height:100vh;
      background:var(--bg-shell);
    }
    .sidebar{
      background:var(--bg-sidebar);
      border-right:1px solid var(--border-soft);
      padding:28px 22px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:28px;
    }
    .logo img{height:40px;}
    .logo span{
      font-weight:700;
      font-size:1.15rem;
      color:#1f2937;
      letter-spacing:0.04em;
    }
    .nav{display:flex;flex-direction:column;gap:8px;}
    .nav a{
      display:flex;
      align-items:center;
      gap:10px;
      padding:11px 14px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.95rem;
      text-decoration:none;
      transition:background .15s,color .15s,transform .15s,box-shadow .15s;
    }
    .nav a i{
      width:20px;
      text-align:center;
      color:#9ca3af;
    }
    .nav a.active{
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
      box-shadow:0 10px 22px rgba(245,158,11,.35);
    }
    .nav a.active i{color:#f59e0b;}
    .nav a:hover{
      background:#ffeeda;
      color:#92400e;
      transform:translateX(3px);
    }
    .nav a.logout{margin-top:10px;color:#b91c1c;}

    .sidebar-student-card{
      margin-top:24px;
      padding:14px 16px;
      border-radius:20px;
      background:radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:12px;
    }
    .sidebar-student-card img{
      width:44px;
      height:44px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-student-card .name{
      font-size:0.98rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-student-card .role{
      font-size:0.8rem;
      color:#92400e;
    }

    .main{
      padding:24px 44px 36px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner{
      max-width:900px;
      margin:0 auto;
    }
    .main-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:16px;
    }
    .main-header-left h2{
      margin:0;
      font-size:1.7rem;
      font-weight:700;
    }
    .main-header-left p{
      margin:4px 0 0;
      font-size:0.92rem;
      color:var(--text-muted);
    }
    .header-avatar{
      display:flex;
      align-items:center;
      gap:10px;
      padding:6px 14px;
      border-radius:999px;
      background:#fff7ea;
      border:1px solid #fed7aa;
      min-width:180px;
    }
    .header-avatar img{
      width:32px;
      height:32px;
      border-radius:50%;
      object-fit:cover;
    }
    .header-avatar .name{
      font-size:0.95rem;
      font-weight:600;
      color:#78350f;
    }
    .header-avatar .role{
      font-size:0.78rem;
      color:#c05621;
    }

    .panel{
      background:var(--bg-main);
      border-radius:18px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:18px 20px 20px;
    }
    .panel-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .panel-header h4{
      margin:0;
      font-size:1.05rem;
    }
    .panel-header span{
      font-size:0.82rem;
      color:var(--text-muted);
    }

    .fee-bill-header{
      text-align:center;
      margin-bottom:14px;
    }
    .fee-bill-header h2{
      margin:0;
      font-size:1.25rem;
    }
    .fee-bill-header p{
      margin:4px 0 0;
      font-size:0.86rem;
      color:var(--text-muted);
    }

    .student-info{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:8px 20px;
      margin-bottom:14px;
      font-size:0.9rem;
    }
    .info-row{
      display:flex;
      gap:6px;
    }
    .info-row .label{
      font-weight:600;
      color:#374151;
      min-width:110px;
    }

    .fee-details{
      width:100%;
      border-collapse:collapse;
      font-size:0.9rem;
      margin-bottom:12px;
    }
    .fee-details th,
    .fee-details td{
      padding:8px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    .fee-details thead th{
      background:#fff7ea;
      text-transform:uppercase;
      letter-spacing:0.05em;
      font-size:0.82rem;
      color:#92400e;
    }

    .fee-status-badge{
      display:inline-block;
      padding:4px 12px;
      border-radius:999px;
      font-size:0.85rem;
      font-weight:600;
      margin:8px 0;
      background:#fffbeb;
      color:#92400e;
      border:1px solid #fed7aa;
    }
    .fee-status-badge.paid{
      background:#dcfce7;
      color:#15803d;
      border-color:#bbf7d0;
    }
    .fee-status-badge.partial{
      background:#eff6ff;
      color:#1d4ed8;
      border-color:#bfdbfe;
    }

    .fee-status-footer{
      margin-top:6px;
      font-size:0.86rem;
      color:var(--text-muted);
      text-align:center;
    }

    .print-btn{
      margin-top:10px;
      padding:6px 12px;
      border:none;
      border-radius:999px;
      background:#111827;
      color:#fff;
      font-size:0.85rem;
      cursor:pointer;
      font-weight:600;
    }

    @media(max-width:800px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:18px;}
      .student-info{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="EduSphere Logo" />
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="assignments.php"><i class="fas fa-book"></i> Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> Results</a>
        <a href="fees.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Fees</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
    <div class="sidebar-student-card">
      <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
      <div>
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="role">Student · EduSphere</div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <div class="main-header">
        <div class="main-header-left">
          <h2>Fee Details</h2>
          <p>View your latest invoice, payment status, and receipt summary.</p>
        </div>
        <div class="header-avatar">
          <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
          <div>
            <div class="name"><?= htmlspecialchars($student_name) ?></div>
            <div class="role"><?= htmlspecialchars($student_email) ?></div>
          </div>
        </div>
      </div>

      <section class="panel" id="feePanel">
        <div class="panel-header">
          <h4>Fee Receipt</h4>
          <span>For your personal records</span>
        </div>

        <?php if ($noInvoiceMsg): ?>
          <p><?= htmlspecialchars($noInvoiceMsg) ?></p>
        <?php else: ?>
          <div class="fee-bill-header">
            <h2>EduSphere School</h2>
            <p>
              Invoice for Class <?= htmlspecialchars($invoice['class_name'] ?: $studentClass) ?> ·
              <?= date('M Y', strtotime($invoice['due_month'])) ?>
              (<?= htmlspecialchars($invoice['academic_year']) ?>)
            </p>
          </div>

          <div class="student-info">
            <div class="info-row">
              <div class="label">Student Name:</div>
              <div class="value"><?= htmlspecialchars($student_name) ?></div>
            </div>
            <div class="info-row">
              <div class="label">Class:</div>
              <div class="value"><?= htmlspecialchars($invoice['class_name'] ?: $studentClass) ?></div>
            </div>
            <div class="info-row">
              <div class="label">Receipt / Invoice No:</div>
              <div class="value"><?= htmlspecialchars($receiptNo) ?></div>
            </div>
            <div class="info-row">
              <div class="label">Generated On:</div>
              <div class="value"><?= htmlspecialchars($paymentDate) ?></div>
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
              <?php if (!empty($components)): ?>
                <?php foreach ($components as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['component_name']) ?></td>
                    <td>Rs. <?= number_format($c['amount'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="2">No fee components defined.</td></tr>
              <?php endif; ?>
              <tr>
                <td><strong>Net Amount</strong></td>
                <td><strong>Rs. <?= number_format($netAmount, 2) ?></strong></td>
              </tr>
              <tr>
                <td>Paid</td>
                <td>Rs. <?= number_format($paidAmount, 2) ?></td>
              </tr>
              <tr>
                <td>Balance</td>
                <td>Rs. <?= number_format($balance, 2) ?></td>
              </tr>
            </tbody>
          </table>

          <?php
            $badgeClass = '';
            if ($paymentStatus === 'Paid') {
                $badgeClass = 'paid';
            } elseif ($paymentStatus === 'Partially Paid') {
                $badgeClass = 'partial';
            }
          ?>
          <div style="text-align:center;">
            <span class="fee-status-badge <?= $badgeClass ?>">
              <?= htmlspecialchars($paymentStatus) ?>
            </span>
          </div>

          <div class="fee-status-footer">
            <?php if ($paymentStatus === 'Paid'): ?>
              Thank you for your payment. Keep this receipt safely for future reference.
            <?php elseif ($paymentStatus === 'Partially Paid'): ?>
              Partial payment recorded. Remaining balance is shown above.
            <?php else: ?>
              Invoice generated. Please clear the due amount at the accounts office.
            <?php endif; ?>
          </div>

          <div style="text-align:center;">
            <button class="print-btn" onclick="window.print();">
              <i class="fa-solid fa-print"></i> Print / Save as PDF
            </button>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</div>
</body>
</html>
