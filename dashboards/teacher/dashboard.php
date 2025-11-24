<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_id   = (int)$_SESSION['user_id'];
$teacher_name = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

// ---------- DASHBOARD STATS ----------
// These are global for now; you can later add teacher_id column to filter per teacher

$stats = [
    'total_assignments' => 0,
    'open_assignments'  => 0,
    'total_grades'      => 0,
    'avg_score'         => null,
    'total_students'    => 0,
    'attendance_avg'    => null,
];

try {
    // assignments table: id, title, due_date, status, class_name, file_url
    $stmt = $conn->query("SELECT 
        COUNT(*) AS total_assignments,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_assignments
        FROM assignments
    ");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total_assignments'] = (int)$row['total_assignments'];
        $stats['open_assignments']  = (int)$row['open_assignments'];
    }
} catch (Exception $e) {}

try {
    // grades table: student_id, category, title, score, grade, comments, date_added
    // If score is stored like "45/50", avg_score will be null; you can later switch to numeric
    $stmt = $conn->query("SELECT COUNT(*) AS total_grades FROM grades");
    $stats['total_grades'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    // How many distinct students exist
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT s.user_id)
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
    ");
    $stats['total_students'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    // Simple attendance % across all students
    $stmt = $conn->query("
        SELECT 
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
          COUNT(*) AS total_records
        FROM attendance
    ");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['total_records'] > 0) {
            $stats['attendance_avg'] = round(
                ($row['present_days'] / $row['total_records']) * 100,
                1
            );
        }
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .cards {
      display: flex;
      flex-wrap: wrap;
      gap: 24px;
      margin-top: 32px;
      justify-content: flex-start;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(76,175,80,0.10);
      padding: 20px 24px;
      min-width: 220px;
      max-width: 260px;
      flex: 1 1 220px;
      border: 2px solid #4caf50;
      transition: box-shadow 0.2s, border-color 0.2s, background 0.2s;
    }
    .card:hover {
      box-shadow: 0 4px 16px rgba(76,175,80,0.18);
      border-color: #388e3c;
      background: #f6fff6;
    }
    .card h3 {
      margin: 0 0 6px;
      font-size: 1rem;
      color: #388e3c;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-weight: 700;
    }
    .card .big-number {
      font-size: 1.9rem;
      font-weight: 800;
      color: #111;
      margin-bottom: 4px;
    }
    .card .sub-text {
      font-size: 0.9rem;
      color: #666;
    }
    .profile-line {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      margin-bottom:8px;
    }
    .profile-line .name {
      font-weight: 700;
      color:#111;
    }
    @media (max-width: 900px) {
      .cards {
        flex-direction: column;
        gap: 16px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
      </div>
      <nav class="nav">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Teacher" />
        <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
        <div class="email"><?= htmlspecialchars($teacher_email) ?></div>
      </div>
    </aside>

    <!-- Main -->
    <main class="main">
      <header class="header">
        <h2>Teacher Dashboard</h2>
        <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
      </header>

      <section class="cards">
        <div class="card">
          <h3>Assignments</h3>
          <div class="big-number"><?= $stats['total_assignments'] ?></div>
          <div class="sub-text">
            Total created<br>
            Open: <strong><?= $stats['open_assignments'] ?></strong>
          </div>
        </div>

        <div class="card">
          <h3>Grades Recorded</h3>
          <div class="big-number"><?= $stats['total_grades'] ?></div>
          <div class="sub-text">Total grade entries in grade book</div>
        </div>

        <div class="card">
          <h3>Students</h3>
          <div class="big-number"><?= $stats['total_students'] ?></div>
          <div class="sub-text">Total students in the system</div>
        </div>

        <div class="card">
          <h3>Attendance</h3>
          <div class="big-number">
            <?= $stats['attendance_avg'] !== null ? $stats['attendance_avg'] . '%' : '—' ?>
          </div>
          <div class="sub-text">
            Overall present ratio
          </div>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
