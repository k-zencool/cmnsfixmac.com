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
