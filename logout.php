<?php
session_start();
session_destroy();
header('Location: /g2forms/login.php');
exit;
