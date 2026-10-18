</div>
</div>
<style>
    #uit-custom-footer {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px);
        /* INCREASED: Thicker border for the 'big' look */
        border-top: 2px solid rgba(0, 0, 0, 0.08) !important;
        /* INCREASED: Padding from 1rem to 3rem for a taller footer */
        padding: 3rem 0;
        margin-top: auto;
        width: 100%;
        box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.03);
    }

    #uit-custom-footer .footer-brand {
        font-weight: 800;
        color: #2d3436;
        letter-spacing: 0.5px;
        /* INCREASED: Larger branding text */
        font-size: 1.2rem;
    }

    #uit-custom-footer .footer-tagline {
        /* INCREASED: Larger tagline text */
        font-size: 1rem;
        color: #636e72;
        margin-top: 10px;
    }

    /* Sidebar transition for the footer */
    body.sidebar-open #uit-custom-footer {
        transition: margin-left 0.3s ease;
    }
</style>

<footer id="uit-custom-footer" class="text-center mt-auto">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="mb-2">
                    <span class="fw-bold text-uppercase">
                        University of Information Technology
                    </span>
                </div>
                <div class="footer-tagline mb-0">
                    &copy; <?php echo date("Y"); ?> <span class="fw-medium"></span>
                    Online Examination System
                    <span class="mx-3 text-muted">|</span>
                    <span class="text-primary fw-bold" style="font-size: 1rem;">Developed by UIT Students</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('mainSidebar');
        const body = document.body;

        // Handle Sidebar Toggle
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                // Ensure this matches your sidebar class (active or sidebar-toggled)
                sidebar.classList.toggle('active');
                body.classList.toggle('sidebar-toggled');
                body.classList.toggle('sidebar-open');

                // Save state
                const state = body.classList.contains('sidebar-toggled') ? 'closed' : 'open';
                document.cookie = "sidebarState=" + state + "; path=/; max-age=" + (30 * 24 * 60 * 60);
            });
        }
    });
</script>
</body>

</html>