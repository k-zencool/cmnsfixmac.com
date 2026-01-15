<?php
/********************************************************************
 * FIX USED SKU SCRIPT (Corrected)
 * เจนรหัส Asset Tag ให้รายการมือสองย้อนหลัง
 * Format: U-{TYPE}-{YYYYMM}-A{ID}  <-- ใส่ A นำหน้าเลขลำดับ
 ********************************************************************/
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// ฟังก์ชันแยกประเภท
function detectPrefix($name, $model) {
    $text = strtolower($name . ' ' . $model);
    if (strpos($text, 'iphone') !== false) return 'IP';
    if (strpos($text, 'ipad') !== false)   return 'PD';
    if (strpos($text, 'imac') !== false)   return 'IM';
    if (strpos($text, 'watch') !== false)  return 'WA';
    return 'MB'; // Default
}

echo "<h1>🛠️ กำลังสร้าง Asset Tag มือสอง (ใส่ A)...</h1>";
echo "<pre style='background:#1f2937; color:#10b981; padding:20px; border-radius:10px; font-family:monospace;'>";

try {
    $pdo->beginTransaction();

    // ดึงรายการทั้งหมดมาเจนใหม่ (หรือจะเอาเฉพาะที่ว่างก็ได้ แต่เจนทับไปเลยชัวร์กว่าจะได้ฟอร์แมตเดียวกันหมด)
    // ถ้าจะเอาแค่ที่ว่างให้เพิ่ม WHERE used_sku IS NULL
    $stmt = $pdo->prepare("SELECT id, part_name, device_models, created_at FROM parts_used ORDER BY created_at ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($rows as $r) {
        // Prefix
        $prefix = detectPrefix($r['part_name'], $r['device_models']);
        
        // Date
        $ym = date('Ym', strtotime($r['created_at']));
        
        // ID (Format 0001)
        $idStr = sprintf("%04d", $r['id']);
        
        // Gen Code: U-MB-202512-A0001  <-- เพิ่ม A ตรงนี้
        $newSku = "U-{$prefix}-{$ym}-A{$idStr}";

        // Update
        $upd = $pdo->prepare("UPDATE parts_used SET used_sku = ? WHERE id = ?");
        $upd->execute([$newSku, $r['id']]);

        // Color logic
        $color = '#f59e0b'; // MB
        if($prefix=='IP') $color='#3b82f6';
        if($prefix=='PD') $color='#ec4899';

        echo "ID: {$r['id']} | {$r['part_name']} \t---> \t<b style='color:{$color}'>{$newSku}</b>\n";
        $count++;
    }

    $pdo->commit();
    echo "\n----------------------------------------\n";
    echo "🎉 เสร็จสิ้น! อัปเดตไปทั้งหมด {$count} รายการ";
    echo "\n<a href='index.php?tab=used' style='color:#fbbf24; text-decoration:none;'>[ กลับไปดูรายการมือสอง ]</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage();
}
echo "</pre>";
?>