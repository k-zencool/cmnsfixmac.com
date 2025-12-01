<?php

/********************************************************************
 * admin/warranty/index.php — Warranty console (jobs | claims | policy | report)
 * สไตล์เรียบง่าย เน้นอ่านง่าย ใช้ง่าย
 * [GEMINI EDIT v1]
 * - Changed default 'jobs' status from 'in_warranty' to 'all'
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

/* ---------------- Helpers (prefix w_) ---------------- */
function w_getv($k, $d = null)
{
  return isset($_GET[$k]) ? trim($_GET[$k]) : $d;
}
function w_get_pager()
{
  $per = max(5, min(200, (int)w_getv('per', 20)));
  $page = max(1, (int)w_getv('page', 1));
  return [$per, $page, ($page - 1) * $per];
}
function w_page_url($i)
{
  $q = $_GET;
  $q['page'] = max(1, (int)$i);
  return '?' . http_build_query($q);
}
function w_whereLikes($q, $cols, &$params, $pfx)
{
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
/* เลือกคอลัมน์แรกที่มีจริงในตาราง แล้ว alias เป็นชื่อมาตรฐาน */
function w_pick_col(PDO $pdo, string $table, array $cands, string $alias, string $prefix = 'c.'): array
{
  $col = null;
  foreach ($cands as $cand) {
    $chk = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
    try {
      $chk->execute([':c' => $cand]);
    } catch (Throwable $e) {
    }
    if (!empty($chk) && $chk->fetch(PDO::FETCH_ASSOC)) {
      $col = $cand;
      break;
    }
  }
  return ['exists' => $col !== null, 'select' => $col ? "{$prefix}`{$col}` AS {$alias}" : "NULL AS {$alias}", 'field' => $col ? "{$prefix}`{$col}`" : null, 'name' => $col];
}

/* ---------------- STATE ---------------- */
$pageTitle = "จัดการประกัน";
$tab  = w_getv('tab', 'jobs');     // jobs|claims|policy|report
$q    = w_getv('q', '');
$msg  = w_getv('msg', '');
$err  = w_getv('err', '');
list($per, $page, $offset) = w_get_pager();

/* filters */
$JSTAT = ['in_warranty' => 'ยังอยู่ในประกัน', 'soon7' => 'ใกล้หมด 7 วัน', 'expired' => 'หมดประกัน', 'void' => 'โมฆะ', 'all' => 'ทั้งหมด'];
// <-- [กูแก้!!] เปลี่ยน 'in_warranty' เป็น 'all'
$j_status    = w_getv('status', 'all');
// <-- [จบจุดที่กูแก้!!]
$j_date_by   = w_getv('date_by', 'until'); // until|base
$j_date_from = w_getv('date_from', '');
$j_date_to   = w_getv('date_to', '');

$CSTAT = ['open' => 'เปิดใหม่', 'investigating' => 'กำลังตรวจสอบ', 'accepted' => 'รับเคลม', 'rejected' => 'ปฏิเสธ', 'closed' => 'ปิดเคส', 'void' => 'โมฆะ', 'all' => 'ทั้งหมด'];
$c_status    = w_getv('c_status', 'all');
$c_date_from = w_getv('c_from', '');
$c_date_to   = w_getv('c_to', '');

$r_by   = w_getv('r_by', 'until');  // report: until|base|created
$r_from = w_getv('r_from', '');
$r_to   = w_getv('r_to', '');

/* ---------------- LOAD DATA ---------------- */
$jobs = [];
$claims = [];
$KPI = null;
$trendCreated = [];
$trendExpire = [];
$claimsByStatus = [];
$soon = [];
$total = 0;
$pages = 1;

/* ===== JOBS ===== */
if ($tab === 'jobs') {
  require_perms(['warranty.jobs.view']);
  $params = [];
  $where = [];
  if ($w = w_whereLikes($q, ['warranty_no', 'repair_no', 'device_model', 'sn', 'customer_name', 'customer_phone'], $params, 'wj')) $where[] = $w;

  if ($j_status !== 'all') {
    if ($j_status === 'soon7') {
      $where[] = "warranty_status='in_warranty' AND warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } else {
      $where[] = "warranty_status=:ws";
      $params[':ws'] = $j_status;
    }
  }
  $dateCol = ($j_date_by === 'base') ? 'base_date' : 'warranty_until';
  if ($j_date_from !== '') {
    $where[] = "$dateCol>=:df";
    $params[':df'] = $j_date_from;
  }
  if ($j_date_to  !== '') {
    $where[] = "$dateCol<=:dt";
    $params[':dt'] = $j_date_to;
  }

  $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $stc = $pdo->prepare("SELECT COUNT(*) FROM warranty_jobs {$where_sql}");
  $stc->execute($params);
  $total = (int)$stc->fetchColumn();
  $pages = max(1, (int)ceil($total / $per));
  if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $per;
  }

  $sql = "
      SELECT id, warranty_no, repair_no, customer_name, customer_phone,
             device_model, sn, base_date, warranty_days, warranty_until,
             warranty_status, created_at, DATEDIFF(warranty_until, CURDATE()) AS days_left
      FROM warranty_jobs
      {$where_sql}
      ORDER BY (warranty_status='in_warranty') DESC,
               (DATEDIFF(warranty_until, CURDATE()) BETWEEN 0 AND 7) DESC,
               warranty_until ASC, id DESC
      LIMIT :lim OFFSET :off";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== CLAIMS (robust column detection) ===== */
if ($tab === 'claims') {
  require_perms(['warranty.claims.view']);
  $C_CLAIM_NO = w_pick_col($pdo, 'warranty_claims', ['claim_no', 'claim_code', 'no', 'ref_no'], 'claim_no');
  $C_STATUS   = w_pick_col($pdo, 'warranty_claims', ['claim_status', 'status', 'state', 'result'], 'claim_status');
  $C_ISSUE    = w_pick_col($pdo, 'warranty_claims', ['issue_summary', 'issue_text', 'issue', 'description', 'details', 'notes', 'remark', 'remarks', 'claim_reason'], 'issue_text');
  $C_CREATED  = w_pick_col($pdo, 'warranty_claims', ['created_at', 'opened_at', 'created', 'claim_date', 'date'], 'created_at');

  $params = [];
  $where = [];
  $likeCols = [];
  if ($C_CLAIM_NO['field']) $likeCols[] = $C_CLAIM_NO['field'];
  $likeCols[] = 'j.warranty_no';
  if ($C_ISSUE['field']) $likeCols[] = $C_ISSUE['field'];
  if ($q !== '' && $likeCols) {
    $ors = [];
    $i = 0;
    foreach ($likeCols as $c) {
      $ph = ":wc{$i}";
      $ors[] = "$c LIKE $ph";
      $params[$ph] = "%{$q}%";
      $i++;
    }
    $where[] = '(' . implode(' OR ', $ors) . ')';
  }
  if ($c_status !== 'all' && $C_STATUS['field']) {
    $where[] = $C_STATUS['field'] . '=:cs';
    $params[':cs'] = $c_status;
  }
  if ($C_CREATED['field']) {
    if ($c_date_from !== '') {
      $where[] = "DATE({$C_CREATED['field']})>=:cf";
      $params[':cf'] = $c_date_from;
    }
    if ($c_date_to  !== '') {
      $where[] = "DATE({$C_CREATED['field']})<=:ct";
      $params[':ct'] = $c_date_to;
    }
  }

  $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $stc = $pdo->prepare("SELECT COUNT(*) FROM warranty_claims c LEFT JOIN warranty_jobs j ON j.id=c.job_id {$where_sql}");
  $stc->execute($params);
  $total = (int)$stc->fetchColumn();
  $pages = max(1, (int)ceil($total / $per));
  if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $per;
  }

  $sql = "
      SELECT c.id, c.job_id,
             {$C_CLAIM_NO['select']},
             {$C_STATUS['select']},
             {$C_ISSUE['select']},
             {$C_CREATED['select']},
             j.warranty_no, j.repair_no
      FROM warranty_claims c
      LEFT JOIN warranty_jobs j ON j.id=c.job_id
      {$where_sql}
      ORDER BY c.id DESC
      LIMIT :lim OFFSET :off";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $claims = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== POLICY (ใหม่ทั้งชุด) ===== */
if ($tab === 'policy') {
  require_perms(['warranty.policy.view']);

  // ฉบับที่มีผล "วันนี้"
  $sqlEff = "
      SELECT id, version, title, body, effective_from, effective_to, is_default
      FROM warranty_terms
      WHERE COALESCE(effective_from,'1000-01-01') <= CURDATE()
        AND COALESCE(effective_to,'9999-12-31')  >= CURDATE()
      ORDER BY id DESC
      LIMIT 1";
  $policyEffective = $pdo->query($sqlEff)->fetch(PDO::FETCH_ASSOC) ?: null;

  // ฉบับค่าเริ่มต้น
  $sqlDef = "
      SELECT id, version, title, body, effective_from, effective_to, is_default
      FROM warranty_terms
      WHERE is_default = 1
      ORDER BY id DESC
      LIMIT 1";
  $policyDefault = $pdo->query($sqlDef)->fetch(PDO::FETCH_ASSOC) ?: null;

  // ถ้าตัวเดียวกัน ไม่ต้องแสดงการ์ดซ้ำ
  $showDefaultCard = !(!empty($policyEffective['id']) && !empty($policyDefault['id']) && (int)$policyEffective['id'] === (int)$policyDefault['id']);

  // ค้นหา/แบ่งหน้า
  $params = [];
  $where = [];
  if ($q !== '') {
    $where[] = "(title LIKE :q OR body LIKE :q OR version LIKE :q)";
    $params[':q'] = "%{$q}%";
  }
  $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

  $stc = $pdo->prepare("SELECT COUNT(*) FROM warranty_terms {$where_sql}");
  $stc->execute($params);
  $total = (int)$stc->fetchColumn();
  $pages = max(1, (int)ceil($total / $per));
  if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $per;
  }

  $sqlList = "
      SELECT id, version, title, effective_from, effective_to, is_default
      FROM warranty_terms
      {$where_sql}
      ORDER BY COALESCE(effective_from,'9999-12-31') DESC, id DESC
      LIMIT :lim OFFSET :off";
  $st = $pdo->prepare($sqlList);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $policies = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== REPORT ===== */
if ($tab === 'report') {
  require_perms(['warranty.report.view']);

  $dateCol = ($r_by === 'base' ? 'base_date' : ($r_by === 'created' ? 'created_at' : 'warranty_until'));
  $params = [];
  $where = [];
  if ($r_from !== '') {
    $where[] = "$dateCol>=:rf";
    $params[':rf'] = $r_from;
  }
  if ($r_to  !== '') {
    $where[] = "$dateCol<=:rt";
    $params[':rt'] = $r_to;
  }
  $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

  $sqlKpi = "
      SELECT
        SUM(warranty_status='in_warranty') AS in_warranty,
        SUM(warranty_status='expired')     AS expired,
        SUM(warranty_status='void')        AS void_cnt,
        SUM(warranty_status='in_warranty' AND warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))  AS soon7,
        SUM(warranty_status='in_warranty' AND warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS soon30
      FROM warranty_jobs
      {$where_sql}";
  $kpi = $pdo->prepare($sqlKpi);
  $kpi->execute($params);
  $KPI = $kpi->fetch(PDO::FETCH_ASSOC) ?: ['in_warranty' => 0, 'expired' => 0, 'void_cnt' => 0, 'soon7' => 0, 'soon30' => 0];

  $sqlSoon = "
      SELECT warranty_no, repair_no, customer_name, device_model, warranty_until,
             DATEDIFF(warranty_until, CURDATE()) AS days_left
      FROM warranty_jobs
      WHERE warranty_status='in_warranty'
        AND warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      ORDER BY warranty_until ASC
      LIMIT 20";
  $soon = $pdo->query($sqlSoon)->fetchAll(PDO::FETCH_ASSOC);

  // สรุปการเคลมตามสถานะ
  $C_STATUS = w_pick_col($pdo, 'warranty_claims', ['claim_status', 'status', 'state', 'result'], 'claim_status', '');
  if ($C_STATUS['field']) {
    $claimsByStatus = $pdo->query("
          SELECT {$C_STATUS['field']} AS claim_status, COUNT(*) AS c
          FROM warranty_claims GROUP BY {$C_STATUS['field']}")->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $claimsByStatus = [];
  }
}

/* ---------------- TEMPLATE ---------------- */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= w_h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="view-switcher">
    <?php if (can('warranty.jobs.view')): ?>
      <a class="switcher-item <?= $tab === 'jobs' ? 'active' : '' ?>" href="index.php?tab=jobs">งานรับประกัน</a>
    <?php endif; ?>
    <?php if (can('warranty.claims.view')): ?>
      <a class="switcher-item <?= $tab === 'claims' ? 'active' : '' ?>" href="index.php?tab=claims">การเคลม</a>
    <?php endif; ?>
    <?php if (can('warranty.policy.view')): ?>
      <a class="switcher-item <?= $tab === 'policy' ? 'active' : '' ?>" href="index.php?tab=policy">นโยบาย</a>
    <?php endif; ?>
    <?php if (can('warranty.report.view')): ?>
      <a class="switcher-item <?= $tab === 'report' ? 'active' : '' ?>" href="index.php?tab=report">สรุป/รายงาน</a>
    <?php endif; ?>
  </div>

  <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;margin:8px 0 12px;">
    <h2>
      <?php if ($tab === 'jobs'): ?>งานรับประกัน
      <?php elseif ($tab === 'claims'): ?>การเคลม
      <?php elseif ($tab === 'policy'): ?>นโยบาย
      <?php else: ?>สรุป/รายงาน<?php endif; ?>
    </h2>
    <div>
      <?php if ($tab === 'jobs' && can('warranty.jobs.create')): ?>
        <a href="job_form.php" class="btn-primary">+ เพิ่มงานประกัน</a>
      <?php endif; ?>
      <?php if ($tab === 'claims' && can('warranty.claims.create')): ?>
        <a href="claim_form.php" class="btn-primary">+ เปิดเคลม</a>
      <?php endif; ?>
      <?php if ($tab === 'policy' && can('warranty.policy.update')): ?>
        <a href="policy_form.php" class="btn-primary">+ แก้นโยบาย</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= w_h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= w_h($err) ?></div><?php endif; ?>

  <?php if ($tab === 'jobs'): ?>
    <form action="index.php" method="get">
      <input type="hidden" name="tab" value="jobs">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= w_h($q) ?>" placeholder="เลขประกัน / เลขงานซ่อม / SN / ลูกค้า / รุ่น">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuJobs')">ตัวกรอง</button>
          <div id="filterMenuJobs" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">สถานะ</div>
              <?php foreach ($JSTAT as $v => $lb): ?>
                <label class="checkline"><input type="radio" name="status" value="<?= w_h($v) ?>" <?= $j_status === $v ? 'checked' : '' ?>><span><?= w_h($lb) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">ช่วงวันที่</div>
              <label class="checkline"><input type="radio" name="date_by" value="until" <?= $j_date_by === 'until' ? 'checked' : '' ?>><span>อิงวันหมดประกัน</span></label>
              <label class="checkline"><input type="radio" name="date_by" value="base" <?= $j_date_by === 'base' ? 'checked' : '' ?>><span>อิงวันเริ่มนับ</span></label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">
                <input type="date" name="date_from" value="<?= w_h($j_date_from) ?>">
                <input type="date" name="date_to" value="<?= w_h($j_date_to) ?>">
              </div>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuJobs')">ล้าง</button>
                <button class="btn-primary">ใช้ตัวกรอง</button>
              </div>
            </div>
          </div>
        </div>
        <input type="hidden" name="page" value="1">
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <colgroup>
          <col class="w-col-idx">
          <col class="w-col-code">
          <col class="w-col-repair">
          <col>
          <col>
          <col>
          <col>
          <col>
          <col>
        </colgroup>
        <thead>
          <tr>
            <th class="center">#</th>
            <th>เลขประกัน</th>
            <th>เลขงานซ่อม</th>
            <th>ลูกค้า</th>
            <th>อุปกรณ์</th>
            <th class="center">S/N</th>
            <th>เริ่ม</th>
            <th>หมดประกัน</th>
            <th class="center">สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($jobs): foreach ($jobs as $i => $r):
              $d = isset($r['days_left']) ? (int)$r['days_left'] : null;
              $label = w_status_label($r['warranty_status'], $d);
              $klass = w_badge_class($r['warranty_status'], $d);
              $title = ($d !== null ? ($d >= 0 ? "เหลืออีก {$d} วัน" : "เลยกำหนดมา " . abs($d) . " วัน") : '');
          ?>
              <tr data-goto="/admin/warranty/job_view.php?id=<?= (int)$r['id'] ?>">
                <td class="center mono"><?= $offset + $i + 1 ?></td>
                <td class="mono nowrap">
                  <div class="cell-code"><strong><?= w_h_nb($r['warranty_no']) ?></strong>
                    <button type="button" class="copy-btn" data-copy="<?= w_h($r['warranty_no']) ?>" title="คัดลอก">⧉</button>
                  </div>
                </td>
                <td class="mono nowrap">
                  <div class="cell-code">
                    <?= $r['repair_no'] ? w_h_nb($r['repair_no']) : '-' ?>
                    <?php if (!empty($r['repair_no'])): ?>
                      <button type="button" class="copy-btn" data-copy="<?= w_h($r['repair_no']) ?>" title="คัดลอก">⧉</button>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= w_h($r['customer_name']) ?><?php if (!empty($r['customer_phone'])): ?><br><small class="muted mono"><?= w_h($r['customer_phone']) ?></small><?php endif; ?></td>
                <td><?= w_h($r['device_model']) ?></td>
                <td class="center mono muted"><?= w_h($r['sn'] ?: '-') ?></td>
                <td class="mono"><?= w_h($r['base_date'] ?: '-') ?></td>
                <td class="mono"><strong><?= w_h($r['warranty_until'] ?: '-') ?></strong></td>
                <td class="center"><span class="badge <?= w_h($klass) ?>" title="<?= w_h($title) ?>"><?= w_h($label) ?></span></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="9" class="text-center">ยังไม่มีข้อมูล</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pager-bar">
      <div class="pager-left"><span class="pager-total">พบ <?= (int)$total ?> รายการ</span><span class="divider">•</span><span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span></div>
      <nav class="pager-nav" aria-label="Pagination">
        <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? w_page_url($page - 1) : '#' ?>" rel="prev">‹</a>
        <?php $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        if ($start > 1) echo '<span class="page-ellipsis">…</span>';
        for ($i = $start; $i <= $end; $i++): ?>
          <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= w_page_url($i) ?>"><?= $i ?></a>
        <?php endfor;
        if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? w_page_url($page + 1) : '#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach ([20, 50, 100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>

  <?php elseif ($tab === 'claims'): ?>
    <form action="index.php" method="get">
      <input type="hidden" name="tab" value="claims">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= w_h($q) ?>" placeholder="เลขเคลม / เลขประกัน / อาการ / บันทึก">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuClaims')">ตัวกรอง</button>
          <div id="filterMenuClaims" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">สถานะ</div>
              <?php foreach ($CSTAT as $v => $lb): ?>
                <label class="checkline"><input type="radio" name="c_status" value="<?= w_h($v) ?>" <?= $c_status === $v ? 'checked' : '' ?>><span><?= w_h($lb) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">ช่วงวันที่ (เปิดเคลม)</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <input type="date" name="c_from" value="<?= w_h($c_date_from) ?>">
                <input type="date" name="c_to" value="<?= w_h($c_date_to) ?>">
              </div>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuClaims')">ล้าง</button>
                <button class="btn-primary">ใช้ตัวกรอง</button>
              </div>
            </div>
          </div>
        </div>
        <input type="hidden" name="page" value="1">
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <colgroup>
          <col class="w-col-idx">
          <col class="w-col-claim">
          <col class="w-col-code">
          <col class="w-col-repair">
          <col>
          <col>
          <col>
        </colgroup>
        <thead>
          <tr>
            <th class="center">#</th>
            <th>เลขเคลม</th>
            <th>เลขประกัน</th>
            <th>เลขงานซ่อม</th>
            <th>อาการ</th>
            <th>เปิดเมื่อ</th>
            <th class="center">สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($claims): foreach ($claims as $i => $r):
              $cs = (string)$r['claim_status'];
              $cls = ['open' => 'badge-amber', 'investigating' => 'badge-blue', 'accepted' => 'badge-green', 'rejected' => 'badge-red', 'closed' => '', 'void' => 'badge-amber', 'pending' => 'badge-amber'][$cs] ?? '';
              $cs_th = ['open' => 'เปิดใหม่', 'investigating' => 'กำลังตรวจสอบ', 'accepted' => 'รับเคลม', 'rejected' => 'ปฏิเสธ', 'closed' => 'ปิดเคส', 'void' => 'โมฆะ', 'pending' => 'รอตรวจ'][$cs] ?? $cs;
          ?>
              <tr data-goto="/admin/warranty/claim_view.php?id=<?= (int)$r['id'] ?>">
                <td class="center mono"><?= $offset + $i + 1 ?></td>
                <td class="mono nowrap">
                  <div class="cell-code"><strong><?= w_h_nb($r['claim_no']) ?></strong>
                    <button type="button" class="copy-btn" data-copy="<?= w_h($r['claim_no']) ?>">⧉</button>
                  </div>
                </td>
                <td class="mono nowrap"><?= $r['warranty_no'] ? w_h_nb($r['warranty_no']) : '-' ?></td>
                <td class="mono nowrap"><?= $r['repair_no'] ? w_h_nb($r['repair_no']) : '-' ?></td>
                <td class="muted"><?= w_h($r['issue_text'] ?? '-') ?></td>
                <td class="mono"><?= w_h($r['created_at'] ?? '-') ?></td>
                <td class="center"><span class="badge <?= w_h($cls) ?>"><?= w_h($cs_th) ?></span></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="7" class="text-center">ยังไม่มีการเคลม</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pager-bar">
      <div class="pager-left"><span class="pager-total">พบ <?= (int)$total ?> รายการ</span><span class="divider">•</span><span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span></div>
      <nav class="pager-nav" aria-label="Pagination">
        <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? w_page_url($page - 1) : '#' ?>" rel="prev">‹</a>
        <?php $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        if ($start > 1) echo '<span class="page-ellipsis">…</span>';
        for ($i = $start; $i <= $end; $i++): ?>
          <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= w_page_url($i) ?>"><?= $i ?></a>
        <?php endfor;
        if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? w_page_url($page + 1) : '#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach ([20, 50, 100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>

  <?php elseif ($tab === 'policy'): ?>

    <form action="index.php" method="get" class="policy-toolbar policy-toolbar--sticky">
      <input type="hidden" name="tab" value="policy">
      <div class="policy-toolbar__left">
        <input class="filter-input policy-search" name="q" value="<?= w_h($q) ?>" placeholder="ค้นหาเวอร์ชัน / ชื่อเรื่อง / เนื้อหา">
        <button class="btn-search">ค้นหา</button>
      </div>
      <div class="policy-toolbar__right">
        <?php if (can('warranty.policy.update')): ?>
          <a href="policy_form.php" class="btn-primary">+ เพิ่ม/แก้นโยบาย</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="policy-cards<?= $showDefaultCard ? '' : ' one' ?>">
      <section class="policy-card">
        <header class="policy-card__head">
          <div>
            <span class="chip chip--live">ฉบับที่ใช้อยู่</span>
            <?php if ($policyEffective): ?>
              <h3 class="policy-card__title">
                v<?= w_h($policyEffective['version']) ?> — <?= w_h($policyEffective['title']) ?>
              </h3>
              <div class="policy-card__meta">
                มีผล <?= w_h($policyEffective['effective_from'] ?: '—') ?>
                ถึง <?= w_h($policyEffective['effective_to'] ?: 'ไม่มีกำหนด') ?>
                <?php if (!empty($policyEffective['is_default'])): ?>
                  <span class="chip chip--muted">ค่าเริ่มต้น</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <h3 class="policy-card__title muted">ยังไม่กำหนดช่วงที่มีผล</h3>
              <div class="policy-card__meta">ระบบจะใช้ฉบับ “ค่าเริ่มต้น” โดยอัตโนมัติ</div>
            <?php endif; ?>
          </div>
          <div>
            <?php if (can('warranty.policy.update') && !empty($policyEffective['id'])): ?>
              <a class="btn-secondary btn-xs" href="policy_form.php?id=<?= (int)$policyEffective['id'] ?>">แก้ไข</a>
            <?php endif; ?>
          </div>
        </header>
        <div class="policy-card__body is-clamp" id="policyBody">
          <?= !empty($policyEffective['body'])
            ? nl2br(w_h($policyEffective['body']))
            : '<div class="muted">— ไม่มีเนื้อหา —</div>' ?>
        </div>
        <?php if (!empty($policyEffective['body']) && mb_strlen($policyEffective['body']) > 240): ?>
          <footer class="policy-card__foot">
            <button type="button" class="btn-secondary btn-xs" id="toggleClamp">อ่านต่อ</button>
          </footer>
        <?php endif; ?>
      </section>

      <?php if ($showDefaultCard): ?>
        <section class="policy-card">
          <header class="policy-card__head">
            <div>
              <span class="chip chip--muted">ฉบับค่าเริ่มต้น</span>
              <?php if ($policyDefault): ?>
                <h3 class="policy-card__title">v<?= w_h($policyDefault['version']) ?> — <?= w_h($policyDefault['title']) ?></h3>
                <div class="policy-card__meta">
                  มีผล <?= w_h($policyDefault['effective_from'] ?: '—') ?>
                  ถึง <?= w_h($policyDefault['effective_to'] ?: 'ไม่มีกำหนด') ?>
                </div>
              <?php else: ?>
                <h3 class="policy-card__title muted">ยังไม่ได้ตั้งค่าเริ่มต้น</h3>
              <?php endif; ?>
            </div>
            <div>
              <?php if (can('warranty.policy.update') && !empty($policyDefault['id'])): ?>
                <a class="btn-secondary btn-xs" href="policy_form.php?id=<?= (int)$policyDefault['id'] ?>">แก้ไข</a>
              <?php endif; ?>
            </div>
          </header>
          <div class="policy-card__body">
            <?= !empty($policyDefault['body'])
              ? nl2br(w_h($policyDefault['body']))
              : '<div class="muted">— ไม่มีเนื้อหา —</div>' ?>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <div class="table-container">
      <div class="table-head-min">
        <h3 class="table-title">เวอร์ชันทั้งหมด</h3>
      </div>
      <table class="data-table policy-table">
        <colgroup>
          <col class="w-col-idx">
          <col style="width:120px">
          <col>
          <col style="width:180px">
          <col style="width:160px">
          <col style="width:120px">
        </colgroup>
        <thead>
          <tr>
            <th class="center">#</th>
            <th>เวอร์ชัน</th>
            <th>ชื่อเรื่อง</th>
            <th>มีผลตั้งแต่</th>
            <th>สิ้นสุด</th>
            <th class="center">ค่าเริ่มต้น</th>
          </tr>
        </thead>

        <tbody>
          <?php if (!empty($policies)): foreach ($policies as $i => $r):
              $isNow = ($r['effective_from'] <= date('Y-m-d') && (empty($r['effective_to']) || $r['effective_to'] >= date('Y-m-d')));
          ?>
              <tr class="<?= $isNow ? 'is-now' : '' ?>" data-goto="policy_form.php?id=<?= (int)$r['id'] ?>">
                <td class="center mono"><?= $offset + $i + 1 ?></td>
                <td class="mono"><span class="ver-badge">v<?= w_h_nb($r['version']) ?></span></td>
                <td><?= w_h($r['title']) ?></td>
                <td class="mono"><?= w_h($r['effective_from'] ?: '—') ?></td>
                <td class="mono"><?= w_h($r['effective_to']   ?: 'ไม่มีกำหนด') ?></td>
                <td class="center">
                  <?= !empty($r['is_default']) ? '<span class="chip chip-gray">ค่าเริ่มต้น</span>' : '—' ?>
                </td>
              </tr>

            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="7" class="text-center">ยังไม่มีนโยบาย</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pager-bar">
      <div class="pager-left">
        <span class="pager-total">พบ <?= (int)$total ?> เวอร์ชัน</span>
        <span class="divider">•</span>
        <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
      </div>
      <nav class="pager-nav" aria-label="Pagination">
        <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? w_page_url($page - 1) : '#' ?>" rel="prev">‹</a>
        <?php $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        if ($start > 1) echo '<span class="page-ellipsis">…</span>';
        for ($i = $start; $i <= $end; $i++): ?>
          <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= w_page_url($i) ?>"><?= $i ?></a>
        <?php endfor;
        if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? w_page_url($page + 1) : '#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach ([20, 50, 100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>




  <?php elseif ($tab === 'report'): ?>
    <form action="index.php" method="get" class="search-and-filter-group report-simple-toolbar">
      <input type="hidden" name="tab" value="report">
      <select name="r_by" class="filter-input">
        <option value="until" <?= $r_by === 'until'  ? 'selected' : '' ?>>กรองด้วยวันหมดประกัน</option>
        <option value="base" <?= $r_by === 'base'   ? 'selected' : '' ?>>กรองด้วยวันเริ่มนับ</option>
        <option value="created" <?= $r_by === 'created' ? 'selected' : '' ?>>กรองด้วยวันบันทึก</option>
      </select>
      <input type="date" name="r_from" value="<?= w_h($r_from) ?>">
      <input type="date" name="r_to" value="<?= w_h($r_to) ?>">
      <button class="btn-search">ดูสรุป</button>

      <div class="quick-range">
        <button type="button" class="btn-secondary" data-range="today">วันนี้</button>
        <button type="button" class="btn-secondary" data-range="this_month">เดือนนี้</button>
        <button type="button" class="btn-secondary" data-range="last_30">30 วันหลัง</button>
        <button type="button" class="btn-secondary" data-range="clear">ล้าง</button>
      </div>
    </form>

    <section class="report-kpi-grid simple">
      <div class="report-kpi-card">
        <div class="kpi-top"><span class="kpi-dot kpi-green"></span><span>อยู่ในประกัน</span></div>
        <div class="kpi-num"><?= (int)$KPI['in_warranty'] ?></div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-top"><span class="kpi-dot kpi-amber"></span><span>ใกล้หมด 7 วัน</span></div>
        <div class="kpi-num"><?= (int)$KPI['soon7'] ?></div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-top"><span class="kpi-dot kpi-amber"></span><span>ใกล้หมด 30 วัน</span></div>
        <div class="kpi-num"><?= (int)$KPI['soon30'] ?></div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-top"><span class="kpi-dot kpi-gray"></span><span>หมดประกัน</span></div>
        <div class="kpi-num"><?= (int)$KPI['expired'] ?></div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-top"><span class="kpi-dot kpi-gray"></span><span>โมฆะ</span></div>
        <div class="kpi-num"><?= (int)$KPI['void_cnt'] ?></div>
      </div>
    </section>

    <?php $soon7 = [];
    $soon30rest = [];
    if (!empty($soon)) {
      foreach ($soon as $row) {
        $d = (int)($row['days_left'] ?? 0);
        if ($d <= 7) $soon7[] = $row;
        else $soon30rest[] = $row;
      }
    } ?>

    <div class="grid-2">
      <section class="table-container">
        <div class="table-head-min">
          <h3 class="table-title">การเคลมตามสถานะ</h3>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>สถานะเคลม</th>
              <th class="right">จำนวน</th>
            </tr>
          </thead>
          <tbody>
            <?php $MAP_TH = ['open' => 'เปิดใหม่', 'investigating' => 'กำลังตรวจสอบ', 'accepted' => 'รับเคลม', 'rejected' => 'ปฏิเสธ', 'closed' => 'ปิดเคส', 'void' => 'โมฆะ', 'pending' => 'รอตรวจ']; ?>
            <?php if ($claimsByStatus): foreach ($claimsByStatus as $r): $th = $MAP_TH[$r['claim_status']] ?? $r['claim_status']; ?>
                <tr>
                  <td><?= w_h($th) ?></td>
                  <td class="right mono"><?= (int)$r['c'] ?></td>
                </tr>
              <?php endforeach;
            else: ?><tr>
                <td colspan="2" class="text-center">ยังไม่มีการเคลม</td>
              </tr><?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="table-container">
        <div class="table-head-min">
          <h3 class="table-title">ใกล้หมดภายใน 7 วัน</h3>
        </div>
        <table class="data-table" id="tbl-soon7">
          <thead>
            <tr>
              <th class="w-idx">#</th>
              <th>เลขประกัน</th>
              <th>ลูกค้า</th>
              <th>อุปกรณ์</th>
              <th>หมดประกัน</th>
              <th class="center">เหลือ(วัน)</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($soon7): foreach ($soon7 as $i => $r): ?>
                <tr>
                  <td class="center mono"><?= $i + 1 ?></td>
                  <td class="mono nowrap"><?= w_h_nb($r['warranty_no']) ?></td>
                  <td><?= w_h($r['customer_name']) ?></td>
                  <td><?= w_h($r['device_model']) ?></td>
                  <td class="mono"><strong><?= w_h($r['warranty_until']) ?></strong></td>
                  <td class="center mono"><?= (int)$r['days_left'] ?></td>
                </tr>
              <?php endforeach;
            else: ?><tr>
                <td colspan="6" class="text-center">ไม่มีรายการ</td>
              </tr><?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <section class="table-container">
      <div class="table-head-min">
        <h3 class="table-title">กำลังจะหมดประกันใน 30 วัน (Top 20)</h3>
      </div>
      <table class="data-table" id="tbl-soon30">
        <colgroup>
          <col class="w-idx">
          <col class="w-col-code">
          <col class="w-col-repair">
          <col>
          <col>
          <col>
          <col>
        </colgroup>
        <thead>
          <tr>
            <th>#</th>
            <th>เลขประกัน</th>
            <th>เลขงานซ่อม</th>
            <th>ลูกค้า</th>
            <th>อุปกรณ์</th>
            <th>หมดประกัน</th>
            <th>เหลือ(วัน)</th>
          </tr>
        </thead>
        <tbody>
          <?php $list = $soon30rest ?: $soon;
          if ($list): foreach ($list as $i => $r): ?>
              <tr>
                <td class="center mono"><?= $i + 1 ?></td>
                <td class="mono nowrap">
                  <div class="cell-code"><strong><?= w_h_nb($r['warranty_no']) ?></strong><button type="button" class="copy-btn" data-copy="<?= w_h($r['warranty_no']) ?>">⧉</button></div>
                </td>
                <td class="mono nowrap"><?php if (!empty($r['repair_no'])): ?><div class="cell-code"><strong><?= w_h_nb($r['repair_no']) ?></strong><button type="button" class="copy-btn" data-copy="<?= w_h($r['repair_no']) ?>">⧉</button></div><?php else: ?>-<?php endif; ?></td>
                <td><?= w_h($r['customer_name']) ?></td>
                <td><?= w_h($r['device_model']) ?></td>
                <td class="mono"><strong><?= w_h($r['warranty_until']) ?></strong></td>
                <td class="center mono"><?= (int)$r['days_left'] ?></td>
              </tr>
            <?php endforeach;
          else: ?><tr>
              <td colspan="7" class="text-center">ไม่มีรายการ</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
  .data-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff
  }

  .data-table th,
  .data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #eee;
    vertical-align: middle
  }

  .data-table th.center,
  .data-table td.center {
    text-align: center
  }

  .data-table td.mono,
  .data-table th.mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace
  }

  .muted {
    color: #6b7280
  }

  .table-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    overflow: hidden
  }

  .empty {
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    padding: 28px;
    background: #fff;
    color: #6b7280;
    text-align: center
  }

  .nowrap {
    white-space: nowrap;
    word-break: keep-all;
    hyphens: none
  }

  .cell-code {
    display: inline-flex;
    align-items: center;
    gap: 6px
  }

  col.w-col-idx {
    width: 56px
  }

  col.w-col-code {
    width: 160px
  }

  col.w-col-repair {
    width: 140px
  }

  col.w-col-claim {
    width: 200px
  }

  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #374151;
    font-size: 12px
  }

  .badge-green {
    background: #e7f7ef;
    color: #0a7f42
  }

  .badge-amber {
    background: #fff6e6;
    color: #a05a00
  }

  .badge-red {
    background: #fde8e8;
    color: #a40000
  }

  .copy-btn {
    margin-left: 6px;
    border: 1px solid #e5e7eb;
    padding: 2px 6px;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 12px
  }

  .copy-btn.copied {
    border-color: #34d399;
    box-shadow: 0 0 0 2px rgba(52, 211, 153, .2) inset
  }

  /* Report Simple */
  .report-simple-toolbar {
    gap: 8px;
    align-items: center;
    flex-wrap: wrap
  }

  .report-simple-toolbar .quick-range {
    display: flex;
    gap: 6px
  }

  .report-kpi-grid.simple {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin: 10px 0 14px
  }

  .report-kpi-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 80px
  }

  .kpi-top {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 12px
  }

  .kpi-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px
  }

  .kpi-green {
    background: #10b981
  }

  .kpi-amber {
    background: #f59e0b
  }

  .kpi-gray {
    background: #9ca3af
  }

  .kpi-num {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-weight: 800;
    font-size: 24px
  }

  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px
  }

  .table-head-min {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    background: #fafafa
  }

  .table-title {
    margin: 0;
    font-size: 14px
  }

  .data-table th.right,
  .data-table td.right {
    text-align: right
  }

  .w-idx {
    width: 56px
  }

  @media (max-width:1024px) {
    .report-kpi-grid.simple {
      grid-template-columns: repeat(3, minmax(0, 1fr))
    }

    .grid-2 {
      grid-template-columns: 1fr
    }
  }

  @media (max-width:720px) {
    .report-kpi-grid.simple {
      grid-template-columns: repeat(2, minmax(0, 1fr))
    }
  }

  /* ===== Policy (refined) ===== */
  .policy-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px
  }

  .policy-toolbar--sticky {
    position: sticky;
    top: 56px;
    z-index: 8;
    background: #fff;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid #f1f5f9
  }

  .policy-toolbar__left {
    display: flex;
    gap: 8px;
    flex: 1;
    min-width: 280px
  }

  .policy-toolbar__right {
    display: flex;
    gap: 8px
  }

  .policy-search {
    flex: 1
  }

  .policy-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px
  }

  @media (max-width:1024px) {
    .policy-cards {
      grid-template-columns: 1fr
    }
  }

  .policy-card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
    overflow: hidden
  }

  .policy-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #fbfcfe
  }

  .policy-card__title {
    margin: 2px 0 0;
    font-size: 15px
  }

  .policy-card__meta {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px
  }

  .policy-card__body {
    padding: 14px;
    line-height: 1.65;
    white-space: pre-wrap
  }

  .policy-card__body.is-clamp {
    max-height: 240px;
    overflow: hidden;
    mask-image: linear-gradient(#000 calc(100% - 36px), transparent)
  }

  .policy-card__foot {
    padding: 10px 14px;
    border-top: 1px solid #f1f5f9;
    background: #fbfcfe;
    text-align: right
  }

  .chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    border: 1px solid #e5e7eb;
    color: #374151;
    background: #f8fafc
  }

  .chip--live {
    background: #e8f8ef;
    color: #0a7f42;
    border-color: #c8efd9
  }

  .chip--muted {
    background: #f3f4f6;
    color: #374151
  }

  .ver-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    font-weight: 600
  }

  .policy-table tr.row-hover:hover {
    background: #e1f0ffff
  }

  tr.is-now {
    background: #eef2ff
  }

  /* ปกติ 2 คอลัมน์ */
  .policy-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
  }

  /* ถ้ามีคลาส .one ให้ยืดเต็มความกว้าง */
  .policy-cards.one {
    grid-template-columns: 1fr;
  }

  /* จอแคบให้เป็นคอลัมน์เดียวเหมือนเดิม */
  @media (max-width:1024px) {
    .policy-cards {
      grid-template-columns: 1fr;
    }
  }
</style>

<script>
  function toggleMenu(id) {
    var m = document.getElementById(id);
    if (m) m.classList.toggle('show');
  }
  document.addEventListener('click', function(e) {
    var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
    document.querySelectorAll('.filter-menu.show').forEach(function(m) {
      if (!dd || !dd.contains(m)) m.classList.remove('show');
    });
  });

  function clearMenu(id) {
    var root = document.getElementById(id);
    if (!root) return;
    root.querySelectorAll('input[type="radio"],input[type="date"]').forEach(function(el) {
      if (el.type === 'radio') el.checked = false;
      if (el.type === 'date') el.value = '';
    });
  }
  (function() {
    const sel = document.getElementById('ppSelect');
    if (!sel) return;
    sel.addEventListener('change', function() {
      const u = new URL(location.href);
      u.searchParams.set('per', this.value);
      u.searchParams.set('page', '1');
      location = u.toString();
    });
  })();
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.copy-btn');
    if (!btn) return;
    const text = btn.getAttribute('data-copy') || '';
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      btn.classList.add('copied');
      setTimeout(() => btn.classList.remove('copied'), 800);
    });
  });
  document.addEventListener('click', function(e) {
    const row = e.target.closest('tr[data-goto]');
    if (!row) return;
    if (e.target.closest('a,button,input,select,textarea')) return;
    location = row.getAttribute('data-goto');
  });
  document.addEventListener('keydown', function(e) {
    if (e.altKey || e.metaKey || e.ctrlKey) return;
    if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
    if (e.key === 'ArrowLeft') document.querySelector('.page-btn[rel="prev"]')?.click();
  });

  // report quick ranges
  (function() {
    const form = document.querySelector('.report-simple-toolbar');
    if (!form) return;
    const from = form.querySelector('input[name="r_from"]');
    const to = form.querySelector('input[name="r_to"]');
    form.addEventListener('click', function(e) {
      const btn = e.target.closest('[data-range]');
      if (!btn) return;
      const r = btn.getAttribute('data-range');
      const d = new Date();
      const fmt = v => v.toISOString().slice(0, 10);
      if (r === 'today') {
        const t = fmt(d);
        from.value = t;
        to.value = t;
      }
      if (r === 'this_month') {
        const s = new Date(d.getFullYear(), d.getMonth(), 1);
        const eom = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        from.value = fmt(s);
        to.value = fmt(eom);
      }
      if (r === 'last_30') {
        const s = new Date(d.getTime() - 29 * 86400000);
        from.value = fmt(s);
        to.value = fmt(d);
      }
      if (r === 'clear') {
        from.value = '';
        to.value = '';
      }
    });
  })();
</script>

<script>
  // อ่านต่อ/ย่อ
  (function() {
    const body = document.getElementById('policyBody');
    const btn = document.getElementById('toggleClamp');
    if (body && btn) {
      btn.addEventListener('click', () => {
        body.classList.toggle('clamp');
        btn.textContent = body.classList.contains('clamp') ? 'อ่านต่อ' : 'ย่อ';
      });
    }
  })();

  // กดทั้งแถวเพื่อแก้ไข (ยกเว้นกดปุ่ม/ลิงก์อยู่แล้ว)
  document.addEventListener('click', function(e) {
    const row = e.target.closest('table.policy-list tr[data-goto]');
    if (!row) return;
    if (e.target.closest('a,button')) return;
    location = row.getAttribute('data-goto');
  });
</script>

<script>
  // อ่านต่อ/ย่อ ตัวเนื้อหาการ์ดปัจจุบัน
  (function() {
    var body = document.getElementById('policyBody');
    var btn = document.getElementById('toggleClamp');
    if (!body || !btn) return;
    btn.addEventListener('click', function() {
      body.classList.toggle('is-clamp');
      btn.textContent = body.classList.contains('is-clamp') ? 'อ่านต่อ' : 'ย่อ';
    });
  })();

  // กดทั้งแถวเพื่อแก้ไข (กันซ้อนกับปุ่ม/ลิงก์)
  document.addEventListener('click', function(e) {
    var row = e.target.closest('table.policy-table tr[data-goto]');
    if (!row) return;
    if (e.target.closest('a,button')) return;
    location = row.getAttribute('data-goto');
  });
</script>