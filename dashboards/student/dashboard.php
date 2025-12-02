<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

// Canonical student identity
$student_id      = (int)($_SESSION['user_id'] ?? ($_SESSION['student_id'] ?? 0));
$student_name    = $_SESSION['student_name']  ?? 'Student';
$student_email   = $_SESSION['student_email'] ?? 'student@example.com';
$student_class   = $_SESSION['class'] ?? null;
$student_avatar  = '../../assets/img/user.jpg';

require_once '../../includes/db.php';
require_once '../../functions/EventManager.php';

$eventManager = new EventManager($conn);

/* -------------------------------------------------------
   Helper: parse score string ("45/50", "72", "72.5/100")
   (same helper as teacher dashboard)
--------------------------------------------------------*/
function score_to_percent(?string $score): ?float {
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
        // No max given – assume already a percentage
        return $obt;
    }
    return null;
}

// ---------- DASHBOARD STATS ----------
$stats = [
    'upcoming_assignments' => 0,
    'overdue_assignments'  => 0,
    'avg_score'            => null,
    'total_results'        => 0,
    'last_result_at'       => null,
    'attendance_present'   => 0,
    'attendance_total'     => 0,
    'attendance_percent'   => null,
    'total_fee_paid'       => 0.00,
    'fee_status'           => 'Pending',
    'unread_notices'       => 0,
];

/** Upcoming + overdue assignments for this student (next 7 days / past due) */
try {
    // upcoming unsubmitted this week
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM assignments a
        LEFT JOIN assignment_submissions s 
               ON a.id = s.assignment_id AND s.student_id = ?
        WHERE a.due_date >= CURDATE()
          AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND (s.id IS NULL OR s.status <> 'submitted')
    ");
    $stmt->execute([$student_id]);
    $stats['upcoming_assignments'] = (int)$stmt->fetchColumn();

    // overdue unsubmitted
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM assignments a
        LEFT JOIN assignment_submissions s 
               ON a.id = s.assignment_id AND s.student_id = ?
        WHERE a.due_date IS NOT NULL
          AND a.due_date < CURDATE()
          AND (s.id IS NULL OR s.status <> 'submitted')
    ");
    $stmt->execute([$student_id]);
    $stats['overdue_assignments'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

/** Grade summary + recent grade trend */
$gradeTrend = [];
try {
    $stmt = $conn->prepare("
        SELECT AVG(score) AS avg_score,
               COUNT(*)   AS total_rows,
               MAX(date_added) AS last_date
        FROM grades
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['avg_score']      = $row['avg_score'] !== null ? round($row['avg_score'], 1) : null;
        $stats['total_results']  = (int)$row['total_rows'];
        $stats['last_result_at'] = $row['last_date'] ?? null;
    }

    // latest 8 grade entries for trend chart
    $stmt = $conn->prepare("
        SELECT DATE(date_added) AS d, score
        FROM grades
        WHERE student_id = :sid
        ORDER BY date_added ASC
        LIMIT 8
    ");
    $stmt->execute([':sid' => $student_id]);
    $gradeTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/** Attendance summary for this student */
try {
    $stmt = $conn->prepare("
        SELECT 
          SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present_days,
          COUNT(*) AS total_records
        FROM attendance
        WHERE student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)$row['total_records'] > 0) {
        $stats['attendance_present'] = (int)$row['present_days'];
        $stats['attendance_total']   = (int)$row['total_records'];
        $stats['attendance_percent'] = round(
            $row['present_days'] / $row['total_records'] * 100,
            1
        );
    }
} catch (Exception $e) {}

/* -------------------------------------------------------
   STUDENT RISK FLAG  (same rules as teacher dashboard)
--------------------------------------------------------*/
$studentRisk = null;

try {
    // Attendance %
    $att = $stats['attendance_percent']; // may be null

    // Average grade % (use score_to_percent in case of "45/50" etc.)
    $gradePercent = null;
    $stmt = $conn->prepare("SELECT score FROM grades WHERE student_id = :sid");
    $stmt->execute([':sid' => $student_id]);
    $sum = 0.0; $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $p = score_to_percent($row['score']);
        if ($p === null) continue;
        $sum += $p;
        $count++;
    }
    if ($count > 0) {
        $gradePercent = round($sum / $count, 1);
    }

    // Discipline incidents
    $discCount = 0;
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM grades 
        WHERE student_id = :sid AND category = 'Discipline'
    ");
    $stmt->execute([':sid' => $student_id]);
    $discCount = (int)$stmt->fetchColumn();

    // Skip if no meaningful data
    if (!($att === null && $gradePercent === null)) {

        // Logistic regression coeffs (optional)
        $logisticCoeffs = null;
        try {
            $stmt = $conn->query("
                SELECT beta0, beta1, beta2, beta3, beta4
                FROM risk_logistic_coeffs
                ORDER BY created_at DESC
                LIMIT 1
            ");
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logisticCoeffs = [
                    (float)$row['beta0'],
                    (float)$row['beta1'],
                    (float)$row['beta2'],
                    (float)$row['beta3'],
                    (float)$row['beta4'],
                ];
            }
        } catch (Exception $e) {
            $logisticCoeffs = null;
        }

        // Decision-tree style rules
        $ruleLevel = 'LOW';
        if (($att !== null && $att < 60) || ($gradePercent !== null && $gradePercent < 40) || $discCount >= 3) {
            $ruleLevel = 'HIGH';
        } elseif (($att !== null && $att < 75) || ($gradePercent !== null && $gradePercent < 55) || $discCount >= 1) {
            $ruleLevel = 'MEDIUM';
        }

        // Logistic probability
        $prob = null;
        if ($logisticCoeffs) {
            [$b0,$b1,$b2,$b3,$b4] = $logisticCoeffs;
            $x1 = $att          ?? 0.0;
            $x2 = $gradePercent ?? 0.0;
            $x3 = $discCount    ?? 0.0;
            $x4 = 100.0; // placeholder assignment completion %
            $z  = $b0 + $b1*$x1 + $b2*$x2 + $b3*$x3 + $b4*$x4;
            $prob = 1.0 / (1.0 + exp(-$z));
            $prob = round($prob, 3);
        }

        // Final risk
        $final = 'LOW';
        if ($ruleLevel === 'HIGH' || ($prob !== null && $prob >= 0.75)) {
            $final = 'HIGH';
        } elseif ($ruleLevel === 'MEDIUM' || ($prob !== null && $prob >= 0.50)) {
            $final = 'MEDIUM';
        }

        if ($final !== 'LOW') {
            $studentRisk = [
                'final'      => $final,
                'attendance' => $att,
                'grade'      => $gradePercent,
                'discipline' => $discCount,
                'prob'       => $prob,
            ];
        }
    }
} catch (Exception $e) {
    $studentRisk = null;
}

/** Fee summary */
try {
    $sql = "SELECT COALESCE(SUM(amount),0) AS total_paid FROM fees WHERE student_id = :sid";
    $params = [':sid' => $student_id];
    if ($student_class) {
        $sql .= " AND class_name = :cls";
        $params[':cls'] = $student_class;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['total_fee_paid'] = (float)$row['total_paid'];
        $stats['fee_status']     = $stats['total_fee_paid'] > 0 ? 'Paid' : 'Pending';
    }
} catch (Exception $e) {}

/** Unread notices + last few items */
$recentNotices = [];
try {
    $stmt = $conn->prepare("
        SELECT id, title, created_at
        FROM notices
        WHERE (student_id = :sid OR student_id IS NULL)
          AND (is_read = 0 OR is_read IS NULL)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':sid' => $student_id]);
    $recentNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['unread_notices'] = count($recentNotices);
} catch (Exception $e) {}

/** Recommended events */
$recommendedEvents = $eventManager->getStudentRecommendedEvents($student_id, 4);

// ---------- Helper for time to event ----------
function computeTimeLeftLabel(?string $date, ?string $time): string {
    if (empty($date)) return '';
    try {
        $datePart = $date;
        $timePart = $time ?: '00:00:00';
        $eventDT  = new DateTime("$datePart $timePart");
        $now      = new DateTime();

        if ($eventDT < $now) return 'Already happened';

        $diff = $now->diff($eventDT);
        if ($diff->days > 0) {
            return 'in ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return 'in ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return 'in ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        return 'in a few moments';
    } catch (Exception $e) {
        return '';
    }
}

foreach ($recommendedEvents as &$ev) {
    $ev['time_left_label'] = computeTimeLeftLabel(
        $ev['event_date'] ?? null,
        $ev['start_time'] ?? null
    );
}
unset($ev);

// ---------- Notification list ----------
$notifications = [];

if ($stats['upcoming_assignments'] > 0) {
    $notifications[] = [
        'type' => 'assignment',
        'text' => "You have {$stats['upcoming_assignments']} assignment(s) due this week."
    ];
}
if ($stats['overdue_assignments'] > 0) {
    $notifications[] = [
        'type' => 'warning',
        'text' => "{$stats['overdue_assignments']} assignment(s) are overdue."
    ];
}
if ($stats['fee_status'] === 'Pending') {
    $notifications[] = [
        'type' => 'fee',
        'text' => "Your fee payment is pending. Check your Fee Details."
    ];
}
if ($stats['attendance_percent'] !== null && $stats['attendance_percent'] < 75) {
    $notifications[] = [
        'type' => 'attendance',
        'text' => "Your attendance is below 75%. Try not to miss upcoming classes."
    ];
}
if ($stats['unread_notices'] > 0) {
    $notifications[] = [
        'type' => 'notice',
        'text' => "You have {$stats['unread_notices']} unread notice(s)."
    ];
}
// NEW: risk notification
if ($studentRisk) {
    $label = $studentRisk['final'] === 'HIGH' ? 'high' : 'medium';
    $notifications[] = [
        'type' => 'risk',
        'text' => "You are currently flagged as {$label}-risk based on your grades, attendance and behaviour. Please review your progress and talk with your teacher."
    ];
}

$notificationCount = count($notifications);

// Initial to-dos (seed To-Do widget)
$initialTodos = [];
if ($stats['upcoming_assignments'] > 0) {
    $initialTodos[] = "Finish assignments due this week.";
}
if ($stats['overdue_assignments'] > 0) {
    $initialTodos[] = "Clear overdue assignment backlog.";
}
if (!empty($recommendedEvents)) {
    $initialTodos[] = "Pick at least one event to participate in this month.";
}
if ($stats['attendance_percent'] !== null && $stats['attendance_percent'] < 90) {
    $initialTodos[] = "Aim for full attendance this week.";
}
if ($studentRisk) {
    $initialTodos[] = "Meet your class teacher to discuss how to improve your performance.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard | EduSphere</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root {
      --bg-page: #f5eee9;
      --bg-shell: #fdfcfb;
      --bg-sidebar: #fdf5ec;
      --bg-main: #ffffff;

      --accent: #f59e0b;
      --accent-soft: #fff5e5;

      --text-main: #111827;
      --text-muted: #6b7280;

      --border-soft: #f3e5d7;
      --shadow-card: 0 12px 30px rgba(15,23,42,0.06);
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:var(--bg-page);
      color:var(--text-main);
    }

    .app-shell {
      width:100%;
      display:grid;
      grid-template-columns:260px 1fr;
      min-height:100vh;
      background:var(--bg-shell);
    }

    /* SIDEBAR (shared style with other pages) */
    .sidebar {
      background:var(--bg-sidebar);
      border-right:1px solid var(--border-soft);
      padding:28px 22px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .logo {
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:28px;
    }
    .logo img { height:40px; }
    .logo span {
      font-weight:700;
      font-size:1.15rem;
      color:#1f2937;
      letter-spacing:0.04em;
    }
    .nav {
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    .nav a {
      display:flex;
      align-items:center;
      gap:10px;
      padding:11px 14px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.95rem;
      text-decoration:none;
      transition:background .15s,color .15s,transform .15s,box-shadow .15s;
    }
    .nav a i {
      width:20px;
      text-align:center;
      color:#9ca3af;
    }
    .nav a.active {
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
      box-shadow:0 10px 22px rgba(245,158,11,.35);
    }
    .nav a.active i { color:#f59e0b; }
    .nav a:hover {
      background:#ffeeda;
      color:#92400e;
      transform:translateX(3px);
    }
    .nav a.logout { margin-top:10px;color:#b91c1c; }

    .sidebar-student-card {
      margin-top:24px;
      padding:14px 16px;
      border-radius:20px;
      background:radial-gradient(circle at top left,#ffe1b8,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:12px;
    }
    .sidebar-student-card img {
      width:44px;
      height:44px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-student-card .name {
      font-size:0.98rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-student-card .role {
      font-size:0.8rem;
      color:#92400e;
    }

    .main {
      padding:24px 44px 36px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner {
      max-width:1320px;
      margin:0 auto;
    }

    .main-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
    }
    .main-header-left h2 {
      margin:0;
      font-size:1.8rem;
      font-weight:700;
    }
    .main-header-left p {
      margin:4px 0 0;
      color:var(--text-muted);
      font-size:0.95rem;
    }

    .header-avatar {
      display:flex;
      align-items:center;
      gap:10px;
      padding:6px 14px;
      border-radius:999px;
      background:#fff7ea;
      border:1px solid #fed7aa;
      min-width:180px;
    }
    .header-avatar img {
      width:32px;
      height:32px;
      border-radius:50%;
      object-fit:cover;
    }
    .header-avatar .name {
      font-size:0.95rem;
      font-weight:600;
      color:#78350f;
    }
    .header-avatar .role {
      font-size:0.78rem;
      color:#c05621;
    }

    /* NOTIF DROPDOWN */
    .notif-wrapper { position:relative; }
    .icon-btn {
      position:relative;
      border:none;
      background:#fdfaf5;
      border-radius:999px;
      width:40px;
      height:40px;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      border:1px solid #e5e7eb;
    }
    .icon-btn i {
      color:#6b7280;
      font-size:1rem;
    }
    .icon-btn .badge {
      position:absolute;
      top:4px;
      right:4px;
      background:#ef4444;
      color:#fff;
      font-size:0.7rem;
      padding:2px 5px;
      border-radius:999px;
      font-weight:600;
    }
    .notif-dropdown {
      position:absolute;
      right:0;
      top:48px;
      width:290px;
      max-height:340px;
      overflow:auto;
      background:#ffffff;
      border-radius:18px;
      box-shadow:0 16px 38px rgba(15,23,42,.35);
      padding:10px;
      display:none;
      border:1px solid var(--border-soft);
      z-index:15;
    }
    .notif-dropdown.active { display:block; }
    .notif-dropdown h4 {
      margin:2px 4px 8px;
      font-size:0.95rem;
    }
    .notif-dropdown ul {
      list-style:none;
      margin:0;
      padding:0;
      font-size:0.86rem;
    }
    .notif-dropdown li {
      padding:7px 6px;
      border-radius:10px;
    }
    .notif-dropdown li + li { margin-top:4px; }

    .notif-tag {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:0.7rem;
      font-weight:600;
      margin-right:6px;
      text-transform:uppercase;
      letter-spacing:0.03em;
    }
    .notif-assign { background:#e8f5e9;color:#166534; }
    .notif-warning{ background:#fef3c7;color:#b45309; }
    .notif-fee    { background:#ffebee;color:#b91c1c; }
    .notif-event  { background:#e3f2fd;color:#1d4ed8; }
    .notif-attend { background:#f3e8ff;color:#6b21a8; }
    .notif-notice { background:#fff8e1;color:#f59e0b; }
    .notif-risk   { background:#fee2e2;color:#b91c1c; }

    .content-grid {
      margin-top:10px;
      display:grid;
      grid-template-columns:minmax(0,1.8fr) minmax(0,1.2fr);
      gap:20px;
      align-items:flex-start;
    }
    .left-column,
    .right-column {
      display:flex;
      flex-direction:column;
      gap:16px;
    }

    .hero-card {
      background:radial-gradient(circle at top right,#ffe6b0,#fff7ea);
      border-radius:20px;
      padding:18px 20px;
      box-shadow:var(--shadow-card);
      display:flex;
      justify-content:space-between;
      gap:18px;
    }
    .hero-left h3 {
      margin:0 0 4px;
      font-size:1.2rem;
    }
    .hero-left p {
      margin:0 0 10px;
      font-size:0.9rem;
      color:var(--text-muted);
    }
    .hero-metric {
      display:flex;
      align-items:flex-end;
      gap:6px;
      margin-bottom:8px;
      font-size:0.92rem;
      color:var(--text-muted);
    }
    .hero-metric span.big {
      font-size:1.8rem;
      font-weight:700;
      color:#b45309;
    }
    .hero-btn {
      border:none;
      border-radius:999px;
      background:var(--accent);
      color:#fff;
      padding:8px 16px;
      font-size:0.9rem;
      font-weight:600;
      cursor:pointer;
      box-shadow:0 10px 25px rgba(180,83,9,.45);
    }

    .hero-right {
      display:flex;
      flex-direction:column;
      align-items:flex-end;
      justify-content:space-between;
      gap:8px;
      min-width:180px;
    }
    .hero-pill {
      background:#fff;
      border-radius:16px;
      padding:10px 14px;
      font-size:0.8rem;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      color:var(--text-muted);
      min-width:180px;
      text-align:right;
    }
    .hero-pill strong { color:var(--text-main); }

    /* Risk pill shown to the flagged student */
    .risk-pill {
      margin-top:4px;
      padding:6px 10px;
      border-radius:999px;
      font-size:0.76rem;
      font-weight:700;
      text-align:right;
    }
    .risk-pill-high {
      background:#fee2e2;
      color:#b91c1c;
    }
    .risk-pill-medium {
      background:#ffedd5;
      color:#9a3412;
    }
    .risk-pill-ok {
      background:#dcfce7;
      color:#166534;
    }

    .stats-row {
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:14px;
    }
    .stat-card {
      background:var(--bg-main);
      border-radius:14px;
      padding:12px 14px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
    }
    .stat-label {
      font-size:0.8rem;
      text-transform:uppercase;
      letter-spacing:0.05em;
      color:#a16207;
      margin-bottom:4px;
    }
    .stat-value {
      font-size:1.3rem;
      font-weight:700;
      margin-bottom:2px;
    }
    .stat-sub {
      font-size:0.8rem;
      color:var(--text-muted);
    }

    .panel {
      background:var(--bg-main);
      border-radius:16px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:14px 16px 16px;
    }
    .panel-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:8px;
    }
    .panel-header h4 {
      margin:0;
      font-size:0.98rem;
    }
    .panel-header span {
      font-size:0.78rem;
      color:var(--text-muted);
    }

    #gradeTrendChart,
    #attendanceRing {
      width:100%;
      max-height:230px;
    }

    .todo-form {
      display:flex;
      gap:8px;
      margin-bottom:10px;
    }
    .todo-form input {
      flex:1;
      padding:8px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      font-size:0.88rem;
      background:#f9fafb;
    }
    .todo-form button {
      padding:8px 14px;
      border:none;
      border-radius:999px;
      background:#111827;
      color:#fff;
      font-weight:600;
      font-size:0.85rem;
      cursor:pointer;
    }
    .todo-list {
      list-style:none;
      padding:0;
      margin:0;
      font-size:0.88rem;
    }
    .todo-item {
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:6px 2px;
      border-bottom:1px solid #f3e5d7;
    }
    .todo-left {
      display:flex;
      align-items:center;
      gap:8px;
    }
    .todo-item.completed span {
      text-decoration:line-through;
      color:#9ca3af;
    }
    .todo-remove {
      border:none;
      background:none;
      color:#b91c1c;
      font-size:0.8rem;
      cursor:pointer;
    }

    .notice-list {
      list-style:none;
      margin:0;
      padding:0;
      font-size:0.86rem;
    }
    .notice-item {
      padding:6px 2px;
      border-bottom:1px solid #f3e5d7;
    }
    .notice-title {
      font-weight:600;
    }
    .notice-date {
      font-size:0.78rem;
      color:var(--text-muted);
    }

    .event-card {
      padding:8px 10px;
      border-radius:10px;
      border:1px dashed #fed7aa;
      background:#fff7ea;
      margin-bottom:8px;
      font-size:0.86rem;
    }
    .event-card h5 {
      margin:0 0 2px;
      font-size:0.9rem;
      color:#92400e;
    }
    .event-meta {
      color:var(--text-muted);
      font-size:0.8rem;
    }
    .event-meta .time-left {
      display:inline-block;
      margin-top:2px;
      font-weight:600;
      color:#16a34a;
    }

    @media(max-width:1100px){
      .app-shell{grid-template-columns:220px 1fr;}
      .stats-row{grid-template-columns:repeat(2,minmax(0,1fr));}
      .content-grid{grid-template-columns:1fr;}
    }
    @media(max-width:800px){
      .app-shell{grid-template-columns:1fr;}
      .sidebar{display:none;}
      .main{padding:18px;}
    }
  </style>
</head>
<body>
<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="EduSphere Logo" />
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="assignments.php"><i class="fas fa-book"></i> Assignments</a>
        <a href="results.php"><i class="fas fa-graduation-cap"></i> Results</a>
        <a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fees</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
    </div>
    <div class="sidebar-student-card">
      <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
      <div>
        <div class="name"><?= htmlspecialchars($student_name) ?></div>
        <div class="role">Student · EduSphere</div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <div class="main-header">
        <div class="main-header-left">
          <h2>My Student Hub</h2>
          <p>Good to see you, <?= htmlspecialchars($student_name) ?> 👋</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
          <div class="notif-wrapper">
            <button class="icon-btn" id="notifToggle" type="button" aria-label="Notifications">
              <i class="fas fa-bell"></i>
              <?php if ($notificationCount > 0): ?>
                <span class="badge"><?= $notificationCount ?></span>
              <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
              <h4>Notifications</h4>
              <?php if ($notificationCount === 0): ?>
                <div style="padding:8px 4px;font-size:0.84rem;color:#9ca3af;">
                  You're all caught up. No new alerts.
                </div>
              <?php else: ?>
                <ul>
                  <?php foreach ($notifications as $n): ?>
                    <?php
                      $tagClass = 'notif-notice';
                      if ($n['type'] === 'assignment')    $tagClass = 'notif-assign';
                      elseif ($n['type'] === 'warning')   $tagClass = 'notif-warning';
                      elseif ($n['type'] === 'fee')       $tagClass = 'notif-fee';
                      elseif ($n['type'] === 'attendance')$tagClass = 'notif-attend';
                      elseif ($n['type'] === 'risk')      $tagClass = 'notif-risk';
                    ?>
                    <li>
                      <span class="notif-tag <?= $tagClass ?>"><?= strtoupper(htmlspecialchars($n['type'])) ?></span>
                      <?= htmlspecialchars($n['text']) ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>

          <div class="header-avatar">
            <img src="<?= htmlspecialchars($student_avatar) ?>" alt="Student" />
            <div>
              <div class="name"><?= htmlspecialchars($student_name) ?></div>
              <div class="role">Class <?= htmlspecialchars($student_class ?? '—') ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENT GRID -->
      <div class="content-grid">
        <!-- LEFT COLUMN -->
        <section class="left-column">
          <!-- HERO -->
          <div class="hero-card">
            <div class="hero-left">
              <h3>Today’s Study Overview</h3>
              <p>Your assignments, results and notices in one place.</p>
              <div class="hero-metric">
                <span class="big"><?= $stats['upcoming_assignments'] ?></span>
                <span>assignment(s) due this week</span>
              </div>
              <button class="hero-btn" onclick="window.location.href='assignments.php'">
                View My Assignments
              </button>
            </div>
            <div class="hero-right">
              <div class="hero-pill">
                Avg score:
                <strong><?= $stats['avg_score'] !== null ? $stats['avg_score'] : '—' ?></strong><br/>
                Attendance:
                <strong><?= $stats['attendance_percent'] !== null ? $stats['attendance_percent'].'%' : '—' ?></strong><br/>
                Unread notices:
                <strong><?= $stats['unread_notices'] ?></strong>
              </div>
              <?php
                $riskClass = 'risk-pill-ok';
                $riskText  = 'You are currently on track.';
                if ($studentRisk) {
                    if ($studentRisk['final'] === 'HIGH') {
                        $riskClass = 'risk-pill-high';
                        $riskText  = 'You are flagged HIGH risk. Please talk with your teacher.';
                    } else {
                        $riskClass = 'risk-pill-medium';
                        $riskText  = 'You are flagged MEDIUM risk. Focus on improving your progress.';
                    }
                }
              ?>
              <div class="risk-pill <?= $riskClass ?>">
                <?= htmlspecialchars($riskText) ?>
                <?php if ($studentRisk && $studentRisk['prob'] !== null): ?>
                  <br><span style="font-weight:400;">Model probability: <?= number_format($studentRisk['prob']*100,1) ?>%</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- STATS -->
          <div class="stats-row">
            <div class="stat-card">
              <div class="stat-label">Assignments</div>
              <div class="stat-value"><?= $stats['upcoming_assignments'] ?></div>
              <div class="stat-sub">Due in next 7 days</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Overdue</div>
              <div class="stat-value"><?= $stats['overdue_assignments'] ?></div>
              <div class="stat-sub">Need your attention</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Results</div>
              <div class="stat-value">
                <?= $stats['avg_score'] !== null ? $stats['avg_score'] : '—' ?>
              </div>
              <div class="stat-sub">
                Avg from <?= $stats['total_results'] ?> record<?= $stats['total_results'] == 1 ? '' : 's' ?>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Attendance</div>
              <div class="stat-value">
                <?= $stats['attendance_percent'] !== null ? $stats['attendance_percent'].'%' : '—' ?>
              </div>
              <div class="stat-sub">
                <?= $stats['attendance_total'] ?> day<?= $stats['attendance_total'] == 1 ? '' : 's' ?> tracked
              </div>
            </div>
          </div>

          <!-- GRADE TREND + ATTENDANCE RING -->
          <div class="panel">
            <div class="panel-header">
              <h4>Progress Snapshot</h4>
              <span>Your recent scores and attendance</span>
            </div>
            <div style="display:grid;grid-template-columns:2fr 1.2fr;gap:12px;align-items:center;">
              <div>
                <canvas id="gradeTrendChart"></canvas>
              </div>
              <div>
                <canvas id="attendanceRing"></canvas>
              </div>
            </div>
          </div>

          <!-- TO-DO -->
          <div class="panel">
            <div class="panel-header">
              <h4><i class="fa-solid fa-list-check"></i> To-Do List</h4>
              <span>Plan your day & tick things off.</span>
            </div>
            <form class="todo-form" id="todoForm">
              <input type="text" id="todoInput" placeholder="Add a new task (e.g., Revise maths chapter 3)" />
              <button type="submit">Add</button>
            </form>
            <ul class="todo-list" id="todoList"></ul>
          </div>
        </section>

        <!-- RIGHT COLUMN -->
        <aside class="right-column">
          <!-- NOTICES -->
          <div class="panel">
            <div class="panel-header">
              <h4>Latest Notices</h4>
              <span><?= $stats['unread_notices'] ?> unread</span>
            </div>
            <?php if (empty($recentNotices)): ?>
              <p style="font-size:0.86rem;color:var(--text-muted);margin:0;">
                No new notices. Check back later.
              </p>
            <?php else: ?>
              <ul class="notice-list">
                <?php foreach ($recentNotices as $n): ?>
                  <li class="notice-item">
                    <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notice-date">
                      <?= htmlspecialchars(date('M j, Y', strtotime($n['created_at'] ?? 'now'))) ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <!-- RECOMMENDED EVENTS -->
          <div class="panel">
            <div class="panel-header">
              <h4><i class="fa-solid fa-calendar-check"></i> Recommended Events</h4>
              <span>Picked for your class & interests</span>
            </div>
            <?php if (empty($recommendedEvents)): ?>
              <p style="font-size:0.86rem;color:var(--text-muted);margin:0;">
                No recommended events right now. Once you join a few events, similar ones will show here.
              </p>
            <?php else: ?>
              <?php foreach ($recommendedEvents as $ev): ?>
                <div class="event-card">
                  <h5><?= htmlspecialchars($ev['title']) ?></h5>
                  <div class="event-meta">
                    <?= htmlspecialchars($ev['category_name'] ?? 'Event') ?>
                    · <?= htmlspecialchars($ev['event_date']) ?>
                    <?php if (!empty($ev['start_time'])): ?>
                      at <?= htmlspecialchars(substr($ev['start_time'],0,5)) ?>
                    <?php endif; ?>
                    <?php if (!empty($ev['location'])): ?>
                      <br><?= htmlspecialchars($ev['location']) ?>
                    <?php endif; ?>
                    <?php if (!empty($ev['time_left_label']) && $ev['time_left_label'] !== 'Already happened'): ?>
                      <br><span class="time-left"><?= htmlspecialchars($ev['time_left_label']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- QUICK TIPS -->
          <div class="panel">
            <div class="panel-header">
              <h4>Study Tips</h4>
            </div>
            <ul style="margin:0;padding-left:16px;font-size:0.86rem;color:var(--text-muted);">
              <li>Use this dashboard every morning to see what needs attention.</li>
              <li>Complete shorter tasks first to build momentum.</li>
              <li>Review your latest results after every exam and set a small improvement goal.</li>
              <li>Maintain 90%+ attendance to avoid backlogs and surprises in exams.</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </main>
</div>

<script>
  // Notification dropdown
  (function() {
    const toggle   = document.getElementById('notifToggle');
    const dropdown = document.getElementById('notifDropdown');
    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.classList.toggle('active');
    });
    document.addEventListener('click', () => dropdown.classList.remove('active'));
    dropdown.addEventListener('click', e => e.stopPropagation());
  })();

  // To-Do list (localStorage per student)
  const studentId   = <?= json_encode($student_id) ?>;
  const storageKey  = 'student_todos_' + studentId;
  const initialTodos = <?= json_encode($initialTodos, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
  const todoForm   = document.getElementById('todoForm');
  const todoInput  = document.getElementById('todoInput');
  const todoListEl = document.getElementById('todoList');
  let todos = [];

  function saveTodos() {
    localStorage.setItem(storageKey, JSON.stringify(todos));
  }
  function renderTodos() {
    todoListEl.innerHTML = '';
    if (todos.length === 0) {
      const li = document.createElement('li');
      li.textContent = 'No tasks yet. Add one above.';
      li.style.fontSize = '0.88rem';
      li.style.color = '#9ca3af';
      todoListEl.appendChild(li);
      return;
    }
    todos.forEach((t, idx) => {
      const li = document.createElement('li');
      li.className = 'todo-item' + (t.done ? ' completed' : '');
      const left = document.createElement('div');
      left.className = 'todo-left';

      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = t.done;
      cb.addEventListener('change', () => {
        todos[idx].done = cb.checked;
        saveTodos();
        renderTodos();
      });

      const span = document.createElement('span');
      span.textContent = t.text;

      left.appendChild(cb);
      left.appendChild(span);

      const removeBtn = document.createElement('button');
      removeBtn.className = 'todo-remove';
      removeBtn.textContent = 'Remove';
      removeBtn.addEventListener('click', () => {
        todos.splice(idx, 1);
        saveTodos();
        renderTodos();
      });

      li.appendChild(left);
      li.appendChild(removeBtn);
      todoListEl.appendChild(li);
    });
  }
  function loadTodos() {
    const stored = localStorage.getItem(storageKey);
    if (stored) {
      try { todos = JSON.parse(stored) || []; } catch(e) { todos = []; }
    } else {
      todos = (initialTodos || []).map(text => ({ text, done:false }));
      saveTodos();
    }
    renderTodos();
  }
  todoForm.addEventListener('submit', e => {
    e.preventDefault();
    const text = todoInput.value.trim();
    if (!text) return;
    todos.push({ text, done:false });
    todoInput.value = '';
    saveTodos();
    renderTodos();
  });
  loadTodos();

  // Grade trend chart
  (function() {
    const data = <?= json_encode($gradeTrend, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    if (!data || !data.length) return;
    const ctx = document.getElementById('gradeTrendChart');
    if (!ctx) return;

    const labels = data.map(r => r.d);
    const values = data.map(r => parseFloat(r.score));

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Score',
          data: values,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });
  })();

  // Attendance ring (doughnut)
  (function() {
    const percent = <?= $stats['attendance_percent'] !== null ? (float)$stats['attendance_percent'] : 'null' ?>;
    const canvas = document.getElementById('attendanceRing');
    if (percent === null || !canvas) return;

    new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Present', 'Absent'],
        datasets: [{
          data: [percent, 100 - percent],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ctx.parsed + '%'
            }
          }
        },
        cutout: '70%'
      }
    });
  })();
</script>
</body>
</html>
