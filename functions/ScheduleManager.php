<?php
require_once __DIR__ . '/../includes/db.php';

class ScheduleManager {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * ✅ Fetch schedule entries for a class/grade
     */
    public function getClassSchedule($grade) {
        $stmt = $this->conn->prepare("
            SELECT se.id, se.day, ts.period_name, ts.start_time, ts.end_time,
                   IFNULL(ss.name, se.special_name) AS subject,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher,
                   se.user_id AS teacher_id,
                   se.is_special
            FROM schedule_entries se
            JOIN schedule_time_slots ts ON se.time_slot_id = ts.id
            LEFT JOIN schedule_subjects ss ON se.subject_id = ss.id
            LEFT JOIN users u ON se.user_id = u.id
            WHERE se.class = ?
            ORDER BY FIELD(se.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'),
                     ts.start_time
        ");
        $stmt->execute([$grade]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Get all teachers with their assigned subjects (for overview)
     */
    public function getTeachersWithSubjects() {
        $stmt = $this->conn->query("
            SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                   GROUP_CONCAT(ss.name SEPARATOR ', ') AS subjects
            FROM users u
            LEFT JOIN schedule_teacher_subjects sts ON u.id = sts.user_id
            LEFT JOIN schedule_subjects ss ON sts.subject_id = ss.id
            WHERE u.role = 'Teacher'
            GROUP BY u.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Insert or update a schedule entry
     */
    public function saveScheduleEntry($id, $class, $day, $time_slot_id, $subject_id, $user_id, $is_special = 0, $special_name = null) {
        if ($id) {
            // Update existing entry
            $stmt = $this->conn->prepare("
                UPDATE schedule_entries 
                SET class = ?, day = ?, time_slot_id = ?, subject_id = ?, user_id = ?, is_special = ?, special_name = ?
                WHERE id = ?
            ");
            return $stmt->execute([$class, $day, $time_slot_id, $subject_id, $user_id, $is_special, $special_name, $id]);
        } else {
            // Insert new entry
            $stmt = $this->conn->prepare("
                INSERT INTO schedule_entries (class, day, time_slot_id, subject_id, user_id, is_special, special_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$class, $day, $time_slot_id, $subject_id, $user_id, $is_special, $special_name]);
        }
    }

    /**
     * ✅ Delete a schedule entry
     */
    public function deleteScheduleEntry($id) {
        $stmt = $this->conn->prepare("DELETE FROM schedule_entries WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * ✅ Check if a teacher is already booked on a time slot
     */
    public function isTeacherBooked($teacher_id, $day, $time_slot_id, $exclude_entry_id = null) {
        $sql = "SELECT COUNT(*) FROM schedule_entries WHERE user_id = ? AND day = ? AND time_slot_id = ?";
        $params = [$teacher_id, $day, $time_slot_id];

        if ($exclude_entry_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_entry_id;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * ✅ Get subjects allowed for a particular grade
     */
    public function getSubjectsByGrade($grade) {
        $stmt = $this->conn->prepare("
            SELECT * FROM schedule_subjects
            WHERE 
              CAST(SUBSTRING_INDEX(grade_range, '-', 1) AS UNSIGNED) <= ? 
              AND CAST(SUBSTRING_INDEX(grade_range, '-', -1) AS UNSIGNED) >= ?
        ");
        $stmt->execute([$grade, $grade]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Get all time slots (normal or Friday special)
     */
    public function getTimeSlots($fridaySpecial = false) {
        if ($fridaySpecial) {
            $stmt = $this->conn->prepare("SELECT * FROM schedule_time_slots WHERE is_friday_special = 1 ORDER BY start_time");
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM schedule_time_slots WHERE is_friday_special = 0 ORDER BY start_time");
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Get all teachers for dropdown
     */
    public function getAllTeachers() {
        $stmt = $this->conn->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) AS name
            FROM users
            WHERE role = 'Teacher'
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Get all available subjects
     */
    public function getAllSubjects() {
        $stmt = $this->conn->query("SELECT id, name FROM schedule_subjects ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ Assign multiple subjects to a teacher
     * First deletes old assignments and saves the new ones
     */
    public function assignSubjectsToTeacher($teacherId, $subjectIds) {
        // Validate input
        if (!$teacherId || !is_array($subjectIds)) {
            return false;
        }

        // Delete old subject assignments
        $stmt = $this->conn->prepare("DELETE FROM schedule_teacher_subjects WHERE user_id = ?");
        $stmt->execute([$teacherId]);

        // Insert new subject assignments
        $stmt = $this->conn->prepare("INSERT INTO schedule_teacher_subjects (user_id, subject_id) VALUES (?, ?)");

        foreach ($subjectIds as $subjectId) {
            if (!empty($subjectId)) {
                $stmt->execute([$teacherId, $subjectId]);
            }
        }

        return true;
    }
}
?>
