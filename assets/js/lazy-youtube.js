document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".youtube-lazy").forEach(el => {
    el.addEventListener("click", function () {
      const videoId = this.dataset.id;
      const iframe = document.createElement("iframe");
      iframe.setAttribute("src", "https://www.youtube.com/embed/" + videoId + "?autoplay=1");
      iframe.setAttribute("frameborder", "0");
      iframe.setAttribute("allowfullscreen", "1");
      iframe.setAttribute("allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture");
      iframe.style.width = "100%";
      iframe.style.height = "100%";
      this.innerHTML = "";
      this.appendChild(iframe);
    });
  });
});
