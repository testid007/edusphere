<?php
// dashboards/admin/reports.php
require_once '../../includes/db.php';

// counts by role
$roles = ['student', 'teacher', 'parent', 'admin'];
$counts = [];
$total = 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE LOWER(role) = ?");
foreach ($roles as $r) {
    $stmt->execute([$r]);
    $row = $stmt->fetch();
    $counts[$r] = (int)($row['c'] ?? 0);
    $total += $counts[$r];
}
?>

<div class="card reports-card">
  <h3>Reports &amp; Analytics</h3>

  <div class="reports-chart-row">
    <div class="reports-chart">
      <canvas id="barChart"></canvas>
    </div>
    <div class="reports-chart">
      <canvas id="pieChart"></canvas>
    </div>
  </div>

  <div class="reports-summary"
       id="userRoleStats"
       data-student="<?= $counts['student'] ?>"
       data-teacher="<?= $counts['teacher'] ?>"
       data-parent="<?= $counts['parent'] ?>"
       data-admin="<?= $counts['admin'] ?>">

    <h4>Total Users: <?= $total ?></h4>
    <p>Students: <?= $counts['student'] ?></p>
    <p>Teachers: <?= $counts['teacher'] ?></p>
    <p>Parents: <?= $counts['parent'] ?></p>
    <p>Admins: <?= $counts['admin'] ?></p>
  </div>
</div>
