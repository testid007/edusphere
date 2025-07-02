<?php
session_start();
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']); // Clear the error after retrieving it
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EduSphere | Login</title>
  <link rel="stylesheet" href="../assets/css/login.css" />
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <h2>Login to EduSphere</h2>
      </div>

      <?php if ($login_error): ?>
        <div class="error-message" style="color: red; margin-bottom: 1rem; font-weight: bold;">
          <?= htmlspecialchars($login_error) ?>
        </div>
      <?php endif; ?>

      <form action="login_process.php" method="POST">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required />
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required />
        </div>

        <div class="form-group">
          <label>Role</label>
          <select name="role" required>
            <option value="Student">Student</option>
            <option value="Teacher">Teacher</option>
            <option value="Admin">Admin</option>
            <option value="Parent">Parent</option>
          </select>
        </div>

        <button type="submit">Login</button>
      </form>

      <p class="switch">
        Don't have an account? <a href="register.php">Register</a>
      </p>
    </div>
  </div>
</body>
</html>
