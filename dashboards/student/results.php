<?php
session_start();
require_once '../../includes/db.php';

// TEMP DEBUG: force a student_id if missing
if (empty($_SESSION['student_id'])) {
    $_SESSION['student_id'] = 36; // <-- Change this to a valid student_id for testing
    $_SESSION['student_name'] = 'Test Student';
}

// Now get from session
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Allowed categories for filter dropdown
$allowed_categories = ['Assignment', 'Exam', 'Discipline', 'Classroom Activity'];
$selected_category = $_GET['category'] ?? 'All';

// Prepare SQL with optional category filter
$sql = "SELECT category, title, score, grade, comments, date_added FROM grades WHERE student_id = :student_id";
$params = [':student_id' => $student_id];

if (in_array($selected_category, $allowed_categories)) {
    $sql .= " AND category = :category";
    $params[':category'] = $selected_category;
}

$sql .= " ORDER BY date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Grades (Debug)</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .performance-table {
      width: 100%;
      border-collapse: collapse;
    }
    .performance-table th, .performance-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    .performance-table thead tr {
      background-color: #f2f2f2;
    }
    .filter-form {
      margin-bottom: 15px;
    }
    .filter-select {
      padding: 6px 10px;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .filter-btn {
      background-color: #007bff;
      border: none;
      color: white;
      padding: 6px 12px;
      font-size: 1rem;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 8px;
    }
    .filter-btn:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="30" />
      </div>

      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="assignments.php"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php" class="active"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
      </div>
    </aside>

    <main class="main">
      <header class="header">
        <h2>My Grades (Debug Mode)</h2>
        <p>Welcome, <?= htmlspecialchars($student_name) ?>!</p>
      </header>

      <section class="content">
        <form method="GET" class="filter-form">
          <label for="category">Filter by Category: </label>
          <select name="category" id="category" class="filter-select">
            <option value="All" <?= $selected_category === 'All' ? 'selected' : '' ?>>All</option>
            <?php foreach ($allowed_categories as $cat): ?>
              <option value="<?= $cat ?>" <?= $selected_category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="filter-btn">Filter</button>
        </form>

        <table class="performance-table">
          <thead>
            <tr>
              <th>Category</th>
              <th>Title</th>
              <th>Score</th>
              <th>Grade</th>
              <th>Comments</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($results)): ?>
              <tr><td colspan="6" style="text-align:center; padding:10px;">No grades found.</td></tr>
            <?php else: ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['category']) ?></td>
                  <td><?= htmlspecialchars($row['title']) ?></td>
                  <td><?= htmlspecialchars($row['score']) ?></td>
                  <td><?= htmlspecialchars($row['grade']) ?></td>
                  <td><?= htmlspecialchars($row['comments']) ?></td>
                  <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['date_added']))) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
