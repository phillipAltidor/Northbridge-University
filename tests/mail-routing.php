<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/mail.php';

// Message construction only: no database access or network delivery.
$_ENV = [
    'APP_ENV' => 'local', 'MAIL_HOST' => 'smtp.example.invalid',
    'MAIL_USERNAME' => 'sender@example.invalid', 'MAIL_PASSWORD' => 'test-only',
    'MAIL_FROM_ADDRESS' => 'sender@example.invalid',
    'MAIL_DEMO_RECIPIENT' => 'demo@example.invalid',
];
$checks = 0;
function check_routing(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo "PASS: $label\n";
}
foreach (['student', 'faculty', 'admin', 'statstaff'] as $role) {
    $account = "$role@northbridge.edu";
    $mail = reset_email($account, '123456');
    check_routing($mail->getToAddresses() === [['demo@example.invalid', '']], "$role routes only to demo inbox");
    check_routing(str_contains($mail->Body, $account) && str_contains($mail->Body, '123456'), "$role message identifies account and code");
    check_routing(str_starts_with($mail->Subject, '[DEMO]'), "$role message marked demo");
}
$notice = reset_email('student@northbridge.edu');
check_routing($notice->getToAddresses() === [['demo@example.invalid', '']], 'Change notification uses demo inbox');
check_routing(!str_contains($notice->Body, '123456'), 'Notification contains no reset code');
$_ENV['APP_ENV'] = 'demo';
check_routing(reset_demo_recipient() === 'demo@example.invalid', 'Explicit demo environment supported');
foreach (['production', 'staging', ''] as $environment) {
    $_ENV['APP_ENV'] = $environment;
    $blocked = false;
    try { reset_email('student@northbridge.edu', '123456'); }
    catch (RuntimeException $error) { $blocked = true; }
    check_routing($blocked, 'Demo override blocked outside local/demo');
}
$_ENV['APP_ENV'] = 'local';
$_ENV['MAIL_DEMO_RECIPIENT'] = 'invalid';
$blocked = false;
try { reset_demo_recipient(); } catch (RuntimeException $error) { $blocked = true; }
check_routing($blocked, 'Invalid override rejected');
$_ENV['APP_ENV'] = 'production';
$_ENV['MAIL_DEMO_RECIPIENT'] = '';
$mail = reset_email('student@northbridge.edu', '123456');
check_routing($mail->getToAddresses() === [['student@northbridge.edu', '']], 'Blank override preserves normal registered delivery');
check_routing(!str_contains($mail->Subject, '[DEMO]'), 'Normal email has no demo label');
echo "PASS: $checks mail-routing checks; no emails sent\n";
