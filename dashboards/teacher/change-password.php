<?php
session_start();
$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}
require_once '../../includes/db.php';

$userId = (int)$_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $message = 'Error: New passwords do not match.';
    } else {
        // TODO: adjust password hashing / column to your auth logic
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($current, $row['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$hash, $userId]);
            $message = 'Password updated successfully.';
        } else {
            $message = 'Error: Current password is incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password | EduSphere</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5eee9;}
    .app-shell{width:100%;display:grid;grid-template-columns:250px 1fr;min-height:100vh;background:#fdfcfb;}
    .sidebar{background:#fdf5ec;border-right:1px solid #f3e5d7;padding:24px 20px;display:flex;flex-direction:column;justify-content:space-between;}
    .logo{display:flex;align-items:center;gap:10px;margin-bottom:24px;}
    .logo img{height:36px;}
    .logo span{font-weight:700;font-size:1.05rem;color:#1f2937;letter-spacing:.04em;}
    .nav{display:flex;flex-direction:column;gap:6px;}
    .nav a{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:999px;color:#6b7280;font-size:.9rem;text-decoration:none;}
    .nav a i{width:18px;text-align:center;color:#9ca3af;}
    .nav a.active{background:#fff5e5;color:#92400e;font-weight:600;}
    .nav a.logout{margin-top:8px;color:#b91c1c;}
    .main{padding:22px;background:radial-gradient(circle at top left,#fff8ef 0,#ffffff 55%);}
    .header h2{margin:0 0 18px;font-size:1.4rem;}
    .card{background:#fff;border-radius:18px;padding:18px 20px;box-shadow:0 12px 30px rgba(15,23,42,.06);max-width:480px;}
    .field{margin-bottom:12px;}
    .field label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:#4b5563;}
    .field input[type="password"]{width:100%;padding:8px 10px;border-radius:10px;border:1px solid #e5e7eb;font-size:.9rem;}
    .btn-primary{border:none;border-radius:999px;background:#f59e0b;color:#fff;padding:8px 18px;font-size:.9rem;font-weight:600;cursor:pointer;box-shadow:0 10px 25px rgba(180,83,9,.45);}
    .message{margin-bottom:10px;font-size:.85rem;color:#16a34a;}
    .message.error{color:#b91c1c;}
  </style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo">
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="change-password.php" class="active"><i class="fas fa-key"></i> Change Password</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
  </aside>

  <main class="main">
    <div class="header">
      <h2>Change Password</h2>
    </div>
    <div class="card">
      <?php if ($message): ?>
        <div class="message<?= strpos($message,'Error') === 0 ? ' error' : '' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="field">
          <label>Current Password</label>
          <input type="password" name="current_password" required>
        </div>
        <div class="field">
          <label>New Password</label>
          <input type="password" name="new_password" required>
        </div>
        <div class="field">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn-primary">Update Password</button>
      </form>
    </div>
  </main>
</div>
</body>
</html>
