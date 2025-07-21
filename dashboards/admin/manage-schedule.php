<?php
require_once __DIR__.'/../../functions/ScheduleManager.php';
require_once __DIR__.'/../components/header.php';
// require_once __DIR__.'/../components/sidebar.php';

$scheduleManager = new ScheduleManager();
$teachers = $scheduleManager->getTeachersWithSubjects();
?>

<div class="main-content">
    <div class="container-fluid">
        <h2>Schedule Management</h2>
        
        <!-- Grade Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <select id="grade-select" class="form-select">
                    <?php for($i=1; $i<=10; $i++): ?>
                        <option value="<?= $i ?>">Grade <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <!-- Schedule Display -->
        <div id="schedule-display" class="card">
            <div class="card-body">
                <?php 
                $sampleSchedule = $scheduleManager->getClassSchedule(1);
                include __DIR__.'/schedule-view.php';
                ?>
            </div>
        </div>
    </div>
</div>

<script>
// AJAX loading of schedules
document.getElementById('grade-select').addEventListener('change', function() {
    fetch(`/api/fetch_schedule.php?grade=${this.value}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('schedule-display').innerHTML = html;
        });
});
</script>

<?php //require_once __DIR__.'/../components/footer.php'; ?>