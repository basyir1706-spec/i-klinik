<?php
/**
 * Setup Checker - i-Klinik Politeknik Besut
 * File ini akan check jika semua requirements telah dipenuhi
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Checker - i-Klinik</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #022c2e 0%, #001f1f 100%);
            color: #fff;
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 10px; color: #00ff00; text-shadow: 0 0 20px rgba(0, 255, 0, 0.5); }
        .subtitle { text-align: center; color: #aaa; margin-bottom: 40px; }
        .check-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .check-item.success { border-color: #00ff00; background: rgba(0, 255, 0, 0.1); }
        .check-item.error { border-color: #ff4444; background: rgba(255, 68, 68, 0.1); }
        .check-item.warning { border-color: #ffbb33; background: rgba(255, 187, 51, 0.1); }
        .icon { font-size: 2rem; width: 50px; text-align: center; }
        .content { flex: 1; }
        .title { font-weight: bold; font-size: 1.1rem; margin-bottom: 5px; }
        .message { color: #ccc; font-size: 0.9rem; }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #009a44, #00ff00);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0, 255, 0, 0.4); }
        .actions { text-align: center; margin-top: 30px; }
        .code {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
            overflow-x: auto;
        }
        .step {
            background: rgba(0, 154, 68, 0.2);
            border-left: 4px solid #00ff00;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>i-Klinik Setup Checker</h1>
        <p class="subtitle">Politeknik Besut - System Setup Verification</p>

        <?php
        $allPassed = true;
        $conn = null;
        
        // Check 1: PHP Version
        $phpVersion = phpversion();
        $phpOk = version_compare($phpVersion, '7.4.0', '>=');
        ?>
        <div class="check-item <?php echo $phpOk ? 'success' : 'error'; ?>">
            <div class="icon"><?php echo $phpOk ? '✅' : '❌'; ?></div>
            <div class="content">
                <div class="title">PHP Version</div>
                <div class="message">
                    Current: <?php echo $phpVersion; ?> | Required: 7.4.0 or higher
                    <?php if (!$phpOk): ?>
                        <br><strong style="color: #ff4444;">Please upgrade PHP!</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$phpOk) $allPassed = false; ?>

        <?php
        // Check 2: MySQL Connection
        $mysqlOk = false;
        try { $conn = @new mysqli('localhost', 'root', ''); } catch(Exception $e) { $conn = null; }
        if ($conn && !$conn->connect_error) {
            $mysqlOk = true;
        }
        ?>
        <div class="check-item <?php echo $mysqlOk ? 'success' : 'error'; ?>">
            <div class="icon"><?php echo $mysqlOk ? '✅' : '❌'; ?></div>
            <div class="content">
                <div class="title">MySQL Connection</div>
                <div class="message">
                    <?php if ($mysqlOk): ?>
                        Connected to MySQL successfully
                    <?php else: ?>
                        Failed to connect to MySQL
                        <br><strong style="color: #ff4444;">Please start MySQL in XAMPP!</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$mysqlOk) $allPassed = false; ?>

        <?php
        // Check 3: Database Exists
        $dbExists = false;
        if ($mysqlOk && $conn) {
            $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'eklinik'");
            if ($result && $result->num_rows > 0) {
                $dbExists = true;
            }
        }
        ?>
        <div class="check-item <?php echo $dbExists ? 'success' : 'error'; ?>">
            <div class="icon"><?php echo $dbExists ? '✅' : '❌'; ?></div>
            <div class="content">
                <div class="title">Database 'eklinik'</div>
                <div class="message">
                    <?php if ($dbExists): ?>
                        Database exists
                    <?php else: ?>
                        Database 'eklinik' does not exist!
                        <br><strong style="color: #ff4444;">Please import database/eklinik.sql!</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$dbExists) $allPassed = false; ?>

        <?php
        // Check 4: Users Table
        $usersTableOk = false;
        $userCount = 0;
        if ($dbExists && $conn) {
            $conn->select_db('eklinik');
            $result = $conn->query("SELECT COUNT(*) as count FROM users");
            if ($result) {
                $usersTableOk = true;
                $row = $result->fetch_assoc();
                $userCount = $row['count'];
            }
        }
        ?>
        <div class="check-item <?php echo $usersTableOk ? 'success' : 'error'; ?>">
            <div class="icon"><?php echo $usersTableOk ? '✅' : '❌'; ?></div>
            <div class="content">
                <div class="title">Users Table</div>
                <div class="message">
                    <?php if ($usersTableOk): ?>
                        Table exists with <?php echo $userCount; ?> users
                    <?php else: ?>
                        Users table not found!
                        <br><strong style="color: #ff4444;">Please import database/eklinik.sql!</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$usersTableOk) $allPassed = false; ?>

        <?php
        // Check 5: Users with correct password
        $passwordOk = false;
        if ($usersTableOk && $conn) {
            $result = $conn->query("SELECT id, name, role, password FROM users WHERE password = '123456' LIMIT 5");
            if ($result && $result->num_rows > 0) {
                $passwordOk = true;
            }
        }
        ?>
        <div class="check-item <?php echo $passwordOk ? 'success' : 'warning'; ?>">
            <div class="icon"><?php echo $passwordOk ? '✅' : '⚠️'; ?></div>
            <div class="content">
                <div class="title">User Passwords</div>
                <div class="message">
                    <?php if ($passwordOk): ?>
                        Passwords are set correctly (plain text: 123456)
                    <?php else: ?>
                        No users with password '123456' found!
                        <br><strong style="color: #ffbb33;">Run database/reset_passwords.sql to fix!</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // List users if password OK
        if ($passwordOk && $conn) {
            $result = $conn->query("SELECT name, role, matrix_no, email FROM users ORDER BY role, name");
            if ($result && $result->num_rows > 0) {
                echo '<div class="check-item success">';
                echo '<div class="icon">👥</div>';
                echo '<div class="content">';
                echo '<div class="title">Available Users:</div>';
                echo '<div class="message">';
                while ($user = $result->fetch_assoc()) {
                    $loginInfo = '';
                    if ($user['role'] === 'student' || $user['role'] === 'staff') {
                        $loginInfo = $user['matrix_no'] ? 'Matrix: ' . htmlspecialchars($user['matrix_no']) : 'Email: ' . htmlspecialchars($user['email']);
                    } else {
                        $loginInfo = 'Email: ' . htmlspecialchars($user['email']);
                    }
                    echo '<div>• ' . htmlspecialchars($user['name']) . ' (' . ucfirst($user['role']) . ') - ' . $loginInfo . '</div>';
                }
                echo '<div style="margin-top: 10px;"><strong>Password: 123456</strong></div>';
                echo '</div></div></div>';
            }
        }
        
        // Close connection
        if ($conn) {
            $conn->close();
        }
        ?>

        <?php if ($allPassed): ?>
        <div class="check-item success">
            <div class="icon">🎉</div>
            <div class="content">
                <div class="title">All Checks Passed!</div>
                <div class="message">Your system is ready to use!</div>
            </div>
        </div>
        <?php else: ?>
        <div class="check-item error">
            <div class="icon">⚠️</div>
            <div class="content">
                <div class="title">Setup Incomplete</div>
                <div class="message">Please follow the steps below to fix the issues.</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="actions">
            <?php if ($allPassed): ?>
                <a href="login.php" class="btn">🚀 Go to Login Page</a>
            <?php else: ?>
                <h3 style="margin-bottom: 20px;">📋 Steps to Fix:</h3>
                
                <div class="step">
                    <strong>Step 1: Start XAMPP</strong><br>
                    Open XAMPP Control Panel → Start Apache and MySQL
                </div>
                
                <div class="step">
                    <strong>Step 2: Import Database</strong><br>
                    <div class="code">
                        1. Open: http://localhost/phpmyadmin<br>
                        2. Click "SQL" tab<br>
                        3. Copy & paste content from: database/eklinik.sql<br>
                        4. Click "Go"
                    </div>
                </div>
                
                <div class="step">
                    <strong>Step 3: Reset Passwords (if needed)</strong><br>
                    <div class="code">
                        1. Open: http://localhost/phpmyadmin<br>
                        2. Select database 'eklinik'<br>
                        3. Click "SQL" tab<br>
                        4. Copy & paste content from: database/reset_passwords.sql<br>
                        5. Click "Go"
                    </div>
                </div>
                
                <div class="step">
                    <strong>Step 4: Refresh This Page</strong><br>
                    Press F5 to check if all issues are resolved.
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px; color: #666; font-size: 0.85rem;">
            <p>© 2024 i-Klinik Politeknik Besut</p>
        </div>
    </div>
</body>
</html>
