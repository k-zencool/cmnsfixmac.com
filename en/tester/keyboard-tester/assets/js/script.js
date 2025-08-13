
const allKeys = document.querySelectorAll('.key');
const keyLogEl = document.getElementById('key-log');

function handleKeyPress(e, addClass) {
    // Find key by data-code first (more reliable for layout) then by data-key
    const keyElement = document.querySelector(`.key[data-code="${e.code}"], .key[data-key="${e.key}"]`);
    if (keyElement) {
        if (addClass) {
            keyElement.classList.add('active');
        } else {
            keyElement.classList.remove('active');
            keyElement.classList.add('pressed');
        }
    }
}

function updateKeyLog(e) {
    if (!keyLogEl) return;
    const keyMap = {
        " ": "Space", "Shift": "⇧", "Enter": "↵", "Tab": "⇥",
        "Backspace": "⌫", "Escape": "Esc", "Control": "⌃", "Alt": "⌥",
        "Meta": "⌘", "ArrowLeft": "←", "ArrowRight": "→",
        "ArrowUp": "↑", "ArrowDown": "↓"
    };
    const displayKey = keyMap[e.key] || e.key;
    keyLogEl.textContent = displayKey;
}

// Main event listener for keydown
document.addEventListener('keydown', (e) => {
  // Prevent default browser action for ALL keys on this page
  e.preventDefault();

  handleKeyPress(e, true);
  updateKeyLog(e);
});

// Main event listener for keyup
document.addEventListener('keyup', (e) => {
    handleKeyPress(e, false);
});

// Function to clear highlights when switching windows/tabs
function clearAllActive() {
    allKeys.forEach(key => key.classList.remove('active'));
}
window.addEventListener('blur', clearAllActive);
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') {
    clearAllActive();
  }
});
