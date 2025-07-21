<?php
// Start session if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info from session or set defaults
$user_name = $_SESSION['teacher_name'] ?? 'Teacher';
$user_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';
?>

<!-- Profile section shown inside sidebar -->
<div class="profile">
  <!-- Profile picture -->
  <img src="../../assets/img/user.jpg" alt="User Profile Picture" />

  <!-- User name -->
  <div class="name"><?= htmlspecialchars($user_name) ?></div>

  <!-- User email -->
  <div class="email"><?= htmlspecialchars($user_email) ?></div>

  <!-- Profile actions (settings, logout) -->
  <div class="profile-actions">
    <div class="dropdown">
      <i class="fas fa-cog" id="settingsToggle"></i>
      <div class="settings-dropdown" id="settingsMenu">
        <label>
          <input type="checkbox" id="darkModeToggle" />
          Dark Mode
        </label>
        <label>
          Language:
          <select id="languageSelect">
            <option value="en">English</option>
            <option value="np">Nepali</option>
          </select>
        </label>
      </div>
    </div>

    <!-- Logout icon -->
    <a href="../auth/logout.php" class="logout-icon" title="Logout">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</div>
