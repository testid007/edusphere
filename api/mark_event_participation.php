<?php
// api/mark_event_participation.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';
require_once '../functions/EventManager.php';

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$status  = $_POST['status'] ?? 'registered';

if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

$eventManager = new EventManager($conn);

$ok = $eventManager->markParticipation($eventId, (int)$_SESSION['user_id'], $status);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'You have registered for this event.' : 'Error saving participation',
]);
