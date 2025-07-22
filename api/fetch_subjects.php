<?php
require_once __DIR__.'/../functions/ScheduleManager.php';
$grade = intval($_GET['grade'] ?? 1);
$scheduleManager = new ScheduleManager();
$subjects = $scheduleManager->getSubjectsForGrade($grade);
header('Content-Type: application/json');
echo json_encode($subjects);
?>