<?php
/* =========================================================
   includes/job_view_lib.php — repair job detail sheet data

   One place that turns a `tracking` row into what the read-only job
   sheet shows. Used by admin/tracking/index.php (tap a job in the list)
   and admin/scan/job.php (scan a sticker, sheet opens over the camera),
   so the two can never show a job differently.

   All functions are prefixed jv_ and guarded, so the file is safe to
   include more than once (same pattern as warranty_lib.php).
   ========================================================= */

if (!function_exists('jv_status_map')) {
    function jv_status_map(): array {
        return [
            'QS'  => ['label' => 'รอเช็คราคา',          'class' => 'st-amber'],
            'WC'  => ['label' => 'รอคอนเฟิร์ม',         'class' => 'st-blue'],
            'OK'  => ['label' => 'กำลังซ่อม',           'class' => 'st-purple'],
            'RW'  => ['label' => 'งานแก้ / เคลม',       'class' => 'st-red'],
            'FN'  => ['label' => 'ซ่อมเสร็จ (รอรับ)',   'class' => 'st-green'],
            'NCF' => ['label' => 'ติดต่อไม่ได้ (เสร็จ)', 'class' => 'st-gray'],
            'NCS' => ['label' => 'ติดต่อไม่ได้ (เสนอ)',  'class' => 'st-gray'],
            'XX'  => ['label' => 'ยกเลิก (รอรับคืน)',   'class' => 'st-red'],
            'DV'  => ['label' => 'ส่งมอบแล้ว',          'class' => 'st-dark'],
            'RT'  => ['label' => 'ยกเลิก (คืนแล้ว)',    'class' => 'st-dark'],
        ];
    }
}

/* split "[ sym1, sym2 ] detail" → [symptoms[], clean detail] */
if (!function_exists('jv_parse_problem')) {
    function jv_parse_problem($raw): array {
        $symps = []; $detail = $raw ?? '';
        if (preg_match('/^\[(.*?)\](.*)/s', $detail, $m)) {
            $symps  = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            $detail = trim($m[2]);
        }
        $detail = trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?>/i', "\n", $detail)));
        return [$symps, $detail];
    }
}

/* split "สภาพ: a, b | Note: xxx" → [states[], clean note] */
if (!function_exists('jv_parse_note')) {
    function jv_parse_note($raw): array {
        $states = []; $note = $raw ?? '';
        if (preg_match('/^สภาพ:\s*([^|]*)(?:\|\s*(?:Note:\s*)?(.*))?$/su', $note, $m)) {
            $states = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            $note   = trim($m[2] ?? '');
        }
        return [$states, $note];
    }
}

/* Appointment countdown: [text, css class, days|null]. Delivered/returned jobs have none. */
if (!function_exists('jv_time_left')) {
    function jv_time_left(array $row): array {
        if (in_array($row['status'] ?? '', ['DV', 'RT'], true) || empty($row['appointment_date'])) {
            return ['—', '', null];
        }
        $today  = strtotime(date('Y-m-d'));
        $target = strtotime(date('Y-m-d', strtotime($row['appointment_date'])));
        $days   = ($target - $today) / 86400;
        if ($days > 0)   return ['อีก ' . number_format($days) . ' วัน', 'time-ok', $days];
        if ($days == 0)  return ['วันนี้', 'time-warn', $days];
        return ['เกิน ' . number_format(abs($days)) . ' วัน', 'time-danger', $days];
    }
}

/* The JSON the sheet's JS (admin/tracking/assets/js/job-view.js) reads */
if (!function_exists('jv_payload')) {
    function jv_payload(array $row): array {
        $stCode = $row['status'] ?? 'QS';
        $stData = jv_status_map()[$stCode] ?? ['label' => $stCode, 'class' => 'st-gray'];
        [$timeText, $timeClass] = jv_time_left($row);
        [$symps, $detail]  = jv_parse_problem($row['problem_details'] ?? '');
        [$states, $note]   = jv_parse_note($row['technician_note'] ?? '');

        return [
            'id'       => (int)$row['id'],
            'ticket'   => $row['ticket_number'],
            'stLabel'  => $stData['label'],
            'stClass'  => $stData['class'],
            'created'  => date('d/m/Y H:i', strtotime($row['created_at'])),
            'appt'     => $row['appointment_date'] ? date('d/m/Y', strtotime($row['appointment_date'])) : null,
            'pickup'   => !empty($row['pickup_date']) ? date('d/m/Y H:i', strtotime($row['pickup_date'])) : null,
            'timeText' => $timeText,
            'timeClass'=> $timeClass,
            'name'     => $row['customer_name'],
            'phone'    => $row['customer_phone'],
            'device'   => trim(($row['device_type'] ?? '') . ' ' . ($row['device_series'] ?? '')),
            'model'    => $row['device_model'],
            'sn'       => $row['serial_number'] ?: null,
            'pass'     => $row['device_password'] ?: null,
            'symptoms' => $symps,
            'detail'   => $detail !== '' ? $detail : null,
            'states'   => $states,
            'note'     => $note !== '' ? $note : null,
            'accs'     => array_values(array_filter(array_map('trim', explode(',', $row['accessories'] ?? '')))),
            'cost'     => number_format((float)$row['estimated_cost']),
        ];
    }
}
