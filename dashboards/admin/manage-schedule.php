<?php
session_start();
require_once __DIR__ . '/../../functions/ScheduleManager.php';

/*
  manage-schedule.php
  Loaded inside admin/dashboard.php -> #dashboardContent
  So this file should NOT output <html>, <head>, <body>.
*/

// -------------------- Helpers --------------------
function redirect_with_message($url, $type = 'success', $text = '') {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . $url);
    exit;
}

function get_flash_message() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// instantiate manager
$scheduleManager = new ScheduleManager();

// sanitize helper
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

// -------------------- Actions (POST handlers) --------------------
// Using Post-Redirect-Get pattern to avoid resubmits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // simple CSRF check
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_entry') {
        // Manual save (insert or update)
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $data = [
            'class' => (int)($_POST['class'] ?? 1),
            'day' => $_POST['day'] ?? '',
            'time_slot_id' => (int)($_POST['time_slot_id'] ?? 0),
            'subject_id' => !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null,
            'user_id' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
            'is_special' => !empty($_POST['is_special']) ? 1 : 0,
            'special_name' => !empty($_POST['special_name']) ? trim($_POST['special_name']) : null,
        ];

        // validation
        if (
            !$data['class'] || !$data['day'] || !$data['time_slot_id'] ||
            (!$data['is_special'] && (!$data['subject_id'] || !$data['user_id']))
        ) {
            redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Missing required fields for schedule entry.');
        }

        try {
            if ($id) {
                if (method_exists($scheduleManager, 'updateScheduleEntry')) {
                    $scheduleManager->updateScheduleEntry($id, $data);
                    redirect_with_message($_SERVER['REQUEST_URI'], 'success', 'Schedule entry updated.');
                } else {
                    redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Update method not available in ScheduleManager.');
                }
            } else {
                $scheduleManager->saveScheduleEntry($data);
                redirect_with_message($_SERVER['REQUEST_URI'], 'success', 'Schedule entry saved.');
            }
        } catch (Exception $ex) {
            redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Database error: ' . $ex->getMessage());
        }

    } elseif ($action === 'delete_entry') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Invalid entry id to delete.');
        try {
            $scheduleManager->deleteScheduleEntry($id);
            redirect_with_message($_SERVER['REQUEST_URI'], 'success', 'Schedule entry deleted.');
        } catch (Exception $ex) {
            redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Delete failed: ' . $ex->getMessage());
        }

    } elseif ($action === 'generate_auto') {
        // Auto-generation: call integrated AutoScheduleGenerator
        $grade = (int)($_POST['grade_for_generation'] ?? 1);
        $options = [
            'max_hours_per_teacher' => (int)($_POST['max_hours_per_teacher'] ?? 25),
            'max_consecutive_periods' => (int)($_POST['max_consecutive_periods'] ?? 3),
        ];

        // Inline simple auto generator (same as your old one)
        class AutoScheduleGeneratorInline {
            private $scheduleManager;
            private $constraints;
            public function __construct($scheduleManager) { $this->scheduleManager = $scheduleManager; }

            public function generateSchedule($grade, $options = []) {
                $teachersRaw = $this->scheduleManager->getTeachersWithSubjects();
                $subjects = $this->scheduleManager->getAllSubjects();
                $timeSlots = $this->scheduleManager->getTimeSlots();
                $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];

                // normalize
                $teachers = [];
                foreach ($teachersRaw as $t) {
                    $t['subjects_list'] = [];
                    if (!empty($t['subjects'])) {
                        $t['subjects_list'] = array_map('trim', explode(',', $t['subjects']));
                    }
                    if (isset($t['availability'])) {
                        if (is_string($t['availability'])) {
                            $t['availability'] = array_filter(array_map('trim', explode(',', $t['availability'])));
                        } elseif (!is_array($t['availability'])) {
                            $t['availability'] = [];
                        }
                    } else {
                        $t['availability'] = [];
                    }
                    $teachers[] = $t;
                }

                $this->constraints = [
                    'max_hours_per_teacher' => $options['max_hours_per_teacher'] ?? 25,
                    'max_consecutive_periods' => $options['max_consecutive_periods'] ?? 3,
                    'required_subjects_per_week' => [
                        'Mathematics'=>5,'English'=>4,'Science'=>3,'Social Studies'=>3,'Physical Education'=>2
                    ]
                ];

                $schedule = $this->backtracking($days, $timeSlots, $subjects, $teachers);
                if ($schedule === null) {
                    return ['success'=>false,'error'=>'No valid schedule found'];
                }

                // save
                $this->scheduleManager->clearClassSchedule($grade);
                foreach ($schedule as $day=>$slots) {
                    foreach ($slots as $slotId=>$entry) {
                        if (!$entry) continue;
                        $this->scheduleManager->saveScheduleEntry([
                            'class' => $grade,
                            'day' => $day,
                            'time_slot_id' => $slotId,
                            'subject_id' => $entry['subject_id'],
                            'user_id' => $entry['teacher_id'],
                            'is_special' => 0,
                            'special_name' => null
                        ]);
                    }
                }
                return ['success'=>true,'schedule'=>$schedule];
            }

            private function canTeach($teacher, $subject) {
                return in_array($subject['name'], $teacher['subjects_list']);
            }

            private function backtracking($days, $timeSlots, $subjects, $teachers) {
                $schedule = [];
                $teacherWorkload = [];
                $consec = [];
                $subjectCount = [];

                foreach ($teachers as $t) {
                    $teacherWorkload[$t['id']] = 0;
                    $consec[$t['id']] = array_fill_keys($days, 0);
                }
                foreach ($subjects as $s) {
                    $subjectCount[$s['id']] = 0;
                }
                foreach ($days as $d) {
                    $schedule[$d] = [];
                    foreach ($timeSlots as $ts) {
                        $schedule[$d][$ts['id']] = null;
                    }
                }

                $slotList = [];
                foreach ($days as $day) {
                    foreach ($timeSlots as $slot) {
                        if (stripos($slot['period_name'],'lunch') !== false ||
                            stripos($slot['period_name'],'break') !== false) {
                            continue;
                        }
                        $slotList[] = ['day'=>$day,'slot'=>$slot];
                    }
                }

                $self = $this;
                $assign = function($idx) use (&$assign, &$schedule, $slotList, $teachers, $subjects,
                                             &$teacherWorkload, &$consec, &$subjectCount, $self) {
                    if ($idx >= count($slotList)) {
                        return true;
                    }

                    $cur = $slotList[$idx];
                    $day = $cur['day'];
                    $slot = $cur['slot'];

                    usort($subjects, function($a, $b) use (&$subjectCount, $self) {
                        $ra = ($self->constraints['required_subjects_per_week'][$a['name']] ?? PHP_INT_MAX)
                              - $subjectCount[$a['id']];
                        $rb = ($self->constraints['required_subjects_per_week'][$b['name']] ?? PHP_INT_MAX)
                              - $subjectCount[$b['id']];
                        return $rb <=> $ra;
                    });

                    foreach ($subjects as $subject) {
                        $required = $self->constraints['required_subjects_per_week'][$subject['name']] ?? PHP_INT_MAX;
                        if ($subjectCount[$subject['id']] >= $required) {
                            continue;
                        }

                        foreach ($teachers as $teacher) {
                            if (!$self->canTeach($teacher, $subject)) continue;
                            if (!empty($teacher['availability']) &&
                                !in_array($day . '_' . $slot['id'], $teacher['availability'])) continue;
                            if ($teacherWorkload[$teacher['id']] >= $self->constraints['max_hours_per_teacher']) continue;
                            if ($consec[$teacher['id']][$day] >= $self->constraints['max_consecutive_periods']) continue;

                            $schedule[$day][$slot['id']] = [
                                'subject_id'=>$subject['id'],
                                'subject_name'=>$subject['name'],
                                'teacher_id'=>$teacher['id'],
                                'teacher_name'=>$teacher['name'],
                                'time_slot_id'=>$slot['id']
                            ];
                            $teacherWorkload[$teacher['id']]++;
                            $consec[$teacher['id']][$day]++;
                            $subjectCount[$subject['id']]++;

                            if ($assign($idx+1)) {
                                return true;
                            }

                            // backtrack
                            $schedule[$day][$slot['id']] = null;
                            $teacherWorkload[$teacher['id']]--;
                            $consec[$teacher['id']][$day]--;
                            $subjectCount[$subject['id']]--;
                        }
                    }
                    return false;
                };

                return $assign(0) ? $schedule : null;
            }
        }

        $gen = new AutoScheduleGeneratorInline($scheduleManager);
        $res = $gen->generateSchedule($grade, $options);
        if ($res['success']) {
            redirect_with_message($_SERVER['REQUEST_URI'], 'success', 'Auto schedule generated successfully.');
        } else {
            redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Auto-generation failed: ' . ($res['error'] ?? 'unknown'));
        }
    }

    // unknown action
    redirect_with_message($_SERVER['REQUEST_URI'], 'danger', 'Unknown action.');
}

// -------------------- Page render (GET) --------------------
// get common data
$teachers = $scheduleManager->getTeachersWithSubjects();
$subjects = $scheduleManager->getAllSubjects();
$timeSlots = $scheduleManager->getTimeSlots();

// grade selection
$currentGrade = isset($_GET['grade']) ? (int)$_GET['grade'] : 1;

// handle edit prefill
$editEntry = null;
if (!empty($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if (method_exists($scheduleManager, 'getScheduleEntryById')) {
        $editEntry = $scheduleManager->getScheduleEntryById($editId);
    } else {
        $all = $scheduleManager->getClassSchedule($currentGrade);
        foreach ($all as $day => $slots) {
            foreach ($slots as $slotId => $entry) {
                if (!empty($entry['id']) && $entry['id'] == $editId) {
                    $editEntry = $entry;
                    break 2;
                }
            }
        }
    }
}

// schedule entries for display
$scheduleGrid = $scheduleManager->getClassSchedule($currentGrade);
$flash = get_flash_message();

// days
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

// -------------------- HTML fragment (no <html>/<body>) --------------------
?>

<div class="schedule-page">
  <?php if ($flash): ?>
    <div class="schedule-alert schedule-alert-<?= e($flash['type']) ?>">
      <?= e($flash['text']) ?>
    </div>
  <?php endif; ?>

  <!-- Top: Auto generator -->
  <div class="card schedule-card schedule-card-full">
    <h3 class="schedule-title">
      <i class="fas fa-calendar-alt"></i> Automated Schedule Generation
    </h3>
    <p class="schedule-subtitle">
      Generate a full weekly timetable for the selected grade based on teacher availability &amp; constraints.
    </p>

    <form method="post" class="schedule-auto-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="generate_auto">

      <div class="field-group">
        <label>Grade</label>
        <select name="grade_for_generation">
          <?php for ($i=1;$i<=12;$i++): ?>
            <option value="<?= $i ?>" <?= $i===$currentGrade ? 'selected' : '' ?>>Grade <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="field-group">
        <label>Max hours per teacher</label>
        <input type="number" name="max_hours_per_teacher" value="25" min="1" max="60">
      </div>

      <div class="field-group">
        <label>Max consecutive periods</label>
        <input type="number" name="max_consecutive_periods" value="3" min="1" max="6">
      </div>

      <div class="field-group field-group-button">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-magic"></i> Generate
        </button>
      </div>
    </form>

    <p class="schedule-hint">
      Generating will replace the existing schedule for the selected grade.
    </p>
  </div>

  <!-- Bottom: two columns -->
  <div class="schedule-two-col">
    <!-- Manual entry -->
    <div class="card schedule-card schedule-card-left">
      <h3 class="schedule-title">
        <i class="fas fa-edit"></i> Manual Schedule Entry
      </h3>

      <form method="post" class="schedule-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="save_entry">
        <input type="hidden" name="id" value="<?= e($editEntry['id'] ?? '') ?>">

        <div class="field">
          <label>Grade</label>
          <select name="class">
            <?php for ($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= ($i===$currentGrade) ? 'selected' : '' ?>>Grade <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="field">
          <label>Day</label>
          <select name="day">
            <?php foreach ($days as $d): ?>
              <option value="<?= e($d) ?>" <?= (!empty($editEntry) && $editEntry['day']===$d) ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Time Slot</label>
          <select name="time_slot_id">
            <?php foreach ($timeSlots as $ts): ?>
              <option value="<?= e($ts['id']) ?>" <?= (!empty($editEntry) && $editEntry['time_slot_id']==$ts['id']) ? 'selected' : '' ?>>
                <?= e($ts['period_name']) ?> (<?= e($ts['start_time']) ?> - <?= e($ts['end_time']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Subject</label>
          <select name="subject_id">
            <option value="">-- Select subject --</option>
            <?php foreach ($subjects as $sub): ?>
              <option value="<?= e($sub['id']) ?>" <?= (!empty($editEntry) && $editEntry['subject_id']==$sub['id']) ? 'selected' : '' ?>>
                <?= e($sub['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Teacher</label>
          <select name="user_id">
            <option value="">-- Select teacher --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= e($t['id']) ?>" <?= (!empty($editEntry) && $editEntry['user_id']==$t['id']) ? 'selected' : '' ?>>
                <?= e($t['name']) ?><?php if (!empty($t['subjects'])): ?> (<?= e($t['subjects']) ?>)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field field-inline">
          <label class="checkbox-label">
            <input
              type="checkbox"
              name="is_special"
              id="is_special"
              onchange="document.getElementById('special_name_group').style.display=this.checked?'block':'none';"
              <?= (!empty($editEntry) && $editEntry['is_special']) ? 'checked' : '' ?>
            >
            Special event
          </label>
        </div>

        <div class="field" id="special_name_group"
             style="display: <?= (!empty($editEntry) && $editEntry['is_special']) ? 'block' : 'none' ?>;">
          <label>Special name</label>
          <input type="text" name="special_name" value="<?= e($editEntry['special_name'] ?? '') ?>">
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Save Entry</button>
          <?php if (!empty($editEntry)): ?>
            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-secondary">Cancel Edit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Schedule grid -->
    <div class="card schedule-card schedule-card-right">
      <div class="schedule-header-row">
        <h3 class="schedule-title">
          <i class="fas fa-table"></i> Current Schedule – Grade <?= e($currentGrade) ?>
        </h3>
        <form method="get" class="grade-select-form">
          <label>Grade</label>
          <select name="grade" onchange="this.form.submit()">
            <?php for ($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= $i===$currentGrade ? 'selected' : '' ?>>Grade <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>

      <div class="schedule-table-wrapper">
        <table class="schedule-table">
          <thead>
            <tr>
              <th>Time Slot</th>
              <?php foreach ($days as $d): ?><th><?= e($d) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($timeSlots as $ts): ?>
              <tr>
                <td class="slot-cell">
                  <strong><?= e($ts['period_name']) ?></strong><br>
                  <span class="small-muted"><?= e($ts['start_time']) ?> – <?= e($ts['end_time']) ?></span>
                </td>
                <?php foreach ($days as $d): ?>
                  <td>
                    <?php if (!empty($scheduleGrid[$d][$ts['id']])): $entry = $scheduleGrid[$d][$ts['id']]; ?>
                      <?php if (!empty($entry['is_special'])): ?>
                        <div><strong>Special:</strong> <?= e($entry['special_name']) ?></div>
                      <?php else: ?>
                        <div><strong><?= e($entry['subject_name'] ?? 'N/A') ?></strong></div>
                        <div class="small-muted"><?= e($entry['teacher_name'] ?? '') ?></div>
                      <?php endif; ?>

                      <div class="cell-actions">
                        <a href="?grade=<?= $currentGrade ?>&edit_id=<?= e($entry['id']) ?>" class="btn btn-small btn-secondary">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this entry?');">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="delete_entry">
                          <input type="hidden" name="id" value="<?= e($entry['id']) ?>">
                          <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <div class="text-muted">-- empty --</div>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
  /* Wrapper for this page */
  .schedule-page {
    padding: 4px 4px 20px 4px;
  }

  .schedule-title {
    margin: 0 0 6px;
    font-size: 1.05rem;
    color: #111;
  }

  .schedule-subtitle,
  .schedule-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 2px 0 0;
  }

  .schedule-alert {
    margin-bottom: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
  }
  .schedule-alert-success { background: #ecfdf3; color: #166534; border:1px solid #bbf7d0; }
  .schedule-alert-danger  { background: #fef2f2; color: #b91c1c; border:1px solid #fecaca; }

  .schedule-card {
    margin-bottom: 16px;
  }
  .schedule-card-full {
    width: 100%;
  }

  .schedule-auto-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 10px;
    align-items: flex-end;
  }
  .schedule-auto-form .field-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
  }
  .schedule-auto-form label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 3px;
  }

  .schedule-two-col {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
  }
  .schedule-card-left {
    flex: 0 0 320px;
    max-width: 360px;
  }
  .schedule-card-right {
    flex: 1 1 400px;
  }

  .schedule-form .field {
    margin-bottom: 8px;
    display: flex;
    flex-direction: column;
  }
  .schedule-form label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 3px;
  }

  .schedule-page select,
  .schedule-page input[type="text"],
  .schedule-page input[type="number"] {
    border-radius: 6px;
    border: 1px solid #d1d5db;
    padding: 6px 8px;
    font-size: 0.88rem;
  }

  .schedule-page .checkbox-label {
    font-size: 0.85rem;
  }

  .form-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* Buttons (re-use style similar to Manage Events) */
  .schedule-page .btn {
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .schedule-page .btn-primary {
    background: #111;
    color: #fff;
  }
  .schedule-page .btn-secondary {
    background: #e5e7eb;
    color: #111827;
  }
  .schedule-page .btn-danger {
    background: #ef4444;
    color: #fff;
  }
  .schedule-page .btn-small {
    padding: 4px 10px;
    font-size: 0.78rem;
  }

  .schedule-header-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    margin-bottom: 8px;
  }
  .grade-select-form {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
  }
  .grade-select-form label {
    font-weight: 600;
  }
  .grade-select-form select {
    font-size: 0.85rem;
  }

  .schedule-table-wrapper {
    overflow-x: auto;
    margin-top: 6px;
  }
  .schedule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
  }
  .schedule-table th,
  .schedule-table td {
    border: 1px solid #e5e7eb;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
  }
  .schedule-table th {
    background: #f9fafb;
    font-weight: 600;
  }
  .slot-cell {
    min-width: 150px;
  }
  .small-muted {
    color: #6b7280;
    font-size: 0.78rem;
  }

  .cell-actions {
    margin-top: 6px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
  }

  @media (max-width: 900px) {
    .schedule-two-col {
      flex-direction: column;
    }
    .schedule-card-left,
    .schedule-card-right {
      flex: 1 1 auto;
      max-width: 100%;
    }
  }
</style>
