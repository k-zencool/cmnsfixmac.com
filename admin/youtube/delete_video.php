<?php
session_start();
include_once __DIR__ . '/../../includes/db.php';
include_once __DIR__ . '/../../includes/auth.php';

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // ตรวจสอบว่าข้อมูลวิดีโอนั้นมีอยู่จริงหรือไม่
    $stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();

    if ($video) {
        // ลบข้อมูล
        $deleteStmt = $pdo->prepare("DELETE FROM youtube_videos WHERE id = ?");
        $deleteStmt->execute([$id]);
    }
}

// กลับไปยัง index.php
header('Location: index.php');
exit;
