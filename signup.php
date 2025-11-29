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
    $username = trim($_POST['username-reg'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password-reg'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';
    if ($username === '' || $email === '' || $pass === '' || $pass !== $pass2) {
        header('Location: index.php'); exit;
    }
    $s = mysqli_prepare($dbc, "SELECT 1 FROM {$table} WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($s, "s", $username);
    mysqli_stmt_execute($s);
    mysqli_stmt_store_result($s);
    if (mysqli_stmt_num_rows($s) > 0) {
        mysqli_stmt_close($s);
        header('Location: index.php'); exit;
    }
    mysqli_stmt_close($s);
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $user_type = 'simple';
    $ins = mysqli_prepare($dbc, "INSERT INTO {$table} (username, email, password, user_type) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins, "ssss", $username, $email, $hash, $user_type);
    if (mysqli_stmt_execute($ins)) {
        $new_id = mysqli_insert_id($dbc);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $new_id;
        $_SESSION['username'] = $username;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['can_post'] = $row['can_post'];
    }
    mysqli_stmt_close($ins);
    header('Location: index.php'); exit;
}
header('Location: index.php'); exit;
?>