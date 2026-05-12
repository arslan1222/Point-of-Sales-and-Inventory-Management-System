<?php
require_once '../../config/app.php';
session_destroy();
redirect(APP_URL . '/modules/auth/login.php');
?>