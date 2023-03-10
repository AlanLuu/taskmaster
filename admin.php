<?php
    declare(strict_types = 1);
    require_once "util.php";

    session_start();
    if (!isset($_SESSION['account_id']) || !in_array(($admin_account_id = (int) $_SESSION['account_id']), ADMIN_ACCOUNT_IDS, true)) {
        Util::respond_http_and_die(403, "Forbidden");
    }

    $action_buttons = [
        "Delete user" => "delete",
        "Reset password" => "resetpass",
        "Reset API token" => "resetapikey"
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
                    $delete = function(string $statement_name, string $table_name) use(&$conn, $id_clicked_num): void {
                        pg_prepare($conn, $statement_name, "DELETE FROM $table_name WHERE account_id = $1");
                        pg_execute($conn, $statement_name, array($id_clicked_num));
                    };
                    $delete("delete_user", "passwords");
                    $delete("delete_user_email", "emails");
                    $delete("delete_user_tasks", "content");
                    $delete("delete_user_api_token", "api_keys");
                    $delete("delete_user_pass_reset_token", "password_reset_tokens");
                    setcookie("status_cookie", "Account successfully deleted");
                    $refresh();
                    break;
                }
                case "resetpass": {
                    $characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
                    $temp_pass = "";
                    for ($i = 0; $i < 15; $i++) {
                        try {
                            $rand_char_pos = random_int(0, strlen($characters) - 1);
                            $temp_pass .= $characters[$rand_char_pos];
                        } catch (Exception $_) {
                            $temp_pass = "changeme123";
                            break;
                        }
                    }
                    $temp_pass_hash = Util::password_hash($temp_pass);
                    pg_prepare($conn, "reset_pass", "UPDATE passwords SET pass = $1 WHERE account_id = $2");
                    pg_execute($conn, "reset_pass", array($temp_pass_hash, $id_clicked_num));
                    setcookie("status_cookie", "Password successfully reset. Temp password: $temp_pass");
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
                    $user_table_titles = [
                        "Account ID",
                        "Username",
                        "Email",
                        "API token",
                        "Actions"
                    ];
                    echo "<thead><tr>";
                    foreach ($user_table_titles as $title) {
                        echo "<th>$title</th>";
                    }
                    echo "</tr></thead>";

                    $rows = pg_fetch_all(Util::query("
                        SELECT passwords.account_id, username, email, token FROM passwords
                        LEFT JOIN emails ON passwords.account_id = emails.account_id
                        LEFT JOIN api_keys ON passwords.account_id = api_keys.account_id
                        ORDER BY account_id ASC
                    "));
                    foreach ($rows as $i => $row) {
                        echo "<tr>";
                        foreach ($row as $e) {
                            echo $e !== null ? "<th>$e</th>" : "<th></th>";
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