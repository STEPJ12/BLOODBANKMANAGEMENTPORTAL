<?php
// Include database connection
// Encryption key for sensitive data
$encryption_key = null; // deprecated in favor of centralized helpers
require_once 'config/db.php';

// Define variables and set to empty values
$role = $name = $email = $password = $confirm_password = "";
$name_err = $email_err = $password_err = $confirm_password_err = "";

// Set role from URL parameter if available
if (isset($_GET['role']) && in_array($_GET['role'], ['donor', 'patient'])) {
    $role = $_GET['role'];
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate role
    if (empty($_POST["role"] ?? "")) {
        $name_err = "Please select a role.";
    } else {
        $role = trim($_POST["role"]);
    }

    // Validate name (letters and single spaces, title case)
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = normalize_input($_POST["name"], true);
        if (!preg_match('/^(?:[A-Z][a-z]*)(?: [A-Z][a-z]*)*$/', $name)) {
            $name_err = "Each word must start with a capital letter, only single spacing allowed.";
        }
    }

    // Validate email (server-side)
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = normalize_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Prepare a select statement to check if the email already exists
            $conn = getConnection();

            // Ensure role is set and valid - use whitelist mapping for table names
            $table_map = [
                'donor' => 'donor_users',
                'patient' => 'patient_users'
            ];
            if (!in_array($role, ['donor', 'patient'])) {
                $email_err = "Invalid role selected.";
            } else {
                $table_name = $table_map[$role]; // Use whitelist mapping instead of concatenation
                $sql = "SELECT id FROM " . $table_name . " WHERE email = ?";

                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bindParam(1, $email, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        if ($stmt->rowCount() == 1) {
                            $email_err = "This email is already taken.";
                        }
                    } else {
                        echo "Oops! Something went wrong. Please try again later.";
                    }

                    unset($stmt);
                }
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

    // Check input errors before inserting in database
    if (empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        // Use whitelist mapping for table names to prevent SQL injection
        $table_map = [
            'donor' => 'donor_users',
            'patient' => 'patient_users'
        ];
        if (!in_array($role, ['donor', 'patient'])) {
            echo "Oops! Invalid role selected.";
            exit;
        }
        $table_name = $table_map[$role]; // Use whitelist mapping instead of concatenation
        $sql = "INSERT INTO " . $table_name . " (name, email, password) VALUES (?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(1, $name, PDO::PARAM_STR);
            $stmt->bindParam(2, $email, PDO::PARAM_STR);
            $stmt->bindParam(3, $hashed_password, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // Create email verification token (10 min expiry)
                $newUserId = getConnection()->lastInsertId();
                $token = bin2hex(random_bytes(32));
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
                insertRow(
                    "INSERT INTO email_verifications (user_id, role, token, code, expires_at) VALUES (?, ?, ?, ?, ?)",
                    [ (int)$newUserId, $role, $token, $code, $expiresAt ]
                );
                // Send verification email
                if (!function_exists('sendVerificationEmail')) { require_once __DIR__ . '/includes/functions.php'; }
                sendVerificationEmail($email, $name, $token, $role, $code);
                // Show success message and auto-redirect to code verification
                $success_banner = 'Registered successfully. A 6-digit verification code was sent to your email.';
                $redirect_after_register = 'verify-code.php?role=' . urlencode($role) . '&email=' . urlencode($email);
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            unset($stmt);
        }
    }

    // Close connection
    unset($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Blood Bank Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 100px auto;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 30px auto;
                padding: 0 10px;
            }
            .login-form {
                padding: 15px;
            }
            .login-header {
                margin-bottom: 15px;
            }
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .login-tabs .nav-link {
            border-radius: 0;
            padding: 15px 0;
            font-weight: 500;
        }
        .login-tabs .nav-link.active {
            background-color: transparent;
            border-bottom: 3px solid #dc3545;
            color: #dc3545;
        }
        .login-form {
            padding: 30px;
        }
        .form-control {
            padding: 12px;
            border-radius: 5px;
        }
        .btn-login {
            padding: 12px;
            font-weight: 500;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="bi bi-droplet-fill"></i>
            </div>
            <h2>Blood Bank Portal</h2>
            <p class="text-muted">Create your account</p>
        </div>
        <div class="card login-card">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs login-tabs nav-fill" id="registerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($role === 'donor') ? 'active' : ''; ?>" href="register.php?role=donor">
                            <i class="bi bi-droplet me-2"></i>Donor
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($role === 'patient') ? 'active' : ''; ?>" href="register.php?role=patient">
                            <i class="bi bi-person me-2"></i>Patient
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body login-form">
                <?php if (!empty($success_banner)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success_banner); ?>
                    </div>
                    <script>
                        setTimeout(function(){ window.location.href = <?php echo json_encode($redirect_after_register, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; }, 1500);
                    </script>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role ?? ''); ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your name" autocomplete="name">
                            <div class="invalid-feedback" id="name-feedback"><?php echo $name_err; ?></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="text" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email address" autocomplete="username">
                            <div class="invalid-feedback" id="email-feedback"><?php echo $email_err; ?></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                            <div class="invalid-feedback" id="password-feedback"><?php echo $password_err; ?></div>
                        </div>
                        <small class="text-muted">Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.</small>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm your password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="bi bi-eye"></i></button>
                            <div class="invalid-feedback" id="confirm-password-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 btn-login">
                        <i class="bi bi-person-plus me-2"></i>Sign Up
                    </button>
                </form>
                <script>
                // Auto-hide feedback messages after 30 seconds
                setTimeout(function() {
                    var feedbacks = document.querySelectorAll('.invalid-feedback');
                    feedbacks.forEach(function(fb) {
                        if (fb.textContent.trim() !== '') {
                            fb.style.display = 'none';
                        }
                    });
                }, 5000);
                </script>
                <div class="text-center mt-4">
                    <p class="mb-0">Already have an account? <a href="login.php?role=<?php echo htmlspecialchars(urlencode($role ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">Login here</a></p>
                </div>
            </div>
        </div>
        <div class="login-footer text-muted">
            <p>&copy; <?php echo date('Y'); ?> Blood Bank Portal. All rights reserved.</p>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Name validation
    const nameInput = document.getElementById('name');
    nameInput.addEventListener('input', function() {
        // Remove all non-letters and non-spaces
        let value = this.value.replace(/[^A-Za-z ]/g, '');
        // Remove leading/trailing spaces
        value = value.replace(/^\s+|\s+$/g, '');
        // If more than one space, keep only the first
        let first = value.indexOf(' ');
        if (first !== -1) {
            let before = value.substring(0, first + 1);
            let after = value.substring(first + 1).replace(/ /g, '');
            value = before + after;
        }
        // Auto-capitalize first letter of each word, rest lowercase
        value = value.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
        this.value = value;

        const feedback = document.getElementById('name-feedback');
        // Each word must start with a capital letter, only one space
        const namePattern = /^[A-Z][a-z]+( [A-Z][a-z]+)?$/;
        if (!namePattern.test(value)) {
            feedback.textContent = 'Each word must start with a capital letter, only one space allowed (e.g., John or John Doe).';
            this.classList.add('is-invalid');
        } else {
            feedback.textContent = '';
            this.classList.remove('is-invalid');
        }
    });

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
    });

    // Confirm password validation (client-side)
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const confirmPasswordFeedback = confirmPasswordInput.parentElement.querySelector('.invalid-feedback');
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
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwd = document.getElementById('password');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            this.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            pwd.type = 'password';
            this.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });
    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        const pwd = document.getElementById('confirm_password');
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
