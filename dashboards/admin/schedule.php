<?php
session_start();

// Only admin access
$role = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$admin_id     = (int)($_SESSION['user_id'] ?? 0);
$admin_name   = $_SESSION['admin_name']  ?? 'Main';
$admin_email  = $_SESSION['admin_email'] ?? 'admin@example.com';
$admin_avatar = '../../assets/img/user.jpg';

/* ============================================
   HARDCODED DATA FOR SCHEDULE GENERATION
   ============================================ */

// Subjects by grade level
$subjectsByGrade = [
    'Grade 1'  => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 2'  => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 3'  => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 4'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Studies'],
    'Grade 5'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Studies'],
    'Grade 6'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 7'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 8'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 9'  => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Optional Mathematics', 'Computer Science'],
    'Grade 10' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Optional Mathematics', 'Computer Science']
];

// Teachers with their assigned subjects
$teachers = [
    'Ms. Gurung' => [
        'subjects'    => ['English', 'Moral Education'],
        'max_classes' => 5
    ],
    'Mr. Sharma' => [
        'subjects'    => ['Mathematics', 'Optional Mathematics'],
        'max_classes' => 6
    ],
    'Ms. Bhandari' => [
        'subjects'    => ['Nepali', 'Social Studies'],
        'max_classes' => 5
    ],
    'Mr. Thapa' => [
        'subjects'    => ['Science', 'My Science'],
        'max_classes' => 6
    ],
    'Mr. Rai' => [
        'subjects'    => ['HPE', 'Social Studies'],
        'max_classes' => 4
    ],
    'Ms. Lama' => [
        'subjects'    => ['Computer Studies', 'Computer Science'],
        'max_classes' => 5
    ],
    'Mr. Joshi' => [
        'subjects'    => ['Mathematics', 'Science'],
        'max_classes' => 5
    ]
];

// Class teachers (homeroom teachers)
$classTeachers = [
    'Grade 1'  => 'Ms. Gurung',
    'Grade 2'  => 'Ms. Bhandari',
    'Grade 3'  => 'Mr. Thapa',
    'Grade 4'  => 'Mr. Sharma',
    'Grade 5'  => 'Ms. Lama',
    'Grade 6'  => 'Mr. Joshi',
    'Grade 7'  => 'Mr. Rai',
    'Grade 8'  => 'Ms. Gurung',
    'Grade 9'  => 'Mr. Sharma',
    'Grade 10' => 'Ms. Bhandari'
];

// Time slots structure
$timeSlots = [
    'Regular' => [
        'Period 1'        => '10:00-10:45',
        'Period 2'        => '10:45-11:30',
        'Short Break'     => '11:30-11:35',
        'Period 3'        => '11:35-12:20',
        'Period 4'        => '12:20-13:05',
        'Lunch'           => '13:05-13:35',
        'Period 5'        => '13:35-14:20',
        'Period 6'        => '14:20-15:05',
        '2nd Short Break' => '15:05-15:10',
        'Period 7'        => '15:10-15:55'
    ],
    'Friday' => [
        'Period 1'        => '10:00-10:45',
        'Period 2'        => '10:45-11:30',
        'Short Break'     => '11:30-11:35',
        'Period 3'        => '11:35-12:20',
        'Period 4'        => '12:20-13:05',
        'Lunch'           => '13:05-13:35',
        'Club 1'          => '13:35-14:20',
        'Club 2'          => '14:20-15:05',
        '2nd Short Break' => '15:05-15:10',
        'Club 3'          => '15:10-15:55'
    ]
];

// =============================================
// SCHEDULE GENERATION WITH CONFLICT AVOIDANCE
// =============================================

$schedule = [];
$teacherAssignments = [];
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Init teacher assignments tracker
foreach ($days as $day) {
    $teacherAssignments[$day] = [];
    foreach ($timeSlots['Regular'] as $period => $time) {
        $teacherAssignments[$day][$period] = [];
    }
}

// Helper: find available teacher
function getAvailableTeacher($day, $period, $subjects, &$teacherAssignments) {
    global $teachers;

    foreach ($teachers as $teacher => $data) {
        // already teaching this period?
        if (isset($teacherAssignments[$day][$period][$teacher])) {
            continue;
        }

        $common = array_intersect($data['subjects'], $subjects);
        if (!empty($common)) {
            return $teacher;
        }
    }

    return 'To be assigned';
}

// Generate schedule for each grade
foreach ($subjectsByGrade as $grade => $subjects) {
    foreach ($days as $day) {
        $isFriday     = ($day === 'Friday');
        $timeTemplate = $isFriday ? $timeSlots['Friday'] : $timeSlots['Regular'];

        // First period = homeroom teacher
        $classTeacher = $classTeachers[$grade];
        $firstSubject = $subjects[0];

        $schedule[$grade][$day]['Period 1'] = [
            'subject' => $firstSubject,
            'teacher' => $classTeacher
        ];
        $teacherAssignments[$day]['Period 1'][$classTeacher] = true;

        $periodCount = 1;

        foreach ($timeTemplate as $period => $time) {
            if ($period === 'Period 1') {
                continue;
            }

            // Breaks & lunch
            if (strpos($period, 'Break') !== false || $period === 'Lunch') {
                $schedule[$grade][$day][$period] = [
                    'subject' => strpos($period, 'Break') !== false ? 'Short Break' : 'Lunch Break',
                    'teacher' => ''
                ];
                continue;
            }

            // Friday clubs after Period 4
            if ($isFriday && $periodCount > 4) {
                $schedule[$grade][$day][$period] = [
                    'subject' => 'Club Activity',
                    'teacher' => getAvailableTeacher($day, $period, ['HPE', 'Social Studies', 'Moral Education'], $teacherAssignments)
                ];
                $periodCount++;
                continue;
            }

            $periodCount++;
            if ($periodCount > ($isFriday ? 4 : 7)) {
                continue;
            }

            $subjectIndex = ($periodCount - 2) % count($subjects);
            $subject      = $subjects[$subjectIndex];

            $teacher = getAvailableTeacher($day, $period, [$subject], $teacherAssignments);

            $schedule[$grade][$day][$period] = [
                'subject' => $subject,
                'teacher' => $teacher
            ];

            if (!empty($teacher)) {
                $teacherAssignments[$day][$period][$teacher] = true;
            }
        }
    }
}

// Check if teacher is double-booked same day/period
function isTeacherOverbooked($teacher, $day, $period) {
    global $schedule;
    $count = 0;

    foreach ($schedule as $grade => $days) {
        if (isset($days[$day][$period]['teacher']) &&
            $days[$day][$period]['teacher'] === $teacher) {
            $count++;
            if ($count > 1) return true;
        }
    }
    return false;
}

// Which grade to show
$selectedGrade = (isset($_GET['grade']) && isset($schedule[$_GET['grade']]))
    ? $_GET['grade']
    : 'Grade 1';

// Render table for a grade
function displayGradeSchedule($grade) {
    global $schedule, $timeSlots, $classTeachers;

    if (!isset($schedule[$grade])) {
        echo "<p>No schedule available for {$grade}.</p>";
        return;
    }

    echo '<div class="schedule-grade-block">';
    echo "<h3 class=\"schedule-grade-title\">{$grade} Weekly Schedule</h3>";
    echo "<p>Class Teacher: <strong>" . htmlspecialchars($classTeachers[$grade]) . "</strong></p>";

    foreach ($schedule[$grade] as $day => $periods) {
        $isFriday     = ($day === 'Friday');
        $timeTemplate = $isFriday ? $timeSlots['Friday'] : $timeSlots['Regular'];

        echo "<h4 class=\"schedule-day-title\">{$day}</h4>";
        echo "<table class='schedule-table'>";
        echo "<tr><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th></tr>";

        foreach ($timeTemplate as $periodName => $time) {
            $subject = $periods[$periodName]['subject'] ?? '';
            $teacher = $periods[$periodName]['teacher'] ?? '';

            $conflictClass = '';
            if (!empty($teacher) && isTeacherOverbooked($teacher, $day, $periodName)) {
                $conflictClass = 'conflict';
            }

            $rowClass = '';
            if (strpos($periodName, 'Break') !== false) {
                $rowClass = 'break-cell';
            } elseif ($periodName === 'Lunch') {
                $rowClass = 'lunch-cell';
            }

            echo "<tr class='{$rowClass} {$conflictClass}'>";
            echo "<td>{$periodName}</td>";
            echo "<td>{$time}</td>";
            echo "<td>{$subject}</td>";
            echo "<td class='teacher-name'>{$teacher}</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Schedule View | EduSphere</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<style>
  /* Scoped only to this page content */
  .schedule-wrapper {
    margin-top: 8px;
  }

  .schedule-wrapper .section-header h2 {
    margin-bottom: 4px;
  }
  .schedule-wrapper .section-header p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-muted);
  }

  /* Grade selector bar */
  .schedule-wrapper .grade-selector {
    background: var(--accent-soft);
    padding: 12px 16px;
    border-radius: 16px;
    margin-bottom: 18px;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: space-between;
    flex-wrap: wrap;
    border: 1px solid var(--border-soft);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
  }
  .schedule-wrapper .grade-selector label {
    font-weight: 600;
    color: #92400e;
  }
  .schedule-wrapper .grade-selector select {
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-soft);
    font-size: 0.9rem;
    min-width: 130px;
    background: #ffffff;
    color: var(--text-main);
  }
  .schedule-wrapper .grade-selector button {
    padding: 7px 14px;
    border-radius: 999px;
    border: none;
    background: #111827;
    color: #fff;
    font-size: 0.9rem;
    cursor: pointer;
    font-weight: 600;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.25);
  }
  .schedule-wrapper .grade-selector button:hover {
    background: #020617;
  }

  .schedule-wrapper .schedule-grade-title {
    margin: 0 0 4px;
    font-size: 1.1rem;
  }
  .schedule-wrapper .schedule-day-title {
    margin: 16px 0 6px;
    font-size: 1rem;
    font-weight: 600;
    color: #78350f;
  }

  /* Timetable table styling */
  .schedule-wrapper .schedule-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    font-size: 0.9rem;
    border: 1px solid var(--border-soft);
  }
  .schedule-wrapper .schedule-table th {
    background: linear-gradient(90deg, #f59e0b, #f97316);
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
  }
  .schedule-wrapper .schedule-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-soft);
  }
  .schedule-wrapper .schedule-table tr:nth-child(even) td {
    background-color: #fffaf2;
  }
  .schedule-wrapper .schedule-table tr:hover td {
    background-color: #fff2db;
  }

  /* Break / lunch rows */
  .schedule-wrapper .break-cell td {
    background-color: #fee2e2 !important;
    color: #b91c1c;
    font-weight: 600;
  }
  .schedule-wrapper .lunch-cell td {
    background-color: #fffbeb !important;
    color: #92400e;
    font-weight: 600;
  }

  .schedule-wrapper .teacher-name {
    font-style: italic;
    color: var(--text-muted);
  }

  /* Conflict highlight (teacher double-booked) */
  .schedule-wrapper .conflict td {
    background-color: #fef2f2 !important;
    color: #b91c1c;
    font-weight: 600;
    border-top: 1px solid #fecaca;
    border-bottom: 1px solid #fecaca;
  }

  /* Print button */
  .schedule-wrapper .print-button {
    margin: 4px 0 10px;
    padding: 7px 14px;
    border-radius: 999px;
    border: none;
    background: #111827;
    color: #fff;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.2);
  }
  .schedule-wrapper .print-button:hover {
    background: #020617;
  }

  /* Teacher summary block */
  .schedule-wrapper .teacher-summary {
    margin-top: 18px;
  }
  .schedule-wrapper .teacher-summary h3 {
    margin-bottom: 6px;
    font-size: 1rem;
  }

  @media print {
    .sidebar,
    .main-header,
    .schedule-wrapper .grade-selector,
    .schedule-wrapper .print-button {
      display: none !important;
    }
    body {
      background: #fff;
    }
    .main {
      padding: 0;
    }
  }
</style>

</head>
<body>
<div class="container">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div>
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="EduSphere Logo">
        <span>EduSphere</span>
      </div>

      <nav class="nav">
        <a href="dashboard.php" class="nav-link">
          <i class="fas fa-home"></i> Overview
        </a>
        <a href="manage-users.php" class="nav-link">
          <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="create-fee.php" class="nav-link">
          <i class="fas fa-layer-group"></i> Create Fee
        </a>
        <a href="fees.php" class="nav-link">
          <i class="fas fa-file-invoice-dollar"></i> Fees &amp; Payments
        </a>
        <a href="reports.php" class="nav-link">
          <i class="fas fa-chart-line"></i> View Reports
        </a>
        <a href="schedule.php" class="nav-link active">
          <i class="fas fa-calendar-alt"></i> Schedule View
        </a>
        <a href="manage-events.php" class="nav-link">
          <i class="fas fa-calendar-plus"></i> Manage Events
        </a>
        <a href="../../auth/logout.php" class="nav-link logout">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </nav>

      <div class="sidebar-teacher-card">
        <img src="<?= htmlspecialchars($admin_avatar) ?>" alt="Admin">
        <div>
          <div class="name"><?= htmlspecialchars($admin_name) ?></div>
          <div class="role">Administrator · EduSphere</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="main-header">
      <div class="main-header-left">
        <h2>Schedule View</h2>
        <p>Weekly teacher schedule by grade.</p>
      </div>
      <div class="main-header-right">
        <div class="profile-wrapper">
          <button class="header-avatar" type="button">
            <img src="<?= htmlspecialchars($admin_avatar) ?>" alt="Admin">
            <div>
              <div class="name"><?= htmlspecialchars($admin_name) ?></div>
              <div class="role"><?= htmlspecialchars($admin_email) ?></div>
            </div>
          </button>
        </div>
      </div>
    </div>

    <section class="content schedule-wrapper">
      <div class="section-header">
        <h2>School Teacher Schedule System</h2>
        <p>Auto-generated class-wise timetable with basic conflict checks.</p>
      </div>

      <!-- Grade selector -->
      <div class="grade-selector">
        <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0;">
          <label for="grade">Select Grade:</label>
          <select name="grade" id="grade">
            <?php foreach ($subjectsByGrade as $gradeName => $subjects): ?>
              <option value="<?= htmlspecialchars($gradeName) ?>"
                <?= $selectedGrade === $gradeName ? 'selected' : '' ?>>
                <?= htmlspecialchars($gradeName) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Show Schedule</button>
        </form>
      </div>

      <!-- Grade schedule -->
      <div class="card">
        <?php displayGradeSchedule($selectedGrade); ?>
      </div>

      <button class="print-button" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print / Save as PDF
      </button>

      <!-- Teacher summary -->
      <div class="card teacher-summary">
        <h3>Teacher Assignments Summary</h3>
        <table class="schedule-table">
          <tr>
            <th>Teacher</th>
            <th>Subjects</th>
            <th>Class Teacher For</th>
          </tr>
          <?php foreach ($teachers as $teacher => $data): ?>
            <tr>
              <td><?= htmlspecialchars($teacher) ?></td>
              <td><?= htmlspecialchars(implode(', ', $data['subjects'])) ?></td>
              <td>
                <?php
                $homeroom = array_search($teacher, $classTeachers, true);
                echo $homeroom ? htmlspecialchars($homeroom) : 'N/A';
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
