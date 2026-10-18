// ===== SEND EVENT TO PHP (OPTIONAL) =====
function sendToPHP(event) {
    fetch('log_activity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'event=' + encodeURIComponent(event)
    });
}


// Disable Whole Page Selection JS
document.addEventListener('selectstart', function(e) {
    e.preventDefault();
});
// Also Disable Mouse Selection
document.addEventListener("mousedown", e => e.preventDefault());
// Also Disable Copy Cut
document.addEventListener("copy", e => e.preventDefault());
document.addEventListener("cut", e => e.preventDefault());


// Disable Whole Page Selection JQuery
$(document).on("selectstart", function(e) {
    e.preventDefault();
});