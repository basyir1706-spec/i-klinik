<?php
// Verify Pages Script

$pages = [
    'eklinik/student/profile.php',
    'eklinik/student/notifications.php',
    'eklinik/doctor/profile.php',
    'eklinik/doctor/notifications.php'
];

foreach ($pages as $page) {
    if (file_exists($page)) {
        echo "✅ Found $page\n";
        // Check for syntax errors
        $output = shell_exec("php -l $page");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "   Syntax OK\n";
        } else {
            echo "   ❌ Syntax Error: $output\n";
        }
    } else {
        echo "❌ Missing $page\n";
    }
}
?>
