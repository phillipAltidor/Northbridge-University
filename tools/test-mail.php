<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/mail.php';
try {
    $mail = portal_mailer();
    if (in_array('--send', $argv, true)) {
        $mail->addAddress($_ENV['MAIL_USERNAME']);
        $mail->Subject = 'Northbridge University email setup test';
        $mail->Body = "This is a setup test from the Northbridge University portal.\n\nThe portal successfully connected to the configured mail service. This is not a password-reset code and no account password was changed.\n\nNorthbridge University";
        $mail->send();
        echo "PASS: SMTP accepted the setup-test email addressed to the configured sender. Confirm inbox receipt separately.\n";
    } else {
        if (!$mail->smtpConnect()) throw new RuntimeException('SMTP connection failed.');
        $mail->smtpClose();
        echo "PASS: encrypted SMTP authentication; no email sent. Use --send to send a setup test to the sender inbox.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Email check failed. Verify local mail configuration; no credentials were displayed.\n");
    exit(1);
}
