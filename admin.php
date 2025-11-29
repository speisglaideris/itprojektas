<?php
session_start();
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: index.php");
    exit;
}

$server = "localhost";
$db = "IT_projektas";
$user = "admin";
$password = "adminas";
$table1 = "users";

$dbc=mysqli_connect($server,$user,$password, $db);
if(!$dbc){ die ("Negaliu prisijungti prie MySQL:"	.mysqli_error($dbc)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_can_post = isset($_POST["button_val"]) ? (int)$_POST["button_val"] : null;
    $user_id = isset($_POST["usr_id"]) ? (int)$_POST["usr_id"] : 0;

    if ($user_id > 0 && ($user_can_post === 0 || $user_can_post === 1)) {
        $stmt = mysqli_prepare($dbc, "UPDATE $table1 SET can_post = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $user_can_post, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: admin.php");
    exit();
}

$user_query = "SELECT user_id, username, email, user_type, can_post FROM $table1 ORDER BY user_id ASC";
$user_result = mysqli_query($dbc, $user_query);
?>
<!DOCTYPE html>
<html lang="lt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
        <link href="src/styles.css" rel="stylesheet">
        <script src="src/index_scripts.js" defer></script>
        <title>Skelbimų portalas</title>
    </head>
    <body data-bs-theme="light">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

        <nav class="navbar navbar-expand-lg sticky-top">
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
                        <a class="nav-link" href="index.php">Pagrindinis puslapis</a>
                        <a class="nav-link active" href="admin.php">Administraciniai nustatymai</a>
                        <a class='nav-link' style='color: red;' aria-current='page' href='logout.php'><b>Atsijungti</b></a>
                    </div>
                </div>
            </div>
        </nav>
        <div class="container py-4">
            <div class="mx-auto" style="max-width:1100px;">
                <div class="admin-card mb-3 background-color-gray">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="mb-0">Vartotojų sąrašas</h4>
                        <div>
                            <input id="userSearch" class="form-control search-input" type="search" placeholder="Ieškoti pagal slapyvardį...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="users-table" class="table table-hover table-borderless" style="color: #000; background:#fff; border-radius:8px; overflow:hidden;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:8%;">ID</th>
                                    <th style="width:20%;">Slapyvardis</th>
                                    <th style="width:28%;">E-paštas</th>
                                    <th style="width:18%;">Vartotojo tipas</th>
                                    <th style="width:10%;">Gali kelti</th>
                                    <th style="width:16%;">Galimi veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    if ($user_result) {
                                        while ($user_r = mysqli_fetch_assoc($user_result)) {
                                            $user_id = (int)$user_r["user_id"];
                                            $user_name = htmlspecialchars($user_r["username"], ENT_QUOTES, 'UTF-8');
                                            $user_email = htmlspecialchars($user_r["email"], ENT_QUOTES, 'UTF-8');
                                            $user_ability = (int)$user_r["can_post"];
                                            $user_type = htmlspecialchars($user_r['user_type'], ENT_QUOTES, 'UTF-8');

                                            echo "<tr data-username=\"".strtolower($user_name)."\">";
                                            echo "<td>$user_id</td>";
                                            echo "<td>$user_name</td>";
                                            echo "<td>$user_email</td>";
                                            echo "<td>$user_type</td>";
                                            echo "<td>" . ($user_ability ? 'Taip' : 'Ne') . "</td>";
                                            if ($user_type === "simple" && $user_ability === 0) {
                                                echo "<td>
                                                        <form method='post' class='d-inline'>
                                                            <input type='hidden' name='usr_id' value='$user_id'>
                                                            <input type='hidden' name='button_val' value='1'>
                                                            <input type='submit' class='btn btn-success btn-sm' value='Leisti kelti skelbimus'>
                                                        </form>
                                                      </td></tr>";
                                            } elseif ($user_type === "simple" && $user_ability === 1) {
                                                echo "<td>
                                                        <form method='post' class='d-inline'>
                                                            <input type='hidden' name='usr_id' value='$user_id'>
                                                            <input type='hidden' name='button_val' value='0'>
                                                            <input type='submit' class='btn btn-danger btn-sm' value='Drausti kelti skelbimus'>
                                                        </form>
                                                      </td></tr>";
                                            } else {
                                                echo "<td><span class='small-muted'>Veiksmų nėra</span></td></tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>Klaida užkraunant vartotojus.</td></tr>";
                                    }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function(){
                const input = document.getElementById('userSearch');
                const tbody = document.querySelector('#users-table tbody');
                if (!input || !tbody) return;
                input.addEventListener('input', function(){
                    const q = this.value.trim().toLowerCase();
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(r => {
                        const uname = r.dataset.username || r.cells[1]?.textContent?.toLowerCase() || '';
                        r.style.display = q === '' ? '' : (uname.indexOf(q) !== -1 ? '' : 'none');
                    });
                });
            })();
        </script>
    </body>
</html>