const CACHE_NAME = 'cmns-cache-v1';
// รายการไฟล์ที่มึงอยากให้มัน "โหลดเก็บไว้" ตั้งแต่แรก
// เช่น หน้าแรก, CSS, JS, หน้าติดต่อเรา
const urlsToCache = [
  '/', // หน้าแรก
  '/index.php', // หรือ index.html ถ้ามึงใช้
  '/assets/css/style.css', // <-- กูแก้ให้
  '/assets/js/main.js', // <-- กูแก้ให้
  '/assets/js/script.js', // <-- กูแก้ให้
  '/assets/img/favicon1.png' // <-- เอาไอคอนเว็บมึงไปก่อน
];

// 1. ตอนที่มัน "ติดตั้ง" (Install)
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache); // ยัดไฟล์ทั้งหมดเข้าแคช
      })
  );
});

// 2. ตอนที่มัน "เรียกหน้า" (Fetch)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // ถ้า "เจอ" ในแคช -> ก็ส่งไฟล์ในแคชกลับไปเลย (ไม่ต้องสนเน็ต)
        if (response) {
          return response;
        }
        // ถ้า "ไม่เจอ" -> ก็ค่อยไปโหลดจากเน็ตจริงๆ
        return fetch(event.request);
      }
    )
  );
});