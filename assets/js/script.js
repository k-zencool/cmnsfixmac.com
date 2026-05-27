document.addEventListener("DOMContentLoaded", () => {
  // Animate .service-box cards on scroll (legacy pages still use this class)
  const serviceBoxes = document.querySelectorAll(".service-box");
  if (serviceBoxes.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    serviceBoxes.forEach(box => observer.observe(box));
  }

  // Legacy services toggle (#toggleBtn only exists on old pages)
  const toggleBtn = document.getElementById("toggleBtn");
  if (toggleBtn) {
    const extraCards = document.querySelectorAll(".service-box.extra");
    let isExpanded = false;
    toggleBtn.addEventListener("click", () => {
      isExpanded = !isExpanded;
      extraCards.forEach((card, index) => {
        if (isExpanded) {
          setTimeout(() => {
            card.style.display = "block";
            card.classList.add("show");
          }, index * 80);
        } else {
          card.classList.remove("show");
          setTimeout(() => { card.style.display = "none"; }, 350);
        }
      });
      toggleBtn.textContent = isExpanded ? "แสดงน้อยลง" : "ดูเพิ่มเติม";
    });
  }
});

function toggleDiagnose() {
  const wrapper = document.querySelector('.diagnose-wrapper');
  if (wrapper) wrapper.classList.toggle('show');
}
