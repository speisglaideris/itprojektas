<?php
session_start();

$server = "localhost";
$db = "IT_projektas";
$user = "admin";
$password = "adminas";
$table_posts = "posts";
$table_users = "users";
$table_messages = "messages";
 
$dbc = mysqli_connect($server, $user, $password, $db);
if (!$dbc) {
    die("DB connect error: " . mysqli_connect_error());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'controller') {
        header('Location: post.php?id=' . $id);
        exit;
    }

    $stmt_img = mysqli_prepare($dbc, "SELECT img_path FROM {$table_posts} WHERE post_id = ? LIMIT 1");
    if ($stmt_img) {
        mysqli_stmt_bind_param($stmt_img, "i", $id);
        mysqli_stmt_execute($stmt_img);
        $res_img = mysqli_stmt_get_result($stmt_img);
        $row_img = $res_img ? mysqli_fetch_assoc($res_img) : null;
        mysqli_stmt_close($stmt_img);

        if (!empty($row_img['img_path'])) {
            $imgs = array_filter(array_map('trim', explode(';', $row_img['img_path'])));
            foreach ($imgs as $im) {
                $fp = __DIR__ . '/' . $im;
                if (is_file($fp)) @unlink($fp);
            }
        }
    }

    $del = mysqli_prepare($dbc, "DELETE FROM {$table_posts} WHERE post_id = ?");
    if ($del) {
        mysqli_stmt_bind_param($del, "i", $id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }

    header('Location: index.php');
    exit;
}

$u = mysqli_prepare($dbc, "UPDATE {$table_posts} SET views = views + 1 WHERE post_id = ?");
if ($u) {
    mysqli_stmt_bind_param($u, "i", $id);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);
}

$stmt = mysqli_prepare(
    $dbc,
    "SELECT p.city, p.post_id, p.post_title, p.content, p.img_path, p.posted_on, p.views, p.owner_id, COALESCE(u.username, 'Unknown') AS owner_name
     FROM {$table_posts} p
     LEFT JOIN {$table_users} u ON p.owner_id = u.user_id
     WHERE p.post_id = ? LIMIT 1"
);
if (!$stmt) {
    die("Query prepare failed");
}
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$post = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$post) {
    echo "Pranešimas nerastas.";
    exit;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$comments = [];
$stmtc = mysqli_prepare(
    $dbc,
    "SELECT m.message_id, m.owner_id, m.message_text, COALESCE(u.username, 'Nežinomas') AS owner_name
     FROM {$table_messages} m
     LEFT JOIN {$table_users} u ON m.owner_id = u.user_id
     WHERE m.post_id = ?
     ORDER BY m.message_id ASC"
);
if ($stmtc) {
    mysqli_stmt_bind_param($stmtc, "i", $id);
    mysqli_stmt_execute($stmtc);
    $rc = mysqli_stmt_get_result($stmtc);
    $comments = $rc ? mysqli_fetch_all($rc, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmtc);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_text'])) {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $comment_text = trim((string)$_POST['comment_text']);
    if ($comment_text !== '') {
        $owner_id = (int) $_SESSION['user_id'];
        $post_id = $id;
        $unread = 1;
        $recipient = isset($post['owner_name']) ? $post['owner_name'] : '';

        $ins = mysqli_prepare($dbc, "INSERT INTO {$table_messages} (owner_id, post_id, message_text, unread, recipient) VALUES (?, ?, ?, ?, ?)");
        if ($ins) {
            mysqli_stmt_bind_param($ins, "iisis", $owner_id, $post_id, $comment_text, $unread, $recipient);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }

    header("Location: post.php?id={$id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo e($post['post_title']); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="src/styles.css" rel="stylesheet">
  <script src="src/index_scripts.js" defer></script>
</head>
<body>
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
                if (!empty($messages)) {
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
                }
            ?>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="src/images/logo.png" alt="logo" width="24" height="24" class="d-inline-block align-text-top">
                Simas Velioniškis - IT Projektas: Internetinių skelbimų portalas
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
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
                                echo '<a class="nav-link" id="unread-messages">
                                        <img src="src/images/unread.png" alt="Naujos Žinutės" width="16" height="16">
                                      </a>';
                            } else {
                                echo '<a class="nav-link" id="messages">
                                        <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                                      </a>';
                            }

                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        elseif (isset($_SESSION['username']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'simple' && isset($_SESSION['can_post']) && $_SESSION['can_post'] == 0) {

                            echo "<a class='nav-link disabled' href='advertisement.php'>Naujas skelbimas</a>";

                            if ($unread_count > 0) {
                                echo '<a class="nav-link" id="unread-messages">
                                        <img src="src/images/unread.png" alt="Naujos Žinutės" width="16" height="16">
                                      </a>';
                            } else {
                                echo '<a class="nav-link" id="messages">
                                        <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                                      </a>';
                            }

                            echo "<a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>";
                        }
                        elseif (isset($_SESSION['username']) && $_SESSION['user_type'] === 'controller') {
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
  <main class="container py-4">
    <article class="card" style="background:#acacac; color:#000; border-radius:10px;">
      <div class="card-body">
        <h2 class="card-title"><?php echo e($post['post_title']); ?></h2>
        <div class="mb-2 text-muted">
          <small>Įkėlė: <?php echo e($post['owner_name']); ?> • Miestas: <?php echo $post['city']?> • <?php echo e($post['posted_on']); ?> • Peržiūrų: <?php echo (int)$post['views']; ?></small>
        </div>

        <div class="card-text" style="background:#ffff; padding:15px; border-radius:6px;">
          <?php echo nl2br(e($post['content'])); ?>
        </div>

        <?php
          $img_path = trim((string)$post['img_path']);
          if ($img_path !== '') {
              $images = array_filter(array_map('trim', explode(';', $img_path)));
              foreach ($images as $img) {
                  $safe = e($img);
                  echo "<div class='mt-3'><img src='{$safe}' alt='Skelbimo nuotrauka' class='post-image' data-full='{$safe}' style='max-width:18%; height:auto; border-radius:6px; cursor:zoom-in;'></div>";
              }
          }
        ?>

        <div class="mt-3 mb-3 d-flex gap-2">
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'controller'): ?>
                <form method="post" onsubmit="return confirm('Ar tikrai trinti šį skelbimą?');" style="margin:0;">
                    <input type="hidden" name="delete_post" value="1">
                    <button type="submit" class="btn btn-danger">Trinti skelbimą</button>
                </form>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$post['owner_id']): ?>
                <a href="advertisement_editor.php?post_id=<?php echo (int)$post['post_id']; ?>" class="btn btn-dark">Redaguoti</a>
            <?php endif; ?>
        </div>

        <div class="mt-4">
          <h5>Komentarai</h5>

          <?php if (!empty($_SESSION['user_id'])): ?>
            <form method="post" class="mb-3">
              <div class="mb-2">
                <textarea name="comment_text" rows="3" class="form-control" placeholder="Parašykite komentarą..." required></textarea>
              </div>
              <button class="btn btn-dark" type="submit">Siųsti komentarą</button>
            </form>
          <?php else: ?>
            <div class="mb-3 small text-muted">
              Norėdami palikti komentarą, <a href=# id="pop-up-login-button-post">prisijunkite</a>.
            </div>
          <?php endif; ?>

          <div class="comments-list" style="max-height:40vh; overflow-y:auto; padding:8px;">
            <?php
              if (!empty($comments)) {
                foreach ($comments as $c) {
                  $author = e($c['owner_name']);
                  $text = nl2br(e($c['message_text']));
                  echo "<div class='message-item mb-2' style='background:#fff;padding:10px;border-radius:6px;'>
                          <div><strong>{$author}</strong></div>
                          <div style='margin-top:6px;'>{$text}</div>
                        </div>";
                }
              } else {
                echo "<div class='small text-muted'>Nėra komentarų.</div>";
              }
            ?>
          </div>
        </div>

        <div class="mt-3">
          <a class="btn btn-dark" href="index.php">Atgal</a>
        </div>
      </div>
    </article>
  </main>
  
    <div id="img-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);justify-content:center;align-items:center;z-index:20000;cursor:zoom-out;">
      <img id="lightbox-img" src="" alt="" style="max-width:95%;max-height:95%;box-shadow:0 6px 24px rgba(0,0,0,0.6);border-radius:6px;">
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
      const lightbox = document.getElementById('img-lightbox');
      const lbimg = document.getElementById('lightbox-img');
      if (!lightbox || !lbimg) return;
      document.querySelectorAll('.post-image').forEach(img => {
        img.addEventListener('click', () => {
          const src = img.dataset.full || img.src;
          lbimg.src = src;
          lightbox.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        });
      });
      lightbox.addEventListener('click', () => {
        lightbox.style.display = 'none';
        lbimg.src = '';
        document.body.style.overflow = '';
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
          lightbox.style.display = 'none';
          lbimg.src = '';
          document.body.style.overflow = '';
        }
      });
    });
    </script>
  </body>
</html>