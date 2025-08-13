// script.js (รองรับภาษาไทยและอังกฤษ พร้อมใช้ .mp4 แทน .webm เพื่อรองรับ iOS พร้อมภาษาอังกฤษใน UI)

const i18n = {
  th: {
    loading: "กำลังโหลด...",
    select: "เลือกไมโครโฟน",
    heading: "เลือกไมโครโฟนและเริ่มการทดสอบ",
    startTest: "เริ่มทดสอบ",
    stop: "หยุด",
    startRec: "เริ่มอัดเสียง",
    stopRec: "หยุดอัดเสียง",
    play: "ฟังเสียง",
    download: "ดาวน์โหลดเสียง",
    needPermission: "⚠️ กรุณาอนุญาตให้เข้าถึงไมโครโฟน",
    startFirst: "กรุณากด 'เริ่มทดสอบ' ก่อนเพื่อเปิดไมโครโฟน",
    cannotStart: "⚠️ ไม่สามารถเริ่มการทดสอบไมค์ได้",
    cannotRecord: "ไม่สามารถเริ่มอัดเสียงได้: ",
    recording: "กำลังอัดเสียง...",
    toggleLang: "เปลี่ยนภาษา"
  },
  en: {
    loading: "Loading...",
    select: "Select Microphone",
    heading: "Select a Microphone and Start Testing",
    startTest: "Start Test",
    stop: "Stop",
    startRec: "Start Recording",
    stopRec: "Stop Recording",
    play: "Play",
    download: "Download",
    needPermission: "⚠️ Please allow microphone access",
    startFirst: "Please click 'Start Test' to enable the microphone",
    cannotStart: "⚠️ Unable to start microphone test",
    cannotRecord: "Cannot start recording: ",
    recording: "Recording...",
    toggleLang: "Change Language"
  }
};

let currentLang = 'th';

const micSelect = document.getElementById('mic-select');
const startBtn = document.getElementById('start-btn');
const stopBtn = document.getElementById('stop-btn');
const micBar = document.getElementById('mic-bar');
const micPercent = document.getElementById('mic-percent');
const recordBtn = document.getElementById('record-btn');
const stopRecordBtn = document.getElementById('stop-record-btn');
const playBtn = document.getElementById('play-btn');
const downloadBtn = document.getElementById('download-btn');
const audioPlayback = document.getElementById('audio-playback');
const recordingStatus = document.getElementById('recording-status');
const headingEl = document.querySelector('h1');
const micLabel = document.querySelector('label[for="mic-select"]');
const langToggleBtn = document.getElementById('lang-toggle');

let stream = null;
let audioContext, analyser, dataArray, source;
let mediaRecorder;
let recordedChunks = [];

function applyLang() {
  const t = i18n[currentLang];
  if (micSelect.options.length === 0) {
    micSelect.innerHTML = `<option value="" disabled selected hidden>${t.loading}</option>`;
  }
  startBtn.innerText = t.startTest;
  stopBtn.innerText = t.stop;
  recordBtn.innerText = t.startRec;
  stopRecordBtn.innerText = t.stopRec;
  playBtn.innerText = t.play;
  downloadBtn.innerText = t.download;
  if (headingEl) headingEl.innerText = t.heading;
  if (micLabel) micLabel.innerText = t.select;
  if (recordingStatus) recordingStatus.innerHTML = `<span class="dot"></span> ${t.recording}`;
  if (langToggleBtn) langToggleBtn.innerHTML = `<span class="material-symbols-outlined">translate</span> ${t.toggleLang}`;
}

async function loadMicrophones() {
  applyLang();
  try {
    const tempStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const devices = await navigator.mediaDevices.enumerateDevices();
    const t = i18n[currentLang];
    micSelect.innerHTML = `<option value="" disabled selected hidden>${t.select}</option>`;
    devices.forEach(device => {
      if (device.kind === 'audioinput') {
        const option = document.createElement('option');
        option.value = device.deviceId;
        option.text = device.label || `Microphone ${micSelect.length + 1}`;
        micSelect.appendChild(option);
      }
    });
    tempStream.getTracks().forEach(track => track.stop());
  } catch (err) {
    alert(i18n[currentLang].needPermission);
    console.error(err);
  }
}

loadMicrophones();

startBtn.addEventListener('click', async () => {
  const selectedDeviceId = micSelect.value;
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      audio: { deviceId: selectedDeviceId ? { exact: selectedDeviceId } : undefined }
    });
    startVisualizer(stream);
    startWaveform(stream);

    startBtn.style.display = 'none';
    stopBtn.style.display = 'inline-block';
  } catch (err) {
    alert(i18n[currentLang].cannotStart);
    console.error(err);
  }
});

stopBtn.addEventListener('click', () => {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
  if (audioContext) audioContext.close();
  micBar.style.width = '0%';
  micPercent.innerText = '0%';
  startBtn.style.display = 'inline-block';
  stopBtn.style.display = 'none';
});

function startVisualizer(stream) {
  audioContext = new (window.AudioContext || window.webkitAudioContext)();
  analyser = audioContext.createAnalyser();
  analyser.fftSize = 256;
  source = audioContext.createMediaStreamSource(stream);
  source.connect(analyser);
  dataArray = new Uint8Array(analyser.frequencyBinCount);

  function updateBar() {
    requestAnimationFrame(updateBar);
    analyser.getByteFrequencyData(dataArray);
    const avg = dataArray.reduce((a, b) => a + b) / dataArray.length;
    micBar.style.width = `${Math.min(avg, 100)}%`;
    micPercent.innerText = `${Math.round(avg)}%`;
  }
  updateBar();
}

recordBtn.addEventListener('click', async () => {
  try {
    if (!stream) {
      alert(i18n[currentLang].startFirst);
      return;
    }
    recordedChunks = [];
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/mp4' });

    mediaRecorder.ondataavailable = e => {
      if (e.data.size > 0) recordedChunks.push(e.data);
    };

    mediaRecorder.onstop = () => {
      const blob = new Blob(recordedChunks, { type: 'audio/mp4' });
      const audioURL = URL.createObjectURL(blob);
      audioPlayback.src = audioURL;
      audioPlayback.style.display = 'block';
      playBtn.style.display = 'inline-block';
      downloadBtn.style.display = 'inline-block';
      recordingStatus.style.display = 'none';
      downloadBtn.onclick = () => {
        const a = document.createElement('a');
        a.href = audioURL;
        a.download = 'recorded-audio.mp4';
        a.click();
      };
    };

    mediaRecorder.start();
    recordBtn.style.display = 'none';
    stopRecordBtn.style.display = 'inline-block';
    recordingStatus.style.display = 'block';
  } catch (err) {
    alert(i18n[currentLang].cannotRecord + err.message);
    console.error(err);
  }
});

stopRecordBtn.addEventListener('click', () => {
  if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  recordBtn.style.display = 'inline-block';
  stopRecordBtn.style.display = 'none';
  recordingStatus.style.display = 'none';
});

playBtn.addEventListener('click', () => {
  audioPlayback.play();
});

if (langToggleBtn) {
  langToggleBtn.addEventListener('click', () => {
    currentLang = currentLang === 'th' ? 'en' : 'th';
    applyLang();
    loadMicrophones();
  });
}

function startWaveform(stream) {
  const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  const source = audioCtx.createMediaStreamSource(stream);
  const analyser = audioCtx.createAnalyser();
  analyser.fftSize = 2048;

  source.connect(analyser);

  const canvas = document.createElement('canvas');
  canvas.width = document.getElementById('waveform').clientWidth;
  canvas.height = 100;
  canvas.style.width = '100%';
  canvas.style.height = '100px';

  const waveformDiv = document.getElementById('waveform');
  waveformDiv.innerHTML = '';
  waveformDiv.appendChild(canvas);

  const canvasCtx = canvas.getContext('2d');
  const dataArray = new Uint8Array(analyser.fftSize);

  function draw() {
    requestAnimationFrame(draw);
    analyser.getByteTimeDomainData(dataArray);

    canvasCtx.fillStyle = '#ffffff';
    canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

    canvasCtx.lineWidth = 2;
    canvasCtx.strokeStyle = '#fc7404';
    canvasCtx.beginPath();

    const sliceWidth = canvas.width * 1.0 / dataArray.length;
    let x = 0;

    for (let i = 0; i < dataArray.length; i++) {
      const v = dataArray[i] / 128.0;
      const y = v * canvas.height / 2;

      if (i === 0) {
        canvasCtx.moveTo(x, y);
      } else {
        canvasCtx.lineTo(x, y);
      }

      x += sliceWidth;
    }

    canvasCtx.lineTo(canvas.width, canvas.height / 2);
    canvasCtx.stroke();
  }

  draw();
}
