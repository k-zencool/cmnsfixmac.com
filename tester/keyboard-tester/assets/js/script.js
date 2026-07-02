/* ==========================================================
   Keyboard Tester engine
   keydown  -> key lights orange (active) + marked green (ok) once
   keyup    -> orange clears, green stays
   progress -> counts distinct testable keys verified
   ========================================================== */
(function () {
  "use strict";

  const keys      = Array.from(document.querySelectorAll(".key"));
  const testable  = keys.filter((k) => !k.hasAttribute("data-skip"));
  const logEl     = document.getElementById("key-log");
  const doneEl    = document.getElementById("ktDone");
  const totalEl   = document.getElementById("ktTotal");
  const fillEl    = document.getElementById("ktFill");
  const resetBtn  = document.getElementById("ktReset");

  const total = testable.length;
  const tested = new Set();
  if (totalEl) totalEl.textContent = total;

  const GLYPH = {
    " ": "Space", "Shift": "⇧", "Enter": "↵", "Tab": "⇥", "Backspace": "⌫",
    "Escape": "Esc", "Control": "⌃", "Alt": "⌥", "Meta": "⌘", "CapsLock": "⇪",
    "ArrowLeft": "←", "ArrowRight": "→", "ArrowUp": "↑", "ArrowDown": "↓"
  };

  function findKey(e) {
    return document.querySelector(
      `.key[data-code="${CSS.escape(e.code)}"], .key[data-key="${CSS.escape(e.key)}"]`
    );
  }

  function mark(el) {
    if (!el || el.hasAttribute("data-skip")) return;
    const id = el.dataset.code || el.dataset.key;
    if (tested.has(id)) return;
    tested.add(id);
    el.classList.add("ok");
    updateProgress();
  }

  function updateProgress() {
    if (doneEl) doneEl.textContent = tested.size;
    if (fillEl) fillEl.style.width = (total ? (tested.size / total) * 100 : 0) + "%";
  }

  document.addEventListener("keydown", (e) => {
    e.preventDefault();
    if (logEl) logEl.textContent = GLYPH[e.key] || (e.key.length === 1 ? e.key.toUpperCase() : e.key);
    const el = findKey(e);
    if (!el) return;
    el.classList.add("active");
    mark(el);
  });

  document.addEventListener("keyup", (e) => {
    const el = findKey(e);
    if (el) el.classList.remove("active");
  });

  // clicking/tapping a key also marks it (touch devices, or keys the OS swallows)
  testable.forEach((el) => el.addEventListener("click", () => mark(el)));

  resetBtn?.addEventListener("click", () => {
    tested.clear();
    keys.forEach((k) => k.classList.remove("active", "ok"));
    if (logEl) logEl.textContent = "—";
    updateProgress();
  });

  // clear stuck "active" highlight when the window loses focus
  function clearActive() { keys.forEach((k) => k.classList.remove("active")); }
  window.addEventListener("blur", clearActive);
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") clearActive();
  });

  updateProgress();
})();
