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
if (!$dbc) { die("DB connect error: " . mysqli_connect_error()); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$max_images = 5;
$max_width = 1280;
$max_height = 720;
$font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

function resize_and_watermark($src_path, $dest_path, $max_w, $max_h, $font_path) {
    $info = @getimagesize($src_path); if (!$info) return false;
    $mime = $info['mime'];
    if ($mime === 'image/jpeg' || $mime === 'image/pjpeg') $src = @imagecreatefromjpeg($src_path);
    elseif ($mime === 'image/png') $src = @imagecreatefrompng($src_path);
    else return false;
    if (!$src) return false;
    $ow = imagesx($src); $oh = imagesy($src);
    $scale = min(1, min($max_w / $ow, $max_h / $oh));
    $nw = max(1, (int)round($ow * $scale)); $nh = max(1, (int)round($oh * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') { imagealphablending($dst, false); imagesavealpha($dst, true); $transparent = imagecolorallocatealpha($dst,0,0,0,127); imagefilledrectangle($dst,0,0,$nw,$nh,$transparent); }
    else { $white = imagecolorallocate($dst,255,255,255); imagefilledrectangle($dst,0,0,$nw,$nh,$white); }
    imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $ow,$oh);
    $text = 'ITPROJEKTAS';
    $font_size = max(12, (int)round($nw / 20));
    $padding = (int)round($nw / 50);
    $white_alpha = imagecolorallocatealpha($dst,255,255,255,50);
    $black_alpha = imagecolorallocatealpha($dst,0,0,0,70);
    if (file_exists($font_path)) {
        $box = imagettfbbox($font_size,0,$font_path,$text);
        $text_w = abs($box[2]-$box[0]); $text_h = abs($box[7]-$box[1]);
        $x = $nw - $text_w - $padding; $y = $nh - $padding;
        imagettftext($dst,$font_size,0,$x+2,$y+2,$black_alpha,$font_path,$text);
        imagettftext($dst,$font_size,0,$x,$y,$white_alpha,$font_path,$text);
    } else {
        $font = 5; $text_w = imagefontwidth($font) * strlen($text); $text_h = imagefontheight($font);
        $x = $nw - $text_w - $padding; $y = $nh - $text_h - $padding;
        imagestring($dst,$font,$x+1,$y+1,$text,$black_alpha); imagestring($dst,$font,$x,$y,$text,$white_alpha);
    }
    $ok = imagejpeg($dst, $dest_path, 85);
    imagedestroy($src); imagedestroy($dst);
    return $ok;
}

$id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = mysqli_prepare($dbc, "SELECT * FROM {$table_posts} WHERE post_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$post = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);
if (!$post) { header('Location: index.php'); exit; }

if (empty($_SESSION['user_id']) || ((int)$_SESSION['user_id'] !== (int)$post['owner_id'] && ($_SESSION['user_type'] ?? '') !== 'controller')) {
    header('Location: post.php?id=' . $id); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $allowed_cities = ['Kaunas','Vilnius','Klaipėda','Šiauliai','Alytus'];
    if ($title === '') $errors[] = 'Pavadinimas privalomas.';
    if ($content === '') $errors[] = 'Tekstas privalomas.';
    if (!in_array($city, $allowed_cities, true)) $errors[] = 'Neteisingas miestas.';
    $existing = array_filter(array_map('trim', explode(';', (string)$post['img_path'])));
    $to_delete = $_POST['delete_image'] ?? [];
    foreach ($to_delete as $del) {
        $del = trim($del);
        if ($del === '') continue;
        $idx = array_search($del, $existing, true);
        if ($idx !== false) {
            $fp = __DIR__ . '/' . $existing[$idx];
            if (is_file($fp)) @unlink($fp);
            unset($existing[$idx]);
        }
    }
    $existing = array_values($existing);
    $saved_paths = $existing;
    if (!empty($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = min($max_images - count($saved_paths), count($files['name']));
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $files['tmp_name'][$i];
            if (!is_uploaded_file($tmp)) continue;
            $info = @getimagesize($tmp); if (!$info) continue;
            $mime = $info['mime'];
            if (!in_array($mime, ['image/jpeg','image/pjpeg','image/png'], true)) continue;
            $basename = 'post_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
            $dest_path = $upload_dir . '/' . $basename;
            $public_path = 'uploads/' . $basename;
            $ok = resize_and_watermark($tmp, $dest_path, $max_width, $max_height, $font_path);
            if ($ok) $saved_paths[] = $public_path;
        }
    }
    if (count($saved_paths) > $max_images) $saved_paths = array_slice($saved_paths, 0, $max_images);
    if (empty($errors)) {
        $img_path = implode(';', $saved_paths);
        $stmtu = mysqli_prepare($dbc, "UPDATE {$table_posts} SET post_title = ?, content = ?, img_path = ?, city = ? WHERE post_id = ?");
        if ($stmtu) {
            mysqli_stmt_bind_param($stmtu, "ssssi", $title, $content, $img_path, $city, $id);
            mysqli_stmt_execute($stmtu);
            mysqli_stmt_close($stmtu);
            header('Location: post.php?id=' . $id);
            exit;
        } else {
            $errors[] = 'Klaida atnaujinant įrašą.';
        }
    }
}

$messages = [];
$unread_count = 0;
$usernames = [];
if (!empty($_SESSION['username'])) {
    $allusers = mysqli_prepare($dbc, "SELECT user_id, username FROM {$table_users}");
    if ($allusers) {
        mysqli_stmt_execute($allusers);
        $ur = mysqli_stmt_get_result($allusers);
        while ($r = mysqli_fetch_assoc($ur)) $usernames[(int)$r['user_id']] = $r['username'];
        mysqli_stmt_close($allusers);
    }
    $stmtm = mysqli_prepare($dbc, "SELECT message_id, owner_id, post_id, message_text, unread FROM {$table_messages} WHERE recipient = ? ORDER BY message_id DESC");
    if ($stmtm) {
        mysqli_stmt_bind_param($stmtm, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmtm);
        $rm = mysqli_stmt_get_result($stmtm);
        $messages = $rm ? mysqli_fetch_all($rm, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmtm);
        foreach ($messages as $r) if (!empty($r['unread'])) $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Redaguoti skelbimą - <?php echo e($post['post_title']); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="src/styles.css" rel="stylesheet">
  <script src="src/index_scripts.js" defer></script>
</head>
<body class="fixed-nav-padding">
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
                    <a class='nav-link' href='advertisement.php'>Naujas skelbimas</a>
                    <a class="nav-link" id="messages">
                      <img src="src/images/read.png" alt="Žinutės" width="16" height="16">
                    </a>
                    <a class='nav-link' style='color: red;' href='logout.php'><b>Atsijungti</b></a>
                </div>
            </div>
        </div>
    </nav>

<main class="container py-4">
  <div class="mx-auto" style="max-width:900px;">
    <div class="card p-3" style="background:#acacac; color:#000; border-radius:10px;">
      <h4 class="mb-3">Redaguoti skelbimą</h4>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label class="form-label">Pavadinimas</label>
          <input name="title" class="form-control" required value="<?php echo e($_POST['title'] ?? $post['post_title']); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Tekstas</label>
          <textarea name="content" class="form-control" rows="6" required><?php echo e($_POST['content'] ?? $post['content']); ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Miestas</label>
          <select name="city" class="form-select" required>
            <option value="">-- Pasirinkite --</option>
            <?php
            $cities = ['Kaunas','Vilnius','Klaipėda','Šiauliai','Alytus'];
            $sel = $_POST['city'] ?? $post['city'];
            foreach ($cities as $c) {
                $s = ($c === $sel) ? ' selected' : '';
                echo "<option value=\"" . e($c) . "\"{$s}>" . e($c) . "</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Esamos nuotraukos</label>
          <div class="d-flex flex-wrap gap-2">
            <?php
            $existing = array_filter(array_map('trim', explode(';', (string)$post['img_path'])));
            if (!empty($existing)) {
                foreach ($existing as $img) {
                    $img_e = e($img);
                    echo "<div style='width:140px;'>
                            <img src=\"{$img_e}\" style='width:100%;height:auto;border-radius:6px;display:block;margin-bottom:6px;'>
                            <label class='form-check-label'><input type='checkbox' name='delete_image[]' value=\"{$img_e}\"> Pašalinti</label>
                          </div>";
                }
            } else {
                echo "<div class='small text-muted'>Nuotraukų nėra.</div>";
            }
            ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Pridėti nuotraukas (iki <?php echo $max_images; ?> bendrai)</label>
          <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png" multiple>
          <div class="form-text">Najai pridėtų nuotraukų skaičius negali viršyti bendro nuotraukų limito skelbimui: <?php echo $max_images; ?> nuotr.</div>
        </div>

        <div>
          <button class="btn btn-primary" name="save" type="submit">Išsaugoti</button>
          <a class="btn btn-danger" href="post.php?id=<?php echo $id; ?>" style="margin-left: 10px;">Atšaukti</a>
        </div>
      </form>
    </div>
  </div>
</main>
</body>
</html>
