<?php
require_once __DIR__ . '/../includes/db.php';

class ScheduleManager {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    /* ============================================================
     *  BASIC FETCHERS
     * ============================================================ */

    /**
     * Get full schedule for a class/grade.
     * Returns rows grouped by day and ordered by time.
     */
    public function getClassSchedule($classId) {
    $stmt = $this->conn->prepare("
        SELECT 
            se.id,
            se.class,
            se.day,
            se.is_special,
            se.special_name,
            ts.period_name,
            ts.start_time,
            ts.end_time,
            IFNULL(ss.name, se.special_name) AS subject,
            CONCAT(u.first_name, ' ', u.last_name) AS teacher,
            se.user_id AS teacher_id
        FROM schedule_entries se
        JOIN schedule_time_slots ts ON se.time_slot_id = ts.id
        LEFT JOIN schedule_subjects ss ON se.subject_id = ss.id
        LEFT JOIN users u ON se.user_id = u.id
        WHERE se.class = ?
        ORDER BY 
          FIELD(se.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'),
          ts.start_time
    ");
    $stmt->execute([$classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Get all subjects (for tools / summaries).
     */
    public function getAllSubjects() {
        $stmt = $this->conn->query("
            SELECT id, name, grade_range, is_core
            FROM schedule_subjects
            ORDER BY name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get subjects that are valid for a particular grade.
     * Uses grade_range like '1-3', '4-10', etc.
     */
    public function getSubjectsForGrade(int $grade) {
        $stmt = $this->conn->prepare("
            SELECT id, name, grade_range, is_core
            FROM schedule_subjects
            WHERE 
                grade_range LIKE '%-%'
                AND ? BETWEEN 
                    CAST(SUBSTRING_INDEX(grade_range, '-', 1) AS UNSIGNED)
                AND CAST(SUBSTRING_INDEX(grade_range, '-', -1) AS UNSIGNED)
            ORDER BY is_core DESC, name
        ");
        $stmt->execute([$grade]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Optional: attach teachers list for each subject (can be ignored by frontend if not needed)
        foreach ($subjects as &$subject) {
            $stmt2 = $this->conn->prepare("
                SELECT 
                    u.id, 
                    CONCAT(u.first_name, ' ', u.last_name) AS name
                FROM schedule_teacher_subjects sts 
                JOIN users u ON sts.user_id = u.id 
                WHERE sts.subject_id = ?
            ");
            $stmt2->execute([$subject['id']]);
            $subject['teachers'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        return $subjects;
    }

    /**
     * Teachers + the subjects they can teach.
     * Uses schedule_teacher_subjects + schedule_subjects.
     */
    public function getTeachersWithSubjects() {
        $stmt = $this->conn->query("
            SELECT 
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                GROUP_CONCAT(ss.name ORDER BY ss.name SEPARATOR ', ') AS subjects
            FROM users u
            LEFT JOIN schedule_teacher_subjects sts ON u.id = sts.user_id
            LEFT JOIN schedule_subjects ss          ON sts.subject_id = ss.id
            WHERE u.role = 'Teacher'
            GROUP BY u.id
            ORDER BY name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All teachers (simple list for dropdowns).
     */
    public function getAllTeachers() {
        $stmt = $this->conn->query("
            SELECT id, CONCAT(first_name, ' ', last_name) AS name
            FROM users
            WHERE role = 'Teacher'
            ORDER BY name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Time slots. If $fridaySpecial = true, return special Friday slots.
     */
    public function getTimeSlots(bool $fridaySpecial = false) {
        if ($fridaySpecial) {
            $stmt = $this->conn->prepare("
                SELECT * 
                FROM schedule_time_slots 
                WHERE is_friday_special = 1 
                ORDER BY start_time
            ");
            $stmt->execute();
        } else {
            $stmt = $this->conn->prepare("
                SELECT * 
                FROM schedule_time_slots 
                WHERE is_friday_special = 0 
                ORDER BY start_time
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     *  AVAILABILITY / CONFLICT CHECKS
     * ============================================================ */

    /**
     * Is teacher already booked in *any* class at a given day & time slot?
     * Used by the scheduler to avoid double-booking across classes.
     */
    public function isTeacherBooked(int $teacher_id, string $day, int $time_slot_id, ?int $exclude_entry_id = null): bool {
        $sql = "
            SELECT COUNT(*) 
            FROM schedule_entries
            WHERE user_id = ? AND day = ? AND time_slot_id = ?
        ";
        $params = [$teacher_id, $day, $time_slot_id];

        if ($exclude_entry_id !== null) {
            $sql .= " AND id <> ? ";
            $params[] = $exclude_entry_id;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * For API: get *all* time slots for a class/day, marking which are booked.
     * Returns slots with 'booked' => true/false (used by JS to disable options).
     */
    public function getAvailableTimeSlots(string $grade, string $day): array {
        $fridaySpecial = ($day === 'Friday');
        $timeSlots = $this->getTimeSlots($fridaySpecial);

        // Time slots already used by this class on this day
        $stmt = $this->conn->prepare("
            SELECT time_slot_id
            FROM schedule_entries
            WHERE class = ? AND day = ?
        ");
        $stmt->execute([$grade, $day]);
        $used = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $used = array_map('intval', $used);

        foreach ($timeSlots as &$slot) {
            $slot['booked'] = in_array((int)$slot['id'], $used, true);
        }
        return $timeSlots;
    }

    /* ============================================================
     *  WRITE HELPERS
     * ============================================================ */

    /**
     * Insert or update a single schedule entry.
     */
    public function saveScheduleEntry(
        ?int $id,
        string $class,
        string $day,
        int $time_slot_id,
        ?int $subject_id,
        ?int $user_id,
        int $is_special = 0,
        ?string $special_name = null
    ): bool {
        if ($id !== null) {
            // Update
            $stmt = $this->conn->prepare("
                UPDATE schedule_entries
                SET class = ?, day = ?, time_slot_id = ?, subject_id = ?, user_id = ?, is_special = ?, special_name = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $class,
                $day,
                $time_slot_id,
                $subject_id,
                $user_id,
                $is_special,
                $special_name,
                $id
            ]);
        } else {
            // Insert
            $stmt = $this->conn->prepare("
                INSERT INTO schedule_entries (class, day, time_slot_id, subject_id, user_id, is_special, special_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $class,
                $day,
                $time_slot_id,
                $subject_id,
                $user_id,
                $is_special,
                $special_name
            ]);
        }
    }

    public function deleteScheduleEntry(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM schedule_entries WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Clear all schedule entries for a class (used before auto-generate).
     */
    public function clearClassSchedule(string $grade): bool {
        $stmt = $this->conn->prepare("DELETE FROM schedule_entries WHERE class = ?");
        return $stmt->execute([$grade]);
    }

    /* ============================================================
     *  SUBJECT ASSIGNMENT
     * ============================================================ */

    /**
     * Assign multiple subjects to a teacher.
     * Completely replaces old assignments for that teacher.
     */
    public function assignSubjectsToTeacher(int $teacherId, array $subjectIds): bool {
        if (!$teacherId || !is_array($subjectIds)) {
            return false;
        }

        $conn = $this->conn;
        try {
            $conn->beginTransaction();

            // Remove old assignments for this teacher
            $stmt = $conn->prepare("DELETE FROM schedule_teacher_subjects WHERE user_id = ?");
            $stmt->execute([$teacherId]);

            // Insert new assignments
            $stmt = $conn->prepare("
                INSERT INTO schedule_teacher_subjects (user_id, subject_id) 
                VALUES (?, ?)
            ");
            foreach ($subjectIds as $subjectId) {
                $subjectId = (int)$subjectId;
                if ($subjectId > 0) {
                    $stmt->execute([$teacherId, $subjectId]);
                }
            }

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            error_log("assignSubjectsToTeacher error: " . $e->getMessage());
            return false;
        }
    }

    /* ============================================================
     *  CLASS TEACHERS
     * ============================================================ */

    /**
     * Get current class teacher mapping (class => teacher info).
     */
    public function getClassTeachers(): array {
        $stmt = $this->conn->query("
            SELECT 
                sct.class,
                u.id AS teacher_id,
                CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
            FROM schedule_class_teachers sct
            JOIN users u ON sct.user_id = u.id
            ORDER BY sct.class
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['class']] = $row;
        }
        return $map;
    }

    /**
     * Assign a class teacher (1 teacher per class).
     */
    public function assignClassTeacher(string $class, int $teacherId): bool {
        if ($class === '' || !$teacherId) return false;

        try {
            // Remove previous assignment for this class
            $stmt = $this->conn->prepare("DELETE FROM schedule_class_teachers WHERE class = ?");
            $stmt->execute([$class]);

            // Insert new one
            $stmt = $this->conn->prepare("
                INSERT INTO schedule_class_teachers (class, user_id)
                VALUES (?, ?)
            ");
            return $stmt->execute([$class, $teacherId]);

        } catch (\Exception $e) {
            error_log("assignClassTeacher error: " . $e->getMessage());
            return false;
        }
    }
        /**
     * Get class teacher user_id for a given class (grade) from schedule_class_teachers.
     * Returns null if not found.
     */
    public function getClassTeacherId(string $class): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT user_id
            FROM schedule_class_teachers
            WHERE class = ?
            LIMIT 1
        ");
        $stmt->execute([$class]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

}
