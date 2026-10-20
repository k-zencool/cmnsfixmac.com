<?php
/********************************************************************
 * admin/manager/_status.php — status map + query ของบอร์ดงานค้าง
 * ใช้ร่วมกันระหว่าง index.php (บอร์ด) และ print.php (TODO list)
 ********************************************************************/

$STATUS = [
    'QS'  => ['label' => 'รอเช็คราคา',           'color' => '#f59e0b', 'group' => 'todo'],
    'WC'  => ['label' => 'รอคอนเฟิร์ม',          'color' => '#3b82f6', 'group' => 'todo'],
    'OK'  => ['label' => 'กำลังซ่อม',            'color' => '#8b5cf6', 'group' => 'todo'],
    'RW'  => ['label' => 'งานแก้ / เคลม',        'color' => '#ef4444', 'group' => 'todo'],
    'FN'  => ['label' => 'เสร็จ รอรับ',          'color' => '#10b981', 'group' => 'waiting'],
    'XX'  => ['label' => 'ยกเลิก รอรับคืน',      'color' => '#ef4444', 'group' => 'waiting'],
    // not a real status: XX jobs cancelled more than MGR_XX_OLD_DAYS ago (the black box at the shop)
    'XXO' => ['label' => 'ยกเลิก เกิน 3 เดือน',   'color' => '#111827', 'group' => 'waiting', 'ink' => true],
    'NCF' => ['label' => 'ติดต่อไม่ได้ (เสร็จ)',  'color' => '#6b7280', 'group' => 'waiting'],
    'NCS' => ['label' => 'ติดต่อไม่ได้ (เสนอ)',   'color' => '#6b7280', 'group' => 'waiting'],
];

const MGR_XX_OLD_DAYS = 90;

/**
 * tracking + `bucket` = status, except old cancellations become XXO.
 * Cancel date = last move INTO XX in tracking_history (edit.php logs every
 * save with status_new, so rows XX→XX are just edits); rows from before the
 * history log fall back to updated_at, which can only make them look newer.
 */
function mgr_jobs_from(): string
{
    return "(
        SELECT t.*,
               CASE WHEN t.status = 'XX' AND COALESCE(
                        (SELECT MAX(h.changed_at) FROM tracking_history h
                         WHERE h.tracking_id = t.id AND h.status_new = 'XX'
                           AND (h.status_old IS NULL OR h.status_old <> 'XX')),
                        t.updated_at) < NOW() - INTERVAL " . MGR_XX_OLD_DAYS . " DAY
                    THEN 'XXO' ELSE t.status END AS bucket
        FROM tracking t
    ) j";
}

/**
 * ดึงงานค้างตาม filter เดียวกันทั้งบอร์ดและใบพิมพ์
 * filters: ?group=todo|waiting  ?st=QS  ?dev=MacBook  ?q=...  (+ ?per / ?page เมื่อ $paged)
 * $paged = true → แบ่งหน้า (บอร์ด), false → LIMIT 300 (ใบพิมพ์เอาทั้งชุด)
 */
function mgr_fetch_stuck_jobs(PDO $pdo, array $STATUS, bool $paged = false): array
{
    $group = ($_GET['group'] ?? 'todo') === 'waiting' ? 'waiting' : 'todo';
    $st    = trim($_GET['st'] ?? '');
    $dev   = trim($_GET['dev'] ?? '');
    $q     = trim($_GET['q'] ?? '');
    if ($st !== '' && !isset($STATUS[$st])) $st = '';

    $where  = [];
    $params = [];
    if ($st !== '') {
        $where[] = "bucket = ?";
        $params[] = $st;
    } else {
        $codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
        $where[] = "bucket IN ('" . implode("','", $codes) . "')";
    }
    if ($dev !== '') { $where[] = "device_type = ?"; $params[] = $dev; }
    if ($q !== '') {
        $where[] = "(ticket_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR device_model LIKE ? OR device_type LIKE ? OR serial_number LIKE ?)";
        $kw = "%$q%";
        array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
    }
    $where_sql = implode(" AND ", $where);
    $from = mgr_jobs_from();

    $per = 20; $page = 1; $total = 0; $pages = 1; $offset = 0;
    if ($paged) {
        $per    = max(5, min(200, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $cnt    = $pdo->prepare("SELECT COUNT(*) FROM $from WHERE $where_sql");
        $cnt->execute($params);
        $total  = (int)$cnt->fetchColumn();
        $pages  = max(1, (int)ceil($total / $per));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $per;
        $limit_sql = "LIMIT $per OFFSET $offset";
    } else {
        $limit_sql = "LIMIT 300";
    }

    $stmt = $pdo->prepare("
        SELECT id, ticket_number, customer_name, customer_phone, device_type, device_model,
               bucket AS status, appointment_date, created_at, DATEDIFF(NOW(), created_at) AS days_in
        FROM $from WHERE $where_sql
        ORDER BY created_at ASC $limit_sql");
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$paged) $total = count($jobs);

    return [
        'group' => $group, 'st' => $st, 'dev' => $dev, 'q' => $q,
        'jobs'  => $jobs,  'total' => $total,
        'per'   => $per,   'page' => $page, 'pages' => $pages, 'offset' => $offset,
    ];
}

/**
 * ประเภทเครื่อง + จำนวน ภายใน scope งานค้างของกลุ่ม/สถานะปัจจุบัน (สำหรับ dropdown)
 * @return array ['MacBook' => 12, 'iPhone' => 3, ...]
 */
function mgr_device_counts(PDO $pdo, array $STATUS, string $group, string $st): array
{
    if ($st !== '') {
        $stmt = $pdo->prepare("SELECT device_type, COUNT(*) FROM " . mgr_jobs_from() . " WHERE bucket = ? AND device_type IS NOT NULL AND device_type != '' GROUP BY device_type ORDER BY device_type ASC");
        $stmt->execute([$st]);
    } else {
        $codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
        $in = "'" . implode("','", $codes) . "'";
        $stmt = $pdo->query("SELECT device_type, COUNT(*) FROM " . mgr_jobs_from() . " WHERE bucket IN ($in) AND device_type IS NOT NULL AND device_type != '' GROUP BY device_type ORDER BY device_type ASC");
    }
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * สร้าง URL จาก filter ปัจจุบัน + override (ค่า null/'' = ตัดทิ้ง)
 */
function mgr_url(array $set = []): string
{
    $qs = array_merge($_GET, $set);
    foreach ($qs as $k => $v) {
        if ($v === null || $v === '') unset($qs[$k]);
    }
    return '?' . http_build_query($qs);
}
