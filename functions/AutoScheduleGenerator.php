<?php
require_once __DIR__ . '/ScheduleManager.php';

class AutoScheduleGenerator
{
    private ScheduleManager $scheduleManager;
    private array $constraints = [];

    public function __construct(ScheduleManager $scheduleManager)
    {
        $this->scheduleManager = $scheduleManager;
    }

    /**
     * Main entry point.
     *
     * @param string|int $grade  e.g. "1", "2", ..., "10"
     * @param array      $options Optional: ['constraints' => [...], 'generated_by' => userId]
     *
     * @return array ['success' => bool, 'message' => string, 'stats' => [...]]
     */
    public function generateSchedule($grade, array $options = []): array
    {
        $grade = (string) $grade;

        // 1) Load data from DB using your existing manager
        $teachersRaw = $this->scheduleManager->getTeachersWithSubjects();
        $subjects    = $this->scheduleManager->getSubjectsForGrade((int) $grade);
        $timeSlots   = $this->scheduleManager->getTimeSlots(false); // normal (Sun–Thu) slots
          // 🔎 Remove non-teaching "subjects" like Lunch / Break / Club
        $subjects = array_values(array_filter($subjects, function ($s) {
            $n = strtolower($s['name'] ?? '');
            return !(
                str_contains($n, 'break') ||
                str_contains($n, 'lunch') ||
                str_contains($n, 'club')
            );
        }));

        if (empty($subjects)) {
            return [
                'success' => false,
                'message' => "No academic subjects found for class '{$grade}' " .
                             "(schedule_subjects only contains breaks/lunch/club?).",
                'stats'   => []
            ];
        }
        // Normalise teachers: add subjects_list array for quick checks
        $teachers = [];
        foreach ($teachersRaw as $t) {
            $t['subjects_list'] = [];
            if (!empty($t['subjects'])) {
                $t['subjects_list'] = array_map('trim', explode(',', $t['subjects']));
            }
            $teachers[] = $t;
        }

        if (empty($subjects)) {
            return [
                'success' => false,
                'message' => "No subjects found in schedule_subjects for class '{$grade}'.",
                'stats'   => []
            ];
        }

        if (empty($timeSlots)) {
            return [
                'success' => false,
                'message' => 'No time slots defined in schedule_time_slots.',
                'stats'   => []
            ];
        }

        // Days: keep it simple for now (Fri special can be added later)
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

        // 2) Build constraints purely from schedule_subjects (NO extra tables)
        $this->buildConstraints($grade, $subjects, $timeSlots, $days, $options['constraints'] ?? []);

        // 3) Construct schedule
        $result = $this->constructSchedule($days, $timeSlots, $subjects, $teachers);

        if ($result === null) {
            return [
                'success' => false,
                'message' => 'No feasible schedule found with current constraints.',
                'stats'   => []
            ];
        }

        [$schedule, $stats] = $result;

        // 4) Save into DB
        $this->scheduleManager->clearClassSchedule($grade);
        foreach ($schedule as $day => $slotMap) {
            foreach ($slotMap as $slotId => $entry) {
                if ($entry === null) {
                    continue;
                }

                if (!empty($entry['is_special'])) {
                    // Special entry (Break/Lunch/Club)
                    $this->scheduleManager->saveScheduleEntry(
                        null,
                        $grade,
                        $day,
                        (int) $slotId,
                        null,
                        null,
                        1,
                        $entry['special_name'] ?? null
                    );
                } else {
                    // Teaching period
                    $this->scheduleManager->saveScheduleEntry(
                        null,
                        $grade,
                        $day,
                        (int) $slotId,
                        $entry['subject_id'],
                        $entry['teacher_id'], // may be NULL = "Not assigned"
                        0,
                        null
                    );
                }
            }
        }

        return [
            'success' => true,
            'message' => "Schedule generated successfully for class '{$grade}'.",
            'stats'   => $stats
        ];
    }

    /* ============================================================
     *  Constraint Setup (NO separate class_requirements table)
     * ============================================================ */

    private function buildConstraints(string $grade, array $subjects, array $timeSlots, array $days, array $override = []): void
    {
        // Base defaults (you can tweak these numbers)
        $defaults = [
            'max_hours_per_teacher_per_week' => 30,
            'max_periods_per_day'           => 6,
            'max_consecutive_periods'       => 3,
            'default_core_periods'          => 5, // e.g. English/Math/Science etc
            'default_noncore_periods'       => 3, // Computer, HPE, Moral etc
            'required_subjects_per_week'    => [] // filled below
        ];

        $constraints = array_merge($defaults, $override);

        // Build required counts using schedule_subjects (no DB table)
        $required = [];
        foreach ($subjects as $subj) {
            $name     = $subj['name'];
            $isCore   = !empty($subj['is_core']);
            $base     = $isCore ? $constraints['default_core_periods']
                                : $constraints['default_noncore_periods'];

            // Allow override by name if user passes it
            if (isset($constraints['required_subjects_per_week'][$name])) {
                $base = (int) $constraints['required_subjects_per_week'][$name];
            }

            $required[$name] = max(1, (int) $base);
        }

        // Total teachable slots per day (ignore breaks/lunch/club)
        $teachingSlotsPerDay = 0;
        foreach ($timeSlots as $slot) {
            $label = strtolower($slot['period_name']);
            if (
                strpos($label, 'break') !== false ||
                strpos($label, 'lunch') !== false ||
                strpos($label, 'club')  !== false
            ) {
                continue;
            }
            $teachingSlotsPerDay++;
        }

        $totalSlots    = $teachingSlotsPerDay * count($days);
        $totalRequired = array_sum($required);

        // If subject demands > available slots, scale down proportionally
        if ($totalRequired > 0 && $totalRequired > $totalSlots) {
            $scale = $totalSlots / $totalRequired;
            foreach ($required as $name => $count) {
                $required[$name] = max(1, (int) floor($count * $scale));
            }
        }

        $constraints['required_subjects_per_week'] = $required;
        $this->constraints = $constraints;
    }

    /* ============================================================
     *  Core Heuristic Scheduling
     * ============================================================ */

    private function constructSchedule(array $days, array $timeSlots, array $subjects, array $teachers): ?array
    {
        // Final structure: $schedule[day][slotId] = [subject_id, teacher_id, is_special, special_name]
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = [];
            foreach ($timeSlots as $slot) {
                $schedule[$day][(int)$slot['id']] = null;
            }
        }

        // Teacher load tracking
        $teacherWeekLoad    = [];
        $teacherDayLoad     = [];
        $teacherLastTeacher = []; // per day: last teacher assigned in that class
        $teacherConsecutive = [];

        foreach ($teachers as $t) {
            $tid = (int) $t['id'];
            $teacherWeekLoad[$tid] = 0;
            $teacherDayLoad[$tid]  = [];
            $teacherConsecutive[$tid] = [];
            foreach ($days as $d) {
                $teacherDayLoad[$tid][$d]     = 0;
                $teacherConsecutive[$tid][$d] = 0;
            }
        }
        foreach ($days as $d) {
            $teacherLastTeacher[$d] = null;
        }

        // Subject counts for fairness / requirement tracking
        $subjectCount = [];
        $subjectById  = [];
        foreach ($subjects as $s) {
            $sid = (int) $s['id'];
            $subjectCount[$sid] = 0;
            $subjectById[$sid]  = $s;
        }

        // Build list of slots; mark breaks/lunch/club as special, not teachable
        $slotList = [];
        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                $slotId = (int) $slot['id'];
                $name   = $slot['period_name'];
                $label  = strtolower($name);

                if (
                    strpos($label, 'break') !== false ||
                    strpos($label, 'lunch') !== false ||
                    strpos($label, 'club')  !== false
                ) {
                    $schedule[$day][$slotId] = [
                        'subject_id'   => null,
                        'teacher_id'   => null,
                        'is_special'   => 1,
                        'special_name' => $name
                    ];
                    continue;
                }

                $slotList[] = [
                    'day'  => $day,
                    'slot' => $slot
                ];
            }
        }

        $assignedSlots = 0;

        // Go through each teaching slot and decide (subject, teacher)
        foreach ($slotList as $slotInfo) {
            $day    = $slotInfo['day'];
            $slot   = $slotInfo['slot'];
            $slotId = (int) $slot['id'];

            // Heuristic: prefer subjects that are furthest from required weekly count
            $subjectsOrdered = $subjects;
            usort($subjectsOrdered, function ($a, $b) use (&$subjectCount) {
                $sa = $subjectCount[(int)$a['id']] ?? 0;
                $sb = $subjectCount[(int)$b['id']] ?? 0;
                return $sa <=> $sb; // fewer used first
            });

            $chosen     = null;
            $bestScore  = PHP_INT_MAX;

            foreach ($subjectsOrdered as $subject) {
                $subjectId   = (int) $subject['id'];
                $subjectName = $subject['name'];

                // Have we already satisfied weekly count for this subject?
                $required = $this->constraints['required_subjects_per_week'][$subjectName] ?? 0;
                if ($required > 0 && $subjectCount[$subjectId] >= $required) {
                    continue;
                }

                // ---- TRY WITH TEACHERS FIRST ----
                $candidateFound = false;

                foreach ($teachers as $teacher) {
                    $tid = (int) $teacher['id'];

                    if (!$this->canTeacherTeach($teacher, $subjectName)) {
                        continue;
                    }

                    // HARD CONSTRAINTS: only if teacher is assigned (tid)
                    if ($this->scheduleManager->isTeacherBooked($tid, $day, $slotId, null)) {
                        continue;
                    }

                    if ($teacherWeekLoad[$tid] + 1 > $this->constraints['max_hours_per_teacher_per_week']) {
                        continue;
                    }

                    if ($teacherDayLoad[$tid][$day] + 1 > $this->constraints['max_periods_per_day']) {
                        continue;
                    }

                    $isSameAsLast = ($teacherLastTeacher[$day] === $tid);
                    $nextConsec   = $isSameAsLast
                        ? $teacherConsecutive[$tid][$day] + 1
                        : 1;

                    if ($nextConsec > $this->constraints['max_consecutive_periods']) {
                        continue;
                    }

                    // SOFT SCORE: lower is better
                    $score = 0;

                    // Encourage subjects that still have many periods remaining
                    $remaining = max(0, $required - $subjectCount[$subjectId]);
                    $score -= $remaining;

                    // Penalise heavy daily load
                    $score += $teacherDayLoad[$tid][$day];

                    // Slight penalty for consecutive periods
                    if ($isSameAsLast) {
                        $score += 1;
                    }

                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $chosen = [
                            'subject_id' => $subjectId,
                            'teacher_id' => $tid
                        ];
                        $candidateFound = true;
                    }
                }

                // ---- IF NO TEACHER ASSIGNED FOR THIS SUBJECT, STILL PLACE SUBJECT ----
                if (!$candidateFound) {
                    // We place subject with teacher_id = null if weekly requirement not yet met.
                    // This gives you timetable with "Not assigned" teachers that you can fix manually.
                    $score = 0;
                    $remaining = max(0, $required - $subjectCount[$subjectId]);
                    $score -= $remaining;

                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $chosen = [
                            'subject_id' => $subjectId,
                            'teacher_id' => null // <-- important
                        ];
                    }
                }
            }

            if ($chosen !== null) {
                $sid = $chosen['subject_id'];
                $tid = $chosen['teacher_id']; // may be null

                $schedule[$day][$slotId] = [
                    'subject_id'   => $sid,
                    'teacher_id'   => $tid,
                    'is_special'   => 0,
                    'special_name' => null
                ];

                $subjectCount[$sid]++;
                $assignedSlots++;

                // Only track load if teacher is real (not NULL)
                if ($tid !== null) {
                    if (!isset($teacherWeekLoad[$tid])) {
                        $teacherWeekLoad[$tid] = 0;
                    }
                    if (!isset($teacherDayLoad[$tid][$day])) {
                        $teacherDayLoad[$tid][$day] = 0;
                    }

                    $teacherWeekLoad[$tid]++;
                    $teacherDayLoad[$tid][$day]++;

                    if ($teacherLastTeacher[$day] === $tid) {
                        $teacherConsecutive[$tid][$day]++;
                    } else {
                        $teacherLastTeacher[$day]      = $tid;
                        $teacherConsecutive[$tid][$day] = 1;
                    }
                }
            } else {
                // nothing feasible — leave as empty hole
                $schedule[$day][$slotId] = null;
            }
        }

        $stats = [
            'assigned_slots' => $assignedSlots,
            'total_slots'    => count($slotList),
            'subject_counts' => $subjectCount
        ];

        return [$schedule, $stats];
    }

    /* ============================================================
     *  Helper: check if teacher can teach subject
     * ============================================================ */

    private function canTeacherTeach(array $teacher, string $subjectName): bool
    {
        if (empty($teacher['subjects_list'])) {
            return false;
        }
        foreach ($teacher['subjects_list'] as $name) {
            if (strcasecmp(trim($name), trim($subjectName)) === 0) {
                return true;
            }
        }
        return false;
    }
}
