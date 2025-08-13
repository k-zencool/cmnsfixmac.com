<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

function e($string) { return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8'); }

// --- Configuration for all pricing tables ---
$allowed_tables = [
    'macbook_fix_pricing'    => 'ราคาซ่อม MacBook',
    'imac_fix_pricing'       => 'ราคาซ่อม iMac',
    'iphone_fix_pricing'     => 'ราคาซ่อม iPhone',
    'ipad_fix_pricing'       => 'ราคาซ่อม iPad',
    'applewatch_fix_pricing' => 'ราคาซ่อม Apple Watch',
    'airpods_fix_pricing'    => 'ราคาซ่อม AirPods',
    'software_fix_pricing'   => 'ค่าบริการ Software'
];

// Define categories for each table's dropdown
$table_categories = [
    'macbook_fix_pricing' => ['display' => 'เปลี่ยนจอ', 'battery' => 'เปลี่ยนแบตเตอรี่', 'board' => 'ซ่อมบอร์ด'],

    'imac_fix_pricing' => ['display' => 'เปลี่ยนจอ', 'upgrade' => 'อัปเกรด SSD / RAM', 'board' => 'ซ่อมบอร์ด'],

    'iphone_fix_pricing' => ['display' => 'เปลี่ยนจอ', 'battery' => 'เปลี่ยนแบตเตอรี่', 'board' => 'ซ่อมบอร์ด'],

    'ipad_fix_pricing' => ['display' => 'เปลี่ยนจอ', 'battery' => 'เปลี่ยนแบตเตอรี่', 'board' => 'ซ่อมบอร์ด'],
    
    'applewatch_fix_pricing' => ['display' => 'เปลี่ยนจอ', 'battery' => 'เปลี่ยนแบตเตอรี่', 'board' => 'ซ่อมบอร์ด'],

    'airpods_fix_pricing' => ['earpiece' => 'ซ่อมหูฟัง', 'case' => 'ซ่อมเคสชาร์จ', 'board' => 'ซ่อมบอร์ด'],
    
    'software_fix_pricing' => ['os' => 'ลง macOS ใหม่', 'apps' => 'ลงโปรแกรมทำงาน', 'clean' => 'ล้างเครื่อง / แก้ไวรัส']
];
// --- End Configuration ---


$table = $_GET['table'] ?? '';
if (!array_key_exists($table, $allowed_tables)) {
    die("ตารางไม่ถูกต้องหรือไม่ได้รับอนุญาต");
}

// Get the specific categories for the current table being edited
$current_categories = $table_categories[$table] ?? [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = ['category' => '', 'model' => '', 'detail' => '', 'detail_en' => '', 'price' => ''];
$pageTitle = "เพิ่มรายการใหม่ใน " . $allowed_tables[$table];
$isEditMode = false;

if ($id) {
    $isEditMode = true;
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) { header("Location: index.php"); exit; } // Redirect to main pricing menu
    $pageTitle = "แก้ไขรายการ #" . e($id) . " ใน " . $allowed_tables[$table];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $model = $_POST['model'] ?? '';
    $detail = $_POST['detail'] ?? '';
    $detail_en = $_POST['detail_en'] ?? '';
    $price = $_POST['price'] ?? '';

    if ($isEditMode) {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET category=?, model=?, detail=?, detail_en=?, price=? WHERE id=?");
        $stmt->execute([$category, $model, $detail, $detail_en, $price, $id]);
    } else {
        // For INSERT, we need to ensure created_at is handled if the table has it and it's not auto-updating
        // This simplified version assumes no created_at on INSERT, but can be added
        $stmt = $pdo->prepare("INSERT INTO `{$table}` (category, model, detail, detail_en, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$category, $model, $detail, $detail_en, $price]);
    }
    header("Location: index.php"); // Redirect back to the main pricing menu
    exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= e($pageTitle) ?></span>
        <a href="index.php" class="view-site">← กลับหน้าเมนูราคา</a>
    </div>

    <div class="form-section">
        <form method="POST">
            <fieldset>
                <legend>ข้อมูลราคา</legend>
                
                <label for="category">หมวดหมู่ (Category):</label>
                <select id="category" name="category" required>
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php foreach ($current_categories as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($item['category'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= e($label) ?> (key: <?= e($key) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="model">รุ่น (Model):</label>
                <input type="text" id="model" name="model" value="<?= e($item['model']) ?>" placeholder="e.g., MacBook Pro 13-inch M1 2020">
                
                <label for="price">ราคา (Price):</label>
                <input type="text" id="price" name="price" value="<?= e($item['price']) ?>" required placeholder="e.g., 2,900.-">
            </fieldset>

            <hr style="margin: 20px 0;">

            <fieldset>
                <legend>รายละเอียด (Details)</legend>
                <label for="detail">รายละเอียด (ไทย):</label>
                <textarea id="detail" name="detail" rows="4"><?= e($item['detail']) ?></textarea>

                <label for="detail_en">รายละเอียด (English):</label>
                <textarea id="detail_en" name="detail_en" rows="4"><?= e($item['detail_en']) ?></textarea>
            </fieldset>
            
            <div style="margin-top: 20px;">
                <button type="submit">บันทึกข้อมูล</button>
                <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ</a>
            </div>
        </form>
    </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>