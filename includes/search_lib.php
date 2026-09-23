<?php
/********************************************************************
 * includes/search_lib.php — ทำให้ช่องค้นหา "พิมพ์อะไรก็เจอ"
 *
 * แก้ 3 อย่างที่ทำให้ค้นไม่เจอทั้งที่ของมีอยู่:
 *   1. ตัวพิมพ์เล็ก/ใหญ่  — collation เป็น _ci อยู่แล้ว ไม่ต้องทำอะไร
 *   2. ช่องว่าง          — พิมพ์ "macmini" แต่ในฐานข้อมูลเก็บ "Mac mini"
 *                          แก้ด้วยการเทียบแบบถอดช่องว่างออกทั้งสองฝั่ง
 *   3. คำย่อ / พิมพ์ตก   — "ipd" ควรเจอ iPad, "mbp" ควรเจอ MacBook
 *                          ใช้ตารางคำพ้องข้างล่าง (จับแบบตรงตัวเท่านั้น
 *                          ไม่ใช่ substring — กันคำสั้นไปลากผลลัพธ์มั่ว)
 *
 * ทุกฟังก์ชันขึ้นต้น srch_ และครอบ function_exists ให้ include ซ้ำได้
 ********************************************************************/

if (!function_exists('srch_aliases')) {
    /**
     * คำย่อ/คำที่พิมพ์ตก -> คำจริงที่เก็บใน tracking.device_type
     * ค่าใน list ต้องพิมพ์เล็กทั้งหมด และเทียบแบบ "ตรงทั้งคำ"
     */
    function srch_aliases(): array {
        static $map = [
            'ipad'        => ['ipd', 'ipda', 'ipad', 'ไอแพด', 'ไอแพต'],
            'iphone'      => ['iph', 'ipone', 'iphon', 'iphone', 'ไอโฟน'],
            'macbook'     => ['mbp', 'mba', 'macbook', 'macbok', 'mackbook', 'แมคบุ๊ค', 'แมคบุค', 'แมคบุ๊ก'],
            'airpods'     => ['apd', 'airpod', 'airpods', 'แอร์พอด', 'หูฟัง'],
            'imac'        => ['imac', 'ไอแมค'],
            'mac mini'    => ['macmini', 'mcmini'],
            'apple watch' => ['applewatch', 'watch', 'นาฬิกา'],
            'notebook'    => ['notebook', 'โน๊ตบุ๊ค', 'โน้ตบุ๊ก', 'โน๊ตบุค'],
        ];
        return $map;
    }
}

if (!function_exists('srch_variants')) {
    /**
     * คำที่พิมพ์มา 1 คำ -> รายการคำที่ควรเอาไปค้นจริง (ตัวมันเอง + คำพ้อง)
     * "ipd" -> ["ipd", "ipad"]      "A2338" -> ["a2338"]
     */
    function srch_variants(string $word): array {
        $w   = mb_strtolower(trim($word));
        $out = [$w];
        if ($w === '') return $out;

        foreach (srch_aliases() as $canonical => $forms) {
            // ตรงทั้งคำเท่านั้น — ถ้าใช้ substring คำอย่าง "a" จะลากมาทั้งร้าน
            if (in_array($w, $forms, true)) $out[] = $canonical;
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('srch_where')) {
    /**
     * สร้างเงื่อนไข WHERE ของคำค้นหนึ่งชุด
     *
     * แต่ละคำที่พิมพ์มาต้องเจอที่ไหนสักที่ (AND ระหว่างคำ)
     * แต่ภายในคำเดียว จะกวาดทุกคอลัมน์ + ทุกคำพ้อง (OR)
     *
     * @param string $q       ข้อความที่พิมพ์มาทั้งก้อน
     * @param array  $cols    คอลัมน์ที่ค้นแบบตรง ๆ
     * @param array  $squash  คอลัมน์ที่ค้นแบบถอดช่องว่างด้วย (ควรเป็นคอลัมน์สั้น)
     * @param array  $params  (by ref) ผูกค่าพารามิเตอร์ให้ PDO
     * @return string[]       เงื่อนไข พร้อมเอาไป implode ด้วย ' AND '
     */
    function srch_where(string $q, array $cols, array $squash, array &$params): array {
        $where = [];
        $words = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $i => $word) {
            $ors = [];
            foreach (srch_variants($word) as $j => $v) {
                $k = ":q{$i}_{$j}";
                $params[$k] = '%' . $v . '%';
                foreach ($cols as $c) $ors[] = "$c LIKE $k";

                // เทียบแบบไม่สนช่องว่าง: "macmini" เจอ "Mac mini", "mac book" เจอ "MacBook"
                $flat = str_replace(' ', '', $v);
                if ($flat !== $v || strpos($v, ' ') !== false) {
                    $k2 = ":f{$i}_{$j}";
                    $params[$k2] = '%' . $flat . '%';
                } else {
                    $k2 = $k;   // ไม่มีช่องว่างในคำค้น ใช้พารามิเตอร์เดิมได้เลย
                }
                foreach ($squash as $c) $ors[] = "REPLACE($c, ' ', '') LIKE $k2";
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
        return $where;
    }
}
