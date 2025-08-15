<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);
$pageTitle="ประวัติการเคลื่อนไหวอะไหล่";
include __DIR__.'/../../templates/header_admin.php';
include __DIR__.'/../../templates/sidebar_admin.php';
$st=$pdo->query("
  SELECT d.doc_id,d.doc_type,d.ref_no,d.remarks,d.created_at,
         l.part_code,l.qty,l.location_from,l.location_to,l.unit_cost
  FROM parts_docs d
  JOIN parts_doc_lines l ON l.doc_id=d.doc_id
  ORDER BY d.doc_id DESC, l.line_id ASC
  LIMIT 200
");
$rows=$st->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="main" id="main-content">
  <div class="topbar"><span><?= htmlspecialchars($pageTitle) ?></span></div>
  <div class="section-header"><h2><?= htmlspecialchars($pageTitle) ?></h2></div>
  <div class="table-container">
    <table class="data-table">
      <thead><tr>
        <th>เวลา</th><th>ประเภท</th><th>part_code</th><th>จำนวน</th>
        <th>จาก</th><th>ไป</th><th>ต้นทุน</th><th>อ้างอิง</th><th>หมายเหตุ</th>
      </tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars($r['doc_type']) ?></td>
            <td><?= htmlspecialchars($r['part_code']) ?></td>
            <td><?= (int)$r['qty'] ?></td>
            <td><?= htmlspecialchars($r['location_from']) ?></td>
            <td><?= htmlspecialchars($r['location_to']) ?></td>
            <td><?= $r['unit_cost']!==null ? number_format($r['unit_cost'],2) : '' ?></td>
            <td><?= htmlspecialchars($r['ref_no']) ?></td>
            <td><?= htmlspecialchars($r['remarks']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
