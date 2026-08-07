<?php
require_once 'includes/db.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; // plain text

    $query = "SELECT * FROM users WHERE username='$username' AND password='$password' LIMIT 1";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        if (is_salesman()) {
            header("Location: pages/deliveries.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $software_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .login-container {
            display: flex;
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }

        /* Left Side - Branding */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
            padding: 48px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-logo {
            margin-bottom: 40px;
        }

        .brand-logo h1 {
            font-family: 'Quicksand', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .brand-logo p {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 8px;
        }

        .brand-content {
            flex: 1;
        }

        .brand-content h2 {
            font-family: 'Quicksand', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .brand-content p {
            font-size: 15px;
            opacity: 0.85;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            opacity: 0.9;
        }

        .feature-list li i {
            width: 20px;
            font-size: 16px;
        }

        .brand-footer {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 40px;
        }

        /* Right Side - Login Form */
        .login-form {
            flex: 1;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h3 {
            font-family: 'Quicksand', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #1a2c3e;
            margin: 0 0 8px 0;
        }

        .form-header p {
            color: #6c7a8a;
            font-size: 14px;
            margin: 0;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa9b9;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            transition: all 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #A04657;
            box-shadow: 0 0 0 3px rgba(160,70,87,0.1);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #A04657;
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .btn-login:hover {
            background: #7f3543;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(160,70,87,0.3);
        }

        .alert-custom {
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-custom i {
            font-size: 16px;
        }

        .alert-custom.alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eef2f6;
        }

        .form-footer p {
            font-size: 12px;
            color: #8a99aa;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
                margin: 0 auto;
            }
            
            .login-brand {
                padding: 32px;
                text-align: center;
            }
            
            .brand-content h2 {
                font-size: 24px;
            }
            
            .feature-list {
                text-align: left;
            }
            
            .login-form {
                padding: 32px;
            }
            
            .form-header h3 {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .login-brand {
                padding: 24px;
            }
            
            .login-form {
                padding: 24px;
            }
            
            .brand-content h2 {
                font-size: 20px;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            
            <!-- Left Side - Branding -->
            <div class="login-brand">
                <div class="brand-logo">
                    <h1><?php echo $software_name; ?></h1>
                    <p>Water Supply Management System</p>
                </div>
                
                <div class="brand-content">
                    <h2>Welcome to<br>Fly Water H2O System</h2>
                    <p>Manage your water supply business efficiently with our comprehensive solution.</p>
                    
                    <ul class="feature-list">
                        <li><i class="fas fa-check-circle"></i> Customer Management</li>
                        <li><i class="fas fa-check-circle"></i> Daily Delivery Tracking</li>
                        <li><i class="fas fa-check-circle"></i> Payment Collection</li>
                        <li><i class="fas fa-check-circle"></i> Bottle Tracking System</li>
                        <li><i class="fas fa-check-circle"></i> Stock Management</li>
                        <li><i class="fas fa-check-circle"></i> Detailed Reports</li>
                    </ul>
                </div>
                
                <div class="brand-footer">
                    <p>Secure access for authorized personnel only</p>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="login-form">
                <div class="form-header">
                    <h3>Sign In</h3>
                    <p>Enter your credentials to access the dashboard</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert-custom alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group-custom">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" class="form-control" placeholder="Enter your username" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group-custom">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-arrow-right-to-bracket me-2"></i> Login
                    </button>
                </form>
                
                <div class="form-footer">
                    <p><i class="fas fa-shield-alt me-1"></i> Secure Login | <?php echo date('Y'); ?> <?php echo $software_name; ?></p>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>