<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_id   = (int)$_SESSION['user_id'];
$child_name  = 'Child';
$events      = [];
$errorMessage = null;

try {
    // Get linked child name (for subtitle only)
    $stmt = $conn->prepare("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM parents p
        JOIN students s ON p.student_id = s.user_id
        JOIN users   u ON s.user_id   = u.id
        WHERE p.user_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $child_name = $row['full_name'];
    }

    // Fetch active events (treated as notices)
    // NOTE: your events table has no class_name column, so we do NOT filter by class.
    $stmt = $conn->prepare("
        SELECT 
            e.event_date,
            e.start_time,
            e.end_time,
            e.title,
            e.description,
            e.location,
            c.name AS category_name
        FROM events e
        LEFT JOIN event_categories c ON e.category_id = c.id
        WHERE e.is_active = 1
        ORDER BY e.event_date DESC, e.start_time DESC
        LIMIT 50
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $errorMessage = 'Error loading notices: ' . $e->getMessage();
}
?>

<div class="section">
  <div class="section-header">
    <h2>Notices &amp; School Events</h2>
    <p>Announcements and events relevant to <?= htmlspecialchars($child_name) ?>.</p>
  </div>

  <?php if ($errorMessage): ?>
    <div class="alert alert-error">
      <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Title</th>
          <th>Category</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($events)): ?>
        <tr>
          <td colspan="4">No notices or events available.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($events as $e): ?>
          <tr>
            <td>
              <?php
                $dateStr = $e['event_date'] ?? '';
                echo $dateStr ? htmlspecialchars(date('M d, Y', strtotime($dateStr))) : '-';
              ?>
              <?php if (!empty($e['start_time'])): ?>
                <br>
                <span style="font-size:0.78rem;color:#6b7280;">
                  <?= htmlspecialchars(substr($e['start_time'], 0, 5)) ?>
                  <?php if (!empty($e['end_time'])): ?>
                    – <?= htmlspecialchars(substr($e['end_time'], 0, 5)) ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($e['title']) ?></td>
            <td><?= htmlspecialchars($e['category_name'] ?? 'General') ?></td>
            <td>
              <div style="font-size:0.86rem;">
                <?php if (!empty($e['location'])): ?>
                  <strong>Location:</strong> <?= htmlspecialchars($e['location']) ?><br>
                <?php endif; ?>

                <?php if (!empty($e['description'])): ?>
                  <?= htmlspecialchars(mb_strimwidth($e['description'], 0, 120, '…')) ?>
                <?php else: ?>
                  <span style="color:#6b7280;">No extra details.</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
