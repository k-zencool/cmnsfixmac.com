function copyPhone() {
  navigator.clipboard.writeText("0841511684");
  alert("คัดลอกเบอร์โทรแล้ว!");
}

window.addEventListener("scroll", () => {
  const contactBtn = document.getElementById("floatingContact");
  const footer = document.querySelector("footer");
  const windowHeight = window.innerHeight;
  const footerTop = footer.getBoundingClientRect().top;

  // แสดงเมื่อ scroll ลง > 100px
  if (window.scrollY > 100 && footerTop > windowHeight - 120) {
    contactBtn.classList.add("show");
  } else {
    contactBtn.classList.remove("show");
  }
});

