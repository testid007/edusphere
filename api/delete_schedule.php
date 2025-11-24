<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__.'/../functions/ScheduleManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    $scheduleManager = new ScheduleManager();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid entry ID']);
        exit;
    }
    
    $result = $scheduleManager->deleteScheduleEntry($id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Schedule entry deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete schedule entry']);
    }
    
} catch (Exception $e) {
    error_log("Schedule delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>