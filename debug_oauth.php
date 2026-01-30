<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers/GoogleAuth.php';

echo "<h2>OAuth Debug Information</h2>";

echo "<h3>Session Info:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";

echo "<h3>OAuth State:</h3>";
if (isset($_SESSION['google_oauth_state'])) {
    echo "Current State: " . $_SESSION['google_oauth_state'] . "<br>";
    echo "State Timestamp: " . ($_SESSION['google_oauth_timestamp'] ?? 'Not set') . "<br>";
    if (isset($_SESSION['google_oauth_timestamp'])) {
        $age = time() - $_SESSION['google_oauth_timestamp'];
        echo "State Age: " . $age . " seconds<br>";
        echo "Expires in: " . (600 - $age) . " seconds<br>";
    }
} else {
    echo "No OAuth state in session<br>";
}

echo "<h3>Configuration:</h3>";
echo "Base URL: " . BASE_URL . "<br>";
echo "Google Client ID: " . (GOOGLE_CLIENT_ID ? 'Set (' . substr(GOOGLE_CLIENT_ID, 0, 20) . '...)' : 'Not set') . "<br>";
echo "Redirect URI: " . BASE_URL . '/api/google-callback.php<br>';

echo "<h3>Generate New OAuth URL:</h3>";
$oauth_url = GoogleAuth::getLoginUrl();
echo '<a href="' . $oauth_url . '" target="_blank">Test Google OAuth</a><br>';
echo "URL: " . $oauth_url . "<br>";

echo "<h3>Clear Session:</h3>";
if (isset($_GET['clear'])) {
    session_destroy();
    session_start();
    echo "Session cleared!<br>";
}
echo '<a href="?clear=1">Clear Session</a>';
?>