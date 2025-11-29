<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_id = (int)$_SESSION['user_id'];

$child_name  = 'Child';
$child_class = null;
$events      = [];

try {
    // Find linked child and class
    $stmt = $conn->prepare("
        SELECT 
            s.user_id AS student_id,
            s.class   AS class_name,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name
        FROM parents p
        JOIN students s ON p.student_id = s.user_id
        JOIN users   u ON s.user_id   = u.id
        WHERE p.user_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $child_name  = $row['full_name'];
        $child_class = $row['class_name'];
    }

    // Events treated as notices: either global or for child's class
    if ($child_class) {
        $stmt = $conn->prepare("
            SELECT 
                e.title,
                e.description,
                e.event_date,
                e.start_time,
                e.location,
                c.name AS category_name
            FROM events e
            LEFT JOIN event_categories c ON e.category_id = c.id
            WHERE 
                (e.class_name IS NULL OR e.class_name = '' OR e.class_name = :class OR e.class_name = 'All')
            ORDER BY e.event_date DESC, e.start_time DESC
        ");
        $stmt->execute([':class' => $child_class]);
    } else {
        $stmt = $conn->query("
            SELECT 
                e.title,
                e.description,
                e.event_date,
                e.start_time,
                e.location,
                c.name AS category_name
            FROM events e
            LEFT JOIN event_categories c ON e.category_id = c.id
            ORDER BY e.event_date DESC, e.start_time DESC
        ");
    }

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo '<div class="alert alert-error">Error loading notices: '
        . htmlspecialchars($e->getMessage())
        . '</div>';
}
?>

<div class="section">
  <div class="section-header">
    <h2>Notices &amp; School Events</h2>
    <p>Announcements and events relevant to <?= htmlspecialchars($child_name) ?>.</p>
  </div>

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
        <tr><td colspan="4">No notices or events available.</td></tr>
      <?php else: ?>
        <?php foreach ($events as $e): ?>
          <tr>
            <td>
              <?= !empty($e['event_date'])
                    ? htmlspecialchars(date('M d, Y', strtotime($e['event_date'])))
                    : '-' ?>
            </td>
            <td><?= htmlspecialchars($e['title']) ?></td>
            <td><?= htmlspecialchars($e['category_name'] ?? 'General') ?></td>
            <td>
              <?php
                $parts = [];
                if (!empty($e['location']))   $parts[] = $e['location'];
                if (!empty($e['start_time'])) $parts[] = $e['start_time'];
                $meta = implode(' • ', $parts);
              ?>
              <div>
                <?php if (!empty($meta)): ?>
                  <span style="font-size:0.8rem;color:#6b7280;">
                    <?= htmlspecialchars($meta) ?>
                  </span><br>
                <?php endif; ?>
                <span style="font-size:0.85rem;">
                  <?= htmlspecialchars($e['description'] ?? '') ?>
                </span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
