<?php
/********************************************************************
 * admin/dashboard/index.php
 * Dashboard V.Final (No Charts): Clean + High Utility + Full Info
 * - Removed: "ประเมินรายได้เดือนนี้"
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$pageTitle = "ภาพรวมระบบ";

// -------------------------
// Time helpers
// -------------------------
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// -------------------------
// 1) KPI (ตัวเลขที่ใช้จริง)
// -------------------------
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE DATE(created_at)=CURDATE()");
$stmt->execute();
$jobsToday = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE status NOT IN ('FN','DV','XX','RT')");
$stmt->execute();
$jobsActive = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE status='WC'");
$stmt->execute();
$waitConfirm = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE status='FN'");
$stmt->execute();
$readyPickup = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM warranty_claims WHERE result='pending'");
$stmt->execute();
$claimsPending = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM parts_new WHERE quantity <= min_stock AND min_stock > 0");
$stmt->execute();
$lowStockCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM tracking
  WHERE appointment_date IS NOT NULL AND DATE(appointment_date)=CURDATE()
");
$stmt->execute();
$apptToday = (int)$stmt->fetchColumn();

// งานค้างเกิน X วัน
$staleDays = 7;
$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM tracking
  WHERE status NOT IN ('FN','DV','XX','RT')
    AND created_at < (NOW() - INTERVAL :d DAY)
");
$stmt->bindValue(':d', $staleDays, PDO::PARAM_INT);
$stmt->execute();
$staleCount = (int)$stmt->fetchColumn();

// -------------------------
// 2) Lists / Tables
// -------------------------

// คิววันนี้/พรุ่งนี้
$stmt = $pdo->prepare("
  SELECT id, ticket_number, customer_name, customer_phone, device_model, status, appointment_date
  FROM tracking
  WHERE appointment_date IS NOT NULL
    AND DATE(appointment_date) IN (:d0, :d1)
  ORDER BY appointment_date ASC
  LIMIT 10
");
$stmt->execute([':d0'=>$today, ':d1'=>$tomorrow]);
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

// งานค้างเกิน X วัน (รายการ)
$stmt = $pdo->prepare("
  SELECT id, ticket_number, customer_name, device_model, status, created_at, estimated_cost
  FROM tracking
  WHERE status NOT IN ('FN','DV','XX','RT')
    AND created_at < (NOW() - INTERVAL :d DAY)
  ORDER BY created_at ASC
  LIMIT 8
");
$stmt->bindValue(':d', $staleDays, PDO::PARAM_INT);
$stmt->execute();
$staleJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// งานล่าสุด
$stmt = $pdo->prepare("
  SELECT id, ticket_number, customer_name, device_model, status, created_at, estimated_cost
  FROM tracking
  ORDER BY created_at DESC
  LIMIT 8
");
$stmt->execute();
$recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// อะไหล่ต้องเติม
$stmt = $pdo->prepare("
  SELECT part_code, part_name, quantity, min_stock
  FROM parts_new
  WHERE quantity <= min_stock AND min_stock > 0
  ORDER BY quantity ASC
  LIMIT 8
");
$stmt->execute();
$lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// เคลม pending ล่าสุด
$stmt = $pdo->prepare("
  SELECT claim_no, result, claim_date, job_id
  FROM warranty_claims
  WHERE result='pending'
  ORDER BY claim_date DESC
  LIMIT 8
");
$stmt->execute();
$pendingClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// Status badge map
// -------------------------
$statusMap = [
  'QS' => ['label'=>'รอเช็คราคา',   'bg'=>'#fff7ed', 'c'=>'#ea580c'],
  'WC' => ['label'=>'รอคอนเฟิร์ม',  'bg'=>'#eff6ff', 'c'=>'#2563eb'],
  'OK' => ['label'=>'กำลังซ่อม',     'bg'=>'#f0fdf4', 'c'=>'#16a34a'],
  'RW' => ['label'=>'งานแก้/เคลม',  'bg'=>'#fef2f2', 'c'=>'#dc2626'],
  'FN' => ['label'=>'ซ่อมเสร็จ',     'bg'=>'#ecfccb', 'c'=>'#65a30d'],
  'DV' => ['label'=>'ส่งมอบแล้ว',   'bg'=>'#f1f5f9', 'c'=>'#475569'],
  'XX' => ['label'=>'ยกเลิก',        'bg'=>'#fef2f2', 'c'=>'#ef4444'],
  'RT' => ['label'=>'รับคืนแล้ว',    'bg'=>'#f8fafc', 'c'=>'#64748b'],
];

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

<main class="main" id="main-content">

  <div class="topbar">
    <div class="tb-left">
      <h1><?= h($pageTitle) ?></h1>
      <div class="tb-sub">ดูแล้วรู้เลยว่าอะไรต้องทำก่อน ไม่ต้องดูกราฟให้รก</div>
    </div>
    <div class="user-welcome">
      สวัสดี, <strong><?= h($_SESSION['username'] ?? 'Admin') ?></strong>
    </div>
  </div>

  <!-- KPI (8 ใบ) -->
  <section class="kpi-grid">
    <div class="kpi kpi-blue">
      <div class="kpi-ic"><span class="material-symbols-rounded">today</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">งานเข้าวันนี้</div>
        <div class="kpi-value"><?= number_format($jobsToday) ?></div>
      </div>
    </div>

    <div class="kpi kpi-amber">
      <div class="kpi-ic"><span class="material-symbols-rounded">event</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">คิวนัดวันนี้</div>
        <div class="kpi-value"><?= number_format($apptToday) ?></div>
      </div>
    </div>

    <div class="kpi kpi-slate">
      <div class="kpi-ic"><span class="material-symbols-rounded">pending_actions</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">กำลังดำเนินการ</div>
        <div class="kpi-value"><?= number_format($jobsActive) ?></div>
      </div>
    </div>

    <div class="kpi kpi-blue">
      <div class="kpi-ic"><span class="material-symbols-rounded">mark_chat_unread</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">รอคอนเฟิร์ม</div>
        <div class="kpi-value"><?= number_format($waitConfirm) ?></div>
      </div>
    </div>

    <div class="kpi <?= $readyPickup>0 ? 'kpi-green' : 'kpi-slate' ?>">
      <div class="kpi-ic"><span class="material-symbols-rounded">task_alt</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">ซ่อมเสร็จรอรับ</div>
        <div class="kpi-value"><?= number_format($readyPickup) ?></div>
      </div>
    </div>

    <div class="kpi <?= $lowStockCount>0 ? 'kpi-red' : 'kpi-slate' ?>">
      <div class="kpi-ic"><span class="material-symbols-rounded">inventory_2</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">อะไหล่ต้องเติม</div>
        <div class="kpi-value"><?= number_format($lowStockCount) ?></div>
      </div>
    </div>

    <div class="kpi <?= $claimsPending>0 ? 'kpi-red' : 'kpi-slate' ?>">
      <div class="kpi-ic"><span class="material-symbols-rounded">gpp_maybe</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">เคลมรอตรวจสอบ</div>
        <div class="kpi-value"><?= number_format($claimsPending) ?></div>
      </div>
    </div>

    <div class="kpi <?= $staleCount>0 ? 'kpi-red' : 'kpi-slate' ?>">
      <div class="kpi-ic"><span class="material-symbols-rounded">schedule</span></div>
      <div class="kpi-meta">
        <div class="kpi-title">ค้างเกิน <?= (int)$staleDays ?> วัน</div>
        <div class="kpi-value"><?= number_format($staleCount) ?></div>
      </div>
    </div>
  </section>

  <!-- Main Grid -->
  <section class="dash-grid">
    <!-- Upcoming -->
    <div class="card">
      <div class="card-hd">
        <div class="card-title">
          <span class="material-symbols-rounded">event_available</span>
          คิววันนี้/พรุ่งนี้
        </div>
        <a class="card-link" href="../tracking/index.php">ดูทั้งหมด</a>
      </div>

      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>เวลา</th>
              <th>Job</th>
              <th>ลูกค้า</th>
              <th>รุ่น</th>
              <th>สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if($upcoming): foreach($upcoming as $r):
            $st = $r['status'] ?? 'QS';
            $stStyle = $statusMap[$st] ?? ['label'=>$st,'bg'=>'#f1f5f9','c'=>'#475569'];
          ?>
            <tr>
              <td class="mono"><?= h(date('d/m H:i', strtotime($r['appointment_date']))) ?></td>
              <td><a class="link" href="../tracking/edit.php?id=<?= (int)$r['id'] ?>"><?= h($r['ticket_number']) ?></a></td>
              <td><?= h($r['customer_name']) ?><div class="sub"><?= h($r['customer_phone'] ?? '') ?></div></td>
              <td class="muted"><?= h($r['device_model']) ?></td>
              <td><span class="badge" style="background:<?= $stStyle['bg'] ?>; color:<?= $stStyle['c'] ?>;"><?= h($stStyle['label']) ?></span></td>
              <td class="right">
                <a class="icon-link" href="../tracking/edit.php?id=<?= (int)$r['id'] ?>"><span class="material-symbols-rounded">edit_square</span></a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">ไม่มีคิววันนี้/พรุ่งนี้</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Stale jobs -->
    <div class="card card-warn">
      <div class="card-hd">
        <div class="card-title">
          <span class="material-symbols-rounded">schedule</span>
          งานค้างเกิน <?= (int)$staleDays ?> วัน
        </div>
        <a class="card-link" href="../tracking/index.php">ไปจัดการ</a>
      </div>

      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>Job</th>
              <th>ลูกค้า</th>
              <th>รุ่น</th>
              <th>อายุงาน</th>
              <th>สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if($staleJobs): foreach($staleJobs as $r):
            $st = $r['status'] ?? 'QS';
            $stStyle = $statusMap[$st] ?? ['label'=>$st,'bg'=>'#f1f5f9','c'=>'#475569'];
            $daysOld = max(0, (int)floor((time() - strtotime($r['created_at'])) / 86400));
          ?>
            <tr>
              <td><a class="link" href="../tracking/edit.php?id=<?= (int)$r['id'] ?>"><?= h($r['ticket_number']) ?></a></td>
              <td><?= h($r['customer_name']) ?></td>
              <td class="muted"><?= h($r['device_model']) ?></td>
              <td class="mono"><span class="pill"><?= $daysOld ?>d</span></td>
              <td><span class="badge" style="background:<?= $stStyle['bg'] ?>; color:<?= $stStyle['c'] ?>;"><?= h($stStyle['label']) ?></span></td>
              <td class="right">
                <a class="icon-link" href="../tracking/edit.php?id=<?= (int)$r['id'] ?>"><span class="material-symbols-rounded">edit_square</span></a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">ไม่มีงานค้างเกิน <?= (int)$staleDays ?> วัน</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent jobs -->
    <div class="card">
      <div class="card-hd">
        <div class="card-title">
          <span class="material-symbols-rounded">history</span>
          งานล่าสุด
        </div>
        <a class="card-link" href="../tracking/index.php">ดูทั้งหมด</a>
      </div>

      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>Job</th>
              <th>ลูกค้า</th>
              <th>รุ่น</th>
              <th>สถานะ</th>
              <th>วันที่</th>
              <th class="right">ประเมิน</th>
            </tr>
          </thead>
          <tbody>
          <?php if($recentJobs): foreach($recentJobs as $r):
            $st = $r['status'] ?? 'QS';
            $stStyle = $statusMap[$st] ?? ['label'=>$st,'bg'=>'#f1f5f9','c'=>'#475569'];
          ?>
            <tr>
              <td><a class="link" href="../tracking/edit.php?id=<?= (int)$r['id'] ?>"><?= h($r['ticket_number']) ?></a></td>
              <td><?= h($r['customer_name']) ?></td>
              <td class="muted"><?= h($r['device_model']) ?></td>
              <td><span class="badge" style="background:<?= $stStyle['bg'] ?>; color:<?= $stStyle['c'] ?>;"><?= h($stStyle['label']) ?></span></td>
              <td class="mono"><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></td>
              <td class="right mono">฿<?= number_format((float)$r['estimated_cost'], 0) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">ยังไม่มีข้อมูล</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Stock + Claims -->
    <div class="card">
      <div class="card-hd">
        <div class="card-title">
          <span class="material-symbols-rounded">inventory</span>
          แจ้งเตือนคลัง & เคลม
        </div>
        <div class="card-actions">
          <a class="btn" href="../tracking/create.php"><span class="material-symbols-rounded">add_circle</span> เปิดงานใหม่</a>
          <a class="btn btn-ghost" href="../parts/index.php"><span class="material-symbols-rounded">warehouse</span> คลัง</a>
        </div>
      </div>

      <div class="two-cols">
        <div class="subcard">
          <div class="subhd">
            <span class="material-symbols-rounded">warning</span> อะไหล่ต้องเติม
            <span class="tag <?= $lowStockCount>0 ? 'tag-red' : 'tag-slate' ?>"><?= number_format($lowStockCount) ?></span>
          </div>
          <div class="table-wrap">
            <table class="tbl tbl-compact">
              <thead>
                <tr>
                  <th>อะไหล่</th>
                  <th class="right">คงเหลือ</th>
                  <th class="right">ขั้นต่ำ</th>
                </tr>
              </thead>
              <tbody>
              <?php if($lowStockItems): foreach($lowStockItems as $p): ?>
                <tr>
                  <td>
                    <div class="strong"><?= h($p['part_name']) ?></div>
                    <div class="sub mono muted"><?= h($p['part_code']) ?></div>
                  </td>
                  <td class="right mono"><span class="pill danger"><?= (int)$p['quantity'] ?></span></td>
                  <td class="right mono"><?= (int)$p['min_stock'] ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="3" class="empty">สต็อกปกติ (หรือมึงยังไม่ตั้ง min_stock)</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="subcard">
          <div class="subhd">
            <span class="material-symbols-rounded">gpp_maybe</span> เคลม pending
            <span class="tag <?= $claimsPending>0 ? 'tag-red' : 'tag-slate' ?>"><?= number_format($claimsPending) ?></span>
          </div>

          <div class="table-wrap">
            <table class="tbl tbl-compact">
              <thead>
                <tr>
                  <th>Claim No.</th>
                  <th>วันที่</th>
                  <th class="right">ลิงก์</th>
                </tr>
              </thead>
              <tbody>
              <?php if($pendingClaims): foreach($pendingClaims as $c): ?>
                <tr>
                  <td class="mono strong"><?= h($c['claim_no'] ?? '-') ?></td>
                  <td class="mono"><?= h(!empty($c['claim_date']) ? date('d/m H:i', strtotime($c['claim_date'])) : '-') ?></td>
                  <td class="right">
                    <?php if(!empty($c['job_id'])): ?>
                      <a class="link" href="../warranty/edit.php?id=<?= (int)$c['job_id'] ?>">เปิด</a>
                    <?php else: ?>
                      <span class="muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="3" class="empty">ไม่มีเคลมค้าง</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="mini-actions">
            <a class="btn btn-ghost" href="../warranty/index.php"><span class="material-symbols-rounded">assignment</span> ไปหน้าประกัน</a>
          </div>
        </div>
      </div>
    </div>

  </section>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
