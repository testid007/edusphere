<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_user_id = (int)($_SESSION['user_id']);

try {
    // 1) Get child linked to this parent (single child for now)
    $stmt = $conn->prepare("
        SELECT 
            s.user_id,
            s.class,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM students s
        JOIN users   u ON s.user_id = u.id
        JOIN parents p ON p.student_id = s.user_id
        WHERE p.user_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_user_id]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$child) {
        throw new Exception('No children found for this parent.');
    }

    $studentId = (int)$child['user_id'];

    // 2) Latest invoice for this student (joins fee_structures + payments)
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
    $stmt->execute([':sid' => $studentId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        // No invoice yet – show a friendly message
        $noInvoiceMessage = 'No fee invoice has been generated yet for this student.';
    } else {
        $invoiceId     = (int)$invoice['invoice_id'];
        $amountDue     = (float)$invoice['amount_due'];
        $discount      = (float)$invoice['discount_amount'];
        $paidAmount    = (float)$invoice['paid_amount'];
        $netAmount     = max(0, $amountDue - $discount);
        $balance       = max(0, $netAmount - $paidAmount);

        if ($paidAmount >= $netAmount && $netAmount > 0) {
            $paymentStatus = 'Paid';
        } elseif ($paidAmount > 0 && $balance > 0) {
            $paymentStatus = 'Partially Paid';
        } else {
            $paymentStatus = 'Due';
        }

        // 3) Components for this invoice -> from fee_components via fee_structures
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

        // 4) Latest payment (for receipt number & date)
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
            // Fallback receipt/date when not yet paid
            $receiptNo   = '#INV' . date('Y') . sprintf('%04d', $invoiceId);
            $paymentDate = date('F j, Y', strtotime($invoice['created_at']));
        }
    }

} catch (Exception $e) {
    echo '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}
?>

<style>
.fee-status {
  margin-top: 10px;
}

.fee-status-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:12px;
}

.fee-status-header h3 {
  margin:0;
  font-size:1.05rem;
  font-weight:600;
}

.fee-status-header .muted {
  margin:2px 0 0;
  font-size:0.85rem;
  color:#6b7280;
}

.badge {
  display:inline-block;
  padding:4px 10px;
  border-radius:999px;
  font-size:0.78rem;
  font-weight:600;
}

.badge-success {
  background:#ecfdf3;
  color:#166534;
  border:1px solid #bbf7d0;
}

.badge-warning {
  background:#fffbeb;
  color:#92400e;
  border:1px solid #fed7aa;
}

.badge-partial {
  background:#eff6ff;
  color:#1d4ed8;
  border:1px solid #bfdbfe;
}

.student-info {
  margin:8px 0 12px;
}

.student-info.grid-2 {
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:8px 18px;
}

.info-row span {
  font-size:0.82rem;
  color:#6b7280;
}

.info-row strong {
  display:block;
  font-size:0.9rem;
}

.data-table {
  width:100%;
  border-collapse:collapse;
  font-size:0.87rem;
  margin-top:6px;
}

.data-table th,
.data-table td {
  border-bottom:1px solid #f3e5d7;
  padding:8px 10px;
  text-align:left;
}

.data-table thead th {
  background:#fff7e6;
  font-weight:600;
}

.data-table .table-total td {
  background:#fffbeb;
}

.fee-status-footer {
  margin-top:10px;
  font-size:0.85rem;
  color:#4b5563;
  text-align:right;
}

@media (max-width: 700px) {
  .student-info.grid-2 {
    grid-template-columns:1fr;
  }
}
</style>

<div class="section">
  <div class="section-header">
    <h2>Fee Status</h2>
    <p>Receipt and payment details for <?= htmlspecialchars($child['full_name']) ?>.</p>
  </div>

  <div class="card fee-status">
    <?php if (!empty($noInvoiceMessage)): ?>
      <p><?= htmlspecialchars($noInvoiceMessage) ?></p>
    </div>
  </div>
</div>
<?php return; endif; ?>

    <div class="fee-status-header">
      <div>
        <h3>Fee Receipt</h3>
        <p class="muted">
          EduSphere School · Class <?= htmlspecialchars($invoice['class_name']) ?> ·
          <?= date('M Y', strtotime($invoice['due_month'])) ?> (<?= htmlspecialchars($invoice['academic_year']) ?>)
        </p>
      </div>
      <?php
        $badgeClass = 'badge-warning';
        if ($paymentStatus === 'Paid')      $badgeClass = 'badge-success';
        elseif ($paymentStatus === 'Partially Paid') $badgeClass = 'badge-partial';
      ?>
      <span class="badge <?= $badgeClass ?>">
        <?= htmlspecialchars($paymentStatus) ?>
      </span>
    </div>

    <div class="student-info grid-2">
      <div class="info-row">
        <span>Student Name</span>
        <strong><?= htmlspecialchars($child['full_name']) ?></strong>
      </div>
      <div class="info-row">
        <span>Class</span>
        <strong><?= htmlspecialchars($child['class']) ?></strong>
      </div>
      <div class="info-row">
        <span>Receipt / Invoice No.</span>
        <strong><?= htmlspecialchars($receiptNo) ?></strong>
      </div>
      <div class="info-row">
        <span>Date</span>
        <strong><?= htmlspecialchars($paymentDate) ?></strong>
      </div>
    </div>

    <table class="data-table fee-details">
      <thead>
        <tr>
          <th>Description</th>
          <th>Amount (NPR)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($components)): ?>
          <tr><td colspan="2">No fee components defined.</td></tr>
        <?php else: ?>
          <?php foreach ($components as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['component_name']) ?></td>
              <td>Rs <?= number_format($c['amount'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="table-total">
            <td><strong>Net Amount (after discount)</strong></td>
            <td><strong>Rs <?= number_format($netAmount, 2) ?></strong></td>
          </tr>
          <tr>
            <td>Paid</td>
            <td>Rs <?= number_format($paidAmount, 2) ?></td>
          </tr>
          <tr>
            <td>Balance</td>
            <td>Rs <?= number_format($balance, 2) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="fee-status-footer">
      <?php if ($paymentStatus === 'Paid'): ?>
        Thank you for your payment. Keep this receipt for future reference.
      <?php elseif ($paymentStatus === 'Partially Paid'): ?>
        Partial payment received. Remaining balance is shown above.
      <?php else: ?>
        Invoice generated. Please clear the due amount at the accounts office.
      <?php endif; ?>
    </div>
  </div>
</div>
