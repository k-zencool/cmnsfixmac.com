<?php

/********************************************************************
 * admin/parts/index.php
 * - Tab "new": รายการอะไหล่มือ 1 พร้อมปุ่ม เติมสต็อก / เบิก / แก้ไข
 * - Tab "history": ประวัติการเคลื่อนไหว (รับเข้า/เบิก/ย้าย/ปรับ) พร้อมชื่อผู้ทำรายการ
 * ใช้ตาราง:
 *   parts_new (คงเหลือจริง, UNIQUE(part_code, location))
 *   parts_docs (doc_id, doc_type, ref_no, remarks, user_id, created_at)
 *   parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
 *   admin_users (id, username, ...)
 ********************************************************************/

// ========================== [SETUP & GUARD] ==========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "จัดการอะไหล่";

// ========================== [HELPERS] ================================
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function getv($k, $d = null)
{
  return isset($_GET[$k]) ? trim($_GET[$k]) : $d;
}

function doc_label($t)
{
  switch ($t) {
    case 'IN':
      return 'รับเข้า';
    case 'CONSUME':
      return 'เบิก';
    case 'MOVE':
      return 'ย้าย';
    case 'ADJUST':
      return 'ปรับยอด';
    default:
      return $t;
  }
}
function qty_fmt($t, $q)
{
  $q = (int)$q;
  if ($t === 'IN') return '+' . $q;
  if ($t === 'CONSUME') return '-' . $q;
  return (string)$q;
}

// ========================== [STATE / FLASH] ==========================
$tab = getv('tab', 'new');
$q   = getv('q', ''); // ค้นหาในแท็บที่กำลังเปิด
$msg = '';
$err = '';

$devices = isset($_GET['device']) ? (array)$_GET['device'] : [];
$kinds   = isset($_GET['kind'])   ? (array)$_GET['kind']   : [];
$allowDevices = ['macbook', 'iphone', 'ipad', 'imac'];
$allowKinds   = ['screen', 'battery', 'keyboard', 'trackpad', 'speaker', 'camera', 'board', 'cable', 'fan', 'hinge', 'case'];
$devices = array_values(array_intersect($devices, $allowDevices));
$kinds   = array_values(array_intersect($kinds, $allowKinds));

// ========================== [LOAD DATA: TAB NEW] =====================
$parts = [];
if ($tab === 'new') {
  $params = [];
  $where  = [];

  if ($q !== '') {
    $where[] = "(part_name LIKE :q OR part_number LIKE :q OR device_models LIKE :q OR category LIKE :q)";
    $params[':q'] = "%{$q}%";
  }

  if (!empty($devices)) {
    $devOR = [];
    foreach ($devices as $i => $d) {
      $kw = $d === 'macbook' ? 'MacBook' : ($d === 'iphone' ? 'iPhone' : ($d === 'ipad' ? 'iPad' : 'iMac'));
      $ph = ":dv{$i}";
      $devOR[] = "(part_name LIKE {$ph} OR device_models LIKE {$ph} OR category LIKE {$ph})";
      $params[$ph] = "%{$kw}%";
    }
    $where[] = '(' . implode(' OR ', $devOR) . ')';
  }

  if (!empty($kinds)) {
    $map = [
      'screen' => ['จอ', 'screen', 'display', 'lcd'],
      'battery' => ['แบต', 'battery'],
      'keyboard' => ['คีย์บอร์ด', 'keyboard', 'kb'],
      'trackpad' => ['trackpad', 'ทัชแพด'],
      'speaker' => ['ลำโพง', 'speaker'],
      'camera' => ['กล้อง', 'camera'],
      'board' => ['board', 'logic', 'mainboard', 'เมนบอร์ด', 'บอร์ด'],
      'cable' => ['สาย', 'cable', 'flex'],
      'fan' => ['พัดลม', 'fan'],
      'hinge' => ['บานพับ', 'hinge'],
      'case' => ['ฝาหลัง', 'ฝา', 'case', 'top case', 'bottom']
    ];
    $orKind = [];
    $idx = 0;
    foreach ($kinds as $k) {
      if (!isset($map[$k])) continue;
      $likes = [];
      foreach ($map[$k] as $w) {
        $ph = ":k{$idx}";
        $likes[] = "part_name LIKE {$ph}";
        $params[$ph] = "%{$w}%";
        $idx++;
      }
      if ($likes) $orKind[] = '(' . implode(' OR ', $likes) . ')';
    }
    if ($orKind) $where[] = '(' . implode(' OR ', $orKind) . ')';
  }

  $sql = "
    SELECT
      part_code,
      MAX(part_name)     AS part_name,
      MAX(part_number)   AS part_number,
      MAX(device_models) AS device_models,
      MAX(category)      AS category,
      MAX(image_url)     AS image_url,
      MAX(min_stock)     AS min_stock,
      MAX(is_active)     AS is_active,
      SUM(quantity)      AS qty
    FROM parts_new
  ";
  if ($where) $sql .= " WHERE " . implode(' AND ', $where);
  $sql .= " GROUP BY part_code ORDER BY part_code DESC LIMIT 500";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $parts = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ========================== [LOAD DATA: TAB USED] =====================
$usedItems = [];
if ($tab === 'used') {
  $params = [];
  $where  = [];

  // ค้นหาด้วย q
  if ($q !== '') {
    $where[] = "(part_code LIKE :q OR part_name LIKE :q OR part_number LIKE :q OR device_models LIKE :q OR category LIKE :q OR serial_no LIKE :q OR remarks LIKE :q)";
    $params[':q'] = "%{$q}%";
  }

  // กรองอุปกรณ์
  if (!empty($devices)) {
    $devOR = [];
    foreach ($devices as $i => $d) {
      $kw = $d === 'macbook' ? 'MacBook' : ($d === 'iphone' ? 'iPhone' : ($d === 'ipad' ? 'iPad' : 'iMac'));
      $ph = ":udv{$i}";
      $devOR[] = "(part_name LIKE {$ph} OR device_models LIKE {$ph} OR category LIKE {$ph})";
      $params[$ph] = "%{$kw}%";
    }
    $where[] = '(' . implode(' OR ', $devOR) . ')';
  }

  // กรองชนิดอะไหล่จากชื่อ
  if (!empty($kinds)) {
    $map = [
      'screen' => ['จอ', 'screen', 'display', 'lcd'],
      'battery' => ['แบต', 'battery'],
      'keyboard' => ['คีย์บอร์ด', 'keyboard', 'kb'],
      'trackpad' => ['trackpad', 'ทัชแพด'],
      'speaker' => ['ลำโพง', 'speaker'],
      'camera' => ['กล้อง', 'camera'],
      'board' => ['board', 'logic', 'mainboard', 'เมนบอร์ด', 'บอร์ด'],
      'cable' => ['สาย', 'cable', 'flex'],
      'fan' => ['พัดลม', 'fan'],
      'hinge' => ['บานพับ', 'hinge'],
      'case' => ['ฝาหลัง', 'ฝา', 'case', 'top case', 'bottom']
    ];
    $orKind = [];
    $idx = 0;
    foreach ($kinds as $k) {
      if (!isset($map[$k])) continue;
      $likes = [];
      foreach ($map[$k] as $w) {
        $ph = ":uk{$idx}";
        $likes[] = "part_name LIKE {$ph}";
        $params[$ph] = "%{$w}%";
        $idx++;
      }
      if ($likes) $orKind[] = '(' . implode(' OR ', $likes) . ')';
    }
    if ($orKind) $where[] = '(' . implode(' OR ', $orKind) . ')';
  }

  // กรองสถานะชิ้น (optional)
  $status = getv('status', '');
  if (in_array($status, ['in_stock', 'reserved', 'consumed', 'defect'], true)) {
    $where[] = "status = :st";
    $params[':st'] = $status;
  }

  $sqlU = "
    SELECT
      id, part_code, part_name, part_number, device_models, category,
      image_url, serial_no, status, remarks, created_at, updated_at, is_active, min_stock
    FROM parts_used
  ";
  if ($where) $sqlU .= " WHERE " . implode(' AND ', $where);
  $sqlU .= " ORDER BY id DESC LIMIT 500";

  $ust = $pdo->prepare($sqlU);
  $ust->execute($params);
  $usedItems = $ust->fetchAll(PDO::FETCH_ASSOC);
}


// ==================== [LOAD DATA: TAB HISTORY] =======================
// ค้นหาประวัติด้วย q: ตรงนี้ให้กรอง part_code / ref_no / remarks / admin username
$historyRows = [];
if ($tab === 'history') {
  $params = [];
  $where = [];
  if ($q !== '') {
    $where[] = "(l.part_code LIKE :q OR d.ref_no LIKE :q OR d.remarks LIKE :q OR au.username LIKE :q)";
    $params[':q'] = "%{$q}%";
  }
  $doc_type = getv('doc_type', '');
  $date_from = getv('date_from', '');
  $date_to = getv('date_to', '');

  if ($doc_type !== '') {
    $where[] = "d.doc_type = :doc_type";
    $params[':doc_type'] = $doc_type;
  }

  if ($date_from !== '') {
    $where[] = "DATE(d.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
  }

  if ($date_to !== '') {
    $where[] = "DATE(d.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
  }

  $sqlH = "
    SELECT
      d.created_at,
      d.doc_type,
      d.ref_no,
      d.remarks,
      l.part_code,
      l.qty,
      l.location_from,
      l.location_to,
      l.unit_cost,
      au.username AS admin_name
    FROM parts_docs d
    JOIN parts_doc_lines l ON l.doc_id=d.doc_id
    LEFT JOIN admin_users au ON au.id=d.user_id
  ";
  if ($where) $sqlH .= " WHERE " . implode(' AND ', $where);
  $sqlH .= " ORDER BY d.doc_id DESC, l.line_id DESC LIMIT 200";

  $hst = $pdo->prepare($sqlH);
  $hst->execute($params);
  $historyRows = $hst->fetchAll(PDO::FETCH_ASSOC);
}

// ========================== [TEMPLATES] ===============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="view-switcher" style="margin-bottom:15px;">
    <a class="switcher-item <?= $tab === 'new' ? 'active' : '' ?>" href="index.php?tab=new">ของมือ 1</a>
    <a class="switcher-item <?= $tab === 'used' ? 'active' : '' ?>" href="index.php?tab=used">ของมือ 2</a>
    <a class="switcher-item <?= $tab === 'donor' ? 'active' : '' ?>" href="index.php?tab=donor">เครื่องซาก</a>
    <a class="switcher-item <?= $tab === 'history' ? 'active' : '' ?>" href="index.php?tab=history">ประวัติ</a>
  </div>

  <div class="section-header">
    <h2>
      <?php if ($tab === 'new'): ?>อะไหล่มือ 1
      <?php elseif ($tab === 'used'): ?>อะไหล่มือ 2
      <?php elseif ($tab === 'donor'): ?>เครื่องซาก
      <?php else: ?>ประวัติการเคลื่อนไหว<?php endif; ?>
    </h2>
    <?php if ($tab === 'new'): ?>
      <a href="form.php" class="btn-primary">+ เพิ่มชนิดอะไหล่ใหม่</a>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?><div class="alert alert-success">บันทึกชิ้นมือ 2 เรียบร้อย</div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>


  <?php if ($tab === 'new'): ?>
    <!-- ค้นหา/ตัวกรอง ของแท็บมือ 1 -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="new">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/เบอร์/รุ่น/หมวด...">

        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleFilterMenu()">ตัวกรอง</button>
          <div id="filterMenu" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">อุปกรณ์</div>
              <?php $deviceOpts = ['macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac'];
              foreach ($deviceOpts as $val => $label):
                $checked = in_array($val, $devices, true) ? 'checked' : '';
              ?>
                <label class="checkline">
                  <input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>>
                  <span><?= h($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="filter-section">
              <div class="filter-title">ชนิดอะไหล่</div>
              <?php $kindOpts = ['screen' => 'จอ/Screen', 'battery' => 'แบต/Battery', 'keyboard' => 'คีย์บอร์ด', 'trackpad' => 'Trackpad', 'speaker' => 'ลำโพง', 'camera' => 'กล้อง', 'board' => 'บอร์ด/Logic', 'cable' => 'สาย/Flex', 'fan' => 'พัดลม', 'hinge' => 'บานพับ', 'case' => 'ฝา/เคส'];
              foreach ($kindOpts as $val => $label):
                $checked = in_array($val, $kinds, true) ? 'checked' : '';
              ?>
                <label class="checkline">
                  <input type="checkbox" name="kind[]" value="<?= h($val) ?>" <?= $checked ?>>
                  <span><?= h($label) ?></span>
                </label>
              <?php endforeach; ?>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearFilterChecks()">ล้าง</button>
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
          <?php if ($parts): foreach ($parts as $i => $p): ?>
              <?php
              $qty = (int)$p['qty'];
              $min = (int)$p['min_stock'];
              $low = $min > 0 && $qty < $min;
              $img = !empty($p['image_url']) ? "../../uploads/parts/" . h($p['image_url']) : "";
              ?>
              <tr>
                <!-- ลำดับ -->
                <td><?= $i + 1 ?></td>

                <!-- รูป (คลิกเพื่อพรีวิว) -->
                <td>
                  <?php if ($img): ?>
                    <button type="button"
                      class="thumb-btn"
                      data-src="<?= $img ?>"
                      aria-label="ดูรูปใหญ่">
                      <img src="<?= $img ?>"
                        class="thumb"
                        style="width:48px;height:48px;object-fit:cover;border-radius:8px;"
                        alt="">
                    </button>
                  <?php else: ?>
                    <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;
                      display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">-</div>
                  <?php endif; ?>
                </td>

                <!-- ชื่ออะไหล่ -->
                <td>
                  <strong><?= h($p['part_name'] ?: $p['part_code']) ?></strong>
                  <?php if ((int)$p['is_active'] === 0): ?>
                    <div><span class="badge" style="background:#eee;color:#666;">ปิดใช้งาน</span></div>
                  <?php endif; ?>
                </td>

                <!-- เลขอะไหล่ -->
                <td class="muted"><?= h($p['part_number']) ?></td>

                <!-- ใช้กับรุ่น -->
                <td><?= h($p['device_models']) ?></td>

                <!-- หมวด -->
                <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>

                <!-- คงเหลือ -->
                <td>
                  <?php if ($low): ?>
                    <span class="badge" style="background:#ffe6e6;color:#b30000;" title="ต่ำกว่าขั้นต่ำ"><?= $qty ?></span>
                  <?php else: ?>
                    <?= $qty ?>
                  <?php endif; ?>
                </td>

                <!-- จัดการ -->
                <td class="no-wrap" style="min-width:220px;display:flex;gap:6px;flex-wrap:wrap;">
                  <a href="restock.php?part_code=<?= h($p['part_code']) ?>" class="btn-primary">เติมสต็อก</a>
                  <a href="consume.php?part_code=<?= h($p['part_code']) ?>" class="btn-secondary">เบิก</a>
                  <a href="form.php?part_code=<?= h($p['part_code']) ?>" class="btn-edit">แก้ไข</a>
                </td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="8" style="text-align:center;">ยังไม่มีข้อมูล</td>
            </tr>
          <?php endif; ?>
        </tbody>

      </table>
    </div>

  <?php elseif ($tab === 'history'): ?>
    <!-- ค้นหาในประวัติ -->
    <form action="index.php" method="get" class="search-and-filter-group">
      <input type="hidden" name="tab" value="history">

      <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาด้วย part_code / เลขอ้างอิง / หมายเหตุ / ผู้ทำรายการ">

      <!-- ตัวกรองประเภทเอกสาร -->
      <select name="doc_type" class="filter-input">
        <option value="">ทุกประเภท</option>
        <option value="IN" <?= getv('doc_type') === 'IN' ? 'selected' : '' ?>>รับเข้า</option>
        <option value="CONSUME" <?= getv('doc_type') === 'CONSUME' ? 'selected' : '' ?>>เบิก</option>
        <option value="MOVE" <?= getv('doc_type') === 'MOVE' ? 'selected' : '' ?>>ย้าย</option>
        <option value="ADJUST" <?= getv('doc_type') === 'ADJUST' ? 'selected' : '' ?>>ปรับยอด</option>
      </select>

      <!-- วันที่ -->
      <input type="date" name="date_from" value="<?= h(getv('date_from')) ?>">
      <input type="date" name="date_to" value="<?= h(getv('date_to')) ?>">

      <button class="btn-search">ค้นหา</button>
    </form>


    <div class="table-container" style="margin-top:12px;">
      <table class="data-table">
        <thead>
          <tr>
            <th>เวลา</th>
            <th>ประเภท</th>
            <th>Part Code</th>
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
                <td><?= h($r['part_code']) ?></td>
                <td style="font-weight:600;"><?= h(qty_fmt($r['doc_type'], $r['qty'])) ?></td>
                <td><?= h($r['location_from']) ?></td>
                <td><?= h($r['location_to']) ?></td>
                <td><?= $r['unit_cost'] !== null ? number_format($r['unit_cost'], 2) : '' ?></td>
                <td class="muted"><?= h($r['ref_no']) ?></td>
                <td><?= h($r['admin_name'] ?? 'N/A') ?></td>
                <td><?= h($r['remarks']) ?></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="10" style="text-align:center;">ยังไม่มีประวัติ</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab === 'used'): ?>
    <!-- ค้นหา/ตัวกรอง ของแท็บมือ 2 -->
    <form action="index.php" method="GET">
      <input type="hidden" name="tab" value="used">
      <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/เบอร์/รุ่น/หมวด/part code/serial/หมายเหตุ">

        <!-- เมนูตัวกรอง (ID แยกเฉพาะแท็บ used กันชนกับแท็บอื่น) -->
        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleFilterMenuUsed()">ตัวกรอง</button>
          <div id="filterMenuUsed" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">อุปกรณ์</div>
              <?php $deviceOpts = ['macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac'];
              foreach ($deviceOpts as $val => $label):
                $checked = in_array($val, $devices ?? [], true) ? 'checked' : ''; ?>
                <label class="checkline">
                  <input type="checkbox" name="device[]" value="<?= h($val) ?>" <?= $checked ?>>
                  <span><?= h($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="filter-section">
              <div class="filter-title">ชนิดอะไหล่</div>
              <?php $kindOpts = ['screen' => 'จอ/Screen', 'battery' => 'แบต/Battery', 'keyboard' => 'คีย์บอร์ด', 'trackpad' => 'Trackpad', 'speaker' => 'ลำโพง', 'camera' => 'กล้อง', 'board' => 'บอร์ด/Logic', 'cable' => 'สาย/Flex', 'fan' => 'พัดลม', 'hinge' => 'บานพับ', 'case' => 'ฝา/เคส'];
              foreach ($kindOpts as $val => $label):
                $checked = in_array($val, $kinds ?? [], true) ? 'checked' : ''; ?>
                <label class="checkline">
                  <input type="checkbox" name="kind[]" value="<?= h($val) ?>" <?= $checked ?>>
                  <span><?= h($label) ?></span>
                </label>
              <?php endforeach; ?>
              <div class="filter-actions">
                <button type="button" class="btn-secondary" onclick="clearFilterChecksUsed()">ล้าง</button>
                <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
              </div>
            </div>

            <div class="filter-section">
              <div class="filter-title">สถานะชิ้น</div>
              <?php $st = getv('status', ''); ?>
              <select name="status" class="filter-input">
                <option value="">ทุกสถานะ</option>
                <option value="in_stock" <?= $st === 'in_stock' ? 'selected' : '' ?>>คงอยู่</option>
                <option value="reserved" <?= $st === 'reserved' ? 'selected' : '' ?>>จอง</option>
                <option value="consumed" <?= $st === 'consumed' ? 'selected' : '' ?>>ตัดจ่าย</option>
                <option value="defect" <?= $st === 'defect'  ? 'selected' : '' ?>>ชำรุด</option>
              </select>
            </div>
          </div>
        </div>

        <button class="btn-search">ค้นหา</button>
      </div>
    </form>

    <div class="section-header" style="margin-top:10px;">
      <h3 style="margin:0;">รายการชิ้นมือ 2</h3>
      <a href="form_used.php" class="btn-primary">+ เพิ่มชิ้นมือ 2</a>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th style="width:56px;">รูป</th>
            <th>ชื่ออะไหล่</th>
            <th>Part code</th>
            <th>เลขอะไหล่</th>
            <th>รุ่น</th>
            <th>หมวด</th>
            <th>Serial</th>
            <th>สถานะ</th>
            <th>หมายเหตุ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($usedItems)): foreach ($usedItems as $i => $u):
              $imgPath = !empty($u['image_url']) ? "../../uploads/parts/" . h($u['image_url']) : "";
          ?>
              <tr>
                <td><?= $i + 1 ?></td>

                <!-- รูป + พรีวิว -->
                <td>
                  <?php if ($imgPath): ?>
                    <button type="button" class="thumb-btn" data-src="<?= $imgPath ?>" aria-label="ดูรูปใหญ่">
                      <img src="<?= $imgPath ?>" class="thumb" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt="">
                    </button>
                  <?php else: ?>
                    <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">-</div>
                  <?php endif; ?>
                </td>

                <!-- ข้อมูลหลัก -->
                <td>
                  <strong><?= h($u['part_name'] ?: $u['part_code']) ?></strong>
                  <?php if ((int)($u['is_active'] ?? 1) === 0): ?>
                    <div><span class="badge" style="background:#eee;color:#666;">ปิดใช้งาน</span></div>
                  <?php endif; ?>
                </td>
                <td class="muted"><?= h($u['part_code']) ?></td>
                <td class="muted"><?= h($u['part_number']) ?></td>
                <td><?= h($u['device_models']) ?></td>
                <td><span class="badge"><?= h($u['category'] ?: 'Other') ?></span></td>
                <td><?= h($u['serial_no']) ?></td>
                <td>
                  <?php $lab = ['in_stock' => 'คงอยู่', 'reserved' => 'จอง', 'consumed' => 'ตัดจ่าย', 'defect' => 'ชำรุด']; ?>
                  <span class="badge"><?= h($lab[$u['status']] ?? $u['status']) ?></span>
                </td>
                <td>
                  <?php if (trim((string)$u['remarks']) !== ''): ?>
                    <details>
                      <summary class="muted" style="cursor:pointer;">ดู</summary>
                      <div style="white-space:pre-wrap;max-width:320px;"><?= h($u['remarks']) ?></div>
                    </details>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>

                <!-- จัดการ: ใช้ POST สำหรับลบ ให้ลบได้จริง -->
                <td class="no-wrap" style="min-width:260px;display:flex;gap:6px;flex-wrap:wrap;">
                  <a class="btn-secondary" href="form_used.php?id=<?= (int)$u['id'] ?>&op=consume">เบิก/จ่าย</a>
                  <a class="btn-edit" href="form_used.php?id=<?= (int)$u['id'] ?>">แก้ไข</a>

                  <form action="form_used.php" method="post" onsubmit="return confirm('ลบชิ้นนี้ถาวร ใช่ไหม?')" style="display:inline;">
                    <input type="hidden" name="op" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn-delete">ลบ</button>
                  </form>

                </td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="11" style="text-align:center;">ยังไม่มีชิ้นมือ 2</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- JS ของเมนูตัวกรอง (แท็บ used) -->
    <script>
      function toggleFilterMenuUsed() {
        var m = document.getElementById('filterMenuUsed');
        if (m) m.classList.toggle('show');
      }
      document.addEventListener('click', function(e) {
        var wrap = e.target.closest ? e.target.closest('.filter-dropdown') : null;
        var menu = document.getElementById('filterMenuUsed');
        if (menu && !wrap) menu.classList.remove('show');
      });

      function clearFilterChecksUsed() {
        document.querySelectorAll('#filterMenuUsed input[type="checkbox"]').forEach(function(el) {
          el.checked = false;
        });
        var st = document.querySelector('#filterMenuUsed select[name="status"]');
        if (st) st.selectedIndex = 0;
      }
    </script>



  <?php elseif ($tab === 'donor'): ?>
    <div class="muted">หน้าเครื่องซาก (ยังว่าง)</div>
  <?php endif; ?>


  <!-- Image Preview Modal (ต้องมีในหน้า เพื่อให้ JS จับได้) -->
  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>


</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<!-- UI เล็กน้อยสำหรับ dropdown ตัวกรอง -->
<style>
  .filter-dropdown {
    position: relative;
    display: inline-block;
  }

  .filter-menu {
    display: none;
    position: absolute;
    right: 0;
    z-index: 20;
    min-width: 280px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 10px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .08);
  }

  .filter-menu.show {
    display: block;
  }

  .filter-section {
    padding: 8px 6px;
    border-top: 1px dashed #eee;
  }

  .filter-section:first-child {
    border-top: 0;
  }

  .filter-title {
    font-weight: 600;
    margin-bottom: 6px;
  }

  .checkline {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    cursor: pointer;
  }

  .filter-actions {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-top: 8px;
  }

  .thumb-btn {
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
  }

  .thumb-btn:focus {
    outline: 2px solid #99c;
    outline-offset: 2px;
  }

  .imgpv-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .65);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }

  .imgpv-overlay.show {
    display: flex;
  }

  .imgpv-dialog {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .imgpv-img {
    max-width: 90vw;
    max-height: 85vh;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .4);
    background: #fff;
  }

  .imgpv-close {
    position: absolute;
    top: -36px;
    right: -6px;
    border: 0;
    background: transparent;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
  }

  @media (max-width: 640px) {
    .imgpv-close {
      top: -44px;
      right: 0;
    }
  }
</style>
<script>
  function toggleFilterMenu() {
    var m = document.getElementById('filterMenu');
    if (m) m.classList.toggle('show');
  }
  document.addEventListener('click', function(e) {
    var wrap = e.target.closest ? e.target.closest('.filter-dropdown') : null;
    var menu = document.getElementById('filterMenu');
    if (menu && !wrap) menu.classList.remove('show');
  });

  function clearFilterChecks() {
    document.querySelectorAll('#filterMenu input[type="checkbox"]').forEach(function(el) {
      el.checked = false;
    });
  }
</script>

<script>
  (function() {
    var overlay = document.getElementById('imgPreviewOverlay');
    var imgEl = document.getElementById('imgPreview');
    var closeBtn;

    function openPreview(src) {
      if (!overlay || !imgEl) return;
      imgEl.src = src;
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden', 'false');
      closeBtn = overlay.querySelector('.imgpv-close');
      if (closeBtn) closeBtn.focus();
    }

    function closePreview() {
      if (!overlay) return;
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden', 'true');
      if (imgEl) imgEl.src = '';
    }

    // เปิดเมื่อกดรูป
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.thumb-btn');
      if (!btn) return;
      var src = btn.getAttribute('data-src');
      if (src) openPreview(src);
    });

    // ปิดเมื่อคลิกนอกภาพหรือกดปุ่มปิด
    if (overlay) {
      overlay.addEventListener('click', function(e) {
        if (e.target === overlay || e.target.classList.contains('imgpv-close')) {
          closePreview();
        }
      });
    }

    // ปิดด้วย ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) {
        closePreview();
      }
    });
  })();
</script>