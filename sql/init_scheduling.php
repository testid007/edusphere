<?php
require_once __DIR__.'/../db.php';


try{
// 1. Insert time slots
$timeSlots = [
    // Regular days (Sun-Thu)
    ['Period 1', '10:00:00', '10:45:00', 0],
    ['Period 2', '10:45:00', '11:30:00', 0],
    ['Short Break', '11:30:00', '11:35:00', 0],
    ['Period 3', '11:35:00', '12:20:00', 0],
    ['Period 4', '12:20:00', '13:05:00', 0],
    ['Lunch', '13:05:00', '13:35:00', 0],
    ['Period 5', '13:35:00', '14:20:00', 0],
    ['Period 6', '14:20:00', '15:05:00', 0],
    ['Short Break', '15:05:00', '15:10:00', 0],
    ['Period 7', '15:10:00', '15:55:00', 0],
    
    // Friday special
    ['Period 1', '10:00:00', '10:45:00', 1],
    ['Period 2', '10:45:00', '11:30:00', 1],
    ['Short Break', '11:30:00', '11:35:00', 1],
    ['Period 3', '11:35:00', '12:20:00', 1],
    ['Period 4', '12:20:00', '13:05:00', 1],
    ['Lunch', '13:05:00', '13:35:00', 1],
    ['Club 1', '13:35:00', '14:20:00', 1],
    ['Club 2', '14:20:00', '15:05:00', 1],
    ['Short Break', '15:05:00', '15:10:00', 1],
    ['Club 3', '15:10:00', '15:55:00', 1]
];

$stmt = $conn->prepare("INSERT INTO schedule_time_slots (period_name, start_time, end_time, is_friday_special) VALUES (?, ?, ?, ?)");
foreach ($timeSlots as $slot) {
    $stmt->execute($slot);
}

// 2. Insert sample subjects
$subjects = [
    ['English', '1-10', 1],
    ['Nepali', '1-10', 1],
    ['Mathematics', '1-10', 1],
    ['Science', '4-10', 1],
    ['Social Studies', '4-10', 1],
    ['HPE', '4-10', 1],
    ['Computer Science', '6-10', 1],
    ['Optional Mathematics', '9-10', 0]
];

$stmt = $conn->prepare("INSERT INTO schedule_subjects (name, grade_range, is_core) VALUES (?, ?, ?)");
foreach ($subjects as $subject) {
    $stmt->execute($subject);
}

echo "Scheduling tables initialized successfully!";
}catch (PDOException $e) {
    die("Initialization failed: " . $e->getMessage());
}
?>