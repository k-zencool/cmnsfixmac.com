<?php
/********************************************************************
 * admin/inventory/_helpers.php — shared page helpers
 * (pattern เดียวกับ admin/tracking/index.php)
 ********************************************************************/

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('getv')) {
    function getv($k, $d = null) { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
}

if (!function_exists('inv_get_pager')) {
    /** @return array [$per, $page, $offset] */
    function inv_get_pager(): array {
        $per  = max(5, min(200, (int)getv('per', 20)));
        $page = max(1, (int)getv('page', 1));
        return [$per, $page, ($page - 1) * $per];
    }
}

if (!function_exists('inv_page_url')) {
    /** rebuild current query string with a new page number */
    function inv_page_url(int $i): string {
        $q = $_GET;
        $q['page'] = max(1, $i);
        return '?' . http_build_query($q);
    }
}

if (!function_exists('inv_redirect_err')) {
    /** redirect back with ?err= — works whether or not $back already has a query string */
    function inv_redirect_err(string $back, string $msg): void {
        [$path, $qs] = array_pad(explode('?', $back, 2), 2, '');
        parse_str($qs, $q);
        $q['err'] = $msg;   // replaces a stale err from an earlier failure
        header('Location: ' . $path . '?' . http_build_query($q));
        exit();
    }
}

if (!function_exists('inv_redirect_ok')) {
    /** redirect back after a successful save — drops any err= left from an earlier failure */
    function inv_redirect_ok(string $back): void {
        [$path, $qs] = array_pad(explode('?', $back, 2), 2, '');
        parse_str($qs, $q);
        unset($q['err']);
        header('Location: ' . $path . ($q ? '?' . http_build_query($q) : ''));
        exit();
    }
}

if (!function_exists('inv_err_banner')) {
    /** error banner for pages the process_* handlers redirect back to */
    function inv_err_banner(): string {
        $err = getv('err', '');
        if ($err === '') return '';
        return '<div role="alert" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding:12px 16px;'
             . 'border-radius:10px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#ef4444;font-weight:700;font-size:14px;">'
             . '<span class="material-symbols-rounded">error</span> บันทึกไม่สำเร็จ: ' . h($err) . '</div>';
    }
}

if (!function_exists('inv_type_meta')) {
    /** meta ของ inventory type: label / color / icon */
    function inv_type_meta(string $type): array {
        $map = [
            'new'     => ['label' => 'NEW',     'color' => '#10b981', 'icon' => 'fiber_new'],
            'used'    => ['label' => 'USED',    'color' => '#f59e0b', 'icon' => 'build'],
            'machine' => ['label' => 'MACHINE', 'color' => '#8b5cf6', 'icon' => 'computer'],
            'sale'    => ['label' => 'SALE',    'color' => '#ef4444', 'icon' => 'sell'],
        ];
        return $map[$type] ?? ['label' => strtoupper($type), 'color' => '#888', 'icon' => 'inventory_2'];
    }
}
