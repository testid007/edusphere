<?php
session_start();
require_once '../../includes/db.php';

// ---------- Access control ----------
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }
    echo '<p>You are not authorized to view this page.</p>';
    exit;
}

$adminId = (int)$_SESSION['user_id'];

// ---------- CSRF token ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ---------- Handle AJAX POST (delete user) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    if ($action !== 'delete') {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }

    // Don't let admin delete self
    if ($userId === $adminId) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
        exit;
    }

    try {
        // Check role of the user to be deleted
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $roleToDelete = strtolower($user['role']);

        // If deleting an admin, make sure there is more than one admin
        if ($roleToDelete === 'admin') {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = (int)$stmt->fetchColumn();

            if ($adminCount <= 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot delete the last admin account.'
                ]);
                exit;
            }
        }

        // Do the delete
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } catch (Exception $ex) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $ex->getMessage()]);
    }
    exit;
}

// ---------- GET: fetch all users for table ----------
try {
    $stmt = $conn->query("
        SELECT id, first_name, last_name, email, phone, role, gender
        FROM users
        ORDER BY id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $users = [];
    $loadError = $ex->getMessage();
}

// small helper
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>

<section class="manage-users">
  <div class="panel">
    <div class="panel-header">
      <h4>Manage Users</h4>
      <span><?php echo count($users); ?> registered account(s)</span>
    </div>

    <p style="margin-bottom: 14px; color: var(--text-muted); font-size:0.92rem;">
      View all registered users and remove accounts when necessary. Later you can
      add filters by role (Student / Teacher / Parent / Admin) or export options.
    </p>

    <div id="manageUsersAlert" style="display:none; margin-bottom:12px;"></div>

    <div class="table-container" style="margin:0; padding:0; border-radius:0; box-shadow:none;">
      <table class="gradebook-table manage-users-table">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th style="width:130px;">Phone</th>
            <th style="width:110px;">Role</th>
            <th style="width:80px;">Gender</th>
            <th style="width:140px; text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr data-user-id="<?php echo (int)$u['id']; ?>">
                <td data-label="ID"><?php echo (int)$u['id']; ?></td>
                <td data-label="Full Name">
                  <?php echo e(trim($u['first_name'] . ' ' . $u['last_name'])); ?>
                </td>
                <td data-label="Email"><?php echo e($u['email']); ?></td>
                <td data-label="Phone"><?php echo e($u['phone']); ?></td>
                <td data-label="Role">
                  <span class="role-badge role-<?php echo strtolower(e($u['role'])); ?>">
                    <?php echo e(ucfirst($u['role'])); ?>
                  </span>
                </td>
                <td data-label="Gender"><?php echo e($u['gender']); ?></td>
                <td data-label="Actions" style="text-align:center;">
                  <button
                    type="button"
                    class="btn-delete-user"
                    data-user-id="<?php echo (int)$u['id']; ?>"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:18px; color:var(--text-muted);">
                No users found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<style>
  /* Manage Users table – aligned with amber dashboard theme */

  .manage-users .panel {
    /* panel base comes from dashboard.css; no changes here */
  }

  .manage-users-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
    font-size: 0.95rem;
  }

  /* We reuse gradebook-table header style from dashboard.css,
     so only small tweaks if needed */
  .manage-users-table thead tr {
    /* use existing amber gradient; no override */
  }

  .manage-users-table tbody tr {
    background: #f9fafb;
  }

  .manage-users-table tbody tr:hover {
    background-color: #fff7eb;
  }

  .manage-users-table tbody td {
    border: none;
  }

  /* Delete button */
  .btn-delete-user {
    padding: 6px 14px;
    font-size: 0.82rem;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    background: #ef4444;
    color: #fff;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
    font-weight: 600;
    letter-spacing: 0.02em;
    transition: background 0.18s, box-shadow 0.18s, transform 0.12s;
  }
  .btn-delete-user:hover {
    background: #dc2626;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.35);
    transform: translateY(-1px);
  }

  /* Role badges with warm colors */
  .role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #e5f6ff;
    color: #1d4ed8;
  }
  .role-badge.role-student {
    background: #dcfce7;
    color: #15803d;
  }
  .role-badge.role-teacher {
    background: #e0f2fe;
    color: #1d4ed8;
  }
  .role-badge.role-parent {
    background: #fef3c7;
    color: #92400e;
  }
  .role-badge.role-admin {
    background: #fee2e2;
    color: #b91c1c;
  }

  /* Alert box styles */
  .manage-users-alert-success {
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    color: #166534;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
  }
  .manage-users-alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
  }

  @media (max-width: 768px) {
    .manage-users-table {
      border-spacing: 0 6px;
      font-size: 0.88rem;
    }
  }
</style>

<script>
  (function() {
    const csrfToken = '<?php echo $csrf; ?>';
    const alertBox = document.getElementById('manageUsersAlert');

    function showAlert(message, type) {
      if (!alertBox) return;
      alertBox.style.display = 'block';
      alertBox.className = ''; // reset
      if (type === 'success') {
        alertBox.classList.add('manage-users-alert-success');
      } else {
        alertBox.classList.add('manage-users-alert-error');
      }
      alertBox.textContent = message;
    }

    document.querySelectorAll('.btn-delete-user').forEach(btn => {
      btn.addEventListener('click', function() {
        const userId = this.dataset.userId;
        if (!userId) return;

        if (!confirm('Are you sure you want to delete this user?')) {
          return;
        }

        fetch('manage-users.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'delete',
            user_id: userId,
            csrf_token: csrfToken
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showAlert(data.message || 'User deleted.', 'success');

            // Remove row from table
            const row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (row && row.parentNode) {
              row.parentNode.removeChild(row);
            }
          } else {
            showAlert(data.message || 'Delete failed.', 'error');
          }
        })
        .catch(err => {
          console.error(err);
          showAlert('An error occurred while deleting user.', 'error');
        });
      });
    });
  })();
</script>
