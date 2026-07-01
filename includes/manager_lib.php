<?php
/********************************************************************
 * includes/manager_lib.php
 * ศูนย์ควบคุมผู้จัดการ — audit ledger + reversal framework
 *
 * โมเดล: action ทำงานทันที (ไม่บล็อกงานหน้าร้าน) แต่ทุก sensitive
 * action ของ staff/admin จะถูก log ลง manager_actions และ
 * manager/super_admin สามารถ "ย้อนกลับ (reverse)" ได้ทีหลัง.
 *
 * ทุกฟังก์ชัน prefix ด้วย mgr_ และ guard ด้วย function_exists
 * เพื่อให้ include ซ้ำได้อย่างปลอดภัย (แบบเดียวกับ warranty_lib.php)
 ********************************************************************/

require_once __DIR__ . '/auth.php';

// ── ยศที่ถูกจับตา (การกระทำจะถูก log และย้อนได้) ──
if (!function_exists('mgr_supervised_roles')) {
    function mgr_supervised_roles(): array
    {
        return ['staff', 'admin'];
    }
}

// ── ยศที่คุมได้ (เห็นศูนย์ควบคุม + reverse ได้) ──
if (!function_exists('mgr_approver_roles')) {
    function mgr_approver_roles(): array
    {
        return ['manager', 'super_admin'];
    }
}

/** ยศจริง (ไม่นับ view-as) เป็นผู้อนุมัติไหม */
if (!function_exists('mgr_can_control')) {
    function mgr_can_control(): bool
    {
        return in_array(real_role(), mgr_approver_roles(), true);
    }
}

/** action นี้ทำโดยยศที่ต้องถูกจับตาไหม */
if (!function_exists('mgr_is_supervised_role')) {
    function mgr_is_supervised_role(string $role): bool
    {
        return in_array($role, mgr_supervised_roles(), true);
    }
}

/** ป้ายชื่อ action แบบอ่านง่าย + สี + ไอคอน */
if (!function_exists('mgr_action_meta')) {
    function mgr_action_meta(string $type): array
    {
        $map = [
            'requisition'  => ['label' => 'เบิกอะไหล่',        'icon' => 'inventory_2',   'color' => '#3b82f6'],
            'price_set'    => ['label' => 'ตั้ง/แก้ราคาซ่อม',  'icon' => 'price_change',  'color' => '#f59e0b'],
            'stock_delete' => ['label' => 'ลบสต็อก',          'icon' => 'delete',        'color' => '#ef4444'],
            'stock_edit'   => ['label' => 'แก้สต็อก',          'icon' => 'edit',          'color' => '#8b5cf6'],
            'donor_strip'  => ['label' => 'แยกเครื่องซาก',     'icon' => 'content_cut',   'color' => '#ec4899'],
            'to_sale'      => ['label' => 'เอาขึ้นขาย',        'icon' => 'sell',          'color' => '#10b981'],
            'sale_status'  => ['label' => 'เปลี่ยนสถานะขาย',   'icon' => 'point_of_sale', 'color' => '#14b8a6'],
        ];
        return $map[$type] ?? ['label' => $type, 'icon' => 'help', 'color' => '#64748b'];
    }
}

/**
 * บันทึก action ลง ledger
 * $data keys: action_type, ref_table, ref_id, summary, amount, payload(array),
 *             reversible(bool), actor_id, actor_name, actor_role
 * คืนค่า: id ของ manager_actions (0 ถ้าพลาด — ไม่ทำให้ flow หลักล่ม)
 */
if (!function_exists('mgr_log')) {
    function mgr_log(PDO $pdo, array $data): int
    {
        $actor_id   = $data['actor_id']   ?? ($_SESSION['admin_id'] ?? null);
        $actor_name = $data['actor_name'] ?? ($_SESSION['admin_username'] ?? ($_SESSION['admin_name'] ?? null));
        $actor_role = $data['actor_role'] ?? real_role();

        // ยศเจ้าของ (super_admin) ทำอะไร ไม่ต้องเก็บประวัติในศูนย์ควบคุม
        if ($actor_role === 'super_admin') return 0;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO manager_actions
                    (action_type, ref_table, ref_id, actor_id, actor_name, actor_role,
                     summary, amount, payload, reversible)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $data['action_type'],
                $data['ref_table'] ?? null,
                $data['ref_id']    ?? null,
                $actor_id,
                $actor_name,
                $actor_role,
                mb_substr((string)($data['summary'] ?? ''), 0, 255),
                $data['amount'] ?? null,
                isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
                isset($data['reversible']) ? (int)(bool)$data['reversible'] : 1,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('mgr_log failed: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * ย้อนกลับ action — เรียกจากศูนย์ควบคุม (manager/super_admin เท่านั้น)
 * คืนค่า: ['ok'=>bool, 'msg'=>string]
 */
if (!function_exists('mgr_reverse')) {
    function mgr_reverse(PDO $pdo, int $action_id, string $note = ''): array
    {
        if (!mgr_can_control()) {
            return ['ok' => false, 'msg' => 'เฉพาะผู้จัดการเท่านั้นที่ย้อนรายการได้'];
        }

        $stmt = $pdo->prepare("SELECT * FROM manager_actions WHERE id = ? FOR UPDATE");

        $pdo->beginTransaction();
        try {
            $stmt->execute([$action_id]);
            $act = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$act) {
                $pdo->rollBack();
                return ['ok' => false, 'msg' => 'ไม่พบรายการ'];
            }
            if ($act['status'] === 'reversed') {
                $pdo->rollBack();
                return ['ok' => false, 'msg' => 'รายการนี้ถูกย้อนไปแล้ว'];
            }
            if (!$act['reversible']) {
                $pdo->rollBack();
                return ['ok' => false, 'msg' => 'รายการนี้ย้อนกลับไม่ได้'];
            }

            $payload = $act['payload'] ? json_decode($act['payload'], true) : [];

            // ── dispatch ตามชนิด action ──
            $handler = 'mgr_reverse_' . $act['action_type'];
            if (!function_exists($handler)) {
                $pdo->rollBack();
                return ['ok' => false, 'msg' => "ยังไม่รองรับการย้อน '{$act['action_type']}'"];
            }

            $res = $handler($pdo, $act, $payload);
            if (!($res['ok'] ?? false)) {
                $pdo->rollBack();
                return $res;
            }

            // mark ledger เป็น reversed
            $pdo->prepare("
                UPDATE manager_actions
                SET status = 'reversed', reversed_by = ?, reversed_name = ?, reversed_at = NOW(), reverse_note = ?
                WHERE id = ?
            ")->execute([
                $_SESSION['admin_id'] ?? null,
                $_SESSION['admin_username'] ?? ($_SESSION['admin_name'] ?? null),
                mb_substr($note, 0, 255) ?: null,
                $action_id,
            ]);

            $pdo->commit();
            return ['ok' => true, 'msg' => $res['msg'] ?? 'ย้อนรายการเรียบร้อย'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('mgr_reverse failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'ผิดพลาด: ' . $e->getMessage()];
        }
    }
}

/* ================================================================
 * REVERSE HANDLERS — หนึ่งฟังก์ชันต่อ action_type
 * ต้องรันภายใน transaction เดียวกับ mgr_reverse (มันเปิดให้แล้ว)
 * คืน ['ok'=>bool, 'msg'=>string]
 * ================================================================ */

/** เบิกอะไหล่ → คืนสต็อกเข้า lot เดิมตาม payload.deductions */
if (!function_exists('mgr_reverse_requisition')) {
    function mgr_reverse_requisition(PDO $pdo, array $act, array $payload): array
    {
        $req_id     = (int)($payload['requisition_id'] ?? 0);
        $inv_id     = (int)($payload['inventory_id'] ?? $act['ref_id']);
        $deductions = $payload['deductions'] ?? [];

        if (!$deductions) {
            return ['ok' => false, 'msg' => 'ไม่มีข้อมูล lot ที่ตัดไป ย้อนอัตโนมัติไม่ได้'];
        }

        // กันย้อนซ้ำ: ถ้า requisition ถูก mark reversed แล้ว
        if ($req_id) {
            $chk = $pdo->prepare("SELECT status FROM parts_requisitions WHERE id = ?");
            $chk->execute([$req_id]);
            $st = $chk->fetchColumn();
            if ($st === 'reversed') {
                return ['ok' => false, 'msg' => 'ใบเบิกนี้ถูกย้อนไปแล้ว'];
            }
        }

        // คืนของเข้าแต่ละ lot
        $upd = $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + ? WHERE id = ?");
        foreach ($deductions as $d) {
            $lot_id = (int)($d['lot_id'] ?? 0);
            $take   = (int)($d['take'] ?? 0);
            if ($lot_id && $take > 0) {
                $upd->execute([$take, $lot_id]);
            }
        }

        // ถ้ามีของกลับมาแล้ว ปลดสถานะ OOS
        if ($inv_id) {
            $avail = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots WHERE inventory_id = ?");
            $avail->execute([$inv_id]);
            if ((int)$avail->fetchColumn() > 0) {
                $pdo->prepare("UPDATE inventory SET status = 'STOCK' WHERE id = ? AND status = 'OOS'")
                    ->execute([$inv_id]);
            }
        }

        // mark ใบเบิก
        if ($req_id) {
            $pdo->prepare("UPDATE parts_requisitions SET status = 'reversed' WHERE id = ?")->execute([$req_id]);
        }

        return ['ok' => true, 'msg' => 'คืนอะไหล่เข้าสต็อกเรียบร้อย'];
    }
}

/** ตั้ง/แก้ราคาซ่อม → คืนค่าเดิม (UPDATE) หรือลบแถวที่เพิ่งสร้าง (INSERT) */
if (!function_exists('mgr_reverse_price_set')) {
    function mgr_reverse_price_set(PDO $pdo, array $act, array $payload): array
    {
        $pid = (int)($payload['pricing_id'] ?? $act['ref_id']);
        if (!$pid) return ['ok' => false, 'msg' => 'ไม่พบ pricing id'];

        // กรณีเพิ่มใหม่ → ลบทิ้ง
        if (!empty($payload['was_insert'])) {
            $pdo->prepare("DELETE FROM service_pricing WHERE id = ?")->execute([$pid]);
            return ['ok' => true, 'msg' => 'ลบราคาที่เพิ่งเพิ่มออกแล้ว'];
        }

        // กรณีแก้ไข → คืนค่าคอลัมน์เดิม
        $old = $payload['old'] ?? [];
        if (!$old) return ['ok' => false, 'msg' => 'ไม่มีค่าเดิมให้คืน'];

        $cols = array_keys($old);
        $set  = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        $vals = array_values($old);
        $pdo->prepare("UPDATE service_pricing SET $set, updated_at = NOW() WHERE id = ?")
            ->execute([...$vals, $pid]);

        return ['ok' => true, 'msg' => 'คืนราคาเดิมเรียบร้อย'];
    }
}

/** เปลี่ยนสถานะขาย → คืนสถานะเดิม; ถ้าเป็น SOLD ให้ลบ requisition SOLD ที่บันทึกไว้ */
if (!function_exists('mgr_reverse_sale_status')) {
    function mgr_reverse_sale_status(PDO $pdo, array $act, array $payload): array
    {
        $inv_id      = (int)($payload['inventory_id'] ?? $act['ref_id']);
        $from_status = $payload['from_status'] ?? null;
        if (!$inv_id || $from_status === null) {
            return ['ok' => false, 'msg' => 'ข้อมูลไม่พอสำหรับย้อนสถานะ'];
        }

        $pdo->prepare("UPDATE inventory SET status = ? WHERE id = ? AND type = 'sale'")
            ->execute([$from_status, $inv_id]);

        // ถ้าเปลี่ยนเป็น SOLD ไว้ ให้ลบ record การขายที่ log ไว้ + คืนราคาเดิม
        if (($payload['to_status'] ?? '') === 'SOLD') {
            if (!empty($payload['prev_sell_price'])) {
                $pdo->prepare("UPDATE inventory SET sell_price = ? WHERE id = ?")
                    ->execute([$payload['prev_sell_price'], $inv_id]);
            }
            $pdo->prepare("
                DELETE FROM parts_requisitions
                WHERE inventory_id = ? AND remarks = 'SOLD'
                ORDER BY created_at DESC LIMIT 1
            ")->execute([$inv_id]);
        }

        return ['ok' => true, 'msg' => "คืนสถานะเป็น {$from_status} เรียบร้อย"];
    }
}

/** แยกเครื่องซาก → ลบ USED item + lot ที่เพิ่งสร้าง แล้วคืนสถานะ machine (ถ้ายังไม่ถูกเบิก/ขาย) */
if (!function_exists('mgr_reverse_donor_strip')) {
    function mgr_reverse_donor_strip(PDO $pdo, array $act, array $payload): array
    {
        $new_id     = (int)($payload['new_inv_id'] ?? $act['ref_id']);
        $machine_id = (int)($payload['source_machine_id'] ?? 0);
        if (!$new_id) return ['ok' => false, 'msg' => 'ไม่พบอะไหล่ที่แยกมา'];

        // ต้องยังเป็น used และของยังอยู่ครบ (qty_remaining = 1) ห้ามถูกเบิก/แปลงไปแล้ว
        $chk = $pdo->prepare("
            SELECT i.type, COALESCE(SUM(l.qty_remaining),0) AS qty
            FROM inventory i LEFT JOIN inventory_lots l ON l.inventory_id = i.id
            WHERE i.id = ? GROUP BY i.id
        ");
        $chk->execute([$new_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok' => false, 'msg' => 'อะไหล่ถูกลบไปแล้ว'];
        if ($row['type'] !== 'used' || (int)$row['qty'] < 1) {
            return ['ok' => false, 'msg' => 'อะไหล่ชิ้นนี้ถูกเบิก/เปลี่ยนสถานะไปแล้ว ย้อนอัตโนมัติไม่ได้'];
        }

        $pdo->prepare("DELETE FROM inventory_lots WHERE inventory_id = ?")->execute([$new_id]);
        $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$new_id]);

        // คืนสถานะ machine เป็น intact ถ้าตอนแยกมันยัง intact อยู่
        if ($machine_id && !empty($payload['machine_was_intact'])) {
            $pdo->prepare("UPDATE inventory SET disassembly_status = 'intact' WHERE id = ? AND disassembly_status = 'partially_stripped'")
                ->execute([$machine_id]);
        }

        return ['ok' => true, 'msg' => 'ยกเลิกการแยกอะไหล่เรียบร้อย'];
    }
}
