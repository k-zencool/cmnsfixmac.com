document.addEventListener("DOMContentLoaded", () => {
    const serviceBoxes = document.querySelectorAll(".service-box");
  
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target); // แสดงแค่ครั้งเดียว
        }
      });
    }, {
      threshold: 0.1
    });
  
    serviceBoxes.forEach(box => observer.observe(box));
  });
  document.addEventListener("DOMContentLoaded", () => {
    const extraCards = document.querySelectorAll(".service-box.extra");
    const toggleBtn = document.getElementById("toggleBtn");
    let isExpanded = false;
  
    toggleBtn.addEventListener("click", () => {
      isExpanded = !isExpanded;
  
      extraCards.forEach(card => {
        card.style.display = isExpanded ? 'block' : 'none';
      });
  
      toggleBtn.textContent = isExpanded ? "แสดงน้อยลง" : "ดูเพิ่มเติม";
    });
  });
  
  document.addEventListener("DOMContentLoaded", () => {
    const extraCards = document.querySelectorAll(".service-box.extra");
    const toggleBtn = document.getElementById("toggleBtn");
    let isExpanded = false;
  
    toggleBtn.addEventListener("click", () => {
      isExpanded = !isExpanded;
  
      if (isExpanded) {
        extraCards.forEach((card, index) => {
          setTimeout(() => {
            card.style.display = "block";
            card.classList.add("show");
          }, index * 100); // delay ทีละ 100ms ต่อใบ
        });
        toggleBtn.textContent = "แสดงน้อยลง";
      } else {
        extraCards.forEach(card => {
          card.classList.remove("show");
          setTimeout(() => {
            card.style.display = "none";
          }, 400); // รอให้ animation ปิดเสร็จก่อน
        });
        toggleBtn.textContent = "ดูเพิ่มเติม";
      }
    });
  });
  
    function toggleDiagnose() {
    const wrapper = document.querySelector('.diagnose-wrapper');
    wrapper.classList.toggle('show');
  }