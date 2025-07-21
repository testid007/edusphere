<?php
session_start();
require_once '../../includes/db.php'; // your PDO connection

$student_name = $_SESSION['student_name'] ?? 'Student';
$student_email = $_SESSION['student_email'] ?? 'student@example.com';
$class_name = $_SESSION['class_name'] ?? 'Class 1';

// Handle submission marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment_id'])) {
    $assignment_id = intval($_POST['submit_assignment_id']);

    // Check if already submitted to avoid duplicates
    $check = $conn->prepare("SELECT id FROM submissions WHERE student_email = :student_email AND assignment_id = :assignment_id");
    $check->execute([
        ':student_email' => $student_email,
        ':assignment_id' => $assignment_id,
    ]);

    if ($check->rowCount() === 0) {
        $insert = $conn->prepare("INSERT INTO submissions (student_email, assignment_id) VALUES (:student_email, :assignment_id)");
        $insert->execute([
            ':student_email' => $student_email,
            ':assignment_id' => $assignment_id,
        ]);
    }
}

// Fetch assignments for the student's class
$stmt = $conn->prepare("
    SELECT id, title, due_date, status, pdf_path
    FROM assignments
    WHERE class_name = :class_name AND due_date >= CURDATE()
    ORDER BY due_date ASC
");
$stmt->execute([':class_name' => $class_name]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each assignment, check if the student has submitted it
foreach ($assignments as &$assignment) {
    $check = $conn->prepare("SELECT id FROM submissions WHERE student_email = :student_email AND assignment_id = :assignment_id");
    $check->execute([
        ':student_email' => $student_email,
        ':assignment_id' => $assignment['id'],
    ]);
    $assignment['student_submitted'] = $check->rowCount() > 0;
}
unset($assignment); // break reference
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
    .submit-btn {
      background-color: #28a745;
      border: none;
      color: white;
      padding: 5px 10px;
      cursor: pointer;
      border-radius: 4px;
    }
    .submit-btn:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
    .view-btn {
      background-color: #007bff;
      color: white;
      padding: 5px 10px;
      text-decoration: none;
      border-radius: 4px;
    }
    .view-btn:hover {
      background-color: #0056b3;
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
        <p>Welcome, <?= htmlspecialchars($student_name) ?> (<?= htmlspecialchars($class_name) ?>)</p>
      </header>

      <section class="content">
        <form method="POST">
          <table class="performance-table" style="width:100%; border-collapse: collapse;">
            <thead>
              <tr style="background:#f2f2f2;">
                <th style="padding:8px; border:1px solid #ddd;">Title</th>
                <th style="padding:8px; border:1px solid #ddd;">Due Date</th>
                <th style="padding:8px; border:1px solid #ddd;">Status</th>
                <th style="padding:8px; border:1px solid #ddd;">View</th>
                <th style="padding:8px; border:1px solid #ddd;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignments)): ?>
                <tr><td colspan="5" style="padding:10px; text-align:center;">No assignments available for your class.</td></tr>
              <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                  <tr>
                    <td style="padding:8px; border:1px solid #ddd;"><?= htmlspecialchars($assignment['title']) ?></td>
                    <td style="padding:8px; border:1px solid #ddd;"><?= htmlspecialchars($assignment['due_date']) ?></td>
                    <td style="padding:8px; border:1px solid #ddd;"><?= htmlspecialchars($assignment['status']) ?></td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center;">
                      <?php if (!empty($assignment['pdf_path']) && file_exists('../../' . $assignment['pdf_path'])): ?>
                        <a href="<?= htmlspecialchars('../../' . $assignment['pdf_path']) ?>" target="_blank" rel="noopener" class="view-btn">View</a>
                      <?php else: ?>
                        No File
                      <?php endif; ?>
                    </td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center;">
                      <?php if ($assignment['student_submitted']): ?>
                        <button class="submit-btn" disabled>Submitted</button>
                      <?php elseif ($assignment['status'] === 'Closed'): ?>
                        <button class="submit-btn" disabled>Closed</button>
                      <?php else: ?>
                        <button type="submit" class="submit-btn" name="submit_assignment_id" value="<?= $assignment['id'] ?>">Mark as Submitted</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
