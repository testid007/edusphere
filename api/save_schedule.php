<?php
session_start();
require_once __DIR__.'/../functions/ScheduleManager.php';

header('Content-Type: application/json');

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

// Collect and sanitize input

$defaults = [
    'id' => null,
    'class' => '',
    'day' => '',
    'time_slot_id' => '',
    'subject_id' => '',
    'user_id' => '',
    'is_special' => 0,
    'special_name' => null
];
foreach ($defaults as $key => $val) {
    $$key = $_POST[$key] ?? $val;
}

// Convert types
$id = $id !== '' ? (int)$id : null;
$class = (int)$class;
$time_slot_id = (int)$time_slot_id;
$subject_id = $subject_id !== '' ? (int)$subject_id : null;
$user_id = $user_id !== '' ? (int)$user_id : null;
$is_special = isset($_POST['is_special']) ? 1 : 0;
$special_name = $is_special ? trim($_POST['special_name'] ?? '') : null;

// Validation
if (!$class || !$day || !$time_slot_id || (!$is_special && (!$subject_id || !$user_id)) || ($is_special && !$special_name)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid fields.']);
    exit;
}

$scheduleManager = new ScheduleManager();
$success = $scheduleManager->saveScheduleEntry($id, $class, $day, $time_slot_id, $subject_id, $user_id, $is_special, $special_name);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
