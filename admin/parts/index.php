<?php
/********************************************************************
 * admin/parts/index.php  (clean + no is_active)
 *
 * Tabs:
 *  - new    : อะไหล่มือ 1   — เติมสต็อก / เบิก / แก้ไข
 *  - used   : อะไหล่มือ 2   — (#, รูป, ชื่ออะไหล่, เลขอะไหล่, รุ่น, หมวด, หมายเหตุ, จัดการ)
 *  - donor  : เครื่องซาก    — เพิ่ม/แก้ไข/ลบ
 *  - history: เอกสาร IN/CONSUME/MOVE/ADJUST
 *
 * Tables:
 *  - parts_new, parts_used, parts_donors
 *  - parts_docs, parts_doc_lines, admin_users
 ********************************************************************/

// =========================[ 0) SETUP & GUARD ]========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "จัดการอะไหล่";

// =========================[ 1) CONSTANTS / MAPS ]=====================
$DEVICE_LABELS = ['macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac'];
$KIND_LABELS   = [
  'screen' => 'จอ/Screen',
  'battery' => 'แบต/Battery',
  'keyboard' => 'คีย์บอร์ด',
  'trackpad' => 'Trackpad',
  'speaker' => 'ลำโพง',
  'camera' => 'กล้อง',
  'board' => 'บอร์ด/Logic',
  'cable' => 'สาย/Flex',
  'fan' => 'พัดลม',
  'hinge' => 'บานพับ',
  'case' => 'ฝา/เคส'
];
$KIND_KEYWORDS = [
  'screen'   => ['จอ', 'screen', 'display', 'lcd'],
  'battery'  => ['แบต', 'battery'],
  'keyboard' => ['คีย์บอร์ด', 'keyboard', 'kb'],
  'trackpad' => ['trackpad', 'ทัชแพด'],
  'speaker'  => ['ลำโพง', 'speaker'],
  'camera'   => ['กล้อง', 'camera'],
  'board'    => ['board', 'logic', 'mainboard', 'เมนบอร์ด', 'บอร์ด'],
  'cable'    => ['สาย', 'cable', 'flex'],
  'fan'      => ['พัดลม', 'fan'],
  'hinge'    => ['บานพับ', 'hinge'],
  'case'     => ['ฝาหลัง', 'ฝา', 'case', 'top case', 'bottom']
];
$DONOR_STATUS_LABELS = ['in_stock' => 'คงอยู่', 'reserved' => 'จอง', 'stripped' => 'ถอดอะไหล่แล้ว', 'disposed' => 'จำหน่ายทิ้ง', 'sold' => 'ขายออก', 'scrap' => 'ซาก'];

// =========================[ 2) HELPERS ]==============================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function getvArray($key,array $allow): array {
  $v = isset($_GET[$key]) ? (array)$_GET[$key] : [];
  return array_values(array_intersect($v, array_keys($allow)));
}
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v)) return $v;
  if ($v[0]==='/') return $v;
  return '/uploads/parts/'.$v;
}
function doc_label($t){ return $t==='IN'?'รับเข้า':($t==='CONSUME'?'เบิก':($t==='MOVE'?'ย้าย':($t==='ADJUST'?'ปรับยอด':$t))); }
function qty_fmt($t,$q){ $q=(int)$q; return $t==='IN'?('+'.$q):($t==='CONSUME'?('-'.$q):(string)$q); }

function whereSearch(string $q,array $cols,array &$params,string $pfx):?string{
  if ($q==='') return null;
  $ors=[]; $i=0;
  foreach($cols as $c){
    $ph=":{$pfx}{$i}";
    $ors[]="$c LIKE $ph";
    $params[$ph]="%{$q}%";
    $i++;
  }
  return '(' . implode(' OR ', $ors) . ')';
}
function whereDevices(array $devices,array $cols,array &$params,string $pfx):?string{
  if (!$devices) return null;
  $map=['macbook'=>'MacBook','iphone'=>'iPhone','ipad'=>'iPad','imac'=>'iMac'];
  $ors=[]; $i=0;
  foreach($devices as $d){
    $kw=$map[$d]??$d;
    $ph=":{$pfx}{$i}";
    $params[$ph]="%{$kw}%";
    $inner=[];
    foreach($cols as $c) $inner[]="$c LIKE $ph";
    $ors[]='('.implode(' OR ',$inner).')';
    $i++;
  }
  return '(' . implode(' OR ', $ors) . ')';
}
function whereKinds(array $kinds,array $kwMap,array &$params,string $pfx):?string{
  if (!$kinds) return null;
  $ors=[]; $i=0;
  foreach($kinds as $k){
    if (!isset($kwMap[$k])) continue;
    $likes=[];
    foreach($kwMap[$k] as $w){
      $ph=":{$pfx}{$i}";
      $likes[]="part_name LIKE $ph";
      $params[$ph]="%{$w}%";
      $i++;
    }
    if ($likes) $ors[]='('.implode(' OR ',$likes).')';
  }
  return $ors ? '(' . implode(' OR ', $ors) . ')' : null;
}

// =========================[ 3) STATE ]================================
$tab = getv('tab','new');
$q   = getv('q','');
$msg = getv('msg','');
$err = getv('err','');

$devices = getvArray('device',$DEVICE_LABELS);
$kinds   = getvArray('kind',$KIND_LABELS);

// =========================[ 4) LOAD DATA ]============================
$parts = $usedItems = $donors = $historyRows = [];

/* 4.1 NEW: parts_new (สรุปตาม part_code) */
if ($tab==='new'){
  $params=[]; $where=[];
  if ($w=whereSearch($q,['part_name','part_number','device_models','category'],$params,'qn')) $where[]=$w;
  if ($w=whereDevices($devices,['part_name','device_models','category'],$params,'dn')) $where[]=$w;
  if ($w=whereKinds($kinds,$KIND_KEYWORDS,$params,'kn')) $where[]=$w;

  $sql="
    SELECT part_code,
           MAX(part_name)     AS part_name,
           MAX(part_number)   AS part_number,
           MAX(device_models) AS device_models,
           MAX(category)      AS category,
           MAX(image_url)     AS image_url,
           MAX(min_stock)     AS min_stock,
           SUM(quantity)      AS qty
    FROM parts_new
    ".($where?"WHERE ".implode(' AND ',$where):"")."
    GROUP BY part_code
    ORDER BY part_code DESC
    LIMIT 500";
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $parts=$st->fetchAll(PDO::FETCH_ASSOC);
}

/* 4.2 USED: parts_used (ไม่มี serial_no / status / is_active / min_stock) */
if ($tab==='used'){
  $params=[]; $where=[];
  if ($w=whereSearch($q,['part_code','part_name','part_number','device_models','category','location','remarks'],$params,'qu')) $where[]=$w;
  if ($w=whereDevices($devices,['part_name','device_models','category'],$params,'du')) $where[]=$w;
  if ($w=whereKinds($kinds,$KIND_KEYWORDS,$params,'ku')) $where[]=$w;

  $sql="
    SELECT id, part_code, part_name, part_number, device_models, category,
           image_url, location, remarks, created_at, updated_at
    FROM parts_used
    ".($where?"WHERE ".implode(' AND ',$where):"")."
    ORDER BY id DESC
    LIMIT 500";
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $usedItems=$st->fetchAll(PDO::FETCH_ASSOC);
}

/* 4.3 DONOR: parts_donors */
if ($tab==='donor'){
  $params=[]; $where=[];
  if ($w=whereSearch($q,['device_name','device_models','category','serial_no','remarks'],$params,'qd')) $where[]=$w;
  if ($w=whereDevices($devices,['device_name','device_models','category'],$params,'dd')) $where[]=$w;
  $dstatus=getv('status','');
  if (in_array($dstatus,array_keys($DONOR_STATUS_LABELS),true)){
    $where[]="status=:dst"; $params[':dst']=$dstatus;
  }
  $sql="
    SELECT id, device_name, device_models, category, image_url, serial_no,
           status, remarks, created_at, updated_at
    FROM parts_donors
    ".($where?"WHERE ".implode(' AND ',$where):"")."
    ORDER BY id DESC
    LIMIT 500";
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $donors=$st->fetchAll(PDO::FETCH_ASSOC);
}

/* 4.4 HISTORY: docs + lines + admin */
if ($tab==='history'){
  $params=[]; $where=[];
  if ($w=whereSearch($q,['l.part_code','d.ref_no','d.remarks','au.username'],$params,'qh')) $where[]=$w;
  $doc_type=getv('doc_type','');
  if ($doc_type!==''){ $where[]="d.doc_type=:dt"; $params[':dt']=$doc_type; }
  $df=getv('date_from',''); if ($df!==''){ $where[]="DATE(d.created_at)>=:df"; $params[':df']=$df; }
  $dt=getv('date_to','');   if ($dt!==''){ $where[]="DATE(d.created_at)<=:dt2"; $params[':dt2']=$dt; }

  $sql="
    SELECT d.created_at, d.doc_type, d.ref_no, d.remarks,
           l.part_code, l.qty, l.location_from, l.location_to, l.unit_cost,
           au.username AS admin_name
    FROM parts_docs d
    JOIN parts_doc_lines l ON l.doc_id=d.doc_id
    LEFT JOIN admin_users au ON au.id=d.user_id
    ".($where?"WHERE ".implode(' AND ',$where):"")."
    ORDER BY d.doc_id DESC, l.line_id DESC
    LIMIT 200";
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $historyRows=$st->fetchAll(PDO::FETCH_ASSOC);
}

// =========================[ 5) TEMPLATE HEAD ]========================
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
  <div class="view-switcher" style="margin-bottom:15px;">
    <a class="switcher-item <?= $tab==='new'?'active':'' ?>" href="index.php?tab=new">ของมือ 1</a>
    <a class="switcher-item <?= $tab==='used'?'active':'' ?>" href="index.php?tab=used">ของมือ 2</a>
    <a class="switcher-item <?= $tab==='donor'?'active':'' ?>" href="index.php?tab=donor">เครื่องซาก</a>
    <a class="switcher-item <?= $tab==='history'?'active':'' ?>" href="index.php?tab=history">ประวัติ</a>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <h2>
      <?php if ($tab==='new'): ?>อะไหล่มือ 1
      <?php elseif ($tab==='used'): ?>อะไหล่มือ 2
      <?php elseif ($tab==='donor'): ?>เครื่องซาก
      <?php else: ?>ประวัติการเคลื่อนไหว<?php endif; ?>
    </h2>
    <?php if     ($tab==='new'):   ?><a href="form.php"       class="btn-primary">+ เพิ่มชนิดอะไหล่ใหม่</a>
    <?php elseif ($tab==='used'):  ?><a href="form_used.php"  class="btn-primary">+ เพิ่มชิ้นมือ 2</a>
    <?php elseif ($tab==='donor'): ?><a href="form_donor.php" class="btn-primary">+ เพิ่มเครื่องซาก</a>
    <?php endif; ?>
  </div>

  <!-- Flash -->
  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?><div class="alert alert-success">บันทึกชิ้นมือ 2 เรียบร้อย</div><?php endif; ?>
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

        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th style="width:56px;">รูป</th>
            <th>ชื่ออะไหล่</th>
            <th>เลขอะไหล่</th>
            <th>รุ่น</th>
            <th>หมวด</th>
            <th>คงเหลือ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($parts): foreach($parts as $i=>$p): $img=img_src($p['image_url']??''); $qty=(int)$p['qty']; $min=(int)$p['min_stock']; $low=$min>0 && $qty<$min; ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>
              <?php if ($img): ?>
                <button type="button" class="thumb-btn" data-src="<?= h($img) ?>">
                  <img src="<?= h($img) ?>" class="thumb" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt="">
                </button>
              <?php else: ?>
                <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">-</div>
              <?php endif; ?>
            </td>
            <td><strong><?= h($p['part_name'] ?: $p['part_code']) ?></strong></td>
            <td class="muted"><?= h($p['part_number']) ?></td>
            <td><?= h($p['device_models']) ?></td>
            <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>
            <td><?= $low ? '<span class="badge" style="background:#ffe6e6;color:#b30000;" title="ต่ำกว่าขั้นต่ำ">'.h($qty).'</span>' : h($qty) ?></td>
            <td class="no-wrap" style="min-width:220px;display:flex;gap:6px;flex-wrap:wrap;">
              <a href="restock.php?part_code=<?= h($p['part_code']) ?>" class="btn-primary">เติมสต็อก</a>
              <a href="consume.php?type=new&part_code=<?= h($p['part_code']) ?>" class="btn-secondary">เบิก</a>
              <a href="form.php?part_code=<?= h($p['part_code']) ?>" class="btn-edit">แก้ไข</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;">ยังไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
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
      </select>
      <input type="date" name="date_from" value="<?= h(getv('date_from')) ?>">
      <input type="date" name="date_to"   value="<?= h(getv('date_to')) ?>">
      <button class="btn-search">ค้นหา</button>
    </form>

    <div class="table-container" style="margin-top:12px;">
      <table class="data-table">
        <thead>
          <tr>
            <th>เวลา</th><th>ประเภท</th><th>Part Code</th><th>จำนวน</th><th>จาก</th><th>ไป</th><th>ต้นทุน</th><th>อ้างอิง</th><th>ผู้ทำรายการ</th><th>หมายเหตุ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($historyRows): foreach($historyRows as $r): ?>
          <tr>
            <td><?= h($r['created_at']) ?></td>
            <td><span class="badge"><?= h(doc_label($r['doc_type'])) ?></span></td>
            <td><?= h($r['part_code']) ?></td>
            <td style="font-weight:600;"><?= h(qty_fmt($r['doc_type'],$r['qty'])) ?></td>
            <td><?= h($r['location_from']) ?></td>
            <td><?= h($r['location_to']) ?></td>
            <td><?= $r['unit_cost']!==null ? number_format($r['unit_cost'],2) : '' ?></td>
            <td class="muted"><?= h($r['ref_no']) ?></td>
            <td><?= h($r['admin_name'] ?? 'N/A') ?></td>
            <td><?= h($r['remarks']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="10" style="text-align:center;">ยังไม่มีประวัติ</td></tr>
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
        <a href="form_used.php" class="btn-primary">+ เพิ่มชิ้นมือ 2</a>
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th><th style="width:56px;">รูป</th><th>ชื่ออะไหล่</th><th>เลขอะไหล่</th><th>รุ่น</th><th>หมวด</th><th>หมายเหตุ</th><th style="min-width:220px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($usedItems): foreach($usedItems as $i=>$u): $img=img_src($u['image_url']??''); ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>
              <?php if ($img): ?>
                <button type="button" class="thumb-btn" data-src="<?= h($img) ?>">
                  <img src="<?= h($img) ?>" class="thumb" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt="">
                </button>
              <?php else: ?>
                <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">-</div>
              <?php endif; ?>
            </td>
            <td><strong><?= h($u['part_name'] ?: $u['part_code']) ?></strong></td>
            <td class="muted"><?= h($u['part_number']) ?></td>
            <td><?= h($u['device_models']) ?></td>
            <td><span class="badge"><?= h($u['category'] ?: 'Other') ?></span></td>
            <td>
              <?php if (trim((string)$u['remarks'])!==''): ?>
                <details>
                  <summary class="muted" style="cursor:pointer;">ดู</summary>
                  <div style="white-space:pre-wrap;max-width:360px;"><?= h($u['remarks']) ?></div>
                </details>
              <?php else: ?><span class="muted">-</span><?php endif; ?>
            </td>
            <td class="no-wrap" style="display:flex;gap:6px;flex-wrap:wrap;">
              <a class="btn-secondary" href="consume.php?type=used&used_id=<?= (int)$u['id'] ?>">เบิก</a>
              <a class="btn-edit" href="form_used.php?id=<?= (int)$u['id'] ?>">แก้ไข</a>
              <a class="btn-delete" href="form_used.php?op=delete&id=<?= (int)$u['id'] ?>" onclick="return confirm('ลบชิ้นนี้ถาวร ใช่ไหม?')">ลบ</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;">ยังไม่มีชิ้นมือ 2</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='donor'): ?>
    <!-- ===================== TAB: DONOR ===================== -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="donor">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อเครื่อง/รุ่น/หมวด/serial/หมายเหตุ">
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuDonor')">ตัวกรอง</button>
          <div id="filterMenuDonor" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">อุปกรณ์</div>
              <?php foreach ($DEVICE_LABELS as $val=>$label): $checked=in_array($val,$devices,true)?'checked':''; ?>
                <label class="checkline"><input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">สถานะเครื่อง</div>
              <?php $dst=getv('status',''); ?>
              <select name="status" class="filter-input">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($DONOR_STATUS_LABELS as $k=>$lab): ?>
                  <option value="<?= h($k) ?>" <?= $dst===$k?'selected':'' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="filter-actions" style="margin-top:8px;">
                <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuDonor')">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
              </div>
            </div>
          </div>
        </div>
        <a href="form_donor.php" class="btn-primary">+ เพิ่มเครื่องซาก</a>
        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th><th style="width:56px;">รูป</th><th>เครื่อง/รุ่น</th><th>หมวด</th><th>Serial</th><th>สถานะ</th><th>หมายเหตุ</th><th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($donors): foreach($donors as $i=>$d): $img=img_src($d['image_url']??''); ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>
              <?php if ($img): ?>
                <button type="button" class="thumb-btn" data-src="<?= h($img) ?>">
                  <img src="<?= h($img) ?>" class="thumb" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt="">
                </button>
              <?php else: ?>
                <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">-</div>
              <?php endif; ?>
            </td>
            <td><strong><?= h($d['device_name']) ?></strong><div class="muted"><?= h($d['device_models']) ?></div></td>
            <td><span class="badge"><?= h($d['category'] ?: 'Other') ?></span></td>
            <td class="muted"><?= h($d['serial_no']) ?></td>
            <td><span class="badge"><?= h($DONOR_STATUS_LABELS[$d['status']] ?? $d['status']) ?></span></td>
            <td>
              <?php if (trim((string)$d['remarks'])!==''): ?>
                <details>
                  <summary class="muted" style="cursor:pointer;">ดู</summary>
                  <div style="white-space:pre-wrap;max-width:320px;"><?= h($d['remarks']) ?></div>
                </details>
              <?php else: ?><span class="muted">-</span><?php endif; ?>
            </td>
            <td class="no-wrap" style="min-width:200px;display:flex;gap:6px;flex-wrap:wrap;">
              <a class="btn-edit"   href="form_donor.php?id=<?= (int)$d['id'] ?>">แก้ไข</a>
              <a class="btn-delete" href="form_donor.php?op=delete&id=<?= (int)$d['id'] ?>" onclick="return confirm('ลบเครื่องซากนี้ถาวร ใช่ไหม?')">ลบ</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;">ยังไม่มีเครื่องซาก</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
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

<!-- ========================= STYLES ========================= -->
<style>
  .filter-dropdown{ position:relative; display:inline-block; }
  .filter-menu{ display:none; position:absolute; right:0; z-index:20; min-width:280px; background:#fff; border:1px solid #e5e5e5; border-radius:10px; padding:10px; box-shadow:0 6px 20px rgba(0,0,0,.08);}
  .filter-menu.show{ display:block; }
  .filter-section{ padding:8px 6px; border-top:1px dashed #eee; }
  .filter-section:first-child{ border-top:0; }
  .filter-title{ font-weight:600; margin-bottom:6px; }
  .filter-actions{ display:flex; justify-content:space-between; gap:8px; margin-top:8px; }
  .checkline{ display:flex; align-items:center; gap:8px; padding:4px 0; cursor:pointer; }
  .thumb-btn{ padding:0; border:0; background:transparent; cursor:pointer; }
  .thumb-btn:focus{ outline:2px solid #99c; outline-offset:2px; }
  .imgpv-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.65); display:none; align-items:center; justify-content:center; z-index:1000; }
  .imgpv-overlay.show{ display:flex; }
  .imgpv-dialog{ position:relative; max-width:90vw; max-height:90vh; display:flex; align-items:center; justify-content:center; }
  .imgpv-img{ max-width:90vw; max-height:85vh; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.4); background:#fff; }
  .imgpv-close{ position:absolute; top:-36px; right:-6px; border:0; background:transparent; color:#fff; font-size:24px; cursor:pointer; }
  @media (max-width:640px){ .imgpv-close{ top:-44px; right:0; } }
</style>

<!-- ========================= SCRIPTS ========================= -->
<script>
  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  document.addEventListener('click',function(e){
    var dd=e.target.closest?e.target.closest('.filter-dropdown'):null;
    document.querySelectorAll('.filter-menu.show').forEach(function(m){ if(!dd || !dd.contains(m)) m.classList.remove('show'); });
  });
  function clearMenu(id){
    var root=document.getElementById(id); if(!root) return;
    root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false);
    root.querySelectorAll('select').forEach(sel=>sel.selectedIndex=0);
  }

  // Used tab dropdown helpers
  function toggleFilterMenuUsed(){ var m=document.getElementById('filterMenuUsed'); if(m) m.classList.toggle('show'); }
  function clearFilterChecksUsed(){ document.querySelectorAll('#filterMenuUsed input[type="checkbox"]').forEach(el=>el.checked=false); }

  // Image preview modal
  (function(){
    var overlay=document.getElementById('imgPreviewOverlay');
    var imgEl=document.getElementById('imgPreview');
    function openPreview(src){ if(!overlay||!imgEl) return; imgEl.src=src; overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); }
    function closePreview(){ if(!overlay) return; overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); if(imgEl) imgEl.src=''; }
    document.addEventListener('click',function(e){
      var btn=e.target.closest?e.target.closest('.thumb-btn'):null;
      if(!btn) return; var src=btn.getAttribute('data-src'); if(src) openPreview(src);
    });
    if(overlay){ overlay.addEventListener('click',function(e){ if(e.target===overlay || e.target.classList.contains('imgpv-close')) closePreview(); }); }
    document.addEventListener('keydown',function(e){ if(e.key==='Escape' && overlay && overlay.classList.contains('show')) closePreview(); });
  })();
</script>
