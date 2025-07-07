<section class="section create-fee">
  <h3 style="margin-bottom: 20px;">Create Fee</h3>
  <form method="POST" action="save-fee.php" style="display: flex; flex-direction: column; gap: 15px; max-width: 400px;">
    <label>
      Student ID:
      <input type="text" name="student_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    </label>
    <label>
      Fee Amount:
      <input type="number" name="fee_amount" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    </label>
    <label>
      Due Month:
      <input type="month" name="due_month" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    </label>
    <button type="submit" style="padding: 10px 20px; background-color: #111; color: white; border: none; border-radius: 8px; cursor: pointer;">Submit</button>
  </form>
</section>
