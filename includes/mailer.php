<?php

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailer_send_code($toEmail, $toName, $codigo) {

  $mail = new PHPMailer(true);

  try {

    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = "cb473355@gmail.com";
    $mail->Password = "swhmcfielbhgwccz";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->CharSet = "UTF-8";

    $mail->setFrom("cb473355@gmail.com", "Biblioteca No-Reply");
    $mail->addAddress($toEmail, $toName);

    $mail->Subject = "Código de confirmação";

    $mail->Body = "Seu código é: " . $codigo;

    return $mail->send();

  } catch (Exception $e) {
    return false;
  }
}
