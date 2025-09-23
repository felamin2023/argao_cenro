<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send'])) {
    $to = $_POST['to'];
    $message = $_POST['message'];

    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kkangpae59@gmail.com'; // Your Gmail address
        $mail->Password   = 'pjkx lvhg outl ldcg';    // App password, not your Gmail password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom('your_gmail@gmail.com', 'DENR System');
        $mail->addAddress($to);

        //Content
        $mail->isHTML(false);
        $mail->Subject = 'Message from DENR System';
        $mail->Body    = $message;

        $mail->send();
        $status = "<p style='color:green;'>Email sent successfully!</p>";
    } catch (Exception $e) {
        $status = "<p style='color:red;'>Failed to send email. Mailer Error: {$mail->ErrorInfo}</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Email</title>
</head>

<body>
    <form action="sendmsgemail.php" method="post">
        <label for="to">Recipient Email:</label><br>
        <input type="email" id="to" name="to" required><br><br>
        <label for="message">Message:</label><br>
        <textarea id="message" name="message" required></textarea><br><br>
        <button type="submit" name="send">Send</button>
    </form>
    <?php if (isset($status)) echo $status; ?>
</body>

</html>