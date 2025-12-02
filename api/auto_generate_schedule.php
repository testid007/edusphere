<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$role = strtolower($_SESSION['user_role'] ?? ($_SESSION['role'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);

// Only admin can auto-generate schedules
if (!$userId || $role !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: only admin can auto-generate schedules.'
    ]);
    exit;
}

$grade = $_POST['grade'] ?? '';
$grade = trim($grade);

if ($grade === '') {
    echo json_encode([
        'success' => false,
        'message' => 'No class/grade provided.'
    ]);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../functions/ScheduleManager.php';
require_once __DIR__ . '/../functions/AutoScheduleGenerator.php';

try {
    // Create ScheduleManager first
    $scheduleManager = new ScheduleManager();

    // Pass ScheduleManager into AutoScheduleGenerator
    $generator = new AutoScheduleGenerator($scheduleManager);

    // Pass user ID as an option (for logging generated_schedules)
    $result = $generator->generateSchedule($grade, [
        'generated_by' => $userId
    ]);

    echo json_encode($result);
} catch (Throwable $e) {
    error_log('Auto-generate error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error while generating schedule.',
        'debug'   => $e->getMessage()
    ]);
}