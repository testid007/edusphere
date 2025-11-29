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
    $first  = trim($_POST['first_name'] ?? '');
    $last   = trim($_POST['last_name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $avatarPath = null;

    // avatar upload (optional)
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $newName = 'teacher_' . $userId . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $newName;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatarPath = 'uploads/avatars/' . $newName;
        }
    }

    try {
        // TODO: adjust column names if different in your `users` table
        if ($avatarPath) {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, avatar_path=? WHERE id=?");
            $stmt->execute([$first, $last, $email, $phone, $avatarPath, $userId]);
            $_SESSION['teacher_avatar'] = $avatarPath;
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
            $stmt->execute([$first, $last, $email, $phone, $userId]);
        }

        $_SESSION['teacher_name']  = trim($first . ' ' . $last);
        $_SESSION['teacher_email'] = $email;
        $message = 'Profile updated successfully.';
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
    }
}

// load current data
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, avatar_path FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$first  = $user['first_name'] ?? '';
$last   = $user['last_name'] ?? '';
$email  = $user['email'] ?? $teacher_email;
$phone  = $user['phone'] ?? '';
$avatar = $user['avatar_path'] ?? ($_SESSION['teacher_avatar'] ?? '../../assets/img/user.jpg');
$teacher_name = $_SESSION['teacher_name'] ?? trim($first . ' ' . $last);
$teacher_email = $_SESSION['teacher_email'] ?? $email;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Profile | EduSphere</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- reuse same styles as dashboard: simplest is to include dashboard.php CSS via dashboard.css or copy -->
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
    .sidebar-teacher-card{margin-top:20px;padding:12px 14px;border-radius:18px;background:linear-gradient(135deg,#ffe9cf,#fff7ea);display:flex;align-items:center;gap:10px;}
    .sidebar-teacher-card img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #fff;}
    .main{padding:22px;background:radial-gradient(circle at top left,#fff8ef 0,#ffffff 55%);}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
    .header h2{margin:0;font-size:1.4rem;}
    .card{background:#fff;border-radius:18px;padding:18px 20px;box-shadow:0 12px 30px rgba(15,23,42,.06);max-width:640px;}
    .card h3{margin-top:0;margin-bottom:12px;}
    .field{margin-bottom:12px;}
    .field label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:#4b5563;}
    .field input[type="text"],
    .field input[type="email"],
    .field input[type="tel"]{width:100%;padding:8px 10px;border-radius:10px;border:1px solid #e5e7eb;font-size:.9rem;}
    .field input[type="file"]{font-size:.85rem;}
    .profile-top{display:flex;align-items:center;gap:16px;margin-bottom:16px;}
    .profile-top img{width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 8px 20px rgba(148,119,73,.3);}
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
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="sidebar-teacher-card">
        <img src="<?= htmlspecialchars($avatar) ?>" alt="Teacher">
        <div>
          <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
          <div class="role">Teacher · EduSphere</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="header">
      <h2>Teacher Profile</h2>
    </div>

    <div class="card">
      <h3>Personal Details</h3>

      <?php if ($message): ?>
        <div class="message<?= strpos($message,'Error') === 0 ? ' error' : '' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="profile-top">
          <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar">
          <div>
            <div style="font-weight:600;"><?= htmlspecialchars($teacher_name) ?></div>
            <div style="font-size:.82rem;color:#6b7280;"><?= htmlspecialchars($teacher_email) ?></div>
          </div>
        </div>

        <div class="field">
          <label>First Name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($first) ?>" required>
        </div>

        <div class="field">
          <label>Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($last) ?>" required>
        </div>

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="field">
          <label>Phone</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="98XXXXXXXX">
        </div>

        <div class="field">
          <label>Profile Image</label>
          <input type="file" name="avatar" accept="image/*">
        </div>

        <button type="submit" class="btn-primary">Save Changes</button>
      </form>
    </div>
  </main>
</div>
</body>
</html>
