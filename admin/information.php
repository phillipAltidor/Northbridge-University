<?php

require_once __DIR__ . '/../includes/auth.php';

require_role(['Admin']);

header('Location: ' . app_url('home.php'));
exit;
