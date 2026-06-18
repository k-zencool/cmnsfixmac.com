# 💻 CMNS Fix Mac — `cmnsfixmac.com`

ระบบเว็บไซต์ + หลังบ้าน (admin panel) ครบวงจรสำหรับร้านซ่อม Apple และจำหน่ายอะไหล่ที่เชียงใหม่
**Bilingual** (ไทย/อังกฤษ) เน้น SEO ท้องถิ่น, มี Progressive enhancement, ระบบจัดการสต็อก/งานซ่อม/ประกัน และ Unified chat inbox (Facebook + LINE)

> Live: <https://cmnsfixmac.com> · EN mirror: <https://cmnsfixmac.com/en/>

---

## 📑 สารบัญ

- [Tech Stack](#-tech-stack)
- [ความสามารถของโปรเจค (Features)](#-ความสามารถของโปรเจค-features)
- [โครงสร้างไดเรกทอรี](#-โครงสร้างไดเรกทอรี)
- [SEO — รายละเอียดเต็ม](#-seo--รายละเอียดเต็ม)
- [การติดตั้ง & Local Development](#️-การติดตั้ง--local-development)
- [Environment Variables](#-environment-variables)
- [ระบบสิทธิ์ (Auth & Roles)](#-ระบบสิทธิ์-auth--roles)
- [Conventions ที่ต้องรู้](#-conventions-ที่ต้องรู้)
- [Deploy ขึ้น Production](#-deploy-ขึ้น-production)
- [Database](#-database)

---

## 🛠 Tech Stack

| ชั้น | เทคโนโลยี |
|------|-----------|
| Language / Runtime | **PHP 8.1** + Apache (mod_rewrite, mod_headers, mod_deflate, mod_expires) |
| Database | **MySQL 8.0** (PDO, `ERRMODE_EXCEPTION`, prepared statements ทุกที่) |
| Dependency | `vlucas/phpdotenv` (โหลด `.env`) — **ไม่มี Node/npm, ไม่มี build step** |
| Frontend | HTML + CSS ล้วน (no preprocessor) + Vanilla JS |
| CDN libraries | AOS (scroll animation), Swiper (carousel), Material Symbols Rounded (icons) |
| Container | Docker Compose (web + MySQL + phpMyAdmin) |
| Integrations | Facebook Messenger API, LINE Messaging API, Telegram Bot (cron alerts), Google Analytics (gtag) |

> **ไม่มี asset pipeline** — cache-bust ด้วยการเพิ่มเลข `?v=N` ที่ `<link>`/`<script>` เอง

---

## 🚀 ความสามารถของโปรเจค (Features)

### หน้าบ้าน (Public — TH ที่ root, EN ที่ `/en/`)

| ส่วน | URL | รายละเอียด |
|------|-----|------------|
| **หน้าแรก** | `/` · `/en/` | Hero + บริการ + ผลงาน + รีวิว, JSON-LD ครบ |
| **บริการซ่อมรายอุปกรณ์** | `/services/{device}/` | macbook, imac, iphone, ipad, apple-watch, airpods, software |
| **ร้านค้า (Shop)** | `/shop/` | รายการสินค้ามือสอง/อะไหล่, หน้า product detail, ผูกกับ inventory |
| **บทความ (Articles)** | `/articles/` | บล็อก/SEO content, pretty URL `/article/{slug}` รองรับ slug ภาษาไทย, นับยอดวิว |
| **ผลงานซ่อม (Works)** | `/works/` | portfolio งานซ่อมจริง พร้อมรูป before/after |
| **รับซื้อเครื่อง (Buyback)** | `/buyback/` | หน้ารับซื้อ Mac มือสอง |
| **ตรวจสอบประกัน (Warranty)** | `/warranty/` | เช็คสถานะประกัน/งานซ่อมด้วยรหัส |
| **เครื่องมือทดสอบฮาร์ดแวร์ (Testers)** | `/tester/` | 6 ตัว — รันบนเบราว์เซอร์ ไม่ต้องลงโปรแกรม |

**Hardware Testers** (`/tester/`):
- `monitor-tester` — เช็ค dead pixel / สีจอ
- `keyboard-tester` — เช็คปุ่มคีย์บอร์ด
- `microphone-tester` — เช็คไมค์ (Web Audio API)
- `camera-tester` — เช็คกล้อง (getUserMedia)
- `sounds-tester` — เช็คลำโพง L/R
- `touchscreen-tester` — เช็คทัชสกรีน (Pointer Events) + fullscreen

**UX / Progressive enhancement:**
- สลับภาษา TH ⇄ EN อัตโนมัติ (header prepend `/en/` หรือ override ด้วย `$switch_to_lang_url`)
- Dark / Light theme toggle (`theme.js`, จำค่าไว้)
- Page loader, scroll progress bar, custom cursor (desktop), back-to-top
- Floating contact buttons (โทร/LINE/Facebook)
- Service worker (`sw.js`)
- CSS โหลดแบบ non-blocking (`media="print" onload`) + `<noscript>` fallback

### หลังบ้าน (Admin — `/admin/`, session-protected)

| โมดูล | path | หน้าที่ |
|-------|------|---------|
| **Dashboard** | `/admin/dashboard/` | สรุปสถิติภาพรวม |
| **Tracking** | `/admin/tracking/` | ติดตามงานซ่อม (เปิดงาน, แก้ไข, ประวัติ) |
| **Parts inventory** | `/admin/parts/` | อะไหล่ มือ 1 (new) / มือ 2 (used) / เครื่องซาก (donor) + เบิก/แตกเครื่อง |
| **Inventory** | `/admin/inventory/` | สต็อกทั่วไป + จัดการหมวดหมู่ + logs |
| **Shop / Products** | `/admin/shop/` · `/admin/products/` | CRUD สินค้า + จัดการรูป |
| **Articles** | `/admin/articles/` | CRUD บทความ + อัปโหลดรูป |
| **Repairs** | `/admin/repairs/` | บันทึกงานซ่อม (มี slug/meta สำหรับ SEO) |
| **Warranty** | `/admin/warranty/` | งานประกัน + เคลม |
| **Pricing** | `/admin/pricing/` | ตารางราคาซ่อมรายอุปกรณ์ |
| **Reports** | `/admin/reports/` | รายงาน |
| **User** | `/admin/user/` | จัดการผู้ใช้ admin |
| **Chat inbox** | `/admin/chat/` | **กล่องข้อความรวม Facebook + LINE** ในที่เดียว + ตั้งค่า platform |
| **Cron** | `/admin/cron/` | สคริปต์แจ้งเตือน Telegram (เช้า/เย็น) + AI helper |

**Chat system:** `webhook/facebook.php` + `webhook/line.php` รับ webhook → เก็บลง DB → ตอบจากหลังบ้าน (`admin/chat/`). Token เก็บใน table `chat_platform_config` (อ่านผ่าน `includes/chat_config.php`).

---

## 📁 โครงสร้างไดเรกทอรี

```
/                     # หน้า TH (index.php, sitemap.php)
/en/                  # หน้า EN (mirror ของ root, ชื่อไฟล์เดียวกัน)
/services/{device}/   # หน้าบริการรายอุปกรณ์
/shop/                # ร้านค้า + product detail
/articles/  /works/   # บทความ + ผลงาน (มี detail.php)
/buyback/  /warranty/ # รับซื้อ + เช็คประกัน
/tester/{x}-tester/   # เครื่องมือทดสอบฮาร์ดแวร์
/includes/            # db.php, header(_en).php, footer(_en).php, auth.php,
                      #   warranty_lib.php, chat_config.php, chat_helpers.php,
                      #   floating-buttons.php
/admin/               # หลังบ้านทั้งหมด (+ /admin/templates/ = layout partials)
/webhook/             # facebook.php, line.php (รับ chat webhook)
/cron/  /admin/cron/  # cron scripts + Telegram alerts
/assets/css/          # CSS ทั้งหมด (services/, shop/ แยกโฟลเดอร์ย่อย)
/assets/js/           # theme.js, micro.js ฯลฯ
/database/            # full_dump.sql, seed_*.sql, migrations/
/db/                  # migration เก่า/สำรอง
/uploads/             # ไฟล์ที่ผู้ใช้อัปโหลด (products, articles, avatars)
/.well-known/         # ACME / verification
```

**Shared include pattern:** ทุกหน้า public เริ่มด้วย `require_once 'includes/db.php'` → `include 'includes/header.php'` … `include 'includes/footer.php'`. หน้า admin ใช้ `admin/templates/` และเรียก `require_login()` / `require_perms([...])` ที่หัวไฟล์

> หน้าที่มี `<head>` เป็นของตัวเอง (เช่น `index.php`) จะตั้ง `$page_has_own_head = true` แล้ว header.php จะข้ามการ render `<head>` ให้

---

## 🔍 SEO — รายละเอียดเต็ม

โปรเจคนี้ทำ SEO แบบจริงจัง โดยเฉพาะ **Local SEO (เชียงใหม่)** และ **Bilingual hreflang**

### 1. Sitemap แบบ bilingual (`sitemap.php` → rewrite เป็น `/sitemap.xml`)

- สร้าง XML แบบ dynamic ดึงจาก DB (articles, shop products, works/repairs)
- **ทุก `<url>` มี `<xhtml:link rel="alternate" hreflang="...">`** จับคู่ TH ⇄ EN + `x-default` ชี้ EN
- **กฎเหล็ก:** `<loc>` ในไซต์แมป **ต้องตรงกับ `<link rel="canonical">` ของหน้านั้นเป๊ะ** (scheme, trailing slash, query string) ไม่งั้น Google เด้ง _"Alternate page with proper canonical tag"_ แล้วถอด URL ออกจาก index
- Canonical map (verified):
  - home `/` ⇄ `/en/`
  - article detail `/article/{slug}` ⇄ `/en/article/{slug_en}` (fallback `?id=N`)
  - works detail `/works/detail.php?id=N` (ใช้ `?id=` เสมอ ไม่ใช่ pretty URL)
  - shop product `/shop/product-detail.php?id=N`
- Priority/changefreq กำหนดต่อประเภท (home 1.0 weekly, services 0.8 monthly ฯลฯ)
- จำกัด `LIMIT 2000` ต่อ query + ครอบ `try/catch` (table หาย → ข้ามเงียบๆ ไม่พังทั้งไฟล์)

### 2. `robots.txt`

- `Allow: /` แต่ block `/admin/`, `/includes/`, `/vendor/`, `/db/`, `/cron/`, `/uploads/avatars/`
- ชี้ `Sitemap: https://cmnsfixmac.com/sitemap.xml`

### 3. `.htaccess` — โครงสร้าง SEO/security ระดับ server

| ส่วน | ทำอะไร |
|------|--------|
| **HTTPS redirect** | บังคับ https บน production (ข้าม localhost/127.0.0.1, เช็ค `X-Forwarded-Proto`) |
| **Canonical host** | บังคับ `www.` → non-www (301) ให้ตรง canonical |
| **Protect files** | block `.env`, `composer.json/lock`, `CLAUDE.md`, `check_db.php` |
| **Security headers** | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy` |
| **Noindex filter params** | query มี `color/ram_/year_/ssd_/sort/price/filter` → ส่ง `X-Robots-Tag: noindex, follow` (กัน duplicate จากหน้า filter ร้านค้า) |
| **Compression** | gzip (`mod_deflate`) html/css/js/json/xml |
| **Caching** | รูป/ฟอนต์ `max-age=1 ปี immutable`, CSS/JS 30 วัน · บน localhost ปิด cache หมด |
| **301 redirects** | map URL เก่า (`*.php`) → โครงสร้างใหม่ (directory) ทั้ง TH + EN |
| **Pretty URL rewrite** | `/article/{slug}`, `/work/{slug}` → `detail.php?slug=` |

> ⚠️ **Thai slug:** rewrite ต้องใช้ `([^/]+)` **ไม่ใช่** `([\w-]+)` เพราะ `\w` ไม่ match อักษรไทย/unicode

### 4. On-page meta (per-page pattern)

แต่ละหน้าตั้ง meta เองผ่านตัวแปรก่อน include header (`$page_title`, `$page_head_extra`) ตัวอย่างจาก `index.php`:

- `<title>` + `<meta name="description/keywords/author/robots">`
- **hreflang** `th` / `en` / `x-default`
- **Open Graph** ครบ (`og:title/description/image/url/type/locale`)
- **Twitter Card** (`summary_large_image`)
- **Google Analytics** (gtag `G-3WXK9GWN7C`)
- **JSON-LD Structured Data:**
  - `ProfessionalService` — ชื่อร้าน, เบอร์โทร, ที่อยู่เต็ม (PostalAddress เชียงใหม่), `priceRange`, `sameAs` (Facebook/YouTube/LINE/TikTok)
  - `FAQPage` — คำถามพบบ่อย (rich result ใน Google)
- Favicon + preload hero image (`fetchpriority="high"`)

### 5. อื่นๆ

- Google Search Console verification: `googlecb67f2725f9321f2.html`
- รูป hero เป็น **WebP** + `<picture>` fallback PNG, ใส่ `width/height` กัน CLS
- Performance: ตัด render-blocking CSS, hero ลื่น, ลบ particle canvas ที่กิน CPU

---

## ⚙️ การติดตั้ง & Local Development

รันทั้ง stack ด้วย Docker Compose:

```bash
docker compose up --build   # ครั้งแรก (build PHP+Apache image)
docker compose up           # ครั้งต่อไป
docker compose down         # หยุด
```

| Service | URL |
|---------|-----|
| Website | <http://localhost:8000> |
| phpMyAdmin | <http://localhost:8081> |

ติดตั้ง dependency (มีแค่ phpdotenv):

```bash
composer install
```

> DB ถูก seed อัตโนมัติจากไฟล์ใน `database/` ตอน `docker compose up` ครั้งแรก (mount เข้า `/docker-entrypoint-initdb.d`)

---

## 🔐 Environment Variables

สร้าง `.env` ที่ root:

```dotenv
DB_HOST=db
DB_NAME=cmnsfixmac_db
DB_USER=cmns_user
DB_PASS=cmns_password

# Chat integrations (ถ้าใช้)
FB_APP_ID=
FB_APP_SECRET=
FB_VERIFY_TOKEN=cmnsfixmac_verify_2025
LINE_CHANNEL_ACCESS_TOKEN=
```

`includes/db.php` โหลด `.env` ผ่าน `vlucas/phpdotenv` แล้ว expose `$pdo` (PDO, `ERRMODE_EXCEPTION`)

> ⚠️ `.gitignore` กัน `.env`, `.env.local`, `.env.*.local` — **แต่ยังไม่ครอบ `.env.` หรือ `.env.production`** ระวังไฟล์ env ที่ตั้งชื่อนอก pattern หลุดขึ้น repo

---

## 👮 ระบบสิทธิ์ (Auth & Roles)

อยู่ใน `includes/auth.php` — RBAC แบบ permission matrix รองรับ wildcard

**Roles:** `super_admin` · `manager` · `admin` · `staff` · `viewer`

**ฟังก์ชันหลัก:**
```php
require_login();                       // เด้งไป login ถ้ายังไม่ล็อกอิน
require_perms(['parts.new.create']);   // บังคับสิทธิ์ฝั่ง server (เด้งถ้าไม่ผ่าน)
can('parts.new.consume');              // เช็คสิทธิ์ (ใช้ show/hide ปุ่มใน UI)
require_role(['super_admin']);         // เช็คแค่ยศ (แบบเดิม)
```

**Wildcard:** `parts.*` match ทุก `parts.X.Y`, `*` = ทำได้ทุกอย่าง (super_admin)

| Role | สิทธิ์ |
|------|--------|
| `super_admin` | `*` (ทุกอย่าง) |
| `manager` | `parts.*` |
| `admin` | CRUD อะไหล่ทุกแท็บ (new/used/donor) + history |
| `staff` | ดูทุกแท็บ + เบิก/แตกเครื่องได้ แต่สร้าง/แก้/ลบไม่ได้ |
| `viewer` | ดูอย่างเดียว |

---

## 📌 Conventions ที่ต้องรู้

- **Cache-bust** CSS/JS ด้วย `?v=N` เพิ่มเลขเองทุกครั้งที่แก้ (ไม่มี build pipeline)
- **DB** ใช้ PDO prepared statements ผ่าน `$pdo` เสมอ
- **Uploads** ไป `/uploads/` แล้ว serve ตรง → **ต้อง validate MIME + นามสกุล ตอนเขียน**
- **Navbar scroll** ใช้ cached boolean `_navScrolled` กัน thrashing classList ทุก scroll event — รักษา pattern นี้เวลาแก้ `header.php`
- **`admin/index.php`** เป็น redirect stub ไป `login.php` (dashboard จริงอยู่ `admin/dashboard/`)
- **Warranty lib** (`includes/warranty_lib.php`): ทุกฟังก์ชัน prefix `w_` + ครอบ `function_exists` → include ซ้ำได้ปลอดภัย
- หน้าที่มี head เอง: ตั้ง `$page_has_own_head = true` ก่อน include header

---

## 🚢 Deploy ขึ้น Production

> Host เป็นแบบ **FTP-only (ไม่มี shell)** — deploy ด้วย FileZilla

1. Push โค้ดขึ้น GitHub แล้วดึง/อัปขึ้น server
2. **รัน migration บน production เอง** ผ่าน phpMyAdmin > SQL tab
   - เช่น `database/migrations/migration_repairs_seo_tracking.sql` (idempotent, รันซ้ำได้ — เพิ่ม `tracking_id`/`slug`/`meta_desc` ใน `repairs` แก้ปัญหา `/admin/repairs/` 500 บน prod)
3. ถ้าต้องเคลียร์รูป temp/local path ที่พังบน prod: ใช้ `clean_temp_images.php`
   - CLI: `php database/clean_temp_images.php --apply`
   - Web (host ไม่มี shell): ลาก `admin/clean_temp_images.php` ขึ้น server เปิด URL → **ลบไฟล์ออกจาก server หลังใช้** (เครื่องมืออันตราย, เปิดได้เฉพาะ super_admin)
4. Cache-bust `?v=N` ที่ asset ที่แก้
5. รันบีบอัดรูป (ถ้ามี `compress_uploads.php`)

> 💡 push ขึ้น GitHub **≠** deploy ขึ้น production — ต้องอัปไฟล์ + รัน migration บน server เองเสมอ

---

## 🗄 Database

- **Schema หลัก:** `database/full_dump.sql` · seed: `database/seed_*.sql`
- **Migrations:** `database/migrations/` (ใหม่) และ `db/` (เก่า/สำรอง)
- ตารางสำคัญ: `admin_users`, `articles` + `article_images`, `repairs` + `repair_images`, `listings`/`products` + `*_images`, `inventory`, `parts_new`/`parts_used`/`parts_donors`, `tracking`, `chat_platform_config`, ตารางราคา `*_fix_pricing` (macbook/imac/iphone/ipad/applewatch/airpods/software), `youtube_videos`
- MySQL 8 **ไม่มี `ADD COLUMN IF NOT EXISTS`** → migration ใช้ stored procedure เช็ค `information_schema` ก่อน add (รันซ้ำปลอดภัย)

---

*ร้านซ่อม Apple เชียงใหม่ — CMNS Fix Mac · 📞 084-151-1684*
