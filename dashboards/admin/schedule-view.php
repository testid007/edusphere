<?php
// expects $schedule to be an array from ScheduleManager::getClassSchedule($classId)
// and optionally $classNumber set by fetch_schedule.php

if (!isset($schedule) || empty($schedule)) {
    echo '<div class="alert alert-info" style="
        background:#dbeafe;
        border:1px solid #93c5fd;
        color:#1e3a8a;
        padding:10px 14px;
        border-radius:10px;
        font-size:0.9rem;
        margin-bottom:14px;
    ">No schedule found for this class.</div>';
    return;
}

if (!isset($classNumber)) {
    $first = reset($schedule);
    $classNumber = htmlspecialchars($first['class'] ?? '');
}

function normalize_special_label(string $name): string {
    $n = strtolower($name);

    if (str_contains($n, 'short break')) return 'Short Break';
    if (str_contains($n, 'break') && !str_contains($n, 'short')) return 'Break';
    if (str_contains($n, 'lunch')) return 'Lunch Break';
    if (str_contains($n, 'club')) return 'Club Time';

    return ucwords($name);
}
?>

<style>
/* Title */
.schedule-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 16px;
}

/* Table wrapper */
.schedule-table-wrapper {
  border-radius: 16px;
  overflow: hidden;
  background: var(--bg-main);
  border: 1px solid var(--border-soft);
  box-shadow: var(--shadow-card);
}

/* Table */
.schedule-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}

/* Day Header */
.schedule-table thead.schedule-day th {
  background: var(--accent);
  color: #fff;
  text-align: center;
  padding: 12px 6px;
  font-size: 1rem;
  letter-spacing: 0.5px;
}

/* Columns Header */
.schedule-table thead.schedule-columns th {
  background: var(--bg-sidebar);
  color: var(--text-muted);
  border-bottom: 1px solid var(--border-soft);
  padding: 10px;
  font-weight: 600;
  text-align: center;
}

/* Body rows */
.schedule-table td {
  border-bottom: 1px solid var(--border-soft);
  padding: 10px;
  text-align: center;
  color: var(--text-main);
}

/* Alternating row color */
.schedule-table tbody tr:nth-child(even) {
  background: #f9fafb;
}

/* Hover */
.schedule-table tbody tr:hover {
  background: var(--accent-soft);
}

/* Special period (break/lunch) */
.schedule-table .special {
  font-weight: 600;
  color: var(--accent-strong);
}

/* Responsive */
@media (max-width: 768px) {
  .schedule-table td,
  .schedule-table th {
    padding: 8px 4px;
  }
}
</style>

<h4 class="schedule-title">Schedule for Class: <?= htmlspecialchars($classNumber) ?></h4>

<div class="table-responsive schedule-table-wrapper">
  <table class="schedule-table">
    <?php
    $currentDay = null;

    foreach ($schedule as $row):

        $day         = $row['day'];
        $periodName  = $row['period_name'];
        $timeRange   = substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5);
        $isSpecial   = !empty($row['is_special']);
        $specialName = $row['special_name'] ?? '';
        $subjectName = $row['subject'] ?? '';
        $teacherName = $row['teacher'] ?? '';

        if ($isSpecial) {
            $subjectLabel = normalize_special_label($specialName ?: $periodName);
            $teacherLabel = '—';
            $specialClass = 'special';
        } else {
            $subjectLabel = $subjectName !== '' ? $subjectName : '—';
            $teacherLabel = $teacherName !== '' ? $teacherName : 'Not assigned';
            $specialClass = '';
        }

        if ($day !== $currentDay):
            if ($currentDay !== null): ?>
              </tbody>
            <?php endif; ?>

            <thead class="schedule-day">
              <tr>
                <th colspan="4"><?= htmlspecialchars($day) ?></th>
              </tr>
            </thead>

            <thead class="schedule-columns">
              <tr>
                <th>Period</th>
                <th>Time</th>
                <th>Subject</th>
                <th>Teacher</th>
              </tr>
            </thead>

            <tbody>
            <?php
            $currentDay = $day;
        endif;
    ?>

    <tr>
      <td><?= htmlspecialchars($periodName) ?></td>
      <td><?= htmlspecialchars($timeRange) ?></td>
      <td class="<?= $specialClass ?>"><?= htmlspecialchars($subjectLabel) ?></td>
      <td><?= htmlspecialchars($teacherLabel) ?></td>
    </tr>

    <?php endforeach; ?>
    </tbody>
  </table>
</div>
