<?php

// Start the session if it has not already been started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


// Make sure the user is logged in
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {

        header(
            'Location: ' . app_url('index.php')
        );

        exit;
    }
}


// Make sure the user has permission to view the page
function require_role(array $roles): void
{
    require_login();

    $userType = $_SESSION['user_type'] ?? '';

    if (!in_array($userType, $roles, true)) {

        http_response_code(403);

        exit(
            '403 - You do not have permission to view this page.'
        );
    }
}


// Safely display information on a page
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// Build URLs that work from the PHP dev server or an XAMPP subfolder
function app_base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = str_replace('\\', '/', dirname($scriptName));
    $parts = array_values(array_filter(explode('/', $directory), 'strlen'));

    if (!$parts) {
        return '';
    }

    $portalDirectories = [
        'admin',
        'student'
    ];

    if (in_array(end($parts), $portalDirectories, true)) {
        array_pop($parts);
    }

    if (!$parts) {
        return '';
    }

    return '/' . implode('/', $parts);
}


function app_url(string $path): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}


// Send each type of user to the correct dashboard
function role_home(string $role): string
{
    switch ($role) {

        case 'Student':
            return app_url('home.php');

        case 'Faculty':
            return app_url('home.php');

        case 'Admin':
            return app_url('home.php');

        case 'StatStaff':
            return app_url('home.php');

        default:
            return app_url('index.php');
    }
}

?>
