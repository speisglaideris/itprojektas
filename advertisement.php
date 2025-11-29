<?php
session_start();

if (empty($_SESSION['username']) || $_SESSION['user_type'] !== 'simple' || $_SESSION['can_post'] != 1) {
    header('Location: index.php');
    exit;
}

$server = "localhost";
$db = "IT_projektas";
$user = "admin";
$password = "adminas";
$table_posts = "posts";
$table_messages = "messages";

$dbc = mysqli_connect($server, $user, $password, $db);
if (!$dbc) { die("DB connect error: " . mysqli_connect_error()); }

$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$max_images = 5;
$max_width = 1280;
$max_height = 720;
$font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

$stmt = mysqli_prepare($dbc, "SELECT message_id, owner_id, post_id, message_text, unread FROM {$table_messages} WHERE recipient = ? ORDER BY message_id DESC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $messages = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
} else {
    $messages = [];
}
$unread_count = 0;
foreach ($messages as $row) {
    if (!empty($row['unread']) && (int)$row['unread'] === 1) {
        $unread_count++;
    }
}

function resize_and_watermark($src_path, $dest_path, $max_w, $max_h, $font_path) {
    $info = @getimagesize($src_path);
    if (!$info) return false;
    $mime = $info['mime'];

    if ($mime === 'image/jpeg' || $mime === 'image/pjpeg') {
        $src = @imagecreatefromjpeg($src_path);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($src_path);
    } else {
        return false;
    }
    if (!$src) return false;

    $ow = imagesx($src);
    $oh = imagesy($src);

    $scale = min(1, min($max_w / $ow, $max_h / $oh));
    $nw = max(1, (int)round($ow * $scale));
    $nh = max(1, (int)round($oh * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    } else {
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);

    $text = 'ITPROJEKTAS';
    $font_size = max(12, (int)round($nw / 20));
    $padding = (int)round($nw / 50);

    $white_alpha = imagecolorallocatealpha($dst, 255, 255, 255, 50);
    $black_alpha = imagecolorallocatealpha($dst, 0, 0, 0, 70);

    if (file_exists($font_path)) {
        $box = imagettfbbox($font_size, 0, $font_path, $text);
        $text_w = abs($box[2] - $box[0]);
        $text_h = abs($box[7] - $box[1]);
        $x = $nw - $text_w - $padding;
        $y = $nh - $padding;
        imagettftext($dst, $font_size, 0, $x+2, $y+2, $black_alpha, $font_path, $text);
        imagettftext($dst, $font_size, 0, $x, $y, $white_alpha, $font_path, $text);
    } else {
        $font = 5;
        $text_w = imagefontwidth($font) * strlen($text);
        $text_h = imagefontheight($font);
        $x = $nw - $text_w - $padding;
        $y = $nh - $text_h - $padding;
        imagestring($dst, $font, $x+1, $y+1, $text, $black_alpha);
        imagestring($dst, $font, $x, $y, $text, $white_alpha);
    }

    $ok = imagejpeg($dst, $dest_path, 85);

    imagedestroy($src);
    imagedestroy($dst);

    return $ok;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploaded_count = 0;
    if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($name === '') continue;
            $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_OK;
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $uploaded_count++;
        }
    }

    if ($uploaded_count > $max_images) {
        $_SESSION['photo_limit_exceeded'] = true;
        header('Location: advertisement.php');
        exit;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));

    $allowed_cities = ['Kaunas','Vilnius','Klaipėda','Šiauliai','Alytus'];
    if ($title === '') $errors[] = 'Pavadinimas privalomas.';
    if ($content === '') $errors[] = 'Tekstas privalomas.';
    if (!in_array($city, $allowed_cities, true)) $errors[] = 'Neteisingas miestas.';

    $saved_paths = [];
    if (empty($errors) && !empty($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = min($max_images, count($files['name']));
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $files['tmp_name'][$i];
            if (!is_uploaded_file($tmp)) continue;
            $info = @getimagesize($tmp);
            if (!$info) continue;
            $mime = $info['mime'];
            if (!in_array($mime, ['image/jpeg','image/pjpeg','image/png'], true)) continue;

            $basename = 'post_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
            $dest_path = $upload_dir . '/' . $basename;
            $public_path = 'uploads/' . $basename;

            $ok = resize_and_watermark($tmp, $dest_path, $max_width, $max_height, $font_path);
            if ($ok) $saved_paths[] = $public_path;
        }
    }

    if (empty($errors)) {
        $img_path = implode(';', $saved_paths);
        $stmt = mysqli_prepare($dbc, "INSERT INTO {$table_posts} (post_title, content, img_path, posted_on, views, owner_id, city) VALUES (?, ?, ?, NOW(), 0, ?, ?)");
        if ($stmt) {
            $owner_id = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($stmt, "sssis", $title, $content, $img_path, $owner_id, $city);
            mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($dbc);
            mysqli_stmt_close($stmt);
            header("Location: post.php?id=" . (int)$new_id);
            exit;
        } else {
            $errors[] = 'Klaida saugant įrašą.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Naujas skelbimas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="src/styles.css" rel="stylesheet">
  <script src="src/index_scripts.js" defer></script>
</head>
<body class="fixed-nav-padding">
<?php if (!empty($_SESSION['photo_limit_exceeded'])) {
  echo '<div class="pop-up-container show-pop-up" id="pop-up-notif-photo">
        <div class="id-notif-photo">
            <a id="pop-up-closer-notif-photo" style="margin-bottom: 5px;">
                <img src="src/images/close.png" alt="close icon" width="16" height="16">
            </a>
            <p>Viršijote leistiną nuotraukų skaičių (maks. 5).</p>
        </div>
    </div>';
unset($_SESSION['photo_limit_exceeded']);
}?>

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
                    <a class='nav-link' aria-current='page' href='index.php'>Pagrindinis puslapis</a>
                    <a class='nav-link active' href='advertisement.php'>Naujas skelbimas</a>
                    <?php
                      if ($unread_count > 0) {
                          echo '<a class="nav-link" id="unread-messages">
                                  <img src="src/images/unread.png" alt="Naujos Žinutės" width="16" height="16">
                                </a>';
                      } else {
                          echo '<a class="nav-link" id="messages">
                                  <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                                </a>';
                      }
                    ?>
                    <a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>
                </div>
            </div>
        </div>
    </nav>

<main class="container py-4">
  <div class="mx-auto" style="max-width:800px;">
    <div class="card p-3" style="background:#acacac; color:#000; border-radius:10px;">
      <h4 class="mb-3">Sukurti naują skelbimą</h4>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label class="form-label">Pavadinimas</label>
          <input name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Skelbimo turinys</label>
          <textarea name="content" class="form-control" rows="6" required><?php echo htmlspecialchars($_POST['content'] ?? '', ENT_QUOTES); ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Miestas</label>
          <select name="city" class="form-select" required>
            <option value="">-- Pasirinkite --</option>
            <?php
            $cities = ['Kaunas','Vilnius','Klaipėda','Šiauliai','Alytus'];
            $sel = $_POST['city'] ?? '';
            foreach ($cities as $c) {
                $s = $c === $sel ? ' selected' : '';
                echo "<option value=\"" . htmlspecialchars($c, ENT_QUOTES) . "\"{$s}>" . htmlspecialchars($c) . "</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Nuotraukos (iki 5) — JPG/PNG</label>
          <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png" multiple>
        </div>

        <div>
          <button class="btn btn-primary" type="submit">Publikuoti</button>
          <a class="btn btn-danger" href="index.php" style="margin-left: 10px;">Atšaukti</a>
        </div>
      </form>
    </div>
  </div>
</main>
</body>
</html>