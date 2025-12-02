<?php
// expects $schedule to be an array from ScheduleManager::getClassSchedule($classId)
// and optionally $classNumber set by fetch_schedule.php

if (!isset($schedule) || empty($schedule)) {
    echo '<div class="alert alert-info">No schedule found for this class.</div>';
    return;
}

// Figure out class label
if (!isset($classNumber)) {
    $first = reset($schedule);
    $classNumber = htmlspecialchars($first['class'] ?? '');
}

/**
 * Normalize label for special rows (Break / Lunch / Club)
 */
function normalize_special_label(string $name): string {
    $n = strtolower($name);

    if (str_contains($n, 'short break')) {
        return 'Short Break';
    }
    if (str_contains($n, 'break') && !str_contains($n, 'short')) {
        return 'Break';
    }
    if (str_contains($n, 'lunch')) {
        return 'Lunch Break';
    }
    if (str_contains($n, 'club')) {
        return 'Club Time';
    }
    // fallback
    return ucwords($name);
}
?>

<style>
.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
    font-size: 14px;
}
.table thead.table-primary th {
    background-color: #21860d;
    color: #fff;
    text-align: center;
    font-size: 16px;
    padding: 10px;
}
.table thead tr:nth-child(2) th {
    background-color: #f8f9fa;
    color: #333;
    text-align: center;
    padding: 8px;
}
.table td, .table th {
    border: 1px solid #dee2e6;
    padding: 8px;
    text-align: center;
    vertical-align: middle;
}
.table tbody tr:nth-child(even) {
    background-color: #f2f2f2;
}
.table td:nth-child(3),
.table td:nth-child(4) {
    font-weight: 500;
}
h4 {
    margin-bottom: 20px;
    font-weight: 600;
    color: #333;
}
</style>

<h4>Schedule for Class: <?= htmlspecialchars($classNumber) ?></h4>

<div class="table-responsive">
  <table class="table table-bordered">
    <?php
    $currentDay = null;
    foreach ($schedule as $row):

        $day          = $row['day'];
        $periodName   = $row['period_name'];
        $timeRange    = substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5);
        $isSpecial    = !empty($row['is_special']);
        $specialName  = $row['special_name'] ?? '';
        $subjectName  = $row['subject'] ?? '';
        $teacherName  = $row['teacher'] ?? '';

        if ($isSpecial) {
            // Use normalized label for special rows
            $subjectLabel = normalize_special_label($specialName ?: $periodName);
            $teacherLabel = '—';
        } else {
            $subjectLabel = $subjectName !== '' ? $subjectName : '—';
            $teacherLabel = $teacherName !== '' ? $teacherName : 'Not assigned';
        }

        if ($day !== $currentDay):
            if ($currentDay !== null): ?>
              </tbody>
            <?php endif; ?>

            <thead class="table-primary">
              <tr>
                <th colspan="4"><?= htmlspecialchars($day) ?></th>
              </tr>
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
          <td><?= htmlspecialchars($subjectLabel) ?></td>
          <td><?= htmlspecialchars($teacherLabel) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
