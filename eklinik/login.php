<?php
/**
 * Login Page - i-Klinik Politeknik Besut
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (hasRole('doctor')) {
        redirect(BASE_URL . '/doctor/dashboard.php');
    } else {
        redirect(BASE_URL . '/student/dashboard.php');
    }
}

$error = '';
$success = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log POST data
    error_log("POST received: " . print_r($_POST, true));
    
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Ralat keselamatan: Sesi tidak sah. Sila cuba lagi.';
    } else {
        $matrixNo = isset($_POST['matrix_no']) ? trim($_POST['matrix_no']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'student';

        if (empty($password)) {
            $error = 'Sila masukkan kata laluan';
        } elseif (empty($matrixNo) && $role === 'student') {
            $error = 'Sila masukkan nombor matrik';
        } elseif (empty($email) && $role === 'doctor') {
            $error = 'Sila masukkan emel';
        } else {
            try {
                $conn = getDBConnection();
                
                if ($role === 'student') {
                    // Login with matrix number for students/staff
                    $stmt = $conn->prepare("SELECT * FROM users WHERE matrix_no = ? AND role IN ('student', 'staff') AND status = 'active'");
                    $stmt->bind_param("s", $matrixNo);
                } else {
                    // Login with email for doctor
                    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role IN ('doctor', 'staff') AND status = 'active'");
                    $stmt->bind_param("s", $email);
                }

                $stmt->execute();
                $result = $stmt->get_result();

                error_log("Query result: " . $result->num_rows . " rows");

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Verify password - plain text comparison
                    if ($password === $user['password']) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['matrix_no'] = $user['matrix_no'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['is_oku'] = $user['is_oku'] ?? 0;
                        $_SESSION['department'] = $user['department'];
                        $_SESSION['course'] = $user['course'];

                        // Log activity
                        try {
                            logActivity($user['id'], 'User logged in', 'Login successful from ' . $_SERVER['REMOTE_ADDR']);
                        } catch (Exception $e) {
                            error_log("Log activity failed: " . $e->getMessage());
                        }

                        // Redirect based on role
                        if ($user['role'] === 'doctor') {
                            header("Location: " . BASE_URL . "/doctor/dashboard.php");
                            exit();
                        } else {
                            header("Location: " . BASE_URL . "/student/dashboard.php");
                            exit();
                        }
                    } else {
                        $error = 'Kata laluan tidak sah. Cuba: 123456';
                    }
                } else {
                    $error = 'Pengguna tidak dijumpai. Sila semak nombor matrik/emel.';
                }

                closeDBConnection($conn);
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Ralat sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - i-Klinik Politeknik Besut</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-box {
            max-width: 500px;
        }
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .role-option {
            flex: 1;
            padding: 15px;
            border: 2px solid var(--light-green);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }
        .role-option:hover {
            border-color: var(--primary-green);
            background: rgba(0, 255, 0, 0.1);
        }
        .role-option.active {
            border-color: var(--primary-green);
            background: rgba(0, 255, 0, 0.2);
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
        }
        .role-option i {
            font-size: 2rem;
            margin-bottom: 8px;
            display: block;
            color: var(--primary-green);
        }
        .role-option span {
            font-size: 0.9rem;
            color: var(--gray-300);
        }
        .form-toggle {
            display: none;
        }
        .form-toggle.active {
            display: block;
        }
        .debug-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.8rem;
            color: #ff0;
        }
    </style>
</head>
<body class="bg-mesh-green">
    <div class="login-container">
        <div class="login-box">
            <h1><i class="fas fa-heartbeat"></i> i-Klinik</h1>
            <p class="subtitle">Politeknik Besut</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="role-selector">
                    <div class="role-option active" onclick="selectRole('student')" id="role-student">
                        <i class="fas fa-user-graduate"></i>
                        <span>Pelajar / Staff</span>
                    </div>
                    <div class="role-option" onclick="selectRole('doctor')" id="role-doctor">
                        <i class="fas fa-user-md"></i>
                        <span>Doktor</span>
                    </div>
                </div>

                <input type="hidden" name="role" id="role" value="student">

                <div id="student-form" class="form-toggle active">
                    <div class="form-group">
                        <label for="matrix_no"><i class="fas fa-id-card"></i> Nombor Matrik / Staff ID</label>
                        <input type="text" class="form-control" id="matrix_no" name="matrix_no"
                               placeholder="Contoh: 34DIT24F1001" required autocomplete="off">
                    </div>
                </div>

                <div id="doctor-form" class="form-toggle">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Emel</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Contoh: doctor@polibesut.edu.my" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Kata Laluan</label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Masukkan kata laluan" required>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Log Masuk
                </button>
            </form>

            <p style="text-align: center; margin-top: 20px; color: var(--gray-600); font-size: 0.85rem;">
                Kata laluan lalai: <strong>123456</strong>
            </p>
            
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                POST: <?php echo print_r($_POST, true); ?><br>
                Session: <?php echo print_r($_SESSION, true); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let currentRole = 'student';
        
        function selectRole(role) {
            currentRole = role;
            document.getElementById('role').value = role;
            
            // Update role options styling
            document.querySelectorAll('.role-option').forEach(opt => {
                opt.classList.remove('active');
            });
            document.getElementById('role-' + role).classList.add('active');
            
            // Toggle forms
            if (role === 'student') {
                document.getElementById('student-form').classList.add('active');
                document.getElementById('doctor-form').classList.remove('active');
                document.getElementById('matrix_no').required = true;
                document.getElementById('email').required = false;
            } else {
                document.getElementById('student-form').classList.remove('active');
                document.getElementById('doctor-form').classList.add('active');
                document.getElementById('matrix_no').required = false;
                document.getElementById('email').required = true;
            }
        }
        
        function validateForm() {
            const password = document.getElementById('password').value;
            if (!password || password.length < 1) {
                alert('Sila masukkan kata laluan!');
                return false;
            }
            
            if (currentRole === 'student') {
                const matrixNo = document.getElementById('matrix_no').value;
                if (!matrixNo || matrixNo.length < 1) {
                    alert('Sila masukkan nombor matrik!');
                    return false;
                }
            } else {
                const email = document.getElementById('email').value;
                if (!email || email.length < 1) {
                    alert('Sila masukkan emel!');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            return true;
        }
        
        // Allow Enter key to submit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const inputs = form.querySelectorAll('input');
            
            inputs.forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
