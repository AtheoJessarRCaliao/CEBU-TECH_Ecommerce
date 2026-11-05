<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';

Auth::logout();
setFlash('info', 'You have been logged out of the Admin Dashboard.');
header('Location: login.php');
exit;

