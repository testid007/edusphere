<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

// Example messages array (replace with DB query)
$messages = [
    [
        'parent_name' => 'Sita Sharma',
        'date' => '2025-07-01',
        'subject' => 'Request for parent-teacher meeting',
        'content' => 'Could we schedule a meeting regarding my child\'s performance?',
        'status' => 'Unread',
    ],
    [
        'parent_name' => 'Ram Thapa',
        'date' => '2025-06-28',
        'subject' => 'Homework query',
        'content' => 'My child has a question about the recent math homework.',
        'status' => 'Read',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Communication - Parent Messages</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/teacher-dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/communication.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>

      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="communication.php" class="active"><i class="fas fa-comments"></i> Communication</a>
        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Teacher" />
        <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
        <div class="email"><?= htmlspecialchars($teacher_email) ?></div>
      </div>
    </aside>

    <main class="main">
      <header class="header">
        <h2>Parent Communications</h2>
        <p>Messages from parents will appear here.</p>
      </header>

      <section class="messages-container">
        <?php if (count($messages) === 0): ?>
          <p>No messages found.</p>
        <?php else: ?>
          <?php foreach ($messages as $msg): ?>
            <div class="message <?= strtolower($msg['status']) === 'unread' ? 'unread' : '' ?>">
              <div class="message-header">
                <div><strong>From:</strong> <?= htmlspecialchars($msg['parent_name']) ?></div>
                <div><strong>Date:</strong> <?= htmlspecialchars($msg['date']) ?></div>
              </div>
              <div class="message-subject"><?= htmlspecialchars($msg['subject']) ?></div>
              <div class="message-content"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
