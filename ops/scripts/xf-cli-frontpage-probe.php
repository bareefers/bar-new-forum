<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['HTTP_HOST'] = 'beta.bareefers.org';
$_SERVER['REQUEST_URI'] = '/forum/';
$_SERVER['SCRIPT_NAME'] = '/forum/index.php';
$_SERVER['PHP_SELF'] = '/forum/index.php';
$_SERVER['SCRIPT_FILENAME'] = '/var/www/bareefers.org/forum/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/var/www/bareefers.org';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'beta.bareefers.org';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS'] = 'on';

chdir('/var/www/bareefers.org/forum');
require '/var/www/bareefers.org/forum/index.php';
