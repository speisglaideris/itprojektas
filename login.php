<?php
session_start();

$server = "localhost";
$db = "IT_projektas";
$user = "admin";
$password = "adminas";
$table = "users";
$dbc = mysqli_connect($server,$user,$password,$db);
if (!$dbc) { die("DB connect error"); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = mysqli_prepare($dbc, "SELECT user_id, username, password, user_type, can_post FROM {$table} WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) !== 1) {
        $_SESSION['user_id'] = 'wrong';
        header('Location: index.php'); exit;
    }
    $row = mysqli_fetch_assoc($res);
    if (!isset($row['password']) || !password_verify($password, $row['password'])) {
        $_SESSION['user_id'] = 'wrong';
        header('Location: index.php'); exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['user_type'] = $row['user_type'];
    $_SESSION['can_post'] = $row['can_post'];
    header('Location: index.php');
    exit;
}
exit;
?>