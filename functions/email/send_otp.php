<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'yourgmail@gmail.com';      // replace with your Gmail
        $mail->Password = 'your_app_password';        // replace with your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('yourgmail@gmail.com', 'EduSphere');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for EduSphere Registration';
        $mail->Body = "Your OTP code is <strong>$otp</strong>. It will expire in 5 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
