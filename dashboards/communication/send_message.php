<?php
session_start();
require_once '../includes/db.php';  // adjust path as needed

// Get logged-in user info from session
$sender_id = $_SESSION['user_id'];    // set when user logs in
$sender_role = $_SESSION['role'];     // 'parent' or 'teacher'

$receiver_id = intval($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($receiver_id > 0 && $message !== '') {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, sender_role, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$sender_id, $receiver_id, $sender_role, $message]);
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
}
