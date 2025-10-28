<?php
// STEP 1: Basic PHP Test
// This tests if PHP is working at all on your GoDaddy server
// Upload this and access: https://yourdomain.com/aviar/test1_basic.php

echo "✓ PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "<br>If you see this message, PHP is running correctly.";
?>

