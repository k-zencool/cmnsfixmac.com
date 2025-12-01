<?php
/*
 * edit_listing.php (FINAL + English Fields)
 * - Layout: Form Left / Preview Right
 * - Minimal Inline CSS
 * - Drag/Drop Gallery ONLY
 * - EAV: Static Keys (Reverted)
 * - Added title_en (DB column) & description_en (JSON)
 * - All previous fixes (Path, Auth, Slug w/ Uniqueness, SKU)
 * - Redirects to index.php
 * - Live Preview Card
 * - [GEMINI EDIT v8 - THE FINAL FIX]
 * - Form ACTION now correctly points to 'edit_listing.php'
 * - Patched PHP unlink (2.5a) to correctly handle 
 * paths with 'https://domain.com' stored in the DB.
 * - Fixed all BASE_URL preview issues.
 * - [GEMINI EDIT v9]
 * - Changed 'status' dropdown to show Thai labels
 * - [GEMINI EDIT v10]
 * - Changed 'in_stock' dropdown to show Thai labels
 * - [GEMINI EDIT v11 - UX UPGRADE]
 * - Added "Loading..." overlay on form submit
 * - Replaced native confirm() with a custom, centered modal
 * - [GEMINI EDIT v12 - POLITE FIX]
 * - Made modal buttons equal width (flex: 1)
 * - Changed modal text to be polite
 * - [GEMINI EDIT v13 - CSS COLLISION FIX]
 * - Renamed modal button classes to prevent style conflicts
 * - [GEMINI EDIT v16 - CLIENT-SIDE VALIDATION]
 * - PHP constants (MAX_SIZE, ALLOWED_MIME) are now passed to JS
 * - Added new JS validateFile() helper
 * - setupMainImageDropZone now validates file on drop/change AND ALERTS
 * - setupDropZone (Gallery) now validates files on drop/change AND ALERTS
 * - [GEMINI EDIT v17 - 5MB LIMIT]
 * - Changed MAX_SIZE constants to 5MB (PHP)
 * - Passed new 5MB limit to JS validator
 * - Updated helper text to show 5MB
 * - [GEMINI EDIT v18 - FAKE JPG/HEIC FIX + ALERT BUTTON FIX]
 * - Added FileReader.onerror to catch "fake" jpgs (HEIC)
 * - Fixed ugly alert button CSS by creating .cmodal-btn-primary
 * - [GEMINI EDIT v19 - SAVE & CLOSE]
 * - Changed final header() redirect to 'index.php'
 */

// ================================================================
// 1) Auth & DB
// ================================================================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// [กูแก้] ลบไอ้เหี้ยนี่ทิ้งไปเลย มึงใช้ relative path ดีกว่า
// defined('BASE_URL') or define('BASE_URL', 'https://cmnsfixmac.com');

// [กูแก้] เพิ่ม CSRF Token (สำหรับฟอร์มหลัก)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ---------------- Helpers ----------------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function postv($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function post_arr($k){ return isset($_POST[$k]) && is_array($_POST[$k]) ? $_POST[$k] : []; }

function slugify($text) { /* ... (same slugify function) ... */
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $asciiText = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    if ($asciiText === false) { $patterns = ['/[[àáâãäå]]/u','/[æ]/u','/[ç]/u','/[èéêë]/u','/[ìíîï]/u','/[ñ]/u','/[òóôõö]/u','/[œ]/u','/[ùúûü]/u','/[ýÿ]/u']; $replacements = ['a','ae','c','e','i','n','o','oe','u','y']; $text = preg_replace($patterns, $replacements, $text); $text = preg_replace('/[^a-zA-Z0-9-]+/', '', $text); } else { $text = $asciiText; }
    $text = preg_replace('~[^-\w]+~', '', $text); $text = trim($text, '-'); $text = preg_replace('~-+~', '-', $text); $text = strtolower($text); return $text ?: ('n-a-' . time());
}
function ensure_unique_slug(PDO $pdo, string $baseSlug, ?int $skipId = null): string { /* ... (same ensure_unique_slug function) ... */
    $slug = $baseSlug; $i = 1; $sql = 'SELECT id FROM listings WHERE slug = :slug'.($skipId ? ' AND id <> :id' : ''); $st = $pdo->prepare($sql);
    while (true) { $params = [':slug' => $slug]; if ($skipId) $params[':id'] = $skipId; $st->execute($params); if (!$st->fetch(PDO::FETCH_ASSOC)) return $slug; $slug = $baseSlug . '-' . (++$i); }
}

// Upload config & helpers
// [กูแก้] ตั้งค่าขนาดไฟล์ตามใจมึง
const MAX_MAIN_SIZE = 5 * 1024 * 1024; // 5MB
const MAX_GALLERY_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp']; // อนุญาตไฟล์ต้นฉบับ
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp']; // อนุญาต Mime type ต้นฉบับ

// <-- [กูเพิ่ม] คำนวณ MB ไว้โชว์
$max_main_mb = round(MAX_MAIN_SIZE / 1024 / 1024, 1);
$max_gallery_mb = round(MAX_GALLERY_SIZE / 1024 / 1024, 1);
// --- [จบจุดที่เพิ่ม] ---

function sanitize_filename($name){ $name = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name); return substr($name, -180); }

// [กูแก้] อัปเกรดฟังก์ชันเช็คไฟล์
function valid_image_upload(array $file, int $maxSize, array $allowedMime): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) return false;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
    
    // [กูแก้] เช็ค MIME Type (ชนิดไฟล์) ก่อน... นี่คือตัวจริง
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) return false;

    // [กูแก้] เช็คนามสกุล (Ext) แค่กันไฟล์ประหลาดๆ... แต่ MIME คือตัวตัดสิน
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (empty($ext) || !in_array($ext, $GLOBALS['ALLOWED_EXT'], true)) {
        // ถ้า Ext ไม่ตรง... แต่ MIME ดันตรง (เช่น ไฟล์ .jpg แต่ตั้งชื่อ .txt)
        // เราควรเชื่อ MIME... แต่เดี๋ยวมันจะพัง...
        // สรุป: เช็คแม่งทั้งคู่แหละ... แต่เช็ค MIME ก่อนชัวร์สุด
        return false;
    }
    
    return true;
}

// [กูแก้!!] ลบ "เครื่องปั๊ม WebP" (convert_to_webp) ทิ้ง... โฮสต์มึงไม่มี GD!


// ---------------- Init ----------------
$id = max(0, (int)getv('id', 0));
$is_edit_mode = ($id > 0);
$page_title = $is_edit_mode ? "แก้ไขสินค้า (ID: $id)" : 'เพิ่มสินค้าใหม่';
$error = '';
$success = getv('saved') ? 'บันทึกข้อมูลเรียบร้อยแล้ว!' : '';

$upload_dir_path = __DIR__ . '/../../uploads/shops'; $upload_dir_url  = '/uploads/shops';
if (!is_dir($upload_dir_path)) {@mkdir($upload_dir_path, 0775, true);}

// --- ดึง Attributes ---
$all_attrs_flipped = $pdo->query("SELECT key_name, id FROM attrs ORDER BY key_name")->fetchAll(PDO::FETCH_KEY_PAIR);
$all_attrs_map = array_flip($all_attrs_flipped);

// Defaults (Added _en fields)
$product = ['title'=>'','title_en'=>'','brand'=>'','sku'=>'','price'=>0,'price_old'=>0,'stock_qty'=>1,'main_image'=>'','status'=>'published','in_stock'=>1,'slug'=>''];
$product_attrs = []; $gallery_urls_str = '';
$json_attrs = ['description'=>'','description_en'=>'','meta_description'=>'','tags'=>[]]; // Added description_en
$gallery_db_rows = []; // <-- [กูแก้] เปลี่ยนจาก $gallery_urls_arr เป็น $gallery_db_rows

// ================================================================
// 2) Handle POST (Save)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // [กูแก้] เช็ค CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Token ไม่ถูกต้อง, กรุณาลองใหม่';
    } else {
        try {
            $pdo->beginTransaction();
            $id = max(0, (int)postv('id', 0)); $is_edit_mode = ($id > 0);

            // [กูแก้!!] ผ่าตัด 2.1 (กลับไปใช้ move_uploaded_file)
            // 2.1 Main image
            $main_image_path = postv('existing_main_image', ''); $old_main_image = $main_image_path;
            if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                 
                 // 1. เช็คไฟล์ (ใช้ MAX_MAIN_SIZE)
                 if (!valid_image_upload($_FILES['main_image_file'], MAX_MAIN_SIZE, $ALLOWED_MIME)) { 
                    throw new Exception('ไฟล์รูปหลักไม่ถูกต้อง/ใหญ่เกิน/ชนิดผิด (สูงสุด ' . (MAX_MAIN_SIZE / 1024 / 1024) . 'MB)'); 
                 }
                 
                 // 2. [กูแก้] กลับไปใช้ชื่อไฟล์แบบเดิม (มี .ext)
                 $ext = strtolower(pathinfo($_FILES['main_image_file']['name'], PATHINFO_EXTENSION)); 
                 $filename = time() . '_' . sanitize_filename($_FILES['main_image_file']['name']);
                 
                 // 3. [กูแก้] ย้ายไฟล์แบบโง่ๆ (ไม่ต้องแปลง)
                 $target_file = $upload_dir_path . '/' . $filename; 
                 if (!move_uploaded_file($_FILES['main_image_file']['tmp_name'], $target_file)) { 
                    throw new Exception('ย้ายไฟล์รูปหลักไม่สำเร็จ'); 
                 }
                 
                 // 4. path ที่เก็บลง DB
                 $main_image_path = $upload_dir_url . '/' . $filename; 

                 // 5. ลบรูปเก่า (เหมือนเดิม)
                 if ($is_edit_mode && $old_main_image && $old_main_image !== $main_image_path) { 
                    $old_abs_path = realpath(__DIR__ . '/../../' . ltrim($old_main_image, '/')); 
                    $allowed_upload_path = realpath($upload_dir_path); 
                    if ($old_abs_path && $allowed_upload_path && strpos($old_abs_path, $allowed_upload_path) === 0 && file_exists($old_abs_path)) { 
                        @unlink($old_abs_path); 
                    } 
                 }
            }

            // 2.2 Form data + slug (No changes needed)
            $title = postv('title', 'N/A');
            $title_en = postv('title_en'); // Get English title
            $baseSlug = slugify($title); // Slug based on Thai title
            $slug = ensure_unique_slug($pdo, $baseSlug, $is_edit_mode ? $id : null);
            $product_data = [
                 'title'      => $title,
                 'title_en'   => $title_en,
                 'slug'       => $slug,
                 'brand'      => postv('brand'),
                 'sku'        => postv('sku'),
                 'price'      => (float)postv('price', 0),
                 'price_old'  => (float)postv('price_old', 0),
                 'stock_qty'  => (int)postv('stock_qty', 0),
                 'main_image' => $main_image_path,
                 'status'     => in_array(postv('status','draft'),['published','draft','pending']) ? postv('status','draft') : 'draft',
                 'in_stock'   => in_array((int)postv('in_stock',0),[0,1]) ? (int)postv('in_stock',0) : 0,
            ];
            $tags_str = postv('tags_str',''); $tags_arr = array_values(array_unique(array_filter(array_map('trim', explode(',', $tags_str)))));
            $json_blob_data = [
                'description'      => postv('description'),
                'description_en'   => postv('description_en'),
                'meta_description' => postv('meta_description'),
                'tags'             => $tags_arr
            ];
            $product_data['attrs'] = json_encode($json_blob_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            if ($product_data['attrs'] === false) { throw new Exception('JSON encoding failed: ' . json_last_error_msg()); }

            // 2.3 Upsert listing (No changes needed)
            if ($is_edit_mode) {
                $sql = "UPDATE listings SET
                            title=:title, title_en=:title_en, slug=:slug, brand=:brand, sku=:sku, price=:price, price_old=:price_old,
                            stock_qty=:stock_qty, main_image=:main_image, status=:status,
                            in_stock=:in_stock, attrs=:attrs
                        WHERE id=:id";
                $product_data['id'] = $id; $listing_id = $id;
            } else {
                $sql = "INSERT INTO listings
                            (title, title_en, slug, brand, sku, price, price_old, stock_qty, main_image, status, in_stock, attrs, created_at)
                        VALUES
                            (:title, :title_en, :slug, :brand, :sku, :price, :price_old, :stock_qty, :main_image, :status, :in_stock, :attrs, NOW())";
            }
            $st = $pdo->prepare($sql); if (!$st->execute($product_data)) { throw new PDOException("Execute failed: ".implode(", ",$st->errorInfo())); }
            if (!$is_edit_mode) $listing_id = (int)$pdo->lastInsertId(); if (!$listing_id) { throw new Exception("Failed to get listing ID."); }

            // 2.4 EAV attributes (No changes needed)
            $pdo->prepare('DELETE FROM listing_attr_values WHERE listing_id = ?')->execute([$listing_id]);
            $posted_attrs = post_arr('attrs');
            $st_insert_attr_value = $pdo->prepare('INSERT INTO listing_attr_values (listing_id, attr_id, value_string, value_int) VALUES (?, ?, ?, ?)');
            $current_attrs_flipped = $pdo->query("SELECT key_name, id FROM attrs")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($posted_attrs as $key_name => $value) {
                $value = trim((string)$value);
                if ($value === '' || !isset($current_attrs_flipped[$key_name])) continue;
                $attr_id = $current_attrs_flipped[$key_name];
                $is_int_key = in_array($key_name, ['year','ram_gb','ssd_gb','cycle_count'], true);
                $value_str = $is_int_key ? null : (string)$value; $value_int = $is_int_key ? (int)$value : null;
                $st_insert_attr_value->execute([$listing_id, $attr_id, $value_str, $value_int]);
            }

            // <-- [กูแก้!!] ผ่าตัดใหญ่ส่วนนี้ (เอา "มาร์คไว้ลบ" กลับมา)
            // 2.5 Gallery (Handle Deletions + Additions)
            
            // 2.5a: ลบรูปเก่าที่ user กด (X)
            $ids_to_delete = post_arr('deleted_gallery_ids');
            if ($is_edit_mode && !empty($ids_to_delete)) {
                $ids_to_delete = array_map('intval', $ids_to_delete);
                $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                
                // 1. [สำคัญ] ดึง URL ของไฟล์ที่จะลบ (เพื่อเอาไป unlink)
                $sql_get_urls = "SELECT url FROM listing_images WHERE listing_id = ? AND id IN ($placeholders)";
                $params_get_urls = array_merge([$listing_id], $ids_to_delete);
                $st_get_urls = $pdo->prepare($sql_get_urls);
                $st_get_urls->execute($params_get_urls);
                $urls_to_unlink = $st_get_urls->fetchAll(PDO::FETCH_COLUMN);

                // 2. ลบออกจาก DB
                $sql_delete = "DELETE FROM listing_images WHERE listing_id = ? AND id IN ($placeholders)";
                $params_delete = array_merge([$listing_id], $ids_to_delete);
                $st_delete = $pdo->prepare($sql_delete);
                $st_delete->execute($params_delete);

                // 3. ลบไฟล์ออกจาก Server (The REAL Fix)
                $webroot_path = realpath(__DIR__ . '/../../'); // -> /var/www/html
                $allowed_upload_path = realpath($upload_dir_path); // -> /var/www/html/uploads/shops

                foreach ($urls_to_unlink as $img_url) {
                    if (empty($img_url) || !$webroot_path || !$allowed_upload_path) {
                        error_log("Unlink failed (pre-check): Empty URL or bad webroot path.");
                        continue;
                    }

                    // 1. "ล้าง" URL จาก DB -> "/uploads/shops/pic.jpg"
                    // ลบ "https://domain.com" ทิ้ง ให้เหลือแค่ "/uploads/shops/pic.jpg"
                    $relative_path = preg_replace('~^https?://[^/]+~', '', $img_url);
                    
                    // 2. สร้าง Full Path (เป็นสตริง) -> "/var/www/html/uploads/shops/pic.jpg"
                    $file_path_string = $webroot_path . '/' . ltrim($relative_path, '/');
                    
                    // 3. [สำคัญ!] เช็คว่า "ไฟล์ (สตริง) นี้มีอยู่จริง"
                    if (file_exists($file_path_string)) {
                        
                        // 4. [สำคัญ!] "ตรวจสอบความปลอดภัย" ด้วย realpath
                        // ว่า path ที่ได้มา... มันอยู่ใน /uploads/shops จริงๆ
                        $real_file_path = realpath($file_path_string);
                        
                        if ($real_file_path && strpos($real_file_path, $allowed_upload_path) === 0) {
                            // 5. ถ้าทุกอย่าง OK -> ลบแม่ง!
                            @unlink($real_file_path);
                        } else {
                            // Path มันแปลกๆ (เช่น ../../... )
                            error_log("Unlink failed (Security Check): Path traversal attempt? " . $file_path_string);
                        }
                    } else {
                        // ไฟล์ไม่มีอยู่จริง (อาจจะลบไปแล้ว หรือ DB พัง)
                        error_log("Unlink failed (Not Found): File does not exist at path: " . $file_path_string);
                    }
                }
            }
            // <-- [จบจุดที่กูแก้!!]

            // 2.5b: นับจำนวนรูปที่เหลือ (เพื่อใช้ตั้ง sort_order)
            $st_count = $pdo->prepare("SELECT COUNT(*) FROM listing_images WHERE listing_id = ?");
            $st_count->execute([$listing_id]);
            $current_image_count = (int)$st_count->fetchColumn();
            $sort_order_start = $current_image_count;

            // [กูแก้!!] ผ่าตัด 2.5c (กลับไปใช้ move_uploaded_file)
            // 2.5c: อัปโหลดรูปใหม่
            $all_gallery_urls = [];
            if (isset($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['name']) && !empty($_FILES['gallery_files']['name'][0])) {
                
                // เช็คโควต้าที่เหลือ
                $new_file_count = count($_FILES['gallery_files']['name']);
                if (($current_image_count + $new_file_count) > 5) {
                    throw new Exception('รูปในคลังรวมของเก่าและใหม่ ห้ามเกิน 5 รูป');
                }
                
                $finfo_gallery = new finfo(FILEINFO_MIME_TYPE);
                foreach ($_FILES['gallery_files']['name'] as $idx => $name) {
                    $tmp_name = $_FILES['gallery_files']['tmp_name'][$idx] ?? ''; 
                    $error_code = $_FILES['gallery_files']['error'][$idx] ?? UPLOAD_ERR_NO_FILE; 
                    $size = $_FILES['gallery_files']['size'][$idx] ?? 0;

                    // 1. เช็คไฟล์ (ใช้ MAX_GALLERY_SIZE)
                    if ($error_code !== UPLOAD_ERR_OK || $size <= 0 || $size > MAX_GALLERY_SIZE || !is_uploaded_file($tmp_name)) continue;
                    $mime = $finfo_gallery->file($tmp_name); 
                    if (!in_array($mime, $ALLOWED_MIME, true)) continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); 
                    if (!in_array($ext, $ALLOWED_EXT, true)) continue;

                    // 2. [กูแก้] กลับไปใช้ชื่อไฟล์แบบเดิม (มี .ext)
                    $safe_name = sanitize_filename($name); 
                    $final_filename = $listing_id.'_'.time().'_'.$idx.'_'.$safe_name; 
                    
                    // 3. [กูแก้] ย้ายไฟล์แบบโง่ๆ
                    $target_path = $upload_dir_path.'/'.$final_filename;
                    if (move_uploaded_file($tmp_name, $target_path)) { 
                        $all_gallery_urls[] = $upload_dir_url.'/'.$final_filename; 
                    } else { 
                        error_log("Failed move gallery: ".$name." to ".$target_path); 
                    }
                }
            }
            
            // 2.5d: บันทึกรูปใหม่ลง DB
            if (!empty($all_gallery_urls)) {
                $st_insert_gallery = $pdo->prepare('INSERT INTO listing_images (listing_id, url, sort_order) VALUES (?, ?, ?)'); 
                $sort_order = $sort_order_start; // เริ่มนับต่อจากของเก่า
                foreach ($all_gallery_urls as $url) { 
                    if (trim($url) !== '') $st_insert_gallery->execute([$listing_id, trim($url), $sort_order++]); 
                }
            }
            // <-- [จบจุดที่กูแก้]

            $pdo->commit();
            
            // <-- [กูแก้!!] นี่คือบรรทัดที่มึงขอ!
            header('Location: index.php?saved=1&id='.$listing_id); 
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'บันทึกไม่สำเร็จ: '.$e->getMessage();
            error_log('[edit_listing.php Exception] '.$e->getMessage()."\n".$e->getTraceAsString());
        }
    } // จบ else (CSRF check)
} // จบ if ($_SERVER['REQUEST_METHOD'] === 'POST')

// ================================================================
// 3) Load for edit
// ================================================================
if ($is_edit_mode && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT * FROM listings WHERE id = ?'); $st->execute([$id]); $product = $st->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        $error = "ไม่พบสินค้า ID: $id"; $is_edit_mode = false; $id = 0;
        $product = ['title'=>'','title_en'=>'','brand'=>'','sku'=>'','price'=>0,'price_old'=>0,'stock_qty'=>1,'main_image'=>'','status'=>'published','in_stock'=>1,'slug'=>'']; // Added title_en default
    } else {
        // Decode JSON attributes
        if (!empty($product['attrs'])) {
             $tmp = json_decode($product['attrs'], true);
             if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                 $json_attrs['description'] = $tmp['description'] ?? '';
                 $json_attrs['description_en'] = $tmp['description_en'] ?? ''; // Get description_en
                 $json_attrs['meta_description'] = $tmp['meta_description'] ?? '';
                 $json_attrs['tags'] = $tmp['tags'] ?? [];
             }
        }
        // Fetch EAV attributes
        $sqlAttrs = 'SELECT a.key_name, v.value_string, v.value_int FROM listing_attr_values v JOIN attrs a ON a.id = v.attr_id WHERE v.listing_id = :id ORDER BY a.key_name';
        $s2 = $pdo->prepare($sqlAttrs); $s2->execute([':id' => $id]); $attrs_raw = $s2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attrs_raw as $attr) { $product_attrs[$attr['key_name']] = $attr['value_string'] ?? $attr['value_int']; }
        
        // <-- [กูแก้] ดึงทั้ง ID และ URL ของรูปเก่า
        $s3 = $pdo->prepare('SELECT id, url FROM listing_images WHERE listing_id = :id ORDER BY sort_order'); 
        $s3->execute([':id' => $id]); 
        $gallery_db_rows = $s3->fetchAll(PDO::FETCH_ASSOC);
        // <-- [จบจุดที่กูแก้]
    }
}

// ================================================================
// 4) HTML Output
// ================================================================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
  .admin-form-with-preview-layout{display:grid;grid-template-columns:1fr 340px;gap:30px;align-items:flex-start}
  .preview-column{position:sticky;top:20px}
  @media (max-width:992px){.admin-form-with-preview-layout{grid-template-columns:1fr;gap:40px}.preview-column{position:static;order:-1}}
  .drop-zone{border:2px dashed #ccc;border-radius:8px;padding:25px;text-align:center;color:#777;cursor:pointer;transition:all .2s}
  .drop-zone.is-dragover{border-color:var(--primary,#007aff);background:var(--primary-ghost,#f4f8ff)}
  .drop-zone input[type=file]{display:none}
  .image-preview{display:flex;flex-wrap:wrap;gap:10px;margin-top:15px}
  .image-preview-item{position:relative;border:1px solid var(--ui-border,#ddd);border-radius:5px;padding:5px;background:var(--ui-bg-alt,#f9f9f9)}
  .image-preview-item img{max-width:100px;max-height:100px;object-fit:cover;border-radius:4px;display:block}

  /* <-- [กูแก้] เพิ่ม CSS สำหรับปุ่มลบ (X) บนรูปพรีวิว */
  .js-delete-preview {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 22px;
      height: 22px;
      background: #c00;
      color: white;
      border: 2px solid white;
      border-radius: 50%;
      font-size: 14px;
      font-weight: bold;
      line-height: 18px; /* ปรับให้กากบาทอยู่กลาง */
      text-align: center;
      cursor: pointer;
      padding: 0;
      z-index: 2;
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
  }
  .js-delete-preview:hover {
      background: #a00;
  }
  /* <-- [จบจุดที่กูแก้] */

  /* [กูแก้] CSS สำหรับซ่อนรูปที่มาร์คไว้ว่าลบ */
  .is-marked-for-delete { display: none !important; }

  .existing-image img{border:2px solid var(--primary,#007aff)}
  .eav-item{margin-bottom:10px}
  .eav-item .form-group{margin-bottom:0!important}
  /* ... (CSS การ์ดพรีวิว เหมือนเดิม) ... */
  .cmnsx-card{background:var(--ui-surface,#fff);border-radius:var(--radius-lg,16px);box-shadow:var(--shadow-card,0 4px 12px rgba(0,0,0,.08));overflow:hidden;font-family:inherit;position:relative}
  .cmnsx-thumb{display:block;position:relative;background:var(--ui-bg-alt,#f4f4f4);padding-bottom:100%;height:0;overflow:hidden}
  .cmnsx-thumb-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--ui-muted,#ccc)}
  .cmnsx-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .cmnsx-badge{position:absolute;top:12px;left:12px;background:rgba(0,0,0,.7);color:#fff;padding:4px 10px;border-radius:999px;font-size:.8rem;font-weight:600;z-index:2}
  .cmnsx-info{padding:16px}
  .cmnsx-cat{font-size:.75rem;font-weight:600;color:var(--ui-muted,#888);text-transform:uppercase;margin-bottom:4px}
  .cmnsx-name{font-size:1.1rem;font-weight:600;color:var(--text-default,#222);margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:3.08rem}
  .cmnsx-name a{text-decoration:none;color:inherit}
  .cmnsx-low{font-size:.9rem;color:var(--danger,#d9534f);font-weight:500;margin-top:8px}
  .cmnsx-price{display:flex;align-items:baseline;gap:8px;margin-top:8px;margin-bottom:12px}
  .cmnsx-price-now{font-size:1.5rem;font-weight:700;color:var(--text-default,#333)}
  .cmnsx-price-old{font-size:1rem;color:var(--ui-muted,#aaa);text-decoration:line-through}
  .cart-on-price{margin-left:auto;background:var(--text-default,#333);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center}
  .btn-line-full{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#50c14e;color:#fff;font-weight:600;text-decoration:none;border-radius:var(--radius,8px);box-sizing:border-box}
  .msg{padding:15px;border-radius:5px;margin-bottom:20px;font-weight:500}
  .msg-error{background:#ffebee;color:#c62828;border:1px solid #c62828}
  .msg-success{background:#e8f5e9;color:#2e7d32;border:1px solid #2e7d32}
  textarea.small{min-height:80px}

  /* --- [กูแก้] CSS สำหรับ Modal --- */

  /* [กูเพิ่ม] คลาสตัวช่วยบอกขนาดไฟล์ */
  .form-helper-text {
      font-size: 0.85rem;
      color: #555;
      margin: -5px 0 10px;
      line-height: 1.4;
  }

  /* 1. Modal "กำลังโหลด" */
  .loading-overlay {
    display: none; /* ซ่อนไว้ก่อน */
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9998;
    /* display: flex; <-- ปล่อย JS จัดการ */
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    font-weight: 600;
  }
  .loading-overlay.show {
    display: flex;
  }
  .loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary, #007aff);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* 2. Modal "ยืนยัน" */
  .confirm-overlay {
    display: none; /* ซ่อนไว้ก่อน */
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    /* display: flex; <-- ปล่อย JS จัดการ */
    align-items: center;
    justify-content: center;
  }
  .confirm-overlay.show {
    display: flex;
  }
  .confirm-dialog {
    background: var(--ui-surface, #fff);
    padding: 25px 30px;
    border-radius: var(--radius-lg, 16px);
    box-shadow: var(--shadow-card, 0 4px 12px rgba(0,0,0,.08));
    width: 90%;
    max-width: 400px;
    text-align: center;
  }
  .confirm-dialog p {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 20px;
    color: var(--text-default, #222);
  }
  .confirm-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
  }
  
  /* [กูแก้!!] สร้างคลาสปุ่มกลาง */
  .cmodal-btn-cancel,
  .cmodal-btn-confirm,
  .cmodal-btn-primary {
    flex: 1; /* <-- ทำให้ปุ่มกว้างเท่ากัน */
    padding: 10px 20px;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    line-height: 1.5;
    border-radius: 6px;
    transition: background-color 0.2s, border-color 0.2s;
  }
  .cmodal-btn-cancel {
    background-color: #f0f0f0;
    color: #333;
    border: 1px solid #ccc;
  }
  .cmodal-btn-cancel:hover {
    background-color: #e0e0e0;
  }
  .cmodal-btn-confirm {
    background: #c00;
    color: white;
    border: 1px solid #c00;
  }
  .cmodal-btn-confirm:hover {
    background: #a00;
  }
  /* [กูเพิ่ม!!] ปุ่มสีน้ำเงินสำหรับ "ตกลง" */
  .cmodal-btn-primary {
    background: var(--primary, #007aff);
    color: white;
    border: 1px solid var(--primary, #007aff);
  }
  .cmodal-btn-primary:hover {
    background: #0056b3;
  }

  /* 3. Modal "แจ้งเตือน" (Alert) */
  .alert-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10000; /* ให้อยู่เหนือทุกสิ่ง */
    align-items: center;
    justify-content: center;
  }
  .alert-overlay.show {
    display: flex;
  }
  .alert-dialog {
    background: var(--ui-surface, #fff);
    padding: 25px 30px;
    border-radius: var(--radius-lg, 16px);
    box-shadow: var(--shadow-card, 0 4px 12px rgba(0,0,0,.08));
    width: 90%;
    max-width: 400px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .alert-icon {
    font-size: 48px;
    color: #f59e0b; /* สีเหลือง/ส้ม */
    margin-bottom: 15px;
    user-select: none;
  }
  .alert-dialog p {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 20px;
    color: var(--text-default, #222);
    white-space: pre-wrap; /* ให้ \n ทำงาน */
  }
  .alert-actions {
    width: 100%;
  }
  /* --- [จบจุดที่กูแก้] --- */
</style>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($page_title) ?> (ตาราง Listings)</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <div class="admin-form-with-preview-layout">
    <div class="form-column">
      <form action="edit_listing.php<?= $is_edit_mode ? '?id='.$id : '' ?>" method="POST" enctype="multipart/form-data" class="form-section">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <input type="hidden" id="csrf_token" name="csrf_token" value="<?= h($CSRF) ?>">
        
        <?php if ($error): ?><div class="msg msg-error"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg msg-success"><?= h($success) ?></div><?php endif; ?>
        
        <div id="deleted_gallery_ids_wrapper"></div>

        <h2 class="form-h2">ข้อมูลหลัก</h2>
        <div class="form-grid">
          <div class="form-group full-width"><label for="title">ชื่อสินค้า (Title TH) *</label><input type="text" id="title" name="title" value="<?= h($product['title']) ?>" required></div>
          <div class="form-group full-width"><label for="title_en">ชื่อสินค้า (Title EN)</label><input type="text" id="title_en" name="title_en" value="<?= h($product['title_en'] ?? '') ?>"></div> {/* Added title_en input */}
          <div class="form-group"><label for="brand">แบรนด์</label><input type="text" id="brand" name="brand" value="<?= h($product['brand']) ?>"></div>
          <div class="form-group"><label for="sku">SKU</label><input type="text" id="sku" name="sku" value="<?= h($product['sku']) ?>"></div>
          <div class="form-group"><label for="price">ราคาขาย *</label><input type="number" id="price" name="price" value="<?= h($product['price']) ?>" step="0.01" min="0" required></div>
          <div class="form-group"><label for="price_old">ราคาเดิม</label><input type="number" id="price_old" name="price_old" value="<?= h($product['price_old']) ?>" step="0.01" min="0"></div>
          <div class="form-group"><label for="stock_qty">จำนวนสต็อก</label><input type="number" id="stock_qty" name="stock_qty" value="<?= h($product['stock_qty']) ?>" step="1" min="0"></div>
          
          <div class="form-group full-width">
            <label for="main_image_drop_zone">รูปภาพหลัก (ลากใส่ หรือ คลิก)</label>
            <p class="form-helper-text">อนุญาต .jpg, .png, .webp (สูงสุด <?= $max_main_mb ?>MB).</p>
            <input type="file" id="main_image_file" name="main_image_file" accept="image/*">
            <div class="drop-zone" id="main_image_drop_zone"><span class="material-symbols-rounded" style="font-size:32px">upload_file</span><div>ลากไฟล์มาใส่ หรือ คลิกเพื่อเลือก (อัปโหลดใหม่จะทับรูปเดิม)</div></div>
            <div class="image-preview" id="main_image_preview">
              <?php if ($is_edit_mode && $product['main_image']): ?>
                <div class="image-preview-item existing-image"><img src="<?= h($product['main_image']) ?>" alt="Existing Image"></div>
              <?php endif; ?>
            </div>
            <input type="hidden" name="existing_main_image" value="<?= h($product['main_image']) ?>">
          </div>
          
          <div class="form-group"><label for="status">สถานะ</label><select id="status" name="status">
              <option value="published" <?= ($product['status']??'') === 'published' ? 'selected' : '' ?>>เผยแพร่</option>
              <option value="draft" <?= ($product['status']??'') === 'draft' ? 'selected' : '' ?>>ฉบับร่าง</option>
              <option value="pending" <?= ($product['status']??'') === 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
          </select></div>
          
          <div class="form-group"><label for="in_stock">สถานะสต็อก</label><select id="in_stock" name="in_stock">
              <option value="1" <?= (int)($product['in_stock']??1) === 1 ? 'selected' : '' ?>>มีสินค้า</option>
              <option value="0" <?= (int)($product['in_stock']??1) === 0 ? 'selected' : '' ?>>สินค้าหมด</option>
          </select></div>
        </div>

        <h2 class="form-h2">รายละเอียด & SEO (JSON)</h2>
        <div class="form-grid">
          <div class="form-group full-width"><label for="description">รายละเอียดสินค้า (Description TH)</label><textarea id="description" name="description" class="rich-text"><?= h($json_attrs['description'] ?? '') ?></textarea></div>
          <div class="form-group full-width"><label for="description_en">รายละเอียดสินค้า (Description EN)</label><textarea id="description_en" name="description_en" class="rich-text"><?= h($json_attrs['description_en'] ?? '') ?></textarea></div> {/* Added description_en textarea */}
          <div class="form-group full-width"><label for="meta_description">Meta Description (SEO)</label><textarea id="meta_description" name="meta_description" class="small" maxlength="160"><?= h($json_attrs['meta_description'] ?? '') ?></textarea></div>
          <div class="form-group full-width"><label for="tags_str">Tags (คั่นด้วย ,)</label><input type="text" id="tags_str" name="tags_str" value="<?= h(implode(', ', $json_attrs['tags'] ?? [])) ?>" placeholder="M2, มือสอง, เชียงใหม่"></div>
        </div>

        <h2 class="form-h2">สเปคสินค้า (EAV)</h2>
        <p style="font-size:.9rem;color:#555;margin-top:0">(กรอกค่าสำหรับสเปคที่มีอยู่ / หากต้องการเพิ่มสเปคใหม่ ต้องไปเพิ่มในตาราง `attrs` ก่อน)</p>
        <div class="form-grid">
            <?php foreach ($all_attrs_map as $attr_id => $key_name): ?>
                <div class="form-group">
                    <label for="attr_<?= h($key_name) ?>"><?= h(ucwords(str_replace('_', ' ', $key_name))) ?></label>
                    <input type="text" id="attr_<?= h($key_name) ?>" name="attrs[<?= h($key_name) ?>]" value="<?= h($product_attrs[$key_name] ?? '') ?>" placeholder="<?= h(ucwords(str_replace('_', ' ', $key_name))) ?>...">
                </div>
            <?php endforeach; ?>
            <?php if (empty($all_attrs_map)): ?>
                <p style="color: red; grid-column: 1 / -1;">ไม่มีสเปคในระบบ! กรุณาเพิ่ม `key_name` ในตาราง `attrs` ก่อน</p>
            <?php endif; ?>
        </div>

        <h2 class="form-h2">คลังรูปภาพ (Gallery)</h2>
        <p style="font-size:.9rem;color:#555;margin-top:0">(ระบบจะบันทึกเฉพาะรูปใหม่ที่อัปโหลดเพิ่ม / รูปเก่าที่กด (X) จะถูกลบถาวร)</p>
        <div class="form-grid">
          <div class="form-group full-width">
            <label for="gallery_drop_zone">อัปโหลดรูป Gallery (ลากใส่ได้, รวมไม่เกิน 5 รูป)</label>
            <p class="form-helper-text">อนุญาต .jpg, .png, .webp (ไฟล์ละไม่เกิน <?= $max_gallery_mb ?>MB).</p>
            <input type="file" id="gallery_files_input" name="gallery_files[]" multiple accept="image/*">
            <div class="drop-zone" id="gallery_drop_zone"><span class="material-symbols-rounded" style="font-size:32px">upload_file</span><div id="gallery_drop_text">ลากไฟล์มาใส่ หรือ คลิกเพื่อเลือก (เพิ่มได้หลายรูป สูงสุด 5 รูป)</div></div>
            
            <div class="image-preview" id="gallery_preview">
              <?php // 1. แสดงรูปเก่า (Existing)
                if ($is_edit_mode && !empty($gallery_db_rows)): 
                  foreach ($gallery_db_rows as $row): 
              ?>
                <div class="image-preview-item existing-image" data-id="<?= (int)$row['id'] ?>">
                  <img src="<?= h($row['url']) ?>" alt="Existing Gallery Image">
                  <button type="button" class="js-delete-preview js-delete-existing" aria-label="ลบรูปเก่า">&times;</button>
                </div>
              <?php 
                  endforeach; 
                endif; 
              ?>
              <?php // 2. ส่วนพรีวิวของ "รูปใหม่" (New) จะถูกสร้างโดย JS มาต่อท้ายตรงนี้ ?>
            </div>
            <textarea name="gallery_urls_str" style="display:none;"><?= h($gallery_urls_str) ?></textarea>
          </div>
        </div>

        <div class="actions">
          <button type="submit" class="btn-primary">
            <span class="material-symbols-rounded" style="vertical-align:middle;font-size:1.25em">save</span>
            <?= $is_edit_mode ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มสินค้าใหม่' ?>
          </button>
          <?php if ($is_edit_mode): ?>
            <a href="../../shop/product-detail.php?id=<?= $id ?>" class="btn-secondary" target="_blank">
              <span class="material-symbols-rounded" style="vertical-align:middle;font-size:1.25em">visibility</span> ดูหน้าเว็บ
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    
    <div class="preview-column">
      <div class="sticky-preview">
        <h2 class="form-h2" style="margin:0 0 15px;padding-bottom:10px;border-bottom:1px solid #eee">พรีวิว</h2>
        <ul class="cmnsx-grid" style="display:block;max-width:300px;margin:0 auto">
          <li class="cmnsx-card">
            <div class="cmnsx-badge" id="preview-badge" style="display:none">ลด 0 ฿ (-0%)</div>
            <a href="#" class="cmnsx-thumb" onclick="return false;">
              <div class="cmnsx-thumb-icon" id="preview-image-placeholder"><span class="material-symbols-rounded" aria-hidden="true" style="font-size:48px">image</span></div>
              <img src="" alt="Preview" class="cmnsx-img" id="preview-image" style="display:none">
            </a>
            <div class="cmnsx-info">
              <div class="cmnsx-cat" id="preview-category">MACBOOK</div>
              <h3 class="cmnsx-name"><a href="#" class="cmnsx-link" id="preview-name" onclick="return false;">ชื่อสินค้า...</a></h3>
              <div class="cmnsx-low" id="preview-stock" style="display:none">• สินค้าใกล้หมดแล้ว</div>
              <div class="cmnsx-price">
                <span class="cmnsx-price-now" id="preview-price-now">฿0</span>
                <span class="cmnsx-price-old" id="preview-price-old" style="display:none">฿0</span>
                <button class="cart-on-price" type="button" aria-label="ใส่ตะกร้า" disabled><span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span></button>
              </div>
              <a class="btn-line-full" href="#" onclick="return false;" style="pointer-events:none"><span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span> สั่งผ่าน LINE</a>
            </div>
          </li>
        </ul>
      </div></div></div>
</main>

<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-spinner"></div>
  <p>กำลังบันทึกข้อมูล...</p>
</div>

<div id="alertOverlay" class="alert-overlay">
  <div class="alert-dialog">
    <span class="alert-icon material-symbols-rounded">warning</span>
    <p id="alertMessage">เกิดข้อผิดพลาด</p>
    <div class="alert-actions">
      <button type="button" id="alertBtnOk" class="cmodal-btn-primary">ตกลง</button>
    </div>
  </div>
</div>

<div id="confirmOverlay" class="confirm-overlay">
  <div class="confirm-dialog">
    <p id="confirmMessage">คุณต้องการลบรูปนี้ใช่หรือไม่?</p>
    <div class="confirm-actions">
      <button type="button" id="confirmBtnCancel" class="cmodal-btn-cancel">ยกเลิก</button>
      <button type="button" id="confirmBtnOk" class="cmodal-btn-confirm">ยืนยัน</button>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
  tinymce.init({
    selector: 'textarea.rich-text', // Applied to both description textareas
    menubar: false, height: 300,
    plugins: 'lists link image paste code help wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image | removeformat | code | help',
    branding: false, content_style: 'body { font-family: Sarabun, sans-serif; font-size: 15px; }'
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', () => {

    // --- [กูแก้] Logic "กำลังบันทึก" ---
    const mainForm = document.querySelector('.form-section');
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (mainForm && loadingOverlay) {
        mainForm.addEventListener('submit', () => {
            // โชว์ overlay ทันทีที่กด submit
            loadingOverlay.classList.add('show');
            // ไม่ต้องดีเลย์... ปล่อยมันโหลดตอน submit... พอ PHP redirect หน้าใหม่ overlay ก็หายเอง
        });
    }

    // --- [กูแก้] Logic "ยืนยันกลางจอ" ---
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmMsgEl = document.getElementById('confirmMessage');
    const confirmOkBtn = document.getElementById('confirmBtnOk');
    const confirmCancelBtn = document.getElementById('confirmBtnCancel');
    let confirmCallback = null; // ตัวเก็บ "สิ่งที่ต้องทำ"

    function showCustomConfirm(message, onConfirm) {
        if (!confirmOverlay || !confirmMsgEl) {
            // Fallback (ถ้า modal พัง) ไปใช้ของกากๆ เหมือนเดิม
            if (confirm(message)) {
                onConfirm();
            }
            return;
        }
        
        confirmMsgEl.textContent = message;
        confirmCallback = onConfirm; // เก็บ "สิ่งที่ต้องทำ"
        confirmOverlay.classList.add('show');
    }

    confirmCancelBtn.addEventListener('click', () => {
        confirmOverlay.classList.remove('show');
        confirmCallback = null; // ล้างค่า
    });
    
    confirmOkBtn.addEventListener('click', () => {
        if (typeof confirmCallback === 'function') {
            confirmCallback(); // รัน "สิ่งที่ต้องทำ"
        }
        confirmOverlay.classList.remove('show');
        confirmCallback = null;
    });
    // --- [จบ Logic ยืนยันกลางจอ] ---


    // --- [กูเพิ่ม!!] Logic "แจ้งเตือน" กลางจอ ---
    const alertOverlay = document.getElementById('alertOverlay');
    const alertMsgEl = document.getElementById('alertMessage');
    const alertOkBtn = document.getElementById('alertBtnOk');

    function showCustomAlert(message) {
        if (!alertOverlay || !alertMsgEl) {
            alert(message); // Fallback
            return;
        }
        alertMsgEl.textContent = message;
        alertOverlay.classList.add('show');
    }
    alertOkBtn.addEventListener('click', () => {
        alertOverlay.classList.remove('show');
    });
    // --- [จบ Logic แจ้งเตือน] ---


    // --- [กูแก้!!] สร้าง "เครื่องเช็คไฟล์" ---
    // 1. ดึงค่าจาก PHP
    const MAX_FILE_SIZE_MAIN = <?= MAX_MAIN_SIZE ?>;
    const MAX_FILE_SIZE_GALLERY = <?= MAX_GALLERY_SIZE ?>;
    const ALLOWED_MIME_TYPES = <?= json_encode($ALLOWED_MIME) ?>;
    const ALLOWED_EXTS = <?= json_encode($ALLOWED_EXT) ?>;
    const MAX_MB_MAIN = <?= $max_main_mb ?>;
    const MAX_MB_GALLERY = <?= $max_gallery_mb ?>;

    // 2. ฟังก์ชัน "เครื่องเช็ค"
    function validateFile(file, maxSize, maxMb, allowedMimes, allowedExts) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        
        if (!allowedMimes.includes(file.type) || !allowedExts.includes(ext)) {
            return `ไฟล์ชนิด "${ext || '??'}" ใช้งานไม่ได้ (ต้องเป็น .jpg, .png, .webp เท่านั้น)`;
        }
        if (file.size > maxSize) {
            return `ไฟล์ "${file.name}" (${(file.size / 1024 / 1024).toFixed(1)}MB) ใหญ่เกิน (สูงสุด ${maxMb}MB)`;
        }
        return null; // No error
    }
    // --- [จบ "เครื่องเช็คไฟล์"] ---


    // ---------- 1. Drop Zone (Gallery) ----------
    const MAX_GALLERY_FILES = 5;
    const galleryDropZone = document.getElementById('gallery_drop_zone');
    const galleryFileInput = document.getElementById('gallery_files_input');
    const galleryPreviewEl = document.getElementById('gallery_preview');
    const galleryDropText = document.getElementById('gallery_drop_text');
    const deletedIdsWrapper = document.getElementById('deleted_gallery_ids_wrapper'); // เอากลับมา

    let newFileArray = []; // เก็บ "ไฟล์ใหม่" ที่รออัปโหลด

    // [สมอง] ใช้นับจำนวนโควต้าที่เหลือ
    function getAvailableSlots() {
        // [กูแก้] นับรูปเก่าที่ไม่ถูก "มาร์ค"
        const existingCount = galleryPreviewEl.querySelectorAll('.existing-image:not(.is-marked-for-delete)').length;
        const newCount = newFileArray.length;
        const available = MAX_GALLERY_FILES - existingCount - newCount;
        
        if (galleryDropText) {
            if (available <= 0) {
                galleryDropText.textContent = `อัปโหลดครบ ${MAX_GALLERY_FILES} รูปแล้ว (เต็ม)`;
                galleryDropZone.style.display = 'none'; // ซ่อน Dropzone ถ้าเต็ม
            } else {
                galleryDropZone.style.display = 'block'; // โชว์ไว้
                galleryDropText.textContent = `ลากไฟล์มาใส่ หรือ คลิก (เหลือ ${available} รูป)`;
            }
        }
        return available;
    }

    // [ฟังก์ชันเดิม] ใช้อัปเดต <input type="file"> และ พรีวิวของ "ไฟล์ใหม่"
    function updateNewFilesAndPreview() {
        const dt = new DataTransfer();
        galleryPreviewEl.querySelectorAll('.js-preview-item.new-upload').forEach(el => el.remove());

        newFileArray.forEach((file, i) => {
            dt.items.add(file); // ยัดไฟล์ใส่ถัง

            const item = document.createElement('div'); 
            item.className = 'image-preview-item js-preview-item new-upload'; 
            item.dataset.index = i; 

            const img = document.createElement('img'); 
            img.alt = file.name; 
            item.appendChild(img); 
            
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'js-delete-preview js-delete-new';
            delBtn.innerHTML = '&times;';
            delBtn.setAttribute('aria-label', 'ลบรูปใหม่นี้');
            item.appendChild(delBtn);

            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; };
            // [กูแก้!!] เพิ่มตัวดักไฟล์ "ไส้เน่า" (HEIC)
            reader.onerror = () => {
                showCustomAlert(`ไฟล์ "${file.name}" เสียหายหรืออ่านไม่ได้ (อาจเป็น HEIC ที่ถูกเปลี่ยนชื่อ)`);
                // ทำลายตัวเอง
                item.remove();
                newFileArray.splice(i, 1); // เอาออกจาก Array
                updateNewFilesAndPreview(); // วาดใหม่
            };
            reader.readAsDataURL(file);

            galleryPreviewEl.appendChild(item); // แปะพรีวิว
        });

        galleryFileInput.files = dt.files;
        getAvailableSlots(); // อัปเดตโควต้า
    }

    // --- 1. Event พื้นฐาน (Click, Dragover) ---
    galleryDropZone.addEventListener('click', () => galleryFileInput.click());
    galleryDropZone.addEventListener('dragover', e => { e.preventDefault(); galleryDropZone.classList.add('is-dragover'); });
    ['dragleave', 'dragend'].forEach(t => galleryDropZone.addEventListener(t, () => galleryDropZone.classList.remove('is-dragover')));

    // --- 2. [กูแก้] Event วางไฟล์ (Drop) ---
    galleryDropZone.addEventListener('drop', e => {
        e.preventDefault();
        galleryDropZone.classList.remove('is-dragover');
        if (!e.dataTransfer.files.length) return;

        let available = getAvailableSlots();
        const newFiles = Array.from(e.dataTransfer.files);
        let filesAddedCount = 0;
        let errorMessages = [];

        for (const file of newFiles) {
            if (available <= 0) break; 
            
            // [กูแก้] เรียก "เครื่องเช็ค"
            const error = validateFile(file, MAX_FILE_SIZE_GALLERY, MAX_MB_GALLERY, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            if (error) {
                errorMessages.push(error);
                continue; // ข้ามไฟล์นี้
            }
            
            newFileArray.push(file); // [เพิ่ม] ต่อท้าย Array
            filesAddedCount++;
            available--; // ลดโควต้า
        }

        if (errorMessages.length > 0) {
            showCustomAlert("มีบางไฟล์อัปโหลดไม่ได้:\n- " + errorMessages.join("\n- "));
        }
        
        updateNewFilesAndPreview(); // อัปเดต input + พรีวิว
    });

    // --- 3. [กูแก้] Event เลือกไฟล์ (Change) ---
    galleryFileInput.addEventListener('change', function(e) {
        const newFiles = Array.from(this.files); 
        if (!newFiles.length) return;
        
        newFileArray = []; // รีเซ็ตไฟล์ใหม่ทุกครั้งที่ "เลือก"
        let available = getAvailableSlots();
        let filesAddedCount = 0;
        let errorMessages = [];

        for (const file of newFiles) {
            if (available <= 0) break;

            // [กูแก้] เรียก "เครื่องเช็ค"
            const error = validateFile(file, MAX_FILE_SIZE_GALLERY, MAX_MB_GALLERY, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            if (error) {
                errorMessages.push(error);
                continue; // ข้ามไฟล์นี้
            }
            
            newFileArray.push(file); // [เพิ่ม] ต่อท้าย Array
            filesAddedCount++;
            available--;
        }

        if (errorMessages.length > 0) {
            showCustomAlert("มีบางไฟล์อัปโหลดไม่ได้:\n- " + errorMessages.join("\n- "));
        }
        updateNewFilesAndPreview(); // อัปเดต input + พรีวิว
    });


    // --- 4. [กูแก้] Event ปุ่มลบ (X) ---
    galleryPreviewEl.addEventListener('click', function(e) {
        
        // 4a. ลบ "รูปเก่า" (Existing) -> "มาร์ค" ไว้ลบ
        if (e.target.classList.contains('js-delete-existing')) {
            e.preventDefault();
            const item = e.target.closest('.existing-image');
            const id = item.dataset.id;
            if (!id || item.classList.contains('is-marked-for-delete')) return;
            
            // [กูแก้] เรียกใช้ Modal กลางจอ (แบบสุภาพ)
            showCustomConfirm('คุณต้องการลบรูปนี้ (ตอนกดบันทึก) ใช่หรือไม่?', () => {
                // นี่คือ "สิ่งที่ต้องทำ" (callback) พอกด OK
                item.classList.add('is-marked-for-delete'); 
                
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'deleted_gallery_ids[]';
                hiddenInput.value = id;
                deletedIdsWrapper.appendChild(hiddenInput); // ยัดใส่ wrapper
                
                getAvailableSlots(); // อัปเดตโควต้า
            });
        }

        // 4b. ลบ "รูปใหม่" (New Upload) -> ลบจาก Array
        if (e.target.classList.contains('js-delete-new')) {
            e.preventDefault();
            const item = e.target.closest('.js-preview-item');
            const indexToRemove = parseInt(item.dataset.index, 10);
            if (isNaN(indexToRemove)) return;

            // [กูแก้] เรียกใช้ Modal กลางจอ (แบบสุภาพ)
            showCustomConfirm('คุณต้องการลบรูปใหม่ (ที่ยังไม่อัปโหลด) นี้ใช่หรือไม่?', () => {
                if (indexToRemove >= 0 && indexToRemove < newFileArray.length) {
                    newFileArray.splice(indexToRemove, 1); // ลบออกจาก Array
                }
                updateNewFilesAndPreview(); // อัปเดต input + พรีวิว
            });
        }
    });

    // --- 5. รันครั้งแรก (Initial Setup) ---
    getAvailableSlots(); // คำนวณโควต้าครั้งแรกที่โหลดหน้า
    
    // [กูแก้!!] ผ่าตัด "รูปหลัก"
    (function setupMainImageDropZone() {
        const dropZoneEl = document.getElementById('main_image_drop_zone');
        const fileInputEl = document.getElementById('main_image_file');
        const previewEl = document.getElementById('main_image_preview');
        if (!dropZoneEl || !fileInputEl || !previewEl) return;
        
        const cardImgEl = document.getElementById('preview-image');
        const placeholderEl = document.getElementById('preview-image-placeholder');

        function handleMainPreview(file) {
            previewEl.innerHTML = ''; // ล้างของเก่า
            
            if (!file) {
                if(cardImgEl){ cardImgEl.src=''; cardImgEl.style.display='none'; } 
                if(placeholderEl) placeholderEl.style.display='flex';
                // ล้างรูปเก่า (ถ้ามี)
                const existing = document.querySelector('input[name="existing_main_image"]');
                if (existing) existing.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = e => { 
                const url = e.target.result;
                // พรีวิวในฟอร์ม
                const item = document.createElement('div');
                item.className = 'image-preview-item js-preview-item';
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                item.appendChild(img);
                previewEl.appendChild(item);
                
                // พรีวิวในการ์ด
                if (cardImgEl){ 
                    cardImgEl.src = url; 
                    cardImgEl.style.display = 'block'; 
                    if(placeholderEl) placeholderEl.style.display = 'none'; 
                }
            };
            
            // [กูแก้!!] เพิ่มตัวดักไฟล์ "ไส้เน่า" (HEIC)
            reader.onerror = () => {
                showCustomAlert(`ไฟล์ "${file.name}" เสียหายหรืออ่านไม่ได้ (อาจเป็น HEIC ที่ถูกเปลี่ยนชื่อ)`);
                fileInputEl.value = null; // [CRITICAL] Clear the input
                handleMainPreview(null); // Clear the preview
            };
            
            reader.readAsDataURL(file);
        }
        
        function createFileList(file){ if(!file) return null; const dt=new DataTransfer(); dt.items.add(file); return dt.files; }

        dropZoneEl.addEventListener('click', () => fileInputEl.click());
        dropZoneEl.addEventListener('dragover', e => { e.preventDefault(); dropZoneEl.classList.add('is-dragover'); });
        ['dragleave','dragend'].forEach(t => dropZoneEl.addEventListener(t, () => dropZoneEl.classList.remove('is-dragover')));
        
        // [กูแก้] Event Drop รูปหลัก
        dropZoneEl.addEventListener('drop', e => {
            e.preventDefault(); 
            dropZoneEl.classList.remove('is-dragover');
            if (!e.dataTransfer.files.length) return;
            
            const file = e.dataTransfer.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_MAIN, MAX_MB_MAIN, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            
            if (error) {
                showCustomAlert(error);
                return;
            }
            
            fileInputEl.files = createFileList(file);
            handleMainPreview(file);
        });
        
        // [กูแก้] Event Change รูปหลัก
        fileInputEl.addEventListener('change', function() {
            if (!this.files.length) {
                handleMainPreview(null);
                return;
            }
            
            const file = this.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_MAIN, MAX_MB_MAIN, ALLOWED_MIME_TYPES, ALLOWED_EXTS);

            if (error) {
                showCustomAlert(error);
                this.value = null; // ล้างไฟล์ที่เลือก
                handleMainPreview(null);
                return;
            }

            handleMainPreview(file);
        });
    })();


    // ---------- 2. Live Preview (เหมือนเดิม) ----------
    const formInputs = {title:document.getElementById('title'), brand:document.getElementById('brand'), price:document.getElementById('price'), priceOld:document.getElementById('price_old'), stockQty:document.getElementById('stock_qty')};
    const preview = {name:document.getElementById('preview-name'), category:document.getElementById('preview-category'), priceNow:document.getElementById('preview-price-now'), priceOld:document.getElementById('preview-price-old'), badge:document.getElementById('preview-badge'), stock:document.getElementById('preview-stock'), image:document.getElementById('preview-image'), placeholder:document.getElementById('preview-image-placeholder')};
    const fmt = n=>(parseFloat(n)||0).toLocaleString('th-TH',{maximumFractionDigits:0});
    function updateStockAndPrice(){
      const price = parseFloat(formInputs.price.value)||0; const old = parseFloat(formInputs.priceOld.value)||0; const qty = parseInt(formInputs.stockQty.value,10);
      preview.priceNow.textContent = '฿' + fmt(price);
      if(old > price){ const d = old - price; const p = old ? Math.round((d/old)*100) : 0; preview.priceOld.textContent = '฿' + fmt(old); preview.priceOld.style.display = 'inline'; preview.badge.textContent = `ลด ${fmt(d)} ฿ (-${p}%)`; preview.badge.style.display = 'block'; }
      else { preview.priceOld.style.display = 'none'; preview.badge.style.display = 'none'; }
      if (!isNaN(qty) && qty > 0 && qty <= 1) preview.stock.style.display = 'block'; else preview.stock.style.display = 'none';
    }
    formInputs.title?.addEventListener('input', () => { preview.name.textContent = formInputs.title.value || 'ชื่อสินค้า...'; }); // Preview uses Thai title
    formInputs.brand?.addEventListener('input', () => { preview.category.textContent = (formInputs.brand.value||'MACBOOK').toUpperCase(); });
    formInputs.stockQty?.addEventListener('input', updateStockAndPrice);
    formInputs.price?.addEventListener('input', updateStockAndPrice);
    formInputs.priceOld?.addEventListener('input', updateStockAndPrice);

    // ---------- 3. Initial Load (เหมือนเดิม) ----------
    (function initial(){
      updateStockAndPrice();
      if (formInputs.title?.value) preview.name.textContent = formInputs.title.value;
      if (formInputs.brand?.value) preview.category.textContent = (formInputs.brand.value||'MACBOOK').toUpperCase();
      
      // [กูแก้] ลบ BASE_URL ออก
      const existingImg = '<?= h($product['main_image'] ?? '') ?>';
      
      if (preview.image && existingImg){ preview.image.src = existingImg; preview.image.style.display = 'block'; if (preview.placeholder) preview.placeholder.style.display = 'none'; }
      else if (preview.placeholder) preview.placeholder.style.display = 'flex';
      // No EAV JS needed for static keys
    })();

  }); // End DOMContentLoaded
</script>