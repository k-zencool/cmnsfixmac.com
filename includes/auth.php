<?php
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: /admin/login.php");
  exit();
}
