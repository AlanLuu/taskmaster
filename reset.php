<?php
    declare(strict_types = 1);
    require_once "util.php";

    const VALIDATE_TOKEN_ERROR = "Token is either invalid or has expired";

    //Case where token is empty
    if (!isset($_GET['token']) || !($token = $_GET['token'])) {
        Util::respond_http_and_die(401, "Unauthorized");
    }

    session_start();
    $pass_set = isset($_POST['newpass']) && isset($_POST['newpass2']);
    if (isset($_COOKIE['passerror'])) {
        //New password input doesn't pass verification
        setcookie("passerror", "");
    } else if ($pass_set) {
        //Validate new password
        $password_changed_str = "";
        $new_pass = $_POST['newpass'];
        $new_pass_2 = $_POST['newpass2'];

        $password_error = Util::validate_password($new_pass);
        if ($password_error) {
            $password_changed_str = $password_error;
        } else if ($new_pass !== $new_pass_2) {
            $password_changed_str = "New passwords do not match. Please try again.";
        } else { //New password is valid
            $conn = Util::get_conn();
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            pg_prepare($conn, "change_password", "UPDATE passwords SET pass = $1 WHERE account_id = $2");
            pg_execute($conn, "change_password", array($new_hash, $_SESSION['user_id']));
            pg_prepare($conn, "delete_token", "DELETE FROM password_reset_tokens WHERE account_id = $1");
            pg_execute($conn, "delete_token", array($_SESSION['user_id']));
            header("Location: logout.php?password_changed=1");
            die();
        }
        setcookie("newpass", $password_changed_str);
        setcookie("passerror", "1");
        header("Location: reset.php?token=$token");
    } else {
        //Verify reset token
        $conn = Util::get_conn();
        pg_prepare($conn, "get_id", "SELECT account_id, expiry FROM password_reset_tokens WHERE token = $1");
        $result = pg_execute($conn, "get_id", array($token));
        $db_contents = pg_fetch_row($result);
        if (!$db_contents) { //No token found in db, so token is invalid
            Util::respond_http_and_die(401, VALIDATE_TOKEN_ERROR);
        }
        [$id, $date_time] = $db_contents;
        $expire_date = new DateTime($date_time, PACIFIC);
        $current_date = new DateTime("now", PACIFIC);
        if ($current_date >= $expire_date) { //Token has expired
            pg_prepare($conn, "delete_token", "DELETE FROM password_reset_tokens WHERE account_id = $1");
            pg_execute($conn, "delete_token", array($id));
            Util::respond_http_and_die(401, VALIDATE_TOKEN_ERROR);
        }
        $_SESSION['user_id'] = $id;
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Forgot Password - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
    </head>
    <body>
        <form method="post" action="reset.php?token=<?= $token ?>" enctype="multipart/form-data">
            <div class="inputgroup">
                <label for="signupuser">New Password:</label> <br>
                <input class="input" id="newpass" type="password" name="newpass">
            </div>
            <div class="inputgroup">
                <label for="signupuser">Confirm New Password:</label> <br>
                <input class="input" id="newpass2" type="password" name="newpass2">
            </div>
            <input class="button" type="submit" name="action" value="Change Password">
        </form>
        <p class="infotext" id="infomsg">
<?php if (isset($_COOKIE['newpass'])): echo Util::sanitize($_COOKIE['newpass'], "htmlspecialchars"); setcookie("newpass", ""); endif ?>
        </p>
    </body>
</html>