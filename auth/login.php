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
  <style>
    .password-container {
      position: relative;
    }
    
    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
      font-size: 14px;
      padding: 0;
    }
    
    .toggle-password:hover {
      color: #333;
    }
  </style>
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
          <div class="password-container">
            <input type="password" name="password" id="password" required />
            <button type="button" class="toggle-password" onclick="togglePassword()">
              <span id="toggle-text">Show</span>
            </button>
          </div>
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

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const toggleText = document.getElementById('toggle-text');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleText.textContent = 'Hide';
      } else {
        passwordInput.type = 'password';
        toggleText.textContent = 'Show';
      }
    }
  </script>
</body>
</html>