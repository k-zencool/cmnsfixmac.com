<?php

/********************************************************************
 * admin/dashboard/index.php — Dashboard: ภาพรวม + Low-Stock ด้านบน
 * ใครล็อกอินได้ก็ดูได้ ไม่เช็ค permission เพิ่ม
 *
 * Schema ที่อ้างอิง:
 * - parts_new(part_code, part_name, device_models, quantity, min_stock, image_url, category, location)
 * - parts_used_log(part_code, part_name, action, consumed_at, created_at)  // ไม่มี qty
 * - parts_doc_lines(doc_id, part_code, qty)
 * - parts_docs(doc_id, doc_type, created_at)
 * - repairs(created_at)
 * - warranty_jobs(..., warranty_until, warranty_status, warranty_no, customer_name, device_model)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "Dashboard | ภาพรวม";

/* ---------------- Helpers ---------------- */
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function kpi($pdo, string $sql, array $params = [])
{
  try {
    if (!($pdo instanceof PDO)) return '-';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    if ($v === false || $v === null) return '-';
    return is_numeric($v) ? number_format((float)$v) : (string)$v;
  } catch (Throwable $e) {
    return '-';
  }
}

function knum($pdo, string $sql, array $params = []): int
{
  try {
    if (!($pdo instanceof PDO)) return 0;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)($st->fetchColumn() ?: 0);
  } catch (Throwable $e) {
    return 0;
  }
}

function qrows($pdo, string $sql, array $params = []): array
{
  try {
    if (!($pdo instanceof PDO)) return [];
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    return [];
  }
}

function th_mon_short($n)
{
  static $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  return $m[(int)$n] ?? (string)$n;
}

function last12_months(): array
{
  $out = [];
  $d = new DateTime('first day of this month');
  $d->modify('-11 months');
  for ($i = 0; $i < 12; $i++) {
    $out[] = [$d->format('Y-m'), th_mon_short((int)$d->format('n')) . ' ' . $d->format('Y')];
    $d->modify('+1 month');
  }
  return $out;
}

/* ---------------- KPIs ---------------- */
$kpi_total_warranty   = kpi($pdo, "SELECT COUNT(*) FROM warranty_jobs");
$kpi_active_warranty  = kpi($pdo, "SELECT COUNT(*) FROM warranty_jobs WHERE warranty_status='in_warranty' OR (warranty_until IS NOT NULL AND warranty_until>=CURDATE())");
$kpi_expired_warranty = kpi($pdo, "SELECT COUNT(*) FROM warranty_jobs WHERE warranty_status='expired' OR (warranty_until IS NOT NULL AND warranty_until<CURDATE())");
$kpi_parts_types      = kpi($pdo, "SELECT COUNT(*) FROM parts_new");
$kpi_parts_sum        = kpi($pdo, "SELECT SUM(quantity) FROM parts_new");
$kpi_parts_low        = kpi($pdo, "SELECT COUNT(*) FROM parts_new WHERE min_stock>0 AND quantity<min_stock");

/* Low-stock counts */
$low_total = knum($pdo, "SELECT COUNT(*) FROM parts_new WHERE min_stock>0 AND quantity<min_stock");
$low_zero  = knum($pdo, "SELECT COUNT(*) FROM parts_new WHERE min_stock>0 AND quantity<=0");

/* Low-stock list (เรียงขาดหนักก่อน) */
$lowRows = qrows($pdo, "
  SELECT
    part_code, part_name, device_models, quantity, min_stock,
    image_url, category,
    (min_stock - quantity) AS deficit,
    CASE WHEN min_stock>0 THEN ROUND(GREATEST(0, LEAST(100, (quantity/min_stock)*100))) ELSE 0 END AS pct
  FROM parts_new
  WHERE min_stock>0 AND quantity<min_stock
  ORDER BY (min_stock - quantity) DESC, quantity ASC, part_name ASC
  LIMIT 200
");

/* ---------------- Chart #1: อะไหล่ที่ใช้บ่อยสุด (ชื่ออะไหล่) ---------------- */
$partsUsage = qrows($pdo, "
  SELECT
    COALESCE(NULLIF(pul.part_name,''), pn.part_name, 'ไม่ทราบชื่อ') AS label,
    COUNT(*) AS used_qty
  FROM parts_used_log pul
  LEFT JOIN parts_new pn ON pn.part_code = pul.part_code
  WHERE pul.action = 'CONSUME'
    AND COALESCE(pul.consumed_at, pul.created_at) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY label
  ORDER BY used_qty DESC
  LIMIT 10
");
if (!$partsUsage) {
  $partsUsage = qrows($pdo, "
    SELECT
      COALESCE(pn.part_name, 'ไม่ทราบชื่อ') AS label,
      SUM(
        CASE
          WHEN pd.doc_type='CONSUME' THEN ABS(pdl.qty)
          WHEN pd.doc_type='ADJUST' AND pdl.qty<0 THEN ABS(pdl.qty)
          ELSE 0
        END
      ) AS used_qty
    FROM parts_doc_lines pdl
    JOIN parts_docs pd ON pd.doc_id = pdl.doc_id
    LEFT JOIN parts_new pn ON pn.part_code = pdl.part_code
    WHERE pd.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY label
    HAVING used_qty > 0
    ORDER BY used_qty DESC
    LIMIT 10
  ");
}
$usage_labels = [];
$usage_data = [];
foreach ($partsUsage as $r) {
  $n = trim((string)($r['label'] ?? ''));
  $q = (float)($r['used_qty'] ?? 0);
  if ($n !== '' && $q > 0) {
    $usage_labels[] = $n;
    $usage_data[] = $q;
  }
}

/* ---------------- Chart #2: งานซ่อมรายเดือน 12 เดือน ---------------- */
$repRows = qrows($pdo, "
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
  FROM repairs
  WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
  GROUP BY ym
  ORDER BY ym
");
$repMap = [];
foreach ($repRows as $r) {
  $repMap[$r['ym']] = (int)$r['cnt'];
}
$month_pairs = last12_months();
$month_labels = [];
$month_data = [];
foreach ($month_pairs as [$ym, $label]) {
  $month_labels[] = $label;
  $month_data[] = $repMap[$ym] ?? 0;
}

/* JSON */
$JS_PARTS_LABELS = json_encode($usage_labels, JSON_UNESCAPED_UNICODE);
$JS_PARTS_DATA   = json_encode($usage_data);
$JS_MONTH_LABELS = json_encode($month_labels, JSON_UNESCAPED_UNICODE);
$JS_MONTH_DATA   = json_encode($month_data);
$JS_LOW_ROWS     = json_encode($lowRows, JSON_UNESCAPED_UNICODE);

/* ตารางแจ้งเตือน/ข้อมูลประกอบ */
$tblWarrantySoon = qrows($pdo, "
  SELECT warranty_no, customer_name, device_model,
         DATEDIFF(warranty_until, CURDATE()) AS days_left,
         DATE_FORMAT(warranty_until, '%Y-%m-%d') AS until_date
  FROM warranty_jobs
  WHERE warranty_until IS NOT NULL AND DATEDIFF(warranty_until, CURDATE()) BETWEEN 0 AND 30
  ORDER BY DATEDIFF(warranty_until, CURDATE()) ASC
  LIMIT 10
");

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span class="topbar-title"><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank" rel="noopener">ดูเว็บไซต์</a>
  </div>

  <section class="section">
    <div class="lowbar">
      <div class="lowbar-left">
        <div class="lowbar-title">อะไหล่ใกล้หมด</div>
        <div class="lowbar-stats">
          <span class="chip warn">ต่ำกว่า min: <b><?= (int)$low_total ?></b></span>
          <span class="chip danger">หมดสต็อก: <b><?= (int)$low_zero ?></b></span>
        </div>
      </div>
      <div class="lowbar-right">
        <input id="low-search" class="low-search" type="search" placeholder="ค้นหาชื่อ/รุ่น/รหัส..." />
        <button class="btn ghost" id="btn-toggle-view" data-mode="cards">สลับเป็นตาราง</button>
        <button class="btn ghost" id="btn-export">Export CSV</button>

        <a class="btn ghost" href="../reports/print_all_parts.php" target="_blank" rel="noopener" title="พิมพ์รายงานสต็อกทั้งหมด">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <path d="M12 12H6v10h12V12h-6z" />
          </svg>
          พิมพ์สต็อก
        </a>

        <a class="btn primary" href="../parts/index.php?tab=new&q=">ดูทั้งหมด</a>
      </div>
    </div>

    <div id="low-cards" class="low-grid"></div>

    <div id="low-table-wrap" class="panel" style="display:none">
      <div class="panel-h">อะไหล่ใกล้หมด (ตาราง)</div>
      <div class="panel-b table-wrap">
        <table id="low-table" class="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>รูป</th>
              <th>ชื่ออะไหล่</th>
              <th>รุ่นที่ใช้ได้</th>
              <th>คงเหลือ</th>
              <th>min</th>
              <th>ขาด</th>
              <th>รหัส</th>
              <th>หมวด</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="grid kpi-grid">
      <div class="card">
        <div class="card-k"><?= h($kpi_total_warranty) ?></div>
        <div class="card-t">งานประกันทั้งหมด</div>
      </div>
      <div class="card">
        <div class="card-k"><?= h($kpi_active_warranty) ?></div>
        <div class="card-t">อยู่ในประกัน</div>
      </div>
      <div class="card">
        <div class="card-k"><?= h($kpi_expired_warranty) ?></div>
        <div class="card-t">หมดประกัน</div>
      </div>
      <div class="card">
        <div class="card-k"><?= h($kpi_parts_types) ?></div>
        <div class="card-t">ชนิดอะไหล่ใหม่</div>
      </div>
      <div class="card">
        <div class="card-k"><?= h($kpi_parts_sum) ?></div>
        <div class="card-t">สต็อกรวม (ชิ้น)</div>
      </div>
      <div class="card">
        <div class="card-k"><?= h($kpi_parts_low) ?></div>
        <div class="card-t">ต่ำกว่า min</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h">อะไหล่ที่ใช้บ่อยสุด (12 เดือนหลัง)</div>
      <div class="panel-b chart-wrap">
        <canvas id="chartPartsUsage" height="200"></canvas>
        <?php if (empty($usage_labels)): ?>
          <div class="chart-empty">ยังไม่มีข้อมูลการใช้อะไหล่ใน 12 เดือน</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h">งานซ่อมรายเดือน (12 เดือนหลัง)</div>
      <div class="panel-b chart-wrap">
        <canvas id="chartRepairsMonthly" height="200"></canvas>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h">งานประกันใกล้หมด (เร็วสุดขึ้นก่อน)</div>
      <div class="panel-b table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>เลขประกัน</th>
              <th>ชื่อลูกค้า</th>
              <th>รุ่น</th>
              <th>เหลือ (วัน)</th>
              <th>ถึงวันที่</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$tblWarrantySoon): ?>
              <tr>
                <td colspan="6" class="muted">ไม่มีข้อมูลหรือไม่พบตาราง warranty_jobs</td>
              </tr>
              <?php else: foreach ($tblWarrantySoon as $i => $r): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= h($r['warranty_no'] ?? '-') ?></td>
                  <td><?= h($r['customer_name'] ?? '-') ?></td>
                  <td><?= h($r['device_model'] ?? '-') ?></td>
                  <td><?= h($r['days_left'] ?? '-') ?></td>
                  <td><?= h($r['until_date'] ?? '-') ?></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    /* ---------- Low-stock render ---------- */
    const rows = <?= $JS_LOW_ROWS ?>; // [{part_code,part_name,device_models,quantity,min_stock,image_url,category,deficit,pct}]
    const grid = document.getElementById('low-cards');
    const tblWrap = document.getElementById('low-table-wrap');
    const tblBody = document.querySelector('#low-table tbody');
    const btnToggle = document.getElementById('btn-toggle-view');
    const btnExport = document.getElementById('btn-export');
    const search = document.getElementById('low-search');

    function esc(s) {
      return (s ?? '').toString();
    }

    function toHrefQuery(s) {
      return encodeURIComponent(s ?? '');
    }

    function imgSrc(v) {
      v = (v ?? '').toString().trim();
      if (!v) return '';
      if (/^https?:\/\//i.test(v)) return v;
      if (v[0] === '/') return v;
      return '/uploads/parts/' + v;
    }

    function badgeClass(qty, min) {
      if (qty <= 0) return 'danger';
      if (qty < Math.max(1, min / 2)) return 'warn';
      return 'soft';
    }

    function renderCards(list) {
      grid.innerHTML = '';
      list.forEach(r => {
        const cls = badgeClass(+r.quantity, +r.min_stock);
        const w = Math.max(0, Math.min(100, +r.pct || 0));
        const img = imgSrc(r.image_url);
        const el = document.createElement('div');
        el.className = 'low-card';
        el.innerHTML = `
        <div class="low-head">
          <div class="low-name" title="${esc(r.part_name)}">${esc(r.part_name||'ไม่ทราบชื่อ')}</div>
          <div class="chip ${cls}">${esc(r.quantity)}/${esc(r.min_stock)}</div>
        </div>
        <div class="low-sub">
          <div class="low-model" title="${esc(r.device_models)}">${esc(r.device_models||'-')}</div>
          ${img ? `<img class="low-thumb" src="${esc(img)}" alt="">` : `<div class="low-thumb"></div>`}
        </div>
        <div class="low-bar"><span style="width:${w}%"></span></div>
        <div class="low-meta">ขาด <b>${esc(r.deficit)}</b> | รหัส <code>${esc(r.part_code||'-')}</code> | หมวด ${esc(r.category||'-')}</div>
        <div class="low-actions">
          <button class="btn tiny" data-copy="${esc(r.part_name)}" title="คัดลอกชื่อ">คัดลอกชื่อ</button>
          <button class="btn tiny" data-copy="${esc(r.part_code)}" title="คัดลอกรหัส">คัดลอกรหัส</button>
          <a class="btn tiny ghost" href="../parts/index.php?tab=new&q=${toHrefQuery(r.part_code||r.part_name||'')}" title="เปิดในรายการ">เปิดดู</a>
          <a class="btn tiny primary" href="../parts/form.php?part_code=${toHrefQuery(r.part_code||'')}" title="แก้ไข">แก้ไข</a>
        </div>
      `;
        grid.appendChild(el);
      });
    }

    function renderTable(list) {
      tblBody.innerHTML = '';
      list.forEach((r, i) => {
        const img = imgSrc(r.image_url);
        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>${i+1}</td>
        <td>${img ? `<img src="${esc(img)}" class="thumb">` : `<div class="thumb"></div>`}</td>
        <td><strong>${esc(r.part_name||'ไม่ทราบชื่อ')}</strong></td>
        <td>${esc(r.device_models||'-')}</td>
        <td>${esc(r.quantity)}</td>
        <td>${esc(r.min_stock)}</td>
        <td>${esc(r.deficit)}</td>
        <td><code>${esc(r.part_code||'-')}</code></td>
        <td>${esc(r.category||'-')}</td>
        <td class="no-wrap">
          <button class="btn tiny" data-copy="${esc(r.part_name)}">คัดลอกชื่อ</button>
          <a class="btn tiny ghost" href="../parts/index.php?tab=new&q=${toHrefQuery(r.part_code||r.part_name||'')}">เปิดดู</a>
          <a class="btn tiny primary" href="../parts/form.php?part_code=${toHrefQuery(r.part_code||'')}">แก้ไข</a>
        </td>
      `;
        tblBody.appendChild(tr);
      });
    }

    function applyFilter() {
      const q = (search.value || '').toLowerCase().trim();
      if (!q) return rows;
      return rows.filter(r =>
        (r.part_name || '').toLowerCase().includes(q) ||
        (r.device_models || '').toLowerCase().includes(q) ||
        (r.part_code || '').toLowerCase().includes(q) ||
        (r.category || '').toLowerCase().includes(q)
      );
    }

    function render() {
      const list = applyFilter();
      if (btnToggle.dataset.mode === 'cards') {
        renderCards(list);
        grid.style.display = '';
        tblWrap.style.display = 'none';
      } else {
        renderTable(list);
        grid.style.display = 'none';
        tblWrap.style.display = '';
      }
    }

    grid.addEventListener('click', e => {
      const t = e.target.closest('[data-copy]');
      if (t) {
        navigator.clipboard.writeText(t.getAttribute('data-copy') || '');
        t.textContent = 'คัดลอกแล้ว';
        setTimeout(() => t.textContent = t.title?.includes('ชื่อ') ? 'คัดลอกชื่อ' : 'คัดลอกรหัส', 900);
      }
    });
    tblBody.addEventListener('click', e => {
      const t = e.target.closest('[data-copy]');
      if (t) {
        navigator.clipboard.writeText(t.getAttribute('data-copy') || '');
        t.textContent = 'คัดลอกแล้ว';
        setTimeout(() => t.textContent = 'คัดลอกชื่อ', 900);
      }
    });

    btnToggle.addEventListener('click', () => {
      if (btnToggle.dataset.mode === 'cards') {
        btnToggle.dataset.mode = 'table';
        btnToggle.textContent = 'สลับเป็นการ์ด';
      } else {
        btnToggle.dataset.mode = 'cards';
        btnToggle.textContent = 'สลับเป็นตาราง';
      }
      render();
    });

    btnExport.addEventListener('click', () => {
      const list = applyFilter();
      const header = ['part_code', 'part_name', 'device_models', 'quantity', 'min_stock', 'deficit', 'category'];
      const csv = [header.join(',')].concat(
        list.map(r => header.map(k => `"${(r[k]??'').toString().replace(/"/g,'""')}"`).join(','))
      ).join('\r\n');
      const blob = new Blob([csv], {
        type: 'text/csv;charset=utf-8;'
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'low-stock.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    search.addEventListener('input', render);
    render(); // initial render

    /* ---------- Charts ---------- */
    // Parts Usage
    const partsLabels = <?= $JS_PARTS_LABELS ?>;
    const partsData = <?= $JS_PARTS_DATA   ?>;
    const el1 = document.getElementById('chartPartsUsage');
    if (el1 && partsLabels && partsLabels.length) {
      const ctx = el1.getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, el1.width, 0);
      grad.addColorStop(0, 'rgba(99,102,241,0.9)');
      grad.addColorStop(1, 'rgba(59,130,246,0.9)');
      new Chart(el1, {
        type: 'bar',
        data: {
          labels: partsLabels,
          datasets: [{
            label: 'ครั้งที่ใช้',
            data: partsData,
            backgroundColor: grad,
            borderRadius: 8,
            barThickness: 22
          }]
        },
        options: {
          indexAxis: 'y',
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: {
                display: false
              },
              ticks: {
                precision: 0
              }
            },
            y: {
              grid: {
                display: false
              }
            }
          }
        }
      });
    }

    // Repairs per Month
    const monthLabels = <?= $JS_MONTH_LABELS ?>;
    const monthData = <?= $JS_MONTH_DATA   ?>;
    const el2 = document.getElementById('chartRepairsMonthly');
    if (el2) {
      const ctx2 = el2.getContext('2d');
      const grad2 = ctx2.createLinearGradient(0, 0, 0, el2.height);
      grad2.addColorStop(0, 'rgba(16,185,129,0.25)');
      grad2.addColorStop(1, 'rgba(16,185,129,0.02)');
      new Chart(el2, {
        type: 'line',
        data: {
          labels: monthLabels,
          datasets: [{
            label: 'จำนวนงานซ่อม',
            data: monthData,
            borderWidth: 2,
            borderColor: 'rgba(16,185,129,1)',
            backgroundColor: grad2,
            fill: true,
            pointRadius: 3,
            tension: 0.3
          }]
        },
        options: {
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              }
            },
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0
              }
            }
          }
        }
      });
    }
  });
</script>

<style>
  /* Topbar */
  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
  }

  .topbar-title {
    font-weight: 700;
    font-size: 18px;
    color: #111827;
  }

  .view-site {
    display: inline-block;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    text-decoration: none;
    color: #111827;
    background: #f9fafb;
  }

  .view-site:hover {
    background: #f3f4f6;
  }

  .section {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  /* LOWBAR */
  .lowbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: linear-gradient(90deg, #fff, #f8fafc);
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 12px 14px;
    position: sticky;
    top: 10px;
    z-index: 5;
  }

  .lowbar-title {
    font-weight: 800;
    color: #0f172a;
  }

  .lowbar-stats {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #0f172a;
  }

  .chip.warn {
    background: #fff7ed;
    border-color: #fdba74;
    color: #9a3412;
  }

  .chip.danger {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
  }

  .lowbar-right {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
  }

  .low-search {
    width: 220px;
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #0f172a;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
  }

  .btn:hover {
    background: #f3f4f6;
  }

  .btn.primary {
    background: #111827;
    color: #fff;
    border-color: #111827;
  }

  .btn.primary:hover {
    background: #0b1220;
  }

  .btn.ghost {
    background: #fff;
  }

  .btn.tiny {
    padding: 5px 8px;
    font-size: 12px;
    border-radius: 8px;
  }

  /* Low grid/cards */
  .low-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  @media (max-width: 900px) {
    .low-grid {
      grid-template-columns: 1fr;
    }
  }

  .low-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .low-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .low-name {
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 70%;
  }

  .low-sub {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .low-thumb {
    width: 56px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
    background: #f1f5f9;
    border: 1px solid #e5e7eb;
  }

  .low-model {
    font-size: 12.5px;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
  }

  .low-bar {
    height: 8px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
  }

  .low-bar>span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #ef4444, #f59e0b 50%, #22c55e);
  }

  .low-meta {
    font-size: 12px;
    color: #334155;
  }

  .low-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  /* KPI */
  .grid.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  @media (max-width: 1100px) {
    .grid.kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 640px) {
    .grid.kpi-grid {
      grid-template-columns: 1fr;
    }
  }

  .card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
  }

  .card-k {
    font-size: 28px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
  }

  .card-t {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
  }

  /* Panels & tables */
  .panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
  }

  .panel-h {
    padding: 12px 16px;
    font-weight: 700;
    color: #111827;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 12px 12px 0 0;
  }

  .panel-b {
    padding: 12px 16px;
  }

  .table-wrap {
    overflow: auto;
    border-radius: 0 0 12px 12px;
  }

  .tbl {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .tbl thead th {
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    color: #374151;
    padding: 10px 8px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafafa;
    position: sticky;
    top: 0;
  }

  .tbl tbody td {
    padding: 10px 8px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #111827;
  }

  .tbl tbody tr:hover td {
    background: #f9fafb;
  }

  .muted {
    color: #6b7280;
    text-align: center;
  }

  .thumb {
    width: 48px;
    height: 36px;
    border-radius: 6px;
    background: #f1f5f9;
    border: 1px solid #e5e7eb;
    object-fit: cover;
  }

  /* Charts */
  .chart-wrap {
    position: relative;
    min-height: 200px;
  }

  .chart-empty {
    padding: 8px 10px;
    font-size: 13px;
    color: #6b7280;
  }
</style>