<?php
// Expect $user_name to be set before including this file
$user_name = $user_name ?? 'User';
?>

<header class="header">
  <div>
    <h2><?= htmlspecialchars($user_name) ?> Dashboard</h2>
    <p>Welcome, <?= htmlspecialchars($user_name) ?>!</p>
  </div>
  <div class="actions">
    <div class="notification">
      <i class="fas fa-bell" id="notificationBell"></i>
      <div class="notification-dropdown" id="notificationDropdown">
        <p><strong>Notifications</strong></p>
        <ul>
          <li>New assignment submissions.</li>
          <li>Upcoming meetings scheduled.</li>
        </ul>
      </div>
    </div>
  </div>
</header>

<script>
  // Notification toggle
  const bell = document.getElementById('notificationBell');
  const dropdown = document.getElementById('notificationDropdown');
  bell.addEventListener('click', () => {
    dropdown.classList.toggle('show');
  });
</script>
