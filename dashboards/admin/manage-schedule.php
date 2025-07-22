<?php
session_start();
require_once __DIR__.'/../../functions/ScheduleManager.php';

$scheduleManager = new ScheduleManager();
$teachers = $scheduleManager->getTeachersWithSubjects();
$subjects = $scheduleManager->getAllSubjects();
$timeSlots = $scheduleManager->getTimeSlots();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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

        <!-- Schedule Form -->
        <div class="card mb-4">
            <div class="card-header">Add / Edit Schedule Entry</div>
            <div class="card-body">
                <form id="schedule-form" method="post" action="/edusphere/api/save_schedule.php">
                <!-- <form id="schedule-form" method="post" action="#"> -->
                    <input type="hidden" name="id" id="entry-id">
                    <input type="hidden" name="class" id="selected-class" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

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
                                <?php foreach ($timeSlots as $timeSlot): ?>
                                    <option value="<?= $timeSlot['id'] ?>"><?= $timeSlot['period_name'] ?> (<?= $timeSlot['start_time'] . ' - ' . $timeSlot['end_time'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="subject_id">Subject:</label>
                            <select name="subject_id" id="subject_id" class="form-select" required>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                                <?php endforeach; ?>
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

                        <div class="col-md-2" id="special-name-group" style="display:none;">
                            <label for="special_name">Special Name:</label>
                            <input type="text" name="special_name" id="special_name" class="form-control">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Save Entry</button>
                    </div>

                    <div id="form-message" class="mt-2" aria-live="polite"></div>
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
window.addEventListener('DOMContentLoaded', () => {
    console.log("JS Loaded!");

    const form = document.getElementById('schedule-form');
    const formMsg = document.getElementById('form-message');
    const isSpecial = document.getElementById('is_special');
    const specialNameGroup = document.getElementById('special-name-group');
    const subjectSelect = document.getElementById('subject_id');
    const teacherSelect = document.getElementById('user_id');

    // Handle special toggle
    function handleSpecialToggle() {
        if (isSpecial.checked) {
            specialNameGroup.style.display = '';
            subjectSelect.disabled = true;
            teacherSelect.disabled = true;
            subjectSelect.removeAttribute('required');
            teacherSelect.removeAttribute('required');
        } else {
            specialNameGroup.style.display = 'none';
            subjectSelect.disabled = false;
            teacherSelect.disabled = false;
            subjectSelect.setAttribute('required', 'required');
            teacherSelect.setAttribute('required', 'required');
        }
    }

    isSpecial.addEventListener('change', handleSpecialToggle);
    handleSpecialToggle();

    // Grade change
    document.getElementById('grade-select').addEventListener('change', function () {
        const grade = this.value;
        document.getElementById('selected-class').value = grade;

        fetch(`/api/fetch_schedule.php?grade=${grade}`)
            .then(res => res.text())
            .then(html => {
                document.getElementById('schedule-display').innerHTML = html;
            });

        fetch(`/api/fetch_subjects.php?grade=${grade}`)
            .then(res => res.json())
            .then(subjects => {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                subjects.forEach(sub => {
                    const option = document.createElement('option');
                    option.value = sub.id;
                    option.textContent = sub.name;
                    subjectSelect.appendChild(option);
                });
            });

        const day = document.getElementById('day').value;
        fetch(`/api/fetch_timeslots.php?grade=${grade}&day=${day}`)
            .then(res => res.json())
            .then(slots => {
                const slotSelect = document.getElementById('time_slot_id');
                slotSelect.innerHTML = '<option value="">Select Time Slot</option>';
                slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.id;
                    option.textContent = `${slot.period_name} (${slot.start_time} - ${slot.end_time})`;
                    if (slot.booked) option.disabled = true;
                    slotSelect.appendChild(option);
                });
            });
    });

    // Day change
    document.getElementById('day').addEventListener('change', function () {
        const day = this.value;
        const grade = document.getElementById('grade-select').value;
        fetch(`/api/fetch_timeslots.php?grade=${grade}&day=${day}`)
            .then(res => res.json())
            .then(slots => {
                const slotSelect = document.getElementById('time_slot_id');
                slotSelect.innerHTML = '<option value="">Select Time Slot</option>';
                slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.id;
                    option.textContent = `${slot.period_name} (${slot.start_time} - ${slot.end_time})`;
                    if (slot.booked) option.disabled = true;
                    slotSelect.appendChild(option);
                });
            });
    });

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        formMsg.textContent = '';
        formMsg.className = '';

        if (!isSpecial.checked) {
            if (!subjectSelect.value) {
                formMsg.textContent = 'Please select a subject.';
                formMsg.className = 'alert alert-danger';
                return false;
            }
            if (!teacherSelect.value) {
                formMsg.textContent = 'Please select a teacher.';
                formMsg.className = 'alert alert-danger';
                return false;
            }
        } else {
            const specialName = document.getElementById('special_name').value.trim();
            if (!specialName) {
                formMsg.textContent = 'Please enter a special name.';
                formMsg.className = 'alert alert-danger';
                return false;
            }
        }

        const formData = new FormData(form);
        fetch('/edusphere/api/save_schedule.php', {
            method: 'POST',
            body: formData
        })
            .then(async res => {
                let data;
                let text = await res.text();
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    formMsg.textContent = 'Invalid JSON or PHP error: ' + text;
                    formMsg.className = 'alert alert-danger';
                    return;
                }
                console.log(data);
                if (data.success) {
                    window.location.href = '/edusphere/dashboards/admin/dashboard.php';
                    // or use window.history.back(); to go to previous page
                } else {
                    formMsg.textContent = (typeof data === 'object' ? JSON.stringify(data) : (data.error || 'Failed to save entry.'));
                    formMsg.className = 'alert alert-danger';
                }
            })
            .catch((err) => {
                formMsg.textContent = 'Server error: ' + err;
                formMsg.className = 'alert alert-danger';
            });
        return false;
    });
});
</script>
