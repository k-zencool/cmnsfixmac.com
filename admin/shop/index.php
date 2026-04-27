<?php

/********************************************************************
 * admin/shop/index.php (ดัดแปลงจาก products/index.php)
 * - ใช้ตาราง listings
 * - ค้นหา: title, brand, sku
 * - ตัวกรอง: status (enum), brand, ช่วงราคา, วันที่
 * - แบ่งหน้า
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); // <-- Ensure admin is logged in

$pageTitle = "จัดการรายการสินค้า (Listings)";

// ============== [CSRF: เพิ่มตรงนี้ แค่ไม่กี่บรรทัด] ==============
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// =======================[ Helpers ]==================================
// Assuming h(), getv(), get_pager(), page_url(), whereSearch() exist from includes or are defined here/globally
// Re-define if not included globally:
if (!function_exists('h')) {
    function h($s)
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('getv')) {
    function getv($k, $d = null)
    {
        return isset($_GET[$k]) ? trim($_GET[$k]) : $d;
    }
}
if (!function_exists('get_pager')) {
    /** คืนค่า [per, page, offset] */
    function get_pager(): array
    {
        $per  = max(5, min(200, (int) getv('per', 20)));
        $page = max(1, (int) getv('page', 1));
        return [$per, $page, ($page - 1) * $per];
    }
}
if (!function_exists('page_url')) {
    /** page url คงพารามอื่นๆไว้ */
    function page_url($i)
    {
        $q = $_GET;
        $q['page'] = max(1, (int)$i);
        // Remove empty params
        foreach ($q as $k => $v) {
            if ($v === '' || $v === null) unset($q[$k]);
        }
        return '?' . http_build_query($q);
    }
}
if (!function_exists('whereSearch')) {
    /** where LIKE หลายคอลัมน์ */
    function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string
    {
        $q = trim($q);
        if ($q === '') return null;
        $ors = [];
        $i = 0;
        foreach ($cols as $c) {
            $ph = ":{$pfx}{$i}";
            $ors[] = "$c LIKE $ph";
            $params[$ph] = "%{$q}%";
            $i++;
        }
        return '(' . implode(' OR ', $ors) . ')';
    }
}
// =======================[ State ]====================================
$q         = getv('q', '');
$status    = getv('status', ''); // '', 'published', 'draft', 'archived', 'sold'
$brands    = isset($_GET['brand']) ? (array)$_GET['brand'] : [];  // multi select brands
$price_min = getv('pmin', '');
$price_max = getv('pmax', '');
$dfrom     = getv('date_from', '');
$dto = getv('date_to', '');
$sort      = getv('sort', 'created_desc');
[$per, $page, $offset] = get_pager();

// =======================[ Brand list ]============================
// NOTE: For better performance on large tables, consider a separate `brands` table.
$brandRows = [];
try {
    $st = $pdo->query("SELECT DISTINCT brand FROM listings WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand");
    $brandRows = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
} catch (Throwable $e) {
    $brandRows = [];
}
$brandRows = array_values(array_filter($brandRows, function ($b) {
    return $b !== null && $b !== '';
}));

// =======================[ Build WHERE ]==============================
$params = [];
$where = [];
if ($w = whereSearch($q, ['l.title', 'l.brand', 'l.sku'], $params, 'q')) $where[] = $w;

$valid_statuses = ['published', 'draft', 'archived', 'sold'];
if (in_array($status, $valid_statuses, true)) {
    $where[] = "l.status = :status";
    $params[':status'] = $status;
}

if ($brands) {
    $sel = array_values(array_intersect($brands, $brandRows)); // Whitelist selected brands
    if ($sel) {
        $in = [];
        foreach ($sel as $i => $b) {
            $ph = ":b{$i}";
            $params[$ph] = $b;
            $in[] = $ph;
        }
        $where[] = 'l.brand IN (' . implode(',', $in) . ')';
    }
}
if ($price_min !== '' && is_numeric($price_min)) {
    $where[] = 'l.price >= :pmin';
    $params[':pmin'] = (float)$price_min;
}
if ($price_max !== '' && is_numeric($price_max)) {
    $where[] = 'l.price <= :pmax';
    $params[':pmax'] = (float)$price_max;
}

// <-- [กูแก้ตรงนี้] แก้ฟิลเตอร์วันที่ให้ใช้ Index ได้เต็มประสิทธิภาพ
if ($dfrom !== '') {
    $where[] = 'l.created_at >= :df'; // ไม่ต้องใช้ DATE()
    $params[':df'] = $dfrom;
}
if ($dto !== '') {
    // ใช้ <= และเพิ่มเวลา 23:59:59 เพื่อให้รวมวันสุดท้ายทั้งวัน
    $where[] = 'l.created_at <= :dt_end';
    try {
        $dt_end_obj = new DateTime($dto);
        $params[':dt_end'] = $dt_end_obj->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $params[':dt_end'] = $dto . ' 23:59:59'; // Fallback
    }
}
// <-- [จบจุดที่กูแก้]

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// =======================[ Sort ]=====================================
$ORDER_MAP = [
    'created_desc' => 'l.created_at DESC',
    'created_asc'  => 'l.created_at ASC',
    'price_desc'   => 'l.price DESC',
    'price_asc'    => 'l.price ASC',
    'title_asc'    => 'l.title ASC'
];
$orderBy = $ORDER_MAP[$sort] ?? $ORDER_MAP['created_desc'];

// =======================[ Count + Fetch ]============================
$stc = $pdo->prepare("SELECT COUNT(*) FROM listings l {$where_sql}");
foreach ($params as $k => $v) $stc->bindValue($k, $v);
$stc->execute();
$total = (int)($stc->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $per;
}

$sql = "
SELECT l.*
FROM listings l
{$where_sql}
ORDER BY {$orderBy}, l.id DESC
LIMIT :limit OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $per, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$listings = $st->fetchAll(PDO::FETCH_ASSOC);

// Helper function to map status enum to Thai text
function format_listing_status($status, $in_stock, $stock_qty)
{
    if (!$in_stock || $stock_qty <= 0) {
        return '<span class="badge badge-red">หมดสต็อก</span>';
    }
    switch ($status) {
        case 'published':
            return '<span class="badge">เผยแพร่</span>';
        case 'draft':
            return '<span class="badge badge-gray">ฉบับร่าง</span>';
        case 'archived':
            return '<span class="badge badge-blue">จัดเก็บ</span>';
        case 'sold':
            return '<span class="badge badge-purple">ขายแล้ว</span>';
        default:
            return '<span class="badge badge-gray">ไม่ระบุ</span>';
    }
}

// =======================[ Template ]=================================
require_once __DIR__ . '/../templates/header_admin.php';

?>
<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="../dashboard/index.php" class="view-site">กลับแดชบอร์ด</a>
    </div>

    <div class="section-header">
        <h2>รายการสินค้า (Listings)</h2>
        <a href="add_listing.php" class="btn-primary">+ เพิ่มรายการใหม่</a>
    </div>

    <form action="index.php" method="get" class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อ/แบรนด์/SKU">

        <select name="status" class="filter-input" aria-label="สถานะ">
            <option value="">ทุกสถานะ</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>เผยแพร่</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>ฉบับร่าง</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>จัดเก็บ</option>
            <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>ขายแล้ว</option>
        </select>

        <select name="sort" class="filter-input" aria-label="เรียง">
            <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>ล่าสุดก่อน</option>
            <option value="created_asc" <?= $sort === 'created_asc' ? 'selected' : ''  ?>>เก่าสุดก่อน</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : ''   ?>>ราคา: มากไปน้อย</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : ''    ?>>ราคา: น้อยไปมาก</option>
            <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : ''    ?>>ชื่อ (A→Z)</option>
        </select>

        <div class="filter-dropdown">
            <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuListings')">ตัวกรอง</button>
            <div id="filterMenuListings" class="filter-menu">
                <div class="filter-section">
                    <div class="filter-title">แบรนด์</div>
                    <?php foreach ($brandRows as $b): $checked = in_array($b, $brands, true) ? 'checked' : ''; ?>
                        <label class="checkline"><input type="checkbox" name="brand[]" value="<?= h($b) ?>" <?= $checked ?>><span><?= h($b) ?></span></label>
                    <?php endforeach;
                    if (!$brandRows): ?>
                        <div class="muted">ยังไม่มีแบรนด์</div>
                    <?php endif; ?>
                </div>
                <div class="filter-section">
                    <div class="filter-title">ราคา</div>
                    <div class="range-inline">
                        <input type="number" step="1" name="pmin" placeholder="ต่ำสุด" value="<?= h($price_min) ?>">
                        <span class="mx-4">ถึง</span>
                        <input type="number" step="1" name="pmax" placeholder="สูงสุด" value="<?= h($price_max) ?>">
                    </div>
                </div>
                <div class="filter-section">
                    <div class="filter-title">วันที่เพิ่ม</div>
                    <div class="range-inline">
                        <input type="date" name="date_from" value="<?= h($dfrom) ?>">
                        <span class="mx-4">ถึง</span>
                        <input type="date" name="date_to" value="<?= h($dto) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuListings')">ล้าง</button>
                    <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
                </div>
            </div>
        </div>

        <input type="hidden" name="page" value="1">
        <button type="submit" class="btn-search">ค้นหา</button>
        <a href="index.php" class="btn-secondary" style="margin-left: 8px;">ล้างทั้งหมด</a>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>รูป</th>
                    <th>ชื่อสินค้า</th>
                    <th>แบรนด์</th>
                    <th>SKU</th>
                    <th class="ta-r">ราคา</th>
                    <th class="ta-c">สต็อก</th>
                    <th>วันที่เพิ่ม</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($listings): foreach ($listings as $i => $l): ?>
                        <tr>
                            <td><?= ($offset + $i + 1) ?></td>
                            <td>
                                <?php
                                $img_src = (!empty($l['main_image']) && substr($l['main_image'], 0, 1) !== '/' && !preg_match('~^https?://~', $l['main_image']))
                                    ? '/' . ltrim($l['main_image'], '/')
                                    : $l['main_image'];
                                ?>
                                <?php if (!empty($img_src)): ?>
                                    <button type="button" class="thumb-btn" data-src="<?= h($img_src) ?>">
                                        <img src="<?= h($img_src) ?>" class="thumb" alt="">
                                    </button>
                                <?php else: ?><div class="thumb"></div><?php endif; ?>
                            </td>
                            <td><strong><?= h($l['title']) ?></strong></td>
                            <td><?= h($l['brand'] ?: '-') ?></td>
                            <td><?= h($l['sku'] ?: '-') ?></td>
                            <td class="ta-r"><?= number_format((float)($l['price'] ?? 0), 0) ?></td>
                            <td class="ta-c"><?= $l['in_stock'] ? (h($l['stock_qty'] ?? '✓')) : '0' ?></td>
                            <td class="muted"><?= h(date('d/m/y H:i', strtotime($l['created_at'] ?? 'now'))) ?></td>
                            <td><?= format_listing_status($l['status'], $l['in_stock'], $l['stock_qty']) ?></td>
                            <td class="no-wrap">
                                <a href="edit_listing.php?id=<?= (int)$l['id'] ?>" class="btn-edit">แก้ไข</a>

                                <form action="delete_listing.php" method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">

                                    <a href="#" class="btn-delete"
                                        onclick="if (confirm('ต้องการลบรายการสินค้านี้ใช่หรือไม่?')) { this.closest('form').submit(); } return false;">
                                        ลบ
                                    </a>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="10" class="text-center">ไม่พบรายการสินค้า</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-bar">
        <div class="pager-left">
            <span class="pager-total">พบ <?= (int)$total ?> รายการ</span>
            <span class="divider">•</span>
            <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
        </div>
        <nav class="pager-nav" aria-label="Pagination">
            <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? page_url($page - 1) : '#' ?>" rel="prev" aria-label="ก่อนหน้า">‹</a>
            <?php $start = max(1, $page - 2);
            $end = min($pages, $page + 2);
            if ($start > 1) echo '<span class="page-ellipsis">…</span>';
            for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= page_url($i) ?>"><?= $i ?></a>
            <?php endfor;
            if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
            <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? page_url($page + 1) : '#' ?>" rel="next" aria-label="ถัดไป">›</a>
            <div class="page-size">
                <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
                    <?php foreach ([20, 50, 100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </nav>
    </div>

    <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
        <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
            <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
            <img id="imgPreview" src="" alt="" class="imgpv-img">
        </div>
    </div>
</main>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
    // Dropdown filter
    function toggleMenu(id) {
        var m = document.getElementById(id);
        if (m) m.classList.toggle('show');
    }

    function clearMenu(id) {
        var root = document.getElementById(id);
        if (!root) return;
        root.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
        root.querySelectorAll('input[type="number"],input[type="date"]').forEach(el => el.value = '');
    }
    document.addEventListener('click', function(e) {
        var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
        document.querySelectorAll('.filter-menu.show').forEach(function(m) {
            if (!dd || !dd.contains(m)) m.classList.remove('show');
        });
    });

    // Image preview modal
    (function() {
        var overlay = document.getElementById('imgPreviewOverlay');
        var imgEl = document.getElementById('imgPreview');

        function openPreview(src) {
            if (!overlay || !imgEl) return;
            imgEl.src = src;
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
        }

        function closePreview() {
            if (!overlay) return;
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
            imgEl.src = '';
        }
        document.addEventListener('click', function(e) {
            var btn = e.target.closest ? e.target.closest('.thumb-btn') : null;
            if (!btn) return;
            var src = btn.getAttribute('data-src');
            if (src) openPreview(src);
        });
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay || e.target.closest('.imgpv-close')) closePreview();
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) closePreview();
        });
    })();

    // Per-page selector
    (function() {
        const sel = document.getElementById('ppSelect');
        if (!sel) return;
        sel.addEventListener('change', function() {
            const u = new URL(location.href);
            u.searchParams.set('per', this.value);
            u.searchParams.set('page', '1'); // Reset to page 1 when changing per page
            location = u.toString();
        });
    })();

    // Arrow keys pager
    (function() {
        document.addEventListener('keydown', function(e) {
            if (e.altKey || e.metaKey || e.ctrlKey || e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
            if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
            if (e.key === 'ArrowLeft') document.querySelector('.page-btn[rel="prev"]')?.click();
        });
    })();
</script>