<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$username = $_SESSION['admin_username'] ?? 'ไม่ทราบชื่อ';

// รวมสถิติหลัก
$totalRepairs = (int) $pdo->query("SELECT COUNT(*) FROM repairs")->fetchColumn();
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();

$currentMonth = date('m');
$currentYear = date('Y');

// ผลงานในเดือนนี้
$stmt = $pdo->prepare("SELECT COUNT(*) FROM repairs WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$currentMonth, $currentYear]);
$monthlyRepairs = (int) $stmt->fetchColumn();

// เตรียมข้อมูลกราฟรายเดือน
$months = [];
$repairsData = [];

for ($i = 1; $i <= 12; $i++) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM repairs WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
  $stmt->execute([$i, $currentYear]);
  $months[] = date('M', mktime(0, 0, 0, $i, 1)); // Jan, Feb, Mar, ...
  $repairsData[] = (int) $stmt->fetchColumn();
}

include '../templates/header_admin.php';
include '../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar" id="topbar">
    <span>ยินดีต้อนรับ, <?= htmlspecialchars($username) ?></span>
    <a href="../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <!-- Dashboard Cards -->
  <section class="dashboard-cards">
    <div class="card card-hover">
      <span class="material-symbols-rounded" style="font-size:40px;">work</span>
      <h2><?= $totalRepairs ?> รายการ</h2>
      <p>ผลงานทั้งหมด</p>
    </div>

    <div class="card card-hover">
      <span class="material-symbols-rounded" style="font-size:40px;">group</span>
      <h2><?= $totalAdmins ?> คน</h2>
      <p>จำนวนแอดมิน</p>
    </div>

    <div class="card card-hover">
      <span class="material-symbols-rounded" style="font-size:40px;">trending_up</span>
      <h2><?= $monthlyRepairs ?> รายการ</h2>
      <p>ผลงานเดือนนี้</p>
    </div>
  </section>

  <!-- Chart -->
  <section style="margin-top: 40px;">
    <h2>สรุปผลงานรายเดือน <?= $currentYear ?></h2>
    <canvas id="repairsChart" height="100"></canvas>
  </section>
</main>

<?php include '../templates/footer_admin.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('repairsChart').getContext('2d');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{
        label: 'จำนวนผลงาน',
        data: <?= json_encode($repairsData) ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.5)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
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
});
</script>
