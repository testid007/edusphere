<?php
require_once __DIR__ . '/../../functions/ScheduleManager.php';
$scheduleManager = new ScheduleManager();

// Get class from GET or default to 1
$classId = isset($_GET['class']) ? (int)$_GET['class'] : 1;
$schedule = $scheduleManager->getClassSchedule($classId);

// Get class number from the first row if available
$classNumber = !empty($schedule) ? htmlspecialchars($schedule[0]['class']) : $classId;
?>
<style>
/* Style only the schedule table */
.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
    font-size: 14px;
}

/* Head row for each day */
.table thead.table-primary th {
    background-color: #21860dff;
    color: white;
    text-align: center;
    font-size: 16px;
    padding: 10px;
}

/* Column headers */
.table thead tr:nth-child(2) th {
    background-color: #f8f9fa;
    color: #333;
    text-align: center;
    padding: 8px;
}

/* Table cells */
.table td, .table th {
    border: 1px solid #dee2e6;
    padding: 8px;
    text-align: center;
    vertical-align: middle;
}

/* Alternate row background for better readability */
.table tbody tr:nth-child(even) {
    background-color: #f2f2f2;
}

/* Subject and teacher bold */
.table td:nth-child(3),
.table td:nth-child(4) {
    font-weight: 500;
}

/* Class heading */
h4 {
    margin-bottom: 20px;
    font-weight: 600;
    color: #333;
}
</style>

<?php if (!empty($schedule)): ?>
    <h4>Schedule for Class: <?= $classNumber ?></h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <?php
            $currentDay = null;
            foreach ($schedule as $row):
                if ($currentDay != $row['day']):
                    if ($currentDay !== null) echo '</tbody>';
                    $currentDay = $row['day'];
            ?>
            <thead class="table-primary">
                <tr>
                    <th colspan="4"><?= htmlspecialchars($currentDay) ?></th>
                </tr>
                <tr>
                    <th>Period</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                </tr>
            </thead>
            <tbody>
            <?php endif; ?>
                <tr>
                    <td><?= htmlspecialchars($row['period_name']) ?></td>
                    <td><?= substr($row['start_time'], 0, 5) ?>-<?= substr($row['end_time'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= htmlspecialchars($row['teacher'] ?? 'Not assigned') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">No schedule found for this class.</div>
<?php endif; ?>

