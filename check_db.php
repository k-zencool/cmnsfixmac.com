<?php
// check_db.php - ไฟล์สืบหาความจริง
require 'includes/db.php';

echo "<h1>Debugging Article ID: 2</h1>";

try {
    // ดึงข้อมูลแถวที่ 2 มาดูไส้ใน
    $stmt = $pdo->query("SELECT * FROM articles WHERE id = 2");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($row);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>