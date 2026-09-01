<?php
require_once __DIR__ . '/includes/helper.php';
session_destroy();
header('Location: index.php');
exit();
