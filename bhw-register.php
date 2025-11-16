<?php
// Include database connection
require_once 'config/db.php';

// Start session for success message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define variables and set to empty values
$name = $email = $password = $confirm_password = $barangay_name = $phone = "";
$name_err = $email_err = $password_err = $confirm_password_err = $barangay_name_err = $phone_err = "";
$success_message = "";

// Check for registration success message from redirect
if (isset($_GET['registered']) && isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate name (letters and single spaces, title case)
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter BHW name.";
    } else {
        $name = normalize_input($_POST["name"], true);
        if (!preg_match('/^(?:[A-Z][a-z]*)(?: [A-Z][a-z]*)*$/', $name)) {
            $name_err = "Each word must start with a capital letter, only single spacing allowed.";
        }
    }

    // Validate barangay name (letters and single spaces, title case)
    if (empty(trim($_POST["barangay_name"]))) {
        $barangay_name_err = "Please enter barangay name.";
    } else {
        $barangay_name = normalize_input($_POST["barangay_name"], true);
        if (!preg_match('/^(?:[A-Z][a-z]*)(?: [A-Z][a-z]*)*$/', $barangay_name)) {
            $barangay_name_err = "Each word must start with a capital letter, only single spacing allowed.";
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter email.";
    } else {
        $email = normalize_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if email already exists
            $check_email = getRow("SELECT id FROM barangay_users WHERE email = ?", [$email]);
            if ($check_email) {
                $email_err = "This email is already registered.";
            }
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':\"\\|,.<>\/?]).{8,}$/", $_POST["password"])) {
        $password_err = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password (server-side)
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords do not match.";
        }
    }

    // Validate phone
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter phone number.";
    } else {
        $phone = trim($_POST["phone"]);
        if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $phone_err = "Please enter a valid phone number.";
        }
    }

    // Check input errors before inserting in database
    if (empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && 
        empty($barangay_name_err) && empty($phone_err)) {
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into database - matching the table structure: id, email, password, name, barangay_name, address, phone, created_at, updated_at
        // Note: id is auto-increment, address is nullable (not in form), created_at and updated_at have defaults
        $sql = "INSERT INTO barangay_users (email, password, name, barangay_name, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        try {
            $result = insertRow($sql, [$email, $hashed_password, $name, $barangay_name, $phone]);
            
            if ($result !== false) {
                // Registration successful
                $_SESSION['registration_success'] = "BHW registration successful! You can now login with your credentials.";
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?registered=1");
                exit;
            } else {
                $email_err = "Something went wrong. Please try again.";
            }
        } catch (Exception $e) {
            // Log error for debugging
            error_log("BHW Registration Error: " . $e->getMessage());
            $email_err = "Registration failed. Please try again or contact support.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Registration - Blood Bank Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome (optional, used elsewhere) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 20%, #fef3c7 40%, #fde68a 60%, #dbeafe 80%, #e0f2fe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container-narrow {
            max-width: 820px;
            margin: 60px auto;
        }
        .register-header {
            text-align: center;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 30%, #eab308 70%, #fbbf24 100%);
            padding: 2.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3), 0 2px 8px rgba(234, 179, 8, 0.2);
            position: relative;
            overflow: hidden;
            margin-top: 2rem;
        }
        
        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .register-header h2,
        .register-header p {
            position: relative;
            z-index: 1;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .register-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .register-header .text-muted {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .register-logo {
            font-size: 48px;
            color: #ffffff;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }
        .register-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
        }
        .form-section-title {
            font-weight: 600;
            color: #495057;
        }
        .input-group > .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .btn-register {
            padding: 12px;
            font-weight: 600;
        }
        .btn-danger {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
            color: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        .text-danger {
            color: #3b82f6 !important;
        }
        .invalid-feedback { display: block; }
        
        /* Improved Container Spacing */
        .container-narrow {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        /* Better Form Spacing */
        .mb-3 {
            margin-bottom: 1.5rem !important;
        }
        
        .mb-4 {
            margin-bottom: 2rem !important;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-control,
        .form-select {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border-radius: 8px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .btn-register {
            padding: 0.875rem 2rem;
            font-size: 1rem;
        }
        
        /* Better Card Spacing */
        .register-card {
            margin-bottom: 2rem;
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 991.98px) {
            .container-narrow {
                max-width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .container-narrow {
                margin: 40px auto;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            .register-header {
                margin-bottom: 1.5rem;
            }
            
            .register-logo {
                font-size: 40px;
                margin-bottom: 1rem;
            }
            
            .register-header h2 {
                font-size: 1.75rem;
            }
            
            .register-header p {
                font-size: 0.95rem;
            }
            
            .card-body {
                padding: 1.75rem 1.25rem;
            }
            
            .form-section-title {
                font-size: 1.1rem;
            }
            
            .mb-3 {
                margin-bottom: 1.25rem !important;
            }
            
            .mb-4 {
                margin-bottom: 1.75rem !important;
            }
            
            .form-label {
                font-size: 0.9rem;
            }
            
            .form-control,
            .form-select {
                font-size: 0.95rem;
                padding: 0.7rem 0.9rem;
            }
            
            .input-group-text {
                padding: 0.7rem 0.9rem;
                font-size: 0.9rem;
            }
            
            .btn-register {
                padding: 0.8rem 1.5rem;
                font-size: 0.95rem;
            }
            
            .text-center {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .container-narrow {
                margin: 30px auto;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .register-header {
                margin-bottom: 1.25rem;
            }
            
            .register-logo {
                font-size: 36px;
            }
            
            .register-header h2 {
                font-size: 1.5rem;
            }
            
            .register-header p {
                font-size: 0.9rem;
            }
            
            .card-body {
                padding: 1.5rem 1rem;
            }
            
            .form-section-title {
                font-size: 1rem;
            }
            
            .mb-3 {
                margin-bottom: 1rem !important;
            }
            
            .mb-4 {
                margin-bottom: 1.5rem !important;
            }
            
            .form-label {
                font-size: 0.875rem;
                margin-bottom: 0.4rem;
            }
            
            .form-control,
            .form-select {
                font-size: 0.9rem;
                padding: 0.65rem 0.85rem;
            }
            
            .input-group-text {
                padding: 0.65rem 0.85rem;
                font-size: 0.85rem;
            }
            
            .btn-register {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
            }
            
            .text-center {
                font-size: 0.85rem;
            }
            
            .text-center a {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 400px) {
            .register-logo {
                font-size: 32px;
            }
            
            .register-header h2 {
                font-size: 1.25rem;
            }
            
            .form-control,
            .form-select {
                font-size: 0.875rem;
            }
            
            .btn-register {
                font-size: 0.875rem;
                padding: 0.7rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container container-narrow">
        <div class="register-header">
            <div class="register-logo">
                <i class="bi bi-droplet-fill"></i>
            </div>
            <h2>Blood Bank Portal</h2>
            <p class="text-muted">Create your BHW account</p>
        </div>
        <div class="card register-card">
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <h4 class="form-section-title mb-1"><i class="bi bi-person-badge me-2"></i>BHW Information</h4>
                    <small class="text-muted">Please provide accurate barangay details.</small>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="name" class="form-label">BHW Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" 
                                   id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" data-titlecase="1" required>
                            <div class="invalid-feedback" id="name-feedback"><?php echo $name_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="text" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email address" autocomplete="username">
                            </div>
                            <div class="invalid-feedback" id="email-feedback"><?php echo $email_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="invalid-feedback" id="password-feedback"><?php echo $password_err; ?></div>
                            <small class="text-muted">Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.</small>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm your password">
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="invalid-feedback" id="confirm_password-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo (!empty($barangay_name_err)) ? 'is-invalid' : ''; ?>" 
                                   id="barangay_name" name="barangay_name" value="<?php echo htmlspecialchars($barangay_name); ?>" data-titlecase="1" required>
                            <div class="invalid-feedback" id="barangay_name-feedback"><?php echo $barangay_name_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" 
                                   id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                            <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                        </div>

                        <div class="d-grid mb-2">
                            <button type="submit" class="btn btn-danger btn-register"><i class="bi bi-person-plus me-2"></i>Register as BHW</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <p class="mb-2">Already have an account? <a href="barangay-login.php" class="text-decoration-none">Login here</a></p>
                        <a href="barangay-portal.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to Home</a>
                    </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Title-case and single-spacing formatter for clean inputs
    // Usage: add data-titlecase="1" on any input/textarea to enforce formatting
    const formatToTitleCase = (str) => {
        if (!str) return '';
        // Collapse multiple spaces to single space and trim
        let s = str.replace(/\s+/g, ' ').trim();
        // Title case words consisting of letters; preserve single spaces
        return s.split(' ').map(w => {
            if (!w) return ''; // Handle empty strings from split
            const m = w.match(/^([A-Za-z])(.*)$/);
            if (!m) return w;
            return m[1].toUpperCase() + m[2].toLowerCase();
        }).filter(w => w.length > 0).join(' '); // Filter empty and join with single space
    };

    const attachTitlecase = (el) => {
        if (!el || el.dataset.titlecaseBound === '1') return;
        
        let lastValue = el.value;
        
        el.addEventListener('input', function(e) {
            const cursorPos = this.selectionStart;
            let value = this.value;
            
            // Prevent multiple consecutive spaces
            value = value.replace(/\s{2,}/g, ' ');
            
            // Apply title case formatting while typing
            // Format each word as user types (when space is pressed or word completes)
            const words = value.split(' ');
            const formattedWords = words.map((word, index) => {
                if (!word) return '';
                // Only format if word has at least one letter
                const match = word.match(/^([A-Za-z])(.*)$/);
                if (match) {
                    return match[1].toUpperCase() + match[2].toLowerCase();
                }
                return word;
            });
            
            value = formattedWords.join(' ');
            
            // Update value if changed
            if (value !== this.value) {
                this.value = value;
                // Try to maintain cursor position as much as possible
                const newPos = Math.min(cursorPos, value.length);
                this.setSelectionRange(newPos, newPos);
            }
            
            lastValue = value;
        });
        
        // Apply final formatting on blur to ensure consistency
        el.addEventListener('blur', function() {
            if (this.value.trim()) {
                const cursorPos = this.value.length;
                this.value = formatToTitleCase(this.value);
                // Move cursor to end after formatting
                this.setSelectionRange(cursorPos, cursorPos);
            }
        });
        
        el.dataset.titlecaseBound = '1';
        // Initial normalize if prefilled
        if (el.value) el.value = formatToTitleCase(el.value);
    };

    // Bind to any fields explicitly marked
    document.querySelectorAll('input[data-titlecase="1"], textarea[data-titlecase="1"]').forEach(attachTitlecase);

    // Email validation (any valid domain)
    document.getElementById('email').addEventListener('input', function() {
        const email = this.value.trim();
        const feedback = document.getElementById('email-feedback');
        // Simple email pattern for client-side hints; server uses filter_var
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!emailPattern.test(email)) {
            feedback.textContent = 'Please enter a valid email address.';
            this.classList.add('is-invalid');
        } else {
            feedback.textContent = '';
            this.classList.remove('is-invalid');
        }
    });

    // Password validation
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const feedback = document.getElementById('password-feedback');
        // Min 8 chars, at least one lowercase, one uppercase, one number, one symbol
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]).{8,}$/;
        if (!passwordPattern.test(password)) {
            feedback.textContent = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
            this.classList.add('is-invalid');
        } else {
            feedback.textContent = '';
            this.classList.remove('is-invalid');
        }
        // Trigger confirm password validation when password changes
        if (document.getElementById('confirm_password').value) {
            validateConfirmPassword();
        }
    });

    // Confirm password validation (client-side)
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const confirmPasswordFeedback = document.getElementById('confirm_password-feedback');
    
    function validateConfirmPassword() {
        if (confirmPasswordInput.value !== passwordInput.value) {
            confirmPasswordFeedback.textContent = 'Passwords do not match.';
            confirmPasswordInput.classList.add('is-invalid');
        } else {
            confirmPasswordFeedback.textContent = '';
            confirmPasswordInput.classList.remove('is-invalid');
        }
    }
    
    confirmPasswordInput.addEventListener('input', validateConfirmPassword);
    passwordInput.addEventListener('input', validateConfirmPassword);

    // Show/hide password
    document.getElementById('togglePassword')?.addEventListener('click', function () {
        const pwd = document.getElementById('password');
        if (!pwd) return;
        if (pwd.type === 'password') {
            pwd.type = 'text';
            this.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            pwd.type = 'password';
            this.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });
    
    document.getElementById('toggleConfirmPassword')?.addEventListener('click', function () {
        const pwd = document.getElementById('confirm_password');
        if (!pwd) return;
        if (pwd.type === 'password') {
            pwd.type = 'text';
            this.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            pwd.type = 'password';
            this.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });
    </script>
</body>
</html>