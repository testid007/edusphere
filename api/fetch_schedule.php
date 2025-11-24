<?php
session_start();
require_once __DIR__.'/../functions/ScheduleManager.php';

try {
    $scheduleManager = new ScheduleManager();
    $grade = isset($_GET['grade']) ? (int)$_GET['grade'] : 1;
    
    $schedule = $scheduleManager->getClassSchedule($grade);
    
    if (!empty($schedule)) {
        echo '<table class="table schedule-table">';
        echo '<thead><tr><th>Day</th><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($schedule as $entry) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['day']) . '</td>';
            echo '<td>' . htmlspecialchars($entry['period_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars(($entry['start_time'] ?? '') . ' - ' . ($entry['end_time'] ?? '')) . '</td>';
            
            // Check if it's a special event
            if (isset($entry['is_special']) && $entry['is_special']) {
                echo '<td colspan="2" class="text-center"><strong>' . htmlspecialchars($entry['special_name'] ?? 'Special Event') . '</strong> <small>(Special Event)</small></td>';
            } else {
                echo '<td>' . htmlspecialchars($entry['subject'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($entry['teacher'] ?? 'N/A') . '</td>';
            }
            
            echo '<td>';
            echo '<button class="btn btn-sm btn-primary me-1" onclick="editScheduleEntry(' . ($entry['id'] ?? 0) . ')"><i class="fas fa-edit"></i></button>';
            echo '<button class="btn btn-sm btn-danger" onclick="deleteScheduleEntry(' . ($entry['id'] ?? 0) . ')"><i class="fas fa-trash"></i></button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="text-center py-4">';
        echo '<i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>';
        echo '<p class="text-muted">No schedule entries found for Grade ' . $grade . '.</p>';
        echo '<p class="text-muted">Create a schedule using the form above or use the automated schedule generator.</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading schedule: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>