<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/admin-navigation.php';
$_SERVER['SCRIPT_NAME'] = '/home.php';
$groups = admin_nav_groups();
if (count($groups) !== 6 || array_sum(array_map(static fn($g) => count($g[2]), $groups)) !== 30) throw new RuntimeException('Approved navigation count changed');
foreach (['home.php', 'admin/create-user.php', 'admin/update-user.php', 'admin/master-schedule.php', 'admin/edit-section.php'] as $active) {
    ob_start();
    admin_navigation($active);
    $html = ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    if ($xpath->query('//a')->length !== 4 || $xpath->query('//span[@aria-disabled="true"]')->length !== 27) throw new RuntimeException('Active/disabled action mismatch');
    if ($xpath->query('//a[@aria-current="page"]')->length !== 1) throw new RuntimeException('Current page not highlighted');
    if ($xpath->query('//details[@open]')->length !== 0) throw new RuntimeException('Groups must start collapsed on every page');
    if (!str_contains($html, ':collapsed-v2:')) throw new RuntimeException('Old auto-expanded preferences must not be restored');
    foreach ($xpath->query('//a') as $link) {
        if (!in_array($link->getAttribute('href'), ['/home.php', '/admin/create-user.php', '/admin/update-user.php', '/admin/master-schedule.php'], true)) throw new RuntimeException('Unexpected working link');
    }
}
$_SERVER['SCRIPT_NAME'] = '/northbridge/admin/master-schedule.php';
ob_start(); admin_navigation('admin/master-schedule.php'); $html = ob_get_clean();
if (!str_contains($html, 'href="/northbridge/admin/master-schedule.php"')) throw new RuntimeException('Subfolder URL failed');
echo "PASS: approved actions, active links, disabled items, all groups collapsed on every page, fresh preference key, and subfolder URLs\n";
