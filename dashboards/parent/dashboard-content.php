<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_id = (int)$_SESSION['user_id'];

/**
 * Convert letter grades to a numeric value for averaging.
 */
function gradeToNumber($grade) {
    $map = [
        'A+' => 4.3, 'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0, 'F' => 0,
    ];
    $g = strtoupper(trim($grade));
    return $map[$g] ?? (is_numeric($g) ? (float)$g : null);
}

// ---------- DEFAULT / FALLBACK VALUES ----------
$attendance        = 0;
$remaining_fee     = 0;
$avg_grade_display = 'N/A';
$child_progress    = 0;
$student_id        = null;
$child_name        = 'Child';
$child_class       = null;
$recentNotices     = [];
$timelineItems     = [];

try {
    // 1) Find the child (student) linked to this parent
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

    if (!$row) {
        throw new Exception('No child linked to this parent account.');
    }

    $student_id   = (int)$row['student_id'];
    $child_name   = $row['full_name'];
    $child_class  = $row['class_name'];
    $_SESSION['child_student_id'] = $student_id; // for other parent pages

    // 2) Attendance percentage (1 row per day; status = 'Present' or similar)
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
            COUNT(*) AS total
        FROM attendance
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $attendanceData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attendanceData && $attendanceData['total'] > 0) {
        $attendance = round(($attendanceData['present'] / $attendanceData['total']) * 100, 2);
    } else {
        $attendance = 0;
    }

    // 3) Fees (from fees table; here we just show total as "paid" and remaining = 0)
    $stmt = $conn->prepare("
        SELECT SUM(amount) AS total_paid
        FROM fees
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $total_paid    = (float)($stmt->fetchColumn() ?? 0);
    $remaining_fee = 0.00; // until you add a "total expected fee" table

    // 4) Grades -> numeric average and GPA-like display
    $stmt = $conn->prepare("
        SELECT grade
        FROM grades
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $numSum = 0;
    $numCnt = 0;
    foreach ($grades as $grade) {
        $n = gradeToNumber($grade);
        if ($n !== null) {
            $numSum += $n;
            $numCnt++;
        }
    }

    if ($numCnt > 0) {
        $gpa          = $numSum / $numCnt;              // 0 – 4.3-ish
        $gradePercent = min(100, round(($gpa / 4.3) * 100, 2));
        $avg_grade_display = round($gpa, 2) . ' GPA';
    } else {
        $gradePercent      = null;
        $avg_grade_display = 'N/A';
    }

    // 5) Overall child progress (simple average of attendance & grade % if available)
    if (isset($gradePercent) && $gradePercent !== null) {
        $child_progress = round(($attendance + $gradePercent) / 2, 1);
    } else {
        $child_progress = $attendance;
    }

    // 6) Recent "notices" from school = recent EVENTS for this child's class (or global)
    try {
        if ($child_class) {
            $stmt = $conn->prepare("
                SELECT 
                    e.id,
                    e.title,
                    e.description,
                    e.event_date
                FROM events e
                WHERE 
                    (e.class_name IS NULL OR e.class_name = '' OR e.class_name = :class OR e.class_name = 'All')
                ORDER BY e.event_date DESC
                LIMIT 3
            ");
            $stmt->execute([':class' => $child_class]);
        } else {
            $stmt = $conn->query("
                SELECT id, title, description, event_date
                FROM events
                ORDER BY event_date DESC
                LIMIT 3
            ");
        }
        $recentNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eNotices) {
        $recentNotices = [];
    }

    // 7) Recent activity timeline (grades + event participation)
    $gradeTimeline = [];
    $eventTimeline = [];

    try {
        // last 5 graded items
        $stmt = $conn->prepare("
            SELECT 
                g.title,
                g.category,
                g.score,
                g.grade,
                g.date_added
            FROM grades g
            WHERE g.student_id = :sid
            ORDER BY g.date_added DESC
            LIMIT 5
        ");
        $stmt->execute([':sid' => $student_id]);
        $gradeTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eGrades) {
        $gradeTimeline = [];
    }

    try {
        // last 5 events where the child has some participation status
        $stmt = $conn->prepare("
            SELECT 
                e.title,
                c.name AS category_name,
                e.event_date,
                e.start_time,
                e.location,
                ep.status
            FROM event_participation ep
            JOIN events e           ON ep.event_id = e.id
            JOIN event_categories c ON e.category_id = c.id
            WHERE ep.user_id = :sid
            ORDER BY e.event_date DESC, e.start_time DESC
            LIMIT 5
        ");
        $stmt->execute([':sid' => $student_id]);
        $eventTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eEvents) {
        $eventTimeline = [];
    }

    // Merge grades + events into one array for the timeline
    foreach ($gradeTimeline as $g) {
        $timelineItems[] = [
            'type' => 'grade',
            'date' => $g['date_added'],
            'title'=> $g['title'],
            'meta' => sprintf(
                '%s • Score: %s (%s)',
                $g['category'] ?: 'Assessment',
                $g['score'] ?? 'N/A',
                $g['grade'] ?? '-'
            )
        ];
    }

    foreach ($eventTimeline as $e) {
        $labelStatus = ucwords(str_replace('_', ' ', $e['status'] ?? ''));
        $timeLabel   = trim(($e['start_time'] ?? ''));

        $timelineItems[] = [
            'type' => 'event',
            'date' => $e['event_date'],
            'title'=> $e['title'],
            'meta' => sprintf(
                '%s • %s%s%s',
                $e['category_name'] ?? 'Event',
                $e['location'] ? $e['location'] . ' • ' : '',
                $timeLabel ? $timeLabel . ' • ' : '',
                $labelStatus ?: 'Planned'
            )
        ];
    }

    // Sort timeline by date DESC
    usort($timelineItems, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

} catch (Exception $e) {
    echo '<div class="alert alert-error">'
        . htmlspecialchars($e->getMessage())
        . '</div>';
    // fallback values already set
}
?>

<div class="section">
  <div class="section-header">
    <h2>Overview</h2>
    <p>Quick summary of your child’s performance and payments.</p>
  </div>

  <div class="cards-grid">
    <div class="card stat-card clickable" data-open-page="child-performance">
      <div class="stat-label">Child Progress</div>
      <div class="stat-value"><?= htmlspecialchars($child_progress) ?>%</div>
      <div class="stat-hint">
        <?= htmlspecialchars($child_name) ?><?= $child_class ? ' · ' . htmlspecialchars($child_class) : '' ?>
      </div>
    </div>

    <div class="card stat-card clickable" data-open-page="child-performance">
      <div class="stat-label">Attendance Record</div>
      <div class="stat-value"><?= htmlspecialchars($attendance) ?>%</div>
      <div class="stat-hint">View detailed attendance history</div>
    </div>

    <div class="card stat-card clickable" data-open-page="fee-status">
      <div class="stat-label">Fee Payment Remaining</div>
      <div class="stat-value">Rs <?= number_format($remaining_fee, 2) ?></div>
      <div class="stat-hint">Open detailed fee status</div>
    </div>

    <div class="card stat-card clickable" data-open-page="child-performance">
      <div class="stat-label">Student Result (Avg Grade)</div>
      <div class="stat-value"><?= htmlspecialchars($avg_grade_display) ?></div>
      <div class="stat-hint">Check grades &amp; feedback</div>
    </div>
  </div>
</div>

<div class="section">
  <div class="section-header">
    <h2>Updates</h2>
    <p>Recent notices from school and your child’s latest activities.</p>
  </div>

  <div class="updates-grid">
    <!-- Recent Notices (based on events) -->
    <div class="card">
      <div class="card-header-row">
        <div>
          <h3 class="card-title">Recent Notices from School</h3>
          <p class="card-sub">Latest updates shared by the school administration.</p>
        </div>
        <button class="chip" data-open-page="notices">View all notices →</button>
      </div>

      <ul class="notice-list">
        <?php if (empty($recentNotices)): ?>
          <li class="empty-row">No recent notices available.</li>
        <?php else: ?>
          <?php foreach ($recentNotices as $notice): ?>
            <li>
              <div class="notice-title">
                <?= htmlspecialchars($notice['title']) ?>
              </div>
              <?php if (!empty($notice['description'])): ?>
                <div class="notice-snippet">
                  <?= htmlspecialchars(mb_strimwidth($notice['description'], 0, 110, '…')) ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($notice['event_date'])): ?>
                <div class="notice-meta">
                  <?= htmlspecialchars(date('M d, Y', strtotime($notice['event_date']))) ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
      <div class="card-header-row">
        <div>
          <h3 class="card-title">Recent Activity Timeline</h3>
          <p class="card-sub">Latest grades and event participation for <?= htmlspecialchars($child_name) ?>.</p>
        </div>
        <div class="chip-group" id="timelineFilter">
          <button type="button" class="chip chip-sm active" data-filter="all">All</button>
          <button type="button" class="chip chip-sm" data-filter="grade">Grades</button>
          <button type="button" class="chip chip-sm" data-filter="event">Events</button>
        </div>
      </div>

      <ul class="timeline" id="timelineList">
        <?php if (empty($timelineItems)): ?>
          <li class="empty-row">No recent activity recorded yet.</li>
        <?php else: ?>
          <?php foreach ($timelineItems as $item): ?>
            <li class="timeline-item" data-type="<?= htmlspecialchars($item['type']) ?>">
              <div class="timeline-dot <?= $item['type'] === 'grade' ? 'grade' : 'event' ?>"></div>
              <div class="timeline-content">
                <div class="timeline-title">
                  <?= htmlspecialchars($item['title']) ?>
                </div>
                <div class="timeline-meta">
                  <?= htmlspecialchars($item['meta']) ?>
                </div>
                <div class="timeline-date">
                  <?= htmlspecialchars(date('M d, Y', strtotime($item['date']))) ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
