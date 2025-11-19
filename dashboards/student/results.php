<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

// Canonical identity
$student_id   = (int)($_SESSION['user_id'] ?? ($_SESSION['student_id'] ?? 0));
$student_name = $_SESSION['student_name'] ?? 'Student';

// Allowed categories for filter dropdown
$allowed_categories = ['Assignment', 'Exam', 'Discipline', 'Classroom Activity'];
$selected_category  = $_GET['category'] ?? 'All';

// Prepare SQL with optional category filter
$sql = "SELECT category, title, score, grade, comments, date_added 
        FROM grades 
        WHERE student_id = :student_id";
$params = [':student_id' => $student_id];

if (in_array($selected_category, $allowed_categories, true)) {
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
  <title>My Results</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .results-header {
      margin-bottom: 18px;
    }
    .filter-form {
      margin-bottom: 15px;
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:center;
    }
    .filter-select {
      padding: 6px 10px;
      font-size: 0.95rem;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .filter-btn {
      background-color: #111;
      border: none;
      color: white;
      padding: 6px 12px;
      font-size: 0.95rem;
      border-radius: 6px;
      cursor: pointer;
    }
    .filter-btn:hover {
      background-color: #333;
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
        <a href="assignments.php"><i class="fas fa-book"></i> My Assignments</a>
        <a href="results.php" class="active"><i class="fas fa-graduation-cap"></i> My Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fee Details</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>

      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="Student" />
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
      </div>
    </aside>

    <!-- Main -->
    <main class="main">
      <header class="header results-header">
        <div>
          <h2>My Results</h2>
          <p>Review your scores and grades across different categories.</p>
        </div>
      </header>

      <section class="content">
        <form method="GET" class="filter-form">
          <label for="category">Filter by Category:</label>
          <select name="category" id="category" class="filter-select">
            <option value="All" <?= $selected_category === 'All' ? 'selected' : '' ?>>All</option>
            <?php foreach ($allowed_categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $selected_category === $cat ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="filter-btn">Apply</button>
        </form>

        <div class="table-container">
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
                <tr>
                  <td colspan="6" style="text-align:center; padding:10px;">No results found.</td>
                </tr>
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
        </div>
      </section>
    </main>
  </div>
</body>
</html>
