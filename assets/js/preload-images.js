// assets/js/preload-images.js

document.addEventListener("DOMContentLoaded", () => {
  const lazyImages = document.querySelectorAll("img[loading='lazy']");
  lazyImages.forEach(img => {
    const src = img.getAttribute("src");
    if (src) {
      const preloadImg = new Image();
      preloadImg.src = src;
    }
  });
});
