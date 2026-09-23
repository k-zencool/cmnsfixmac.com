<?php
/********************************************************************
 * includes/sku_lib.php — ประกอบรหัส SKU ให้อ่านออก
 *
 * รูปแบบ:  <อุปกรณ์>-<ชิ้นส่วน>-<รุ่น>     เช่น  IP-FILM-15PM
 *                                              MB-SCRN-A2338
 *                                              PD-TOUCH-IPAD2-WH
 *
 * - อุปกรณ์/ชิ้นส่วน อ่านจาก parts_categories.code (ดู migration_category_codes.sql)
 *   หมวดหลัก = รหัสอุปกรณ์, หมวดย่อย = รหัสชิ้นส่วน
 * - รุ่น คนกรอกเอง เว้นว่างได้ (จะได้ MB-TRKP เฉย ๆ)
 * - type=used ต่อท้าย -U  กันชนกับของใหม่รุ่นเดียวกัน
 * - ชนแล้วต่อ -2, -3, ... ให้เอง
 * - เครื่อง (machine) ใช้คนละแบบ: <อุปกรณ์>-<YYYYMM>-A#### ตามที่มีอยู่เดิมในระบบ
 *
 * ทุกฟังก์ชันขึ้นต้น sku_ และครอบ function_exists ให้ include ซ้ำได้
 ********************************************************************/

if (!function_exists('sku_token')) {
    /**
     * ล้างข้อความให้เป็นท่อนเดียวที่ใช้ใน SKU ได้: ตัวพิมพ์ใหญ่ A-Z 0-9 และ - เท่านั้น
     * "a2338" -> "A2338"   "15 Pro Max" -> "15-PRO-MAX"   "iPad 2 (ขาว)" -> "IPAD-2"
     */
    function sku_token(?string $s, int $max = 24): string {
        $s = strtoupper(trim((string)$s));
        $s = preg_replace('/[^A-Z0-9]+/', '-', $s);   // อะไรที่ไม่ใช่ A-Z0-9 (รวมภาษาไทย) กลายเป็นขีด
        $s = trim((string)$s, '-');
        $s = preg_replace('/-{2,}/', '-', (string)$s);
        return substr((string)$s, 0, $max);
    }
}

if (!function_exists('sku_category_codes')) {
    /**
     * คืน [รหัสอุปกรณ์, รหัสชิ้นส่วน] ของหมวดที่เลือก
     * ถ้าเลือกหมวดหลักตรง ๆ ("วางไว้ในตู้หลัก") จะไม่มีรหัสชิ้นส่วน
     * @return array{0:string,1:string}
     */
    function sku_category_codes(PDO $pdo, int $category_id): array {
        $st = $pdo->prepare(
            "SELECT c.code AS own, c.parent_id, p.code AS parent
             FROM parts_categories c
             LEFT JOIN parts_categories p ON p.id = c.parent_id
             WHERE c.id = ?"
        );
        $st->execute([$category_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['', ''];

        // หมวดย่อย: อุปกรณ์มาจากแม่ ชิ้นส่วนมาจากตัวเอง
        if (!empty($r['parent_id'])) return [(string)$r['parent'], (string)$r['own']];
        // หมวดหลัก: มีแค่รหัสอุปกรณ์
        return [(string)$r['own'], ''];
    }
}

if (!function_exists('sku_exists')) {
    function sku_exists(PDO $pdo, string $sku): bool {
        $st = $pdo->prepare("SELECT 1 FROM inventory WHERE sku = ? LIMIT 1");
        $st->execute([$sku]);
        return (bool)$st->fetchColumn();
    }
}

if (!function_exists('sku_unique')) {
    /** ถ้า $base ถูกใช้ไปแล้ว ต่อ -2, -3, ... จนกว่าจะว่าง */
    function sku_unique(PDO $pdo, string $base): string {
        if ($base === '') $base = 'ITEM';
        if (!sku_exists($pdo, $base)) return $base;
        for ($i = 2; $i <= 99; $i++) {
            $try = $base . '-' . $i;
            if (!sku_exists($pdo, $try)) return $try;
        }
        // เกิน 99 ตัว (ไม่น่าเกิดขึ้น) — กันไม่ให้ INSERT ล้ม
        return $base . '-' . strtoupper(substr(uniqid(), -5));
    }
}

if (!function_exists('sku_build')) {
    /**
     * ประกอบ SKU ของอะไหล่ (new / used)
     *
     * @param string $model  รุ่นที่คนกรอก เช่น "A2338", "15PM" — เว้นว่างได้
     * @param string $type   new | used   (used ได้ -U ต่อท้าย)
     */
    function sku_build(PDO $pdo, int $category_id, string $model = '', string $type = 'new'): string {
        list($dev, $part) = sku_category_codes($pdo, $category_id);

        $bits = array_filter([$dev, $part, sku_token($model)], fn($b) => $b !== '');
        $base = implode('-', $bits);

        // ไม่มีรหัสหมวดเลย (หมวดที่เพิ่มเองแล้วยังไม่ใส่ code) — อย่าปล่อยให้ได้ SKU ว่าง
        if ($base === '') $base = strtoupper($type === 'used' ? 'USED' : 'ITEM');

        if ($type === 'used') $base .= '-U';

        return sku_unique($pdo, $base);
    }
}

if (!function_exists('sku_build_machine')) {
    /**
     * รหัสเครื่อง: <อุปกรณ์>-<YYYYMM>-A####  (แบบเดียวกับที่มีอยู่เดิม เช่น MB-202510-A0078)
     * เลขรันนับต่อจากเลขสูงสุดที่เคยออกของอุปกรณ์นั้น ไม่ใช่สุ่ม
     */
    function sku_build_machine(PDO $pdo, int $category_id): string {
        list($dev) = sku_category_codes($pdo, $category_id);
        if ($dev === '') $dev = 'OT';
        $ym = date('Ym');

        $st = $pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(sku, '-A', -1) AS UNSIGNED))
             FROM inventory
             WHERE type = 'machine' AND sku LIKE CONCAT(?, '-%-A%')"
        );
        $st->execute([$dev]);
        $next = (int)$st->fetchColumn() + 1;

        return sku_unique($pdo, sprintf('%s-%s-A%04d', $dev, $ym, $next));
    }
}

if (!function_exists('sku_build_sale')) {
    /**
     * รหัสของที่เอาขึ้นขาย: SL-<YYYYMM>-####  เรียงตามเวลา ไม่สุ่ม
     * (ของขายเป็นชิ้นเดี่ยว ไม่ได้ผูกกับรุ่นเหมือนอะไหล่)
     */
    function sku_build_sale(PDO $pdo): string {
        $ym = date('Ym');
        $st = $pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED))
             FROM inventory WHERE sku LIKE CONCAT('SL-', ?, '-%')"
        );
        $st->execute([$ym]);
        $next = (int)$st->fetchColumn() + 1;

        return sku_unique($pdo, sprintf('SL-%s-%04d', $ym, $next));
    }
}
