<?php
    declare(strict_types = 1);
    require_once "util.php";

    session_start();
    if (!isset($_SESSION['account_id']) || !in_array((int) $_SESSION['account_id'], ADMIN_ACCOUNT_IDS, true)) {
        Util::respond_http_and_die(403, "Forbidden");
    }

    $id_separator = "_";
    if (count($_POST) === 1) {
        [$id_clicked] = array_keys($_POST);
        $id_clicked_arr = explode($id_separator, $id_clicked);
        if ($id_clicked_arr) {
            [$id_clicked_type, $id_clicked_num] = $id_clicked_arr;
            $id_clicked_num = (int) $id_clicked_num;
            $conn = Util::get_conn();
            $refresh = fn() => header("Location: admin.php");
            switch ($id_clicked_type) {
                case "delete": {
                    $delete_info = [
                        "delete_user" => "passwords",
                        "delete_user_email" => "emails",
                        "delete_user_tasks" => "content",
                        "delete_user_api_token" => "api_tokens",
                        "delete_user_pass_reset_token" => "password_reset_tokens"
                    ];
                    foreach ($delete_info as $statement_name => $table_name) {
                        pg_prepare($conn, $statement_name, "DELETE FROM $table_name WHERE account_id = $1");
                        pg_execute($conn, $statement_name, array($id_clicked_num));
                    }
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
                    pg_prepare($conn, "new_token", "UPDATE api_tokens SET token = $1 WHERE account_id = $2");
                    pg_execute($conn, "new_token", array($new_token, $id_clicked_num));
                    setcookie("status_cookie", "API token successfully reset");
                    $refresh();
                    break;
                }
            }
        }
    }

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
            <div class="mobilefriendlytable">
                <table class="center">
                    <?php
                        $user_table_titles = [
                            "Account ID",
                            "Username",
                            "Email",
                            "API token",
                            "Actions"
                        ];
                        $action_buttons = [
                            "Delete user" => "delete",
                            "Reset password" => "resetpass",
                            "Reset API token" => "resetapikey"
                        ];

                        echo "<thead><tr>";
                        foreach ($user_table_titles as $title) {
                            echo "<th>$title</th>";
                        }
                        echo "</tr></thead>";

                        $rows = pg_fetch_all(Util::query("
                            SELECT passwords.account_id, username, email, token FROM passwords
                            LEFT JOIN emails ON passwords.account_id = emails.account_id
                            LEFT JOIN api_tokens ON passwords.account_id = api_tokens.account_id
                            ORDER BY account_id ASC
                        "));
                        echo "<tbody>";
                        foreach ($rows as $row) {
                            echo "<tr>";
                            foreach ($row as $content) {
                                echo "<th>";
                                if ($content !== null) {
                                    echo $content;
                                }
                                echo "</th>";
                            }
                            $db_account_id = (int) $row["account_id"];
                            if (!in_array($db_account_id, ADMIN_ACCOUNT_IDS, true)) {
                                echo "<th>";
                                echo "<form method='post' action='admin.php' enctype='multipart/form-data'>";
                                foreach ($action_buttons as $button_name => $button_id) {
                                    $button_id = ($button_id . $id_separator . $db_account_id);
                                    echo "<input type='submit' name='$button_id' class='button' value='$button_name'>";
                                }
                                echo "</form>";
                                echo "</th>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody>";
                    ?>

                </table>
            </div>

            <h2>Tasks</h2>
            <div class="mobilefriendlytable">
                <table class="center">
                    <?php
                        $task_table_titles = [
                            "Task ID",
                            "Task Name",
                            "Task Status",
                            "Account ID",
                            "Username"
                        ];

                        echo "<thead><tr>";
                        foreach ($task_table_titles as $title) {
                            echo "<th>$title</th>";
                        }
                        echo "</tr></thead>";

                        $rows = pg_fetch_all(Util::query("
                            SELECT task_id, task, task_status, account_id, username FROM content
                            ORDER BY task_id ASC
                        "));
                        echo "<tbody>";
                        foreach ($rows as $row) {
                            echo "<tr>";
                            foreach ($row as $category => $content) {
                                if ($category === "task_status") {
                                    $content = [
                                        "t" => "TODO",
                                        "p" => "In progress",
                                        "c" => "Completed"
                                    ][$content];
                                }
                                echo "<th>";
                                if ($content !== null) {
                                    echo $content;
                                }
                                echo "</th>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody>";
                    ?>

                </table>
            </div>
        </div>
    </body>
</html>