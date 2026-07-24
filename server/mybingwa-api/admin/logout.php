<?php
require __DIR__ . '/lib_admin.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
