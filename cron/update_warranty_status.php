<?php
/********************************************************************
 * /cron/update_warranty_status.php
 * * ไฟล์นี้ถูกเรียกโดย Cron Job (ตั้งเวลาใน DirectAdmin) เท่านั้น
 * หน้าที่: อัปเดตสถานะงานประกันที่หมดอายุแล้ว (แต่ status ค้าง)
 * ให้เป็น 'expired' โดยอัตโนมัติ
 ********************************************************************/

// *** แก้ Path ไปหา db.php ให้ถูก ***
// __DIR__ คือโฟลเดอร์ /.../public_html/cron
// เราเลยต้องถอยกลับไป 1 ชั้น (../) เพื่อเข้า /includes/
require_once __DIR__ . '/../includes/db.php'; 

echo "Cron Job: Starting Warranty Status Update...\n";

try {
    // 1. อัปเดตตัวที่หมดอายุ ให้เป็น 'expired'
    // นี่คือคำสั่งที่มึงต้องการ
    $st1 = $pdo->prepare("
        UPDATE warranty_jobs 
        SET warranty_status = 'expired' 
        WHERE warranty_status = 'in_warranty' 
          AND warranty_until < CURDATE()
    ");
    $st1->execute();
    
    // บันทึก Log (มึงจะได้รู้ว่ามันทำงาน)
    $count = $st1->rowCount();
    echo "Expired status updated: " . $count . " rows.\n";
    
    echo "Cron Job: Update complete.\n";

} catch (PDOException $e) {
    // ถ้าพัง ให้มันส่งเสียง
    echo "Error: " . $e->getMessage();
    error_log("Cron Job (update_warranty_status) Error: " . $e->getMessage());
}
?>