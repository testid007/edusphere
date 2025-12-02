<?php
require_once __DIR__ . '/../../functions/ScheduleManager.php';
require_once __DIR__ . '/../components/header.php';

$scheduleManager = new ScheduleManager();
$teachers        = $scheduleManager->getTeachersWithSubjects();
?>

<style>
  .main-content {
    padding: 24px 32px;
    background: transparent;
  }

  .schedule-shell {
    max-width: 1100px;
    margin: 0 auto;
  }

  .schedule-page-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--text-main);
  }

  .schedule-page-subtitle {
    margin: 0 0 16px;
    font-size: 0.95rem;
    color: var(--text-muted);
  }

  .schedule-card {
    background: var(--bg-main);
    border-radius: 16px;
    border: 1px solid var(--border-soft);
    box-shadow: var(--shadow-card);
    padding: 18px 20px;
    margin-bottom: 18px;
  }

  .schedule-card + .schedule-card {
    margin-top: 8px;
  }

  /* Labels & form controls */
  .form-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-main);
    margin-bottom: 4px;
  }

  .form-control,
  .form-select {
    border-radius: 10px;
    border: 1px solid var(--border-soft);
    padding: 8px 10px;
    font-size: 0.9rem;
    background-color: #fff;
  }

  .form-control:focus,
  .form-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 1px var(--accent-soft);
  }

  /* Buttons */
  .btn-primary {
    background: var(--accent);
    border-color: var(--accent-strong);
    border-radius: 999px;
    padding-inline: 18px;
    padding-block: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    box-shadow: 0 10px 22px rgba(245, 158, 11, 0.25);
  }

  .btn-primary:hover,
  .btn-primary:focus {
    background: var(--accent-strong);
    border-color: var(--accent-strong);
  }

  .btn-secondary {
    background: #e5e7eb;
    border-color: #d1d5db;
    border-radius: 999px;
    padding-inline: 16px;
    padding-block: 8px;
    font-weight: 500;
    font-size: 0.9rem;
    color: var(--text-main);
  }

  .btn-secondary:hover,
  .btn-secondary:focus {
    background: #d1d5db;
    border-color: #9ca3af;
  }

  .btn-warning {
    border-radius: 999px;
    padding-inline: 16px;
    padding-block: 7px;
    font-weight: 500;
    font-size: 0.9rem;
  }

  .btn-danger {
    border-radius: 999px;
    padding-inline: 16px;
    padding-block: 7px;
    font-weight: 500;
    font-size: 0.9rem;
  }

  /* Status text */
  #status {
    font-size: 0.85rem;
    color: var(--text-soft);
  }

  /* Inner schedule display card */
  #schedule-display {
    background: var(--bg-main);
    border-radius: 14px;
    border: 1px solid var(--border-soft);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    margin-top: 8px;
  }

  #schedule-display .card-body {
    padding: 14px 16px;
  }

  #schedule-display .card-footer {
    border-top: 1px solid #e5e7eb;
    padding: 10px 16px;
    background: var(--bg-sidebar);
  }

  /* Teacher capability table */
  .schedule-card table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .schedule-card thead th {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-soft);
    text-align: left;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-sidebar);
  }

  .schedule-card tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid #f3f4f6;
    color: var(--text-main);
  }

  .schedule-card tbody tr:nth-child(odd) {
    background-color: #f9fafb;
  }

  .schedule-card tbody tr:hover {
    background-color: var(--accent-soft);
  }

  .text-muted {
    color: var(--text-soft) !important;
  }

  @media (max-width: 768px) {
    .main-content {
      padding: 16px;
    }
    .schedule-card {
      padding: 14px 12px;
    }
  }
</style>

<div class="main-content">
  <div class="container-fluid">
    <div class="schedule-shell">

      <h2 class="schedule-page-title">Schedule Management</h2>
      <p class="schedule-page-subtitle">
        Auto-generate or load class-wise timetables and review teacher–subject capabilities.
      </p>

      <!-- Class / Grade selector + actions -->
      <div class="card mb-4 schedule-card">
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
              <div id="status" class="ms-3" style="margin-top:10px;"></div>
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
      <div class="card schedule-card">
        <div class="card-body">
          <h5 class="mb-3" style="margin:0 0 10px;font-size:1rem;">Teacher capability overview</h5>
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
</div>

<?php // NO <script> TAGS HERE ?>
