/**
 * assets/js/detection.js
 * Comprehensive Exam Security Script
 */

let isViolationTriggered = false;
const VIOLATION_MESSAGE = "SECURITY WARNING: Tab switching or exiting full-screen is prohibited. This activity has been logged.";

// 1. START EXAM & FULL-SCREEN ENFORCEMENT
function startSecureExam() {
    let elem = document.documentElement;
    
    // Request full-screen mode
    if (elem.requestFullscreen) {
        elem.requestFullscreen();
    } else if (elem.webkitRequestFullscreen) { // Safari support
        elem.webkitRequestFullscreen();
    } else if (elem.msRequestFullscreen) { // IE11 support
        elem.msRequestFullscreen();
    }

    // Hide blue overlay and reveal exam content
    const overlay = document.getElementById('exam-overlay');
    const content = document.getElementById('exam-content');
    
    if (overlay) overlay.style.display = 'none';
    if (content) content.style.display = 'block';
}

// 2. DETECT FULL-SCREEN EXIT (Esc Key)
function monitorFullscreenStatus() {
    // Detect if user exits full-screen while exam is active
    if (!document.fullscreenElement && !document.webkitIsFullScreen && !isViolationTriggered) {
        // Only trigger if the exam has already started (overlay is hidden)
        const overlay = document.getElementById('exam-overlay');
        if (overlay && overlay.style.display === 'none') {
            logViolation('Fullscreen Exit (Esc)');
        }
    }
}

document.addEventListener('fullscreenchange', monitorFullscreenStatus);
document.addEventListener('webkitfullscreenchange', monitorFullscreenStatus);

// 3. DETECT TAB SWITCHING / NEW WINDOWS (Blur Event)
window.addEventListener('blur', () => {
    if (!isViolationTriggered) {
        const overlay = document.getElementById('exam-overlay');
        if (overlay && overlay.style.display === 'none') {
            logViolation('Tab/Window Switch');
        }
    }
});

// 4. DISABLE COPY, PASTE, AND RIGHT-CLICK
document.addEventListener('copy', e => e.preventDefault());
document.addEventListener('cut', e => e.preventDefault());
document.addEventListener('paste', e => e.preventDefault());
document.addEventListener('contextmenu', e => e.preventDefault()); // Right-click
document.addEventListener('selectstart', e => e.preventDefault()); // Selection

// 5. VIOLATION HANDLER
function logViolation(reason) {
    isViolationTriggered = true;

    // Send data to PHP logger
    fetch('log_activity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'event=' + encodeURIComponent(reason)
    });

    alert(VIOLATION_MESSAGE + "\nReason: " + reason);

    // Refresh the page to lock the exam back behind the blue screen
    setTimeout(() => {
        location.reload();
    }, 500);
}