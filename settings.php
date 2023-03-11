<?php
    declare(strict_types = 1);
    require_once "util.php";

    //DO NOT REMOVE THESE LINES
    session_start();
    if (isset($_SESSION['username'])):
    
    //Prevent session hijacking
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']
    || $_SESSION['ua'] !== $_SERVER['HTTP_USER_AGENT']) {
        //Automatically log out the user if this happens
        header("Location: logout.php");
        die();
    }

    //Session regeneration to prevent session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id();
        $_SESSION['initiated'] = 1;
    }

    $account_id = $_SESSION['account_id'];
    $session_user = $_SESSION['username'];
    $user_email = $_SESSION['email'];
    $api_token = $_SESSION['api_token'];
    $session_user_db = Util::sanitize($session_user, "strtolower",
        "html_entity_decode", fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));

    $refresh = fn() => header("Location: settings.php");

    if (isset($_POST['delete_email'])) {
        $conn = Util::get_conn();
        pg_prepare($conn, "delete_email", "DELETE FROM emails WHERE email = $1");
        pg_execute($conn, "delete_email", array($user_email));
        $_SESSION['email'] = null;
        setcookie("delete_email", "Email successfully deleted.");
        $refresh();
    }

    $email_changed_str = ""; //Stores email set/change status message
    if (isset($_POST['change_email'])) {
        $new_email = trim($_POST['change_email']);
        $email_changed_str = Util::validate_email($new_email);
        if (!$email_changed_str) { //Email is valid
            $conn = Util::get_conn();
            pg_prepare($conn, "check_email", "SELECT account_id FROM emails WHERE email = $1");
            $check_email_result = pg_fetch_row(pg_execute($conn, "check_email", array($new_email)));
            if (!$check_email_result) { //Email is unique
                [$account_id_2] = pg_fetch_row(pg_execute($conn, "check_email", array($account_id)));
                if ($account_id_2 && $account_id === $account_id_2) { //Update email
                    pg_prepare($conn, "update_email", "UPDATE emails SET email = $1 WHERE account_id = $2");
                    pg_execute($conn, "update_email", array($new_email, $account_id));
                } else { //Set email
                    pg_prepare($conn, "set_email", "INSERT INTO emails VALUES($1, $2)");
                    pg_execute($conn, "set_email", array($account_id, $new_email));
                }
                $_SESSION['email'] = $new_email;
                $email_changed_str = "Email successfully changed.";
            } else {
                $email_changed_str = ((int) $account_id) === ((int) $check_email_result[0])
                    ? "New email is the same as current email."
                    : "This email address already has an account associated with it.";
            }
        }
        setcookie("change_email", $email_changed_str);
        $refresh();
    }

    $username_changed_str = ""; //Stores username change status message
    if (isset($_POST['newuser'])) {
        $db_str_format = fn($str) => 
            Util::sanitize($str, "strtolower",
            fn($str2) => preg_replace("/[^A-Za-z0-9]/", "", $str2));
        $new_user = $db_str_format($_POST['newuser']);

        //Check if user entered a different username from their original one
        if ($new_user === $session_user_db) {
            $username_changed_str = "New username cannot be the same as old username.";
        } else {
            //Check if username is already taken
            $conn = Util::get_conn();
            pg_prepare($conn, "username_check", "SELECT account_id FROM passwords WHERE username = $1");
            $username_check = pg_execute($conn, "username_check", array($new_user));
            if (!pg_fetch_row($username_check)) {
                //Check if username is valid
                $username_error = Util::validate_username($new_user);
                if ($username_error) {
                    $username_changed_str = $username_error;
                } else {
                    //Update username in database
                    pg_prepare($conn, "username_change", "UPDATE passwords SET username = $1 WHERE username = $2");
                    pg_execute($conn, "username_change", array($new_user, $session_user_db));
                    pg_prepare($conn, "update_content", "UPDATE content SET username = $1 WHERE username = $2");
                    pg_execute($conn, "update_content", array($new_user, $session_user_db));

                    //Regenerate session id
                    $display_name = Util::sanitize($_POST['newuser'], "htmlspecialchars");
                    $_SESSION['username'] = $display_name;
                    session_regenerate_id();
                    $_SESSION['initiated'] = 1;
                    $session_user = $display_name;
                    $username_changed_str = "Successfully changed username to $display_name";
                }
            } else {
                $username_changed_str = "The username you have entered already exists.";
            }
        }
        setcookie("newuser", $username_changed_str);
        $refresh();
    }

    $password_changed_str = ""; //Stores password change status message
    if (isset($_POST['currentpass']) && isset($_POST['newpass']) && isset($_POST['newpass2'])) {
        $conn = Util::get_conn();
        $current_pass = $_POST['currentpass'];
        $new_pass = $_POST['newpass'];
        $new_pass_2 = $_POST['newpass2'];

        //Check if current password is correct
        pg_prepare($conn, "current_pass", "SELECT pass FROM passwords WHERE username = $1");
        [$current_hash] = pg_fetch_row(pg_execute($conn, "current_pass", array($session_user_db)));
        if (password_verify($current_pass, $current_hash)) {
            $password_error = Util::validate_password($new_pass);
            switch (true) {
                case $password_error: //Invalid password
                    $password_changed_str = $password_error;
                    break;
                case $new_pass !== $new_pass_2: //Passwords don't match
                    $password_changed_str = "New passwords do not match.";
                    break;
                case $current_pass === $new_pass: //New password is the same as old
                    $password_changed_str = "Your new password cannot be the same as your old password.";
                    break;
                default:
                    //Hash new password, update hash in database, and log the user out
                    $new_hash = Util::password_hash($new_pass);
                    pg_prepare($conn, "change_password", "UPDATE passwords SET pass = $1 WHERE username = $2");
                    pg_execute($conn, "change_password", array($new_hash, $session_user_db));
                    header("Location: logout.php?password_changed=1");
                    die();
            }
        } else {
            $password_changed_str = "Current password is incorrect.";
        }
        setcookie("newpass", $password_changed_str);
        $refresh();
    }

    if (isset($_POST['new_token'])) {
        $new_token = Util::rand_token(15);
        $conn = Util::get_conn();
        pg_prepare($conn, "new_token", "UPDATE api_tokens SET token = $1 WHERE account_id = $2");
        pg_execute($conn, "new_token", array($new_token, $account_id));
        $_SESSION['api_token'] = $new_token;
        $refresh();
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Settings - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
    </head>
    <body>
        <div class="userbanner">
            <div class="center">
                <p>Logged in as <strong><?= $session_user ?></strong></p>
                <a href="logout.php">Log out</a>
                | <a href="./">Back</a>
<?php if (in_array((int) $_SESSION['account_id'], ADMIN_ACCOUNT_IDS, true)): ?>
                | <a href="admin.php">Admin panel</a>
<?php endif ?>
            </div>
        </div> <br>

        <div class="center">
<?php if (!$user_email): ?>
            <br>
<?php else: ?>
            <p style="font-size: 20px;">Email: <strong><?= $user_email ?></strong></p>
            <form method="post" action="settings.php" enctype="multipart/form-data">
                <input class="button" type="submit" name="delete_email" value="Delete email">
            </form> <br>
<?php endif ?>

            <form method="post" action="settings.php" enctype="multipart/form-data">
                <input class="input" id="new_email" placeholder="<?= $user_email ? "New email" : "Set email" ?>" type="text" name="change_email" required>
                <input class="button" type="submit" name="action" value="<?= $user_email ? "Change email" : "Set email" ?>">
            </form>
<?php if (isset($_COOKIE['delete_email'])): ?>
            <p class="infotext" id="infomsg"><?php echo Util::sanitize($_COOKIE['delete_email'], "htmlspecialchars"); setcookie("delete_email", "") ?></p>
<?php elseif (isset($_COOKIE['change_email'])): ?>
            <p class="infotext" id="infomsg"><?php echo Util::sanitize($_COOKIE['change_email'], "htmlspecialchars"); setcookie("change_email", "") ?></p>
<?php endif ?>

            <form method="post" action="settings.php" enctype="multipart/form-data">
                <h2>Change username</h2>
                <input class="input" id="newuser" placeholder="New Username" type="text" name="newuser" required>
                <input class="button" type="submit" name="action" value="Change Username">
            </form>
<?php if (isset($_COOKIE['newuser'])): ?>
            <p class="infotext" id="infomsg"><?php echo Util::sanitize($_COOKIE['newuser'], "htmlspecialchars"); setcookie("newuser", "") ?></p>
<?php endif ?>

            <form method="post" action="settings.php" enctype="multipart/form-data">
                <h2>Change password</h2>
                <div class="inputgroup">
                    <input class="input" id="currentpass" placeholder="Current Password" type="password" name="currentpass" required>
                </div>
                <div class="inputgroup">
                    <input class="input" id="newpass" placeholder="New Password" type="password" name="newpass" required>
                </div>
                <div class="inputgroup">
                    <input class="input" id="newpass2" placeholder="Confirm New Password" type="password" name="newpass2" required>
                </div>
                <input class="button" type="submit" name="action" value="Change Password">
            </form> 
<?php if (isset($_COOKIE['newpass'])): ?>
            <p class="infotext" id="infomsg2"><?php echo Util::sanitize($_COOKIE['newpass'], "htmlspecialchars"); setcookie("newpass", "") ?></p>
<?php endif ?>

            <form method="post" action="settings.php" enctype="multipart/form-data">
                <p>API token: <strong><?= $api_token ?></strong></p>
                <p>
                    Access the API using this link: <br>
                    <a href="api.php?token=<?= $api_token ?>" target="_blank"><?= Util::full_url("api.php?token=$api_token") ?></a>
                </p>
                <input class="button" type="submit" name="new_token" value="Reset token">
            </form>
        </div> <br>
<?php
else:
    //Redirects the user to the login page if not logged in yet
    header("Location: logout.php");
endif;
?>
    </body>
</html>