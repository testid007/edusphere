<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$id = $_SESSION['user_id'];
$name = trim($_POST['teacher_name'] ?? '');
$email = trim($_POST['teacher_email'] ?? '');

if (!$name || !$email) {
    echo json_encode(['status'=>'error','message'=>'Name and email required.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE users SET first_name = :name, email = :email WHERE id = :id AND role = 'teacher'");
    $stmt->execute([':name'=>$name, ':email'=>$email, ':id'=>$id]);
    $_SESSION['teacher_name'] = $name;
    $_SESSION['teacher_email'] = $email;
    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Database error.']);
}