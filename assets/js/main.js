
  
  // copy เบอร์โทร
  function copyPhone() {
    const btn = document.querySelector('.call-bubble-mobile');
    const text = document.getElementById('copy-text').innerText;
    navigator.clipboard.writeText(text);
    btn.classList.add('expanded');
    setTimeout(() => btn.classList.remove('expanded'), 2500);
  }
  
  // ทำให้ฟังก์ชันเข้าถึงได้ทั่วไฟล์
  window.copyPhone = copyPhone;
  
 //ซ่อนทีมช่าง
 const btn = document.getElementById('toggle-team-btn');
 const more = document.getElementById('team-more');

 btn.addEventListener('click', () => {
   more.classList.toggle('active');
   btn.textContent = more.classList.contains('active') ? 'ซ่อนทีมช่าง' : 'ดูทีมช่างทั้งหมด';
 });


  document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.getElementById("toggle-team-btn");
    const teamMore = document.getElementById("team-more");
  
    toggleBtn.addEventListener("click", () => {
      const isOpen = teamMore.classList.contains("show");
      teamMore.classList.toggle("show");
      toggleBtn.innerText = isOpen ? "ดูทีมช่างทั้งหมด" : "แสดงน้อยลง";
    });
  });

  const atmosphereSwiper = new Swiper(".atmosphereSwiper", {
    loop: true,
    autoplay: {
      delay: 2500,
    },
    pagination: {
      el: ".swiper-pagination",
    },
  });

  const reviewSwiper = new Swiper('.reviewSwiper', {
    loop: true,
    autoplay: {
      delay: 3500,
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
  });

  function toggleDiagnose() {
    const tools = document.getElementById('diagnose-tools');
    const button = document.querySelector('.toggle-btn');

    tools.classList.toggle('hidden');
    button.textContent = tools.classList.contains('hidden') ? 'ดูเพิ่มเติม' : 'ซ่อน';
  }

  function toggleDiagnose() {
    const wrapper = document.querySelector('.diagnose-wrapper');
    const button = document.querySelector('.toggle-btn');

    wrapper.classList.toggle('show');
    button.textContent = wrapper.classList.contains('show') ? 'ซ่อน' : 'ดูเพิ่มเติม';
  }

  const swiper = new Swiper(".featuresSwiper", {
    loop: true,
    spaceBetween: 20,
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
  });
  
  const serviceSwiper = new Swiper(".serviceSwiper", {
    slidesPerView: 1,
    spaceBetween: 20,
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
    breakpoints: {
      768: { slidesPerView: 2 },
      1024: { slidesPerView: 3 },
    },
  });
  
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
  
  function toggleServiceCards(btn) {
    const stack = document.getElementById('serviceCards');
    const isExpanded = stack.classList.contains('expanded');
  
    if (isExpanded) {
      stack.classList.remove('expanded');
      btn.textContent = 'ดูเพิ่มเติม';
      stack.scrollIntoView({ behavior: 'smooth' });
    } else {
      stack.classList.add('expanded');
      btn.textContent = 'ซ่อนบริการ';
      setTimeout(() => {
        btn.scrollIntoView({ behavior: 'smooth', block: 'end' });
      }, 100); // เพิ่ม delay เล็กน้อยเพื่อรอให้ layout ขยายก่อน
    }
  }

document.addEventListener("DOMContentLoaded", () => {
  const elfsightDiv = document.querySelector(".elfsight-app-9fd83cb6-9e93-4c0b-97e4-71341f21af05");
  const observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) {
      const s = document.createElement("script");
      s.src = "https://static.elfsight.com/platform/platform.js";
      s.defer = true;
      document.body.appendChild(s);
      observer.disconnect();
    }
  });
  observer.observe(elfsightDiv);
});

  



  