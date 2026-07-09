<?php
/**
 * admin/settings/line.php — รวมเข้ากับ notifications.php แล้ว
 * เก็บไฟล์ไว้เป็น redirect กันลิงก์เก่า/บุ๊กมาร์กพัง
 */
header("Location: /admin/settings/notifications.php#line-config", true, 301);
exit();
