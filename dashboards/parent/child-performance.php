<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo '<div class="alert alert-error">Access denied. Please log in as a parent.</div>';
    exit;
}

$parent_id = (int)$_SESSION['user_id'];

/**
 * Convert letter grades to a numeric value for averaging (GPA-ish).
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
 * Try to interpret a score string as percentage
 * Supports:
 *   "78"         → 78
 *   "18/20"      → 90
 *   "40 / 50"    → 80
 */
function scoreToPercentRisk(?string $score): ?float {
    if ($score === null) return null;
    $s = trim($score);
    if ($s === '') return null;

    if (preg_match('/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/', $s, $m)) {
        $obt = (float)$m[1];
        if (!empty($m[2])) {
            $max = (float)$m[2];
            if ($max <= 0) return null;
            return ($obt / $max) * 100;
        }
        // assume raw percentage (0–100)
        return $obt;
    }
    return null;
}

// ---------- DEFAULTS ----------
$student_id       = null;
$child_name       = 'Child';
$child_class      = null;

$att_present      = 0;
$att_total        = 0;
$att_absent       = 0;
$attendance_pct   = 0.0;

$gpa_display      = 'N/A';
$grade_items      = [];
$grade_summary    = [];
$errorMessage     = null;

// risk-related defaults
$riskSeverity     = 'low';          // low | medium | high
$riskLevelLabel   = 'On Track';
$riskReasonText   = 'Balanced attendance and marks.';
$riskTips         = [];
$gradePercentAll  = null;
$disciplineIncidents = 0;

try {
    // 1) Get linked child (student)
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

    // 2) Attendance stats
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present,
            COUNT(*) AS total
        FROM attendance
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $attData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attData && $attData['total'] > 0) {
        $att_present    = (int)$attData['present'];
        $att_total      = (int)$attData['total'];
        $att_absent     = $att_total - $att_present;
        $attendance_pct = round(($att_present / $att_total) * 100, 2);
    }

    // 3) Grades – last few records and GPA (for display & summary)
    $stmt = $conn->prepare("
        SELECT 
            category,
            title,
            score,
            grade,
            comments,
            date_added
        FROM grades
        WHERE student_id = :sid
        ORDER BY date_added DESC
        LIMIT 10
    ");
    $stmt->execute([':sid' => $student_id]);
    $grade_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // GPA + category summary based on these recent items
    $numSum   = 0; 
    $numCnt   = 0;
    $catCount = [];

    foreach ($grade_items as $g) {
        $n = gradeToNumber($g['grade']);
        if ($n !== null) {
            $numSum += $n;
            $numCnt++;
        }

        $cat = $g['category'] ?: 'Other';
        $catCount[$cat] = ($catCount[$cat] ?? 0) + 1;
    }

    if ($numCnt > 0) {
        $gpa         = $numSum / $numCnt;
        $gpa_display = round($gpa, 2) . ' GPA';
    }

    $grade_summary = $catCount;

    // 4) RISK ANALYSIS – use ALL grades for risk, not just last 10
    $allGrades = [];
    $stmt = $conn->prepare("
        SELECT 
            score,
            category
        FROM grades
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $allGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scorePercents = [];
    $disciplineIncidents = 0;

    foreach ($allGrades as $gr) {
        $p = scoreToPercentRisk($gr['score'] ?? null);
        if ($p !== null) {
            $scorePercents[] = $p;
        }
        if (strcasecmp($gr['category'] ?? '', 'Discipline') === 0) {
            $disciplineIncidents++;
        }
    }

    if (!empty($scorePercents)) {
        $gradePercentAll = round(array_sum($scorePercents) / count($scorePercents), 1);
    }

    // ---------- CLASSIFY RISK ----------
    // Start with defaults
    $riskSeverity   = 'low';
    $riskLevelLabel = 'On Track';
    $riskReasonText = '';

    // High risk conditions
    if (
        ($attendance_pct > 0 && $attendance_pct < 60) ||
        ($gradePercentAll !== null && $gradePercentAll < 40) ||
        $disciplineIncidents >= 3
    ) {
        $riskSeverity   = 'high';
        $riskLevelLabel = 'High Risk';
    }
    // Medium risk conditions
    elseif (
        ($attendance_pct > 0 && $attendance_pct < 75) ||
        ($gradePercentAll !== null && $gradePercentAll < 55) ||
        $disciplineIncidents >= 1
    ) {
        $riskSeverity   = 'medium';
        $riskLevelLabel = 'At Risk';
    }

    // Build reasons
    $reasons = [];
    if ($attendance_pct > 0 && $attendance_pct < 75) {
        $reasons[] = "Low attendance ({$attendance_pct}%)";
    }
    if ($gradePercentAll !== null && $gradePercentAll < 60) {
        $reasons[] = "Low average marks ({$gradePercentAll}%)";
    }
    if ($disciplineIncidents > 0) {
        $reasons[] = "Discipline issues ({$disciplineIncidents} record(s))";
    }

    if (!empty($reasons)) {
        $riskReasonText = implode(', ', $reasons);
    } else {
        $riskReasonText = $riskSeverity === 'low'
            ? 'Balanced attendance and marks.'
            : 'Insufficient data to fully evaluate risk.';
    }

    // Improvement suggestions (shown only when medium/high)
    $riskTips = [];
    if ($riskSeverity === 'high' || $riskSeverity === 'medium') {
        if ($attendance_pct < 75) {
            $riskTips[] = 'Increase regular class attendance.';
        }
        if ($gradePercentAll !== null && $gradePercentAll < 60) {
            $riskTips[] = 'Revise weak subjects and practice daily.';
        }
        if ($disciplineIncidents > 0) {
            $riskTips[] = 'Maintain positive school behaviour and avoid incidents.';
        }
        if (empty($riskTips)) {
            $riskTips[] = 'Regularly review progress with your child and subject teachers.';
        }
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>

<!-- ========= AT-RISK STATUS PANEL ========= -->
<div class="section">
  <div class="card stat-card" style="background:#fff2e0;border-left:5px solid #f59e0b;">
    <div class="stat-label">Student Risk Indicator</div>
    <div class="stat-value">
      <?php if ($riskSeverity === 'high'): ?>
        🔴 <?= htmlspecialchars($riskLevelLabel) ?>
      <?php elseif ($riskSeverity === 'medium'): ?>
        🟠 <?= htmlspecialchars($riskLevelLabel) ?>
      <?php else: ?>
        ✅ <?= htmlspecialchars($riskLevelLabel) ?>
      <?php endif; ?>
    </div>

    <p style="font-size:0.88rem;color:#92400e;margin-top:4px;">
      <strong>Reason:</strong>
      <?= htmlspecialchars($riskReasonText) ?>
    </p>

    <?php if (!empty($riskTips)): ?>
      <div style="margin-top:6px;font-size:0.82rem;color:#0f172a;background:#ffe7c2;padding:6px 10px;border-radius:12px;">
        <strong>Improvement Suggestions:</strong><br/>
        <?= htmlspecialchars(implode(' • ', $riskTips)) ?>
      </div>
    <?php else: ?>
      <div style="margin-top:6px;font-size:0.8rem;color:#6b7280;">
        This child currently appears on track based on available data.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="section">
  <div class="section-header">
    <h2>Child Performance Overview</h2>
    <p>
      Detailed view of attendance and academic performance for
      <?= htmlspecialchars($child_name) ?><?= $child_class ? ' · ' . htmlspecialchars($child_class) : '' ?>.
    </p>
  </div>

  <?php if ($errorMessage): ?>
    <div class="alert alert-error">
      <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <div class="cards-grid">
    <div class="card stat-card">
      <div class="stat-label">Attendance</div>
      <div class="stat-value"><?= htmlspecialchars($attendance_pct) ?>%</div>
      <div class="stat-hint">
        Present: <?= (int)$att_present ?> / <?= (int)$att_total ?> days
      </div>
    </div>

    <div class="card stat-card">
      <div class="stat-label">Academic Standing</div>
      <div class="stat-value"><?= htmlspecialchars($gpa_display) ?></div>
      <div class="stat-hint">Based on recent graded activities</div>
    </div>

    <div class="card stat-card">
      <div class="stat-label">Absences</div>
      <div class="stat-value"><?= (int)$att_absent ?></div>
      <div class="stat-hint">Days marked absent</div>
    </div>

    <div class="card stat-card">
      <div class="stat-label">Categories Covered</div>
      <div class="stat-value"><?= count($grade_summary) ?></div>
      <div class="stat-hint">Exams, Assignments, Discipline, etc.</div>
    </div>
  </div>
</div>

<!-- Grade Category Breakdown -->
<div class="section">
  <div class="section-header">
    <h2>Grade Category Breakdown</h2>
    <p>How many graded items fall into each category.</p>
  </div>

  <div class="card">
    <?php if (empty($grade_summary)): ?>
      <p class="empty-row">No graded items recorded yet.</p>
    <?php else: ?>
      <?php $maxCount = max($grade_summary); ?>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($grade_summary as $cat => $cnt): 
          $percent = $maxCount > 0 ? round(($cnt / $maxCount) * 100) : 0;
        ?>
          <div style="font-size:0.86rem;margin-bottom:2px;">
            <strong><?= htmlspecialchars($cat) ?></strong>
            <span style="color:#6b7280;"> (<?= (int)$cnt ?> items)</span>
          </div>
          <div style="background:#f3e5d7;border-radius:999px;height:8px;overflow:hidden;margin-bottom:6px;">
            <div style="height:100%;width:<?= $percent ?>%;background:#f59e0b;"></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent grade details table -->
<div class="section">
  <div class="section-header">
    <h2>Recent Grade Details</h2>
    <p>Last few graded activities with scores and remarks.</p>
  </div>

  <div class="card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Title</th>
          <th>Score</th>
          <th>Grade</th>
          <th>Comments</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($grade_items)): ?>
        <tr>
          <td colspan="6">No grade records available.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($grade_items as $g): ?>
          <tr>
            <td>
              <?php
                $d = $g['date_added'] ?? '';
                echo $d ? htmlspecialchars(date('M d, Y', strtotime($d))) : '-';
              ?>
            </td>
            <td><?= htmlspecialchars($g['category'] ?: 'Assessment') ?></td>
            <td><?= htmlspecialchars($g['title']) ?></td>
            <td><?= htmlspecialchars($g['score']) ?></td>
            <td><?= htmlspecialchars($g['grade']) ?></td>
            <td><?= htmlspecialchars($g['comments'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
