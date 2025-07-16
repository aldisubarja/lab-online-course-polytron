<footer class="footer mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-graduation-cap"></i> VulnCourse Platform</h5>
                    <p>Educational platform with intentional vulnerabilities for security testing and learning purposes.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/" class="text-light">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="text-light">Courses</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="text-light">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Security Notice</h6>
                    <p class="small">This application contains intentional security vulnerabilities. Do not use in production!</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; 2025 VulnCourse Platform. For educational purposes only.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Vulnerable: Inline JavaScript without CSP -->
    <script>
        // Vulnerable: Eval usage for dynamic content
        function executeCode(code) {
            eval(code);
        }
        
        // Vulnerable: No CSRF protection on AJAX calls
        function makeRequest(url, data) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
        }
        
        // Vulnerable: XSS through URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        if (message) {
            document.addEventListener('DOMContentLoaded', function() {
                const alert = document.createElement('div');
                alert.className = 'alert alert-info';
                alert.innerHTML = message; // Vulnerable to XSS
                document.body.insertBefore(alert, document.body.firstChild);
            });
        }
    </script>
</body>
</html>
