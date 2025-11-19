<?php
// api/generate_auto.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../functions/ScheduleManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// CSRF protection — your UI already generates this token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Basic inputs
$grade = isset($_POST['grade_for_generation']) ? (int)$_POST['grade_for_generation'] : 1;
$options = [
    'max_hours_per_teacher' => isset($_POST['max_hours_per_teacher']) ? (int)$_POST['max_hours_per_teacher'] : 25,
    'max_consecutive_periods' => isset($_POST['max_consecutive_periods']) ? (int)$_POST['max_consecutive_periods'] : 3,
];

$mgr = new ScheduleManager();

/**
 * AutoScheduleGenerator implemented here so we don't change other files.
 * It uses ScheduleManager methods already present in your project.
 */
class AutoScheduleGenerator {
    private $scheduleManager;
    private $constraints;

    public function __construct($scheduleManager) {
        $this->scheduleManager = $scheduleManager;
    }

    public function generateSchedule($grade, $options = []) {
        // read data from ScheduleManager
        $teachersRaw = $this->scheduleManager->getTeachersWithSubjects(); // returns array of teachers
        $subjects = $this->scheduleManager->getAllSubjects();            // returns array of subjects
        $timeSlots = $this->scheduleManager->getTimeSlots();            // returns array of timeslots

        // Normalize teachers: split subjects and availability to arrays
        $teachers = [];
        foreach ($teachersRaw as $t) {
            $t['subjects_list'] = [];
            if (!empty($t['subjects'])) {
                $t['subjects_list'] = array_map('trim', explode(',', $t['subjects']));
            }
            // availability may be stored as CSV like "Monday_1,Tuesday_2"
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

        // constraints (you can customize subject weekly requirements or make them an option)
        $this->constraints = [
            'max_hours_per_teacher' => $options['max_hours_per_teacher'] ?? 25,
            'max_consecutive_periods' => $options['max_consecutive_periods'] ?? 3,
            // default weekly requirements: use actual subject names from DB for mapping as needed
            'required_subjects_per_week' => [
                // If a subject in DB uses different names, change these keys to match your subjects table values
                'Mathematics' => 5,
                'English' => 4,
                'Science' => 3,
                'Social Studies' => 3,
                'Physical Education' => 2
            ],
        ];

        // days
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // transform timeslots to keep id & period_name
        $slots = $timeSlots;

        // run backtracking
        $schedule = $this->backtrackingAlgorithm($days, $slots, $subjects, $teachers);

        if ($schedule === null) {
            return ['success' => false, 'error' => 'No valid schedule found with current constraints'];
        }

        // Save to DB (clear first)
        $this->scheduleManager->clearClassSchedule($grade);

        foreach ($schedule as $day => $slotMap) {
            foreach ($slotMap as $slotId => $entry) {
                if ($entry === null) continue;
                // save as schedule entry using the same keys your save method expects
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

        return ['success' => true, 'schedule' => $schedule];
    }

    private function canTeacherTeach($teacher, $subject) {
        if (empty($teacher['subjects_list'])) return false;
        return in_array($subject['name'], $teacher['subjects_list']);
    }

    private function backtrackingAlgorithm($days, $timeSlots, $subjects, $teachers) {
        // initialize schedule grid
        $schedule = [];
        foreach ($days as $d) {
            $schedule[$d] = [];
            foreach ($timeSlots as $ts) $schedule[$d][$ts['id']] = null;
        }

        // trackers
        $teacherWorkload = [];
        $consecutivePeriods = [];
        $subjectCount = [];

        foreach ($teachers as $t) {
            $teacherWorkload[$t['id']] = 0;
            $consecutivePeriods[$t['id']] = array_fill_keys($days, 0);
        }
        foreach ($subjects as $s) $subjectCount[$s['id']] = 0;

        // flatten slots in stable order
        $slotList = [];
        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                // skip lunch/break if period_name includes these words
                if (stripos($slot['period_name'], 'lunch') !== false || stripos($slot['period_name'], 'break') !== false) continue;
                $slotList[] = ['day' => $day, 'slot' => $slot];
            }
        }

        $self = $this;

        // recursive assignment
        $assign = function($index) use (&$assign, &$schedule, $slotList, $teachers, $subjects, &$teacherWorkload, &$consecutivePeriods, &$subjectCount, $self) {
            if ($index >= count($slotList)) return true;

            $cur = $slotList[$index];
            $day = $cur['day'];
            $slot = $cur['slot'];

            // small heuristic: try subjects with largest remaining requirement first
            usort($subjects, function($a, $b) use (&$subjectCount, $self) {
                $reqA = ($self->constraints['required_subjects_per_week'][$a['name']] ?? PHP_INT_MAX) - $subjectCount[$a['id']];
                $reqB = ($self->constraints['required_subjects_per_week'][$b['name']] ?? PHP_INT_MAX) - $subjectCount[$b['id']];
                return $reqB <=> $reqA;
            });

            foreach ($subjects as $subject) {
                $required = $self->constraints['required_subjects_per_week'][$subject['name']] ?? PHP_INT_MAX;
                if ($subjectCount[$subject['id']] >= $required) continue;

                foreach ($teachers as $teacher) {
                    // qualified?
                    if (!$self->canTeacherTeach($teacher, $subject)) continue;

                    // availability check (if teacher has availability entries, require match)
                    if (!empty($teacher['availability']) && !in_array($day . '_' . $slot['id'], $teacher['availability'])) continue;

                    // already assigned? we guard schedule grid so not needed but keep safety
                    if ($schedule[$day][$slot['id']] !== null) continue;

                    // max hours
                    if ($teacherWorkload[$teacher['id']] >= $self->constraints['max_hours_per_teacher']) continue;

                    // consecutive periods constraint
                    if ($consecutivePeriods[$teacher['id']][$day] >= $self->constraints['max_consecutive_periods']) continue;

                    // assign
                    $schedule[$day][$slot['id']] = [
                        'subject_id' => $subject['id'],
                        'subject_name' => $subject['name'],
                        'teacher_id' => $teacher['id'],
                        'teacher_name' => $teacher['name'],
                        'time_slot_id' => $slot['id']
                    ];

                    $teacherWorkload[$teacher['id']]++;
                    $consecutivePeriods[$teacher['id']][$day]++;
                    $subjectCount[$subject['id']]++;

                    // recurse
                    if ($assign($index + 1)) {
                        return true;
                    }

                    // backtrack
                    $schedule[$day][$slot['id']] = null;
                    $teacherWorkload[$teacher['id']]--;
                    $consecutivePeriods[$teacher['id']][$day]--;
                    $subjectCount[$subject['id']]--;
                }
            }

            // no valid assignment for this slot -> backtrack
            return false;
        };

        return $assign(0) ? $schedule : null;
    }
}

// create generator and run
$gen = new AutoScheduleGenerator($mgr);
$res = $gen->generateSchedule($grade, $options);

// return JSON
echo json_encode($res);
exit;
