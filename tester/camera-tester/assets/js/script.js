const video = document.getElementById("video");
const select = document.getElementById("cameraSelect");
const startBtn = document.getElementById("startBtn");
const flipBtn = document.getElementById("flipBtn");
const stopBtn = document.getElementById("stopBtn");
const snapshotBtn = document.getElementById("snapshotBtn");
const snapshotCanvas = document.getElementById("snapshotCanvas");
const snapshotPreview = document.getElementById("snapshotPreview");
const downloadLink = document.getElementById("downloadLink");
const flashEffect = document.getElementById("flashEffect");
const cameraInfo = document.getElementById("cameraInfo");
const titleText = document.getElementById("titleText");

let currentStream = null;
let useFrontCamera = true;
let currentLang = "th";

const i18n = {
  th: {
    title: "ทดสอบกล้อง",
    start: "เริ่ม",
    flip: "สลับกล้อง",
    stop: "หยุด",
    snapshot: "บันทึกภาพ",
    noCamera: "ยังไม่ได้เปิดกล้อง",
    cameraStopped: "กล้องถูกปิดแล้ว",
    noPermission: "⚠️ กรุณาอนุญาตให้เข้าถึงกล้อง",
    snapshotDownload: "📥 ดาวน์โหลดภาพที่ถ่าย",
    alertBeforeSnapshot: "กรุณาเปิดกล้องก่อนบันทึกภาพ"
  },
  en: {
    title: "Camera Tester",
    start: "Start",
    flip: "Flip Camera",
    stop: "Stop",
    snapshot: "Snapshot",
    noCamera: "Camera not started",
    cameraStopped: "Camera stopped",
    noPermission: "⚠️ Please allow camera access",
    snapshotDownload: "📥 Download captured image",
    alertBeforeSnapshot: "Please start the camera first"
  }
};

function updateText() {
  titleText.textContent = i18n[currentLang].title;
  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.getAttribute("data-i18n");
    el.textContent = i18n[currentLang][key];
  });
  if (!currentStream) {
    cameraInfo.textContent = i18n[currentLang].noCamera;
  }
  if (downloadLink.style.display === "block") {
    downloadLink.textContent = i18n[currentLang].snapshotDownload;
  }
}

function switchLang(lang) {
  currentLang = lang;
  updateText();
}

async function getCameras() {
  const devices = await navigator.mediaDevices.enumerateDevices();
  const videoDevices = devices.filter(device => device.kind === "videoinput");

  select.innerHTML = "";
  videoDevices.forEach((device, index) => {
    const option = document.createElement("option");
    option.value = device.deviceId;
    option.text = device.label || `Camera ${index + 1}`;
    select.appendChild(option);
  });
}

function updateVideoMirror() {
  video.style.transform = useFrontCamera ? "scaleX(-1)" : "scaleX(1)";
}

function stopCamera() {
  if (currentStream) {
    currentStream.getTracks().forEach(track => track.stop());
    currentStream = null;
    video.srcObject = null;
    cameraInfo.textContent = i18n[currentLang].cameraStopped;
  }
}

async function startCamera(constraints, labelHint = "") {
  stopCamera();

  try {
    currentStream = await navigator.mediaDevices.getUserMedia(constraints);
    video.srcObject = currentStream;
    updateVideoMirror();

    const track = currentStream.getVideoTracks()[0];
    const settings = track.getSettings();
    cameraInfo.textContent = `${labelHint || track.label || "Camera"} (${settings.width}x${settings.height})`;
  } catch (err) {
    alert(i18n[currentLang].noPermission);
    console.error(err);
    cameraInfo.textContent = i18n[currentLang].noPermission;
  }
}

startBtn.addEventListener("click", () => {
  const deviceId = select.value;
  if (deviceId) {
    const constraints = {
      video: { deviceId: { exact: deviceId } }
    };
    const label = select.options[select.selectedIndex].text;
    startCamera(constraints, label);
  } else {
    const constraints = {
      video: { facingMode: useFrontCamera ? "user" : "environment" }
    };
    startCamera(constraints, useFrontCamera ? "Front Camera" : "Back Camera");
  }
});

flipBtn.addEventListener("click", () => {
  useFrontCamera = !useFrontCamera;
  const constraints = {
    video: { facingMode: useFrontCamera ? "user" : "environment" }
  };
  startCamera(constraints, useFrontCamera ? "Front Camera" : "Back Camera");
});

stopBtn.addEventListener("click", stopCamera);

snapshotBtn.addEventListener("click", () => {
  if (!currentStream) {
    alert(i18n[currentLang].alertBeforeSnapshot);
    return;
  }

  flashEffect.style.opacity = "1";
  setTimeout(() => flashEffect.style.opacity = "0", 100);

  const videoTrack = currentStream.getVideoTracks()[0];
  const settings = videoTrack.getSettings();

  snapshotCanvas.width = settings.width || video.videoWidth;
  snapshotCanvas.height = settings.height || video.videoHeight;

  const context = snapshotCanvas.getContext("2d");

  if (useFrontCamera) {
    context.translate(snapshotCanvas.width, 0);
    context.scale(-1, 1);
  }

  context.drawImage(video, 0, 0, snapshotCanvas.width, snapshotCanvas.height);

  const imageDataURL = snapshotCanvas.toDataURL("image/png");

  snapshotPreview.src = imageDataURL;
  snapshotPreview.style.display = "block";

  downloadLink.href = imageDataURL;
  downloadLink.download = "snapshot.png";
  downloadLink.textContent = i18n[currentLang].snapshotDownload;
  downloadLink.style.display = "block";
});

async function requestPermissionAndListCameras() {
  try {
    await navigator.mediaDevices.getUserMedia({ video: true });
    await getCameras();
  } catch (err) {
    alert(i18n[currentLang].noPermission);
    console.error(err);
  }
}

requestPermissionAndListCameras();
updateText(); // เริ่มต้นด้วยภาษาเริ่มต้น

const langToggleBtn = document.getElementById("lang-toggle");

langToggleBtn.addEventListener("click", () => {
  currentLang = currentLang === "th" ? "en" : "th";
  updateText();
});
