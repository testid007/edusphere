<?php
require_once __DIR__.'/../../functions/ScheduleManager.php';
// require_once __DIR__.'/../components/header.php';
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

        <!-- Add/Edit Schedule Entry -->
        <div class="card mb-4">
            <div class="card-header">Add / Edit Schedule Entry</div>
            <div class="card-body">
                <form id="schedule-form" method="post" action="/api/save_schedule.php">
                    <input type="hidden" name="id" id="entry-id">

                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="day">Day:</label>
                            <select name="day" id="day" class="form-select" required>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="time_slot_id">Time Slot:</label>
                            <select name="time_slot_id" id="time_slot_id" class="form-select" required>
                                <!-- Load via JS -->
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="subject_id">Subject:</label>
                            <select name="subject_id" id="subject_id" class="form-select">
                                <!-- Load via JS -->
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="user_id">Teacher:</label>
                            <select name="user_id" id="user_id" class="form-select" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>"><?= $teacher['name'] ?> (<?= $teacher['subjects'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label for="is_special">Special:</label>
                            <input type="checkbox" name="is_special" id="is_special" value="1" class="form-check-input">
                        </div>

                        <div class="col-md-2">
                            <label for="special_name">Special Name:</label>
                            <input type="text" name="special_name" id="special_name" class="form-control">
                        </div>
                    </div>

                    <input type="hidden" name="class" id="selected-class" value="1">

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Save Entry</button>
                    </div>
                </form>
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

<!-- JavaScript -->
<script>
document.getElementById('grade-select').addEventListener('change', function() {
    const grade = this.value;
    document.getElementById('selected-class').value = grade;

    // Reload schedule
    fetch(`/api/fetch_schedule.php?grade=${grade}`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('schedule-display').innerHTML = html;
        });

    // Reload subjects
    fetch(`/api/fetch_subjects.php?grade=${grade}`)
        .then(res => res.json())
        .then(subjects => {
            const subjectSelect = document.getElementById('subject_id');
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            subjects.forEach(sub => {
                const option = document.createElement('option');
                option.value = sub.id;
                option.textContent = sub.name;
                subjectSelect.appendChild(option);
            });
        });

    // Reload time slots
    fetch(`/api/fetch_timeslots.php?day=Sunday`)
        .then(res => res.json())
        .then(slots => {
            const slotSelect = document.getElementById('time_slot_id');
            slotSelect.innerHTML = '';
            slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.id;
                option.textContent = slot.period_name + ' (' + slot.start_time + ' - ' + slot.end_time + ')';
                slotSelect.appendChild(option);
            });
        });
});

// Reload time slots when day changes
document.getElementById('day').addEventListener('change', function () {
    const day = this.value;
    fetch(`/api/fetch_timeslots.php?day=${day}`)
        .then(res => res.json())
        .then(slots => {
            const slotSelect = document.getElementById('time_slot_id');
            slotSelect.innerHTML = '';
            slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.id;
                option.textContent = slot.period_name + ' (' + slot.start_time + ' - ' + slot.end_time + ')';
                slotSelect.appendChild(option);
            });
        });
});
</script>

<?php //require_once __DIR__.'/../components/footer.php'; ?>
