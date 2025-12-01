<?php
session_start();

$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_name   = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email  = $_SESSION['teacher_email'] ?? 'teacher@example.com';
$teacher_avatar = '../../assets/img/user.jpg';

/* -------------------------------------------------------
   AJAX HANDLERS (CRUD for grades)
--------------------------------------------------------*/
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    try {
        if ($action === 'list') {
            // include class + student name
            $stmt = $conn->query("
                SELECT g.*,
                       s.class AS class_name,
                       CONCAT(u.first_name, ' ', u.last_name) AS student_name
                FROM grades g
                LEFT JOIN students s ON g.student_id = s.user_id
                LEFT JOIN users   u ON g.student_id = u.id
                ORDER BY g.date_added DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        if ($action === 'get' && isset($_GET['id'])) {
            $id   = (int)$_GET['id'];
            $stmt = $conn->prepare("
                SELECT g.*,
                       s.class AS class_name
                FROM grades g
                LEFT JOIN students s ON g.student_id = s.user_id
                WHERE g.id = ?
            ");
            $stmt->execute([$id]);
            $grade = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => (bool)$grade,
                'data'    => $grade,
                'message' => $grade ? '' : 'Grade not found'
            ]);
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            $class_selected = trim($_POST['class_select'] ?? '');
            $student_id     = (int)($_POST['student_id'] ?? 0);
            $category       = trim($_POST['category'] ?? '');
            $title          = trim($_POST['title'] ?? '');
            $score          = trim($_POST['score'] ?? '');
            $grade_val      = trim($_POST['grade'] ?? '');
            $comments       = trim($_POST['comments'] ?? '');

            $errors = [];
            if ($class_selected === '') $errors[] = 'Class is required';
            if ($student_id <= 0)       $errors[] = 'Invalid student';
            $allowed_cat = ['Assignment','Exam','Discipline','Classroom Activity','Unit Test','Terminal Exam'];
            if (!in_array($category, $allowed_cat, true)) $errors[] = 'Invalid category';
            if ($title === '')  $errors[] = 'Title is required';
            if ($score === '')  $errors[] = 'Score is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO grades (student_id, category, title, score, grade, comments)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade added successfully' : 'Failed to add grade'
                ]);
                exit;
            } else { // update
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing grade ID']);
                    exit;
                }
                $id   = (int)$_POST['id'];
                $stmt = $conn->prepare("
                    UPDATE grades
                    SET student_id = ?, category = ?, title = ?, score = ?, grade = ?, comments = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments, $id]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade updated successfully' : 'Failed to update grade'
                ]);
                exit;
            }
        }

        if ($action === 'delete' && isset($_POST['id'])) {
            $id   = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
            $ok   = $stmt->execute([$id]);
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Grade deleted successfully' : 'Failed to delete grade'
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

/* -------------------------------------------------------
   Helper: parse score string ("45/50", "72", "72.5/100")
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

/* -------------------------------------------------------
   Logistic regression helpers (trained coefficients)
--------------------------------------------------------*/

/**
 * Load the latest trained logistic regression coefficients
 * from risk_logistic_coeffs table.
 *
 * Columns expected:
 *  intercept, beta_attendance, beta_grade_avg,
 *  beta_discipline, beta_assign_comp
 */
function get_risk_logistic_coeffs(PDO $conn): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $stmt = $conn->query("
            SELECT intercept,
                   beta_attendance,
                   beta_grade_avg,
                   beta_discipline,
                   beta_assign_comp
            FROM risk_logistic_coeffs
            ORDER BY id DESC
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $row = false;
    }

    if (!$row) {
        // Fallback demo values (safe default if table empty)
        $cached = [
            'b0' => 4.0,
            'b1' => -0.03,
            'b2' => -0.04,
            'b3' => 0.25,
            'b4' => -0.02,
        ];
    } else {
        $cached = [
            'b0' => (float)$row['intercept'],
            'b1' => (float)$row['beta_attendance'],
            'b2' => (float)$row['beta_grade_avg'],
            'b3' => (float)$row['beta_discipline'],
            'b4' => (float)$row['beta_assign_comp'],
        ];
    }

    return $cached;
}

/**
 * Logistic regression probability that a student is at risk.
 *
 * p = 1 / (1 + exp(-(β0 + β1 x1 + ... + β4 x4)))
 *
 *  x1 = attendance %, x2 = grade average %,
 *  x3 = discipline incidents count,
 *  x4 = assignment completion % (optional feature).
 */
function logistic_at_risk_prob(
    PDO $conn,
    float $attendance,
    float $gradeAvg,
    int $disciplineIncidents = 0,
    float $assignmentCompletion = 100.0
): float {
    $c = get_risk_logistic_coeffs($conn);

    $z = $c['b0']
       + $c['b1'] * $attendance
       + $c['b2'] * $gradeAvg
       + $c['b3'] * $disciplineIncidents
       + $c['b4'] * $assignmentCompletion;

    $p = 1.0 / (1.0 + exp(-$z)); // sigmoid

    if ($p < 0.0) $p = 0.0;
    if ($p > 1.0) $p = 1.0;
    return $p;
}

/**
 * Simple decision–tree style rule engine for risk label.
 * Uses human-readable if/else rules.
 */
function decision_tree_risk_label(
    float $attendance,
    float $gradeAvg,
    int $disciplineIncidents = 0
): string {
    // Example rule set – can be tuned
    if ($attendance < 60 && $gradeAvg < 50) {
        return 'High';
    }
    if ($attendance < 65 && $gradeAvg < 55) {
        return 'High';
    }
    if ($attendance < 75 || $gradeAvg < 55 || $disciplineIncidents >= 2) {
        return 'Medium';
    }
    return 'Low';
}

/* -------------------------------------------------------
   Small grade stats for header chips
--------------------------------------------------------*/
$gradeStats = [
    'total'       => 0,
    'assignment'  => 0,
    'exam'        => 0,
    'discipline'  => 0,
    'activity'    => 0,
];

try {
    $stmt = $conn->query("SELECT category, COUNT(*) AS c FROM grades GROUP BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $c = (int)$row['c'];
        $gradeStats['total'] += $c;
        switch ($row['category']) {
            case 'Assignment':         $gradeStats['assignment'] += $c; break;
            case 'Exam':
            case 'Terminal Exam':      $gradeStats['exam']       += $c; break;
            case 'Discipline':         $gradeStats['discipline'] += $c; break;
            case 'Classroom Activity': $gradeStats['activity']   += $c; break;
        }
    }
} catch (Exception $e) {}

$examPercent = $gradeStats['total'] > 0
    ? round(($gradeStats['exam'] / $gradeStats['total']) * 100)
    : 0;

/* -------------------------------------------------------
   Trend data + overall average (for chart)
--------------------------------------------------------*/
$gradeTrend   = [];
$overallAvg   = null;

try {
    $scoreByDate  = [];
    $countByDate  = [];

    $totalPercent = 0.0;
    $totalCount   = 0;

    $stmt = $conn->query("
        SELECT score, DATE(date_added) AS d
        FROM grades
        ORDER BY date_added ASC
        LIMIT 200
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $percent = score_to_percent($row['score']);
        if ($percent === null) continue;

        $d = $row['d'];
        if (!isset($scoreByDate[$d])) {
            $scoreByDate[$d] = 0.0;
            $countByDate[$d] = 0;
        }
        $scoreByDate[$d] += $percent;
        $countByDate[$d] += 1;

        $totalPercent += $percent;
        $totalCount   += 1;
    }

    foreach ($scoreByDate as $d => $sum) {
        $avg = $countByDate[$d] > 0 ? round($sum / $countByDate[$d], 1) : null;
        if ($avg !== null) {
            $gradeTrend[] = ['date' => $d, 'avg' => $avg];
        }
    }

    if (count($gradeTrend) > 30) {
        $gradeTrend = array_slice($gradeTrend, -30);
    }

    if ($totalCount > 0) {
        $overallAvg = round($totalPercent / $totalCount, 1);
    }
} catch (Exception $e) {
    $gradeTrend = [];
    $overallAvg = null;
}

/* -------------------------------------------------------
   At-risk students:
   - attendance %, grade average %
   - discipline incidents from grades table
   - logistic probability + decision-tree label
--------------------------------------------------------*/
$attendanceAgg    = [];
$gradeAgg         = [];
$disciplineAgg    = [];
$atRiskStudents   = [];

try {
    // Attendance %
    $stmt = $conn->query("
        SELECT student_id,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_days
        FROM attendance
        GROUP BY student_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $present = (int)$row['present_days'];
        $absent  = (int)$row['absent_days'];
        $total   = $present + $absent;
        $percent = $total > 0 ? round(($present / $total) * 100, 1) : null;
        $attendanceAgg[(int)$row['student_id']] = $percent;
    }

    // Average grade %
    $stmt = $conn->query("SELECT student_id, score FROM grades");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['student_id'];
        $percent = score_to_percent($row['score']);
        if ($percent === null) continue;
        if (!isset($gradeAgg[$sid])) {
            $gradeAgg[$sid] = ['sum' => 0.0, 'count' => 0];
        }
        $gradeAgg[$sid]['sum']   += $percent;
        $gradeAgg[$sid]['count'] += 1;
    }

    foreach ($gradeAgg as $sid => $g) {
        $gradeAgg[$sid] = $g['count'] > 0 ? round($g['sum'] / $g['count'], 1) : null;
    }

    // Discipline incidents (from grades table)
    $stmt = $conn->query("
        SELECT student_id, COUNT(*) AS cnt
        FROM grades
        WHERE category = 'Discipline'
        GROUP BY student_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $disciplineAgg[(int)$row['student_id']] = (int)$row['cnt'];
    }

    // All students (for names)
    $stmt = $conn->query("
        SELECT s.user_id AS id, CONCAT(u.first_name,' ',u.last_name) AS name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'Student'
        ORDER BY u.first_name, u.last_name
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id   = (int)$row['id'];
        $name = $row['name'];
        $att  = $attendanceAgg[$id] ?? null;
        $gr   = $gradeAgg[$id]      ?? null;

        // Skip students with no data at all
        if ($att === null && $gr === null) {
            continue;
        }

        $disciplineCnt = $disciplineAgg[$id] ?? 0;

        $attSafe = $att ?? 100.0;
        $grSafe  = $gr  ?? 100.0;

        // Logistic probability
        $prob = logistic_at_risk_prob(
            $conn,
            $attSafe,
            $grSafe,
            $disciplineCnt,
            100.0    // assignment completion placeholder
        );
        $probPercent = round($prob * 100, 1);

        // Decision-tree risk label
        $ruleLabel = decision_tree_risk_label($attSafe, $grSafe, $disciplineCnt);

        // We mark as "at risk" only if probability or rules say so
        $isAtRisk = ($prob >= 0.6) || ($ruleLabel === 'High' || $ruleLabel === 'Medium');

        if ($isAtRisk) {
            $atRiskStudents[] = [
                'name'          => $name,
                'attendance'    => $att,
                'grade'         => $gr,
                'discipline'    => $disciplineCnt,
                'prob_percent'  => $probPercent,
                'rule_label'    => $ruleLabel
            ];
        }
    }

    // Sort: highest probability first
    usort($atRiskStudents, function($a, $b) {
        return ($b['prob_percent'] <=> $a['prob_percent']);
    });

    // Limit to top 8
    if (count($atRiskStudents) > 8) {
        $atRiskStudents = array_slice($atRiskStudents, 0, 8);
    }
} catch (Exception $e) {
    $atRiskStudents = [];
}

/* -------------------------------------------------------
   Class -> students map (for dropdowns)
--------------------------------------------------------*/
$classes           = [];
$classStudentsMap  = [];

try {
    if ($conn->query("SHOW TABLES LIKE 'students'")->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT DISTINCT class
            FROM students
            WHERE class IS NOT NULL AND class <> ''
            ORDER BY class
        ");
        $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $conn->query("
            SELECT s.class,
                   s.user_id AS student_id,
                   CONCAT(u.first_name,' ',u.last_name) AS student_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE u.role = 'Student'
            ORDER BY s.class, student_name
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cls = (string)$row['class'];
            if (!isset($classStudentsMap[$cls])) {
                $classStudentsMap[$cls] = [];
            }
            $classStudentsMap[$cls][] = [
                'id'   => (int)$row['student_id'],
                'name' => $row['student_name'],
            ];
        }
    }
} catch (Exception $e) {
    $classes          = [];
    $classStudentsMap = [];
}

/* -------------------------------------------------------
   Subject & exam/test presets (UI helper only)
--------------------------------------------------------*/
$subjects = [
    'English','Nepali','Mathematics','Science','Social Studies',
    'Computer','Accountancy','Economics','Health & Population',
    'Optional Math'
];

$examTypes = [
    'Unit Test 1','Unit Test 2','First Term','Second Term',
    'Third Term','Terminal Exam','Final Exam','Practical','Project'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Grade Book | Teacher Dashboard</title>
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
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
    }

    .app-shell {
      width: 100%;
      display: grid;
      grid-template-columns: 250px 1fr;
      min-height: 100vh;
      background: var(--bg-shell);
    }
    .sidebar {
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border-soft);
      padding: 24px 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 24px;
    }
    .logo img { height: 36px; }
    .logo span {
      font-weight: 700;
      font-size: 1.05rem;
      color: #1f2937;
      letter-spacing: 0.04em;
    }
    .nav { display:flex; flex-direction:column; gap:6px; }
    .nav a {
      display:flex;
      align-items:center;
      gap:10px;
      padding:9px 12px;
      border-radius:999px;
      color:#6b7280;
      font-size:0.9rem;
      text-decoration:none;
      transition:background 0.15s ease-out, color 0.15s ease-out, transform 0.15s ease-out;
    }
    .nav a i { width:18px; text-align:center; color:#9ca3af; }
    .nav a.active {
      background:var(--accent-soft);
      color:#92400e;
      font-weight:600;
    }
    .nav a.active i { color:#f59e0b; }
    .nav a:hover {
      background:#ffeeda;
      color:#92400e;
      transform:translateX(2px);
    }
    .nav a.logout { margin-top:8px; color:#b91c1c; }

    .sidebar-teacher-card {
      margin-top:20px;
      padding:12px 14px;
      border-radius:18px;
      background:linear-gradient(135deg,#ffe9cf,#fff7ea);
      box-shadow:var(--shadow-card);
      display:flex;
      align-items:center;
      gap:10px;
    }
    .sidebar-teacher-card img {
      width:40px;
      height:40px;
      border-radius:50%;
      object-fit:cover;
      border:2px solid #fff;
    }
    .sidebar-teacher-card .name {
      font-size:0.9rem;
      font-weight:600;
      color:#78350f;
    }
    .sidebar-teacher-card .role {
      font-size:0.78rem;
      color:#92400e;
    }

    .main {
      padding:20px 40px 32px;
      background:radial-gradient(circle at top left,#fff7e6 0,#ffffff 55%);
    }
    .main-inner {
      max-width:1260px;
      margin:0 auto;
    }

    .page-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
    }
    .page-title-line {
      display:flex;
      align-items:center;
      gap:10px;
    }
    .page-title-icon {
      width:34px;
      height:34px;
      border-radius:999px;
      background:#fff3d6;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#d97706;
      box-shadow:0 10px 20px rgba(250,204,21,0.35);
    }
    .page-header h2 { margin:0; font-size:1.45rem; }
    .page-header p {
      margin:4px 0 0;
      font-size:0.9rem;
      color:var(--text-muted);
    }

    .page-header-clipart {
      display:flex;
      align-items:center;
      gap:12px;
    }
    .page-header-clipart img {
      height:60px;
      width:auto;
      animation:floaty 6s ease-in-out infinite;
    }
    .page-header-chip {
      padding:6px 10px;
      border-radius:999px;
      background:#fff7ea;
      font-size:0.8rem;
      color:#b45309;
    }
    @keyframes floaty {
      0%,100% { transform:translateY(0); }
      50% { transform:translateY(-6px); }
    }

    .content-grid {
      margin-top:6px;
      display:grid;
      grid-template-columns:minmax(0, 1.1fr) minmax(0, 1.7fr);
      gap:20px;
      align-items:flex-start;
    }

    .left-column {
      display:flex;
      flex-direction:column;
      gap:16px;
    }
    .right-column {
      display:flex;
      flex-direction:column;
      gap:16px;
    }

    .card {
      background:var(--bg-main);
      border-radius:20px;
      box-shadow:var(--shadow-card);
      border:1px solid var(--border-soft);
      padding:18px 20px 20px;
    }
    .card-title {
      margin:0 0 8px;
      font-size:1rem;
    }
    .card-sub {
      margin:0 0 14px;
      font-size:0.85rem;
      color:var(--text-muted);
    }

    .message {
      padding:10px 12px;
      border-radius:10px;
      font-size:0.86rem;
      margin-bottom:10px;
      display:none;
    }
    .message.success {
      background:#ecfdf3;
      color:#166534;
      border:1px solid #bbf7d0;
    }
    .message.error {
      background:#fef2f2;
      color:#b91c1c;
      border:1px solid #fecaca;
    }

    form.grade-form label {
      display:block;
      font-weight:600;
      margin-bottom:4px;
      color:#4b5563;
      font-size:0.86rem;
    }
    form.grade-form input[type="text"],
    form.grade-form select,
    form.grade-form textarea {
      width:100%;
      padding:9px 11px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      background-color:#f9fafb;
      margin-bottom:10px;
      font-size:0.88rem;
    }
    form.grade-form input:focus,
    form.grade-form select:focus,
    form.grade-form textarea:focus {
      outline:none;
      border-color:#f59e0b;
      box-shadow:0 0 0 1px rgba(245,158,11,0.25);
    }

    .primary-btn {
      background:var(--accent);
      color:#fff;
      border:none;
      border-radius:999px;
      padding:9px 18px;
      font-weight:600;
      font-size:0.9rem;
      cursor:pointer;
      box-shadow:0 10px 24px rgba(245,158,11,0.45);
      display:inline-flex;
      align-items:center;
      gap:6px;
      margin-top:4px;
    }
    .primary-btn:hover { filter:brightness(1.03); }
    .secondary-btn {
      background:#f97373;
      color:#fff;
      border:none;
      border-radius:999px;
      padding:7px 14px;
      font-size:0.85rem;
      cursor:pointer;
      margin-left:8px;
    }

    .form-illustration {
      text-align:center;
      margin-top:8px;
    }
    .form-illustration img {
      max-width:160px;
      width:100%;
      animation:floaty 5s ease-in-out infinite;
    }
    .form-illustration p {
      font-size:0.8rem;
      color:var(--text-muted);
      margin-top:6px;
    }

    .stats-row {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:10px;
    }
    .stat-chip {
      padding:6px 10px;
      border-radius:12px;
      font-size:0.78rem;
      background:#fff7ea;
      color:#92400e;
    }
    .stat-chip strong { color:#78350f; }

    .grade-toolbar {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin:8px 0 10px;
      font-size:0.86rem;
      align-items:center;
    }
    .grade-toolbar input,
    .grade-toolbar select {
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
    }

    .ghost-btn {
      background:#f9fafb;
      color:#4b5563;
      border:1px solid #e5e7eb;
      border-radius:999px;
      padding:7px 14px;
      font-size:0.82rem;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:6px;
      margin-left:auto;
    }

    table.gradebook-table {
      width:100%;
      border-collapse:collapse;
      margin-top:2px;
      background:#fff;
      border-radius:16px;
      overflow:hidden;
      box-shadow:var(--shadow-card);
      font-size:0.87rem;
    }
    .gradebook-table th,
    .gradebook-table td {
      padding:9px 10px;
      border-bottom:1px solid #f3e5d7;
      text-align:left;
    }
    .gradebook-table thead th {
      background:#fbbf24;
      color:#78350f;
    }
    .gradebook-table tbody tr:nth-child(even) {
      background:#fffaf0;
    }
    .gradebook-table tbody tr:hover {
      background:#fff7e6;
    }

    .btn-edit,.btn-delete {
      border:none;
      border-radius:999px;
      padding:4px 9px;
      font-size:0.78rem;
      cursor:pointer;
      color:#fff;
    }
    .btn-edit { background:#22c55e; margin-right:3px; }
    .btn-delete { background:#f97373; }

    .chip {
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      font-size:0.75rem;
      font-weight:600;
    }
    .chip-assignment { background:#e0f2fe;color:#0369a1; }
    .chip-exam,
    .chip-terminal-exam { background:#fee2e2;color:#b91c1c; }
    .chip-discipline { background:#fef3c7;color:#b45309; }
    .chip-classroom-activity { background:#dcfce7;color:#15803d; }
    .chip-unit-test { background:#ede9fe;color:#5b21b6; }

    #gradeTrendChart {
      width:100%;
      max-height:220px;
    }

    .percent-pill {
      display:inline-block;
      margin-top:2px;
      padding:2px 7px;
      border-radius:999px;
      font-size:0.72rem;
      background:#f3f4f6;
      color:#4b5563;
    }
    .badge-good { background:#dcfce7;color:#15803d; }
    .badge-mid  { background:#fef3c7;color:#b45309; }
    .badge-low  { background:#fee2e2;color:#b91c1c; }

    /* AT-RISK – highlight card */
    .risk-card {
      border:1px solid #f97316;
      box-shadow:0 16px 40px rgba(248, 113, 22, 0.25);
      background:linear-gradient(135deg,#fff7e6,#ffffff);
    }
    .risk-card-header {
      display:flex;
      align-items:center;
      gap:10px;
    }
    .risk-badge {
      padding:4px 9px;
      border-radius:999px;
      font-size:0.78rem;
      background:#f97316;
      color:#fff;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    .risk-badge i {
      font-size:0.85rem;
    }

    .risk-chips {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-top:10px;
    }
    .risk-chip {
      padding:8px 10px;
      border-radius:16px;
      font-size:0.8rem;
      background:#fff;
      color:#78350f;
      border:1px solid #fed7aa;
      box-shadow:0 8px 20px rgba(248,113,22,0.18);
      min-width:180px;
    }
    .risk-chip span.name {
      font-weight:600;
      margin-bottom:2px;
      display:block;
    }
    .risk-chip span.meta {
      font-size:0.75rem;
      color:#92400e;
    }
    .risk-label-high {
      background:#b91c1c;
      color:#fff;
      border-radius:999px;
      padding:2px 8px;
      font-size:0.7rem;
      font-weight:600;
      margin-left:6px;
    }
    .risk-label-medium {
      background:#f97316;
      color:#fff;
      border-radius:999px;
      padding:2px 8px;
      font-size:0.7rem;
      font-weight:600;
      margin-left:6px;
    }

    @media (max-width:1100px) {
      .app-shell { grid-template-columns:220px 1fr; }
      .content-grid { grid-template-columns:1fr; }
    }
    @media (max-width:800px) {
      .app-shell { grid-template-columns:1fr; }
      .sidebar { display:none; }
      .main { padding:16px; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
        <span>EduSphere</span>
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php" class="active"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="sidebar-teacher-card">
        <img src="<?= htmlspecialchars($teacher_avatar) ?>" alt="Teacher" />
        <div>
          <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
          <div class="role">Teacher · EduSphere</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-inner">
      <header class="page-header">
        <div>
          <div class="page-title-line">
            <div class="page-title-icon"><i class="fas fa-book-open"></i></div>
            <div>
              <h2>Grade Book</h2>
              <p>Record subject-wise marks for terminals, unit tests, and assignments.</p>
            </div>
          </div>
        </div>
        <div class="page-header-clipart">
          <span class="page-header-chip">
            Total records: <strong><?= $gradeStats['total'] ?></strong>
            · Exams: <strong><?= $examPercent ?>%</strong>
            <?php if ($overallAvg !== null): ?>
              · Avg score: <strong><?= $overallAvg ?>%</strong>
            <?php endif; ?>
          </span>
          <img src="../../assets/img/illustrations/gradebook-illustration.png"
               alt="Grade Illustration" onerror="this.style.display='none';" />
        </div>
      </header>

      <section class="content-grid">
        <!-- LEFT COLUMN: FORM + AT-RISK HIGHLIGHT -->
        <div class="left-column">
          <div class="card">
            <div id="message" class="message"></div>
            <h3 class="card-title">Add / Edit Grade</h3>
            <p class="card-sub">
              Pick class & student, then log marks for a subject and exam/test.
            </p>

            <form id="gradeForm" class="grade-form">
              <input type="hidden" id="grade-id" name="id" />

              <label for="class_select">Class</label>
              <select id="class_select" name="class_select" required>
                <option value="">Select class</option>
                <?php foreach ($classes as $cls): ?>
                  <option value="<?= htmlspecialchars($cls) ?>">
                    Class <?= htmlspecialchars($cls) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <label for="student_id">Student</label>
              <select id="student_id" name="student_id" required>
                <option value="">Select student</option>
              </select>
              <small style="display:block;margin-bottom:8px;font-size:0.78rem;color:#6b7280;">
                Start with class, then choose the student.
              </small>

              <label for="subject">Subject</label>
              <select id="subject" name="subject">
                <option value="">Select subject</option>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
              </select>

              <label for="exam_type">Exam / Test</label>
              <select id="exam_type" name="exam_type">
                <option value="">Select exam / test</option>
                <?php foreach ($examTypes as $et): ?>
                  <option value="<?= htmlspecialchars($et) ?>"><?= htmlspecialchars($et) ?></option>
                <?php endforeach; ?>
              </select>

              <label for="category">Category</label>
              <select id="category" name="category" required>
                <option value="">Select Category</option>
                <option value="Assignment">Assignment</option>
                <option value="Unit Test">Unit Test</option>
                <option value="Exam">Exam</option>
                <option value="Terminal Exam">Terminal Exam</option>
                <option value="Discipline">Discipline</option>
                <option value="Classroom Activity">Classroom Activity</option>
              </select>

              <label for="title">Title (auto-filled from subject & exam, but editable)</label>
              <input type="text" id="title" name="title" required placeholder="e.g. Mathematics - First Term" />

              <label for="score">Score (obtained / out of)</label>
              <input type="text" id="score" name="score" required placeholder="e.g. 45/50 or 78" />

              <label for="grade">Grade</label>
              <input type="text" id="grade" name="grade" placeholder="e.g. A+, B (optional)" />

              <label for="comments">Comments</label>
              <textarea id="comments" name="comments" rows="3"
                        placeholder="Comments (optional, visible in student profile)"></textarea>

              <button type="submit" id="submitBtn" class="primary-btn">
                <i class="fas fa-plus-circle"></i> <span>Add Grade</span>
              </button>
              <button type="button" id="cancelBtn" class="secondary-btn" style="display:none;">
                <i class="fas fa-times"></i> Cancel
              </button>
            </form>

            <div class="form-illustration">
              <img src="../../assets/img/illustrations/progress-animate.png"
                   alt="Progress Illustration" onerror="this.style.display='none';" />
              <p>Tip: use clear titles like “Math – Second Term” so reports stay readable.</p>
            </div>
          </div>

          <!-- AT-RISK – main highlight of the page -->
          <div class="card risk-card">
            <div class="risk-card-header">
              <h3 class="card-title" style="margin-bottom:4px;">At-Risk Students</h3>
              <span class="risk-badge">
                <i class="fas fa-triangle-exclamation"></i>
                Combined logistic probability & decision rules
              </span>
            </div>
            <p class="card-sub">
              Automatically flags learners whose attendance and performance suggest they need follow-up.
            </p>

            <?php if (empty($atRiskStudents)): ?>
              <p style="font-size:0.86rem;color:var(--text-muted);margin-top:4px;">
                No students flagged as at-risk yet. 🎉
              </p>
            <?php else: ?>
              <div class="risk-chips">
                <?php foreach ($atRiskStudents as $s): ?>
                  <?php
                    $labelClass = $s['rule_label'] === 'High'
                        ? 'risk-label-high'
                        : 'risk-label-medium';
                  ?>
                  <div class="risk-chip">
                    <span class="name">
                      <?= htmlspecialchars($s['name']) ?>
                      <?php if (in_array($s['rule_label'], ['High','Medium'], true)): ?>
                        <span class="<?= $labelClass ?>"><?= htmlspecialchars($s['rule_label']) ?> risk</span>
                      <?php endif; ?>
                    </span>
                    <span class="meta">
                      Attendance:
                      <?= $s['attendance'] !== null ? htmlspecialchars($s['attendance']).'%' : 'N/A' ?>
                      · Avg score:
                      <?= $s['grade'] !== null ? htmlspecialchars($s['grade']).'%' : 'N/A' ?>
                      · Discipline:
                      <?= (int)$s['discipline'] ?>
                      <br/>
                      Model risk probability:
                      <strong><?= htmlspecialchars($s['prob_percent']) ?>%</strong>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT COLUMN: GRADES OVERVIEW -->
        <div class="right-column">
          <div class="card">
            <h3 class="card-title">Grades Overview</h3>
            <p class="card-sub">Search, filter, export, and visualize performance trend.</p>

            <div class="stats-row">
              <div class="stat-chip">
                Total <strong><?= $gradeStats['total'] ?></strong> records
              </div>
              <div class="stat-chip">
                Exams <strong><?= $gradeStats['exam'] ?></strong>
              </div>
              <div class="stat-chip">
                Assignments <strong><?= $gradeStats['assignment'] ?></strong>
              </div>
              <div class="stat-chip">
                Behaviour notes <strong><?= $gradeStats['discipline'] ?></strong>
              </div>
              <?php if ($overallAvg !== null): ?>
                <div class="stat-chip">
                  Average score <strong><?= $overallAvg ?>%</strong>
                </div>
              <?php endif; ?>
            </div>

            <!-- Line chart -->
            <canvas id="gradeTrendChart"></canvas>

            <div class="grade-toolbar">
              <input type="text" id="searchGrades" placeholder="Search by student, title, or subject..." />
              <select id="filterCategory">
                <option value="">All Categories</option>
                <option value="Assignment">Assignment</option>
                <option value="Unit Test">Unit Test</option>
                <option value="Exam">Exam</option>
                <option value="Terminal Exam">Terminal Exam</option>
                <option value="Discipline">Discipline</option>
                <option value="Classroom Activity">Classroom Activity</option>
              </select>
              <button type="button" id="exportCsvBtn" class="ghost-btn">
                <i class="fas fa-download"></i> Export CSV
              </button>
            </div>

            <table class="gradebook-table" id="gradesTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Class</th>
                  <th>Student</th>
                  <th>Category</th>
                  <th>Title</th>
                  <th>Score</th>
                  <th>Grade</th>
                  <th>Comments</th>
                  <th>Date Added</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<script>
  const msgBox        = document.getElementById('message');
  const gradeForm     = document.getElementById('gradeForm');
  const submitBtn     = document.getElementById('submitBtn');
  const cancelBtn     = document.getElementById('cancelBtn');
  const tbody         = document.querySelector('#gradesTable tbody');
  const searchInput   = document.getElementById('searchGrades');
  const filterCategory= document.getElementById('filterCategory');
  const exportBtn     = document.getElementById('exportCsvBtn');

  const classSelect   = document.getElementById('class_select');
  const studentSelect = document.getElementById('student_id');
  const subjectSelect = document.getElementById('subject');
  const examTypeSelect= document.getElementById('exam_type');
  const titleInput    = document.getElementById('title');

  let editingId = null;

  // Class -> student mapping from PHP
  const classStudents = <?= json_encode($classStudentsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  function showMessage(text, type='success') {
    msgBox.textContent = text;
    msgBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
    msgBox.style.display = 'block';
    setTimeout(() => msgBox.style.display = 'none', 4000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[m]);
  }

  function parseScorePercent(score) {
    if (!score) return null;
    const s = String(score).trim();
    if (!s) return null;
    const m = s.match(/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/);
    if (!m) return null;
    const obt = parseFloat(m[1]);
    if (m[2]) {
      const max = parseFloat(m[2]);
      if (!max || max <= 0) return null;
      return Math.round((obt / max) * 1000) / 10; // 1 decimal
    }
    return Math.round(obt * 10) / 10;
  }

  function clearForm() {
    editingId = null;
    gradeForm.reset();
    document.getElementById('grade-id').value = '';
    submitBtn.innerHTML = '<i class="fas fa-plus-circle"></i> <span>Add Grade</span>';
    cancelBtn.style.display = 'none';
    if (studentSelect) {
      studentSelect.innerHTML = '<option value="">Select student</option>';
    }
  }

  function getCategoryChipClass(category) {
    if (!category) return '';
    const key = category.toLowerCase().replace(/\s+/g, '-');
    return 'chip-' + key;
  }

  function populateStudentsForClass(classKey, selectedStudentId = null) {
    if (!studentSelect) return;
    studentSelect.innerHTML = '<option value="">Select student</option>';

    if (!classKey || !classStudents[classKey]) return;

    classStudents[classKey].forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.name;
      if (selectedStudentId && String(s.id) === String(selectedStudentId)) {
        opt.selected = true;
      }
      studentSelect.appendChild(opt);
    });
  }

  if (classSelect) {
    classSelect.addEventListener('change', function () {
      populateStudentsForClass(this.value, null);
    });
  }

  function updateTitleFromSubjectExam() {
    if (!titleInput) return;
    const subj = subjectSelect ? subjectSelect.value : '';
    const exam = examTypeSelect ? examTypeSelect.value : '';
    if (!subj && !exam) return;
    if (!editingId && !titleInput.value) {
      titleInput.value = subj && exam ? (subj + ' - ' + exam)
                        : subj || exam;
    }
  }

  if (subjectSelect) subjectSelect.addEventListener('change', updateTitleFromSubjectExam);
  if (examTypeSelect) examTypeSelect.addEventListener('change', updateTitleFromSubjectExam);

  function applyGradeFilters() {
    const term = (searchInput.value || '').toLowerCase();
    const cat  = filterCategory.value;

    document.querySelectorAll('#gradesTable tbody tr').forEach(tr => {
      const student  = tr.children[2].textContent.toLowerCase();
      const category = tr.children[3].textContent.trim();
      const title    = tr.children[4].textContent.toLowerCase();
      const score    = tr.children[5].textContent.toLowerCase();

      const matchTerm = !term || student.includes(term) || title.includes(term) || score.includes(term);
      const matchCat  = !cat || category === cat;

      tr.style.display = (matchTerm && matchCat) ? '' : 'none';
    });
  }

  function loadGrades() {
    fetch('?action=list')
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          showMessage('Failed to load grades.', 'error');
          return;
        }
        tbody.innerHTML = '';
        if (!data.data || data.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="10">No grades found.</td></tr>';
          return;
        }
        data.data.forEach(g => {
          const tr = document.createElement('tr');
          const chipClass = getCategoryChipClass(g.category);
          const percent   = parseScorePercent(g.score);
          let percentText = '—';
          let badgeClass  = '';
          if (percent !== null && !isNaN(percent)) {
            percentText = percent + '%';
            if (percent >= 80) badgeClass = 'badge-good';
            else if (percent >= 50) badgeClass = 'badge-mid';
            else badgeClass = 'badge-low';
          }

          tr.innerHTML = `
            <td>${g.id}</td>
            <td>${escapeHtml(g.class_name || '')}</td>
            <td>${escapeHtml(g.student_name || ('ID ' + g.student_id))}</td>
            <td><span class="chip ${chipClass}">${escapeHtml(g.category)}</span></td>
            <td>${escapeHtml(g.title)}</td>
            <td>
              ${escapeHtml(g.score)}
              <div class="percent-pill ${badgeClass}">${percentText}</div>
            </td>
            <td>${escapeHtml(g.grade || '')}</td>
            <td>${escapeHtml(g.comments || '')}</td>
            <td>${escapeHtml(g.date_added || '')}</td>
            <td>
              <button class="btn-edit" data-id="${g.id}">Edit</button>
              <button class="btn-delete" data-id="${g.id}">Delete</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
        attachRowHandlers();
        applyGradeFilters();
      })
      .catch(() => showMessage('Error loading grades.', 'error'));
  }

  function attachRowHandlers() {
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        const id = btn.dataset.id;
        fetch(`?action=get&id=${id}`)
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              showMessage('Failed to load grade.', 'error');
              return;
            }
            editingId = id;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> <span>Update Grade</span>';
            cancelBtn.style.display = 'inline-flex';
            const g = data.data;

            document.getElementById('grade-id').value = g.id;
            document.getElementById('category').value = g.category;
            document.getElementById('title').value    = g.title;
            document.getElementById('score').value    = g.score;
            document.getElementById('grade').value    = g.grade || '';
            document.getElementById('comments').value = g.comments || '';

            // Restore class + student
            if (classSelect) {
              if (g.class_name) {
                classSelect.value = g.class_name;
                populateStudentsForClass(g.class_name, g.student_id);
              } else {
                classSelect.value = '';
                populateStudentsForClass('', null);
                studentSelect.value = g.student_id;
              }
            }

            // Best-effort parse subject/exam from title (Subject - Exam)
            if (titleInput && subjectSelect && examTypeSelect && g.title) {
              const parts = g.title.split('-');
              if (parts.length >= 2) {
                const subj = parts[0].trim();
                const exam = parts.slice(1).join('-').trim();
                Array.from(subjectSelect.options).forEach(o => {
                  if (o.value === subj) o.selected = true;
                });
                Array.from(examTypeSelect.options).forEach(o => {
                  if (o.value === exam) o.selected = true;
                });
              }
            }
          });
      };
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        if (!confirm('Delete this grade?')) return;
        const id = btn.dataset.id;
        fetch('?action=delete', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showMessage(data.message || 'Deleted.', 'success');
            loadGrades();
            if (editingId === id) clearForm();
          } else {
            showMessage(data.message || 'Failed to delete.', 'error');
          }
        });
      };
    });
  }

  gradeForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(gradeForm));

    let action = 'create';
    if (editingId) {
      action = 'update';
      formData.append('id', editingId);
    }

    fetch(`?action=${action}`, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showMessage(data.message || 'Saved.', 'success');
        loadGrades();
        clearForm();
      } else {
        showMessage((data.errors && data.errors.join(', ')) || data.message || 'Failed to save.', 'error');
      }
    })
    .catch(() => showMessage('Error saving grade.', 'error'));
  });

  cancelBtn.addEventListener('click', clearForm);
  searchInput.addEventListener('input', applyGradeFilters);
  filterCategory.addEventListener('change', applyGradeFilters);

  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const rows = Array.from(document.querySelectorAll('#gradesTable tbody tr'))
        .filter(tr => tr.style.display !== 'none'); // only visible (filtered) rows
      if (!rows.length) {
        showMessage('No rows to export.', 'error');
        return;
      }
      const headers = ['ID','Class','Student','Category','Title','Score','Percent','Grade','Comments','Date Added'];
      const csv = [headers.join(',')];

      rows.forEach(tr => {
        const cells = tr.children;
        const id      = cells[0].innerText.trim();
        const cls     = cells[1].innerText.trim();
        const student = cells[2].innerText.trim();
        const cat     = cells[3].innerText.trim();
        const title   = cells[4].innerText.trim();
        const score   = cells[5].childNodes[0].textContent.trim();
        const percent = cells[5].querySelector('.percent-pill').innerText.trim();
        const grade   = cells[6].innerText.trim();
        const comments= cells[7].innerText.trim();
        const date    = cells[8].innerText.trim();

        const row = [id,cls,student,cat,title,score,percent,grade,comments,date]
          .map(v => `"${v.replace(/"/g,'""')}"`)
          .join(',');
        csv.push(row);
      });

      const blob = new Blob([csv.join('\n')], {type:'text/csv;charset=utf-8;'});
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href = url;
      a.download = 'grades-export.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });
  }

  loadGrades();

  // --------- Chart.js: average score over time ----------
  (function() {
    const trendData = <?= json_encode($gradeTrend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!trendData || !trendData.length) return;

    const labels = trendData.map(item => item.date);
    const values = trendData.map(item => item.avg);

    const canvas = document.getElementById('gradeTrendChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Average Score %',
          data: values,
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return ctx.parsed.y + '%';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              stepSize: 10,
              callback: value => value + '%'
            }
          }
        }
      }
    });
  })();
</script>
</body>
</html>
