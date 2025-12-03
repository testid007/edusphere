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
     * @param string|int $grade   e.g. "1", "2", ..., "10"
     * @param array      $options Optional: ['constraints' => [...], 'generated_by' => userId]
     *
     * @return array ['success' => bool, 'message' => string, 'stats' => [...]]
     */
    public function generateSchedule($grade, array $options = []): array
    {
        $grade = (string)$grade;

        // 1) Load base data from DB using ScheduleManager
        $teachersRaw = $this->scheduleManager->getTeachersWithSubjects();
        $subjects    = $this->scheduleManager->getSubjectsForGrade((int)$grade);
        $timeSlots   = $this->scheduleManager->getTimeSlots(false); // base daily pattern

        // 🔎 Remove non-teaching "subjects" like Break / Lunch / Club
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
                'message' => "No academic subjects found for class '{$grade}'.",
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

        if (empty($timeSlots)) {
            return [
                'success' => false,
                'message' => 'No time slots defined in schedule_time_slots.',
                'stats'   => []
            ];
        }

        // Days: Sun–Thu + Friday
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // 2) Build constraints from schedule_subjects (no extra tables)
        $this->buildConstraints($grade, $subjects, $timeSlots, $days, $options['constraints'] ?? []);

        // 3) Construct schedule (in-memory)
        $result = $this->constructSchedule($days, $timeSlots, $subjects, $teachers, $grade);

        if ($result === null) {
            return [
                'success' => false,
                'message' => 'No feasible schedule found with current constraints.',
                'stats'   => []
            ];
        }

        [$schedule, $stats] = $result;

        // 4) Save into DB (replace previous schedule for this class)
        $this->scheduleManager->clearClassSchedule($grade);
        foreach ($schedule as $day => $slotMap) {
            foreach ($slotMap as $slotId => $entry) {
                if ($entry === null) {
                    continue;
                }

                if (!empty($entry['is_special'])) {
                    // Special entry (Break / Lunch / Club / Class Teacher Time / Club Time)
                    $this->scheduleManager->saveScheduleEntry(
                        null,
                        $grade,
                        $day,
                        (int)$slotId,
                        null,
                        $entry['teacher_id'] ?? null,
                        1,
                        $entry['special_name'] ?? null
                    );
                } else {
                    // Normal teaching period
                    $this->scheduleManager->saveScheduleEntry(
                        null,
                        $grade,
                        $day,
                        (int)$slotId,
                        $entry['subject_id'],
                        $entry['teacher_id'], // may be NULL → "Not assigned"
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

    private function buildConstraints(
        string $grade,
        array $subjects,
        array $timeSlots,
        array $days,
        array $override = []
    ): void {
        // Base defaults (tweak as you like)
        $defaults = [
            'max_hours_per_teacher_per_week' => 30,
            'max_periods_per_day'           => 6,
            'max_consecutive_periods'       => 3,
            'default_core_periods'          => 5, // e.g. English / Math / Science
            'default_noncore_periods'       => 3, // Computer / HPE / Moral / GK etc.
            'required_subjects_per_week'    => [] // filled below
        ];

        $constraints = array_merge($defaults, $override);

        // Build required counts using schedule_subjects (no separate DB table)
        $required = [];
        foreach ($subjects as $subj) {
            $name   = $subj['name'];
            $isCore = !empty($subj['is_core']);
            $base   = $isCore
                ? $constraints['default_core_periods']
                : $constraints['default_noncore_periods'];

            // Allow override by exact subject name if user passes it
            if (isset($constraints['required_subjects_per_week'][$name])) {
                $base = (int)$constraints['required_subjects_per_week'][$name];
            }

            $required[$name] = max(1, (int)$base);
        }

        // Count teachable slots per day (ignore breaks / lunch / club)
        $teachingSlotsPerDay = 0;
        foreach ($timeSlots as $slot) {
            $label = strtolower($slot['period_name']);
            if (
                str_contains($label, 'break') ||
                str_contains($label, 'lunch') ||
                str_contains($label, 'club')
            ) {
                continue;
            }
            $teachingSlotsPerDay++;
        }

        // Approximate: assume same pattern for all days
        $totalSlots    = $teachingSlotsPerDay * count($days);
        $totalRequired = array_sum($required);

        // If demand > available slots, scale down proportionally
        if ($totalRequired > 0 && $totalRequired > $totalSlots) {
            $scale = $totalSlots / $totalRequired;
            foreach ($required as $name => $count) {
                $required[$name] = max(1, (int)floor($count * $scale));
            }
        }

        $constraints['required_subjects_per_week'] = $required;
        $this->constraints = $constraints;
    }

    /* ============================================================
     *  Core Heuristic Scheduling
     * ============================================================ */

    /**
     * Construct schedule using a greedy heuristic with hard constraints.
     *
     * Adds:
     *  - Friday: only 4 teaching periods, rest → "Club Time"
     *  - Strong preference for class teacher in Period 1 (Sun–Thu)
     */
    private function constructSchedule(
        array $days,
        array $timeSlots,
        array $subjects,
        array $teachers,
        string $grade
    ): ?array {
        // Final structure: $schedule[day][slotId] = [subject_id, teacher_id, is_special, special_name]
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = [];
            foreach ($timeSlots as $slot) {
                $schedule[$day][(int)$slot['id']] = null;
            }
        }

        // Who is class teacher for this class?
        $classTeacherId = $this->scheduleManager->getClassTeacherId($grade);

        // Teacher load tracking
        $teacherWeekLoad    = [];
        $teacherDayLoad     = [];
        $teacherLastTeacher = []; // per day: last teacher that taught this class
        $teacherConsecutive = [];

        foreach ($teachers as $t) {
            $tid = (int)$t['id'];
            $teacherWeekLoad[$tid]    = 0;
            $teacherDayLoad[$tid]     = [];
            $teacherConsecutive[$tid] = [];
            foreach ($days as $d) {
                $teacherDayLoad[$tid][$d]     = 0;
                $teacherConsecutive[$tid][$d] = 0;
            }
        }
        foreach ($days as $d) {
            $teacherLastTeacher[$d] = null;
        }

        // Subject counts used this week
        $subjectCount = [];
        foreach ($subjects as $s) {
            $subjectCount[(int)$s['id']] = 0;
        }

        // Precompute required counts by subject name
        $requiredPerName = $this->constraints['required_subjects_per_week'] ?? [];

        // Build list of *teaching* slots; mark breaks/lunch/club as special.
        // Friday: after 4 teaching periods, remaining periods become "Club Time".
        $slotList = [];
        foreach ($days as $day) {
            $teachingCountForDay = 0;

            foreach ($timeSlots as $slot) {
                $slotId = (int)$slot['id'];
                $name   = $slot['period_name'];
                $label  = strtolower($name);

                $isBreakLike = str_contains($label, 'break') || str_contains($label, 'lunch') || str_contains($label, 'club');
                $isPeriod    = str_contains($label, 'period');

                if ($day === 'Friday') {
                    if ($isBreakLike) {
                        // Break / Lunch rows on Friday
                        $schedule[$day][$slotId] = [
                            'subject_id'   => null,
                            'teacher_id'   => null,
                            'is_special'   => 1,
                            'special_name' => $name
                        ];
                        continue;
                    }

                    if ($isPeriod) {
                        $teachingCountForDay++;
                        if ($teachingCountForDay <= 4) {
                            // First 4 periods = teaching periods
                            $slotList[] = [
                                'day'  => $day,
                                'slot' => $slot
                            ];
                        } else {
                            // Periods after 4th = Club Time rows
                            $schedule[$day][$slotId] = [
                                'subject_id'   => null,
                                'teacher_id'   => null,
                                'is_special'   => 1,
                                'special_name' => 'Club Time'
                            ];
                        }
                        continue;
                    }

                    // Anything else on Friday treat as special
                    $schedule[$day][$slotId] = [
                        'subject_id'   => null,
                        'teacher_id'   => null,
                        'is_special'   => 1,
                        'special_name' => $name
                    ];
                    continue;
                }

                // ===== Sun–Thu =====
                if ($isBreakLike) {
                    $schedule[$day][$slotId] = [
                        'subject_id'   => null,
                        'teacher_id'   => null,
                        'is_special'   => 1,
                        'special_name' => $name
                    ];
                    continue;
                }

                // Teaching period (Sun–Thu)
                $slotList[] = [
                    'day'  => $day,
                    'slot' => $slot
                ];
            }
        }

        $assignedSlots = 0;

        // Main loop: choose (subject, teacher) for each teaching slot
        foreach ($slotList as $slotInfo) {
            $day    = $slotInfo['day'];
            $slot   = $slotInfo['slot'];
            $slotId = (int)$slot['id'];

            $periodLabel = strtolower($slot['period_name']);
            $isFirstPeriodNormalDay =
                ($day !== 'Friday' && str_contains($periodLabel, 'period 1'));

            // Heuristic: sort subjects so those used less appear first
            $subjectsOrdered = $subjects;
            usort($subjectsOrdered, function ($a, $b) use (&$subjectCount) {
                $sa = $subjectCount[(int)$a['id']] ?? 0;
                $sb = $subjectCount[(int)$b['id']] ?? 0;
                return $sa <=> $sb; // fewer used first
            });

            $chosen    = null;
            $bestScore = PHP_INT_MAX;

            foreach ($subjectsOrdered as $subject) {
                $subjectId   = (int)$subject['id'];
                $subjectName = $subject['name'];
                $required    = $requiredPerName[$subjectName] ?? 0;

                // ---- TRY WITH REAL TEACHERS FIRST ----
                $candidateFound = false;

                foreach ($teachers as $teacher) {
                    $tid = (int)$teacher['id'];

                    if (!$this->canTeacherTeach($teacher, $subjectName)) {
                        continue;
                    }

                    // Class-teacher rule: in Period 1 (Sun–Thu), strongly favour class teacher
                    if ($isFirstPeriodNormalDay && $classTeacherId !== null && $tid !== $classTeacherId) {
                        // still allowed, but they will have a worse score than class teacher
                        // no early continue here – we let them compete
                    }

                    // HARD CONSTRAINTS (only if we actually assign a teacher):
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

                    // SOFT SCORE (lower is better)
                    $score = 0;

                    // Encourage subjects that are still below their weekly minimum
                    $remaining = max(0, $required - $subjectCount[$subjectId]);
                    $score -= $remaining;

                    // Penalise heavy daily load
                    $score += $teacherDayLoad[$tid][$day];

                    // Slight penalty for consecutive periods
                    if ($isSameAsLast) {
                        $score += 1;
                    }

                    // Strong bonus if this is Period 1 and this teacher is the class teacher
                    if ($isFirstPeriodNormalDay && $classTeacherId !== null && $tid === $classTeacherId) {
                        $score -= 100;
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

                // ---- IF NO TEACHER AVAILABLE, STILL PLACE THE SUBJECT ----
                if (!$candidateFound) {
                    // Timetable will show "Not assigned" so admin can fix later.
                    $score = 0;
                    $remaining = max(0, $required - $subjectCount[$subjectId]);
                    $score -= $remaining;

                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $chosen = [
                            'subject_id' => $subjectId,
                            'teacher_id' => null
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

                // Update teacher load only if a real teacher was assigned
                if ($tid !== null) {
                    $teacherWeekLoad[$tid]++;
                    $teacherDayLoad[$tid][$day]++;

                    if ($teacherLastTeacher[$day] === $tid) {
                        $teacherConsecutive[$tid][$day]++;
                    } else {
                        $teacherLastTeacher[$day]       = $tid;
                        $teacherConsecutive[$tid][$day] = 1;
                    }
                }
            } else {
                // Nothing feasible: leave as hole (very rare with soft constraints)
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
     *  Helper: can this teacher teach this subject?
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
