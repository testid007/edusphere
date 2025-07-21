<?php
require_once __DIR__ . '/../includes/db.php';

class ScheduleManager {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    // Fetch schedule for a class
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

    // Get all teachers with their assigned subjects
    public function getTeachersWithSubjects() {
        $stmt = $this->conn->query("
            SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                   GROUP_CONCAT(ss.name SEPARATOR ', ') AS subjects
            FROM users u
            JOIN schedule_teacher_subjects sts ON u.id = sts.user_id
            JOIN schedule_subjects ss ON sts.subject_id = ss.id
            WHERE u.role = 'Teacher'
            GROUP BY u.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insert or update a schedule entry
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

    // Delete a schedule entry by ID
    public function deleteScheduleEntry($id) {
        $stmt = $this->conn->prepare("DELETE FROM schedule_entries WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Check if teacher is double-booked for a day/time_slot
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

    // Fetch all subjects for a grade range (e.g., "1-3")
    public function getSubjectsByGrade($grade) {
        $stmt = $this->conn->prepare("
            SELECT * FROM schedule_subjects
            WHERE FIND_IN_SET(?, grade_range) OR grade_range LIKE CONCAT(?, '-%') OR grade_range LIKE CONCAT('%-', ?)
        ");
        // This query might need tweaking depending on your grade_range format
        $stmt->execute([$grade, $grade, $grade]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch time slots (optionally for Friday special)
    public function getTimeSlots($fridaySpecial = false) {
        if ($fridaySpecial) {
            $stmt = $this->conn->prepare("SELECT * FROM schedule_time_slots WHERE is_friday_special = 1 ORDER BY start_time");
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM schedule_time_slots WHERE is_friday_special = 0 ORDER BY start_time");
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
