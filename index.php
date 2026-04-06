<?php
session_start();
require_once 'FenyDB.php';

$db = new FenyDB('data');

// logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// check if the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>

<?php
// get all users
$users = $db->getAll('users');

// display users
echo "<h1>Users List</h1>";
foreach ($users as $user) {
    $username = $user['username'] ?? 'N/A';
    $email = $user['email'] ?? 'N/A';
    echo htmlspecialchars($username) . ' - ' . htmlspecialchars($email) . '<br>';
}

// logout button
echo '<br><a href="index.php?logout=1">Logout</a>';
?>
