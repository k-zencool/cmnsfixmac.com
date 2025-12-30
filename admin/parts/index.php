<?php
// 1. เปิดแสดง Error (กันหน้าขาว)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/********************************************************************
 * admin/parts/index.php
 *
 * Update:
 * - Used Tab: ปรับสไตล์ SKU ให้เหมือน Asset Tag (หน้าเครื่อง) เป๊ะๆ
 ********************************************************************/

// =========================[ 0) SETUP & GUARD ]========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "จัดการอะไหล่";

// =========================[ 1) CONSTANTS / MAPS ]=====================
$DEVICE_LABELS = ['macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac'];
$KIND_LABELS   = [
  'screen'=>'จอ/Screen','battery'=>'แบต/Battery','keyboard'=>'คีย์บอร์ด','trackpad'=>'Trackpad',
  'speaker'=>'ลำโพง','camera'=>'กล้อง','board'=>'บอร์ด/Logic','cable'=>'สาย/Flex',
  'fan'=>'พัดลม','hinge'=>'บานพับ','case'=>'ฝา/เคส'
];
$KIND_KEYWORDS = [
  'screen'=>['จอ','screen','display','lcd'],
  'battery'=>['แบต','battery'],
  'keyboard'=>['คีย์บอร์ด','keyboard','kb'],
  'trackpad'=>['trackpad','ทัชแพด'],
  'speaker'=>['ลำโพง','speaker'],
  'camera'=>['กล้อง','camera'],
  'board'=>['board','logic','mainboard','เมนบอร์ด','บอร์ด'],
  'cable'=>['สาย','cable','flex'],
  'fan'=>['พัดลม','fan'],
  'hinge'=>['บานพับ','hinge'],
  'case'=>['ฝาหลัง','ฝา','case','top case','bottom']
];
$DONOR_STATUS = [
  'in_stock' => 'พร้อมแยก',
  'reserved' => 'จอง',
  'for_sale' => 'กำลังขาย',
  'stripped' => 'แยกแล้ว',
  'sold'     => 'ขายแล้ว'
];

// =========================[ 2) HELPERS ]==============================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function getvArray($key, array $allow): array{
  $v = isset($_GET[$key]) ? (array)$_GET[$key] : [];
  return array_values(array_intersect($v, array_keys($allow)));
}
function img_src($v){
  $v = trim((string)$v);
  if ($v === '') return '';
  if (preg_match('~^https?://~i',$v)) return $v;
  if ($v[0] === '/') return $v;
  return '/uploads/parts/'.$v;
}
function doc_label($t){
  return $t==='IN'?'รับเข้า':($t==='CONSUME'?'เบิก':($t==='MOVE'?'ย้าย':($t==='ADJUST'?'ปรับยอด':($t==='DONOR'?'เครื่อง':$t))));
}
function qty_fmt($t,$q){
  if ($q===null) return '';
  $q=(int)$q;
  if ($t==='IN') return '+'.$q;
  if ($t==='CONSUME') return '-'.$q;
  if ($t==='USED') return '+'.$q;
  if ($t==='DONOR') return ($q>0?'+':'').$q;
  return (string)$q;
}
function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string{
  if ($q==='') return null;
  $ors=[]; $i=0;
  foreach ($cols as $c){ $ph=":{$pfx}{$i}"; $ors[]="$c LIKE $ph"; $params[$ph]="%{$q}%"; $i++; }
  return '('.implode(' OR ',$ors).')';
}
function whereDevices(array $devices, array $cols, array &$params, string $pfx): ?string{
  if (!$devices) return null;
  $map=['macbook'=>'MacBook','iphone'=>'iPhone','ipad'=>'iPad','imac'=>'iMac'];
  $ors=[]; $i=0;
  foreach ($devices as $d){
    $kw=$map[$d]??$d; $ph=":{$pfx}{$i}"; $params[$ph]="%{$kw}%";
    $inner=[]; foreach($cols as $c) $inner[]="$c LIKE $ph";
    $ors[]='('.implode(' OR ',$inner).')'; $i++;
  }
  return '('.implode(' OR ',$ors).')';
}
function whereKinds(array $kinds, array $kwMap, array &$params, string $pfx): ?string{
  if (!$kinds) return null;
  $ors=[]; $i=0;
  foreach ($kinds as $k){
    if (!isset($kwMap[$k])) continue;
    $likes=[];
    foreach ($kwMap[$k] as $w){
      $ph=":{$pfx}{$i}"; $likes[]="part_name LIKE $ph"; $params[$ph]="%{$w}%"; $i++;
    }
    if ($likes) $ors[]='('.implode(' OR ',$likes).')';
  }
  return $ors?'('.implode(' OR ',$ors).')':null;
}
function get_pager(): array{
  $per=max(5,min(200,(int)getv('per',20)));
  $page=max(1,(int)getv('page',1));
  $off=($page-1)*$per;
  return [$per,$page,$off];
}
function page_url($i){
  $q=$_GET; $q['page']=max(1,(int)$i);
  return '?'.http_build_query($q);
}

// =========================[ 3) STATE ]================================
$tab = getv('tab','new');
$q   = getv('q','');
$msg = getv('msg','');
$err = getv('err','');

$devices = getvArray('device',$DEVICE_LABELS);
$kinds   = getvArray('kind',$KIND_LABELS);
$donorStatuses = getvArray('status', $DONOR_STATUS);

[$per,$page,$offset] = get_pager();

// =========================[ 4) LOAD DATA ]============================
$parts=$usedItems=$historyRows=$donors=[];
$total=0; $pages=1;

try {
    // ---------- 4.1 NEW ----------
    if ($tab==='new'){
      require_perms(['parts.new.view']);
      $params=[]; $where=[];
      if ($w=whereSearch($q,['part_name','part_number','device_models','category','location'],$params,'qn')) $where[]=$w;
      if ($w=whereDevices($devices,['part_name','device_models','category'],$params,'dn')) $where[]=$w;
      if ($w=whereKinds($kinds,$KIND_KEYWORDS,$params,'kn')) $where[]=$w;
      $where_sql=$where?("WHERE ".implode(' AND ',$where)):"";

      $stc=$pdo->prepare("SELECT COUNT(DISTINCT part_code) FROM parts_new {$where_sql}");
      $stc->execute($params);
      $total=(int)($stc->fetchColumn()?:0);
      $pages=max(1,(int)ceil($total/$per));
      if ($page>$pages){ $page=$pages; $offset=($page-1)*$per; }

      $sql="
        SELECT part_code,
               MAX(part_name)     AS part_name,
               MAX(part_number)   AS part_number,
               MAX(device_models) AS device_models,
               MAX(category)      AS category,
               MAX(image_url)     AS image_url,
               MAX(min_stock)     AS min_stock,
               SUM(quantity)      AS qty,
               GROUP_CONCAT(DISTINCT location ORDER BY location SEPARATOR ', ') AS locations
        FROM parts_new
        {$where_sql}
        GROUP BY part_code
        ORDER BY part_code DESC
        LIMIT :limit OFFSET :off";
      $st=$pdo->prepare($sql);
      foreach($params as $k=>$v) $st->bindValue($k,$v);
      $st->bindValue(':limit',$per,PDO::PARAM_INT);
      $st->bindValue(':off',$offset,PDO::PARAM_INT);
      $st->execute();
      $parts=$st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- 4.2 USED ----------
    if ($tab==='used'){
      require_perms(['parts.used.view']);
      $params=[]; $where=[];
      
      // เพิ่ม used_sku ในการค้นหา
      if ($w=whereSearch($q,['part_code','part_name','part_number','device_models','category','location','remarks','used_sku'],$params,'qu')) $where[]=$w;
      
      if ($w=whereDevices($devices,['part_name','device_models','category'],$params,'du')) $where[]=$w;
      if ($w=whereKinds($kinds,$KIND_KEYWORDS,$params,'ku')) $where[]=$w;
      $where_sql=$where?("WHERE ".implode(' AND ',$where)):"";

      $stc=$pdo->prepare("SELECT COUNT(*) FROM parts_used {$where_sql}");
      $stc->execute($params);
      $total=(int)($stc->fetchColumn()?:0);
      $pages=max(1,(int)ceil($total/$per));
      if ($page>$pages){ $page=$pages; $offset=($page-1)*$per; }

      // เพิ่ม used_sku ใน SELECT
      $sql="
        SELECT id, used_sku, part_code, part_name, part_number, device_models, category,
               image_url, location, remarks, created_at, updated_at
        FROM parts_used
        {$where_sql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :off";
      $st=$pdo->prepare($sql);
      foreach($params as $k=>$v) $st->bindValue($k,$v);
      $st->bindValue(':limit',$per,PDO::PARAM_INT);
      $st->bindValue(':off',$offset,PDO::PARAM_INT);
      $st->execute();
      $usedItems=$st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- 4.3 DONOR ----------
    if ($tab==='donor'){
      require_perms(['parts.donor.view']);
      $params=[]; $where=[];
      
      if ($w=whereSearch($q,['internal_id','device_type','device_series','model_code','serial_no','remarks','location_index'],$params,'qd')) $where[]=$w;
      if ($w=whereDevices($devices,['device_type','device_series'],$params,'dd')) $where[]=$w;

      if (!empty($donorStatuses)){
        $in=[]; foreach($donorStatuses as $i=>$s){ $ph=":ds{$i}"; $in[]=$ph; $params[$ph]=$s; }
        $where[] = 'status IN ('.implode(',',$in).')';
      } else {
        $dism=getv('dism','');
        if ($dism==='0')      $where[]="status <> 'stripped'";
        else if ($dism==='1') $where[]="status = 'stripped'";
      }

      $where_sql=$where?("WHERE ".implode(' AND ',$where)):"";

      $stc=$pdo->prepare("SELECT COUNT(*) FROM parts_donors {$where_sql}");
      $stc->execute($params);
      $total=(int)($stc->fetchColumn()?:0);

      $pages=max(1,(int)ceil($total/$per));
      if ($page>$pages){ $page=$pages; $offset=($page-1)*$per; }

      $sql="
        SELECT
          id, internal_id, 
          device_type, device_series, model_code,
          serial_no, status,
          image_url, remarks,
          location_index,
          created_at, updated_at,
          CASE WHEN status='stripped' THEN 1 ELSE 0 END AS is_dismantled
        FROM parts_donors
        {$where_sql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :off";
      $st=$pdo->prepare($sql);
      foreach($params as $k=>$v) $st->bindValue($k,$v);
      $st->bindValue(':limit',$per,PDO::PARAM_INT);
      $st->bindValue(':off',$offset,PDO::PARAM_INT);
      $st->execute();
      $donors=$st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- 4.4 HISTORY ----------
    if ($tab==='history'){
      require_perms(['parts.history.view']);

      $params=[]; $where=[];
      if ($w=whereSearch($q, ['l.part_code','pn.part_name','pu.part_name','pul.part_name','d.ref_no','d.remarks','au.username', 'pd.internal_id'],$params,'qh')) $where[]=$w;

      $doc_type=getv('doc_type','');
      if ($doc_type!==''){ $where[]="d.doc_type=:dt"; $params[':dt']=$doc_type; }
      $df=getv('date_from',''); if ($df!==''){ $where[]="DATE(d.created_at)>=:df"; $params[':df']=$df; }
      $dt=getv('date_to','');   if ($dt!==''){ $where[]="DATE(d.created_at)<=:dt2"; $params[':dt2']=$dt; }

      // 1. นับจำนวนรวมก่อน (เพื่อ Pagination)
      $sqlCount = "SELECT COUNT(*)
        FROM parts_docs d
        LEFT JOIN parts_doc_lines l ON l.doc_id=d.doc_id
        LEFT JOIN admin_users au ON au.id=d.user_id
        LEFT JOIN parts_new pn ON pn.part_code = l.part_code
        LEFT JOIN parts_used pu ON pu.part_code = l.part_code
        LEFT JOIN parts_used_log pul ON pul.part_code = l.part_code
        LEFT JOIN parts_donors pd ON (d.doc_type='DONOR' AND d.ref_no = CONCAT('DONOR:', pd.id))
        ".($where?("WHERE ".implode(' AND ',$where)):"");
      $stc=$pdo->prepare($sqlCount);
      $stc->execute($params);
      $total=(int)($stc->fetchColumn()?:0);

      $pages=max(1,(int)ceil($total/$per));
      if ($page>$pages){ $page=$pages; $offset=($page-1)*$per; }

      // 2. ดึงข้อมูลจริง (Join ครบทุกตาราง)
      $sql="
        SELECT
          d.created_at, d.doc_type, d.ref_no, d.remarks,
          l.part_code,
          COALESCE(pn.part_name, pu.part_name, pul.part_name) AS part_name,
          CASE
            WHEN d.doc_type='DONOR' AND (d.remarks LIKE 'เพิ่มเครื่อง:%' OR d.remarks LIKE 'CREATE%') THEN 1
            WHEN d.doc_type='DONOR' AND (d.remarks LIKE 'ลบเครื่อง%'   OR d.remarks LIKE 'DELETE%') THEN -1
            WHEN d.doc_type='DONOR' THEN NULL
            ELSE l.qty
          END AS qty,
          l.location_from, l.location_to, l.unit_cost,
          au.username AS admin_name,
          pd.internal_id AS donor_tag
        FROM parts_docs d
        LEFT JOIN parts_doc_lines l ON l.doc_id=d.doc_id
        LEFT JOIN admin_users au    ON au.id=d.user_id
        
        LEFT JOIN (SELECT part_code, MAX(part_name) AS part_name FROM parts_new GROUP BY part_code) pn ON pn.part_code = l.part_code
        LEFT JOIN (SELECT part_code, MAX(part_name) AS part_name FROM parts_used GROUP BY part_code) pu ON pu.part_code = l.part_code
        LEFT JOIN (SELECT part_code, MAX(part_name) AS part_name FROM parts_used_log GROUP BY part_code) pul ON pul.part_code = l.part_code
        
        LEFT JOIN parts_donors pd ON (d.doc_type='DONOR' AND d.ref_no = CONCAT('DONOR:', pd.id))
        
        ".($where?("WHERE ".implode(' AND ',$where)):"")."
        ORDER BY d.doc_id DESC, COALESCE(l.line_id,0) DESC
        LIMIT :limit OFFSET :off";
        
      $st=$pdo->prepare($sql);
      foreach($params as $k=>$v) $st->bindValue($k,$v);
      $st->bindValue(':limit',$per,PDO::PARAM_INT);
      $st->bindValue(':off',$offset,PDO::PARAM_INT);
      $st->execute();
      $historyRows=$st->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    die("<div style='color:red; padding:20px; text-align:center;'>
            <h1>⚠️ เกิดข้อผิดพลาดในระบบ</h1>
            <p>Error: " . h($e->getMessage()) . "</p>
            <p>กรุณาแคปหน้าจอนี้แจ้งผู้ดูแลระบบ</p>
         </div>");
}

// =========================[ 5) TEMPLATE ]=============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
/* CSS LOADING OVERLAY */
#global-loader {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(255, 255, 255, 0.9);
  z-index: 99999;
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  backdrop-filter: blur(2px);
}
.loader-spinner {
  width: 50px; height: 50px;
  border: 5px solid #e5e7eb;
  border-top: 5px solid #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 10px;
}
.loader-text {
  font-family: 'Sarabun', sans-serif;
  color: #374151;
  font-weight: 600;
  animation: pulse 1.5s infinite;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }

/* Print Friendly Styles */
@media print {
  .topbar, .view-switcher, .search-and-filter-group, .pager-bar, .btn-primary, .btn-secondary, .btn-search, .thumb-btn, .btn-edit, .btn-checkout, .btn-success, .btn-danger, #sidebar, footer, .page-size {
    display: none !important;
  }
  .main { margin-left: 0 !important; padding: 0 !important; }
  .table-container { box-shadow: none !important; overflow: visible !important; border: 1px solid #ddd; }
  .data-table { width: 100% !important; border-collapse: collapse; font-size: 10pt; }
  .data-table th, .data-table td { padding: 5px; border: 1px solid #ddd; text-align: left; }
  .section-header { margin-bottom: 10px; }
  h2 { font-size: 16pt; margin: 0; color: #000; }
  body { background: white; -webkit-print-color-adjust: exact; }
  
  .hist-badge { border: 1px solid #ccc; background: none !important; color: #000 !important; padding: 1px 3px; }
  .hist-qty-pos, .hist-qty-neg { color: #000 !important; }
}

/* History Table Styles */
.hist-time { font-family: monospace; font-size: 0.9em; color: #666; }
.hist-ref { font-family: monospace; font-weight: bold; color: #374151; }
.hist-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 600; display: inline-block; min-width: 60px; text-align: center; }
.hist-badge.IN { background: #dcfce7; color: #166534; }
.hist-badge.CONSUME { background: #fee2e2; color: #991b1b; }
.hist-badge.MOVE { background: #e0f2fe; color: #075985; }
.hist-badge.ADJUST { background: #f3f4f6; color: #374151; }
.hist-badge.DONOR { background: #fef3c7; color: #92400e; }
.hist-badge.USED { background: #eab308; color: #fff; }

.hist-qty-pos { color: #16a34a; font-weight: bold; }
.hist-qty-neg { color: #dc2626; font-weight: bold; }

/* Asset Tag UI */
.asset-group { display: inline-flex; align-items: center; gap: 8px; background: transparent; }
.asset-text { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-weight: 700; font-size: 1rem; color: #1f2937; letter-spacing: 0.5px; }
.btn-icon-copy { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; color: #6b7280; transition: all 0.2s; padding: 0; }
.btn-icon-copy:hover { background-color: #f9fafb; border-color: #d1d5db; color: #374151; }
.btn-icon-copy:active { transform: translateY(1px); }
.icon-svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* --- [ADDED] Custom Style for Filter Dropdown --- */
#filterMenuHist select.input {
    width: 100%;
    box-sizing: border-box;
    padding: 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background-color: #f9fafb;
    color: #1f2937;
    font-size: 0.95rem;
    appearance: none; /* ลบลูกศร default */
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    cursor: pointer;
}
#filterMenuHist select.input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
/* Style for options (some browsers) */
#filterMenuHist option {
    padding: 8px;
}
</style>

<div id="global-loader">
  <div class="loader-spinner"></div>
  <div class="loader-text">กำลังค้นหาข้อมูล...</div>
</div>

<main class="main" id="main-content">
  <div class="topbar"><span><?= h($pageTitle) ?></span><a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a></div>

  <div class="view-switcher">
    <?php if (can('parts.new.view')): ?><a class="switcher-item <?= $tab==='new'?'active':'' ?>" href="index.php?tab=new">อะไหล่ใหม่</a><?php endif; ?>
    <?php if (can('parts.used.view')): ?><a class="switcher-item <?= $tab==='used'?'active':'' ?>" href="index.php?tab=used">อะไหล่มือสอง</a><?php endif; ?>
    <?php if (can('parts.donor.view')): ?><a class="switcher-item <?= $tab==='donor'?'active':'' ?>" href="index.php?tab=donor">เครื่อง</a><?php endif; ?>
    <?php if (can('parts.history.view')): ?><a class="switcher-item <?= $tab==='history'?'active':'' ?>" href="index.php?tab=history">ประวัติ</a><?php endif; ?>
  </div>

  <div class="section-header">
    <h2><?php if ($tab==='new'): ?>รายการอะไหล่ใหม่ (New Parts)<?php elseif ($tab==='used'): ?>รายการอะไหล่มือสอง (Used Parts)<?php elseif ($tab==='donor'): ?>รายการเครื่อง (Donor Units)<?php else: ?>ประวัติการเคลื่อนไหว (History Log)<?php endif; ?></h2>
    <div>
      <?php if ($tab==='new'  && can('parts.new.create')): ?><a href="form.php" class="btn-primary">+ เพิ่มชนิดอะไหล่ใหม่</a><?php endif; ?>
      <?php if ($tab==='used' && can('parts.used.create')): ?><a href="form_used.php" class="btn-primary">+ เพิ่มชิ้นมือสอง</a><?php endif; ?>
      <?php if ($tab==='donor' && can('parts.donor.create')): ?><a href="donor_form.php" class="btn-primary">+ เพิ่มเครื่อง</a><?php endif; ?>
      <?php if ($tab==='history'): ?><button onclick="window.print()" class="btn-secondary" style="margin-left:5px;">พิมพ์รายงาน</button><?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <?php if ($tab==='new'): ?>
    <form action="index.php" method="GET" class="search-form-bind">
      <input type="hidden" name="tab" value="new">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/เบอร์/รุ่น/หมวด...">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuNew')">ตัวกรอง</button>
          <div id="filterMenuNew" class="filter-menu">
             <div class="filter-section"><div class="filter-title">ชนิดอะไหล่</div><?php foreach ($KIND_LABELS as $val=>$label): $checked=in_array($val,$kinds,true)?'checked':''; ?><label class="checkline"><input type="checkbox" name="kind[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label><?php endforeach; ?><div class="filter-actions"><button type="button" class="btn-secondary" onclick="clearMenu('filterMenuNew')">ล้าง</button><button type="submit" class="btn-primary">ใช้ตัวกรอง</button></div></div>
          </div>
        </div>
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>
    <div class="table-container">
      <table class="data-table">
        <thead><tr><th>#</th><th>รูป</th><th>ชื่ออะไหล่</th><th>เลขอะไหล่</th><th>รุ่น</th><th>หมวด</th><th>ที่เก็บ</th><th>ขั้นต่ำ</th><th>คงเหลือ</th><th>จัดการ</th></tr></thead>
        <tbody>
          <?php if ($parts): foreach ($parts as $i=>$p): $img=img_src($p['image_url']??''); $qty=(int)$p['qty']; $min=(int)$p['min_stock']; $low=$min>0 && $qty<$min; $locs=array_filter(array_map('trim',explode(',',(string)($p['locations']??'')))); ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button type="button" class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb" alt=""></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              <td><strong><?= h($p['part_name'] ?: $p['part_code']) ?></strong></td>
              <td class="muted"><?= h($p['part_number']) ?></td>
              <td><?= h($p['device_models']) ?></td>
              <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>
              <td><?php if ($locs): ?><div class="chips"><?php foreach($locs as $L): ?><span class="badge"><?= h($L) ?></span><?php endforeach; ?></div><?php else: ?><span class="muted">-</span><?php endif; ?></td>
              <td><?= $min>0 ? h($min) : '<span class="muted">-</span>' ?></td>
              <td><?= $low ? '<span class="badge" title="ต่ำกว่าขั้นต่ำ" style="background:#fee2e2; color:#b91c1c;">'.h($qty).'</span>' : h($qty) ?></td>
              <td class="no-wrap">
                <?php if (can('parts.new.restock')): ?><a href="restock.php?part_code=<?= h($p['part_code']) ?>" class="btn-success">เติมสต็อก</a><?php endif; ?>
                <?php if (can('parts.new.consume')): ?><a href="consume.php?type=new&part_code=<?= h($p['part_code']) ?>" class="btn-checkout">เบิก</a><?php endif; ?>
                <?php if (can('parts.new.update')): ?><a href="form.php?part_code=<?= h($p['part_code']) ?>" class="btn-edit">แก้ไข</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?><tr><td colspan="10" class="text-center">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='used'): ?>
    <form action="index.php" method="GET" class="search-form-bind">
      <input type="hidden" name="tab" value="used">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา SKU / ชื่อ / รุ่น / หมายเหตุ...">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleFilterMenuUsed()">ตัวกรอง</button>
          <div id="filterMenuUsed" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">อุปกรณ์</div>
              <?php foreach ($DEVICE_LABELS as $val=>$label): $checked=in_array($val,$devices,true)?'checked':''; ?>
                <label class="checkline"><input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">ชนิดอะไหล่</div>
              <?php foreach ($KIND_LABELS as $val=>$label): $checked=in_array($val,$kinds,true)?'checked':''; ?>
                <label class="checkline"><input type="checkbox" name="kind[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearFilterChecksUsed()">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
              </div>
            </div>
          </div>
        </div>
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>รูป</th>
            <th>SKU / รหัสทรัพย์สิน</th> 
            <th>ชื่ออะไหล่</th>
            <th>รุ่น / Part No.</th>
            <th>หมวด</th>
            <th>ที่เก็บ</th>
            <th>หมายเหตุ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($usedItems): foreach ($usedItems as $i=>$u): 
              $img=img_src($u['image_url']??''); 
              $remark=trim((string)$u['remarks']); 
              $sku = !empty($u['used_sku']) ? $u['used_sku'] : '-';
          ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button type="button" class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb"></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              
              <td>
                <?php if($sku !== '-'): ?>
                    <div class="asset-group">
                        <span class="asset-text"><?= h($sku) ?></span>
                        <button type="button" class="btn-icon-copy" title="Copy" onclick="copyTag('<?= h($sku) ?>', this)">
                            <svg class="icon-svg" width="14" height="14" viewBox="0 0 24 24"><path d="M8 4v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7.242a2 2 0 0 0-.602-1.43L16.083 2.57A2 2 0 0 0 14.685 2H10a2 2 0 0 0-2 2z"/><path d="M16 18v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h2"/></svg>
                        </button>
                    </div>
                <?php else: ?>
                    <span class="muted" style="font-size:0.85em;">(No SKU)</span>
                <?php endif; ?>
              </td>

              <td><strong><?= h($u['part_name'] ?: $u['part_code']) ?></strong></td>
              
              <td>
                <div class="muted"><?= h($u['part_number']) ?></div>
                <div style="font-size:0.85em; color:#4b5563;"><?= h($u['device_models']) ?></div>
              </td>
              
              <td><span class="badge"><?= h($u['category'] ?: 'Other') ?></span></td>
              <td><?= h($u['location'] ?: '-') ?></td>
              <td class="remark-cell"><?= $remark!=='' ? '<span class="remark-text" data-remark="'.h($remark).'" title="'.h($remark).'">'.h($remark).'</span>' : '<span class="muted">-</span>' ?></td>
              <td class="no-wrap">
                <?php if (can('parts.used.consume')): ?><a class="btn-checkout" href="consume.php?type=used&used_id=<?= (int)$u['id'] ?>">เบิก</a><?php endif; ?>
                <?php if (can('parts.used.update')): ?><a class="btn-edit" href="form_used.php?id=<?= (int)$u['id'] ?>">แก้ไข</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?><tr><td colspan="9" class="text-center">ยังไม่มีชิ้นมือ 2</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='donor'): ?>
    <form action="index.php" method="GET" class="search-form-bind">
      <input type="hidden" name="tab" value="donor">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา Asset Tag / รุ่น / ซีเรียล...">
        <div class="filter-dropdown">
           <button type="button" class="btn-secondary" onclick="toggleFilterMenuDonor()">ตัวกรอง</button>
           <div id="filterMenuDonor" class="filter-menu">
             <div class="filter-section"><div class="filter-title">อุปกรณ์</div><?php foreach ($DEVICE_LABELS as $val=>$label): $checked=in_array($val,$devices,true)?'checked':''; ?><label class="checkline"><input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label><?php endforeach; ?></div>
             <div class="filter-section"><div class="filter-title">สถานะ</div><?php foreach ($DONOR_STATUS as $val=>$label): $checked=in_array($val,$donorStatuses,true)?'checked':''; ?><label class="checkline"><input type="checkbox" name="status[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label><?php endforeach; ?><div class="filter-actions"><button type="button" class="btn-secondary" onclick="clearFilterChecksDonor()">ล้าง</button><button type="submit" class="btn-primary">ใช้ตัวกรอง</button></div></div>
           </div>
        </div>
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>
    <div class="table-container">
      <table class="data-table">
        <thead><tr><th>#</th><th>รูป</th><th>Asset Tag</th><th>รุ่น / เครื่อง</th><th>ซีเรียล</th><th>ที่เก็บ</th><th>สถานะ</th><th>หมายเหตุ</th><th>จัดการ</th></tr></thead>
        <tbody>
          <?php if ($donors): foreach ($donors as $i=>$d): 
            $img=img_src($d['image_url']??''); 
            $remark=trim((string)$d['remarks']);
            $status=(string)($d['status']??'');
            $badgeColor='badge-gray'; if($status==='in_stock') $badgeColor='badge-green'; elseif($status==='reserved') $badgeColor='badge-amber'; elseif($status==='for_sale') $badgeColor='badge-purple'; elseif($status==='stripped') $badgeColor='badge-blue'; elseif($status==='sold') $badgeColor='badge-dark';
            $statusLabel = $DONOR_STATUS[$status] ?? $status;
          ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb"></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              <td>
                <?php if(!empty($d['internal_id'])): ?>
                    <div class="asset-group">
                        <span class="asset-text"><?= h($d['internal_id']) ?></span>
                        <button type="button" class="btn-icon-copy" title="Copy" onclick="copyTag('<?= h($d['internal_id']) ?>', this)">
                            <svg class="icon-svg" width="14" height="14" viewBox="0 0 24 24"><path d="M8 4v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7.242a2 2 0 0 0-.602-1.43L16.083 2.57A2 2 0 0 0 14.685 2H10a2 2 0 0 0-2 2z"/><path d="M16 18v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h2"/></svg>
                        </button>
                    </div>
                <?php else: ?><span class="muted">-</span><?php endif; ?>
              </td>
              <td>
                <div style="font-weight:bold;"><?= h($d['device_type'].' '.$d['device_series']) ?></div>
                <?php if(!empty($d['model_code'])): ?><span style="font-size:0.8em; background:#f3f4f6; padding:1px 4px; border-radius:3px;"><?= h($d['model_code']) ?></span><?php endif; ?>
              </td>
              <td class="muted" style="font-family:monospace;"><?= h($d['serial_no']) ?></td>
              <td><span style="color:#dc2626; font-weight:600;"><?= h($d['location_index']) ?></span></td>
              <td><span class="badge <?= $badgeColor ?>"><?= h($statusLabel) ?></span></td>
              <td class="remark-cell"><?= $remark!=='' ? '<span class="remark-text" data-remark="'.h($remark).'" title="'.h($remark).'">'.h($remark).'</span>' : '<span class="muted">-</span>' ?></td>
              <td class="no-wrap">
                <?php if ((int)$d['is_dismantled']===0): ?>
                  <?php if (can('parts.donor.split')): ?><a class="btn-checkout" href="donor_split.php?id=<?= (int)$d['id'] ?>">แยกอะไหล่</a><?php endif; ?>
                <?php else: ?>
                  <a class="btn-danger" href="donor_split.php?id=<?= (int)$d['id'] ?>">รายการแยก</a>
                <?php endif; ?>
                <?php if (can('parts.donor.update')): ?><a class="btn-edit" href="donor_form.php?id=<?= (int)$d['id'] ?>">แก้ไข</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?><tr><td colspan="9" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='history'): ?>
    <form action="index.php" method="get" class="search-and-filter-group search-form-bind">
      <input type="hidden" name="tab" value="history">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา Part Code / อ้างอิง / หมายเหตุ...">
        
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuHist')">ตัวกรอง</button>
          <div id="filterMenuHist" class="filter-menu" style="min-width:280px;">
            <div class="filter-section">
                <div class="filter-title">ประเภทรายการ</div>
                <select name="doc_type" class="input" style="width:100%; box-sizing:border-box;">
                    <option value="">-- ทุกประเภท --</option>
                    <option value="IN" <?= getv('doc_type')==='IN'?'selected':'' ?>>รับเข้า (IN)</option>
                    <option value="CONSUME" <?= getv('doc_type')==='CONSUME'?'selected':'' ?>>เบิกใช้ (OUT)</option>
                    <option value="MOVE" <?= getv('doc_type')==='MOVE'?'selected':'' ?>>ย้ายที่ (MOVE)</option>
                    <option value="ADJUST" <?= getv('doc_type')==='ADJUST'?'selected':'' ?>>ปรับยอด (ADJ)</option>
                    <option value="USED" <?= getv('doc_type')==='USED'?'selected':'' ?>>มือ 2</option>
                    <option value="DONOR" <?= getv('doc_type')==='DONOR'?'selected':'' ?>>จัดการเครื่อง</option>
                </select>
            </div>
            <div class="filter-section">
                <div class="filter-title">ช่วงเวลา</div>
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:0.85em; color:#666;">ตั้งแต่วันที่</label>
                    <input type="date" name="date_from" value="<?= h(getv('date_from')) ?>" class="input" style="width:100%; box-sizing:border-box;">
                    <label style="font-size:0.85em; color:#666;">ถึงวันที่</label>
                    <input type="date" name="date_to" value="<?= h(getv('date_to')) ?>" class="input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuHist')">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
            </div>
          </div>
        </div>

        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr><th width="130">เวลา</th><th width="80">ประเภท</th><th>รายการ / รายละเอียด</th><th width="80" class="text-right">จำนวน</th><th width="120">ตำแหน่ง</th><th>อ้างอิง / ผู้ทำ</th></tr>
        </thead>
        <tbody>
          <?php if ($historyRows): foreach ($historyRows as $r): 
            $dt = new DateTime($r['created_at']);
            $dateStr = $dt->format('d/m/Y H:i');
            $typeClass = $r['doc_type']; 
            
            $showName = $r['part_name'];
            if(empty($showName)) {
                if($r['doc_type'] === 'DONOR') {
                    if (preg_match('/เพิ่มเครื่อง:\s*(.*?)(\[|$)/', $r['remarks'], $m)) $showName = $m[1];
                    else $showName = "เครื่อง (Machine)";
                } else $showName = $r['part_code'] ?: '(ไม่ระบุชื่อ)';
            }
            $showRef = $r['ref_no'];
            if(!empty($r['donor_tag'])) $showRef = $r['donor_tag'];

            $qVal = (int)$r['qty'];
            $qStr = '';
            if($r['doc_type'] === 'IN') { $qStr = "+".$qVal; $qClass="hist-qty-pos"; }
            elseif($r['doc_type'] === 'CONSUME') { $qStr = "-".$qVal; $qClass="hist-qty-neg"; }
            elseif($r['doc_type'] === 'DONOR') { 
                $qStr = ($qVal > 0 ? "+" : "") . $qVal; 
                $qClass = ($qVal > 0 ? "hist-qty-pos" : "hist-qty-neg");
                if($qVal === 0) $qStr = "-"; 
            } else { $qStr = (string)$qVal; $qClass=""; }
            
            $locStr = h($r['location_from']);
            if($r['location_to']) $locStr .= " ➝ " . h($r['location_to']);
          ?>
            <tr>
              <td class="hist-time"><?= h($dateStr) ?></td>
              <td><span class="hist-badge <?= h($typeClass) ?>"><?= h(doc_label($r['doc_type'])) ?></span></td>
              <td>
                <div style="font-weight:600; color:#1f2937;"><?= h($showName) ?></div>
                <div style="font-size:0.85em; color:#6b7280; margin-top:2px;">
                    <?php if($r['part_code'] && $r['doc_type']!=='DONOR'): ?><span style="font-family:monospace; background:#f3f4f6; padding:1px 4px; border-radius:3px;"><?= h($r['part_code']) ?></span><?php endif; ?>
                    <?= h($r['remarks']) ?>
                </div>
              </td>
              <td class="text-right <?= $qClass ?>" style="font-family:monospace; font-size:1.1em;"><?= h($qStr) ?></td>
              <td style="font-size:0.9em;"><?= $locStr ?: '<span class="muted">-</span>' ?></td>
              <td>
                <div class="hist-ref" style="color:#2563eb;"><?= h($showRef) ?></div>
                <div style="font-size:0.8em; color:#9ca3af;">โดย: <?= h($r['admin_name']) ?></div>
              </td>
            </tr>
          <?php endforeach; else: ?><tr><td colspan="6" class="text-center" style="padding:30px; color:#999;">-- ไม่พบประวัติการทำรายการ --</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true"><div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป"><button type="button" class="imgpv-close" aria-label="ปิด">✕</button><img id="imgPreview" src="" alt="" class="imgpv-img"></div></div>
  <div id="remarkModal" class="remark-modal" aria-hidden="true"><div class="modal-content" role="dialog" aria-modal="true" aria-label="หมายเหตุ"><button type="button" class="close-btn" aria-label="ปิด">✕</button><div id="remarkFullText"></div></div></div>

  <?php if(in_array($tab, ['new','used','donor','history'])): ?>
  <div class="pager-bar">
    <div class="pager-left"><span class="pager-total">พบ <?= (int)$total ?> รายการ</span><span class="divider">•</span><span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span></div>
    <nav class="pager-nav">
        <a class="page-btn <?= $page<=1?'is-disabled':'' ?>" href="<?= $page>1?page_url($page-1):'#' ?>">‹</a>
        <a class="page-btn <?= $page>=$pages?'is-disabled':'' ?>" href="<?= $page<$pages?page_url($page+1):'#' ?>">›</a>
        <div class="page-size"><select id="ppSelect" class="pager-select"><?php foreach([20,50,100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?></select></div>
    </nav>
  </div>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  function showGlobalLoader() { document.getElementById('global-loader').style.display = 'flex'; }
  document.querySelectorAll('.search-form-bind').forEach(form => { form.addEventListener('submit', function() { showGlobalLoader(); }); });
  document.querySelectorAll('.page-btn').forEach(btn => {
    btn.addEventListener('click', function(e) { if(!this.classList.contains('is-disabled') && this.getAttribute('href') !== '#') showGlobalLoader(); });
  });

  function copyTag(text, btn) {
    if(!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<svg class="icon-svg" style="color:#10b981;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
    }).catch(err => { console.error('Failed to copy', err); });
  }

  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  
  // Updated clearMenu to handle Select & Date inputs
  function clearMenu(id){ 
    var root=document.getElementById(id); if(!root) return; 
    root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false); 
    root.querySelectorAll('select').forEach(el=>el.selectedIndex=0);
    root.querySelectorAll('input[type="date"]').forEach(el=>el.value='');
  }

  function toggleFilterMenuUsed(){ var m=document.getElementById('filterMenuUsed'); if(m) m.classList.toggle('show'); }
  function clearFilterChecksUsed(){ document.querySelectorAll('#filterMenuUsed input[type="checkbox"]').forEach(el=>el.checked=false); }
  function toggleFilterMenuDonor(){ var m=document.getElementById('filterMenuDonor'); if(m) m.classList.toggle('show'); }
  function clearFilterChecksDonor(){ document.querySelectorAll('#filterMenuDonor input[type="checkbox"]').forEach(el=>el.checked=false); }

  (function(){
    var overlay=document.getElementById('imgPreviewOverlay');
    var imgEl=document.getElementById('imgPreview');
    function openPreview(src){ if(!overlay||!imgEl) return; imgEl.src=src; overlay.classList.add('show'); }
    function closePreview(){ if(!overlay) return; overlay.classList.remove('show'); if(imgEl) imgEl.src=''; }
    document.addEventListener('click',function(e){
      var btn=e.target.closest?e.target.closest('.thumb-btn'):null;
      if(!btn) return; var src=btn.getAttribute('data-src'); if(src) openPreview(src);
    });
    if(overlay) overlay.addEventListener('click',function(e){ if(e.target===overlay||e.target.classList.contains('imgpv-close')) closePreview(); });
  })();

  (function(){
    const modal = document.getElementById('remarkModal');
    const textContainer = document.getElementById('remarkFullText');
    if(!modal || !textContainer) return;
    document.addEventListener('click', function(e){
      if(e.target.classList.contains('remark-text')){
        let text = e.target.getAttribute('data-remark');
        if(text){ textContainer.textContent = text; modal.classList.add('show'); modal.setAttribute('aria-hidden','false'); }
      }
    });
    function close(){ modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); }
    modal.querySelector('.close-btn')?.addEventListener('click', close);
    modal.addEventListener('click', e => { if(e.target === modal) close(); });
    document.addEventListener('keydown', e => { if(e.key==='Escape' && modal.classList.contains('show')) close(); });
  })();

  (function(){
    const sel=document.getElementById('ppSelect'); if(!sel) return;
    sel.addEventListener('change',function(){
      showGlobalLoader();
      const u=new URL(location.href);
      u.searchParams.set('per',this.value);
      u.searchParams.set('page','1');
      location=u.toString();
    });
  })();

  (function(){
    document.addEventListener('keydown',function(e){
      if(e.altKey||e.metaKey||e.ctrlKey) return;
      if(e.key==='ArrowRight') {
        const next = document.querySelector('.page-btn[rel="next"]');
        if(next && !next.classList.contains('is-disabled')) { showGlobalLoader(); next.click(); }
      }
      if(e.key==='ArrowLeft')  {
        const prev = document.querySelector('.page-btn[rel="prev"]');
        if(prev && !prev.classList.contains('is-disabled')) { showGlobalLoader(); prev.click(); }
      }
    });
  })();
</script>