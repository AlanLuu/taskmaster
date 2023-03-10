<?php
    declare(strict_types = 1);
    require_once "util.php";

    session_start();
    if (!isset($_SESSION['account_id']) || !in_array(($admin_account_id = (int) $_SESSION['account_id']), ADMIN_ACCOUNT_IDS, true)) {
        Util::respond_http_and_die(403, "Unauthorized");
    }

    $action_buttons = [
        "Delete user" => "delete",
        "Reset password" => "resetpass",
        "Reset API key" => "resetapikey"
    ];
    $id_separator = "_";
    if (isset($_SESSION['button_ids']) && count($_POST) === 1) {
        $id_clicked = "";
        foreach ($_SESSION['button_ids'] as $button_id) {
            if (isset($_POST[$button_id])) {
                $id_clicked = $button_id;
                break;
            }
        }
        $id_clicked_arr = explode($id_separator, $id_clicked);
        if ($id_clicked_arr) {
            [$id_clicked_type, $id_clicked_num] = $id_clicked_arr;
            $id_clicked_num = (int) $id_clicked_num;
            $conn = Util::get_conn();
            $refresh = fn() => header("Location: admin.php");
            switch ($id_clicked_type) {
                case "delete": {
                    pg_prepare($conn, "delete_user", "DELETE FROM passwords WHERE account_id = $1");
                    pg_execute($conn, "delete_user", array($id_clicked_num));
                    setcookie("status_cookie", "Account successfully deleted");
                    $refresh();
                    break;
                }
                case "resetpass": {
                    $temp_pass = "changeme123";
                    $temp_pass_hash = password_hash($temp_pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    pg_prepare($conn, "reset_pass", "UPDATE passwords SET pass = $1 WHERE account_id = $2");
                    pg_execute($conn, "reset_pass", array($temp_pass_hash, $id_clicked_num));
                    setcookie("status_cookie", "Password successfully reset");
                    $refresh();
                    break;
                }
                case "resetapikey": {
                    $new_token = Util::rand_token(15);
                    pg_prepare($conn, "new_token", "UPDATE api_keys SET token = $1 WHERE account_id = $2");
                    pg_execute($conn, "new_token", array($new_token, $id_clicked_num));
                    setcookie("status_cookie", "API token successfully reset");
                    $refresh();
                    break;
                }
            }
        }
    }

    $_SESSION['button_ids'] = [];
    $session_user = $_SESSION['username'];
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Admin panel - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
    </head>
    <body>
        <div class="userbanner">
            <p>Logged in as <strong><?= $session_user ?> (admin)</strong></p>
            <div class="center">
                <a href="logout.php">Log out</a> | <a href="settings.php">Back</a>
            </div>
        </div> <br>
        <div class="center">
<?php if (isset($_COOKIE['status_cookie'])): ?>
            <p class="infotext" id="infomsg"><?php echo Util::sanitize($_COOKIE['status_cookie'], "htmlspecialchars"); setcookie("status_cookie", ""); ?></p>
<?php endif ?>
            <h2>Users</h2>
            <table class="center">
                <?php
                    $user_table_titles = ["Account ID", "Username", "Actions"];
                    echo "<thead><tr>";
                    foreach ($user_table_titles as $title) {
                        echo "<th>$title</th>";
                    }
                    echo "</tr></thead>";

                    $rows = pg_fetch_all(Util::query("
                        SELECT passwords.account_id, username FROM passwords
                        ORDER BY account_id ASC
                    "));
                    foreach ($rows as $i => $row) {
                        echo "<tr>";
                        foreach ($row as $e) {
                            echo $e !== null ? "<th>$e</th>" : "<th>null</th>";
                        }
                        $db_account_id = (int) $row["account_id"];
                        if (!in_array($db_account_id, ADMIN_ACCOUNT_IDS, true)) {
                            echo "<th>";
                            echo "<form method='post' action='admin.php' enctype='multipart/form-data'>";
                            foreach ($action_buttons as $button_name => $button_id) {
                                $button_id = ($button_id . $id_separator . $db_account_id);
                                echo "<input type='submit' name='$button_id' class='button' value='$button_name'>";
                                $_SESSION['button_ids'][] = $button_id;
                            }
                            echo "</form>";
                            echo "</th>";
                        }
                        echo "</tr>";
                    }
                ?>

            </table>
        </div>
    </body>
</html>