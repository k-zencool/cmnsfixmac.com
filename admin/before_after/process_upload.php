<?php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login(); 

// === ฟังก์ชันสำหรับย่อและครอบรูป ===
function resize_and_crop_image($source_image_resource, $target_width, $target_height) {
    $source_width = imagesx($source_image_resource);
    $source_height = imagesy($source_image_resource);
    $source_ratio = $source_width / $source_height;
    $target_ratio = $target_width / $target_height;
    $src_x = 0; $src_y = 0; $src_w = $source_width; $src_h = $source_height;
    if ($source_ratio > $target_ratio) {
        $src_w = $source_height * $target_ratio;
        $src_x = ($source_width - $src_w) / 2;
    } else {
        $src_h = $source_width / $target_ratio;
        $src_y = ($source_height - $src_h) / 2;
    }
    $resized_image = imagecreatetruecolor($target_width, $target_height);
    imagecopyresampled($resized_image, $source_image_resource, 0, 0, $src_x, $src_y, $target_width, $target_height, $src_w, $src_h);
    return $resized_image;
}


// === ผู้ช่วยคนใหม่: ฟังก์ชันสำหรับตัดคำ (Text Wrapping) ===
function wrap_text($font_size, $font_path, $text, $max_width) {
    $words = explode(' ', $text);
    $lines = [];
    $current_line = '';

    foreach ($words as $word) {
        $test_line = $current_line . ' ' . $word;
        // ใช้ imagettfbbox เพื่อวัดขนาดของข้อความ
        $test_box = imagettfbbox($font_size, 0, $font_path, $test_line);
        if (abs($test_box[4] - $test_box[0]) > $max_width) {
            $lines[] = trim($current_line);
            $current_line = $word;
        } else {
            $current_line = trim($test_line);
        }
    }
    $lines[] = $current_line; // เพิ่มบรรทัดสุดท้าย

    return $lines;
}

// === โรงงานผลิตรูป (เวอร์ชันตัดคำ) ===
function create_before_after_image($before_path, $after_path, $output_path, $job_number, $device_model, $job_description, $background_filename) {
    // --- ตัวแปรปรับแต่ง ---
    $target_width = 441; $target_height = 588;
    $corner_radius = 20; $border_thickness = 4;
    $border_color = imagecolorallocatealpha(imagecreatetruecolor(1,1), 255, 255, 255, 50);

    // 1. โหลด Template และรูปภาพ
    $template_path = __DIR__ . '/image/' . $background_filename;
    if (!file_exists($template_path)) { return false; }

    $extension = strtolower(pathinfo($template_path, PATHINFO_EXTENSION));
    if ($extension == 'png') {
        $canvas = imagecreatefrompng($template_path);
        imagesavealpha($canvas, true);
    } else {
        return false;
    }
    $canvas_width = imagesx($canvas);

    $before_img_original = imagecreatefromstring(file_get_contents($before_path));
    $after_img_original = imagecreatefromstring(file_get_contents($after_path));
    if (!$before_img_original || !$after_img_original) { return false; }

    // 2. ปรับขนาด (และอาจจะทำขอบโค้ง ถ้าต้องการ)
    $before_img_resized = resize_and_crop_image($before_img_original, $target_width, $target_height);
    $after_img_resized = resize_and_crop_image($after_img_original, $target_width, $target_height);
    
    // 3. วาดกรอบและรูปภาพ
    $before_x = 63; $after_x = 552; $y_pos = 390;
    imagefilledrectangle($canvas, $before_x - $border_thickness, $y_pos - $border_thickness, $before_x + $target_width + $border_thickness, $y_pos + $target_height + $border_thickness, $border_color);
    imagefilledrectangle($canvas, $after_x - $border_thickness, $y_pos - $border_thickness, $after_x + $target_width + $border_thickness, $y_pos + $target_height + $border_thickness, $border_color);
    imagecopy($canvas, $before_img_resized, $before_x, $y_pos, 0, 0, $target_width, $target_height);
    imagecopy($canvas, $after_img_resized, $after_x, $y_pos, 0, 0, $target_width, $target_height);
    
    // 4. ส่วนเพิ่มตัวหนังสือ
    $textColor = imagecolorallocate($canvas, 255, 255, 255);
    $font_path = __DIR__ . '/../../assets/fonts/Kanit-ExtraBold.ttf';

    if (file_exists($font_path)) {
        // เลขงาน
        imagettftext($canvas, 30, 0, 50, 150, $textColor, $font_path, "Job: " . $job_number);

        // ชื่อรุ่นอุปกรณ์ (จัดกลาง)
        $device_model_size = 65;
        $textBox = imagettfbbox($device_model_size, 0, $font_path, $device_model);
        $textWidth = abs($textBox[4] - $textBox[0]);
        $textX = ($canvas_width - $textWidth) / 2;
        imagettftext($canvas, $device_model_size, 0, $textX, 250, $textColor, $font_path, $device_model);

        // --- จัดการรายละเอียดโปรเจกต์ที่ยาวๆ (ตัดคำ + จัดกลาง) ---
        $job_description_size = 40;
        $max_width_for_description = 1000;
        $line_height = 60;
        
        $lines = wrap_text($job_description_size, $font_path, $job_description, $max_width_for_description);
        
        $current_y = 310; // ตำแหน่ง Y เริ่มต้นของรายละเอียด
        foreach ($lines as $line) {
            $textBox = imagettfbbox($job_description_size, 0, $font_path, $line);
            $textWidth = abs($textBox[4] - $textBox[0]);
            $textX = ($canvas_width - $textWidth) / 2;
            imagettftext($canvas, $job_description_size, 0, $textX, $current_y, $textColor, $font_path, $line);
            $current_y += $line_height; // ขยับ Y ลงมาสำหรับบรรทัดต่อไป
        }
    }
    
    // 5. บันทึกและคืนหน่วยความจำ
    $is_saved = imagepng($canvas, $output_path, 0);
    imagedestroy($before_img_original); imagedestroy($after_img_original);
    imagedestroy($before_img_resized); imagedestroy($after_img_resized);
    imagedestroy($canvas);
    return $is_saved;
}

// === ส่วนจัดการหลัก ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    function handle_upload($file_key) {
        $upload_dir = __DIR__ . '/../../uploads/before_after/'; 
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            $file_name = time() . '-' . uniqid() . '-' . basename($_FILES[$file_key]["name"]);
            $target_file = $upload_dir . $file_name;
            $relative_path = "uploads/before_after/" . $file_name; 
            if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                return ['absolute' => $target_file, 'relative' => $relative_path];
            }
        }
        return null;
    }

    $before_upload = handle_upload('before_image');
    $after_upload = handle_upload('after_image');

    if ($before_upload && $after_upload) {
        $selected_bg = basename($_POST['background_choice'] ?? 'default.png');
        $job_number = $_POST['job_number'] ?? '';
        $device_model = $_POST['device_model'] ?? '';
        $job_description = $_POST['job_description'] ?? '';

        try {
            $stmt = $pdo->prepare("INSERT INTO photo_projects (job_number, device_model, job_description, before_image_path, after_image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$job_number, $device_model, $job_description, $before_upload['relative'], $after_upload['relative']]);
            $last_project_id = $pdo->lastInsertId();

            $combined_filename = "combined-" . $job_number . "-" . time() . ".png";
            $output_absolute_path = __DIR__ . '/../../uploads/before_after/' . $combined_filename;
            $output_relative_path = "uploads/before_after/" . $combined_filename;

            $success = create_before_after_image($before_upload['absolute'], $after_upload['absolute'], $output_absolute_path, $job_number, $device_model, $job_description, $selected_bg);

            if ($success) {
                $update_stmt = $pdo->prepare("UPDATE photo_projects SET combined_image_path = ? WHERE id = ?");
                $update_stmt->execute([$output_relative_path, $last_project_id]);
                $_SESSION['success_message'] = "โปรเจกต์ '" . htmlspecialchars($job_number) . "' ถูกสร้างและรวมรูปเรียบร้อยแล้ว!";
                $_SESSION['last_combined_image'] = $output_relative_path;
            } else {
                $_SESSION['error_message'] = "สร้างรูปภาพไม่สำเร็จ (เช็ค path background/font)";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { $_SESSION['error_message'] = "ผิดพลาด: เลขงาน '" . htmlspecialchars($job_number) . "' นี้มีในระบบแล้ว"; } else { $_SESSION['error_message'] = "ผิดพลาดกับฐานข้อมูล: " . $e->getMessage(); }
        }
    } else {
        $_SESSION['error_message'] = "ผิดพลาดในการอัปโหลดไฟล์รูปภาพ";
    }
    
    header("Location: upload_form.php");
    exit;
}
?>