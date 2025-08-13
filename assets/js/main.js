// --- main.js (Corrected and Improved Version) ---

// ใช้ DOMContentLoaded เพื่อให้แน่ใจว่า HTML โหลดเสร็จก่อนที่ JS จะเริ่มทำงาน
document.addEventListener('DOMContentLoaded', function () {

  // --- Swiper Initializations ---
  // เช็คก่อนว่ามี element นี้ในหน้าจริงไหม ก่อนจะ new Swiper
  if (document.querySelector(".atmosphereSwiper")) {
    const atmosphereSwiper = new Swiper(".atmosphereSwiper", {
      loop: true,
      autoplay: {
        delay: 2500,
        disableOnInteraction: false,
      },
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
    });
  }

  if (document.querySelector(".reviewSwiper")) {
    const reviewSwiper = new Swiper('.reviewSwiper', {
      loop: true,
      autoplay: {
        delay: 3500,
        disableOnInteraction: false,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
    });
  }
  
  if (document.querySelector(".featuresSwiper")) {
    const featuresSwiper = new Swiper(".featuresSwiper", { // Changed variable name to avoid conflict
      loop: true,
      spaceBetween: 20,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
    });
  }
  
  // NOTE: You had two initializations for ".serviceSwiper". I've kept the second one
  // because it seems more specific. If you need the first one, adjust accordingly.
  if (document.querySelector(".serviceSwiper")) {
    const swiperService = new Swiper(".serviceSwiper", {
      slidesPerView: "auto",
      spaceBetween: 20,
      centeredSlides: true,
      centeredSlidesBounds: true,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
    });
  }

  // --- Team Toggle Button ---
  const teamToggleButton = document.getElementById('toggle-team-btn');
  const teamMoreSection = document.getElementById('team-more');

  if (teamToggleButton && teamMoreSection) { // เช็คว่ามีทั้งปุ่มและ section ก่อน
    teamToggleButton.addEventListener('click', () => {
      teamMoreSection.classList.toggle('active'); // Use one class, e.g., 'active'
      teamToggleButton.textContent = teamMoreSection.classList.contains('active') ? 'ซ่อนทีมช่าง' : 'ดูทีมช่างทั้งหมด';
    });
  }

  // --- Service Cards Toggle ---
  // We need to attach this to the window scope if it's called from an onclick attribute in HTML
  // Or preferably, select the button here and add the listener.
  // Assuming there's a button with id="toggle-service-btn"
  const serviceToggleButton = document.getElementById('toggle-service-btn');
  if(serviceToggleButton) {
    serviceToggleButton.addEventListener('click', function() {
        toggleServiceCards(this);
    });
  }

  // --- Elfsight Lazy Load (The fix for your error) ---
  const elfsightDiv = document.querySelector(".elfsight-app-9dc2caab-6860-424f-bf62-fa7f8b19d56a"); // Use the correct ID from your index.php
  if (elfsightDiv) { // เช็คว่า div ของ Elfsight มีอยู่จริงไหม
    const observer = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) {
        const s = document.createElement("script");
        s.src = "https://static.elfsight.com/platform/platform.js";
        s.defer = true;
        document.body.appendChild(s);
        observer.disconnect(); // ทำงานครั้งเดียวแล้วหยุด
      }
    });
    observer.observe(elfsightDiv);
  }

}); // End of DOMContentLoaded


// --- Global Functions (if called directly from HTML onclick) ---

// Copy Phone function (keep global if needed for onclick)
function copyPhone() {
  const btn = document.querySelector('.call-bubble-mobile');
  const textElement = document.getElementById('copy-text');
  if (textElement && btn) {
    const text = textElement.innerText;
    navigator.clipboard.writeText(text);
    btn.classList.add('expanded');
    setTimeout(() => btn.classList.remove('expanded'), 2500);
  }
}

// Diagnose Toggle function (Corrected and Combined)
// This function needs to be global if it's called from HTML onclick
function toggleDiagnose() {
  const wrapper = document.querySelector('.diagnose-wrapper');
  const button = document.querySelector('.section-diagnose .toggle-btn'); // More specific selector
  if (wrapper && button) {
    wrapper.classList.toggle('show');
    button.textContent = wrapper.classList.contains('show') ? 'ซ่อน' : 'ดูเพิ่มเติม';
  }
}

// Service Cards Toggle function (Corrected)
// This function needs to be global if it's called from HTML onclick
function toggleServiceCards(btn) {
  const stack = document.getElementById('serviceCards');
  if (stack && btn) {
    const isExpanded = stack.classList.contains('expanded');
    if (isExpanded) {
      stack.classList.remove('expanded');
      btn.textContent = 'ดูเพิ่มเติม';
      // scrollIntoView can be jerky, use with care
      // stack.scrollIntoView({ behavior: 'smooth' });
    } else {
      stack.classList.add('expanded');
      btn.textContent = 'ซ่อนบริการ';
      // setTimeout(() => {
      //   btn.scrollIntoView({ behavior: 'smooth', block: 'end' });
      // }, 100);
    }
  }
}