<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php'; // adjust path if needed

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message(array $params = []): void {
    $query = http_build_query($params); // encodes safely
    header('Location: create-fee.php' . ($query ? "?$query" : ""));
    exit;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$editMode       = false;
$editStructure  = null;
$editComponents = [];

/* ---------- DELETE ---------- */
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    if ($deleteId > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM fee_structures WHERE id = :id");
            $stmt->execute([':id' => $deleteId]);
            redirect_with_message(['success' => 'Fee structure deleted successfully.']);
        } catch (PDOException $e) {
            redirect_with_message(['error' => 'Could not delete fee structure.']);
        }
    } else {
        redirect_with_message(['error' => 'Invalid fee structure ID.']);
    }
}

/* ---------- TOGGLE STATUS (Active / Archived) ---------- */
if (isset($_GET['toggle_id'], $_GET['status'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $status   = $_GET['status'] === 'archived' ? 'archived' : 'active';

    if ($toggleId > 0) {
        try {
            $stmt = $conn->prepare("UPDATE fee_structures SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $toggleId]);
            $msg = $status === 'active'
                ? 'Fee structure marked as Active.'
                : 'Fee structure archived.';
            redirect_with_message(['success' => $msg]);
        } catch (PDOException $e) {
            redirect_with_message(['error' => 'Could not update status.']);
        }
    }
}

/* ---------- CREATE / UPDATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $feeId      = isset($_POST['fee_structure_id']) ? (int)$_POST['fee_structure_id'] : 0;
    $classValue = isset($_POST['class_name']) ? (int)$_POST['class_name'] : 0; // matches students.class
    $dueMonth   = trim($_POST['due_month'] ?? ''); // YYYY-MM
    $adminId    = (int)($_SESSION['user_id'] ?? 0);
    $components = $_POST['components'] ?? [];

    if ($classValue <= 0 || $dueMonth === '') {
        redirect_with_message(['error' => 'Class and Due Month are required.']);
    }

    if (empty($components) || !is_array($components)) {
        redirect_with_message(['error' => 'Please add at least one fee component.']);
    }

    $dueMonthDate = $dueMonth . '-01';
    $year         = (int)substr($dueMonth, 0, 4);
    $academicYear = $year . '/' . ($year + 1);

    $cleanComponents = [];
    $totalAmount     = 0;

    foreach ($components as $comp) {
        $name   = trim($comp['name']   ?? '');
        $amount = trim($comp['amount'] ?? '');

        if ($name === '' && $amount === '') {
            continue;
        }
        if ($name === '') {
            redirect_with_message(['error' => 'One of the component names is empty.']);
        }
        if ($amount === '' || !is_numeric($amount) || $amount < 0) {
            redirect_with_message(['error' => 'Invalid amount for component: ' . $name]);
        }

        $amount = (float)$amount;
        $totalAmount += $amount;

        $cleanComponents[] = [
            'name'   => $name,
            'amount' => $amount,
        ];
    }

    if ($totalAmount <= 0 || empty($cleanComponents)) {
        redirect_with_message(['error' => 'Total amount must be greater than zero with at least one valid component.']);
    }

    try {
        if ($feeId > 0) {
            /* ----- UPDATE ----- */
            $check = $conn->prepare("
                SELECT COUNT(*) FROM fee_structures
                WHERE class_name = :class
                  AND due_month = :due_month
                  AND academic_year = :ay
                  AND id <> :id
            ");
            $check->execute([
                ':class'     => $classValue,
                ':due_month' => $dueMonthDate,
                ':ay'        => $academicYear,
                ':id'        => $feeId,
            ]);
            if ($check->fetchColumn() > 0) {
                redirect_with_message(['error' => 'Another fee structure already exists for this class and month.']);
            }

            $conn->beginTransaction();

            $update = $conn->prepare("
                UPDATE fee_structures
                SET class_name = :class,
                    due_month = :due_month,
                    academic_year = :ay,
                    total_amount = :total
                WHERE id = :id
            ");
            $update->execute([
                ':class'     => $classValue,
                ':due_month' => $dueMonthDate,
                ':ay'        => $academicYear,
                ':total'     => $totalAmount,
                ':id'        => $feeId,
            ]);

            $delComps = $conn->prepare("DELETE FROM fee_components WHERE fee_structure_id = :id");
            $delComps->execute([':id' => $feeId]);

            $insComp = $conn->prepare("
                INSERT INTO fee_components (fee_structure_id, component_name, amount)
                VALUES (:fsid, :name, :amount)
            ");
            foreach ($cleanComponents as $c) {
                $insComp->execute([
                    ':fsid'  => $feeId,
                    ':name'  => $c['name'],
                    ':amount'=> $c['amount'],
                ]);
            }

            $conn->commit();
            redirect_with_message(['success' => 'Fee structure updated successfully.']);

        } else {
            /* ----- CREATE ----- */
            $check = $conn->prepare("
                SELECT COUNT(*) FROM fee_structures
                WHERE class_name = :class
                  AND due_month = :due_month
                  AND academic_year = :ay
            ");
            $check->execute([
                ':class'     => $classValue,
                ':due_month' => $dueMonthDate,
                ':ay'        => $academicYear,
            ]);
            if ($check->fetchColumn() > 0) {
                redirect_with_message(['error' => 'Fee structure already exists for this class and month.']);
            }

            $conn->beginTransaction();

            $insert = $conn->prepare("
                INSERT INTO fee_structures (class_name, due_month, academic_year, total_amount, created_by)
                VALUES (:class, :due_month, :ay, :total, :created_by)
            ");
            $insert->execute([
                ':class'      => $classValue,
                ':due_month'  => $dueMonthDate,
                ':ay'         => $academicYear,
                ':total'      => $totalAmount,
                ':created_by' => $adminId ?: null,
            ]);

            $newId = (int)$conn->lastInsertId();

            $insComp = $conn->prepare("
                INSERT INTO fee_components (fee_structure_id, component_name, amount)
                VALUES (:fsid, :name, :amount)
            ");
            foreach ($cleanComponents as $c) {
                $insComp->execute([
                    ':fsid'  => $newId,
                    ':name'  => $c['name'],
                    ':amount'=> $c['amount'],
                ]);
            }

            $conn->commit();
            redirect_with_message(['success' => 'Fee structure created successfully.']);
        }

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        redirect_with_message(['error' => 'Database error occurred.']);
    }
}

/* ---------- EDIT MODE ---------- */
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if ($editId > 0) {
        try {
            $s = $conn->prepare("SELECT * FROM fee_structures WHERE id = :id");
            $s->execute([':id' => $editId]);
            $editStructure = $s->fetch(PDO::FETCH_ASSOC);

            if ($editStructure) {
                $editMode = true;
                $c = $conn->prepare("
                    SELECT component_name, amount
                    FROM fee_components
                    WHERE fee_structure_id = :id
                    ORDER BY id ASC
                ");
                $c->execute([':id' => $editId]);
                $editComponents = $c->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            // ignore
        }
    }
}

/* ---------- FETCH ALL FOR TABLE ---------- */
$feeRows = [];
try {
    $stmt = $conn->query("
        SELECT 
            fs.id,
            fs.class_name,
            fs.due_month,
            fs.academic_year,
            fs.total_amount,
            fs.status,
            fs.created_at,
            GROUP_CONCAT(CONCAT(fc.component_name, ' (', fc.amount, ')') SEPARATOR ', ') AS components
        FROM fee_structures fs
        LEFT JOIN fee_components fc ON fc.fee_structure_id = fs.id
        GROUP BY fs.id
        ORDER BY fs.due_month DESC, fs.class_name ASC
    ");
    $feeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Fee Structure - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Only scoped classes, no global body overrides -->
  <style>
    .fee-shell {
      max-width: 1100px;
      margin: 0 auto;
      padding-bottom: 24px;
    }
    .fee-card {
      background: var(--bg-main, #fff);
      border-radius: 18px;
      padding: 20px 24px;
      box-shadow: 0 14px 34px rgba(15,23,42,0.08);
      margin-bottom: 24px;
      border: 1px solid rgba(249, 115, 22, 0.06);
    }
    .fee-card-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .fee-card h2 {
      margin: 0 0 4px;
      font-size: 20px;
    }
    .fee-card p {
      margin:0;
      font-size:13px;
      color:#6b7280;
    }
    .fee-badge {
      display:inline-flex;
      align-items:center;
      border-radius:999px;
      padding:3px 10px;
      font-size:12px;
      background:#fff7ed;
      color:#c2410c;
      border:1px solid #fed7aa;
    }
    .fee-msg-success {
      background:#e8f5e9;
      color:#166534;
      padding:10px 16px;
      border-radius:8px;
      margin-bottom:12px;
      font-size: 14px;
    }
    .fee-msg-error {
      background:#ffebee;
      color:#b91c1c;
      padding:10px 16px;
      border-radius:8px;
      margin-bottom:12px;
      font-size: 14px;
    }
    .fee-label {
      font-size: 14px;
      font-weight: 500;
      display: block;
      margin-bottom: 6px;
    }
    .fee-input,
    .fee-select {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
      background:#fff;
    }
    .fee-input:focus,
    .fee-select:focus {
      border-color: #f97316;
      box-shadow: 0 0 0 1px rgba(249,115,22,0.35);
    }
    .fee-form-row {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .fee-form-col {
      flex: 1 1 220px;
    }
    .fee-btn-primary {
      padding: 10px 22px;
      border-radius: 999px;
      border: none;
      cursor: pointer;
      background: #111827;
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .fee-btn-secondary {
      padding: 7px 14px;
      border-radius: 999px;
      border: 1px dashed #9ca3af;
      cursor: pointer;
      background: #f9fafb;
      color: #4b5563;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .fee-btn-secondary:hover {
      border-style: solid;
    }
    .fee-components-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }
    .fee-components-table th,
    .fee-components-table td {
      padding: 8px 10px;
      font-size: 13px;
      border-bottom: 1px solid #e5e7eb;
    }
    .fee-components-table th {
      text-align: left;
      background: #fff7ed;
      font-weight: 600;
      color:#92400e;
    }
    .fee-components-table td input {
      width: 100%;
    }
    .fee-remove-row {
      cursor: pointer;
      font-size: 18px;
      border: none;
      background: transparent;
      color: #b91c1c;
    }
    .fee-total-row {
      text-align: right;
      font-size: 14px;
      font-weight: 600;
      padding-top: 8px;
    }
    .fee-total-row span {
      font-weight: 700;
    }
    .fee-list-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 13px;
      background: #ffffff;
      border-radius: 14px;
      overflow: hidden;
    }
    .fee-list-table th,
    .fee-list-table td {
      padding: 9px 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
    }
    .fee-list-table th {
      background: #f9fafb;
      font-weight: 600;
    }
    .fee-status-pill {
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 12px;
      background: #e0f2fe;
      color: #075985;
    }
    .fee-status-pill.archived {
      background: #fef3c7;
      color: #92400e;
    }
    .fee-link {
      font-size: 13px;
      text-decoration: none;
      color: #2563eb;
      margin-right: 8px;
    }
    .fee-link-danger {
      color: #b91c1c;
    }
    .fee-table-filters {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 8px;
      font-size: 13px;
      color: #6b7280;
      align-items: center;
    }
    .fee-table-filters select {
      width: auto;
      padding: 6px 10px;
      font-size: 13px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background:#fff;
    }
  </style>
</head>
<body>
  <!-- Put this inside your admin main content area -->
  <div class="fee-shell">

    <!-- CREATE / EDIT CARD -->
    <div class="fee-card">
      <div class="fee-card-header">
        <div>
          <h2><?php echo $editMode ? 'Edit Fee Structure' : 'Create Fee Structure'; ?></h2>
          <p>
            <?php if ($editMode): ?>
              Update fee components and details for the selected class and month.
            <?php else: ?>
              Define class-wise fee templates with breakdown components (tuition, library, lab, etc.).
            <?php endif; ?>
          </p>
        </div>
        <span class="fee-badge">Admin &raquo; Fees</span>
      </div>

      <?php if ($success): ?>
        <div class="fee-msg-success"><?php echo h($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="fee-msg-error"><?php echo h($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="create-fee.php" id="feeForm">
        <?php if ($editMode && $editStructure): ?>
          <input type="hidden" name="fee_structure_id" value="<?php echo (int)$editStructure['id']; ?>">
        <?php endif; ?>

        <div class="fee-form-row">
          <div class="fee-form-col">
            <label class="fee-label" for="class_name">Class</label>
            <?php
              $selectedClass = $editMode && $editStructure ? (int)$editStructure['class_name'] : 0;
            ?>
            <select name="class_name" id="class_name" class="fee-select" required>
              <option value="">Select Class</option>
              <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $selectedClass === $i ? 'selected' : ''; ?>>
                  Class <?php echo $i; ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="fee-form-col">
            <label class="fee-label" for="due_month">Due Month</label>
            <?php
              $dueMonthValue = '';
              if ($editMode && $editStructure) {
                  $dueMonthValue = date('Y-m', strtotime($editStructure['due_month']));
              }
            ?>
            <input type="month" name="due_month" id="due_month" class="fee-input" required value="<?php echo h($dueMonthValue); ?>">
          </div>
        </div>

        <h3 style="margin-top:16px; font-size:15px;">FEE COMPONENTS</h3>
        <p style="margin:2px 0 10px; font-size:12px; color:#6b7280;">
          Add fee parts such as Tuition Fee, Library Fee, Lab Fee, etc. Total is calculated automatically.
        </p>

        <table class="fee-components-table" id="componentsTable">
          <thead>
            <tr>
              <th style="width:55%;">Component Name</th>
              <th style="width:35%;">Amount (रू)</th>
              <th style="width:10%;"></th>
            </tr>
          </thead>
          <tbody>
          <?php
            $rowsToRender = [];

            if ($editMode && !empty($editComponents)) {
                foreach ($editComponents as $ec) {
                    $rowsToRender[] = [
                        'name'   => $ec['component_name'],
                        'amount' => $ec['amount'],
                    ];
                }
            } else {
                $rowsToRender = [
                    ['name' => 'Tuition Fee', 'amount' => ''],
                    ['name' => 'Library Fee', 'amount' => ''],
                    ['name' => 'Lab Fee', 'amount' => ''],
                ];
            }

            $idx = 0;
            foreach ($rowsToRender as $row):
          ?>
            <tr>
              <td>
                <input type="text"
                       name="components[<?php echo $idx; ?>][name]"
                       class="fee-input"
                       value="<?php echo h($row['name']); ?>">
              </td>
              <td>
                <input type="number"
                       name="components[<?php echo $idx; ?>][amount]"
                       min="0" step="0.01"
                       class="fee-input amount-input"
                       value="<?php echo h($row['amount']); ?>">
              </td>
              <td>
                <button type="button" class="fee-remove-row" title="Remove row">&times;</button>
              </td>
            </tr>
          <?php
              $idx++;
            endforeach;
          ?>
          </tbody>
        </table>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
          <button type="button" class="fee-btn-secondary" id="addRowBtn">
            + Add Component
          </button>
          <div class="fee-total-row">
            Total: <span id="totalAmount">रू 0.00</span>
          </div>
        </div>

        <div style="margin-top:20px;">
          <button type="submit" class="fee-btn-primary">
            <?php echo $editMode ? 'Update Fee Structure' : 'Save Fee Structure'; ?>
          </button>
          <?php if ($editMode): ?>
            <a href="create-fee.php" style="margin-left:12px; font-size:13px; color:#6b7280; text-decoration:none;">
              Cancel edit
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- EXISTING FEE STRUCTURES -->
    <div class="fee-card">
      <h3 style="margin-bottom:4px;">Existing Fee Structures</h3>
      <p style="margin:0 0 10px; font-size:12px; color:#6b7280;">
        Overview of all defined fee templates. Use filters to quickly find a class or academic year.
      </p>

      <div class="fee-table-filters">
        <span>Filter:</span>
        <select id="filterClass">
          <option value="">All Classes</option>
          <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="Class <?php echo $i; ?>">Class <?php echo $i; ?></option>
          <?php endfor; ?>
        </select>
        <?php
          $years = [];
          foreach ($feeRows as $r) {
              $years[$r['academic_year']] = true;
          }
          $years = array_keys($years);
          sort($years);
        ?>
        <select id="filterYear">
          <option value="">All Academic Years</option>
          <?php foreach ($years as $y): ?>
            <option value="<?php echo h($y); ?>"><?php echo h($y); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($feeRows)): ?>
        <div style="overflow-x:auto;">
          <table class="fee-list-table" id="feeTable">
            <thead>
              <tr>
                <th>Class</th>
                <th>Due Month</th>
                <th>Academic Year</th>
                <th>Total Amount (रू)</th>
                <th>Components</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($feeRows as $row): ?>
              <?php
                $classLabel = 'Class ' . (int)$row['class_name'];
                $ay         = $row['academic_year'];
                $isArchived = ($row['status'] === 'archived');
              ?>
              <tr data-class="<?php echo h($classLabel); ?>" data-year="<?php echo h($ay); ?>">
                <td><?php echo h($classLabel); ?></td>
                <td><?php echo date('M Y', strtotime($row['due_month'])); ?></td>
                <td><?php echo h($ay); ?></td>
                <td><?php echo number_format((float)$row['total_amount'], 2); ?></td>
                <td><?php echo h($row['components'] ?? ''); ?></td>
                <td>
                  <span class="fee-status-pill <?php echo $isArchived ? 'archived' : ''; ?>">
                    <?php echo h(ucfirst($row['status'])); ?>
                  </span>
                </td>
                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                <td>
                  <a class="fee-link"
                     href="create-fee.php?edit_id=<?php echo (int)$row['id']; ?>">
                    Edit
                  </a>
                  <a class="fee-link fee-link-danger"
                     href="create-fee.php?delete_id=<?php echo (int)$row['id']; ?>"
                     onclick="return confirm('Are you sure you want to delete this fee structure?');">
                    Delete
                  </a>
                  <?php if ($isArchived): ?>
                    <a class="fee-link"
                       href="create-fee.php?toggle_id=<?php echo (int)$row['id']; ?>&status=active">
                      Activate
                    </a>
                  <?php else: ?>
                    <a class="fee-link"
                       href="create-fee.php?toggle_id=<?php echo (int)$row['id']; ?>&status=archived">
                      Archive
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="font-size:13px; color:#6b7280; margin-top:6px;">No fee structures created yet.</p>
      <?php endif; ?>
    </div>

  </div><!-- /fee-shell -->

<script>
// Event-delegated JS so it works even when this is included inside another page
(function () {
  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }
  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  // ---------- TOTAL CALCULATION ----------
  function updateTotal() {
    var tableBody = qs('#componentsTable tbody');
    var totalSpan = qs('#totalAmount');
    if (!tableBody || !totalSpan) return;

    var total = 0;
    qsa('.amount-input', tableBody).forEach(function (input) {
      var val = parseFloat(input.value);
      if (!isNaN(val) && val >= 0) {
        total += val;
      }
    });

    totalSpan.textContent = 'रू ' + total.toFixed(2);
  }

  function renumberComponentNames() {
    var tableBody = qs('#componentsTable tbody');
    if (!tableBody) return;

    qsa('tr', tableBody).forEach(function (tr, idx) {
      var nameInput   = qs('input[name*="[name]"]', tr);
      var amountInput = qs('input[name*="[amount]"]', tr);
      if (nameInput) {
        nameInput.name = 'components[' + idx + '][name]';
      }
      if (amountInput) {
        amountInput.name = 'components[' + idx + '][amount]';
      }
    });
  }

  // ---------- CLICK EVENTS ----------
  document.addEventListener('click', function (e) {
    var tableBody = qs('#componentsTable tbody');

    // Add Component
    if (e.target.id === 'addRowBtn' || (e.target.closest && e.target.closest('#addRowBtn'))) {
      if (!tableBody) return;

      var rowCount = qsa('tr', tableBody).length;
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><input type="text" name="components[' + rowCount + '][name]" class="fee-input" placeholder="Other Fee"></td>' +
        '<td><input type="number" name="components[' + rowCount + '][amount]" min="0" step="0.01" class="fee-input amount-input"></td>' +
        '<td><button type="button" class="fee-remove-row" title="Remove row">&times;</button></td>';

      tableBody.appendChild(tr);
      renumberComponentNames();
      updateTotal();
      return;
    }

    // Remove row
    if (e.target.classList.contains('fee-remove-row')) {
      if (!tableBody) return;
      var rows = qsa('tr', tableBody);
      if (rows.length <= 1) return; // keep at least 1 row

      var trToRemove = e.target.closest('tr');
      if (trToRemove) {
        trToRemove.remove();
        renumberComponentNames();
        updateTotal();
      }
    }
  });

  // ---------- INPUT EVENTS (TOTAL) ----------
  document.addEventListener('input', function (e) {
    if (e.target.classList.contains('amount-input')) {
      updateTotal();
    }
  });

  // ---------- FILTERS ----------
  function applyFilters() {
    var table = qs('#feeTable');
    var classSelect = qs('#filterClass');
    var yearSelect  = qs('#filterYear');
    if (!table || !classSelect || !yearSelect) return;

    var classVal = classSelect.value;
    var yearVal  = yearSelect.value;

    qsa('tbody tr', table).forEach(function (tr) {
      var rowClass = tr.getAttribute('data-class') || '';
      var rowYear  = tr.getAttribute('data-year')  || '';
      var show = true;

      if (classVal && rowClass !== classVal) show = false;
      if (yearVal && rowYear !== yearVal)    show = false;

      tr.style.display = show ? '' : 'none';
    });
  }

  document.addEventListener('change', function (e) {
    if (e.target.id === 'filterClass' || e.target.id === 'filterYear') {
      applyFilters();
    }
  });

  // ---------- INITIALISE ----------
  updateTotal();
  renumberComponentNames();
  applyFilters();
})();
</script>
</body>
</html>