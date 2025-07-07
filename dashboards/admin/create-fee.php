<section class="section">
  <h3>Create Fee</h3>
  <form method="POST" action="save-fee.php">
    <label>
      Student ID:
      <input type="text" name="student_id" required>
    </label>
    <label>
      Fee Amount:
      <input type="number" name="fee_amount" required>
    </label>
    <label>
      Due Month:
      <input type="month" name="due_month" required>
    </label>
    <button type="submit">Submit</button>
  </form>
</section>
