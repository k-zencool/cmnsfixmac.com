const audioContext = new (window.AudioContext || window.webkitAudioContext)();
const audioElement = new Audio("/tester/sounds-tester/sounds/test-musi.mp3");
audioElement.loop = true;

const source = audioContext.createMediaElementSource(audioElement);
const splitter = audioContext.createChannelSplitter(2);
const merger = audioContext.createChannelMerger(2);
const dummy = audioContext.createGain();
dummy.gain.value = 0;

const analyser = audioContext.createAnalyser();
analyser.fftSize = 1024;
const dataArray = new Uint8Array(analyser.frequencyBinCount);

const canvas = document.getElementById("waveform");
const ctx = canvas.getContext("2d");

let autoTestLoop = false;
let autoTestInterval = null;
let currentSide = 'left';

source.connect(splitter);

function setOutputChannel(channel) {
  splitter.disconnect();
  merger.disconnect();

  const status = document.getElementById("status");
  status.classList.remove("left", "right", "both");

  if (channel === "left") {
    splitter.connect(merger, 0, 0);
    splitter.connect(dummy, 1);
    status.classList.add("left");
  } else if (channel === "right") {
    splitter.connect(dummy, 0);
    splitter.connect(merger, 1, 1);
    status.classList.add("right");
  } else {
    splitter.connect(merger, 0, 0);
    splitter.connect(merger, 1, 1);
    status.classList.add("both");
  }

  merger.connect(analyser);
  analyser.connect(audioContext.destination);

  status.textContent = `🔉 กำลังเล่นเสียงทางลำโพง: ${channel.toUpperCase()}`;
}

function playContinuousAudio() {
  audioContext.resume();
  audioElement.play().catch(err => console.error("Play error:", err));
}

function autoTest() {
  stopSound();
  autoTestLoop = true;
  currentSide = 'left';
  playContinuousAudio();
  setOutputChannel(currentSide);

  autoTestInterval = setInterval(() => {
    currentSide = currentSide === 'left' ? 'right' : 'left';
    setOutputChannel(currentSide);
  }, 4000); // สลับทุก 4 วินาที
}

function stopSound() {
  audioElement.pause();
  audioElement.currentTime = 0;
  clearInterval(autoTestInterval);
  autoTestLoop = false;

  const status = document.getElementById("status");
  status.textContent = "⏹️ หยุดการเล่นเสียงแล้ว";
  status.classList.remove("left", "right", "both");
}

function playChannel(channel) {
  stopSound();
  audioElement.loop = false;
  audioContext.resume();
  audioElement.pause();
  audioElement.currentTime = 0;

  setOutputChannel(channel);
  audioElement.play()
    .then(() => {
      document.getElementById("status").textContent = `🔉 กำลังเล่นเสียงทางลำโพง: ${channel.toUpperCase()}`;
    })
    .catch(err => {
      document.getElementById("status").textContent = "❌ ไม่สามารถเล่นเสียงได้";
      console.error(err);
    });
}

function setVolume(value) {
  audioElement.volume = parseFloat(value);
  document.getElementById("volume-value").textContent = `${Math.round(value * 100)}%`;
}

function drawWaveform() {
  requestAnimationFrame(drawWaveform);
  analyser.getByteTimeDomainData(dataArray);

  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.lineWidth = 2;
  ctx.strokeStyle = "#fc7404";
  ctx.beginPath();

  const sliceWidth = canvas.width / dataArray.length;
  let x = 0;

  for (let i = 0; i < dataArray.length; i++) {
    const v = dataArray[i] / 128.0;
    const y = (v * canvas.height) / 2;
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    x += sliceWidth;
  }

  ctx.lineTo(canvas.width, canvas.height / 2);
  ctx.stroke();
}

drawWaveform();

const circleCanvas = document.getElementById("circleVisualizer");
const circleCtx = circleCanvas.getContext("2d");

function drawCircularVisualizer() {
  requestAnimationFrame(drawCircularVisualizer);
  analyser.getByteFrequencyData(dataArray);

  circleCtx.clearRect(0, 0, circleCanvas.width, circleCanvas.height);

  const centerX = circleCanvas.width / 2;
  const centerY = circleCanvas.height / 2;
  const radius = 80;
  const bars = 64;
  const step = Math.floor(dataArray.length / bars);

  for (let i = 0; i < bars; i++) {
    const value = dataArray[i * step];
    const angle = (i / bars) * Math.PI * 2;
    const barHeight = value / 2;

    const x1 = centerX + Math.cos(angle) * radius;
    const y1 = centerY + Math.sin(angle) * radius;
    const x2 = centerX + Math.cos(angle) * (radius + barHeight);
    const y2 = centerY + Math.sin(angle) * (radius + barHeight);

    circleCtx.beginPath();
    circleCtx.moveTo(x1, y1);
    circleCtx.lineTo(x2, y2);
    circleCtx.strokeStyle = `hsl(${i * 6}, 100%, 50%)`;
    circleCtx.lineWidth = 2;
    circleCtx.stroke();
  }
}

drawCircularVisualizer();