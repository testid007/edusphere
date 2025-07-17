<?php
// sidebar_teacher.php
// Sidebar component for Teacher Dashboard
?>
<aside class="sidebar">
  <!-- Logo Section -->
  <div class="logo">
    <img src="../../assets/img/logo.png" alt="Logo" width="30" />
  </div>

  <!-- Navigation Links -->
  <nav class="nav">
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
    <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
    <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
    <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </nav>

  <!-- Profile Section included inside Sidebar -->
  <?php include __DIR__ . '/profile.php'; ?>
</aside>
