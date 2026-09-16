<?php
/********************************************************************
 * admin/tracking/stickers.php  –  Repair-number sticker manager
 *
 * Pre-prints QR stickers (V5700, V5701 …) on A4 sticker paper so a
 * machine can be tagged the moment it arrives. The QR carries the ticket
 * number; scan/resolve.php opens the job, or offers to open a new one
 * when the number has not been used yet.
 *
 * Reading (number status, print history) is open to every role, like the
 * job list. Printing writes the history log, so it needs jobs.write.
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sticker_lib.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$canPrint  = can('jobs.write');
$perSheet  = stk_per_sheet();
$maxQty    = $perSheet * 10;   // 10 sheets per run is plenty; stops a typo printing 6,500 labels
$maxPerNo  = 10;               // copies of each number in a batch (one for the machine, one for the charger…)
$errorMsg  = '';
$warn      = [];               // overlap warnings that need a confirm click

$nextNo = stk_next_no($pdo);

/* ── POST: log the run, then hand off to the print sheet ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perms(['jobs.write']);

    $action  = $_POST['action'] ?? '';
    $slot    = max(1, min($perSheet, (int)($_POST['start_slot'] ?? 1)));
    $adminId = $_SESSION['admin_id'] ?? null;
    $adminNm = $_SESSION['admin_username'] ?? ($_SESSION['admin_name'] ?? null);

    if ($action === 'range') {
        $start = stk_parse_no((string)($_POST['start_no'] ?? ''));
        $count = (int)($_POST['count'] ?? 0);
        $perNo = (int)($_POST['per_no'] ?? 1);
        $qty   = $count * $perNo;

        if ($start === null || $start < 1) {
            $errorMsg = 'เลขเริ่มต้องเป็นรูปแบบ V ตามด้วยตัวเลข เช่น ' . stk_fmt($nextNo);
        } elseif ($perNo < 1 || $perNo > $maxPerNo) {
            $errorMsg = "ดวงต่อเลขต้องอยู่ระหว่าง 1–$maxPerNo";
        } elseif ($count < 1 || $qty > $maxQty) {
            $errorMsg = "รวมแล้วต้องไม่เกิน $maxQty ดวง (10 แผ่น)";
        } else {
            $end = $start + $count - 1;

            /* Printing the same number twice puts two identical stickers into
               circulation — possible on purpose (a spoiled sheet), so warn
               and ask, don't block. */
            if (empty($_POST['confirm'])) {
                $ov = $pdo->prepare("SELECT start_no, end_no, created_at FROM sticker_prints
                                     WHERE kind = 'range' AND start_no <= ? AND end_no >= ?
                                     ORDER BY id DESC LIMIT 5");
                $ov->execute([$end, $start]);
                foreach ($ov->fetchAll(PDO::FETCH_ASSOC) as $o) {
                    $warn[] = 'ช่วง ' . stk_fmt((int)$o['start_no']) . '–' . stk_fmt((int)$o['end_no'])
                            . ' เคยพิมพ์แล้วเมื่อ ' . date('d/m/y H:i', strtotime($o['created_at']));
                }
                $us = $pdo->prepare("SELECT COUNT(*) FROM tracking
                                     WHERE ticket_number REGEXP '^V[0-9]+$'
                                       AND CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) BETWEEN ? AND ?");
                $us->execute([$start, $end]);
                if (($usedCount = (int)$us->fetchColumn()) > 0) {
                    $warn[] = "มี $usedCount เลขในช่วงนี้ที่ถูกใช้เปิดงานไปแล้ว";
                }
            }

            if (!$warn) {
                $pdo->prepare("INSERT INTO sticker_prints (kind, start_no, end_no, per_no, qty, start_slot, admin_id, admin_name)
                               VALUES ('range', ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$start, $end, $perNo, $qty, $slot, $adminId, $adminNm]);
                header('Location: stickers_print.php?id=' . (int)$pdo->lastInsertId());
                exit;
            }
        }
    } elseif ($action === 'reprint') {
        $raw    = trim((string)($_POST['ticket'] ?? ''));
        $copies = max(1, min(10, (int)($_POST['copies'] ?? 1)));
        $n      = stk_parse_no($raw);
        $ticket = null;

        if ($n !== null) {
            $ticket = stk_fmt($n);             // "v5700" / "5700" → "V5700"
        } elseif ($raw !== '' && mb_strlen($raw) <= 50) {
            // Legacy free-form tickets ("V5508 (2)") only if a job really has it
            $chk = $pdo->prepare("SELECT ticket_number FROM tracking WHERE ticket_number = ? LIMIT 1");
            $chk->execute([$raw]);
            $ticket = $chk->fetchColumn() ?: null;
        }

        if ($ticket === null) {
            $errorMsg = 'ไม่พบเลขที่ซ่อม "' . $raw . '" — เลขใหม่ต้องเป็นรูปแบบ V ตามด้วยตัวเลข';
        } else {
            $pdo->prepare("INSERT INTO sticker_prints (kind, ticket, qty, start_slot, admin_id, admin_name)
                           VALUES ('reprint', ?, ?, ?, ?, ?)")
                ->execute([$ticket, $copies, $slot, $adminId, $adminNm]);
            header('Location: stickers_print.php?id=' . (int)$pdo->lastInsertId());
            exit;
        }
    }
}

/* ── Print runs (newest first) + which numbers of each batch are in use ── */
$history = [];
try {
    $history = $pdo->query("SELECT * FROM sticker_prints ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = $errorMsg ?: 'ยังไม่ได้รัน migration_sticker_prints.sql — ประวัติการพิมพ์ใช้งานไม่ได้';
}

$statusGroup = [
    'QS' => 'open', 'WC' => 'open', 'OK' => 'open', 'RW' => 'open',
    'FN' => 'done',
    'DV' => 'closed', 'NCF' => 'closed', 'NCS' => 'closed', 'XX' => 'closed', 'RT' => 'closed',
];
$groupLabel = ['open' => 'กำลังซ่อม', 'done' => 'เสร็จรอรับ', 'closed' => 'ปิดงานแล้ว'];

// jobs whose V-number falls inside any listed batch — one query for the whole list
$usedMap = [];
$lo = PHP_INT_MAX; $hi = 0;
foreach ($history as $hr) {
    if ($hr['kind'] !== 'range') continue;
    $lo = min($lo, (int)$hr['start_no']);
    $hi = max($hi, (int)$hr['end_no']);
}
if ($hi > 0) {
    $st = $pdo->prepare("SELECT id, ticket_number, status, customer_name,
                                CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) AS n
                         FROM tracking
                         WHERE ticket_number REGEXP '^V[0-9]+$'
                           AND CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) BETWEEN ? AND ?");
    $st->execute([$lo, $hi]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $usedMap[(int)$r['n']] = $r;
}

/* Free numbers in hand: printed in any batch, never opened as a job */
$loose = 0;
try {
    $rs = $pdo->query("SELECT start_no, end_no FROM sticker_prints WHERE kind = 'range'")->fetchAll(PDO::FETCH_ASSOC);
    if ($rs) {
        $printedAll = [];
        foreach ($rs as $r) {
            for ($i = (int)$r['start_no']; $i <= (int)$r['end_no']; $i++) $printedAll[$i] = true;
        }
        $u = $pdo->prepare("SELECT DISTINCT CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) FROM tracking
                            WHERE ticket_number REGEXP '^V[0-9]+$'
                              AND CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) BETWEEN ? AND ?");
        $u->execute([min(array_keys($printedAll)), max(array_keys($printedAll))]);
        foreach ($u->fetchAll(PDO::FETCH_COLUMN) as $n) unset($printedAll[(int)$n]);
        $loose = count($printedAll);
    }
} catch (PDOException $e) {}

/* ── Form values (sticky after a warning / error) ── */
$postAction = $_POST['action'] ?? '';
$tab     = ($postAction === 'reprint' || ($postAction === '' && isset($_GET['reprint']))) ? 'reprint' : 'range';
$fStart  = $_POST['start_no'] ?? stk_fmt($nextNo);
$fPerNo  = max(1, min($maxPerNo, (int)($_POST['per_no'] ?? 1)));
$fCount  = $_POST['count'] ?? intdiv($perSheet, $fPerNo);
$fTicket = $_POST['ticket'] ?? ($_GET['reprint'] ?? '');
$fCopies = $_POST['copies'] ?? 1;
$fSlot   = max(1, min($perSheet, (int)($_POST['start_slot'] ?? 1)));

$HIST_SHOW = 5;

$pageTitle = 'สติ๊กเกอร์เลขที่ซ่อม';
require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="assets/css/create-v3.css?v=<?= asset_ver('/admin/tracking/assets/css/create-v3.css') ?>">
<link rel="stylesheet" href="assets/css/stickers.css?v=<?= asset_ver('/admin/tracking/assets/css/stickers.css') ?>">

<div class="cr3-wrap stk-wrap">

    <div class="cr3-topbar">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> TRACKING
        </a>
        <h1 class="cr3-title">สติ๊กเกอร์เลขที่ซ่อม</h1>
        <div class="stk-stats">
            <span>เลขถัดไป <b class="stk-no is-primary"><?= stk_fmt($nextNo) ?></b></span>
            <span>เลขว่างในมือ <b><?= number_format($loose) ?></b></span>
        </div>
    </div>

    <?php if ($errorMsg): ?>
    <div class="cr3-alert">
        <span class="material-symbols-rounded">error</span> <?= h($errorMsg) ?>
    </div>
    <?php endif; ?>

    <div class="stk-stack">

      <?php if ($canPrint): ?>
      <!-- ── Print (one card, two modes) ── -->
      <section class="cr3-card stk-print" id="print">
          <header class="cr3-hd">
                  <div class="cr3-hd-txt">
                  <div class="cr3-hd-title">พิมพ์สติ๊กเกอร์</div>
              </div>
          </header>

          <form method="post" class="cr3-body stk-form" id="stkForm">
              <input type="hidden" name="action" value="<?= $tab ?>" data-role="action">

              <div class="stk-tabs" role="tablist">
                  <button type="button" role="tab" data-tab="range" aria-selected="<?= $tab === 'range' ? 'true' : 'false' ?>">
                      <span class="material-symbols-rounded">library_add</span> ชุดใหม่
                  </button>
                  <button type="button" role="tab" data-tab="reprint" aria-selected="<?= $tab === 'reprint' ? 'true' : 'false' ?>">
                      <span class="material-symbols-rounded">replay</span> พิมพ์ซ้ำ
                  </button>
              </div>

              <!-- new batch -->
              <fieldset class="stk-panel" data-panel="range" <?= $tab === 'range' ? '' : 'hidden disabled' ?>>
                  <?php if ($warn): ?>
                  <div class="stk-warn">
                      <span class="material-symbols-rounded">warning</span>
                      <div>
                          <b>เลขชุดนี้ซ้ำกับของเดิม</b>
                          <ul><?php foreach ($warn as $w): ?><li><?= h($w) ?></li><?php endforeach; ?></ul>
                          <small>ตั้งใจพิมพ์ซ้ำ (เช่น แผ่นเก่าเสีย) กด “ยืนยันพิมพ์” ได้เลย</small>
                      </div>
                  </div>
                  <input type="hidden" name="confirm" value="1">
                  <?php endif; ?>

                  <div class="stk-row2">
                      <div class="cr3-field">
                          <label class="cr3-label" for="stkStart">เลขเริ่ม</label>
                          <input type="text" id="stkStart" name="start_no" class="cr3-input stk-no" value="<?= h($fStart) ?>"
                                 required autocomplete="off" autocapitalize="characters" data-role="start">
                      </div>
                      <div class="cr3-field">
                          <label class="cr3-label" for="stkCount">จำนวนเลข</label>
                          <input type="number" id="stkCount" name="count" class="cr3-input" value="<?= (int)$fCount ?>"
                                 min="1" max="<?= $maxQty ?>" required inputmode="numeric" data-role="count">
                      </div>
                  </div>

                  <div class="cr3-field">
                      <span class="cr3-label" id="stkPerLbl">ดวงต่อเลข</span>
                      <div class="stk-per" role="radiogroup" aria-labelledby="stkPerLbl">
                          <?php foreach ([1, 2, 3, 4] as $pn): ?>
                          <label class="stk-per-opt">
                              <input type="radio" name="per_no" value="<?= $pn ?>" <?= $fPerNo === $pn ? 'checked' : '' ?>>
                              <span><?= $pn ?></span>
                          </label>
                          <?php endforeach; ?>
                      </div>
                  </div>

                  <div class="stk-quick" aria-label="เต็มแผ่น">
                      <span class="stk-dim">เต็ม</span>
                      <?php foreach ([1, 2, 3, 5] as $sh): ?>
                      <button type="button" data-sheets="<?= $sh ?>"><?= $sh ?> แผ่น</button>
                      <?php endforeach; ?>
                  </div>
              </fieldset>

              <!-- reprint one number -->
              <fieldset class="stk-panel" data-panel="reprint" <?= $tab === 'reprint' ? '' : 'hidden disabled' ?>>
                  <p class="stk-note">สติ๊กเกอร์หาย ขาด หรือลอก — พิมพ์เลขเดิมซ้ำได้ รวมถึงเลขเก่าที่มีงานอยู่แล้ว</p>
                  <div class="stk-row2">
                      <div class="cr3-field">
                          <label class="cr3-label" for="stkTicket">เลขที่ซ่อม</label>
                          <input type="text" id="stkTicket" name="ticket" class="cr3-input stk-no" value="<?= h($fTicket) ?>"
                                 placeholder="V5700" required autocomplete="off" autocapitalize="characters" data-role="start">
                      </div>
                      <div class="cr3-field">
                          <label class="cr3-label" for="stkCopies">จำนวนดวง</label>
                          <input type="number" id="stkCopies" name="copies" class="cr3-input" value="<?= (int)$fCopies ?>"
                                 min="1" max="10" required inputmode="numeric" data-role="count">
                      </div>
                  </div>
              </fieldset>

              <!-- start slot: shared by both modes -->
              <input type="hidden" name="start_slot" value="<?= $fSlot ?>" data-role="slot">
              <details class="stk-slotbox" data-role="slotbox" <?= $fSlot > 1 ? 'open' : '' ?>>
                  <summary>
                      <span class="material-symbols-rounded">grid_on</span>
                      <span class="stk-slotbox-txt">เริ่มพิมพ์ที่ดวง <b data-role="slot-label"><?= $fSlot ?></b>
                          <small data-role="slot-hint"><?= $fSlot > 1 ? 'ใช้แผ่นที่เหลือ' : 'แผ่นใหม่' ?></small></span>
                      <span class="stk-slotbox-chev material-symbols-rounded">expand_more</span>
                  </summary>
                  <div class="stk-slotbox-body">
                      <p class="stk-note">แผ่นเก่าใช้ไปแล้วบางส่วน? แตะดวงแรกที่ยังว่าง</p>
                      <div class="stk-sheet" data-role="sheet" aria-label="เลือกดวงเริ่มพิมพ์"></div>
                      <button type="button" class="stk-link" data-role="slot-reset">
                          <span class="material-symbols-rounded">restart_alt</span> เริ่มแผ่นใหม่ (ดวงที่ 1)
                      </button>
                  </div>
              </details>

              <div class="stk-foot">
                  <div class="stk-summary" data-role="summary" aria-live="polite"></div>
                  <button type="submit" class="cr3-btn cr3-btn-save stk-submit">
                      <span class="material-symbols-rounded">print</span>
                      <span data-role="submit-label"><?= $warn ? 'ยืนยันพิมพ์' : 'สร้างแผ่นพิมพ์' ?></span>
                  </button>
              </div>
          </form>
      </section>
      <?php endif; ?>

      <!-- ── Printed batches: usage per batch, numbers on demand ── -->
      <section class="cr3-card stk-batches" id="batches">
          <header class="cr3-hd">
                  <div class="cr3-hd-txt">
                  <div class="cr3-hd-title">ชุดที่พิมพ์</div>
              </div>
          </header>
          <div class="stk-hist">
              <?php if (!$history): ?>
              <p class="stk-empty">ยังไม่เคยพิมพ์สติ๊กเกอร์</p>
              <?php endif; ?>
              <?php foreach ($history as $idx => $hr):
                  $isRange = $hr['kind'] === 'range';
                  $perNo   = (int)($hr['per_no'] ?? 1);
                  $tip = ($perNo > 1 ? 'เลขละ ' . $perNo . ' ดวง · ' : '')
                       . 'โดย ' . ($hr['admin_name'] ?: '—') . ' · ' . date('d/m/y H:i', strtotime($hr['created_at']));
                  $more = $idx >= $HIST_SHOW ? 'data-more hidden' : '';
                  $printBtn = $canPrint
                      ? '<a class="stk-hist-open" href="stickers_print.php?id=' . (int)$hr['id'] . '" title="เปิดแผ่นนี้อีกครั้ง" aria-label="เปิดแผ่นนี้อีกครั้ง"><span class="material-symbols-rounded">print</span></a>'
                      : '';
                  $date = date('d/m', strtotime($hr['created_at']));

                  if (!$isRange): ?>
              <div class="stk-batch" <?= $more ?>>
                  <div class="stk-batch-row" title="<?= h('พิมพ์ซ้ำ · ' . $tip) ?>">
                      <span class="stk-batch-ico material-symbols-rounded">replay</span>
                      <span class="stk-batch-no stk-no"><?= h($hr['ticket']) ?></span>
                      <span class="stk-batch-use">พิมพ์ซ้ำ <?= (int)$hr['qty'] ?> ดวง</span>
                      <span class="stk-batch-date"><?= $date ?></span>
                      <?= $printBtn ?>
                  </div>
              </div>
                  <?php continue; endif;

                  $a = (int)$hr['start_no']; $b = (int)$hr['end_no'];
                  $total = $b - $a + 1;
                  $used  = 0;
                  for ($i = $a; $i <= $b; $i++) if (isset($usedMap[$i])) $used++;
                  $pct = $total ? round($used / $total * 100) : 0;
              ?>
              <details class="stk-batch" <?= $more ?>>
                  <summary class="stk-batch-row" title="<?= h($tip) ?>">
                      <span class="stk-batch-ico material-symbols-rounded">chevron_right</span>
                      <span class="stk-batch-no stk-no"><?= stk_fmt($a) ?>–<?= stk_fmt($b) ?></span>
                      <span class="stk-batch-use">
                          <span class="stk-meter"><i style="width:<?= $pct ?>%"></i></span>
                          <span class="stk-batch-count">ใช้ <b><?= $used ?></b>/<?= $total ?></span>
                      </span>
                      <span class="stk-batch-date"><?= $date ?></span>
                      <?= $printBtn ?>
                  </summary>
                  <div class="stk-nums">
                      <?php for ($i = $a; $i <= $b; $i++):
                          $no = stk_fmt($i);
                          if (isset($usedMap[$i])):
                              $u    = $usedMap[$i];
                              $grp  = $statusGroup[$u['status']] ?? 'open';
                              $href = $canPrint ? 'edit.php?id=' . (int)$u['id']
                                                : 'index.php?q=' . urlencode($u['ticket_number']) . '&open=' . (int)$u['id'];
                      ?>
                      <a class="stk-num is-used" href="<?= h($href) ?>" title="<?= h($no . ' · ' . $u['customer_name'] . ' · ' . $groupLabel[$grp]) ?>"><?= $no ?></a>
                      <?php elseif ($canPrint): ?>
                      <a class="stk-num" href="create.php?ticket=<?= urlencode($no) ?>" title="<?= $no ?> ว่าง — แตะเพื่อเปิดงาน"><?= $no ?></a>
                      <?php else: ?>
                      <span class="stk-num"><?= $no ?></span>
                      <?php endif; endfor; ?>
                  </div>
              </details>
              <?php endforeach; ?>
              <?php if (count($history) > $HIST_SHOW): ?>
              <button type="button" class="stk-more" data-role="hist-more">ดูทั้งหมด</button>
              <?php endif; ?>
          </div>
      </section>
    </div>

</div>

<script>
/* batch list: "show all" — without JS the first rows still show */
(function () {
    var more = document.querySelector('[data-role="hist-more"]');
    if (more) more.addEventListener('click', function () {
        document.querySelectorAll('.stk-batch[data-more]').forEach(function (r) { r.hidden = false; });
        more.remove();
    });
})();
</script>

<?php if ($canPrint): ?>
<script>
/* Print card: mode tabs + slot picker + live summary. The mini sheet mirrors
   the real 5 × 13 layout: dashed = skipped (already peeled off), tinted =
   what this run prints on the first sheet. */
(function () {
    var PER = <?= $perSheet ?>, COLS = <?= stk_sheet()['cols'] ?>;
    var RATIO = '<?= stk_sheet()['w'] ?> / <?= stk_sheet()['h'] ?>';
    var form    = document.getElementById('stkForm');
    var actionIn = form.querySelector('[data-role="action"]');
    var slotIn   = form.querySelector('[data-role="slot"]');
    var slotLbl  = form.querySelector('[data-role="slot-label"]');
    var slotHint = form.querySelector('[data-role="slot-hint"]');
    var sheet    = form.querySelector('[data-role="sheet"]');
    var summary  = form.querySelector('[data-role="summary"]');
    var tabs     = form.querySelectorAll('[data-tab]');
    var panels   = form.querySelectorAll('[data-panel]');
    var cells    = [];

    function parseNo(s) {
        var m = /^V?(\d{1,7})$/i.exec((s || '').trim());
        return m ? parseInt(m[1], 10) : null;
    }
    function panel() { return form.querySelector('[data-panel="' + actionIn.value + '"]'); }

    for (var i = 1; i <= PER; i++) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'stk-slot';
        b.dataset.slot = i;
        b.title = 'ดวงที่ ' + i;
        b.style.aspectRatio = RATIO;
        sheet.appendChild(b);
        cells.push(b);
    }
    sheet.style.gridTemplateColumns = 'repeat(' + COLS + ', 1fr)';


    function paint() {
        var p     = panel();
        var slot  = parseInt(slotIn.value, 10) || 1;
        var count = Math.max(0, parseInt(p.querySelector('[data-role="count"]').value, 10) || 0);
        var perNo = actionIn.value === 'range' ? perNow() : 1;
        var qty   = count * perNo;
        var raw   = p.querySelector('[data-role="start"]').value.trim();

        slotLbl.textContent = slot;
        slotHint.textContent = slot > 1 ? 'ใช้แผ่นที่เหลือ' : 'แผ่นใหม่';
        cells.forEach(function (c, idx) {
            var n = idx + 1;
            c.classList.toggle('is-skip', n < slot);
            c.classList.toggle('is-fill', n >= slot && n < slot + qty);
            c.classList.toggle('is-start', n === slot);
        });

        summary.textContent = '';
        if (!raw || qty < 1) return;
        var head = document.createElement('b');
        if (actionIn.value === 'range') {
            var start = parseNo(raw);
            if (start === null) { summary.textContent = 'เลขเริ่มต้องเป็น V ตามด้วยตัวเลข'; return; }
            head.textContent = 'V' + start + (count > 1 ? ' – V' + (start + count - 1) : '');
        } else {
            var n = parseNo(raw);
            head.textContent = n !== null ? 'V' + n : raw;
        }
        var sheets = Math.ceil((slot - 1 + qty) / PER);
        var meta = document.createElement('span');
        meta.textContent = (perNo > 1 ? count + ' เลข × ' + perNo + ' = ' : '') + qty + ' ดวง · ' + sheets + ' แผ่น';
        summary.appendChild(head);
        summary.appendChild(meta);
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            actionIn.value = t.dataset.tab;
            tabs.forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
            panels.forEach(function (p) {
                var on = p.dataset.panel === t.dataset.tab;
                p.hidden = !on;
                p.disabled = !on;   // disabled fieldset: skipped by validation and not posted
            });
            paint();
        });
    });
    sheet.addEventListener('click', function (e) {
        var c = e.target.closest('.stk-slot');
        if (!c) return;
        slotIn.value = c.dataset.slot;
        paint();
    });
    form.querySelector('[data-role="slot-reset"]').addEventListener('click', function () {
        slotIn.value = 1;
        paint();
    });
    function perNow() {
        var r = form.querySelector('input[name="per_no"]:checked');
        return r ? parseInt(r.value, 10) : 1;
    }
    // "full N sheets": as many numbers as fit, given the copies per number
    form.querySelectorAll('[data-sheets]').forEach(function (q) {
        q.addEventListener('click', function () {
            form.querySelector('#stkCount').value = Math.max(1, Math.floor(q.dataset.sheets * PER / perNow()));
            paint();
        });
    });
    form.addEventListener('input', paint);
    paint();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
