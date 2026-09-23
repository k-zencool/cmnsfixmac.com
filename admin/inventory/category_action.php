<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['parts.manage']); // จัดการหมวดหมู่อะไหล่: ผู้จัดการ+ เท่านั้น

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
    if ($id != $parent_id) {
        $stmt = $pdo->prepare("UPDATE parts_categories SET name = :name, code = :code, parent_id = :parent_id, icon = :icon, description = :description WHERE id = :id");
        $stmt->execute([':name' => $name, ':code' => $code, ':parent_id' => $parent_id, ':icon' => $icon, ':description' => $description, ':id' => $id]);
    }
    header("Location: categories.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM parts_categories WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header("Location: categories.php");
    exit();
}