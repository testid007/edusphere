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
    public function getClassSchedule($classId) {
        $stmt = $this->conn->prepare("
            SELECT se.class, se.day, ts.period_name, ts.start_time, ts.end_time, ss.name AS subject, CONCAT(u.first_name, ' ', u.last_name) AS teacher
            FROM schedule_entries se
            JOIN schedule_time_slots ts ON se.time_slot_id = ts.id
            LEFT JOIN schedule_subjects ss ON se.subject_id = ss.id
            LEFT JOIN users u ON se.user_id = u.id
            WHERE se.class = ?
            ORDER BY FIELD(se.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), ts.start_time
        ");
        $stmt->execute([$classId]);
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
        $conn = $this->conn; // Use the existing PDO connection

        // Start transaction
        $conn->beginTransaction();

        try {
            // Remove old assignments for this teacher
            $stmt = $conn->prepare("DELETE FROM schedule_teacher_subjects WHERE user_id = ?");
            $stmt->execute([$teacherId]);

            // Insert new assignments
            $stmt = $conn->prepare("INSERT INTO schedule_teacher_subjects (user_id, subject_id) VALUES (?, ?)");
            foreach ($subjectIds as $subjectId) {
                print( $stmt->execute([$teacherId, $subjectId]) ? "Success++++" : "Failed******" );
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("AssignSubjectsToTeacher error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Get subjects for a specific grade
     */
    public function getSubjectsForGrade($grade) {
        // Get subjects for the grade
        $stmt = $this->conn->prepare("SELECT id, name FROM schedule_subjects WHERE 
            (CAST(SUBSTRING_INDEX(grade_range, '-', 1) AS UNSIGNED) <= ? AND 
             CAST(SUBSTRING_INDEX(grade_range, '-', -1) AS UNSIGNED) >= ?)
            ORDER BY name");
        $stmt->execute([$grade, $grade]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach teachers for each subject
        foreach ($subjects as &$subject) {
            $stmt2 = $this->conn->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name FROM schedule_teacher_subjects sts JOIN users u ON sts.user_id = u.id WHERE sts.subject_id = ?");
            $stmt2->execute([$subject['id']]);
            $subject['teachers'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        return $subjects;
    }

    /**
     * ✅ Get available time slots for a specific grade and day
     */
    public function getAvailableTimeSlots($grade, $day) {
        // Get all slots
        $all = $this->conn->query("SELECT id, period_name, start_time, end_time FROM schedule_time_slots")->fetchAll(PDO::FETCH_ASSOC);
        // Get booked slots for this grade/day
        $stmt = $this->conn->prepare("SELECT time_slot_id FROM schedule_entries WHERE class = ? AND day = ?");
        $stmt->execute([$grade, $day]);
        $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Mark booked
        foreach ($all as &$slot) {
            $slot['booked'] = in_array($slot['id'], $booked);
        }
        return $all;
    }
}
?>
