
<?php
// Optional: Show success/error messages after redirect from save-fee.php
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<section class="section create-fee">
  <h3 style="margin-bottom: 20px;">Create Fee Structure</h3>
  <?php if ($success): ?>
    <div style="background:#e8f5e9;color:#388e3c;padding:10px 16px;border-radius:8px;margin-bottom:12px;">
      <?php echo htmlspecialchars($success); ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div style="background:#ffebee;color:#c62828;padding:10px 16px;border-radius:8px;margin-bottom:12px;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>
  <form method="POST" action="save-fee.php" style="display: flex; flex-direction: column; gap: 15px; max-width: 400px;">
    <label>
      Class:
      <select name="class" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
        <option value="">Select Class</option>
        <option value="Nursery">Nursery</option>
        <option value="KG">KG</option>
        <option value="1">Class 1</option>
        <option value="2">Class 2</option>
        <option value="3">Class 3</option>
        <option value="4">Class 4</option>
        <option value="5">Class 5</option>
        <option value="6">Class 6</option>
        <option value="7">Class 7</option>
        <option value="8">Class 8</option>
        <option value="9">Class 9</option>
        <option value="10">Class 10</option>
        <option value="11">Class 11</option>
        <option value="12">Class 12</option>
      </select>
    </label>
    <label>
      Fee Amount:
      <input type="number" name="fee_amount" required min="0" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    </label>
    <label>
      Due Month:
      <input type="month" name="due_month" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    </label>
    <button type="submit" style="padding: 10px 20px; background-color: #111; color: white; border: none; border-radius: 8px; cursor: pointer;">Create Fee</button>
  </form>
</section>