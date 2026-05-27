<?php
// includes/warranty_lib.php

if (!function_exists('w_next_no')) {
    function w_next_no(PDO $pdo, string $table, string $col, string $prefix): string {
        $year = date('Y');
        $row  = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED)) AS mx
                              FROM `$table` WHERE $col LIKE '$prefix-$year-%'")->fetch();
        $n    = (int)($row['mx'] ?? 0) + 1;
        return "$prefix-$year-" . str_pad($n, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('w_next_warranty_no')) {
    function w_next_warranty_no(PDO $pdo): string { return w_next_no($pdo, 'warranties', 'warranty_no', 'W'); }
}

if (!function_exists('w_next_claim_no')) {
    function w_next_claim_no(PDO $pdo): string { return w_next_no($pdo, 'warranty_claims', 'claim_no', 'C'); }
}

if (!function_exists('w_status_badge')) {
    function w_status_badge(string $status): string {
        $map = [
            'active'  => ['st-green', 'verified',     'ใช้งานได้'],
            'expired' => ['st-gray',  'schedule',      'หมดอายุ'],
            'voided'  => ['st-red',   'block',         'ยกเลิก'],
        ];
        [$cls, $icon, $label] = $map[$status] ?? ['st-gray', 'help', $status];
        return "<span class=\"status-badge $cls\">
                    <span class=\"material-symbols-rounded\" style=\"font-size:14px;\">$icon</span> $label
                </span>";
    }
}

if (!function_exists('w_claim_badge')) {
    function w_claim_badge(string $status): string {
        $map = [
            'pending'  => ['st-amber', 'hourglass_empty', 'รอดำเนินการ'],
            'approved' => ['st-blue',  'task_alt',        'อนุมัติ'],
            'rejected' => ['st-red',   'cancel',          'ปฏิเสธ'],
            'resolved' => ['st-green', 'check_circle',    'แก้ไขแล้ว'],
        ];
        [$cls, $icon, $label] = $map[$status] ?? ['st-gray', 'help', $status];
        return "<span class=\"status-badge $cls\">
                    <span class=\"material-symbols-rounded\" style=\"font-size:14px;\">$icon</span> $label
                </span>";
    }
}

if (!function_exists('w_sync_expired')) {
    function w_sync_expired(PDO $pdo): void {
        $pdo->exec("UPDATE warranties SET status='expired'
                    WHERE status='active' AND end_date < CURDATE()");
    }
}

if (!function_exists('w_days_left')) {
    function w_days_left(string $end_date): int {
        return (int)ceil((strtotime($end_date) - time()) / 86400);
    }
}
