<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

// --- Whitelist of allowed tables ---
$allowed_tables = [
    'macbook_fix_pricing'    => 'MacBook',
    'imac_fix_pricing'       => 'iMac',
    'iphone_fix_pricing'     => 'iPhone',
    'ipad_fix_pricing'       => 'iPad',
    'applewatch_fix_pricing' => 'Apple Watch',
    'airpods_fix_pricing'    => 'AirPods',
    'software_fix_pricing'   => 'Software'
];

// --- Handle Delete Request ---
if (isset($_GET['delete_id']) && isset($_GET['from_table'])) {
    $id_to_delete = intval($_GET['delete_id']);
    $table_to_delete_from = $_GET['from_table'];

    if (array_key_exists($table_to_delete_from, $allowed_tables) && $id_to_delete > 0) {
        $stmt = $pdo->prepare("DELETE FROM `{$table_to_delete_from}` WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        header("Location: index.php"); // Reload the page
        exit;
    }
}


// --- Fetch all data ---
$all_data = [];
foreach (array_keys($allowed_tables) as $table) {
    $all_data[$table] = $pdo->query("SELECT * FROM `{$table}` ORDER BY category, model, id")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include '../../templates/header_admin.php'; ?>
<style>
.tab-buttons-container { overflow: hidden; border-bottom: 1px solid #ccc; background-color: #f1f1f1; margin-bottom: -1px; border-radius: 8px 8px 0 0; }
.tab-buttons-container button { background-color: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 14px 16px; transition: 0.3s; font-size: 0.9rem; font-weight: 500; }
.tab-buttons-container button:hover { background-color: #ddd; }
.tab-buttons-container button.active { background-color: #fff; color: #007aff; font-weight: bold; }
.tab-content { padding: 20px; border: 1px solid #ccc; border-top: none; border-radius: 0 0 8px 8px; background-color: #fff; display: none; }
.tab-content.active { display: block; }
.tab-content h3 { margin-top: 0; }
</style>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
    <div class="topbar">
        <span>จัดการตารางราคาทั้งหมด</span>
        <a href="../dashboard.php" class="view-site">← กลับ Dashboard</a>
    </div>

    <div class="tab-buttons-container">
        <?php $isFirst = true; ?>
        <?php foreach ($allowed_tables as $table_name => $display_name): ?>
            <button class="tab-link <?= $isFirst ? 'active' : '' ?>" onclick="openTab(event, '<?= e($table_name) ?>')"><?= e($display_name) ?></button>
            <?php $isFirst = false; ?>
        <?php endforeach; ?>
    </div>

    <?php $isFirst = true; ?>
    <?php foreach ($allowed_tables as $table_name => $display_name): ?>
        <div id="<?= e($table_name) ?>" class="tab-content <?= $isFirst ? 'active' : '' ?>">
            <h3>รายการราคา: <?= e($display_name) ?></h3>
            <a href="form.php?table=<?= urlencode($table_name) ?>" class="btn-primary" style="margin-bottom: 15px; display: inline-block;">+ เพิ่มรายการใหม่ในหมวดนี้</a>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>หมวดหมู่</th><th>รุ่น</th><th>รายละเอียด (ไทย)</th><th>รายละเอียด (EN)</th><th>ราคา</th><th>จัดการ</th></tr></thead>
                    <tbody>
                        <?php if (isset($all_data[$table_name]) && count($all_data[$table_name])): ?>
                            <?php foreach($all_data[$table_name] as $item): ?>
                            <tr>
                                <td><?= e($item['category'] ?? '') ?></td>
                                <td><?= e($item['model'] ?? '') ?></td>
                                <td><?= e($item['detail'] ?? '') ?></td>
                                <td><?= e($item['detail_en'] ?? '<i>(ยังไม่มี)</i>') ?></td>
                                <td><?= e($item['price'] ?? '') ?></td>
                                <td>
                                    <a href="form.php?table=<?= urlencode($table_name) ?>&id=<?= $item['id'] ?>" class="btn-edit">แก้ไข</a>
                                    <a href="?delete_id=<?= $item['id'] ?>&from_table=<?= urlencode($table_name) ?>" class="btn-delete" onclick="return confirm('แน่ใจนะว่าจะลบรายการนี้? ข้อมูลจะหายถาวรเลยนะ!')">ลบ</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">ยังไม่มีข้อมูล</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $isFirst = false; ?>
    <?php endforeach; ?>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script>
function openTab(evt, tabName) {
  let i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tab-content");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tab-link");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
}
// Set the first tab to be active on page load
document.querySelector('.tab-link').click();
</script>