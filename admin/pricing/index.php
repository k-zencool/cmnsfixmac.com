<?php
/********************************************************************
 * admin/pricing/index.php
 * รายการราคาค่าซ่อม (Enterprise Edition)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "จัดการราคาค่าซ่อม (Service Pricing)";

// --- 1. จัดการ Action (ลบข้อมูล) ---
$msg = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        $del_id = $_POST['del_id'];
        $stmt = $pdo->prepare("DELETE FROM service_pricing WHERE id = ?");
        $stmt->execute([$del_id]);
        
        $msg = "ลบรายการเรียบร้อยแล้ว";
        $msgType = "success";
    } catch (Exception $e) {
        $msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $msgType = "error";
    }
}

// --- 2. ระบบค้นหา & กรอง ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';

$where = ["1=1"];
$params = [];

if ($search) {
    // ค้นหาครอบจักรวาล: รหัส, รุ่น, อาการ
    $where[] = "(service_code LIKE ? OR device_series LIKE ? OR device_model LIKE ? OR service_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter) {
    $where[] = "device_type = ?";
    $params[] = $type_filter;
}

// --- 3. ดึงข้อมูล ---
// เรียงลำดับ: Type -> Series -> Model -> Service Name
$sql = "SELECT * FROM service_pricing 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY device_type, device_series, device_model ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึง Type ทั้งหมดมาทำตัวเลือกกรอง
$types = $pdo->query("SELECT DISTINCT device_type FROM service_pricing ORDER BY device_type")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../templates/header_admin.php';

?>

<style>
    /* Status Colors */
    .outdated-row { background-color: #fff1f2 !important; } /* แดงอ่อนเมื่อเก่า */
    .outdated-row:hover { background-color: #ffe4e6 !important; }

    /* Badges & Icons */
    .sn-badge { font-family: monospace; font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px; }
    
    .type-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 2px; }
    .series-name { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
    .model-code { font-size: 0.8rem; color: #64748b; background: #e2e8f0; padding: 1px 5px; border-radius: 4px; margin-left: 5px; }
    
    .price-tag { font-weight: 700; color: #059669; font-size: 1rem; }
    
    /* Toggle Status Icons */
    .status-icon { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; margin: 0 auto; }
    .st-on { color: #2563eb; background: #eff6ff; }
    .st-off { color: #cbd5e1; background: #f8fafc; }
    .st-active { color: #166534; background: #dcfce7; }
    .st-inactive { color: #991b1b; background: #fee2e2; }

    /* Action Buttons */
    .btn-icon { border: none; background: none; cursor: pointer; padding: 5px; transition: 0.2s; border-radius: 4px; }
    .btn-icon:hover { background: #f1f5f9; }
    .btn-edit { color: #f59e0b; }
    .btn-del { color: #ef4444; }

    /* Search Bar */
    .filter-bar { display: flex; gap: 10px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 20px; flex-wrap: wrap; }
    .search-input { flex-grow: 1; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; }
    .select-input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; min-width: 150px; }
    .btn-search { background: #334155; color: white; border: none; padding: 0 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 5px; }
</style>

<main class="main" id="main-content">
    
    <div class="topbar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="material-symbols-rounded" style="font-size: 28px; color: #3b82f6;">price_check</span>
            <span style="font-size: 1.2rem; font-weight: 700;">จัดการราคาค่าซ่อม</span>
        </div>
        
        <a href="form.php" class="btn-primary" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
            <span class="material-symbols-rounded">add</span> เพิ่มราคาใหม่
        </a>
    </div>

    <?php if ($msg): ?>
        <div style="padding: 12px 20px; margin-bottom: 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px; 
            background: <?= $msgType == 'success' ? '#dcfce7' : '#fee2e2' ?>; 
            color: <?= $msgType == 'success' ? '#166534' : '#991b1b' ?>;">
            <span class="material-symbols-rounded"><?= $msgType == 'success' ? 'check_circle' : 'error' ?></span>
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar">
        <select name="type" class="select-input" onchange="this.form.submit()">
            <option value="">-- ทุกประเภท --</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        
        <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา รหัสสินค้า, ชื่อรุ่น, หรืออาการเสีย...">
        
        <button type="submit" class="btn-search">
            <span class="material-symbols-rounded">search</span> ค้นหา
        </button>
        
        <?php if($search || $type_filter): ?>
            <a href="index.php" style="display: flex; align-items: center; color: #ef4444; text-decoration: none; padding: 0 10px;">ล้างค่า</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="12%">รหัส (SN)</th>
                    <th width="30%">รุ่นอุปกรณ์ (Series / Model)</th>
                    <th width="25%">รายการซ่อม</th>
                    <th width="10%" class="text-right">ราคา</th>
                    <th width="8%" class="text-center">Web</th>
                    <th width="8%" class="text-center">Store</th>
                    <th width="7%" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 50px; color: #94a3b8;">
                            <span class="material-symbols-rounded" style="font-size: 48px; opacity: 0.5;">search_off</span>
                            <br>ไม่พบข้อมูลราคา
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): 
                        // Logic เช็ควันหมดอายุ (90 วัน)
                        $last_update = strtotime($item['updated_at']);
                        $is_outdated = ($last_update < strtotime('-90 days'));
                    ?>
                    <tr class="<?= $is_outdated ? 'outdated-row' : '' ?>">
                        
                        <td>
                            <?php if(!empty($item['service_code'])): ?>
                                <span class="sn-badge">
                                    <span class="material-symbols-rounded" style="font-size:12px;">qr_code_2</span>
                                    <?= htmlspecialchars($item['service_code']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="type-label"><?= htmlspecialchars($item['device_type']) ?></div>
                            <div style="display: flex; align-items: center;">
                                <span class="series-name"><?= htmlspecialchars($item['device_series']) ?></span>
                                <?php if(!empty($item['device_model'])): ?>
                                    <span class="model-code"><?= htmlspecialchars($item['device_model']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span class="material-symbols-rounded" style="font-size: 16px; color: #64748b;">build_circle</span>
                                <?= htmlspecialchars($item['service_name']) ?>
                            </div>
                        </td>

                        <td class="text-right">
                            <div class="price-tag"><?= number_format($item['price']) ?></div>
                            <?php if($is_outdated): ?>
                                <div style="font-size: 0.7rem; color: #ef4444; display: flex; align-items: center; justify-content: flex-end; gap: 2px;">
                                    <span class="material-symbols-rounded" style="font-size: 12px;">warning</span> ไม่อัปเดต
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if($item['show_on_web']): ?>
                                <div class="status-icon st-on" title="แสดงบนเว็บไซต์">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">public</span>
                                </div>
                            <?php else: ?>
                                <div class="status-icon st-off" title="ซ่อนจากเว็บ">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">visibility_off</span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if($item['is_active']): ?>
                                <div class="status-icon st-active" title="ใช้งานปกติ">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">check</span>
                                </div>
                            <?php else: ?>
                                <div class="status-icon st-inactive" title="ปิดการใช้งาน">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <div style="display: flex; justify-content: center; gap: 4px;">
                                <a href="form.php?id=<?= $item['id'] ?>" class="btn-icon btn-edit" title="แก้ไข">
                                    <span class="material-symbols-rounded">edit_square</span>
                                </a>
                                
                                <form method="POST" onsubmit="return confirm('⚠️ ยืนยันการลบ?\n<?= $item['device_series'] ?> - <?= $item['service_name'] ?>');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="del_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn-icon btn-del" title="ลบ">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>