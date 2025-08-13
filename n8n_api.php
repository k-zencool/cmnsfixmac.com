<?php
// ==================================================================
//  ส่วนการตั้งค่า (Configuration)
// ==================================================================
require_once 'includes/db.php';
// !!! เปลี่ยนเป็น Token ของมึงเอง !!!
$secret_token = 'a7d9k3nLp8RzXb4vG2hJ5mC1fQeT6yU';

// ==================================================================
//  ฟังก์ชันตัวช่วย (Helper Functions)
// ==================================================================

/**
 * ส่ง JSON response กลับไปและจบการทำงาน
 */
function send_json_response(int $status_code, array $data): void {
    header("Content-Type: application/json; charset=utf-8");
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * อ่านและแปลง JSON input จาก request body
 */
function get_json_input(): array {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_response(400, ['status' => 'error', 'message' => 'Bad Request - Invalid JSON format.']);
    }
    return $data ?: [];
}

// ==================================================================
//  ส่วนประมวลผลหลัก (Main Logic)
// ==================================================================
try {
    if (($_GET['token'] ?? '') !== $secret_token) {
        send_json_response(403, ['status' => 'error', 'message' => 'Forbidden - Invalid Token']);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'GET': handle_get_request($pdo); break;
        case 'POST': handle_post_request($pdo); break;
        case 'PUT': handle_put_request($pdo); break;
        default: send_json_response(405, ['status' => 'error', 'message' => 'Method Not Allowed']);
    }
} catch (PDOException $e) {
    send_json_response(500, ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $pdo = null;
}

// ==================================================================
//  ฟังก์ชันจัดการแต่ละ Request Method (Handlers)
// ==================================================================

/**
 * จัดการ GET request
 */
function handle_get_request(PDO $pdo): void {
    $fetch_mode = $_GET['fetch'] ?? '';

    if ($fetch_mode === 'published') {
        // --- กูแก้ตรงนี้ ---
        // เพิ่ม AND facebook_post_url LIKE '%_%' เพื่อกรองเอาเฉพาะ URL ที่มีขีด _
        $sql = "(SELECT 'article' AS content_type, unique_id, facebook_post_url FROM content_article WHERE status = 'published' AND facebook_post_url IS NOT NULL AND facebook_post_url != '' AND facebook_post_url LIKE '%_%')
                UNION ALL
                (SELECT 'repair' AS content_type, unique_id, facebook_post_url FROM content_repair WHERE status = 'published' AND facebook_post_url IS NOT NULL AND facebook_post_url != '' AND facebook_post_url LIKE '%_%')";
        // --- จบส่วนที่แก้ ---
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json_response(200, ['status' => 'success', 'data' => $data ?: []]);

    } else {
        $type = $_GET['type'] ?? '';
        if ($type !== 'article' && $type !== 'repair') {
            send_json_response(400, ['status' => 'error', 'message' => "Invalid or missing 'type' or 'fetch' parameter."]);
        }

        $table_name = 'content_' . $type;
        $sql = "SELECT * FROM `$table_name` WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        send_json_response(200, ['status' => 'success', 'data' => $data ?: null]);
    }
}

/**
 * จัดการ POST request
 */
function handle_post_request(PDO $pdo): void {
    $input = get_json_input();
    if (empty($input['unique_id']) || empty($input['content_type'])) {
        send_json_response(400, ['status' => 'error', 'message' => 'Bad Request - Missing unique_id or content_type']);
    }

    $type = $input['content_type'];
    if ($type !== 'article' && $type !== 'repair') {
        send_json_response(400, ['status' => 'error', 'message' => "Invalid content_type. Must be 'article' or 'repair'."]);
    }

    $table_name = 'content_' . $type;
    $sql = "INSERT INTO `$table_name` (unique_id, content_type, title, source_url, content_summary, image_url, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending') 
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title), 
                source_url = VALUES(source_url), 
                content_summary = VALUES(content_summary), 
                image_url = VALUES(image_url)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input['unique_id'], 
        $type,
        $input['title'] ?? '',
        $input['source_url'] ?? '', 
        $input['content_summary'] ?? '', 
        $input['image_url'] ?? null
    ]);

    $message = ($stmt->rowCount() > 0) ? "Data ingested or updated in $table_name for " . $input['unique_id'] : "No new data to update for " . $input['unique_id'];
    send_json_response(200, ['status' => 'success', 'message' => $message]);
}


/**
 * จัดการ PUT request
 */
function handle_put_request(PDO $pdo): void {
    $input = get_json_input();
    $update_type = $_GET['update'] ?? '';

    if (empty($input['unique_id']) || empty($input['content_type'])) {
        send_json_response(400, ['status' => 'error', 'message' => 'PUT requests require unique_id and content_type in the JSON body.']);
    }

    $type = $input['content_type'];
    if ($type !== 'article' && $type !== 'repair') {
        send_json_response(400, ['status' => 'error', 'message' => "Invalid content_type. Must be 'article' or 'repair'."]);
    }

    $table_name = 'content_' . $type;
    $sql = ''; 
    $params = [];

    switch ($update_type) {
        
        case 'post_status':
            if (!isset($input['generated_caption'], $input['facebook_post_url'])) {
                 send_json_response(400, ['status' => 'error', 'message' => 'Missing generated_caption or facebook_post_url for post_status update.']);
            }
            $sql = "UPDATE `$table_name` SET last_posted_date = NOW(), status = 'published', post_count = post_count + 1, generated_caption = ?, facebook_post_url = ? WHERE unique_id = ?";
            $params = [
                $input['generated_caption'], 
                $input['facebook_post_url'], 
                $input['unique_id']
            ];
            break;
        
        case 'engagement_counts':
            if (!isset($input['likes_count'], $input['comments_count'], $input['shares_count'])) {
                send_json_response(400, ['status' => 'error', 'message' => 'Missing engagement data (likes_count, comments_count, shares_count).']);
            }
            $sql = "UPDATE `$table_name` SET likes_count = ?, comments_count = ?, shares_count = ?, last_checked_at = NOW() WHERE unique_id = ?";
            $params = [
                (int)$input['likes_count'],
                (int)$input['comments_count'],
                (int)$input['shares_count'],
                $input['unique_id']
            ];
            break;
        
        default:
            send_json_response(400, ['status' => 'error', 'message' => "Invalid 'update' parameter. Must be 'post_status' or 'engagement_counts'."]);
            return;
    }
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        send_json_response(200, ['status' => 'success', 'message' => 'Data updated successfully in ' . $table_name . ' for ' . $input['unique_id']]);
    } else {
        send_json_response(500, ['status' => 'error', 'message' => 'Failed to update data in ' . $table_name]);
    }
}
?>