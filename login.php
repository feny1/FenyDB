<?php
session_start();
require_once 'FenyDB.php';

$db = new FenyDB('data');

if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $db->find('users', 'username', $username);
    if (count($user) > 1) {
        echo 'Multiple users found';
        exit;
    }

    if (!empty($user)) {
        $userData = $db->findById('users', $user[0]);
        if ($userData && $userData['password'] == $password) {
            $_SESSION['user'] = $userData;
            header('Location: index.php');
            exit;
        }
    }
    echo 'Invalid username or password';
}
?>

<html>
<form action="login.php" method="post">
    <input type="hidden" name="action" value="login">
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <button type="submit">Login</button>
</form>

</html>