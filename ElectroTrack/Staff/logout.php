<?php
session_start();
session_unset();
session_destroy();

// Redirect to ElectroTrack login page after logout
header('Location: http://localhost/SIA/ElectroTrack/login.php');
exit();
?>
