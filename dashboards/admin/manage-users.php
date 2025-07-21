
<section class="section manage-users">
  <h3 style="margin-bottom: 20px;">Manage Users</h3>
  <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse;">
      <thead style="background-color: #111; color: white;">
        <tr>
          <th style="padding: 12px;">ID</th>
          <th style="padding: 12px;">Name</th>
          <th style="padding: 12px;">Email</th>
          <th style="padding: 12px;">Role</th>
          <th style="padding: 12px;">Status</th>
          <th style="padding: 12px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr style="background-color: #fff; border-bottom: 1px solid #eee;">
          <td style="padding: 12px;">1001</td>
          <td style="padding: 12px;">Aayush Shrestha</td>
          <td style="padding: 12px;">aayush@school.com</td>
          <td style="padding: 12px;">Student</td>
          <td style="padding: 12px;">Active</td>
          <td style="padding: 12px;">
            <form method="POST" action="">
              <input type="hidden" name="delete_user_id" value="1001">
              <button type="submit">Delete</button>
            </form>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<?php
session_start();
require_once '../../includes/db.php'; // Adjust path if necessary

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteUserId = (int)$_POST['delete_user_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$deleteUserId]);
}

// Fetch all users
$stmt = $conn->prepare("SELECT * FROM users");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        header {
            background-color:none;
            padding: 15px 30px;
            color: #fff;
        }

        h1 {
            margin: 0;
        }

        main {
            padding: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        th, td {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        button {
            padding: 6px 12px;
            background-color: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background-color: #c82333;
        }

        form {
            display: inline;
        }
    </style>
</head>
<body>

<header>
    <h1>Admin Dashboard - Manage Users</h1>
</header>

<main>
    <h2>All Registered Users</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Gender</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= htmlspecialchars($user['gender']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

</body>
</html>

