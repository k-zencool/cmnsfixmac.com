<?php
require_once '../includes/db.php';

$page_title = 'CMNS Mac: รับซื้อ MacBook, iPhone, iPad, iMac ทุกสภาพ เชียงใหม่ | ให้ราคายุติธรรม';
$page_css   = [
    '/assets/css/buyback-style.css?v=2',
    'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/css/splide.min.css',
    'https://unpkg.com/aos@2.3.4/dist/aos.css',
];

$faqs = [
    ['เครื่องเสียหนักมาก เปิดไม่ติดเลย รับซื้อจริงเหรอ?',
     'จริง! เรารับซื้อทุกสภาพตามที่แจ้งไว้ แม้จะเปิดไม่ติด เราก็ตีราคาเป็นค่าอะไหล่ให้ได้ ลองส่งมาประเมินดูก่อนได้ ไม่เสียหาย'],
    ['รับซื้อเครื่องติด iCloud หรือติดล็อคอื่นๆ ไหม?',
     'รับพิจารณา แต่ราคาจะขึ้นอยู่กับรุ่น สภาพ และประเภทการติดล็อค รบกวนแจ้งรายละเอียดให้ครบถ้วนตอนส่งประเมิน'],
    ['อยู่ต่างจังหวัด ส่งเครื่องไปขายได้ไหม?',
     'ได้ หากไม่สะดวกมาที่เชียงใหม่ สามารถแพ็คเครื่องส่งมาอย่างปลอดภัยได้ หลังจากเราได้รับและตรวจสอบเครื่องเรียบร้อย ตกลงราคากันได้ ก็โอนเงินให้ทันที (แนะนำให้สอบถามขั้นตอนการส่งอย่างละเอียดกับเราก่อน)'],
    ['ใช้เวลานานไหมกว่าจะรู้ราคา และได้เงิน?',
     'การประเมินราคาเบื้องต้นผ่าน LINE รวดเร็วมาก ส่วนใหญ่ภายในไม่กี่ชั่วโมง (ในเวลาทำการ) หากตกลงขายและนัดเจอหรือส่งเครื่องมาถึงเราแล้ว การตรวจสอบและจ่ายเงินก็รวดเร็วเช่นกัน'],
    ['ต้องเตรียมอะไรบ้างตอนขายเครื่อง?',
     'ตัวเครื่องเป็นหลัก หากมีอุปกรณ์เสริมแท้ครบกล่อง (สายชาร์จ, อะแดปเตอร์, กล่อง) ก็จะช่วยให้ได้ราคาดีขึ้น และสำคัญมากคือ อย่าลืม Sign out ออกจาก Apple ID และ iCloud ของคุณก่อน'],
];

ob_start(); ?>
<meta name="description" content="รับซื้อ MacBook, iPhone, iPad, iMac ทุกรุ่น ทุกสภาพ ในเชียงใหม่! ให้ราคายุติธรรม ตรวจสอบเครื่องฟรี นัดรับถึงที่ ติดต่อประเมินราคาผ่าน LINE ง่ายๆ">
<link rel="canonical" href="https://cmnsfixmac.com/buyback/">
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/buyback/">
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/buyback/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/buyback/">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-3WXK9GWN7C');</script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(fn($f) => [
        '@type' => 'Question', 'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

include_once '../includes/header.php';
?>

<main>

<!-- ═══════════════════════════════════════════════ HERO ═══════════════════════════════════════════════ -->
<section class="sv-hero">
  <div class="sv-hero-dots" aria-hidden="true"></div>
  <canvas id="sv-particles" aria-hidden="true"></canvas>
  <div class="sv-hero-orb sv-orb-1" aria-hidden="true"></div>
  <div class="sv-hero-orb sv-orb-2" aria-hidden="true"></div>
  <div class="sv-hero-inner">

    <div class="sv-hero-text" data-aos="fade-right" data-aos-duration="700">
      <span class="sv-eyebrow">
        <span class="material-symbols-rounded">sell</span>
        CMNS Mac · รับซื้อ เชียงใหม่
      </span>
      <h1 class="sv-h1">รับซื้อ MacBook iPhone iPad iMac<br><span class="sv-h1-accent">ทุกรุ่น ทุกสภาพ</span></h1>
      <p class="sv-hero-sub">เครื่องเสีย เปิดไม่ติด จอแตก แบตเสื่อม เราก็รับ!<br>ให้ราคายุติธรรม โอนไว นัดรับถึงที่ หรือส่งมาก็ได้</p>
      <div class="sv-hero-cta">
        <a href="https://page.line.me/cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> ส่งรูปตีราคาผ่าน LINE
        </a>
        <a href="#bb-devices" class="btn btn-ghost">
          ดูรุ่นที่รับซื้อ <span class="material-symbols-rounded">arrow_downward</span>
        </a>
      </div>
      <div class="sv-trust-pills">
        <span class="sv-pill"><span class="material-symbols-rounded">price_check</span> ประเมินฟรี</span>
        <span class="sv-pill"><span class="material-symbols-rounded">handshake</span> รับทุกสภาพ</span>
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> โอนไว</span>
      </div>
      <p class="bb-hero-note">ประเมินฟรี ไม่บังคับขาย เงื่อนไขเป็นไปตามที่ร้านกำหนด ร้านขอสงวนสิทธิ์งดประเมินหากเครื่องไม่เข้าเกณฑ์ หรือร้านไม่สนใจซื้อ และบางเคสอาจต้องนำเครื่องมาตรวจเช็คที่ร้านก่อน</p>
    </div>

    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/buyback/Macbook.webp" alt="รับซื้อ MacBook iPhone iPad iMac ทุกสภาพ เชียงใหม่" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">paid</span>
          <div><strong>1,857 เครื่อง</strong><span>รับซื้อแล้ว</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">verified</span>
          <div><strong>ให้ราคายุติธรรม</strong><span>ประเมินตามสภาพจริง</span></div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════════════ ANCHOR NAV ═══════════════════════════════════════════════ -->
<div class="sv-nav-outer">
  <nav class="sv-nav" data-aos="fade-up" data-aos-offset="0" aria-label="เมนูหน้ารับซื้อ">
    <a href="#bb-devices" class="sv-nav-item"><span class="material-symbols-rounded">devices</span><span>รุ่นที่รับซื้อ</span></a>
    <a href="#bb-why"     class="sv-nav-item"><span class="material-symbols-rounded">verified_user</span><span>ทำไมขายกับเรา</span></a>
    <a href="#bb-steps"   class="sv-nav-item"><span class="material-symbols-rounded">list_alt</span><span>ขั้นตอน</span></a>
    <a href="#bb-gallery" class="sv-nav-item"><span class="material-symbols-rounded">photo_library</span><span>ตัวอย่าง</span></a>
    <a href="#bb-faq"     class="sv-nav-item"><span class="material-symbols-rounded">help_outline</span><span>คำถามพบบ่อย</span></a>
    <a href="#bb-contact" class="sv-nav-item"><span class="material-symbols-rounded">location_on</span><span>ติดต่อ</span></a>
  </nav>
</div>

<!-- ═══════════════════════════════════════════════ WHY US ═══════════════════════════════════════════════ -->
<section class="sv-section sv-services" id="bb-why">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ทำไมขายกับเรา</span>
      <h2>ขายอุปกรณ์ Apple กับ CMNS Mac ดียังไง</h2>
      <p class="sv-desc">ร้านคนเชียงใหม่ ประเมินตามสภาพจริง ไม่กดราคา โอนไว รับทุกสภาพ</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['thumb_up',       'ให้ราคายุติธรรมที่สุดในเชียงใหม่', 'ประเมินตามสภาพจริง ไม่กดราคา บอกราคาตรงไปตรงมา'],
          ['flash_on',       'ประเมินราคาไว',                    'รู้ผลเบื้องต้นรวดเร็วผ่าน LINE ภายในไม่กี่ชั่วโมง'],
          ['handshake',      'รับซื้อทุกสภาพ',                   'เครื่องสวย มีตำหนิ จอแตก เปิดไม่ติด ติด iCloud เราก็พิจารณา'],
          ['local_shipping', 'สะดวกสบาย',                        'นัดรับถึงที่ในตัวเมืองเชียงใหม่และใกล้เคียง หรือส่งมาก็ได้'],
          ['paid',           'รับเงินทันที',                     'ตกลงราคาได้ รับเงินสด/โอนไว ไม่ต้องรอ'],
          ['storefront',     'ร้านคนเชียงใหม่ เข้าใจคนเจียงใหม่', 'บริการเป็นกันเอง จริงใจ ปรึกษาได้ทุกเรื่อง'],
      ] as $i => [$icon, $title, $desc]): ?>
      <div class="sv-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="sv-card-icon"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ DEVICES WE BUY ═══════════════════════════════════════════════ -->
<section class="sv-section bb-devices" id="bb-devices">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">รุ่นที่รับซื้อ</span>
      <h2>เรารับซื้ออุปกรณ์ Apple รุ่นไหนบ้าง?</h2>
      <p class="sv-desc">ทุกสภาพ! ตั้งแต่เครื่องสวยกริ๊บ ยันเปิดไม่ติด ลองส่งมาประเมินราคาก่อนได้</p>
    </div>
    <div class="bb-device-grid">
      <?php foreach ([
          ['laptop_mac',  'MacBook ทุกรุ่น', '/assets/img/buyback/Macbook.webp', 'รับซื้อ MacBook Air, MacBook Pro ทุกขนาดจอ ชิป Intel, M1–M4 ตั้งแต่ปี 2015 ขึ้นไป สภาพไหนก็รับ'],
          ['smartphone',  'iPhone ทุกรุ่น',  '/assets/img/buyback/iPhone.webp',  'รับซื้อ iPhone 11–16 ทุก Pro / Pro Max / Mini / Plus หรือรุ่นเก่ากว่าลองสอบถาม จอแตก ติดล็อค ก็รับพิจารณา'],
          ['tablet_mac',  'iPad ทุกรุ่น',    '/assets/img/buyback/iPad.webp',    'รับซื้อ iPad, iPad Air, iPad Pro, iPad mini ทุก Gen ตั้งแต่ปี 2015 ขึ้นไป จอเสีย แบตเสื่อม เสนอมาได้'],
          ['desktop_mac', 'iMac ทุกรุ่น',    '/assets/img/buyback/iMac.webp',    'รับซื้อ iMac จอ 21.5", 24", 27" ชิป Intel, M1, M3 ตั้งแต่ปี 2015 ขึ้นไป เครื่องสวยหรือมีปัญหาก็รับ'],
      ] as $i => [$icon, $title, $img, $desc]): ?>
      <div class="bb-dcard" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
        <div class="bb-dcard-img">
          <img src="<?= $img ?>" alt="รับซื้อ <?= $title ?> เชียงใหม่" loading="lazy" decoding="async">
        </div>
        <div class="bb-dcard-body">
          <h3 class="bb-dcard-title"><span class="material-symbols-rounded"><?= $icon ?></span><?= $title ?></h3>
          <p><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="bb-devices-foot">รุ่นอื่นๆ หรืออุปกรณ์ Apple Watch, Accessories ก็ลองสอบถามได้!</p>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ STEPS ═══════════════════════════════════════════════ -->
<section class="sv-section bb-process" id="bb-steps">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ขั้นตอน</span>
      <h2>ขายอุปกรณ์ Apple กับเรา ง่ายแค่ 4 ขั้นตอน</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'photo_camera',  'ถ่ายรูปเครื่องของคุณ',   'หลายๆ มุม ชัดๆ โดยเฉพาะตำหนิ (ถ้ามี)'],
          ['2', 'chat_bubble',   'ส่งข้อมูลผ่าน LINE',     'บอกรุ่น สเปคคร่าวๆ และสภาพเครื่อง พร้อมแนบรูป'],
          ['3', 'request_quote', 'รอรับราคาประเมิน',       'เราจะตีราคาเบื้องต้นให้คุณทราบอย่างรวดเร็ว'],
          ['4', 'local_mall',    'นัดหมาย/จัดส่ง และรับเงิน', 'นัดเจอเช็คเครื่องจริง (ในเชียงใหม่) หรือส่งมา ตกลงกันได้ รับเงินทันที!'],
      ] as $i => [$num, $icon, $title, $desc]): ?>
      <div class="sv-step" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
        <div class="sv-step-num"><?= $num ?></div>
        <div class="sv-step-icon"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h4><?= $title ?></h4>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ SAMPLE GALLERY ═══════════════════════════════════════════════ -->
<section class="sv-section bb-gallery" id="bb-gallery">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ตัวอย่างที่รับซื้อ</span>
      <h2>ตัวอย่างเครื่องที่เราเคยรับซื้อ</h2>
      <p class="sv-desc">สภาพไหนก็รับจริง! ตั้งแต่เครื่องนางฟ้า ยันเปิดไม่ติด</p>
    </div>
    <div class="splide" data-aos="fade-up">
      <div class="splide__track">
        <ul class="splide__list">
          <?php foreach ([
              ['sample-mbp-good.webp',      'MacBook Pro สภาพดี',   'MacBook Pro ปี 2020 (สภาพนางฟ้า)'],
              ['sample-iphone-broken.webp', 'iPhone จอแตก',         'iPhone 12 (จอแตก แต่ยังรับ)'],
              ['sample-imac-ok.webp',       'iMac สภาพใช้งาน',      'iMac 2019 (มีรอยบ้าง แต่ใช้งานปกติ)'],
              ['sample-ipad-bad.webp',      'iPad เปิดไม่ติด',      'iPad Air 3 (เปิดไม่ติด ก็ให้ราคาอะไหล่)'],
              ['sample-macbook-icloud.webp','MacBook ติด iCloud',  'MacBook Air M1 (ติด iCloud ก็รับปรึกษา)'],
          ] as [$img, $alt, $cap]): ?>
          <li class="splide__slide">
            <img src="/assets/img/buyback/<?= $img ?>" alt="<?= $alt ?>" loading="lazy">
            <figcaption class="splide-caption"><?= $cap ?></figcaption>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ STATS ═══════════════════════════════════════════════ -->
<section class="sv-stats">
  <div class="sv-container sv-stats-inner">
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="0">
      <span class="material-symbols-rounded">sell</span>
      <strong class="counter" data-count="1857">0</strong>
      <span>เครื่องที่รับซื้อแล้ว</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="80">
      <span class="material-symbols-rounded">price_check</span>
      <strong>ฟรี</strong>
      <span>ประเมินราคา ไม่มีค่าใช้จ่าย</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="160">
      <span class="material-symbols-rounded">bolt</span>
      <strong>โอนไว</strong>
      <span>ตกลงราคาได้ รับเงินทันที</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="240">
      <span class="material-symbols-rounded">handshake</span>
      <strong>ทุกสภาพ</strong>
      <span>เปิดไม่ติด ติดล็อค ก็รับ</span>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ FAQ ═══════════════════════════════════════════════ -->
<section class="sv-section sv-faq" id="bb-faq">
  <div class="sv-container sv-faq-wrap">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">FAQ</span>
      <h2>คำถามที่พบบ่อย</h2>
    </div>
    <div class="sv-faq-list" data-aos="fade-up">
      <?php foreach ($faqs as $f): ?>
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span><?= htmlspecialchars($f[0], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="material-symbols-rounded faq-arr">expand_more</span>
        </button>
        <div class="faq-a"><p><?= htmlspecialchars($f[1], ENT_QUOTES, 'UTF-8') ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ CTA ═══════════════════════════════════════════════ -->
<section class="sv-cta" id="bb-contact">
  <div class="sv-container">
    <div class="sv-cta-inner" data-aos="fade-up">
      <span class="material-symbols-rounded sv-cta-icon">sell</span>
      <h2>มีเครื่อง Apple อยากขาย?<br>ส่งรูปมาตีราคาเลย</h2>
      <p>ประเมินฟรี ไม่บังคับขาย · รับทุกสภาพ · โอนไว<br>นัดรับในตัวเมืองเชียงใหม่ หรือส่งมาก็ได้ จ.–ส. 9:00–19:00</p>
      <div class="sv-cta-btns">
        <a href="https://page.line.me/cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> ตีราคาผ่าน LINE
        </a>
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://maps.app.goo.gl/bDboFFwykRSCSMX7A" target="_blank" rel="noopener" class="btn btn-ghost">
          <span class="material-symbols-rounded">location_on</span> ดูแผนที่
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ MAP ═══════════════════════════════════════════════ -->
<section class="sv-section bb-map-section">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">จุดนัดรับ</span>
      <h2>ติดต่อเรา / จุดนัดรับในเชียงใหม่</h2>
    </div>
    <p class="bb-map-intro" data-aos="fade-up">ติดต่อสอบถาม นัดดูเครื่อง หรือประเมินราคาได้เลย หลักๆ เราจะนัดรับในตัวเมืองเชียงใหม่ หรือตามตกลง (โทรหรือ LINE มาคุยกันก่อนได้)</p>
    <div class="bb-map-frame" data-aos="fade-up">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3497.641581228488!2d98.967748!3d18.751606100000004!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e1!3m2!1sth!2sth!4v1748670162801!5m2!1sth!2sth"
        allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="แผนที่ CMNS Fix Mac เชียงใหม่"></iframe>
    </div>
  </div>
</section>

</main>

<?php include_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/js/splide.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });

/* Sample gallery carousel */
if (document.querySelector('.splide')) {
    new Splide('.splide', {
        type: 'loop',
        perPage: 3,
        gap: '1.25rem',
        autoplay: true,
        pauseOnHover: true,
        breakpoints: { 768: { perPage: 2 }, 576: { perPage: 1 } }
    }).mount();
}

/* FAQ accordion */
document.querySelectorAll('.faq-q').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.faq-item');
        const open = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
        if (!open) item.classList.add('open');
    });
});

/* Counter — animate once when scrolled into view */
document.querySelectorAll('.counter').forEach(counter => {
    const target = +counter.getAttribute('data-count');
    const io = new IntersectionObserver((entries, obs) => {
        if (!entries[0].isIntersecting) return;
        obs.disconnect();
        const step = Math.max(1, Math.ceil(target / 120));
        const tick = () => {
            const cur = +counter.innerText.replace(/,/g, '');
            if (cur < target) {
                counter.innerText = Math.min(cur + step, target).toLocaleString();
                requestAnimationFrame(tick);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };
        tick();
    }, { threshold: 0.4 });
    io.observe(counter);
});

/* Hero particles */
(function () {
  const canvas = document.getElementById('sv-particles');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const hero = canvas.closest('.sv-hero');
  const COUNT = 45;
  let W, H, particles = [], raf;

  function resize() {
    const dpr = window.devicePixelRatio || 1;
    W = hero.offsetWidth; H = hero.offsetHeight;
    canvas.width = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }
  function mkParticle(randomY) {
    const maxLife = 220 + Math.random() * 180;
    return {
      x: Math.random() * W, y: randomY ? Math.random() * H : H + 8,
      r: Math.random() * 1.6 + 0.4, vy: -(Math.random() * 0.35 + 0.12),
      vx: (Math.random() - 0.5) * 0.18, life: randomY ? Math.random() * maxLife : 0,
      maxLife: maxLife, isAccent: Math.random() > 0.55
    };
  }
  function init() { particles = Array.from({ length: COUNT }, () => mkParticle(true)); }
  function draw() {
    ctx.clearRect(0, 0, W, H);
    const dark = isDark();
    for (let i = 0; i < particles.length; i++) {
      const p = particles[i];
      p.x += p.vx; p.y += p.vy; p.life++;
      if (p.life >= p.maxLife || p.y < -10) { particles[i] = mkParticle(false); continue; }
      const t = p.life / p.maxLife;
      const alpha = t < 0.15 ? t / 0.15 : t > 0.75 ? (1 - t) / 0.25 : 1;
      let opacity;
      if (p.isAccent) {
        opacity = alpha * (dark ? 0.55 : 0.35);
        ctx.fillStyle = `rgba(252,116,4,${opacity})`;
      } else {
        opacity = alpha * (dark ? 0.22 : 0.12);
        ctx.fillStyle = dark ? `rgba(255,255,255,${opacity})` : `rgba(80,80,80,${opacity})`;
      }
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill();
    }
    raf = requestAnimationFrame(draw);
  }
  resize(); init(); draw();
  window.addEventListener('resize', function () { cancelAnimationFrame(raf); resize(); init(); draw(); }, { passive: true });
})();
</script>
</body>
</html>
