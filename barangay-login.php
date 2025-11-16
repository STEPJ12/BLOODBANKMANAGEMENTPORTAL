<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in as barangay
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'barangay') {
    header('Location: dashboard/barangay/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $user = getRow('SELECT * FROM barangay_users WHERE email = ?', [$email]);
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'barangay';
            $_SESSION['user_name'] = $user['name'];
            header('Location: dashboard/barangay/index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Login - Blood Bank Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-color: #4a5568;
            --light-bg: #f7fafc;
            --card-bg: rgba(255,255,255,0.95);
            --gradient-primary: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --gradient-secondary: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            --shadow-md: 0 10px 25px rgba(59, 130, 246, 0.1);
            --shadow-lg: 0 20px 40px rgba(59, 130, 246, 0.15);
            --border-radius: 12px;
            --border-radius-lg: 16px;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 20%, #fef3c7 40%, #fde68a 60%, #dbeafe 80%, #e0f2fe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            display: flex;
            flex-direction: column;
            color: #2d3748;
            line-height: 1.6;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .login-card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 30%, #eab308 70%, #fbbf24 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3), 0 2px 8px rgba(234, 179, 8, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
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
        
        .login-header h3,
        .login-header p {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .login-header h3 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 1rem;
        }
        
        .login-body {
            padding: 2.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .input-group {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .input-group-text {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 1rem;
        }
        
        .form-control {
            border: none;
            border-radius: 0;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: transparent;
            background: #f8f9fa;
        }
        
        .btn-danger {
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 0.875rem 2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #eab308 0%, #fbbf24 100%);
            color: #1e293b;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .login-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #718096;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--primary-color);
        }
        
        .alert {
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
        }
        
        .alert-danger {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border-left: 4px solid var(--primary-color);
        }
        
        .footer {
            margin-top: auto;
            padding: 1.5rem 0;
            text-align: center;
            color: #718096;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.5);
        }
        
        /* Improved Form Spacing */
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-group:last-of-type {
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            margin-top: 0.5rem;
        }
        
        /* Better Button Spacing */
        .d-grid {
            margin-bottom: 1.5rem;
        }
        
        /* Improved Link Spacing */
        .login-links {
            margin-top: 2rem;
        }
        
        .login-links p {
            margin-bottom: 1rem;
        }
        
        .back-link {
            margin-top: 0.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 1.5rem 1rem;
            }
            
            .login-card {
                max-width: 100%;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-header h3 {
                font-size: 1.5rem;
            }
            
            .login-header p {
                font-size: 0.95rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-label {
                font-size: 0.95rem;
            }
            
            .form-control {
                font-size: 0.95rem;
                padding: 0.7rem 1rem;
            }
            
            .input-group-text {
                padding: 0.7rem 1rem;
            }
            
            .btn-danger {
                padding: 0.875rem 1.5rem;
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 1rem 0.75rem;
            }
            
            .login-header {
                padding: 1.5rem 1.25rem;
            }
            
            .login-header h3 {
                font-size: 1.375rem;
            }
            
            .login-header p {
                font-size: 0.9rem;
            }
            
            .login-body {
                padding: 1.75rem 1.25rem;
            }
            
            .form-group {
                margin-bottom: 1.25rem;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }
            
            .form-control {
                font-size: 0.9rem;
                padding: 0.65rem 0.9rem;
            }
            
            .input-group-text {
                padding: 0.65rem 0.9rem;
                font-size: 0.9rem;
            }
            
            .btn-danger {
                padding: 0.8rem 1.25rem;
                font-size: 0.9rem;
            }
            
            .login-links {
                margin-top: 1.5rem;
            }
            
            .login-links a {
                font-size: 0.9rem;
            }
            
            .back-link {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 400px) {
            .login-header h3 {
                font-size: 1.25rem;
            }
            
            .login-header p {
                font-size: 0.85rem;
            }
            
            .form-control {
                font-size: 0.875rem;
            }
            
            .btn-danger {
                font-size: 0.875rem;
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h3>BHW Login</h3>
                <p>Barangay Health Worker Portal</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="Enter your email address">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fa fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                </form>
                
                <div class="login-links">
                    <p class="mb-3">Don't have an account? <a href="bhw-register.php">Register here</a></p>
                    <a href="barangay-portal.php" class="back-link">
                        <i class="fa fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Barangay Health Worker Portal. All rights reserved.
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 