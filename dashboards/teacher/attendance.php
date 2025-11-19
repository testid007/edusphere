<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

$student_id    = (int)$_SESSION['user_id'];
$student_name  = $_SESSION['student_name']  ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';

require_once '../../includes/db.php';

// --------------------------------------------------
// Determine student's class
// --------------------------------------------------
$student_class = $_SESSION['class'] ?? null;
if (!$student_class) {
    try {
        // TODO: adjust students.class_name if different
        $stmt = $conn->prepare("SELECT class_name FROM students WHERE user_id = ?");
        $stmt->execute([$student_id]);
        $student_class = $stmt->fetchColumn() ?: 'Unknown';
    } catch (Exception $e) {
        $student_class = 'Unknown';
    }
}

// --------------------------------------------------
// Fetch assignments for this class
// --------------------------------------------------
$assignments = [];
try {
    $stmt = $conn->prepare("
        SELECT id, title, due_date, status, class_name, file_url
        FROM assignments
        WHERE class_name = ?
        ORDER BY (due_date IS NULL), due_date ASC, id ASC
    ");
    $stmt->execute([$student_class]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function timeLeftForAssignment(?string $dueDate): string
{
    if (empty($dueDate)) return 'No due date';

    try {
        $due = new DateTime($dueDate . ' 23:59:59');
        $now = new DateTime();

        if ($due < $now) {
            return 'Overdue';
        }

        $diff = $now->diff($due);
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' left';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' left';
        }
        return 'Due today';
    } catch (Exception $e) {
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Assignments</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .assignment-card {
      background:#fff;
      border-radius:10px;
      padding:12px 16px;
      margin-bottom:10px;
      box-shadow:0 1px 4px rgba(0,0,0,0.06);
      display:flex;
      flex-direction:column;
      gap:4px;
    }
    .assignment-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
    }
    .assignment-title {
      font-weight:600;
      font-size:1rem;
    }
    .assignment-meta {
      font-size:0.85rem;
      color:#555;
    }
    .badge {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:0.75rem;
      font-weight:600;
      text-transform:uppercase;
      letter-spacing:0.03em;
    }
    .badge-open  { background:#e8f5e9; color:#2e7d32; }
    .badge-closed{ background:#ffebee; color:#c62828; }
    .time-left {
      font-weight:600;
      font-size:0.85rem;
    }
    .time-left.overdue { color:#c62828; }
    .time-left.todays { color:#f9a825; }
    .assignment-actions {
      margin-top:4px;
      font-size:0.85rem;
    }
    .assignment-actions a {
      color:#1565c0;
      text-decoration:none;
      font-weight:600;
    }
    .assignment-actions a:hover {
      text-decoration:underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>

      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="assignments.php" class="active"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="email"><?= htmlspecialchars($student_email) ?></div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <header class="header">
        <h2>My Assignments</h2>
        <p>Class: <?= htmlspecialchars($student_class) ?></p>
      </header>

      <section class="content">
        <?php if (empty($assignments)): ?>
          <p>No assignments have been posted for your class yet.</p>
        <?php else: ?>
          <?php foreach ($assignments as $a): ?>
            <?php
              $timeLeft = timeLeftForAssignment($a['due_date']);
              $timeClass = '';
              if ($timeLeft === 'Overdue')      $timeClass = 'overdue';
              elseif ($timeLeft === 'Due today')$timeClass = 'todays';
            ?>
            <div class="assignment-card">
              <div class="assignment-header">
                <div>
                  <div class="assignment-title"><?= htmlspecialchars($a['title']) ?></div>
                  <div class="assignment-meta">
                    Due date:
                    <?= $a['due_date'] ? htmlspecialchars($a['due_date']) : 'Not set' ?>
                  </div>
                </div>
                <div>
                  <span class="badge badge-<?= strtolower($a['status']) ?>">
                    <?= htmlspecialchars($a['status']) ?>
                  </span>
                </div>
              </div>
              <div class="assignment-meta">
                <span class="time-left <?= $timeClass ?>">
                  <?= htmlspecialchars($timeLeft) ?>
                </span>
              </div>
              <?php if (!empty($a['file_url'])): ?>
                <div class="assignment-actions">
                  <a href="<?= htmlspecialchars($a['file_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-download"></i> Download Assignment File
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
