</main>

<!-- Footer -->
<?php if (!isset($isDashboard) || !$isDashboard): ?>
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Blood Bank Portal</h5>
                <p class="mb-0">Connecting donors, patients, and healthcare providers through an integrated blood management system.</p>
            </div>
            <div class="col-md-2 mb-4 mb-md-0">
                <h5 class="mb-3">Links</h5>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white-50 text-decoration-none">Home</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">About Us</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Donations</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Contact</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <h5 class="mb-3">Contact</h5>
                <ul class="list-unstyled text-white-50">
                    <li class="mb-2"><i class="bi bi-geo-alt-fill me-2"></i> 123 Blood Bank St., Bacolod City</li>
                    <li class="mb-2"><i class="bi bi-telephone-fill me-2"></i> +63 123 456 7890</li>
                    <li><i class="bi bi-envelope-fill me-2"></i> info@bloodbankportal.com</li>
                </ul>
            </div>
            <div class="col-md-3">
                <h5 class="mb-3">Connect With Us</h5>
                <div class="d-flex gap-3">
                    <a href="#" class="text-white fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white fs-5"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-white fs-5"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-white fs-5"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
        </div>
        <hr class="my-4">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Blood Bank Portal. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-white-50 text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-white-50 text-decoration-none">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<?php
// Determine the correct path for JS files
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) {
    $basePath = '../../';
    echo '<script src="' . $basePath . 'assets/js/charts.js"></script>';
}
?>
<script src="<?php echo $basePath; ?>assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // For all modals
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('hide.bs.modal', function (event) {
            // If the modal is being hidden and the mouse is still over the table or modal, prevent it
            if (document.querySelector('.modal:hover') || document.querySelector('table:hover')) {
                event.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>
