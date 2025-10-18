<?php
/********************************************************************
 * includes/warranty_lib.php
 * - Helper กลุ่มงานประกัน/เคลม ใช้ prefix w_ ทั้งหมด (กันชนชื่อ)
 * - ไม่โยน exception แปลก ๆ ออกไปโดยไม่จำเป็น
 * - ออกแบบให้ “include ซ้ำได้” (ทุกฟังก์ชันห่อด้วย function_exists)
 ********************************************************************/

/* =============== HTML Escaper =============== */

if (!function_exists('w_h')) {
  /** escape HTML ปกติ */
  function w_h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('w_h_nb')) {
  /** escape + ไม่ตัดบรรทัดโดยง่าย (ใช้ในเลขอ้างอิง) */
  function w_h_nb($s){
    $t = htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    // กันการตัดคำแปลกๆ เช่นมีขีด/สแลชยาวๆ
    return str_replace(['-','/'], ['&#8209;','&#47;'], $t);
  }
}

/* =============== Warranty status helpers =============== */

if (!function_exists('w_badge_class')) {
  /**
   * คืนคลาส badge ตามสถานะงานประกัน
   * - in_warranty  : เขียว
   * - soon (0..7d) : เหลือง (เฉพาะถ้าส่ง $daysLeft เข้ามา)
   * - expired      : แดง
   * - void/อื่นๆ   : เทา (ใช้ default)
   */
  function w_badge_class(?string $status, ?int $daysLeft = null): string {
    $st = (string)$status;
    if ($st === 'in_warranty') {
      if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 7) return 'badge-amber';
      return 'badge-green';
    }
    if ($st === 'expired') return 'badge-red';
    if ($st === 'void')    return '';
    return '';
  }
}

if (!function_exists('w_status_label')) {
  /** ป้ายภาษาไทยของสถานะงานประกัน */
  function w_status_label(?string $status, ?int $daysLeft = null): string {
    $st = (string)$status;
    if ($st === 'in_warranty') {
      if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 7) return 'ใกล้หมดประกัน';
      return 'ยังอยู่ในประกัน';
    }
    if ($st === 'expired') return 'หมดประกัน';
    if ($st === 'void')    return 'โมฆะ';
    return $st;
  }
}

/* =============== Date/Calc helpers =============== */

if (!function_exists('w_calc_until')) {
  /** คำนวณวันหมดประกันจาก base_date + days */
  function w_calc_until(string $base_date, int $days): string {
    $d = new DateTime($base_date ?: date('Y-m-d'));
    if ($days > 0) $d->modify("+{$days} day");
    return $d->format('Y-m-d');
  }
}

if (!function_exists('w_status_from_dates')) {
  /**
   * คืนสถานะจากวันหมดประกัน:
   * - จนถึงวันนี้ (>= today) ถือว่า in_warranty
   * - ก่อนหน้า today ถือว่า expired
   * - ค่าว่าง → void
   */
  function w_status_from_dates(?string $until): string {
    if (!$until) return 'void';
    $u = new DateTime($until);
    $today = new DateTime('today');
    return ($u >= $today) ? 'in_warranty' : 'expired';
  }
}

/* =============== Running number generators =============== */

if (!function_exists('w_next_running_no')) {
  /**
   * แม่แบบสร้างเลขรัน: PREFIX + yy + zero-pad
   * - ใช้ SELECT หาเลขล่าสุดที่ขึ้นต้น $prefix.$yy แล้ว +1
   * - ควรมี UNIQUE INDEX ที่คอลัมน์ (เช่น warranty_no/claim_no)
   * - ในตัว save ให้รับมือ duplicate โดยลอง gen ใหม่อีกรอบ
   */
  function w_next_running_no(PDO $pdo, string $table, string $column, string $prefixLetter='W', int $pad=4): string {
    $yy = date('y');
    $prefix = $prefixLetter . $yy;           // เช่น W25 / C25
    $sql = "SELECT {$column}
            FROM {$table}
            WHERE {$column} LIKE :pfx
            ORDER BY {$column} DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':pfx' => $prefix.'%']);
    $last = $st->fetchColumn();

    if ($last) {
      // ตัด prefix ออก (ความยาว prefix + pad ไม่จำเป็นต้อง fix แต่เอาง่าย: ตัด 3 ตัวแรก Wyy)
      // รองรับ prefix ตัวเดียว + ปี 2 หลัก → len = 3
      $num = (int)substr($last, strlen($prefix)) + 1;
    } else {
      $num = 1;
    }
    return $prefix . str_pad((string)$num, $pad, '0', STR_PAD_LEFT);
  }
}

if (!function_exists('w_next_warranty_no')) {
  /** เลขรันงานประกัน: WyyNNNN */
  function w_next_warranty_no(PDO $pdo): string {
    return w_next_running_no($pdo, 'warranty_jobs', 'warranty_no', 'W', 4);
  }
}

if (!function_exists('w_next_claim_no')) {
  /** เลขรันเคลม: CyyNNNN */
  function w_next_claim_no(PDO $pdo): string {
    // ชื่อคอลัมน์ของเคลมไม่แน่นอน ให้ลองตามลำดับ
    $cands = ['claim_no','claim_code','no','ref_no'];
    $col = null;
    foreach ($cands as $c) {
      if (w_col_exists($pdo, 'warranty_claims', $c)) { $col = $c; break; }
    }
    if (!$col) $col = 'claim_no'; // best-effort
    return w_next_running_no($pdo, 'warranty_claims', $col, 'C', 4);
  }
}

/* =============== Claims status mapping (TH) =============== */

if (!function_exists('w_claim_status_th')) {
  /** แปลงโค้ดสถานะเคลม → ภาษาไทย (ไม่รู้จักก็คืนค่าดิบ) */
  function w_claim_status_th(?string $code): string {
    $map = [
      'open'           => 'เปิดใหม่',
      'investigating'  => 'กำลังตรวจสอบ',
      'accepted'       => 'รับเคลม',
      'rejected'       => 'ปฏิเสธ',
      'closed'         => 'ปิดเคส',
      'void'           => 'โมฆะ',
    ];
    $c = strtolower((string)$code);
    return $map[$c] ?? (string)$code;
  }
}

if (!function_exists('w_claim_badge_class')) {
  /** คืนคลาส badge ของสถานะเคลม */
  function w_claim_badge_class(?string $code): string {
    $c = strtolower((string)$code);
    return [
      'open'           => 'badge-amber',
      'investigating'  => 'badge-blue',
      'accepted'       => 'badge-green',
      'rejected'       => 'badge-red',
      'closed'         => '',
      'void'           => 'badge-amber',
    ][$c] ?? '';
  }
}

/* =============== Column detection helpers =============== */

if (!function_exists('w_col_exists')) {
  /** ตรวจว่าคอลัมน์มีจริงในตารางไหม (รองรับ shared env) */
  function w_col_exists(PDO $pdo, string $table, string $column): bool {
    try {
      $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
      $st->execute([':c'=>$column]);
      return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      return false; // บาง host อาจปิดสิทธิ์ SHOW COLUMNS
    }
  }
}

if (!function_exists('w_pick_col')) {
  /**
   * เลือกคอลัมน์แรกที่มีจริงในตาราง แล้วสร้าง select fragment
   * @param string $alias     ชื่อ alias ที่อยากได้ในผลลัพธ์
   * @param string $prefix    prefix สำหรับเขียนหน้า select เช่น "c." หรือ "" (ว่าง)
   * @return array {exists, select, field, name}
   */
  function w_pick_col(PDO $pdo, string $table, array $cands, string $alias, string $prefix='c.'): array {
    $col = null;
    foreach ($cands as $cand) {
      if (w_col_exists($pdo, $table, $cand)) { $col = $cand; break; }
    }
    return [
      'exists' => $col !== null,
      'select' => $col ? "{$prefix}`{$col}` AS {$alias}" : "NULL AS {$alias}",
      'field'  => $col ? "{$prefix}`{$col}`" : null,
      'name'   => $col,
    ];
  }
}

/* =============== Misc small helpers =============== */

if (!function_exists('w_days_left')) {
  /** คำนวณจำนวนวันคงเหลือจาก until (ติดลบได้ถ้าเลยกำหนด) */
  function w_days_left(?string $until): ?int {
    if (!$until) return null;
    $u = new DateTime($until);
    $today = new DateTime('today');
    return (int)$today->diff($u)->format('%r%a');
  }
}

if (!function_exists('w_nullable_int')) {
  /** แปลงค่าเป็น int หรือคืน null ถ้าไม่ใช่เลข */
  function w_nullable_int($v): ?int {
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v)) return null;
    return (int)$v;
  }
}

if (!function_exists('w_trim')) {
  function w_trim($s){ return trim((string)$s); }
}

?>
