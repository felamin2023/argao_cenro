<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../../vendor/autoload.php';

function sendOTP($email, $otp_code)
{
    $mail = new PHPMailer(true);

    try {

        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'argaocenro@gmail.com';
        $mail->Password   = 'rlqh eihc lyoa etbl';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;


        $mail->setFrom('argaocenro@gmail.com', 'DENR System');
        $mail->addAddress($email);


        $mail->isHTML(true);
        $mail->Subject = 'DENR Account: Email Verification Code';
        $mail->Body    = "
            <h2>DENR Account Verification</h2>
            <p>Your verification code is: <strong>$otp_code</strong></p>
            <p>This code will expire in 5 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";
        $mail->AltBody = "Your DENR verification code is: $otp_code (expires in 5 minutes)";

        $mail->send();
        return true;
    } catch (Exception $e) {

        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
