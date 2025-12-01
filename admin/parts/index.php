<?php
/********************************************************************
 * admin/parts/index.php  (RBAC-ready + Pagination)
 *
 * รวม 4 แท็บ:
 *  - new    : อะไหล่มือ 1 — GROUP BY part_code + แบ่งหน้า (+แสดงขั้นต่ำ)
 *  - used   : อะไหล่มือ 2 — แบ่งหน้า
 *  - donor  : เครื่อง  — แบ่งหน้า + ตัวกรอง (อุปกรณ์/สถานะ) + ที่เก็บ (location_index)
 *  - history: เอกสาร IN/CONSUME/MOVE/ADJUST/DONOR — (LIMIT 200)
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
// สถานะเครื่อง
$DONOR_STATUS = [
  'in_stock' => 'พร้อมแยก',
  'reserved' => 'จอง',
  'for_sale' => 'กำลังขาย', // << [ เพิ่มตรงนี้ ]
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
$donorStatuses = getvArray('status', $DONOR_STATUS); // ตัวกรองสถานะเครื่อง

[$per,$page,$offset] = get_pager();

// =========================[ 4) LOAD DATA ]============================
$parts=$usedItems=$historyRows=$donors=[];
$total=0; $pages=1;

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
  if ($w=whereSearch($q,['part_code','part_name','part_number','device_models','category','location','remarks'],$params,'qu')) $where[]=$w;
  if ($w=whereDevices($devices,['part_name','device_models','category'],$params,'du')) $where[]=$w;
  if ($w=whereKinds($kinds,$KIND_KEYWORDS,$params,'ku')) $where[]=$w;
  $where_sql=$where?("WHERE ".implode(' AND ',$where)):"";

  $stc=$pdo->prepare("SELECT COUNT(*) FROM parts_used {$where_sql}");
  $stc->execute($params);
  $total=(int)($stc->fetchColumn()?:0);
  $pages=max(1,(int)ceil($total/$per));
  if ($page>$pages){ $page=$pages; $offset=($page-1)*$per; }

  $sql="
    SELECT id, part_code, part_name, part_number, device_models, category,
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
  // เพิ่มให้ค้นหาใน location_index ด้วย
  if ($w=whereSearch($q,['device_name','device_models','category','serial_no','reserved_ref','remarks','location_index'],$params,'qd')) $where[]=$w;
  if ($w=whereDevices($devices,['device_name','device_models','category'],$params,'dd')) $where[]=$w;

  // ตัวกรองสถานะ
  if (!empty($donorStatuses)){
    $in=[]; foreach($donorStatuses as $i=>$s){ $ph=":ds{$i}"; $in[]=$ph; $params[$ph]=$s; }
    $where[] = 'status IN ('.implode(',',$in).')';
  } else {
    // รองรับของเดิม (dism=0/1)
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
      id, device_name, device_models, category, serial_no, status,
      purchase_cost, reserved_ref, image_url, remarks,
      location_index,                               -- << new column
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
  // เพิ่ม pn.part_name เข้าใน whereSearch ด้วย
  if ($w=whereSearch($q, ['l.part_code','pn.part_name','d.ref_no','d.remarks','au.username'],$params,'qh')) $where[]=$w;

  $doc_type=getv('doc_type','');
  if ($doc_type!==''){ $where[]="d.doc_type=:dt"; $params[':dt']=$doc_type; }
  $df=getv('date_from',''); if ($df!==''){ $where[]="DATE(d.created_at)>=:df"; $params[':df']=$df; }
  $dt=getv('date_to','');   if ($dt!==''){ $where[]="DATE(d.created_at)<=:dt2"; $params[':dt2']=$dt; }

  $sql="
    SELECT
      d.created_at, d.doc_type, d.ref_no, d.remarks,
      l.part_code,
      pn.part_name,
      CASE
        WHEN d.doc_type='DONOR' AND (d.remarks LIKE 'เพิ่มเครื่อง:%' OR d.remarks LIKE 'CREATE%') THEN 1
        WHEN d.doc_type='DONOR' AND (d.remarks LIKE 'ลบเครื่อง%'   OR d.remarks LIKE 'DELETE%') THEN -1
        WHEN d.doc_type='DONOR' THEN NULL
        ELSE l.qty
      END AS qty,
      l.location_from, l.location_to, l.unit_cost,
      au.username AS admin_name
    FROM parts_docs d
    LEFT JOIN parts_doc_lines l ON l.doc_id=d.doc_id
    LEFT JOIN admin_users au    ON au.id=d.user_id
    LEFT JOIN (
      SELECT part_code, MAX(part_name) AS part_name
      FROM parts_new
      GROUP BY part_code
    ) pn ON pn.part_code = l.part_code
    ".($where?("WHERE ".implode(' AND ',$where)):"")."
    ORDER BY d.doc_id DESC, COALESCE(l.line_id,0) DESC
    LIMIT 200";
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $historyRows=$st->fetchAll(PDO::FETCH_ASSOC);
}

// =========================[ 5) TEMPLATE ]=============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <!-- Tabs -->
  <div class="view-switcher">
    <?php if (can('parts.new.view')): ?>
      <a class="switcher-item <?= $tab==='new'?'active':'' ?>" href="index.php?tab=new">อะไหร่ใหม่</a>
    <?php endif; ?>
    <?php if (can('parts.used.view')): ?>
      <a class="switcher-item <?= $tab==='used'?'active':'' ?>" href="index.php?tab=used">อะไหร่มือสอง</a>
    <?php endif; ?>
    <?php if (can('parts.donor.view')): ?>
      <a class="switcher-item <?= $tab==='donor'?'active':'' ?>" href="index.php?tab=donor">เครื่อง</a>
    <?php endif; ?>
    <?php if (can('parts.history.view')): ?>
      <a class="switcher-item <?= $tab==='history'?'active':'' ?>" href="index.php?tab=history">ประวัติ</a>
    <?php endif; ?>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <h2>
      <?php if ($tab==='new'): ?>อะไหล่มือ 1<?php elseif ($tab==='used'): ?>อะไหล่มือ 2<?php elseif ($tab==='donor'): ?>เครื่อง<?php else: ?>ประวัติการเคลื่อนไหว<?php endif; ?>
    </h2>
    <div>
      <?php if ($tab==='new'  && can('parts.new.create')): ?><a href="form.php" class="btn-primary">+ เพิ่มชนิดอะไหล่ใหม่</a><?php endif; ?>
      <?php if ($tab==='used' && can('parts.used.create')): ?><a href="form_used.php" class="btn-primary">+ เพิ่มชิ้นมือสอง</a><?php endif; ?>
      <?php if ($tab==='donor' && can('parts.donor.create')): ?><a href="donor_form.php" class="btn-primary">+ เพิ่มเครื่อง</a><?php endif; ?>
    </div>
  </div>

  <!-- Flash -->
  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <?php if ($tab==='new'): ?>
    <!-- ===================== TAB: NEW ===================== -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="new">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/เบอร์/รุ่น/หมวด...">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuNew')">ตัวกรอง</button>
          <div id="filterMenuNew" class="filter-menu">
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
                <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuNew')">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
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
        <thead>
          <tr>
            <th>#</th>
            <th>รูป</th>
            <th>ชื่ออะไหล่</th>
            <th>เลขอะไหล่</th>
            <th>รุ่น</th>
            <th>หมวด</th>
            <th>ที่เก็บ</th>
            <th>ขั้นต่ำ</th><!-- new -->
            <th>คงเหลือ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($parts): foreach ($parts as $i=>$p):
            $img=img_src($p['image_url']??'');
            $qty=(int)$p['qty'];
            $min=(int)$p['min_stock'];
            $low=$min>0 && $qty<$min;
            $locs=array_filter(array_map('trim',explode(',',(string)($p['locations']??''))));
          ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button type="button" class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb" alt=""></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              <td><strong><?= h($p['part_name'] ?: $p['part_code']) ?></strong></td>
              <td class="muted"><?= h($p['part_number']) ?></td>
              <td><?= h($p['device_models']) ?></td>
              <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>
              <td><?php if ($locs): ?><div class="chips"><?php foreach($locs as $L): ?><span class="badge"><?= h($L) ?></span><?php endforeach; ?></div><?php else: ?><span class="muted">-</span><?php endif; ?></td>
              <td><?= $min>0 ? h($min) : '<span class="muted">-</span>' ?></td>
              <td><?= $low ? '<span class="badge" title="ต่ำกว่าขั้นต่ำ">'.h($qty).'</span>' : h($qty) ?></td>
              <td class="no-wrap">
                <?php if (can('parts.new.restock')): ?><a href="restock.php?part_code=<?= h($p['part_code']) ?>" class="btn-success">เติมสต็อก</a><?php endif; ?>
                <?php if (can('parts.new.consume')): ?><a href="consume.php?type=new&part_code=<?= h($p['part_code']) ?>" class="btn-checkout">เบิก</a><?php endif; ?>
                <?php if (can('parts.new.update')): ?><a href="form.php?part_code=<?= h($p['part_code']) ?>" class="btn-edit">แก้ไข</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="10" class="text-center">ยังไม่มีข้อมูล</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pager -->
    <div class="pager-bar">
      <div class="pager-left">
        <span class="pager-total">พบ <?= (int)$total ?> รายการ</span>
        <span class="divider">•</span>
        <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
      </div>
      <nav class="pager-nav" aria-label="Pagination">
        <a class="page-btn <?= $page<=1?'is-disabled':'' ?>" href="<?= $page>1?page_url($page-1):'#' ?>" rel="prev">‹</a>
        <?php $start=max(1,$page-2); $end=min($pages,$page+2);
        if ($start>1) echo '<span class="page-ellipsis">…</span>';
        for($i=$start;$i<=$end;$i++): ?><a class="page-btn <?= $i==$page?'is-active':'' ?>" href="<?= page_url($i) ?>"><?= $i ?></a><?php endfor;
        if ($end<$pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page>=$pages?'is-disabled':'' ?>" href="<?= $page<$pages?page_url($page+1):'#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach([20,50,100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>

  <?php elseif ($tab==='history'): ?>
    <!-- ===================== TAB: HISTORY ===================== -->
    <form action="index.php" method="get" class="search-and-filter-group">
      <input type="hidden" name="tab" value="history">
      <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="part_code / อ้างอิง / หมายเหตุ / ผู้ทำรายการ">
      <select name="doc_type" class="filter-input">
        <option value="">ทุกประเภท</option>
        <option value="IN" <?= getv('doc_type')==='IN'?'selected':'' ?>>รับเข้า</option>
        <option value="CONSUME" <?= getv('doc_type')==='CONSUME'?'selected':'' ?>>เบิก</option>
        <option value="MOVE" <?= getv('doc_type')==='MOVE'?'selected':'' ?>>ย้าย</option>
        <option value="ADJUST" <?= getv('doc_type')==='ADJUST'?'selected':'' ?>>ปรับยอด</option>
        <option value="USED" <?= getv('doc_type')==='USED'?'selected':'' ?>>มือ 2</option>
        <option value="DONOR" <?= getv('doc_type')==='DONOR'?'selected':'' ?>>เครื่อง</option>
      </select>
      <input type="date" name="date_from" value="<?= h(getv('date_from')) ?>">
      <input type="date" name="date_to" value="<?= h(getv('date_to')) ?>">
      <button class="btn-search">ค้นหา</button>
    </form>

    <div class="table-container">
      <table class="data-table">
<thead>
  <tr>
    <th>เวลา</th>
    <th>ประเภท</th>
    <th>ชื่ออะไหล่</th>
    <th>จำนวน</th>
    <th>จาก</th>
    <th>ไป</th>
    <th>ต้นทุน</th>
    <th>อ้างอิง</th>
    <th>ผู้ทำรายการ</th>
    <th>หมายเหตุ</th>
  </tr>
</thead>
<tbody>
  <?php if ($historyRows): foreach ($historyRows as $r): ?>
    <tr>
      <td><?= h($r['created_at']) ?></td>
      <td><span class="badge"><?= h(doc_label($r['doc_type'])) ?></span></td>
      <td><?= h($r['part_name'] ?: $r['part_code'] ?: '') ?></td>
      <td class="fw-600"><?= h(qty_fmt($r['doc_type'],$r['qty'])) ?></td>
      <td><?= h($r['location_from']) ?></td>
      <td><?= h($r['location_to']) ?></td>
      <td><?= $r['unit_cost']!==null ? number_format($r['unit_cost'],2) : '' ?></td>
      <td class="muted"><?= h($r['ref_no']) ?></td>
      <td><?= h($r['admin_name'] ?? 'N/A') ?></td>
      <td><?= h($r['remarks']) ?></td>
    </tr>
  <?php endforeach; else: ?>
    <tr><td colspan="10" class="text-center">ยังไม่มีประวัติ</td></tr>
  <?php endif; ?>
</tbody>
      </table>
    </div>

  <?php elseif ($tab==='used'): ?>
    <!-- ===================== TAB: USED ===================== -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="used">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/เลขอะไหล่/รุ่น/หมวด/หมายเหตุ">
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
        <input type="hidden" name="page" value="1">
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th><th>รูป</th><th>ชื่ออะไหล่</th><th>เลขอะไหล่</th><th>รุ่น</th><th>หมวด</th><th>ที่เก็บ</th><th>หมายเหตุ</th><th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($usedItems): foreach ($usedItems as $i=>$u): $img=img_src($u['image_url']??''); $remark=trim((string)$u['remarks']); ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button type="button" class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb" alt=""></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              <td><strong><?= h($u['part_name'] ?: $u['part_code']) ?></strong></td>
              <td class="muted"><?= h($u['part_number']) ?></td>
              <td><?= h($u['device_models']) ?></td>
              <td><span class="badge"><?= h($u['category'] ?: 'Other') ?></span></td>
              <td><?= h($u['location'] ?: '-') ?></td>
              <td class="remark-cell"><?= $remark!=='' ? '<span class="remark-text" data-remark="'.h($remark).'" title="'.h($remark).'">'.h($remark).'</span>' : '<span class="muted">-</span>' ?></td>
              <td class="no-wrap">
                <?php if (can('parts.used.consume')): ?><a class="btn-checkout" href="consume.php?type=used&used_id=<?= (int)$u['id'] ?>">เบิก</a><?php endif; ?>
                <?php if (can('parts.used.update')): ?><a class="btn-edit" href="form_used.php?id=<?= (int)$u['id'] ?>">แก้ไข</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center">ยังไม่มีชิ้นมือ 2</td></tr>
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
        <a class="page-btn <?= $page<=1?'is-disabled':'' ?>" href="<?= $page>1?page_url($page-1):'#' ?>" rel="prev">‹</a>
        <?php $start=max(1,$page-2); $end=min($pages,$page+2);
        if ($start>1) echo '<span class="page-ellipsis">…</span>';
        for($i=$start;$i<=$end;$i++): ?><a class="page-btn <?= $i==$page?'is-active':'' ?>" href="<?= page_url($i) ?>"><?= $i ?></a><?php endfor;
        if ($end<$pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page>=$pages?'is-disabled':'' ?>" href="<?= $page<$pages?page_url($page+1):'#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach([20,50,100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>

  <?php elseif ($tab==='donor'): ?>
    <!-- ===================== TAB: DONOR (ครบ) ===================== -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="donor">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา รุ่น/ซีเรียล/อ้างอิงจอง/หมายเหตุ/ที่เก็บ">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleFilterMenuDonor()">ตัวกรอง</button>
          <div id="filterMenuDonor" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">อุปกรณ์</div>
              <?php foreach ($DEVICE_LABELS as $val=>$label): $checked=in_array($val,$devices,true)?'checked':''; ?>
                <label class="checkline"><input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">สถานะ</div>
              <?php foreach ($DONOR_STATUS as $val=>$label): $checked=in_array($val,$donorStatuses,true)?'checked':''; ?>
                <label class="checkline"><input type="checkbox" name="status[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearFilterChecksDonor()">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
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
        <thead>
          <tr>
            <th>#</th>
            <th>รูป</th>
            <th>รุ่น/เครื่อง</th>
            <th>ซีเรียล</th>
            <th>หมวด</th>
            <th>สถานะ</th>
            <th>ทุน</th>
            <th>อ้างอิงจอง</th>
            <th>หมายเหตุ</th>
            <th>ที่เก็บ</th>     <!-- << added column -->
            <th>วันที่เพิ่ม</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($donors): foreach ($donors as $i=>$d):
            $img=img_src($d['image_url']??'');
            $remark=trim((string)$d['remarks']);
            $status=(string)($d['status']??'');
            $statusClass=[
              'in_stock'=>'badge-green',
              'reserved'=>'badge-amber',
              'stripped'=>'badge-blue',
              'sold'=>'badge-gray'
            ][$status] ?? 'badge-gray';
            $statusLabel=$DONOR_STATUS[$status] ?? $status;
          ?>
            <tr>
              <td><?= ($offset+$i+1) ?></td>
              <td><?php if ($img): ?><button type="button" class="thumb-btn" data-src="<?= h($img) ?>"><img src="<?= h($img) ?>" class="thumb" alt=""></button><?php else: ?><div class="thumb"></div><?php endif; ?></td>
              <td><strong><?= h($d['device_models'] ?: $d['device_name']) ?></strong></td>
              <td class="muted"><?= h($d['serial_no']) ?></td>
              <td><?= h($d['category']) ?></td>
              <td><span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
              <td><?= $d['purchase_cost']!==null ? number_format($d['purchase_cost'],2) : '' ?></td>
              <td><?= $d['reserved_ref'] ? h($d['reserved_ref']) : '<span class="muted">-</span>' ?></td>
              <td class="remark-cell">
                <?= $remark!=='' ? '<span class="remark-text" data-remark="'.h($remark).'" title="'.h($remark).'">'.h($remark).'</span>' : '<span class="muted">-</span>' ?>
              </td>
              <td><?= $d['location_index'] ? h($d['location_index']) : '<span class="muted">-</span>' ?></td> <!-- << show -->
              <td class="muted"><?= h($d['created_at']) ?></td>
              <td class="no-wrap">
                <?php if ((int)$d['is_dismantled']===0): ?>
                  <?php if (can('parts.donor.split')): ?>
                    <a class="btn-checkout" href="donor_split.php?id=<?= (int)$d['id'] ?>">แยกอะไหล่</a>
                  <?php else: ?>
                    <a class="btn-secondary" href="donor_split.php?id=<?= (int)$d['id'] ?>">ดู</a>
                  <?php endif; ?>
                <?php else: ?>
                  <a class="btn-danger" href="donor_split.php?id=<?= (int)$d['id'] ?>">ดูรายการที่แยก</a>
                <?php endif; ?>
                <?php if (can('parts.donor.update')): ?>
                  <a class="btn-edit" href="donor_form.php?id=<?= (int)$d['id'] ?>">แก้ไข</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="12" class="text-center">ยังไม่มีเครื่อง</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pager -->
    <div class="pager-bar">
      <div class="pager-left">
        <span class="pager-total">พบ <?= (int)$total ?> รายการ</span>
        <span class="divider">•</span>
        <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
      </div>
      <nav class="pager-nav" aria-label="Pagination">
        <a class="page-btn <?= $page<=1?'is-disabled':'' ?>" href="<?= $page>1?page_url($page-1):'#' ?>" rel="prev">‹</a>
        <?php $start=max(1,$page-2); $end=min($pages,$page+2);
        if ($start>1) echo '<span class="page-ellipsis">…</span>';
        for($i=$start;$i<=$end;$i++): ?><a class="page-btn <?= $i==$page?'is-active':'' ?>" href="<?= page_url($i) ?>"><?= $i ?></a><?php endfor;
        if ($end<$pages) echo '<span class="page-ellipsis">…</span>'; ?>
        <a class="page-btn <?= $page>=$pages?'is-disabled':'' ?>" href="<?= $page<$pages?page_url($page+1):'#' ?>" rel="next">›</a>
        <div class="page-size">
          <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
            <?php foreach([20,50,100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
          </select>
        </div>
      </nav>
    </div>

    <!-- Modal หมายเหตุ -->
    <div id="remarkModal" class="remark-modal" aria-hidden="true">
      <div class="modal-content" role="dialog" aria-modal="true" aria-label="หมายเหตุ">
        <button type="button" class="close-btn" aria-label="ปิด">✕</button>
        <div id="remarkFullText"></div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Image Preview Modal -->
  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<!-- ========================= SCRIPTS ========================= -->
<script>
  // dropdown: new
  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  document.addEventListener('click',function(e){
    var dd=e.target.closest?e.target.closest('.filter-dropdown'):null;
    document.querySelectorAll('.filter-menu.show').forEach(function(m){ if(!dd || !dd.contains(m)) m.classList.remove('show'); });
  });
  function clearMenu(id){ var root=document.getElementById(id); if(!root) return; root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false); }

  // dropdown: used
  function toggleFilterMenuUsed(){ var m=document.getElementById('filterMenuUsed'); if(m) m.classList.toggle('show'); }
  function clearFilterChecksUsed(){ document.querySelectorAll('#filterMenuUsed input[type="checkbox"]').forEach(el=>el.checked=false); }

  // dropdown: donor
  function toggleFilterMenuDonor(){ var m=document.getElementById('filterMenuDonor'); if(m) m.classList.toggle('show'); }
  function clearFilterChecksDonor(){ document.querySelectorAll('#filterMenuDonor input[type="checkbox"]').forEach(el=>el.checked=false); }

  // Image preview
  (function(){
    var overlay=document.getElementById('imgPreviewOverlay');
    var imgEl=document.getElementById('imgPreview');
    function openPreview(src){ if(!overlay||!imgEl) return; imgEl.src=src; overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); }
    function closePreview(){ if(!overlay) return; overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); if(imgEl) imgEl.src=''; }
    document.addEventListener('click',function(e){
      var btn=e.target.closest?e.target.closest('.thumb-btn'):null;
      if(!btn) return; var src=btn.getAttribute('data-src'); if(src) openPreview(src);
    });
    if(overlay){ overlay.addEventListener('click',function(e){ if(e.target===overlay||e.target.classList.contains('imgpv-close')) closePreview(); }); }
    document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&overlay&&overlay.classList.contains('show')) closePreview(); });
  })();

  // page size
  (function(){
    const sel=document.getElementById('ppSelect'); if(!sel) return;
    sel.addEventListener('change',function(){
      const u=new URL(location.href);
      u.searchParams.set('per',this.value);
      u.searchParams.set('page','1');
      location=u.toString();
    });
  })();

  // arrow shortcuts
  (function(){
    document.addEventListener('keydown',function(e){
      if(e.altKey||e.metaKey||e.ctrlKey) return;
      if(e.key==='ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
      if(e.key==='ArrowLeft')  document.querySelector('.page-btn[rel="prev"]')?.click();
    });
  })();
</script>
