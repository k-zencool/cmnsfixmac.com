<?php
// admin/cron/test_msg.php
require_once 'telegram_helper.php'; // เรียกตัวช่วยส่งที่เราแก้ Chat ID ไปแล้ว

echo "<h1>📡 กำลังทดสอบสัญญาณ Jarvis...</h1>";

// ส่งข้อความเทส
$result = sendTelegram("👋 <b>ฮัลโหล!</b> นี่คือการทดสอบสัญญาณจาก Localhost\nถ้าเห็นข้อความนี้ แสดงว่า Jarvis พร้อมทำงานแล้วครับลูกพี่! 🚀");

echo "<h3>ผลลัพธ์จาก Telegram Server:</h3>";
echo "<pre>" . htmlspecialchars($result) . "</pre>";
?>