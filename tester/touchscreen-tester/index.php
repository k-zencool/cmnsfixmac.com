<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
  <title>Touchscreen Tester</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

</head>
<body>
  <div class="header-toggle">
    <?php include_once '../../includes/header.php'; ?>
  </div>

  <h1>ทดสอบหน้าจอสัมผัส</h1>
  <div id="touchCountDisplay">สัมผัส: 0 จุด</div>

  <div class="ui">
    <button onclick="clearCanvas()">ล้างหน้าจอ</button>
    <button onclick="downloadImage()">บันทึกภาพ</button>
    <button onclick="toggleGrid()">เปิด/ปิดตาราง</button>
  </div>

  <canvas id="touchCanvas"></canvas>
  <div class="footer">แตะหน้าจอเพื่อตรวจสอบการสัมผัสทุกจุด</div>

  <script src="assets/js/script.js"></script>
</body>
</html>
