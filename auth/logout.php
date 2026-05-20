<?php
session_start();
session_destroy();
header('Location: /student-dashboard/auth/login.php');
exit;
