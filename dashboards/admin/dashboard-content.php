<?php
require_once '../../includes/db.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------
   1) CORE STATS (users, assignments, events, etc.)
-------------------------------------------------- */
$stats = [
    'total_users'      => 0,
    'open_assignments' => 0,
    'upcoming_events'  => 0,
    'timetables'       => 0,
    'overall_att'      => null,
];

$classSnapshot = [];
$classStats    = [];
$newUsersLast3 = 0;
$hasTimetables = false;

try {
    // total users
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = (int)$stmt->fetchColumn();

    // open assignments
    if ($conn->query("SHOW TABLES LIKE 'assignments'")->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT COUNT(*)
            FROM assignments
            WHERE status = 'Open'
        ");
        $stats['open_assignments'] = (int)$stmt->fetchColumn();
    }

    // upcoming events (30 days)
    if ($conn->query("SHOW TABLES LIKE 'events'")->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT COUNT(*)
            FROM events
            WHERE event_date >= CURDATE()
              AND event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $stats['upcoming_events'] = (int)$stmt->fetchColumn();
    }

    // timetables generated
    if ($conn->query("SHOW TABLES LIKE 'generated_schedules'")->rowCount() > 0) {
        $stmt = $conn->query("SELECT COUNT(*) FROM generated_schedules");
        $stats['timetables'] = (int)$stmt->fetchColumn();
    }
    $hasTimetables = $stats['timetables'] > 0;

    // overall attendance (optional)
    if ($conn->query("SHOW TABLES LIKE 'attendance'")->rowCount() > 0) {
        $stmt = $conn->query("SELECT AVG(attendance_rate) FROM attendance");
        $overallAttendance = $stmt->fetchColumn();
        if ($overallAttendance !== null) {
            $stats['overall_att'] = round((float)$overallAttendance, 2);
        }
    }

    // class snapshot (top 3)
    if ($conn->query("SHOW TABLES LIKE 'students'")->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT class, COUNT(*) AS cnt
            FROM students
            GROUP BY class
            ORDER BY cnt DESC, class ASC
            LIMIT 3
        ");
        $classSnapshot = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // new users last 3 days
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM users
        WHERE created_at IS NOT NULL
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $newUsersLast3 = (int)$stmt->fetchColumn();

    // students per class for bar chart
    if ($conn->query("SHOW TABLES LIKE 'students'")->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT class AS class_name, COUNT(*) AS cnt
            FROM students
            GROUP BY class
            ORDER BY class_name ASC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classStats[] = [
                'class_name' => $row['class_name'],
                'count'      => (int)$row['cnt'],
            ];
        }
    }

} catch (Exception $e) {
    // silently fail for now
}

/* -------------------------------------------------
   2) ROLE COUNTS (this powers hero pill + doughnut)
-------------------------------------------------- */
$roleCounts = [
    'Student' => 0,
    'Teacher' => 0,
    'Parent'  => 0,
    'Admin'   => 0,
];

$userRoleStats = [];

try {
    $stmt = $conn->query("
        SELECT role, COUNT(*) AS cnt
        FROM users
        GROUP BY role
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roleRaw = trim((string)$row['role']);   // e.g., "Student"
        $cnt     = (int)$row['cnt'];

        // normalize to the keys we use
        $normalized = ucfirst(strtolower($roleRaw));  // "student" -> "Student"

        if (isset($roleCounts[$normalized])) {
            $roleCounts[$normalized] = $cnt;
        }

        $labelMap = [
            'Student' => 'Students',
            'Teacher' => 'Teachers',
            'Parent'  => 'Parents',
            'Admin'   => 'Admins',
        ];
        $label = $labelMap[$normalized] ?? $normalized;

        $userRoleStats[] = [
            'label' => $label,
            'count' => $cnt,
        ];
    }

} catch (Exception $e) {
    // if this fails, roleCounts stays 0 and chart empty
}

/* -------------------------------------------------
   3) SIMPLE VARIABLES FOR TEMPLATE
-------------------------------------------------- */
$totalUsers     = $stats['total_users'];
$openAssign     = $stats['open_assignments'];
$upcomingEvents = $stats['upcoming_events'];
$timetables     = $stats['timetables'];
$overallAtt     = $stats['overall_att'];

$studentsCount = $roleCounts['Student'];
$teachersCount = $roleCounts['Teacher'];
$parentsCount  = $roleCounts['Parent'];
$adminsCount   = $roleCounts['Admin'];

/* -------------------------------------------------
   4) JSON FOR CHART.JS (SAFELY ESCAPED)
-------------------------------------------------- */
$userRoleJsonRaw   = json_encode($userRoleStats, JSON_UNESCAPED_UNICODE);
$classStatsJsonRaw = json_encode($classStats,   JSON_UNESCAPED_UNICODE);

$userRoleJsonAttr   = htmlspecialchars($userRoleJsonRaw,   ENT_QUOTES, 'UTF-8');
$classStatsJsonAttr = htmlspecialchars($classStatsJsonRaw, ENT_QUOTES, 'UTF-8');
?>

<!-- Hidden JSON blobs for Chart.js -->
<input type="hidden" id="userRoleStatsJson" value="<?php echo $userRoleJsonAttr; ?>">
<input type="hidden" id="classStatsJson" value="<?php echo $classStatsJsonAttr; ?>">

<!-- ========== HERO ========== -->
<div class="hero-card">
  <div class="hero-left">
    <h3>Today’s System Overview</h3>
    <p>Monitor users, schedules, events, fees, and performance insights from one place.</p>

    <div class="hero-metric">
      <span class="big"><?php echo $totalUsers; ?></span>
      <span>active user accounts</span>
    </div>

    <button class="hero-btn" type="button" id="btnQuickManageUsers">
      <i class="fas fa-users-cog"></i> Manage Users
    </button>

    <div class="fs-quick-actions" style="margin-top:10px;">
      <button class="fs-qa-btn" type="button" id="btnQuickCreateFee">
        Create Fee Record
      </button>
      <button class="fs-qa-btn" type="button" id="btnQuickManageSchedule">
        Manage Schedule
      </button>
    </div>
  </div>

  <div class="hero-right">
    <div class="hero-graphic">
      <div class="hero-circle">
        <i class="fas fa-user-shield"></i>
      </div>
      <span>System admin control</span>
    </div>

    <div class="hero-pill">
      <div>
        <strong><?php echo $studentsCount; ?></strong> students ·
        <strong><?php echo $teachersCount; ?></strong> teachers
      </div>
      <div style="font-size:0.8rem;margin-top:4px;">
        <?php if ($overallAtt !== null): ?>
          Overall attendance <strong><?php echo $overallAtt; ?>%</strong>
        <?php else: ?>
          Overall attendance tracking via schedule &amp; attendance modules.
        <?php endif; ?>
      </div>
      <!-- tiny debug line – remove later if you want -->
      <div style="font-size:0.7rem;color:#9ca3af;margin-top:4px;">
        Debug roles → S:<?php echo $studentsCount; ?>,
        T:<?php echo $teachersCount; ?>,
        P:<?php echo $parentsCount; ?>,
        A:<?php echo $adminsCount; ?>
      </div>
    </div>
  </div>
</div>

<!-- ========== TOP STAT CARDS ========== -->
<div class="stats-row" style="margin-top:18px;">
  <div class="stat-card">
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?php echo $totalUsers; ?></div>
    <div class="stat-sub">Students, teachers, parents &amp; admins</div>
  </div>

  <div class="stat-card">
    <div class="stat-label">Open Assignments</div>
    <div class="stat-value"><?php echo $openAssign; ?></div>
    <div class="stat-sub">Awaiting student submissions</div>
  </div>

  <div class="stat-card">
    <div class="stat-label">Upcoming Events</div>
    <div class="stat-value"><?php echo $upcomingEvents; ?></div>
    <div class="stat-sub">Within next 30 days</div>
  </div>

  <div class="stat-card">
    <div class="stat-label">Timetables</div>
    <div class="stat-value"><?php echo $timetables; ?></div>
    <div class="stat-sub">Generated schedule versions</div>
  </div>
</div>

<!-- ========== MAIN GRID ========== -->
<div class="content-grid">
  <!-- LEFT -->
  <div class="left-column">
    <!-- Class Enrollment Snapshot -->
    <div class="panel">
      <div class="panel-header">
        <h4>Class Enrollment Snapshot</h4>
        <span>Top classes by student count</span>
      </div>

      <?php if (!empty($classSnapshot)): ?>
        <ul class="insights-list">
          <?php foreach ($classSnapshot as $row): ?>
            <li>
              <span class="insights-dot"></span>
              <div>
                <strong><?php echo h('Grade ' . $row['class']); ?></strong> –
                <?php echo (int)$row['cnt']; ?> students
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="font-size:0.9rem;color:#6b7280;">
          No student enrolment data found yet. Add students to see class-wise counts.
        </p>
      <?php endif; ?>

      <p style="margin-top:10px;font-size:0.8rem;color:#9ca3af;">
        Derived directly from students table.
      </p>
    </div>

    <!-- User Distribution (Chart) -->
    <div class="panel" style="min-height:260px;">
      <div class="panel-header">
        <h4>User Distribution</h4>
        <span>Students, teachers, parents &amp; admins</span>
      </div>
      <p style="font-size:0.88rem;color:#6b7280;margin-bottom:10px;">
        A user role chart is rendered here using Chart.js.
      </p>
      <div style="width:100%;height:210px;">
        <canvas id="userDistributionChart"></canvas>
      </div>
    </div>

    <!-- Upcoming Events -->
    <div class="panel">
      <div class="panel-header">
        <h4>Upcoming Events</h4>
        <span>From Events module</span>
      </div>
      <ul class="upcoming-list">
        <?php
        try {
            if ($conn->query("SHOW TABLES LIKE 'events'")->rowCount() > 0) {
                $stmt = $conn->query("
                    SELECT title, event_date, location
                    FROM events
                    WHERE event_date >= CURDATE()
                    ORDER BY event_date ASC
                    LIMIT 5
                ");
                if ($stmt->rowCount() === 0): ?>
                  <li class="upcoming-item">
                    <div class="up-meta">No events scheduled yet.</div>
                  </li>
                <?php
                else:
                    while ($ev = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                      <li class="upcoming-item">
                        <div>
                          <div class="up-title"><?php echo h($ev['title']); ?></div>
                          <div class="up-meta">
                            <?php echo date('M d, Y', strtotime($ev['event_date'])); ?>
                            · <?php echo h($ev['location']); ?>
                          </div>
                        </div>
                        <div class="up-time">
                          <?php echo date('D', strtotime($ev['event_date'])); ?>
                        </div>
                      </li>
                <?php
                    endwhile;
                endif;
            } else {
                echo '<li class="upcoming-item"><div class="up-meta">Events table not found.</div></li>';
            }
        } catch (Exception $e) {
            echo '<li class="upcoming-item"><div class="up-meta">Unable to load events.</div></li>';
        }
        ?>
      </ul>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="right-column">
    <!-- System Latest Update Notes -->
    <div class="panel">
      <div class="panel-header">
        <h4>System Latest Update Notes</h4>
        <span>Quick admin alerts</span>
      </div>
      <ul class="alerts-list riskAbsentStudents">
        <li class="unread">
          <span class="alerts-badge"><i class="fas fa-user-plus"></i></span>
          <div class="alerts-text">
            <strong><?php echo $newUsersLast3; ?> new user(s)</strong> registered in the last 3 days.
            <small>Monitor onboarding and correct roles if needed.</small>
          </div>
        </li>
        <li>
          <span class="alerts-badge"><i class="fas fa-tasks"></i></span>
          <div class="alerts-text">
            <strong><?php echo $openAssign; ?> open assignment(s)</strong> currently active.
            <small>Check assignment deadlines and submission load.</small>
          </div>
        </li>
        <li>
          <span class="alerts-badge"><i class="fas fa-calendar-times"></i></span>
          <div class="alerts-text">
            <?php if ($hasTimetables): ?>
              All timetable versions generated. Keep track of future changes.
            <?php else: ?>
              No timetable versions generated yet. Use <strong>Manage Schedule</strong> to create one.
            <?php endif; ?>
            <small>Schedules are required for accurate attendance tracking.</small>
          </div>
        </li>
      </ul>
    </div>

    <!-- Students per Class (Chart) -->
    <div class="panel" style="min-height:260px;">
      <div class="panel-header">
        <h4>Students per Class</h4>
        <span>Visualization hook</span>
      </div>
      <p style="font-size:0.88rem;color:#6b7280;margin-bottom:10px;">
        Class-wise student counts using the <code>students</code> table.
      </p>
      <div style="width:100%;height:210px;">
        <canvas id="studentsPerClassChart"></canvas>
      </div>
    </div>

    <!-- Activity Snapshot -->
    <div class="panel">
      <div class="panel-header">
        <h4>Activity Snapshot</h4>
        <span>Assignments &amp; messages</span>
      </div>
      <ul class="insights-list">
        <li>
          <span class="insights-dot"></span>
          <div>
            <strong><?php echo $openAssign; ?></strong> active assignment submission(s).
          </div>
        </li>
        <li>
          <span class="insights-dot"></span>
          <div>
            <strong>0</strong> teacher–parent messages logged (placeholder).</div>
        </li>
        <li>
          <span class="insights-dot"></span>
          <div>
            Detailed reports are available from the <strong>Reports</strong> and
            <strong>Manage Schedule</strong> sections.
          </div>
        </li>
      </ul>
    </div>
  </div>
</div>
