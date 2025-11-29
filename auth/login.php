<?php
session_start();
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EduSphere | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="login-page">
  <div class="login-shell">
    <!-- HERO / TOP SECTION -->
    <section class="login-hero">
      <div class="hero-left">
        <div class="hero-header-row">
          <div class="brand-pill">
            <!-- Logo image in pill -->
            <img
              src="../assets/img/logo.png"
              alt="EduSphere logo"
              class="brand-logo-img"
              onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <!-- Fallback if logo not found -->
            <div class="brand-logo-fallback" style="display:none;">ES</div>
          </div>

          <div class="hero-title-block">
            <h1>Welcome back</h1>
            <p>Sign in to your EduSphere dashboard and pick up where you left off.</p>
          </div>
        </div>

        <!-- Recent logins row (static demo, you can make dynamic later) -->
        <div class="recent-logins">
          <span class="recent-logins-label">Recent logins</span>
          <div class="recent-logins-row">
            <button type="button" class="recent-account">
              <span class="recent-avatar">P</span>
              <span class="recent-meta">
                <span class="recent-name">testparent one</span>
                <span class="recent-role">Parent</span>
              </span>
            </button>

            <button type="button" class="recent-account">
              <span class="recent-avatar">T</span>
              <span class="recent-meta">
                <span class="recent-name">testteacher one</span>
                <span class="recent-role">Teacher</span>
              </span>
            </button>

            <button type="button" class="recent-account">
              <span class="recent-avatar">S</span>
              <span class="recent-meta">
                <span class="recent-name">teststudent two</span>
                <span class="recent-role">Student</span>
              </span>
            </button>

            <button type="button" class="recent-account add-account">
              <span class="recent-avatar">+</span>
              <span class="recent-meta">
                <span class="recent-name">Add account</span>
                <span class="recent-role">Create new</span>
              </span>
            </button>
          </div>
        </div>

        <!-- Sign up & tips card (kept compact under welcome) -->
        <div class="signup-tips-card">
          <div class="signup-tips-title">Sign up &amp; tips</div>
          <div class="signup-tips-heading">
            Create a new EduSphere account
          </div>
          <ul class="signup-tips-list">
            <li>
              <span class="signup-dot"></span>
              <span>One login for Student, Teacher, Admin &amp; Parent.</span>
            </li>
            <li>
              <span class="signup-dot"></span>
              <span>Never share your password with anyone.</span>
            </li>
            <li>
              <span class="signup-dot"></span>
              <span>Switch roles later from your profile if needed.</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- we keep hero-right empty so layout stays nice & compact -->
      <div class="hero-right"></div>
    </section>

    <!-- Divider -->
    <div class="login-divider">
      <div class="login-divider-line"></div>
      <span>Sign into EduSphere</span>
      <div class="login-divider-line"></div>
    </div>

    <!-- MAIN LOGIN CARD -->
    <section class="login-main-card">
      <!-- PHP error message -->
      <?php if ($login_error): ?>
        <div class="error-message">
          <?= htmlspecialchars($login_error) ?>
        </div>
      <?php endif; ?>

      <!-- badges row -->
      <div class="login-badges">
        <span class="badge-pill">
          <span class="icon">🔒</span> Secure login
        </span>
        <span class="badge-pill">
          <span class="icon">👥</span> Multi-role access
        </span>
        <span class="badge-pill">
          <span class="icon">⏰</span> 24×7 portal
        </span>
      </div>

      <form action="login_process.php" method="POST" id="loginForm">
        <div class="login-form-grid">
          <!-- Email -->
          <div class="form-field">
            <label for="email">Email</label>
            <div class="input-wrapper">
              <input
                type="email"
                id="email"
                name="email"
                class="login-input"
                placeholder="you@example.com"
                required
              >
            </div>
          </div>

          <!-- Password -->
          <div class="form-field">
            <label for="password">Password</label>
            <div class="input-wrapper">
              <input
                type="password"
                id="password"
                name="password"
                class="login-input"
                placeholder="Enter your password"
                required
              >
              <button type="button" class="password-toggle" id="togglePassword">
                Show
              </button>
            </div>
            <div class="login-subtext" id="capsWarning" style="display:none;">
              Caps Lock is ON
            </div>
          </div>

          <!-- Role -->
          <div class="form-field">
            <label for="role">Role</label>
            <div class="input-wrapper">
              <select id="role" name="role" class="login-select" required>
                <option value="Student">Student</option>
                <option value="Teacher">Teacher</option>
                <option value="Admin">Admin</option>
                <option value="Parent">Parent</option>
              </select>
              <span class="role-icon" id="roleIcon">🎓</span>
            </div>
            <div class="login-subtext">
              Choose how you want to access EduSphere.
            </div>
          </div>
        </div>

        <!-- Remember / Forgot -->
        <div class="login-meta-row">
          <div class="login-meta-left">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Remember for 30 days</label>
          </div>
          <div class="login-meta-right">
            <a href="request_password_reset.php">Forgot password?</a>
          </div>
        </div>

        <!-- Buttons -->
        <div class="login-actions">
          <button type="submit" class="btn-primary">
            <span>Sign in</span>
          </button>

          <button
            type="button"
            class="btn-secondary"
            onclick="window.location.href='register.php';"
          >
            Create new account
          </button>
        </div>
      </form>

      <p class="login-footer-text">
        By signing in, you agree to EduSphere’s
        <a href="#">Terms of Use</a> and
        <a href="#">Privacy Policy</a>.
      </p>
    </section>
  </div>
</div>

<script>
// password show/hide
document.getElementById('togglePassword').addEventListener('click', function () {
  const pwd = document.getElementById('password');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    this.textContent = 'Hide';
  } else {
    pwd.type = 'password';
    this.textContent = 'Show';
  }
});

// caps lock detection
const passwordInput = document.getElementById('password');
const capsWarning = document.getElementById('capsWarning');

passwordInput.addEventListener('keyup', function (e) {
  if (e.getModifierState && e.getModifierState('CapsLock')) {
    capsWarning.style.display = 'block';
  } else {
    capsWarning.style.display = 'none';
  }
});

// change role icon based on selection
const roleSelect = document.getElementById('role');
const roleIcon = document.getElementById('roleIcon');

function updateRoleIcon() {
  switch (roleSelect.value) {
    case 'Student':
      roleIcon.textContent = '🎓';
      break;
    case 'Teacher':
      roleIcon.textContent = '📘';
      break;
    case 'Admin':
      roleIcon.textContent = '🛡️';
      break;
    case 'Parent':
      roleIcon.textContent = '👨‍👩‍👧';
      break;
    default:
      roleIcon.textContent = '👤';
  }
}
roleSelect.addEventListener('change', updateRoleIcon);
updateRoleIcon();
</script>
</body>
</html>
