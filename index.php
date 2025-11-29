<?php
session_start();
error_reporting(0);

$server = "localhost";
$db = "IT_projektas";
$user = "admin";
$password = "adminas";
$table1 = "users";
$table2 = "posts";
$table3 = "messages";

$dbc = mysqli_connect($server, $user, $password, $db);
if (!$dbc) {
    die("SQL Error: " . mysqli_error($dbc));
}

$stmt = mysqli_prepare($dbc, "SELECT message_id, owner_id, post_id, message_text, unread FROM {$table3} WHERE recipient = ? ORDER BY message_id DESC");

mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$allusers = mysqli_prepare($dbc, "SELECT user_id, username FROM {$table1}");
mysqli_stmt_execute($allusers);
$allusers_result = mysqli_stmt_get_result($allusers);

$usernames = [];
while ($row = mysqli_fetch_assoc($allusers_result)) {
    $usernames[(int)$row['user_id']] = $row['username'];
}

$messages = $result->fetch_all(MYSQLI_ASSOC);

$unread_count = 0;
foreach ($messages as $row) {
    if ($row['unread'] == 1) {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="src/styles.css" rel="stylesheet">
    <script src="src/index_scripts.js?v=1" defer></script>
    <title>Skelbimų portalas</title>
</head>
<body>
    <?php
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === 'wrong') {
            echo '<div class="pop-up-container show-pop-up" id="pop-up-notif">
                    <div class="id-notif">
                        <a id="pop-up-closer-notif" style="margin-bottom: 5px;">
                            <img src="src/images/close.png" alt="close icon" width="16" height="16">
                        </a>
                        <p>Netinkami prisijungimo duomenys!</p>
                        <button class="btn btn-secondary" id="notif-to-login">Prisijungti</button>
                        <p style="margin-top: 5px; margin-bottom: 5px;">- - - - - - arba - - - - - -</p>
                        <button class="btn btn-secondary" id="notif-to-reg">Registruotis</button>
                    </div>
                </div>';
            unset($_SESSION['user_id']);
        }
    ?>
    <div class="pop-up-container" id="pop-up-login">
        <div class="pop-up-content">
            <a id="pop-up-closer-login">
                <img src="src/images/close.png" alt="close icon" width="16" height="16">
            </a>
            <form method="POST" action="login.php">
                <label id="pop-up-label">Prisijungimas</label>
                <input type="text" name="username" placeholder="Vartotojo slapyvardis" class="form-control input-sm mb-3" required>
                <input type="password" name="password" placeholder="Slaptažodis" class="form-control input-sm mb-3" required>
                <button class="pop-up-button" type="submit">Prisijungti</button>
                <p>- - - - - - arba - - - - - -</p>
            </form>
            <button class="pop-up-button" id="replace-with-signup">Registruotis</button>
        </div>
    </div>

    <div class="pop-up-container" id="pop-up-signup">
        <div class="pop-up-content">
            <a id="pop-up-closer-signup">
                <img src="src/images/close.png" alt="close icon" width="16" height="16">
            </a>
            <form method="POST" action="signup.php">
                <label id="pop-up-label">Registracija</label>
                <input type="text" name="username" placeholder="Vartotojo vardas" class="form-control input-sm mb-3" required>
                <input type="email" name="email" placeholder="Elektroninio pašto adresas" class="form-control input-sm mb-3" required>
                <input type="password" name="password" placeholder="Slaptažodis" class="form-control input-sm mb-3" required>
                <input type="password" name="password_confirm" placeholder="Pakartoti slaptažodį" class="form-control input-sm mb-3" required>
                <button class="pop-up-button" type="submit">Registruotis</button>
                <p>- - - - - - arba - - - - - -</p>
            </form>
            <button class="pop-up-button" id="replace-with-login">Prisijungti</button>
        </div>
    </div>

    <div class="message-pop-up-container" id="messages">
        <div class="message-pop-up-content">
            <?php
                foreach ($messages as $row) {
                    $sender = $row["owner_id"];
                    $post_id = $row["post_id"];
                    $message_text = $row["message_text"];

                    $userq = mysqli_prepare($dbc, "SELECT username FROM {$table1} WHERE user_id = ?");
                    mysqli_stmt_bind_param($userq, "i", $sender);
                    mysqli_stmt_execute($userq);
                    $user_result = mysqli_stmt_get_result($userq);

                    foreach ($user_result as $user_row) {
                        $sender_name = $user_row["username"];
                        echo "<div class='message-item'>
                                <strong>{$sender_name}</strong> pakomentavo ant jūsų skelbimo: nr. <strong>{$post_id}</strong>. 
                                <i style='opacity:0.75;'>{$message_text}</i>
                              </div>";
                    }
                }
            ?>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="src/images/logo.png" alt="logo" width="24" height="24" class="d-inline-block align-text-top">
                Simas Velioniškis - IT Projektas: Internetinių skelbimų portalas
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link active" aria-current="page" href="index.php">Pagrindinis puslapis</a>

                    <?php
                        if (isset($_SESSION['username']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
                            echo "<a class='nav-link' href='admin.php'>Administaciniai nustatymai</a>";
                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        elseif (isset($_SESSION['username']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'simple' && isset($_SESSION['can_post']) && $_SESSION['can_post'] == 1) {

                            echo "<a class='nav-link' href='advertisement.php'>Naujas skelbimas</a>";

                            if ($unread_count > 0) {
                                echo '<a class="nav-link" id="unread-messages-1">
                                        <img src="src/images/unread.png" alt="Naujos Žinutės" width="16" height="16">
                                      </a>';
                            } else {
                                echo '<a class="nav-link" id="messages-1">
                                        <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                                      </a>';
                            }

                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        elseif (isset($_SESSION['username']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'simple' && isset($_SESSION['can_post']) && $_SESSION['can_post'] == 0) {

                            echo "<a class='nav-link disabled' href='advertisement.php'>Naujas skelbimas</a>";

                            if ($unread_count > 0) {
                                echo '<a class="nav-link" id="unread-messages-0">
                                        <img src="src/images/unread.png" alt="Naujos Žinutės" width="16" height="16">
                                      </a>';
                            } else {
                                echo '<a class="nav-link" id="messages-0">
                                        <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                                      </a>';
                            }

                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        elseif ($_SESSION['user_type'] === 'controller') {
                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        else {
                            echo '<a class="nav-link" id="pop-up-login-button">Prisijungimas</a>';
                            echo '<a class="nav-link" id="pop-up-signup-button">Registracija</a>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="posts-container">
        <?php
            $post_query = mysqli_prepare($dbc, "SELECT post_id, owner_id, post_title, content, img_path, posted_on, views FROM {$table2} ORDER BY posted_on DESC");
            mysqli_stmt_execute($post_query);
            $post_result = mysqli_stmt_get_result($post_query);
            foreach( $post_result as $post ) {
                $post_age = strtotime($post['posted_on']);
                $time_now = time();
                $post_owner = $post['owner_id'];
                $post_title = $post['post_title'];
                $post_content = $post['content'];
                $post_content_arr = explode(" ", $post_content);
                $post_description = "";
                for ($word_count = 0; $word_count < 25; $word_count++) {
                    if (!isset($post_content_arr[$word_count])) {
                        break;
                    }
                    if ($post_description !== "") {
                        $post_description .= " ";
                    }
                    $post_description .= $post_content_arr[$word_count];
                }
                $view_count = $post['views'];
                $post_username = isset($usernames[(int)$post_owner]) ? $usernames[(int)$post_owner] : 'Unknown';
                $calc = $time_now - $post_age;
                if ($time_now - $post_age > 2629744 && ($_SESSION['user_type'] == 'admin' || !isset($_SESSION['user_type']))) {
                    echo 
                    "<div class='a-post' style='background-color: #4d2525ff; pointer-events: none;'>
                        <h5 style='margin-bottom: 2px;'>{$post_title}</h5>
                        <small><i style='opacity: 0.75;'>Skelbimą paskelbė: {$post_username}</i></small>
                        <p style='margin-top: 10px; margin-bottom: 2px;'><strong>Aprašas:</strong> {$post_description}...</p>
                        <a href='post.php?id={$post['post_id']}'>Plačiau</a>
                    </div>";
                }
                elseif ($time_now - $post_age > 2629744) {
                    echo 
                    "<div class='a-post' style='background-color: #4d2525ff; pointer-events: none;'>
                        <h5 style='margin-bottom: 2px;'>{$post_title}</h5>
                        <small><i style='opacity: 0.75;'>Skelbimą paskelbė: {$post_username}. Peržiūrų sk.: {$view_count}</i></small>
                        <p style='margin-top: 10px; margin-bottom: 2px;'><strong>Aprašas:</strong> {$post_description}...</p>
                        <a class='disabled' href='post.php?id={$post['post_id']}'>Plačiau</a>
                    </div>";
                }
                elseif ($time_now - $post_age < 2629744 && ($_SESSION['user_type'] == 'admin' || !isset($_SESSION['user_type']))) {
                    echo 
                    "<div class='a-post'>
                        <h5 style='margin-bottom: 2px;'>{$post_title}</h5>
                        <small><i style='opacity: 0.75;'>Skelbimą paskelbė: {$post_username}</i></small>
                        <p style='margin-top: 10px; margin-bottom: 2px;'><strong>Aprašas:</strong> {$post_description}...</p>
                        <a href='post.php?id={$post['post_id']}'>Plačiau</a>
                    </div>";
                }
                else{
                    echo 
                    "<div class='a-post'>
                        <h5 style='margin-bottom: 2px;'>{$post_title}</h5>
                        <small><i style='opacity: 0.75;'>Skelbimą paskelbė: {$post_username}. Peržiūrų sk.: {$view_count}</i></small>
                        <p style='margin-top: 10px; margin-bottom: 2px;'><strong>Aprašas:</strong> {$post_description}...</p>
                        <a href='post.php?id={$post['post_id']}'>Plačiau</a>
                    </div>";
                }
            }
        ?>
    </div>
</body>
</html>
