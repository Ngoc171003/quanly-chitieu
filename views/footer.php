    </main>

    <?php if (isAuthenticated()): ?>
    <footer class="bg-light py-2 mt-5 border-top footer-minimal">
        <div class="container-fluid">
            <div class="text-center text-muted">
                &copy; 2026 <?php echo APP_NAME; ?> | v1.0
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>public/js/main.js?v=1.3"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>service-worker.js')
                    .then(function(registration) {
                        console.log('Service Worker registered with scope:', registration.scope);
                    })
                    .catch(function(error) {
                        console.warn('Service Worker registration failed:', error);
                    });
            });
        }
    </script>
</body>
</html>
