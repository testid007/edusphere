<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Fetch user by email and role
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password_hash'])) {
                    // Password matches, set session variables based on role
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];

                    switch ($user['role']) {
                        case 'Student':
                            $_SESSION['student_name'] = $user['first_name'];
                            header('Location: ../dashboards/student_dashboard.php');
                            exit();
                        case 'Teacher':
                            $_SESSION['teacher_name'] = $user['first_name'];
                            header('Location: ../dashboards/teacher_dashboard.php');
                            exit();
                        case 'Admin':
                            $_SESSION['admin_name'] = $user['first_name'];
                            header('Location: ../dashboards/admin_dashboard.php');
                            exit();
                        case 'Parent':
                            $_SESSION['parent_name'] = $user['first_name'];
                            header('Location: ../dashboards/parent_dashboard.php');
                            exit();
                        default:
                            $error = "Unknown user role.";
                    }
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "No user found with this email and role.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
} else {
    // Redirect to login form if accessed without POST
    header('Location: login.php');
    exit();
}

// If error occurred, save to session and redirect back to login page
$_SESSION['login_error'] = $error;
header('Location: login.php');
exit();
