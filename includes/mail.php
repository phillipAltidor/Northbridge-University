<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function portal_mailer(): PHPMailer
{
    reset_demo_recipient();
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Email dependencies are not installed.');
    require_once $autoload;
    foreach (['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'] as $key) {
        if (empty($_ENV[$key])) throw new RuntimeException('Email configuration is incomplete.');
    }
    $encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
    if (!in_array($encryption, ['ssl', 'tls'], true)) throw new RuntimeException('Encrypted SMTP is required.');
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 465);
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'];
    $mail->Password = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPDebug = 0;
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME'] ?? 'Northbridge University');
    return $mail;
}

function reset_demo_recipient(): string
{
    $recipient = trim($_ENV['MAIL_DEMO_RECIPIENT'] ?? '');
    if ($recipient === '') return '';
    if (!in_array($_ENV['APP_ENV'] ?? '', ['local', 'demo'], true)) {
        throw new RuntimeException('Demo email routing is not allowed in this environment.');
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Demo email recipient is invalid.');
    }
    return $recipient;
}

function reset_email(string $recipient, ?string $code = null): PHPMailer
{
    $mail = portal_mailer();
    $demoRecipient = reset_demo_recipient();
    $mail->addAddress($demoRecipient !== '' ? $demoRecipient : $recipient);
    if ($code !== null) {
        $mail->Subject = 'Northbridge University password reset code';
        $mail->Body = "Your password reset code is: $code\n\nThis code expires in 10 minutes and can be used once.\nIf you did not request a reset, ignore this email. Do not share this code.\n\nNorthbridge University";
    } else {
        $mail->Subject = 'Your Northbridge University password was changed';
        $mail->Body = "Your Northbridge University password was changed and your account was unlocked.\n\nIf you did not make this change, contact your administrator immediately.\n\nNorthbridge University";
    }
    if ($demoRecipient !== '') {
        $mail->Subject = '[DEMO] ' . $mail->Subject;
        $mail->Body = "DEMO EMAIL: requested account $recipient\nDelivery redirected to the demo inbox.\n\n" . $mail->Body;
    }
    return $mail;
}

function send_reset_code(string $recipient, string $code): void
{
    reset_email($recipient, $code)->send();
}

function send_password_changed_notice(string $recipient): void
{
    reset_email($recipient)->send();
}
