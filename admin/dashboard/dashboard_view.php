<?php
if (!defined('IN_APP')) {
  // เด้งกลับไปที่หน้า index ของ dashboard
  header("Location: /admin/dashboard/");
  exit();
}
// ...

// ใช้ include แทน require เพราะไฟล์ data ถูกเรียกใช้ไปแล้วใน index.php
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar" id="topbar">
    <span>ยินดีต้อนรับ, <?= e($username) ?></span>
    <a href="/" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <section class="dashboard-cards">
    <div class="card">
      <span class="material-symbols-rounded">category</span>
      <h2><?= e($totalPartTypes) ?> ชนิด</h2>
      <p>ชนิดอะไหล่ในแคตตาล็อก</p>
    </div>
    <div class="card">
      <span class="material-symbols-rounded">inventory</span>
      <h2><?= e($totalStockQuantity) ?> ชิ้น</h2>
      <p>อะไหล่ทั้งหมดในสต็อก</p>
    </div>
    <div class="card" style="background-color: #fef2f2; color: #991b1b;">
      <span class="material-symbols-rounded">warning</span>
      <h2><?= e($lowStockCount) ?> ชนิด</h2>
      <p>อะไหล่ใกล้หมด</p>
    </div>
    <div class="card">
      <span class="material-symbols-rounded">arrow_circle_up</span>
      <h2><?= e($checkoutsThisMonth) ?> ครั้ง</h2>
      <p>การเบิกของในเดือนนี้</p>
    </div>
  </section>

  <section class="dashboard-section">
    <h2>สรุปการเคลื่อนไหวสต็อกรายเดือน (ปี <?= e($currentYear) ?>)</h2>
    <div class="chart-container">
      <canvas id="stockMovementChart"></canvas>
    </div>
  </section>

  <section class="dashboard-section">
    <h2>รายการล่าสุด</h2>
    <div class="latest-items-grid">
      <div class="latest-item-list">
        <h3>10 รายการเคลื่อนไหวล่าสุด</h3>
        <?php if (empty($latestStockMovements)): ?>
          <p>ยังไม่มีการเคลื่อนไหว</p>
        <?php else: ?>
          <ul>
            <?php foreach ($latestStockMovements as $move): ?>
              <li>
                <a href="../parts/index.php?view=history&search=<?= e($move['notes']) ?>">
                  <span>
                    <?= e($move['part_name']) ?>
                    <strong style="color: <?= $move['quantity_change'] > 0 ? '#28a745' : '#dc3545' ?>;">
                      (<?= $move['quantity_change'] > 0 ? '+' : '' ?><?= e($move['quantity_change']) ?>)
                    </strong>
                    <small>โดย <?= e($move['username']) ?></small>
                  </span>
                  <small><?= date('d M Y H:i', strtotime($move['log_date'])) ?></small>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="latest-item-list">
        <h3 style="color: #b91c1c;">อะไหล่ใกล้หมด!</h3>
        <?php if (empty($lowStockParts)): ?>
          <p>ยอดเยี่ยม! ไม่มีอะไหล่ใกล้หมด</p>
        <?php else: ?>
          <ul>
            <?php foreach ($lowStockParts as $item): ?>
              <li>
                <a href="../parts/form.php?id=<?= e($item['part_id']) ?>">
                  <span><strong><?= e($item['part_name']) ?></strong> (<?= e($item['part_number']) ?>)</span>
                  <small style="color: #b91c1c; font-weight: bold;">เหลือ <?= e($item['quantity']) ?> ชิ้น</small>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="latest-item-list">
        <h3>5 ชนิดอะไหล่ที่เพิ่มล่าสุด</h3>
        <?php if (empty($latestAddedParts)): ?>
          <p>ยังไม่มีข้อมูล</p>
        <?php else: ?>
          <ul>
            <?php foreach ($latestAddedParts as $item): ?>
              <li>
                <a href="../parts/form.php?id=<?= e($item['part_id']) ?>">
                  <span><?= e($item['part_name']) ?></span>
                  <small><?= date('d M Y', strtotime($item['created_at'])) ?></small>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    </div>
  </section>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('stockMovementChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?= json_encode($chartMonths) ?>,
          datasets: [{
              label: 'จำนวนรับเข้า (ชิ้น)',
              data: <?= json_encode($stockInData) ?>,
              backgroundColor: 'rgba(40, 167, 69, 0.6)',
              borderColor: 'rgba(40, 167, 69, 1)',
              borderWidth: 1,
              borderRadius: 5
            },
            {
              label: 'จำนวนเบิกออก (ชิ้น)',
              data: <?= json_encode($stockOutData) ?>,
              backgroundColor: 'rgba(220, 53, 69, 0.6)',
              borderColor: 'rgba(220, 53, 69, 1)',
              borderWidth: 1,
              borderRadius: 5
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
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