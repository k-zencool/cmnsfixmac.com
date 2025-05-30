document.addEventListener('keydown', function (e) {
  // ล้าง console แล้วแสดง debug log
  console.clear();
  console.log('%c🔍 Key Pressed', 'color: #007aff; font-weight: bold;');
  console.log('e.key:', JSON.stringify(e.key));
  console.log('e.code:', e.code);
  console.log('e.location:', e.location);

  const code = e.code;
  const selector = `.key[data-code="${CSS.escape(code)}"]`;
  const elements = document.querySelectorAll(selector);
  elements.forEach(el => el.classList.add('active'));
});

document.addEventListener('keyup', function (e) {
  const code = e.code;
  const selector = `.key[data-code="${CSS.escape(code)}"]`;
  const elements = document.querySelectorAll(selector);
  elements.forEach(el => {
    el.classList.remove('active');
    el.classList.add('pressed');
  });
});

// ล้าง .active ถ้าสลับแท็บหรือหน้าต่าง
window.addEventListener('blur', clearAllActive);
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') clearAllActive();
});

// ฟังก์ชันล้าง .active
function clearAllActive() {
  document.querySelectorAll('.key.active').forEach(el => el.classList.remove('active'));
}

document.addEventListener('keydown', function (e) {
  const selector = `.key[data-key="${CSS.escape(e.key)}"]`;
  const elements = document.querySelectorAll(selector);
  elements.forEach(el => el.classList.add('active'));
});

document.addEventListener('keyup', function (e) {
  const selector = `.key[data-key="${CSS.escape(e.key)}"]`;
  const elements = document.querySelectorAll(selector);
  elements.forEach(el => {
    el.classList.remove('active');
    el.classList.add('pressed');
  });
});

///==========================================================///

