<?php
/*
 * delete_listing.php
 * - ลบสินค้า (Listings)
 * - ใช้ POST + CSRF Token เท่านั้น (ห้ามใช้ GET)
 * - ลบข้อมูลในตารางลูก (attrs, images) ด้วย
 * - ลบไฟล์รูปออกจาก server ด้วย
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); // บังคับ Login

// ---------------------------------------------------
// 1. ตรวจสอบ Method และ CSRF Token
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // ถ้าไม่ใช่ POST, ถีบกลับ
    header('Location: index.php?error=method');
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    // ถ้า Token ไม่ตรง, ถีบกลับ
    header('Location: index.php?error=csrf');
    exit;
}

$id = max(0, (int)($_POST['id'] ?? 0));
if ($id <= 0) {
    header('Location: index.php?error=noid');
    exit;
}

// ---------------------------------------------------
// 2. เริ่ม Transaction (โคตรสำคัญ)
// ---------------------------------------------------
try {
    $pdo->beginTransaction();

    // 2a. [จำเป็น] หา Path รูปทั้งหมดก่อนที่จะลบ DB
    // (จะได้เอาไปลบไฟล์ออกจาก server ได้)
    $st_main = $pdo->prepare("SELECT main_image FROM listings WHERE id = ?");
    $st_main->execute([$id]);
    $main_image = $st_main->fetchColumn();

    $st_gallery = $pdo->prepare("SELECT url FROM listing_images WHERE listing_id = ?");
    $st_gallery->execute([$id]);
    $gallery_images = $st_gallery->fetchAll(PDO::FETCH_COLUMN);

    // 2b. ลบข้อมูลจากตารางลูก (FK) ก่อน
    $pdo->prepare("DELETE FROM listing_attr_values WHERE listing_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM listing_images WHERE listing_id = ?")->execute([$id]);

    // 2c. ลบข้อมูลจากตารางหลัก (Listings)
    $st_delete_listing = $pdo->prepare("DELETE FROM listings WHERE id = ?");
    $st_delete_listing->execute([$id]);
    $delete_count = $st_delete_listing->rowCount();

    if ($delete_count === 0) {
        // ถ้า $id ที่ส่งมาไม่มีใน DB
        throw new Exception("Product ID $id not found.");
    }

    // 2d. ถ้าลบ DB สำเร็จ -> Commit
    $pdo->commit();

    // ---------------------------------------------------
    // 3. ลบไฟล์ออกจาก Server (ทำ *หลัง* Commit สำเร็จ)
    // ---------------------------------------------------
    $upload_dir_path = realpath(__DIR__ . '/../../uploads/shops'); // Path จริง
    if ($upload_dir_path) { // เช็คว่า path มีจริง
        
        $all_images_to_delete = $gallery_images;
        if ($main_image) {
            $all_images_to_delete[] = $main_image;
        }

        // <-- [กูแก้ตรงนี้] ใช้ array_unique เพื่อกำจัด path รูปที่อาจซ้ำซ้อน
        // เผื่อ user มันอัปโหลดรูปเดียวกันเป็นทั้ง main และ gallery
        $unique_images = array_values(array_unique(array_filter($all_images_to_delete)));
        // <-- [จบจุดที่กูแก้]

        foreach ($unique_images as $img_url) { // <-- ใช้ตัวแปรใหม่
            if (empty($img_url)) continue;

            // สร้าง path จริงของไฟล์
            $file_path = realpath(__DIR__ . '/../../' . ltrim($img_url, '/'));

            // [โคตรสำคัญ] เช็คให้ชัวร์ว่าไฟล์ที่จะลบ อยู่ในโฟลเดอร์ /uploads/shops/ จริงๆ
            // (กันพวกแฮกเกอร์ส่ง path แปลกๆ เช่น ../../includes/db.php มาให้มึงลบ)
            if ($file_path && strpos($file_path, $upload_dir_path) === 0 && file_exists($file_path)) {
                @unlink($file_path); // @ คือ ปิด error ถ้ามันลบไม่สำเร็จ
            }
        }
    }

    // ---------------------------------------------------
    // 4. ส่งกลับหน้า Index
    // ---------------------------------------------------
    header('Location: index.php?deleted=1&id=' . $id);
    exit;

} catch (Exception $e) {
    // ---------------------------------------------------
    // 5. ถ้าพัง -> Rollback
    // ---------------------------------------------------
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // (มึงควรไป log error ไว้ดูเอง)
    error_log("Delete listing $id failed: " . $e->getMessage());
    header('Location: index.php?error=deletefail&msg=' . urlencode($e->getMessage()));
    exit;
}
?>