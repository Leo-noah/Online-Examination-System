// ===== CONFIG =====
const REFRESH_DELAY = 500; // ms
const ALERT_MESSAGE = "Warning: You changed tabs. The page will refresh.";

// ===== INTERNAL STATE =====
let triggered = false;

// ===== TAB VISIBILITY CHANGE =====
document.addEventListener('visibilitychange', function() {
    if (document.hidden && !triggered) {
        handleViolation('tab_hidden');
    }
});

// ===== WINDOW FOCUS LOSS =====
window.addEventListener('blur', function() {
    if (!triggered) {
        handleViolation('window_blur');
    }
});

// ===== CORE HANDLER =====
function handleViolation(type) {
    triggered = true;

    // 1. Optional server log
    sendToPHP(type);

    // 2. Alert user
    alert(ALERT_MESSAGE);

    // 3. Refresh page
    setTimeout(() => {
        location.reload();
    }, REFRESH_DELAY);
}