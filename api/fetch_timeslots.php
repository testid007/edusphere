<?php
require_once __DIR__.'/../functions/ScheduleManager.php';
$day = $_GET['day'] ?? 'Sunday';
$grade = intval($_GET['grade'] ?? 1);
$scheduleManager = new ScheduleManager();
$slots = $scheduleManager->getAvailableTimeSlots($grade, $day);
header('Content-Type: application/json');
echo json_encode($slots);