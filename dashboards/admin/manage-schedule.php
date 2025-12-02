<?php
require_once __DIR__ . '/../../functions/ScheduleManager.php';
require_once __DIR__ . '/../components/header.php';

$scheduleManager = new ScheduleManager();
$teachers        = $scheduleManager->getTeachersWithSubjects();
?>

<div class="main-content">
  <div class="container-fluid">
    <h2>Schedule Management</h2>

    <!-- Class / Grade selector + actions -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="grade-select" class="form-label">Class / Grade</label>
            <select id="grade-select" class="form-control">
              <option value="">Select class</option>
              <?php for ($g = 1; $g <= 10; $g++): ?>
                <option value="<?= $g ?>">Grade <?= $g ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="col-md-8 d-flex align-items-end flex-wrap gap-2">
            <button id="btn-auto-generate" type="button" class="btn btn-primary">
              Auto Generate
            </button>
            <button id="btn-load" type="button" class="btn btn-secondary">
              Load Schedule
            </button>
            <div id="status" class="small text-muted ms-3" style="margin-top:10px;"></div>
          </div>
        </div>

        <!-- Where the schedule table will appear -->
        <div id="schedule-display" class="card">
  <div class="card-body">
    <!-- Schedule table loads here -->
  </div>
  <div class="card-footer d-flex justify-content-end gap-2">
      <button id="btn-edit-schedule" class="btn btn-warning">Edit Manually</button>
      <button id="btn-delete-schedule" class="btn btn-danger">Delete Schedule</button>
  </div>
</div>

      </div>
    </div>

    <!-- Teacher capability overview -->
    <div class="card">
      <div class="card-body">
        <h5 class="mb-3">Teacher capability overview</h5>
        <?php if (!empty($teachers)): ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Teacher</th>
                  <th>Subjects</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teachers as $t): ?>
                  <tr>
                    <td><?= htmlspecialchars($t['name']) ?></td>
                    <td><?= htmlspecialchars($t['subjects'] ?? '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">
            No teacher–subject mapping found. Please configure
            <code>schedule_teacher_subjects</code> first for better schedules.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php // NO <script> TAGS HERE ?>
