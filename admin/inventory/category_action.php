<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['parts.manage']); // จัดการหมวดหมู่อะไหล่: ผู้จัดการ+ เท่านั้น
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: categories.php");
    exit();
}

if (isset($_POST['add_category'])) {
    $name        = trim($_POST['name']);
    $parent_id   = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $icon        = trim($_POST['icon'] ?? 'folder') ?: 'folder';
    // รหัสสำหรับประกอบ SKU (includes/sku_lib.php) — A-Z 0-9 เท่านั้น
    $code        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? '')) ?: null;
    $description = trim($_POST['description'] ?? '');
    $stmt = $pdo->prepare("INSERT INTO parts_categories (name, code, parent_id, icon, description) VALUES (:name, :code, :parent_id, :icon, :description)");
    $stmt->execute([':name' => $name, ':code' => $code, ':parent_id' => $parent_id, ':icon' => $icon, ':description' => $description]);
    header("Location: categories.php");
    exit();
}

if (isset($_POST['update_category'])) {
    $id          = $_POST['id'];
    $name        = trim($_POST['name']);
    $parent_id   = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $icon        = trim($_POST['icon'] ?? 'folder') ?: 'folder';
    // รหัสสำหรับประกอบ SKU (includes/sku_lib.php) — A-Z 0-9 เท่านั้น
    $code        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? '')) ?: null;
    $description = trim($_POST['description'] ?? '');
    // walk up from the new parent — if we meet $id, the move would make a loop (A→B→A)
    $walk = $parent_id ? (int)$parent_id : 0;
    $up   = $pdo->prepare("SELECT parent_id FROM parts_categories WHERE id = ?");
    for ($guard = 0; $walk && $guard < 50; $guard++) {
        if ($walk === (int)$id) inv_redirect_err('categories.php', 'ย้ายโฟลเดอร์ไปไว้ใต้ตัวเอง/โฟลเดอร์ลูกของตัวเองไม่ได้');
        $up->execute([$walk]);
        $walk = (int)$up->fetchColumn();
    }
    if ($id != $parent_id) {
        $stmt = $pdo->prepare("UPDATE parts_categories SET name = :name, code = :code, parent_id = :parent_id, icon = :icon, description = :description WHERE id = :id");
        $stmt->execute([':name' => $name, ':code' => $code, ':parent_id' => $parent_id, ':icon' => $icon, ':description' => $description, ':id' => $id]);
    }
    header("Location: categories.php");
    exit();
}

if (isset($_POST['delete_category'])) {
    $id = (int)$_POST['id'];
    // inventory.category_id is an FK (NO ACTION) → deleting a folder with items threw a 500;
    // child folders are ON DELETE SET NULL → they silently jumped to the top level
    $cnt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM inventory WHERE category_id = ?),
        (SELECT COUNT(*) FROM parts_categories WHERE parent_id = ?)");
    $cnt->execute([$id, $id]);
    [$items, $children] = array_map('intval', $cnt->fetch(PDO::FETCH_NUM));
    if ($children) inv_redirect_err('categories.php', "ลบไม่ได้ — ยังมีโฟลเดอร์ย่อย {$children} โฟลเดอร์ ย้ายหรือลบก่อน");
    if ($items)    inv_redirect_err('categories.php', "ลบไม่ได้ — ยังมีสินค้า {$items} รายการในโฟลเดอร์นี้ ย้ายออกก่อน");

    $pdo->prepare("DELETE FROM parts_categories WHERE id = ?")->execute([$id]);
    header("Location: categories.php");
    exit();
}