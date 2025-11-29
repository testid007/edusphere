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

// Only allow known statuses
$allowedStatuses = ['interested', 'not_interested', 'registered', 'participated'];
if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

$studentId    = (int)$_SESSION['user_id'];
$eventManager = new EventManager($conn);

try {
    // 1) Mark participation for the clicked event
    $ok = $eventManager->markParticipation($eventId, $studentId, $status);

    if (!$ok) {
        echo json_encode([
            'success' => false,
            'message' => 'Error saving participation',
        ]);
        exit;
    }

    $extraUpdated = 0;

    // 2) If INTERESTED -> auto-mark similar category events as interested
    if ($status === 'interested') {
        // Find this event's category
        $stmt = $conn->prepare("SELECT category_id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $categoryId = (int)$stmt->fetchColumn();

        if ($categoryId) {
            // Get other upcoming active events in the same category
            $stmt = $conn->prepare("
                SELECT id
                FROM events
                WHERE category_id = :cat
                  AND is_active = 1
                  AND event_date >= CURDATE()
                  AND id <> :event_id
            ");
            $stmt->execute([
                ':cat'      => $categoryId,
                ':event_id' => $eventId,
            ]);

            $similarEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($similarEvents) {
                foreach ($similarEvents as $similarId) {
                    if ($eventManager->markParticipation((int)$similarId, $studentId, 'interested')) {
                        $extraUpdated++;
                    }
                }
            }
        }
    }

    // 3) Build user-friendly message
    if ($status === 'interested') {
        $msg = 'Marked as interested.';
        if ($extraUpdated > 0) {
            $msg .= " Also marked {$extraUpdated} similar event(s) in this category as interested.";
        }
    } elseif ($status === 'not_interested') {
        $msg = 'Marked as not interested.';
    } elseif ($status === 'registered') {
        $msg = 'You have registered for this event.';
    } else { // participated
        $msg = 'Participation updated.';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error saving participation',
    ]);
}
