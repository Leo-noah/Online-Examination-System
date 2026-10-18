<?php
session_start();

// Clear all session data
session_unset();
session_destroy();

// Redirect back to the login page immediately
header("Location: login.php");
exit();
