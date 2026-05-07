<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo '[]'; exit; }

$stmt = $pdo->prepare("
    SELECT id, ticket_number, customer_name, device_type, device_model,
           problem_details, technician_note, status, final_cost
    FROM tracking
    WHERE status IN ('OK','DV')
      AND (ticket_number LIKE ? OR customer_name LIKE ? OR device_model LIKE ?)
    ORDER BY updated_at DESC
    LIMIT 15
");
$like = "%$q%";
$stmt->execute([$like, $like, $like]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
