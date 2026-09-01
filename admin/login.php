<?php
session_start();
include_once realpath(__DIR__ . '/../includes/db.php');
require_once __DIR__ . '/../includes/ua_parser.php';

if (isset($_SESSION['admin_logged_in'])) {
  header("Location: dashboard/");
  exit();
}

$error = '';
if (isset($_GET['kicked'])) {
    $error = 'คุณถูกออกจากระบบจากอุปกรณ์อื่น กรุณาเข้าสู่ระบบใหม่';
}

// เพิ่ม sleep(1) หลอกๆ หน่อย ให้เห็นหลอดโหลดสัก 1 วิ (ถ้าเซิร์ฟเร็วมันจะแวบเดียว)
// ถ้าเอาไปใช้จริงแล้วรำคาญ ลบบรรทัด sleep(1) ทิ้งได้เลย
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['username'])) {
  sleep(1);
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  try {
      $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
      $stmt->execute([$username]);
      $admin = $stmt->fetch();

      if ($admin && password_verify($password, $admin['password']) && (($admin['is_active'] ?? 1) == 1)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['LAST_ACTIVE'] = time();

        // บันทึก session สำหรับหน้า admin/user/ (ออนไลน์ตอนนี้ / อุปกรณ์ / บังคับออกจากระบบ)
        // กัน error ไว้ เผื่อยังไม่ได้รัน migration_admin_sessions.sql — ต้องไม่บล็อกการ login
        try {
            $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
            $pdo->prepare("INSERT INTO admin_sessions (admin_id, session_hash, ip, user_agent, device_label) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $admin['id'],
                    hash('sha256', session_id()),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $ua,
                    parse_device_label($ua),
                ]);
        } catch (Throwable $e) {
            error_log('admin_sessions insert failed: ' . $e->getMessage());
        }

        header("Location: dashboard/");
        exit();
      } else {
        $error = "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง";
      }
  } catch (PDOException $e) {
      $error = "System Error: " . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <!-- viewport-fit=cover จำเป็นสำหรับ env(safe-area-inset-*) ตอนรันเป็น PWA เต็มจอบน iPhone -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Administrator Login | CMNS FixMac</title>
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />

  <?php include __DIR__ . '/templates/pwa_head.php'; ?>

  <script>
    (function() {
      // เดียวกับ header_admin.php — ต้อง sync กันเป๊ะ ไม่งั้นจอกระพริบสลับสีตอนเข้าหน้า dashboard
      // ยังไม่เคยเลือกเอง = ตามโหมดของเครื่อง (prefers-color-scheme)
      const stored = localStorage.getItem('admin_theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const savedTheme = stored || (prefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="image" href="/assets/img/admin-login-bg.webp">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

  <style>
    /* ===========================================================
       Palette — ดูดสีจริงจาก assets/img/Logo1.png
       ดำ #05090b (แอปเปิ้ล) / ส้ม #f97104 (ns) / เขียว #0df05f (ส่วนโค้ง)
       ส้มใช้เป็น accent อย่างเดียว ไม่เอามาถมเต็มพื้นเพราะแสบตา
       =========================================================== */
    :root {
      --ink:        #05090b;
      --brand:      #f97104;
      --brand-dark: #d95f00;
      --green:      #0df05f;
      --green-deep: #0aa843;

      /* แถบโลโก้ — โหมดสว่างขาวล้วน ส้มเก็บไว้แค่ปุ่ม CTA */
      --bar-bg:     #ffffff;
      --bar-end:    #ffffff;

      --page-bg:    #eef0f3;
      --card-bg:    #ffffff;
      --field-bg:   #f4f5f7;
      --text-main:  #14161a;
      --text-muted: #767a84;
      --divider:    #e6e8ec;
      --card-ring:  rgba(0, 0, 0, 0.06);

      /* brand panel — โหมดสว่าง: ไม่ใช้ฝ้าขาวคลุม (คลุมแล้วเป็นหมอก)
         แต่ไปดึงตัวรูปให้สว่างขึ้นแทน รูปเลยเห็นชัดแต่ยังเป็นโทนสว่าง */
      --panel-base:    #f7f8fa;
      --photo-filter:  brightness(2.1) saturate(0.75) contrast(0.82);
      --photo-opacity: 0.85;
      /* ทึบเฉพาะฝั่งซ้ายที่มีตัวหนังสือ ฝั่งขวาปล่อยโล่งให้เห็นรูป */
      --panel-veil:    linear-gradient(100deg, rgba(255,255,255,0.94) 0%, rgba(255,255,255,0.86) 34%, rgba(255,255,255,0.42) 66%, rgba(255,255,255,0.08) 100%);
      --panel-text:    #14161a;
      --panel-sub:     #565b64;
      --panel-chip-bg: rgba(5, 9, 11, 0.05);
      --panel-chip-bd: rgba(5, 9, 11, 0.10);
      --glow-o:        rgba(249, 113, 4, 0.30);
      --glow-g:        rgba(13, 240, 95, 0.16);
      --feat-bg:       rgba(10, 168, 67, 0.10);
      --feat-bd:       rgba(10, 168, 67, 0.22);
      --feat-fg:       var(--green-deep);
      --toggle-bg:     rgba(255, 255, 255, 0.78);
      --toggle-bd:     rgba(5, 9, 11, 0.10);
      --toggle-fg:     #14161a;
    }
    [data-theme="dark"] {
      --page-bg:    #08090b;
      --card-bg:    #131417;
      --field-bg:   #1e2024;
      --text-main:  #e9eaec;
      --text-muted: #9a9ea7;
      --divider:    #2a2d33;
      --card-ring:  rgba(255, 255, 255, 0.07);

      /* brand panel — โหมดมืด: หรี่รูปลง ตัวหนังสือสีขาว */
      --panel-base:    #05090b;
      --photo-filter:  brightness(0.62) saturate(0.9);
      --photo-opacity: 1;
      --panel-veil:    linear-gradient(100deg, rgba(5,9,11,0.92) 0%, rgba(5,9,11,0.82) 34%, rgba(5,9,11,0.42) 66%, rgba(5,9,11,0.10) 100%);
      --panel-text:    #ffffff;
      --panel-sub:     rgba(255, 255, 255, 0.78);
      --panel-chip-bg: rgba(255, 255, 255, 0.10);
      --panel-chip-bd: rgba(255, 255, 255, 0.16);
      --glow-o:        rgba(249, 113, 4, 0.42);
      --glow-g:        rgba(13, 240, 95, 0.20);
      --feat-bg:       rgba(13, 240, 95, 0.13);
      --feat-bd:       rgba(13, 240, 95, 0.22);
      --feat-fg:       var(--green);
      --toggle-bg:     rgba(255, 255, 255, 0.12);
      --toggle-bd:     rgba(255, 255, 255, 0.18);
      --toggle-fg:     #ffffff;

      /* แถบโลโก้โหมดมืด — ถ่านเข้ม ไม่ใช่ส้ม */
      --bar-bg:     linear-gradient(180deg, #1a1c20 0%, #101216 100%);
      --bar-end:    #101216;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { background: var(--page-bg); }
    body {
      font-family: 'Sarabun', sans-serif;
      background: var(--page-bg);
      color: var(--text-main);
      min-height: 100vh;
      min-height: 100dvh;
      touch-action: manipulation;
      -webkit-tap-highlight-color: transparent;
      overscroll-behavior-y: none;
    }

    /* dvh = ความสูงจอจริงหลังหักแถบ Safari ถ้าใช้ vh เฉยๆ บน iOS จะเกินจอแล้วเลื่อนได้
       (บรรทัด vh ไว้ให้ browser เก่าที่ไม่รู้จัก dvh) */
    .auth { display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh; }
    .brand-side { display: flex; flex-direction: column; }

    /* ===================== BRAND PANEL (รูปร้าน + overlay) ===================== */
    .brand-panel {
      position: relative;
      overflow: hidden;
      background: var(--panel-base);
      /* padding-top เผื่อคลื่นที่ยื่นลงมาจากแถบโลโก้ (34px) */
      padding: 52px 26px 88px;
      color: var(--panel-text);
    }
    /* ชั้นรูปแยกออกมาเป็น layer ของตัวเอง จะได้ใส่ filter ที่ตัวรูปได้
       โดยไม่โดนตัวหนังสือข้างในไปด้วย
       รูป: Unsplash (ฟรี ใช้เชิงพาณิชย์ได้ ไม่ต้องให้เครดิต)
       ต้นฉบับ https://unsplash.com/photos/photo-1581244249923-172ef5029576
       ย่อ+บีบเป็น webp 1600px q70 = 38KB แล้ว
       อยากเปลี่ยนรูปแก้ url() บรรทัดล่างบรรทัดเดียว */
    .brand-panel::after {
      content: '';
      position: absolute; inset: 0; z-index: 0;
      background: url('/assets/img/admin-login-bg.webp') center/cover no-repeat;
      filter: var(--photo-filter);
      opacity: var(--photo-opacity);
      pointer-events: none;
    }
    /* veil ไล่เฉด: ทึบฝั่งตัวหนังสือ จางฝั่งที่ปล่อยให้เห็นรูป */
    .brand-panel::before {
      content: '';
      position: absolute; inset: 0; z-index: 1;
      background: var(--panel-veil);
      pointer-events: none;
    }
    /* ดวงไฟส้ม/เขียวจางๆ ลอยอยู่หลังภาพ — ลูกเล่นแบบไม่แสบตา */
    .glow {
      position: absolute; z-index: 1; border-radius: 50%; filter: blur(58px); pointer-events: none;
    }
    .glow-orange {
      width: 260px; height: 260px; top: -80px; right: -70px;
      background: var(--glow-o);
      animation: drift-a 14s ease-in-out infinite alternate;
    }
    .glow-green {
      width: 190px; height: 190px; bottom: -30px; left: -60px;
      background: var(--glow-g);
      animation: drift-b 17s ease-in-out infinite alternate;
    }
    @keyframes drift-a { to { transform: translate(-26px, 30px) scale(1.12); } }
    @keyframes drift-b { to { transform: translate(30px, -22px) scale(1.1); } }
    @media (prefers-reduced-motion: reduce) {
      .glow-orange, .glow-green { animation: none; }
    }

    /* flex column กัน chip กับ logo-plate (ทั้งคู่เป็น inline-flex) ไปเรียงชิดกันบรรทัดเดียว */
    .brand-inner {
      position: relative; z-index: 2;
      display: flex; flex-direction: column; align-items: flex-start;
    }

    .status-chip {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 6px 13px 6px 10px;
      border-radius: 999px;
      background: var(--panel-chip-bg);
      border: 1px solid var(--panel-chip-bd);
      font-size: 12px; color: var(--panel-text);
      margin-bottom: 18px;
      backdrop-filter: blur(6px);
    }
    .status-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 0 3px rgba(13, 240, 95, 0.22);
    }

    /* ===================== LOGO BAR (โซนของตัวเอง ไม่ทับรูป) =====================
       สว่าง = แถบขาว + Logo1.png ตัวเดียวกับ nav ของเว็บหลัก
       มืด   = แถบถ่าน + logo-on-dark.png (เจนจาก Logo1.png เปลี่ยนส่วนดำเป็นขาว ส้ม/เขียวคงไว้)
       ไม่ใช้ส้มเต็มแถบแล้ว แสบตา */
    .brand-bar {
      position: relative;
      z-index: 3;
      background: var(--bar-bg);
      padding: calc(26px + env(safe-area-inset-top)) 26px 24px;
      display: flex; justify-content: center;
    }
    .brand-logo { width: 152px; height: auto; display: block; }
    .logo-on-dark { display: none; }
    [data-theme="dark"] .logo-on-light { display: none; }
    [data-theme="dark"] .logo-on-dark  { display: block; }

    /* คลื่นแบ่งโซน — เติมด้วยสีปลายไล่เฉดของแถบ รอยต่อเลยเนียน */
    .bar-wave {
      position: absolute; top: 100%; left: 0;
      width: 100%; height: 34px;
      display: block; margin-top: -1px;
      fill: var(--bar-end);
      pointer-events: none;
    }

    .brand-title {
      margin-top: 20px;
      font-size: 26px; font-weight: 700; line-height: 1.42; letter-spacing: -0.3px;
    }
    .brand-title em { font-style: normal; color: var(--brand); }
    .brand-sub {
      margin-top: 9px;
      font-size: 14px; font-weight: 300; line-height: 1.65;
      color: var(--panel-sub);
      max-width: 42ch;
    }

    .feats { list-style: none; margin-top: 24px; display: flex; flex-direction: column; gap: 12px; }
    .feats li { display: flex; align-items: center; gap: 11px; font-size: 13.5px; color: var(--panel-text); }
    .feats .ico {
      width: 32px; height: 32px; flex-shrink: 0;
      border-radius: 10px;
      display: grid; place-items: center;
      background: var(--feat-bg);
      border: 1px solid var(--feat-bd);
      color: var(--feat-fg);
    }
    .feats .ico .material-symbols-rounded { font-size: 18px; }

    /* ===================== FORM PANEL ===================== */
    .form-panel {
      position: relative; z-index: 3;
      margin-top: -52px;
      background: var(--card-bg);
      border-radius: 30px 30px 0 0;
      padding: 32px 24px calc(32px + env(safe-area-inset-bottom));
      flex: 1;
    }
    .form-card { width: 100%; max-width: 380px; margin: 0 auto; }

    .form-card h1 { font-size: 23px; font-weight: 700; text-align: center; letter-spacing: -0.2px; }
    .form-sub { text-align: center; color: var(--text-muted); font-size: 13.5px; margin-top: 6px; margin-bottom: 24px; }

    .error {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #ef4444;
      padding: 11px 14px; border-radius: 14px; font-size: 13.5px; margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px; line-height: 1.5;
    }
    .error .material-symbols-rounded { font-size: 19px; flex-shrink: 0; }

    form { display: flex; flex-direction: column; gap: 13px; }
    .input-group { position: relative; }

    .input-icon {
      position: absolute; left: 17px; top: 50%; transform: translateY(-50%);
      color: var(--text-muted); font-size: 21px; z-index: 2; pointer-events: none;
      transition: color 0.2s;
    }
    .input-group:focus-within .input-icon { color: var(--brand); }

    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 15px 48px 15px 50px;
      background: var(--field-bg);
      border: 1.5px solid transparent;
      border-radius: 999px;
      font-size: 15px; color: var(--text-main);
      font-family: 'Sarabun', sans-serif;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input::placeholder { color: var(--text-muted); }
    input[type="text"]:focus, input[type="password"]:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(249, 113, 4, 0.13);
    }

    .toggle-password {
      position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
      cursor: pointer; color: var(--text-muted); font-size: 21px; z-index: 3;
      display: flex; padding: 3px;
    }
    .toggle-password:active { color: var(--brand); }

    button[type="submit"] {
      margin-top: 9px; padding: 15px;
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
      color: #fff; border: none; border-radius: 999px;
      font-size: 16px; font-weight: 600; font-family: 'Sarabun', sans-serif;
      cursor: pointer;
      box-shadow: 0 10px 24px -10px rgba(249, 113, 4, 0.65);
      transition: transform 0.15s, filter 0.15s;
    }
    button[type="submit"]:active { transform: scale(0.985); filter: brightness(1.07); }

    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 24px 0 15px;
      color: var(--text-muted); font-size: 12.5px;
    }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--divider); }

    .contact-links { display: flex; gap: 10px; }
    .contact-item {
      flex: 1;
      display: inline-flex; align-items: center; justify-content: center; gap: 7px;
      padding: 13px 10px;
      background: var(--field-bg);
      border: 1px solid transparent;
      border-radius: 999px;
      color: var(--text-main); text-decoration: none;
      font-size: 13px; font-weight: 500;
      transition: border-color 0.2s, background 0.2s;
    }
    .contact-item .material-symbols-rounded { font-size: 18px; color: var(--brand); }
    .contact-item:hover { border-color: var(--brand); }

    /* มือถือ: บีบให้จบใน 1 หน้าจอ ไม่ต้องเลื่อน
       ตัด list ฟีเจอร์ + ย่อหน้าอธิบายออก เหลือ chip กับหัวข้อพอ
       (วัดแล้วเนื้อหาเดิมเกินจอ ~104px) */
    @media (max-width: 899px) {
      .feats, .brand-sub { display: none; }
      .brand-panel { padding: 44px 26px 62px; }
      .status-chip { margin-bottom: 14px; }
      .brand-title { font-size: 22px; margin-top: 0; }
      .form-panel { padding-top: 26px; }
      .form-card h1 { font-size: 21px; }
      .form-sub { margin-bottom: 20px; }
    }

    /* จอเตี้ย (iPhone SE / จอที่มีแถบ Safari กินที่) บีบอีกชั้นให้ยังจบใน 1 หน้าจอ */
    @media (max-width: 899px) and (max-height: 740px) {
      .brand-bar { padding: calc(16px + env(safe-area-inset-top)) 22px 14px; }
      .brand-logo { width: 118px; }
      .brand-panel { padding: 30px 24px 46px; }
      .brand-title { font-size: 19px; }
      .status-chip { margin-bottom: 10px; padding: 5px 11px 5px 9px; font-size: 11.5px; }
      .form-panel { margin-top: -44px; padding-top: 22px; padding-bottom: calc(18px + env(safe-area-inset-bottom)); }
      .form-card h1 { font-size: 19px; }
      .form-sub { font-size: 12.5px; margin-bottom: 16px; }
      form { gap: 10px; }
      input[type="text"], input[type="password"] { padding: 12px 44px 12px 46px; }
      button[type="submit"] { padding: 13px; margin-top: 6px; }
      .divider { margin: 18px 0 12px; }
      .contact-item { padding: 11px 8px; }
    }

    /* ===================== DESKTOP: แบ่งสองฝั่ง ===================== */
    @media (min-width: 900px) {
      .auth { flex-direction: row; }

      .brand-side { flex: 1.15; }
      .brand-bar { padding: 28px 6vw 26px; justify-content: flex-start; }
      .brand-logo { width: 178px; }

      .brand-panel {
        flex: 1;
        display: flex; align-items: center;
        padding: 70px 6vw 56px;
        border-radius: 0;
      }
      .brand-inner { max-width: 460px; }
      .glow-orange { width: 460px; height: 460px; top: -140px; right: -120px; }
      .glow-green  { width: 340px; height: 340px; bottom: -110px; left: -90px; }
      .brand-title { font-size: 36px; margin-top: 26px; }
      .brand-sub { font-size: 15px; }
      .logo-plate img { width: 150px; }
      .feats { margin-top: 32px; gap: 14px; }
      .feats li { font-size: 14.5px; }

      .form-panel {
        flex: 1;
        margin-top: 0;
        border-radius: 0;
        background: var(--page-bg);
        display: flex; align-items: center; justify-content: center;
        padding: 48px 40px;
      }
      .form-card {
        max-width: 400px;
        background: var(--card-bg);
        border: 1px solid var(--card-ring);
        border-radius: 24px;
        padding: 40px 34px;
        box-shadow: 0 24px 60px -30px rgba(0, 0, 0, 0.35);
      }
    }

    /* ===================== ปุ่มสลับธีม =====================
       เก็บลง localStorage คีย์ 'admin_theme' ตัวเดียวกับ dashboard
       เลือกไว้ตรงนี้แล้วเข้าไปข้างในธีมจะตามมาด้วย */
    .theme-toggle {
      position: fixed;
      top: calc(14px + env(safe-area-inset-top)); right: 14px;
      z-index: 50;
      width: 42px; height: 42px;
      display: grid; place-items: center;
      border-radius: 50%;
      background: var(--toggle-bg);
      border: 1px solid var(--toggle-bd);
      color: var(--toggle-fg);
      cursor: pointer;
      backdrop-filter: blur(10px);
      transition: transform 0.15s, background 0.2s;
    }
    .theme-toggle .material-symbols-rounded { font-size: 21px; }
    .theme-toggle:active { transform: scale(0.92); }
    @media (min-width: 900px) { .theme-toggle { top: 20px; right: 22px; } }

    /* ===================== Pull to Refresh ===================== */
    #pull-refresh {
      position: fixed;
      top: env(safe-area-inset-top, 0px);
      left: 50%;
      transform: translate(-50%, -52px);
      z-index: 998;
      width: 40px; height: 40px;
      display: grid; place-items: center;
      border-radius: 50%;
      background: var(--card-bg);
      border: 1px solid var(--divider);
      box-shadow: 0 6px 18px -8px rgba(0, 0, 0, 0.45);
      opacity: 0;
      pointer-events: none;
    }
    #pull-refresh .ptr-spinner {
      width: 20px; height: 20px;
      border: 2.5px solid var(--field-bg);
      border-top-color: var(--brand);
      border-radius: 50%;
    }
    #pull-refresh.ptr-loading .ptr-spinner { animation: login-spin 0.7s linear infinite; }

    /* ===================== Loading Overlay ===================== */
    .loading-overlay {
      position: fixed; inset: 0;
      background: var(--card-bg);
      z-index: 999;
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      opacity: 0; visibility: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .loading-overlay.active { opacity: 0.98; visibility: visible; }
    .login-spinner {
      width: 40px; height: 40px;
      border: 3px solid var(--field-bg);
      border-top-color: var(--brand);
      border-radius: 50%;
      animation: login-spin 0.7s linear infinite;
      margin-bottom: 15px;
    }
    @keyframes login-spin { to { transform: rotate(360deg); } }
    .loading-text { font-size: 14px; font-weight: 600; color: var(--brand); letter-spacing: 0.3px; }
  </style>
</head>

<body>

  <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับโหมดสว่าง/มืด">
    <span class="material-symbols-rounded" id="themeIcon">light_mode</span>
  </button>

  <div class="auth">

    <div class="brand-side">

      <header class="brand-bar">
        <img class="brand-logo logo-on-light" src="/assets/img/Logo1.png" alt="CMNS FixMac">
        <img class="brand-logo logo-on-dark" src="/assets/img/logo-on-dark.png" alt="CMNS FixMac">
        <svg class="bar-wave" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
          <path d="M0,0 H1440 V18 C1180,56 980,4 720,22 C460,40 250,0 0,28 Z"/>
        </svg>
      </header>

      <aside class="brand-panel">
      <div class="glow glow-orange"></div>
      <div class="glow glow-green"></div>

      <div class="brand-inner">
        <div class="status-chip"><span class="status-dot"></span> ระบบพร้อมใช้งาน</div>

        <h2 class="brand-title">จัดการงานซ่อม<br>ได้ครบ <em>จบในที่เดียว</em></h2>
        <p class="brand-sub">ระบบหลังบ้านสำหรับทีมงาน CMNS FixMac เชียงใหม่ — ติดตามงานซ่อม สต็อกอะไหล่ และใบรับประกัน ได้จากทุกอุปกรณ์</p>

        <ul class="feats">
          <li><span class="ico"><span class="material-symbols-rounded">build</span></span> ติดตามสถานะงานซ่อมแบบเรียลไทม์</li>
          <li><span class="ico"><span class="material-symbols-rounded">inventory_2</span></span> จัดการสต็อกอะไหล่และเครื่องแยกชิ้น</li>
          <li><span class="ico"><span class="material-symbols-rounded">verified_user</span></span> ออกและตรวจสอบใบรับประกัน</li>
        </ul>
      </div>
      </aside>

    </div>

    <main class="form-panel">
      <div class="form-card">
        <h1>เข้าสู่ระบบ</h1>
        <p class="form-sub">สำหรับทีมงานร้านเท่านั้น</p>

        <?php if ($error): ?>
          <div class="error"><span class="material-symbols-rounded">error</span> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
          <div class="input-group">
            <span class="material-symbols-rounded input-icon">person</span>
            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required autofocus autocomplete="username">
          </div>

          <div class="input-group">
            <span class="material-symbols-rounded input-icon">lock</span>
            <input type="password" name="password" id="passwordInput" placeholder="รหัสผ่าน" required autocomplete="current-password">
            <span class="material-symbols-rounded toggle-password" id="togglePassword">visibility</span>
          </div>

          <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <div class="divider">ติดปัญหาการเข้าใช้งาน?</div>

        <div class="contact-links">
          <a href="tel:0612955236" class="contact-item"><span class="material-symbols-rounded">call</span> 061-295-5236</a>
          <a href="https://www.facebook.com/search/top?q=Khun%20Natt" target="_blank" rel="noopener" class="contact-item"><span class="material-symbols-rounded">forum</span> Khun Natt</a>
        </div>
      </div>
    </main>

  </div>

  <div id="pull-refresh" aria-hidden="true"><span class="ptr-spinner"></span></div>

  <div id="loadingOverlay" class="loading-overlay">
    <div class="login-spinner"></div>
    <div class="loading-text">กำลังตรวจสอบข้อมูล...</div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
        // 0. สลับธีม — ใช้คีย์ 'admin_theme' ร่วมกับ dashboard
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon   = document.getElementById('themeIcon');
        const syncIcon = function() {
            const cur = document.documentElement.getAttribute('data-theme');
            if (themeIcon) themeIcon.textContent = cur === 'dark' ? 'light_mode' : 'dark_mode';
        };
        syncIcon();
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('admin_theme', next); } catch (e) {}
                syncIcon();
            });
        }

        // 1. จัดการเรื่องลูกตา (Toggle Password)
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('passwordInput');

        if(togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
                e.preventDefault(); // กันพลาด
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
            });
        }

        // 2. จัดการเรื่องหลอดโหลด (Loading Overlay)
        const loginForm = document.getElementById('loginForm');
        const loadingOverlay = document.getElementById('loadingOverlay');

        if(loginForm && loadingOverlay) {
            loginForm.addEventListener('submit', function() {
                const inputs = loginForm.querySelectorAll('input[required]');
                let isFilled = true;
                inputs.forEach(input => { if (!input.value) isFilled = false; });

                if (isFilled) {
                    loadingOverlay.classList.add('active');
                    const btn = loginForm.querySelector('button[type="submit"]');
                    if(btn) btn.innerText = 'กำลังเข้าสู่ระบบ...';
                }
            });
        }

        // กัน Safari กด Back แล้ว overlay ค้าง
        window.addEventListener('pageshow', function() {
            if (loadingOverlay) loadingOverlay.classList.remove('active');
        });

        // 3. ดึงลงเพื่อรีเฟรช — ตรรกะเดียวกับ admin.js
        // ทำเฉพาะตอนรันเป็น PWA เพราะ browser ปกติมีของตัวเองอยู่แล้ว ใส่ทับจะเด้งสองชั้น
        (function initPullToRefresh() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches
                            || navigator.standalone === true;
            if (!standalone) return;

            const ptr = document.getElementById('pull-refresh');
            if (!ptr) return;

            const THRESHOLD = 70, MAX_PULL = 110, DAMPING = 0.5;
            let startY = 0, pulled = 0, tracking = false, refreshing = false;

            const park = function (animate) {
                ptr.style.transition = animate ? 'transform .22s ease, opacity .22s ease' : '';
                ptr.style.transform  = 'translate(-50%, -52px)';
                ptr.style.opacity    = '0';
            };

            document.addEventListener('touchstart', function (e) {
                if (refreshing || e.touches.length !== 1) return;
                if (window.scrollY > 0) return;
                startY = e.touches[0].clientY;
                pulled = 0;
                tracking = true;
                ptr.style.transition = '';
            }, { passive: true });

            document.addEventListener('touchmove', function (e) {
                if (!tracking || refreshing) return;
                const delta = e.touches[0].clientY - startY;
                if (delta <= 0 || window.scrollY > 0) { tracking = false; park(true); return; }
                if (e.cancelable) e.preventDefault();
                pulled = Math.min(delta * DAMPING, MAX_PULL);
                ptr.style.transform = 'translate(-50%, ' + (pulled - 52) + 'px) rotate(' + (pulled * 4) + 'deg)';
                ptr.style.opacity   = Math.min(pulled / THRESHOLD, 1);
            }, { passive: false });

            document.addEventListener('touchend', function () {
                if (!tracking || refreshing) return;
                tracking = false;
                if (pulled < THRESHOLD) { park(true); return; }

                refreshing = true;
                ptr.classList.add('ptr-loading');
                ptr.style.transition = 'transform .22s ease';
                ptr.style.transform  = 'translate(-50%, 22px)';
                ptr.style.opacity    = '1';
                setTimeout(function () { location.reload(); }, 180);
            }, { passive: true });
        })();
    });
  </script>

</body>
</html>
