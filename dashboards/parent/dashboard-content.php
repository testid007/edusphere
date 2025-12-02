<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_id = (int)$_SESSION['user_id'];

/**
 * Convert letter grades to a numeric value for averaging (GPA-style).
 */
function gradeToNumber($grade) {
    $map = [
        'A+' => 4.3, 'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0, 'F' => 0,
    ];
    $g = strtoupper(trim((string)$grade));
    return $map[$g] ?? (is_numeric($g) ? (float)$g : null);
}

/**
 * Convert a score string like "45/50" or "78" to a percentage (0–100).
 */
function scoreToPercent(?string $score): ?float {
    if ($score === null) return null;
    $s = trim($score);
    if ($s === '') return null;

    if (preg_match('/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/', $s, $m)) {
        $obt = (float)$m[1];
        if (!empty($m[2])) {
            $max = (float)$m[2];
            if ($max <= 0) return null;
            return ($obt / $max) * 100.0;
        }
        return $obt; // already a percentage
    }
    return null;
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

// risk related
$gradePercent        = null;
$disciplineIncidents = 0;
$riskLevelLabel      = 'On Track';
$riskSeverity        = 'low';   // 'low' | 'medium' | 'high'
$riskReasonText      = 'Doing well overall. Keep supporting regular study habits.';
$riskTips            = [];

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

    $student_id  = (int)$row['student_id'];
    $child_name  = $row['full_name'];
    $child_class = $row['class_name'];
    $_SESSION['child_student_id'] = $student_id; // for other parent pages

    // 2) Attendance percentage (1 row per day; status = 'present')
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present,
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

    // 3) Fees using fee_invoices + fee_payments
    $total_invoiced = 0.0;
    $total_paid     = 0.0;

    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) AS total_invoiced
            FROM fee_invoices
            WHERE student_id = :sid
        ");
        $stmt->execute([':sid' => $student_id]);
        $total_invoiced = (float)($stmt->fetchColumn() ?? 0);

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(fp.amount), 0) AS total_paid
            FROM fee_payments fp
            JOIN fee_invoices fi ON fp.invoice_id = fi.id
            WHERE fi.student_id = :sid
        ");
        $stmt->execute([':sid' => $student_id]);
        $total_paid = (float)($stmt->fetchColumn() ?? 0);

        $remaining_fee = max(0, $total_invoiced - $total_paid);
    } catch (Exception $eFee) {
        $total_invoiced = 0.0;
        $total_paid     = 0.0;
        $remaining_fee  = 0.0;
    }

    // 4) Grades -> GPA-like average AND numeric percentage from scores
    $stmt = $conn->prepare("
        SELECT grade, score, category
        FROM grades
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $gradeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $numSumGpa   = 0;
    $numCntGpa   = 0;
    $numSumScore = 0;
    $numCntScore = 0;

    foreach ($gradeRows as $g) {
        // GPA style
        $n = gradeToNumber($g['grade']);
        if ($n !== null) {
            $numSumGpa += $n;
            $numCntGpa++;
        }

        // percentage from score field (for risk)
        $p = scoreToPercent($g['score'] ?? null);
        if ($p !== null) {
            $numSumScore += $p;
            $numCntScore++;
        }

        // discipline count
        if (isset($g['category']) && $g['category'] === 'Discipline') {
            $disciplineIncidents++;
        }
    }

    if ($numCntGpa > 0) {
        $gpa              = $numSumGpa / $numCntGpa;   // 0 – 4.3-ish
        $avg_grade_display = round($gpa, 2) . ' GPA';
    } else {
        $gpa              = null;
        $avg_grade_display = 'N/A';
    }

    if ($numCntScore > 0) {
        $gradePercent = round($numSumScore / $numCntScore, 1);  // 0–100
    } else {
        $gradePercent = null;
    }

    // 5) Overall child progress (simple average of attendance & grade % if available)
    if ($gradePercent !== null) {
        $child_progress = round(($attendance + $gradePercent) / 2, 1);
    } else {
        $child_progress = $attendance;
    }

    // 6) At-Risk model for parent view (same spirit as teacher dashboard)
    if ($gradePercent === null && $attendance == 0 && $disciplineIncidents === 0) {
        // no data yet
        $riskSeverity   = 'low';
        $riskLevelLabel = 'On Track';
        $riskReasonText = 'Not enough data yet to calculate risk. Check again after a few classes and assessments.';
    } else {
        // Determine severity
        if (($attendance < 60 && $attendance > 0) ||
            ($gradePercent !== null && $gradePercent < 40) ||
            $disciplineIncidents >= 3) {
            $riskSeverity   = 'high';
            $riskLevelLabel = 'High Risk';
        } elseif (($attendance < 75 && $attendance > 0) ||
                  ($gradePercent !== null && $gradePercent < 55) ||
                  $disciplineIncidents >= 1) {
            $riskSeverity   = 'medium';
            $riskLevelLabel = 'Medium Risk';
        } else {
            $riskSeverity   = 'low';
            $riskLevelLabel = 'On Track';
        }

        // Reasons
        $reasons = [];
        if ($attendance > 0 && $attendance < 75) {
            $reasons[] = "attendance is " . $attendance . "%";
            $riskTips[] = "Encourage regular class attendance and avoid unnecessary absences.";
        }
        if ($gradePercent !== null && $gradePercent < 60) {
            $reasons[] = "average score is " . $gradePercent . "%";
            $riskTips[] = "Help your child revise weak subjects and complete assignments on time.";
        }
        if ($disciplineIncidents > 0) {
            $reasons[] = $disciplineIncidents . " discipline record" . ($disciplineIncidents === 1 ? '' : 's');
            $riskTips[] = "Discuss classroom behaviour and school rules calmly with your child.";
        }

        if (!empty($reasons)) {
            $riskReasonText = "Areas to watch: " . implode(', ', $reasons) . ".";
        } elseif ($riskSeverity === 'low') {
            $riskReasonText = "Balanced attendance and marks. Keep up the current routine.";
        }
    }

    // 7) Recent "notices" from school = recent ACTIVE EVENTS
    try {
        $stmt = $conn->prepare("
            SELECT 
                e.id,
                e.title,
                e.description,
                e.event_date
            FROM events e
            WHERE e.is_active = 1
            ORDER BY e.event_date DESC, e.start_time DESC
            LIMIT 3
        ");
        $stmt->execute();
        $recentNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eNotices) {
        $recentNotices = [];
    }

    // 8) Recent activity timeline (grades + event participation)
    $gradeTimeline = [];
    $eventTimeline = [];

    try {
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
        $timeLabel   = trim((string)($e['start_time'] ?? ''));

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

    usort($timelineItems, function($a, $b) {
        return strcmp($b['date'], $a['date']); // DESC
    });

} catch (Exception $e) {
    echo '<div class="alert alert-error">'
        . htmlspecialchars($e->getMessage())
        . '</div>';
}
?>

<div class="section">
  <div class="section-header">
    <h2>Overview</h2>
    <p>Quick summary of your child’s performance and payments.</p>
  </div>

  <div class="cards-grid">
    <!-- AT-RISK STATUS CARD -->
    <div class="card stat-card clickable" data-open-page="child-performance">
      <div class="stat-label">At-Risk Status</div>
      <div class="stat-value">
        <?php if ($riskSeverity === 'high'): ?>
          🔴 <?= htmlspecialchars($riskLevelLabel) ?>
        <?php elseif ($riskSeverity === 'medium'): ?>
          🟠 <?= htmlspecialchars($riskLevelLabel) ?>
        <?php else: ?>
          🟢 <?= htmlspecialchars($riskLevelLabel) ?>
        <?php endif; ?>
      </div>
      <div class="stat-hint">
        <?= htmlspecialchars($riskReasonText) ?>
      </div>
      <?php if (!empty($riskTips)): ?>
        <div style="margin-top:4px;font-size:0.78rem;color:#6b7280;">
          <strong>Parent tips:</strong>
          <?= htmlspecialchars(implode(' • ', $riskTips)) ?>
        </div>
      <?php endif; ?>
    </div>

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
