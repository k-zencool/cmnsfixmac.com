<?php
/********************************************************************
 * admin/manager/_status.php — status map ของบอร์ดงานค้าง
 * ใช้ร่วมกันระหว่าง index.php (บอร์ด) และ print.php (TODO list)
 ********************************************************************/

$STATUS = [
    'QS'  => ['label' => 'รอเช็คราคา',           'color' => '#f59e0b', 'group' => 'todo'],
    'WC'  => ['label' => 'รอคอนเฟิร์ม',          'color' => '#3b82f6', 'group' => 'todo'],
    'OK'  => ['label' => 'กำลังซ่อม',            'color' => '#8b5cf6', 'group' => 'todo'],
    'RW'  => ['label' => 'งานแก้ / เคลม',        'color' => '#ef4444', 'group' => 'todo'],
    'FN'  => ['label' => 'เสร็จ รอรับ',          'color' => '#10b981', 'group' => 'waiting'],
    'XX'  => ['label' => 'ยกเลิก รอรับคืน',      'color' => '#ef4444', 'group' => 'waiting'],
    'NCF' => ['label' => 'ติดต่อไม่ได้ (เสร็จ)',  'color' => '#6b7280', 'group' => 'waiting'],
    'NCS' => ['label' => 'ติดต่อไม่ได้ (เสนอ)',   'color' => '#6b7280', 'group' => 'waiting'],
];

/**
 * ดึงงานค้างตาม filter เดียวกันทั้งบอร์ดและใบพิมพ์
 * filters: ?group=todo|waiting  ?st=QS  ?dev=MacBook
 * @return array [$group, $st, $dev, $jobs]
 */
function mgr_fetch_stuck_jobs(PDO $pdo, array $STATUS): array
{
    $group = ($_GET['group'] ?? 'todo') === 'waiting' ? 'waiting' : 'todo';
    $st    = trim($_GET['st'] ?? '');
    $dev   = trim($_GET['dev'] ?? '');
    if ($st !== '' && !isset($STATUS[$st])) $st = '';

    $where  = [];
    $params = [];
    if ($st !== '') {
        $where[] = "status = ?";
        $params[] = $st;
    } else {
        $codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
        $where[] = "status IN ('" . implode("','", $codes) . "')";
    }
    if ($dev !== '') { $where[] = "device_type = ?"; $params[] = $dev; }

    $stmt = $pdo->prepare("
        SELECT id, ticket_number, customer_name, customer_phone, device_type, device_model,
               status, appointment_date, created_at, DATEDIFF(NOW(), created_at) AS days_in
        FROM tracking WHERE " . implode(" AND ", $where) . "
        ORDER BY created_at ASC LIMIT 300");
    $stmt->execute($params);
    return [$group, $st, $dev, $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * ประเภทเครื่อง + จำนวน ภายใน scope งานค้างของกลุ่ม/สถานะปัจจุบัน (สำหรับ dropdown)
 * @return array ['MacBook' => 12, 'iPhone' => 3, ...]
 */
function mgr_device_counts(PDO $pdo, array $STATUS, string $group, string $st): array
{
    if ($st !== '') {
        $stmt = $pdo->prepare("SELECT device_type, COUNT(*) FROM tracking WHERE status = ? AND device_type IS NOT NULL AND device_type != '' GROUP BY device_type ORDER BY device_type ASC");
        $stmt->execute([$st]);
    } else {
        $codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
        $in = "'" . implode("','", $codes) . "'";
        $stmt = $pdo->query("SELECT device_type, COUNT(*) FROM tracking WHERE status IN ($in) AND device_type IS NOT NULL AND device_type != '' GROUP BY device_type ORDER BY device_type ASC");
    }
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
