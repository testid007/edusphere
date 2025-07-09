<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../includes/db.php';
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$formData = [
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'confirmPassword' => '',
    'gender' => '',
    'role' => 'Student',
    'class' => '',
    'dob' => '',
    'relationship' => '',
];

$roles = ['Student', 'Teacher', 'Admin', 'Parent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key])) {
            $formData[$key] = trim(htmlspecialchars($_POST[$key]));
        }
    }

    if ($formData['password'] !== $formData['confirmPassword']) {
        $error = "Passwords do not match";
    } elseif (
        empty($formData['firstName']) || empty($formData['lastName']) ||
        empty($formData['email']) || empty($formData['phone']) ||
        empty($formData['password']) || empty($formData['gender'])
    ) {
        $error = "Please fill in all required fields";
    } elseif ($formData['role'] === 'Student' && empty($formData['class'])) {
        $error = "Please select a class for Student role";
    } elseif ($formData['role'] === 'Student' && empty($formData['dob'])) {
        $error = "Please enter Date of Birth for Student";
    } elseif ($formData['role'] === 'Parent' && (empty($_POST['student_id']) || empty($formData['relationship']))) {
        $error = "Please provide Student ID and relationship for Parent role";
    }

    if (!$error) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                // Commenting out OTP generation and email sending for now
                /*
                // Generate OTP
                $otp = rand(100000, 999999);
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_data'] = $formData;
                $_SESSION['student_id'] = $_POST['student_id'] ?? null; // Only used for Parent
                $_SESSION['password_hash'] = password_hash($formData['password'], PASSWORD_DEFAULT);

                // Send email via PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'your_email@gmail.com'; // Replace with your Gmail
                    $mail->Password = 'your_app_password';    // App Password from Google
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('your_email@gmail.com', 'EduSphere');
                    $mail->addAddress($formData['email']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your OTP for EduSphere Registration';
                    $mail->Body    = "<p>Hello <strong>{$formData['firstName']} {$formData['lastName']}</strong>,<br>Your OTP is: <strong>{$otp}</strong></p>";

                    $mail->send();

                    header('Location: verify_otp.php');
                    exit();
                } catch (Exception $e) {
                    $error = "OTP email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
                */

                // Instead directly insert user now (no OTP)
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, password_hash, phone, role, gender) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $passwordHash,
                    $formData['phone'],
                    $formData['role'],
                    $formData['gender']
                ]);

                $userId = $conn->lastInsertId();

                if ($formData['role'] === 'Student') {
                    $fixedPrefix = '987';
                    $year = date('y');
                    $likePattern = $fixedPrefix . $year . '%';

                    $stmt = $conn->prepare("SELECT student_serial FROM students WHERE student_serial LIKE ? ORDER BY student_serial DESC LIMIT 1");
                    $stmt->execute([$likePattern]);
                    $lastSerial = $stmt->fetchColumn();

                    $newCount = $lastSerial ? ((int)substr($lastSerial, 5)) + 1 : 1;
                    $studentSerial = $fixedPrefix . $year . str_pad($newCount, 5, '0', STR_PAD_LEFT);

                    $stmt = $conn->prepare("INSERT INTO students (user_id, class, dob, student_serial) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $formData['class'], $formData['dob'], $studentSerial]);
                } elseif ($formData['role'] === 'Teacher') {
                    $stmt = $conn->prepare("INSERT INTO teachers (user_id, subject, department) VALUES (?, '', '')");
                    $stmt->execute([$userId]);
                } elseif ($formData['role'] === 'Parent') {
                    $studentId = (int)$_POST['student_id'];
                    $stmtCheck = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
                    $stmtCheck->execute([$studentId]);

                    if ($stmtCheck->rowCount() === 0) {
                        $error = "Student ID not found";
                        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO parents (user_id, student_id, relationship) VALUES (?, ?, ?)");
                        $stmt->execute([$userId, $studentId, $formData['relationship']]);
                    }
                } elseif ($formData['role'] === 'Admin') {
                    $stmt = $conn->prepare("INSERT INTO admins (user_id, role_description) VALUES (?, '')");
                    $stmt->execute([$userId]);
                }

                if (!$error) {
                    $_SESSION['success_message'] = "Registered successfully as {$formData['role']}!";
                    header('Location: login.php');
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
