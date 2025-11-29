<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$admin_name  = $_SESSION['admin_name']  ?? 'Main';
$admin_email = $_SESSION['admin_email'] ?? 'admin@example.com';
$admin_avatar = '../../assets/img/user.jpg';

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

function redirect_with_msg(array $params = []): void {
    $q = http_build_query($params);
    header('Location: fees.php' . ($q ? "?$q" : ''));
    exit;
}

/* ---------- HANDLE POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Generate invoices from a fee structure
    if ($action === 'generate_invoices') {
        $fsId = (int)($_POST['fee_structure_id'] ?? 0);

        if ($fsId <= 0) {
            redirect_with_msg(['error' => 'Please select a fee structure.']);
        }

        try {
            $stmt = $conn->prepare("
                SELECT id, class_name, total_amount
                FROM fee_structures
                WHERE id = :id
            ");
            $stmt->execute([':id' => $fsId]);
            $fs = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fs) {
                redirect_with_msg(['error' => 'Fee structure not found.']);
            }

            $class     = (int)$fs['class_name'];
            $amountDue = (float)$fs['total_amount'];

            $stmt = $conn->prepare("
                SELECT user_id
                FROM students
                WHERE class = :class
            ");
            $stmt->execute([':class' => $class]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($students)) {
                redirect_with_msg(['error' => 'No students found in this class.']);
            }

            $inserted = 0;

            $check = $conn->prepare("
                SELECT COUNT(*)
                FROM fee_invoices
                WHERE student_id = :sid
                  AND fee_structure_id = :fsid
            ");

            $insert = $conn->prepare("
                INSERT INTO fee_invoices (student_id, fee_structure_id, amount_due, discount_amount)
                VALUES (:sid, :fsid, :amount, 0)
            ");

            foreach ($students as $sid) {
                $sid = (int)$sid;
                $check->execute([':sid' => $sid, ':fsid' => $fsId]);
                if ($check->fetchColumn() == 0) {
                    $insert->execute([
                        ':sid'    => $sid,
                        ':fsid'   => $fsId,
                        ':amount' => $amountDue,
                    ]);
                    $inserted++;
                }
            }

            if ($inserted > 0) {
                redirect_with_msg([
                    'success' => "Invoices generated for {$inserted} student(s)."
                ]);
            } else {
                redirect_with_msg([
                    'success' => 'Invoices already exist for all students in this class for this fee structure.'
                ]);
            }

        } catch (PDOException $e) {
            redirect_with_msg(['error' => 'Database error while generating invoices.']);
        }
    }

    // Record a payment for an invoice
    if ($action === 'record_payment') {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        $date      = $_POST['payment_date'] ?? '';
        $method    = trim($_POST['method'] ?? '');
        $refNo     = trim($_POST['reference_no'] ?? '');
        $adminId   = (int)($_SESSION['user_id'] ?? 0);

        if ($invoiceId <= 0 || $amount <= 0 || $date === '') {
            redirect_with_msg(['error' => 'Invoice, amount, and date are required.']);
        }

        try {
            $stmt = $conn->prepare("SELECT id FROM fee_invoices WHERE id = :id");
            $stmt->execute([':id' => $invoiceId]);
            if (!$stmt->fetchColumn()) {
                redirect_with_msg(['error' => 'Invoice not found.']);
            }

            $receiptNo = 'F' . date('Y') . '-' . date('mdHis');

            $stmt = $conn->prepare("
                INSERT INTO fee_payments
                    (invoice_id, amount, payment_date, method, reference_no, receipt_no, recorded_by)
                VALUES
                    (:iid, :amount, :pdate, :method, :ref, :receipt, :admin)
            ");
            $stmt->execute([
                ':iid'     => $invoiceId,
                ':amount'  => $amount,
                ':pdate'   => $date,
                ':method'  => $method !== '' ? $method : null,
                ':ref'     => $refNo !== '' ? $refNo : null,
                ':receipt' => $receiptNo,
                ':admin'   => $adminId ?: null,
            ]);

            redirect_with_msg(['success' => 'Payment recorded successfully. Receipt: ' . $receiptNo]);

        } catch (PDOException $e) {
            redirect_with_msg(['error' => 'Database error while recording payment.']);
        }
    }
}

/* ---------- FETCH DATA FOR DISPLAY ---------- */

// active fee structures for dropdown
$feeStructures = [];
try {
    $stmt = $conn->query("
        SELECT id, class_name, due_month, academic_year, total_amount
        FROM fee_structures
        WHERE status = 'active'
        ORDER BY due_month DESC, class_name ASC
    ");
    $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feeStructures = [];
}

// invoices + payment summary
$invoices = [];
try {
    $stmt = $conn->query("
        SELECT 
            fi.id,
            fi.student_id,
            fi.amount_due,
            fi.discount_amount,
            fi.created_at,
            s.class,
            CONCAT(u.first_name, ' ', u.last_name) AS student_name,
            fs.class_name,
            fs.due_month,
            fs.academic_year,
            COALESCE(SUM(fp.amount),0) AS paid_amount
        FROM fee_invoices fi
        JOIN students s   ON s.user_id = fi.student_id
        JOIN users   u    ON u.id = fi.student_id
        JOIN fee_structures fs ON fs.id = fi.fee_structure_id
        LEFT JOIN fee_payments fp ON fp.invoice_id = fi.id
        GROUP BY fi.id
        ORDER BY fi.created_at DESC
        LIMIT 50
    ");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fees & Payments | EduSphere Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
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
      --shadow-card:0 14px 34px rgba(15,23,42,0.08);
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
      padding:24px 20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:26px;
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
      padding:10px 14px;
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

    .sidebar-admin-card{
      margin-top:24px;
      padding:14px 16px;
      border-radius:20px;
      background:radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:12px;
    }
    .sidebar-admin-card img{
      width:44px;
      height:44px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-admin-card .name{
      font-size:0.98rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-admin-card .role{
      font-size:0.8rem;
      color:#92400e;
    }

    .main{
      padding:24px 44px 36px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner{
      max-width:1000px;
      margin:0 auto;
    }
    .main-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
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
      min-width:190px;
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

    .fees-card{
      background:#fff;
      border-radius:18px;
      padding:18px 22px 22px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      margin-bottom:16px;
    }
    .fees-header-row{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      margin-bottom:14px;
    }
    .fees-header-row h3{
      margin:0;
      font-size:1.05rem;
      font-weight:600;
    }
    .fees-header-row p{
      margin:4px 0 0;
      font-size:0.85rem;
      color:var(--text-muted);
    }
    .fees-badge{
      font-size:0.78rem;
      padding:4px 10px;
      border-radius:999px;
      background:#fff7e6;
      color:#92400e;
      border:1px solid #fed7aa;
    }

    .fees-msg-success{
      background:#e8f5e9;color:#166534;
      padding:8px 12px;border-radius:8px;
      margin-bottom:10px;font-size:0.84rem;
    }
    .fees-msg-error{
      background:#ffebee;color:#b91c1c;
      padding:8px 12px;border-radius:8px;
      margin-bottom:10px;font-size:0.84rem;
    }

    .fees-grid{
      display:grid;
      grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);
      gap:18px;
    }
    @media(max-width:900px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:18px;}
      .fees-grid{grid-template-columns:1fr;}
    }

    .fees-block h4{
      margin:0 0 4px;
      font-size:0.96rem;
      font-weight:600;
    }
    .fees-block p{
      margin:0 0 10px;
      font-size:0.83rem;
      color:var(--text-muted);
    }

    .fees-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
    .fees-col{flex:1 1 200px;}
    .fees-label{display:block;font-size:0.82rem;font-weight:500;margin-bottom:4px;}
    .fees-input,.fees-select{
      width:100%;padding:8px 10px;border-radius:10px;
      border:1px solid #d1d5db;font-size:0.85rem;box-sizing:border-box;
    }
    .fees-input:focus,.fees-select:focus{
      border-color:#f97316;outline:none;
      box-shadow:0 0 0 1px rgba(249,115,22,0.35);
    }

    .fees-btn-primary{
      padding:8px 16px;border-radius:999px;border:none;
      background:#111827;color:#fff;font-size:0.85rem;
      font-weight:600;cursor:pointer;
    }
    .fees-btn-secondary{
      padding:7px 14px;border-radius:999px;
      border:1px solid #e5e7eb;background:#f9fafb;
      font-size:0.82rem;cursor:pointer;
    }

    .fees-table{
      width:100%;border-collapse:collapse;
      font-size:0.82rem;margin-top:8px;
    }
    .fees-table th,.fees-table td{
      padding:7px 9px;border-bottom:1px solid #e5e7eb;text-align:left;
    }
    .fees-table th{
      background:#f9fafb;font-weight:600;
    }
    .status-pill{
      display:inline-block;padding:3px 9px;border-radius:999px;font-size:0.7rem;
    }
    .status-paid{background:#dcfce7;color:#15803d;}
    .status-partial{background:#eff6ff;color:#1d4ed8;}
    .status-due{background:#fef3c7;color:#92400e;}
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
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="create-fee.php"><i class="fas fa-layer-group"></i> Create Fee</a>
        <a href="fees.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Fees &amp; Payments</a>
        <a href="reports.php"><i class="fas fa-chart-line"></i> View Reports</a>
        <a href="schedule.php"><i class="fas fa-calendar-alt"></i> Manage Schedule</a>
        <a href="schedule-view.php"><i class="fas fa-table"></i> Schedule View</a>
        <a href="events.php"><i class="fas fa-bullhorn"></i> Manage Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
    <div class="sidebar-admin-card">
      <img src="<?= h($admin_avatar) ?>" alt="Admin" />
      <div>
        <div class="name"><?= h($admin_name) ?></div>
        <div class="role">Administrator · EduSphere</div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <div class="main-header">
        <div class="main-header-left">
          <h2>Fees &amp; Payments</h2>
          <p>Generate class-wise invoices and record student payments.</p>
        </div>
        <div class="header-avatar">
          <img src="<?= h($admin_avatar) ?>" alt="Admin" />
          <div>
            <div class="name"><?= h($admin_name) ?></div>
            <div class="role"><?= h($admin_email) ?></div>
          </div>
        </div>
      </div>

      <!-- TOP CARD: generate + record -->
      <section class="fees-card">
        <div class="fees-header-row">
          <div>
            <h3>Fees &amp; Payments</h3>
            <p>Class fee invoices and payment recording from one place.</p>
          </div>
          <span class="fees-badge">Admin &gt; Fees</span>
        </div>

        <?php if ($success): ?>
          <div class="fees-msg-success"><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="fees-msg-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="fees-grid">
          <!-- Generate invoices -->
          <div class="fees-block">
            <h4>Generate Invoices</h4>
            <p>Select a fee structure (class + month) to create invoices for every student in that class.</p>

            <form method="POST">
              <input type="hidden" name="action" value="generate_invoices">
              <div class="fees-row">
                <div class="fees-col">
                  <label class="fees-label" for="fee_structure_id">Fee Structure</label>
                  <select class="fees-select" id="fee_structure_id" name="fee_structure_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($feeStructures as $fs): ?>
                      <option value="<?= (int)$fs['id'] ?>">
                        Class <?= h($fs['class_name']) ?> ·
                        <?= date('M Y', strtotime($fs['due_month'])) ?> ·
                        <?= h($fs['academic_year']) ?> ·
                        Total: रू <?= number_format($fs['total_amount'], 2) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button type="submit" class="fees-btn-primary">Generate Invoices for Class</button>
            </form>
          </div>

          <!-- Record payment -->
          <div class="fees-block">
            <h4>Record Payment</h4>
            <p>Enter invoice ID and payment details to record a new payment.</p>

            <form method="POST">
              <input type="hidden" name="action" value="record_payment">
              <div class="fees-row">
                <div class="fees-col">
                  <label class="fees-label" for="invoice_id">Invoice ID</label>
                  <input type="number" class="fees-input" id="invoice_id" name="invoice_id" required>
                </div>
                <div class="fees-col">
                  <label class="fees-label" for="amount">Amount (रू)</label>
                  <input type="number" step="0.01" min="0" class="fees-input" id="amount" name="amount" required>
                </div>
              </div>
              <div class="fees-row">
                <div class="fees-col">
                  <label class="fees-label" for="payment_date">Payment Date</label>
                  <input type="date" class="fees-input" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="fees-col">
                  <label class="fees-label" for="method">Method (optional)</label>
                  <input type="text" class="fees-input" id="method" name="method" placeholder="Cash, Bank, Online...">
                </div>
              </div>
              <div class="fees-row">
                <div class="fees-col">
                  <label class="fees-label" for="reference_no">Reference No. (optional)</label>
                  <input type="text" class="fees-input" id="reference_no" name="reference_no">
                </div>
              </div>
              <button type="submit" class="fees-btn-secondary">Save Payment</button>
            </form>
          </div>
        </div>
      </section>

      <!-- BOTTOM CARD: existing invoices -->
      <section class="fees-card">
        <h3 style="margin:0 0 4px;font-size:0.98rem;font-weight:600;">Existing Invoices</h3>
        <p style="margin:0 0 8px;font-size:0.83rem;color:#6b7280;">
          Overview of generated invoices with payment status.
        </p>

        <div style="overflow-x:auto;">
          <table class="fees-table">
            <thead>
            <tr>
              <th>ID</th>
              <th>Student</th>
              <th>Class</th>
              <th>Due Month</th>
              <th>Academic Year</th>
              <th>Net Amount</th>
              <th>Paid</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="10">No invoices generated yet.</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv):
                $amountDue = (float)$inv['amount_due'];
                $discount  = (float)$inv['discount_amount'];
                $paid      = (float)$inv['paid_amount'];
                $net       = max(0, $amountDue - $discount);
                $balance   = max(0, $net - $paid);

                if ($paid >= $net && $net > 0) {
                    $status = 'Paid'; $pill = 'status-paid';
                } elseif ($paid > 0 && $balance > 0) {
                    $status = 'Partially Paid'; $pill = 'status-partial';
                } else {
                    $status = 'Due'; $pill = 'status-due';
                }
              ?>
                <tr>
                  <td><?= (int)$inv['id'] ?></td>
                  <td><?= h($inv['student_name']) ?></td>
                  <td>Class <?= h($inv['class_name']) ?></td>
                  <td><?= date('M Y', strtotime($inv['due_month'])) ?></td>
                  <td><?= h($inv['academic_year']) ?></td>
                  <td>रू <?= number_format($net, 2) ?></td>
                  <td>रू <?= number_format($paid, 2) ?></td>
                  <td>रू <?= number_format($balance, 2) ?></td>
                  <td><span class="status-pill <?= $pill ?>"><?= h($status) ?></span></td>
                  <td><?= date('Y-m-d', strtotime($inv['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</div>
</body>
</html>
