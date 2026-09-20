<?php
// admin/login.php
// Retired standalone legacy admin login page.
// All authentication is consolidated into the unified /login endpoint.
header("Location: ../login", true, 301);
exit;
