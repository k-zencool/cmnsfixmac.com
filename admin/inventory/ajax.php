<?php
/**
 * ajax.php — inventory HTML fragment endpoints (row expansion)
 * แยกออกจาก view.php เพื่อให้หน้า page ไม่ต้องทำตัวเป็น AJAX responder
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// fragment endpoint — ตอบ 403 ตรงๆ ไม่ redirect (redirect จะพ่นหน้า login ลงไปในแถวตาราง)
if (!is_logged_in()) {
    http_response_code(403);
    exit('forbidden');
}

if (isset($_GET['action']) && $_GET['action'] === 'get_lots_inline') {
    $item_id = (int)$_GET['item_id'];

    // USED type — แสดง item details แทน lot table
    $item_row = $pdo->prepare("SELECT i.*, inv2.name as src_name, inv2.asset_tag as src_tag FROM inventory i LEFT JOIN inventory AS inv2 ON i.source_machine_id = inv2.id WHERE i.id = ?");
    $item_row->execute([$item_id]);
    $item_row = $item_row->fetch(PDO::FETCH_ASSOC);

    if ($item_row && $item_row['type'] === 'used') {
        $st_color = $item_row['status'] === 'GOOD' ? '#10b981' : '#f59e0b';
        $source   = $item_row['src_name'] ? "[{$item_row['src_tag']}] {$item_row['src_name']}" : null;
        ?>
        <div style="padding:16px 20px 16px 80px;">
            <div style="font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;gap:6px;">
                <span class="material-symbols-rounded" style="font-size:16px;color:#f59e0b;">build</span> USED PART DETAILS
            </div>
            <div style="display:flex;gap:28px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">SERIAL NO.</div>
                    <code style="font-size:13px;background:var(--bg-surface-alt);padding:2px 8px;border-radius:6px;"><?= htmlspecialchars($item_row['serial_number'] ?: '—') ?></code>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">STATUS</div>
                    <span style="font-weight:800;color:<?= $st_color ?>;font-size:13px;"><?= htmlspecialchars($item_row['status']) ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">CONDITION NOTE</div>
                    <span style="font-size:13px;color:var(--text-main);"><?= htmlspecialchars($item_row['condition_note'] ?: '—') ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">LOCATION</div>
                    <span style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($item_row['location'] ?: '—') ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">PART NO.</div>
                    <span style="font-size:13px;color:var(--text-muted);font-family:monospace;"><?= htmlspecialchars($item_row['part_number'] ?: '—') ?></span>
                </div>
                <?php if($source): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">SOURCE MACHINE</div>
                    <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($source) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        exit;
    }

    // MACHINE type — แสดง specs + ตำหนิ
    if ($item_row && $item_row['type'] === 'machine') {
        $grade_color = ['A' => '#10b981', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#ef4444'][$item_row['condition_grade'] ?? ''] ?? '#888';
        $dis_map = [
            'intact'             => ['lock',         '#10b981', 'Intact — ยังไม่แกะ'],
            'partially_stripped' => ['build',         '#f59e0b', 'Partially Stripped — แกะบางส่วน'],
            'stripped'           => ['check_circle',  '#6b7280', 'Fully Stripped — แกะหมดแล้ว'],
        ];
        $dis = $dis_map[$item_row['disassembly_status'] ?? ''] ?? ['info', '#888', '—'];

        // ดึงอะไหล่ที่แกะออกจากเครื่องนี้
        $stripped_parts = $pdo->prepare("SELECT name, sku, status, serial_number FROM inventory WHERE source_machine_id = ? AND type = 'used' ORDER BY created_at DESC");
        $stripped_parts->execute([$item_id]);
        $parts = $stripped_parts->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div style="padding:14px 20px 14px 80px; border-top:1px solid var(--border);">
            <?php if($item_row['condition_note']): ?>
            <div style="<?= $parts ? 'margin-bottom:12px;' : '' ?>">
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">ตำหนิ / หมายเหตุ</div>
                <div style="font-size:13px;color:var(--text-main);line-height:1.6;padding:10px 14px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:8px;"><?= htmlspecialchars($item_row['condition_note']) ?></div>
            </div>
            <?php endif; ?>

            <?php if($parts): ?>
            <div>
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">อะไหล่ที่แยกออกมาแล้ว (<?= count($parts) ?>)</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach($parts as $p):
                        $pc = ['GOOD'=>'#10b981','TEST'=>'#f59e0b'][$p['status']] ?? '#ef4444';
                    ?>
                    <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:<?= $pc ?>14;border:1px solid <?= $pc ?>33;color:<?= $pc ?>;font-weight:700;">
                        <?= htmlspecialchars($p['name']) ?> · <?= $p['status'] ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!$item_row['condition_note'] && !$parts): ?>
            <div style="font-size:13px;color:var(--text-muted);font-style:italic;">ไม่มีหมายเหตุและยังไม่ได้แยกอะไหล่</div>
            <?php endif; ?>
        </div>
        <?php
        exit;
    }

    // SALE type — แสดง warranty + battery + condition note
    if ($item_row && $item_row['type'] === 'sale') {
        $apple_days   = null;
        $store_w_days = $item_row['store_warranty_days'] ?? null;
        if (!empty($item_row['apple_warranty_date'])) $apple_days = (int)((strtotime($item_row['apple_warranty_date']) - time()) / 86400);
        $grade_color = ['A'=>'#10b981','B'=>'#3b82f6','C'=>'#f59e0b'][$item_row['condition_grade'] ?? ''] ?? '#888';
        ?>
        <div style="padding:14px 20px 14px 80px; border-top:1px solid var(--border);">
            <div style="display:flex;gap:28px;flex-wrap:wrap;margin-bottom:<?= $item_row['condition_note'] ? '14px' : '0' ?>;">
                <?php if($item_row['condition_grade']): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Grade</div>
                    <span style="font-size:22px;font-weight:900;color:<?= $grade_color ?>;"><?= htmlspecialchars($item_row['condition_grade']) ?></span>
                </div>
                <?php endif; ?>

                <?php if($apple_days !== null): $ac = $apple_days < 0 ? '#ef4444' : ($apple_days < 90 ? '#f59e0b' : '#10b981'); ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">ประกัน Apple ศูนย์</div>
                    <div style="font-size:13px;font-weight:700;color:<?= $ac ?>;display:flex;align-items:center;gap:5px;">
                        <span class="material-symbols-rounded" style="font-size:16px;">verified</span>
                        <?php if($apple_days < 0): ?>หมดแล้ว (<?= date('d/m/Y', strtotime($item_row['apple_warranty_date'])) ?>)
                        <?php else: ?>เหลือ <?= $apple_days ?> วัน (หมด <?= date('d/m/Y', strtotime($item_row['apple_warranty_date'])) ?>)<?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($store_w_days !== null): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">ประกันร้าน</div>
                    <div style="font-size:13px;font-weight:700;color:#3b82f6;display:flex;align-items:center;gap:5px;">
                        <span class="material-symbols-rounded" style="font-size:16px;">store</span>
                        <?= $store_w_days ?> วัน (นับจากวันที่ขาย)
                    </div>
                </div>
                <?php endif; ?>

                <?php if($item_row['battery_health'] !== null): $bh=(int)$item_row['battery_health']; $bc=$bh>=85?'#10b981':($bh>=70?'#f59e0b':'#ef4444'); ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">สุขภาพแบต</div>
                    <div style="font-size:18px;font-weight:900;color:<?= $bc ?>;display:flex;align-items:center;gap:6px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">battery_charging_full</span>
                        <?= $bh ?>%
                        <?php if($item_row['battery_cycles']): ?>
                        <span style="font-size:11px;color:var(--text-muted);font-weight:500;"><?= $item_row['battery_cycles'] ?> รอบชาร์จ</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if($item_row['condition_note']): ?>
            <div>
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">ตำหนิ / สภาพ</div>
                <div style="font-size:13px;color:var(--text-main);line-height:1.6;padding:10px 14px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.12);border-radius:8px;"><?= htmlspecialchars($item_row['condition_note']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        exit;
    }

    // NEW — แสดง lot table เดิม
    $stmt_lots = $pdo->prepare("SELECT * FROM inventory_lots WHERE inventory_id = ? AND qty_remaining > 0 ORDER BY warranty_end ASC");
    $stmt_lots->execute([$item_id]);
    $lots = $stmt_lots->fetchAll(PDO::FETCH_ASSOC);

    if (!$lots) {
        echo '<div style="padding:20px 20px 20px 80px; color:var(--text-muted); font-size:13px; font-style:italic;">🚫 ไม่มีข้อมูลสต็อกในระบบล็อต</div>';
    } else {
        ?>
        <div class="lot-seamless-wrapper">
            <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-rounded" style="font-size: 16px; color: var(--primary);">account_tree</span> 
                WARRANTY TRACKING PER LOT
            </div>

            <table class="seamless-inner-table">
                <thead>
                    <tr>
                        <th align="left">LOT NUMBER</th>
                        <th align="center">QTY (LEFT/TOTAL)</th>
                        <th align="center">COST (ทุน)</th>
                        <th align="center">WARRANTY EXP.</th>
                        <th align="left">SUPPLIER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lots as $l):
                        $wEnd   = $l['warranty_end'] ?? null;
                        $urgent = $wEnd && (strtotime($wEnd) - time() < 2592000);
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($l['lot_number'] ?? '—') ?></code></td>
                        <td align="center" style="color: var(--text-main);">
                            <b style="font-size: 14px;"><?= $l['qty_remaining'] ?></b>
                            <span style="color: var(--text-muted);">/ <?= $l['qty_received'] ?></span>
                        </td>
                        <td align="center" style="color: var(--text-muted);">฿<?= number_format($l['cost_price'] ?? 0) ?></td>
                        <td align="center" style="color: <?= $urgent ? '#ef4444' : 'var(--text-main)' ?>; font-weight: <?= $urgent ? '800' : '500' ?>;">
                            <?= $wEnd ? date('d/m/Y', strtotime($wEnd)) : '—' ?>
                        </td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($l['supplier_name'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    exit;
}

// =========================================================
// 2. MAIN DATA FETCHING & FILTER LOGIC

http_response_code(400);
exit('unknown action');
