<?php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once __DIR__.'/../functions/ScheduleManager.php';

// Check if request is POST
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
    
    // Validate required fields
    $requiredFields = ['day', 'time_slot_id', 'class'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Check if it's a special event or regular class
    $isSpecial = isset($_POST['is_special']) && $_POST['is_special'] == '1';
    
    if (!$isSpecial) {
        // Regular class - validate subject and teacher
        if (!isset($_POST['subject_id']) || empty($_POST['subject_id'])) {
            echo json_encode(['success' => false, 'error' => 'Subject is required for regular classes']);
            exit;
        }
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Teacher is required for regular classes']);
            exit;
        }
    } else {
        // Special event - validate special name
        if (!isset($_POST['special_name']) || empty(trim($_POST['special_name']))) {
            echo json_encode(['success' => false, 'error' => 'Special event name is required']);
            exit;
        }
    }
    
    // Prepare data for saving
    $scheduleData = [
        'class' => (int)$_POST['class'],
        'day' => trim($_POST['day']),
        'time_slot_id' => (int)$_POST['time_slot_id'],
        'is_special' => $isSpecial ? 1 : 0
    ];
    
    if ($isSpecial) {
        $scheduleData['special_name'] = trim($_POST['special_name']);
        $scheduleData['subject_id'] = null;
        $scheduleData['user_id'] = null;
    } else {
        $scheduleData['subject_id'] = (int)$_POST['subject_id'];
        $scheduleData['user_id'] = (int)$_POST['user_id'];
        $scheduleData['special_name'] = null;
    }
    
    // Check for conflicts (same class, day, time slot)
    $existingEntry = $scheduleManager->checkScheduleConflict(
        $scheduleData['class'], 
        $scheduleData['day'], 
        $scheduleData['time_slot_id']
    );
    
    // Allow update if it's the same entry
    if ($existingEntry && (!isset($_POST['id']) || $_POST['id'] != $existingEntry['id'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Time slot already occupied. Please choose a different time slot.'
        ]);
        exit;
    }
    
    // Check teacher availability (for regular classes only)
    if (!$isSpecial) {
        $teacherConflict = $scheduleManager->checkTeacherConflict(
            $scheduleData['user_id'], 
            $scheduleData['day'], 
            $scheduleData['time_slot_id']
        );
        
        if ($teacherConflict && (!isset($_POST['id']) || $_POST['id'] != $teacherConflict['id'])) {
            echo json_encode([
                'success' => false, 
                'error' => 'Teacher is already assigned to another class at this time.'
            ]);
            exit;
        }
    }
    
    // Save or update the schedule entry
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update existing entry
        $scheduleData['id'] = (int)$_POST['id'];
        $result = $scheduleManager->updateScheduleEntry($scheduleData);
        $message = 'Schedule entry updated successfully!';
    } else {
        // Create new entry
        $result = $scheduleManager->saveScheduleEntry($scheduleData);
        $message = 'Schedule entry saved successfully!';
    }
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'data' => $scheduleData
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to save schedule entry. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Schedule save error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>