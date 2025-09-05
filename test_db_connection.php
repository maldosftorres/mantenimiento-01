<?php
// test_db_connection.php

echo "Attempting to connect to database...<br>";

// Include the database configuration file
include 'db_config.php'; // This line is crucial. Make sure db_config.php exists and is in the same directory.

// After including, check if $pdo exists and is a PDO object
if (isset($pdo) && $pdo instanceof PDO) {
    echo "<h2>SUCCESS: Database connection established!</h2>";
    echo "Details:<br>";
    echo "Host: " . (isset($host) ? htmlspecialchars($host) : 'N/A') . "<br>";
    echo "Database: " . (isset($db) ? htmlspecialchars($db) : 'N/A') . "<br>";
    echo "User: " . (isset($user) ? htmlspecialchars($user) : 'N/A') . "<br>";
    echo "<br>You can now try running generate_pdf.php if this is successful.";

    // Optional: Try a simple query to confirm table exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM conformidades_digitales");
        $count = $stmt->fetchColumn();
        echo "<h3 style='color: green;'>Table 'conformidades_digitales' exists and has " . $count . " rows.</h3>";
    } catch (PDOException $e) {
        echo "<h3 style='color: red;'>WARNING: Could not query 'conformidades_digitales' table.</h3>";
        echo "<p style='color: red;'>Possible reasons: Table name 'conformidades_digitales' is wrong, or table doesn't exist in the '$db' database.</p>";
        echo "<p style='color: red;'>Error message: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

} else {
    echo "<h2 style='color: red;'>ERROR: Database connection FAILED!</h2>";
    echo "<p style='color: red;'>The \$pdo variable was not created or is not a PDO object.</p>";
    echo "<p style='color: red;'>This means <b>db_config.php</b> failed to connect.</p>";
    echo "<p style='color: red;'>Please check your <b>db_config.php</b> file for the following:</p>";
    echo "<ul>";
    echo "<li>Is MySQL/MariaDB running in your XAMPP Control Panel?</li>";
    echo "<li>Correct database name (<code>\$db = 'mantenimiento_db';</code>) - check for typos!</li>";
    echo "<li>Correct username (<code>\$user = 'root';</code>)</li>";
    echo "<li>Correct password (<code>\$pass = '';</code> - usually empty for 'root' in XAMPP. Make sure it's *empty* if you don't have one, or contains your actual password if you do.)</li>";
    echo "</ul>";
    echo "<p style='color: red;'>The error message from db_config.php (if any) should be displayed right above this message.</p>";
}
?>