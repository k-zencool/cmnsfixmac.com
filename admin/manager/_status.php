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
 * @return array [$group, $st, $jobs]
 */
function mgr_fetch_stuck_jobs(PDO $pdo, array $STATUS): array
{
    $group = ($_GET['group'] ?? 'todo') === 'waiting' ? 'waiting' : 'todo';
    $st    = trim($_GET['st'] ?? '');
    if ($st !== '' && !isset($STATUS[$st])) $st = '';

    if ($st !== '') {
        $stmt = $pdo->prepare("
            SELECT id, ticket_number, customer_name, customer_phone, device_type, device_model,
                   status, appointment_date, created_at, DATEDIFF(NOW(), created_at) AS days_in
            FROM tracking WHERE status = ?
            ORDER BY created_at ASC LIMIT 300");
        $stmt->execute([$st]);
    } else {
        $codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
        $in = "'" . implode("','", $codes) . "'";
        $stmt = $pdo->query("
            SELECT id, ticket_number, customer_name, customer_phone, device_type, device_model,
                   status, appointment_date, created_at, DATEDIFF(NOW(), created_at) AS days_in
            FROM tracking WHERE status IN ($in)
            ORDER BY created_at ASC LIMIT 300");
    }
    return [$group, $st, $stmt->fetchAll(PDO::FETCH_ASSOC)];
}
