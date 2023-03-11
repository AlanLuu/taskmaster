<?php
    declare(strict_types = 1);
    require_once "util.php";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Log In - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
    </head>
    <body>
        <div style="float: right;">
            <form method="post" action="login.php" enctype="multipart/form-data">
                <input class="input" type="text" placeholder="Username" name="loginuser" required>
                <input class="input" type="password" placeholder="Password" name="loginpass" required>
                <input class="button" type="submit" value="Log In">
            </form>
            <div class="center">
                <p class="infotext">
<?php
    if (isset($_COOKIE['password_changed']) && $_COOKIE['password_changed'] === "1") {
        echo "Password change was successful. Please log in again.";
        setcookie("password_changed", "");
    } else if (isset($_COOKIE['loginfail'])) {
        echo "Incorrect username or password. Please try again."; 
        setcookie("loginfail", "");
    }
?>
                </p>
                <p><a href="register.php"> Create an account</a> | <a href="forgot.php">Forgot Password?</a></p>
            </div>
        </div>
        <div class="half-width" id="title">
            <h1><?= WEBSITE_NAME ?></h1>
            <p>Keep your tasks organized!</p>
        </div>
        <hr/>
        <div class="half-width left">
            <h1>About <?= WEBSITE_NAME ?></h1>
            <p>
                <?= WEBSITE_NAME ?> is a simple task organizer that allows you to enter
                your tasks and modify them as you wish.
            </p>
        </div>
<?php
    //This code handles the users logging in to the website
    if (isset($_POST['loginuser']) && isset($_POST['loginpass'])) {
        $conn = Util::get_conn();
        $login_attempt_log = Util::create_logger("loginattempts.txt");
        $tmp_login_user = $_POST['loginuser'];
        $tmp_login_pass = $_POST['loginpass'];

        //Remove whitespace and special characters to match against database
        $tmp_login_user_db = Util::sanitize($tmp_login_user,
            fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));
        
        $handle_failed_login = function() use(&$login_attempt_log, &$tmp_login_user_db): void {
            $login_attempt_log("Failed login attempt using username \"$tmp_login_user_db\"");
            setcookie("loginfail", "1");
            header("Location: login.php");
            die();
        };
        
        pg_prepare($conn, "pass", 
            "SELECT passwords.account_id, pass, email, token FROM passwords
            LEFT JOIN emails ON passwords.account_id = emails.account_id
            LEFT JOIN api_tokens ON passwords.account_id = api_tokens.account_id
            WHERE username = $1"
        );
        $result = pg_execute($conn, "pass", array($tmp_login_user_db));
        $result = pg_fetch_row($result);
        if ($result) {
            [$account_id, $hash, $email, $api_token] = $result;
            if (password_verify($tmp_login_pass, $hash)) {
                //Password matches the hash, so authentication is successful
                $login_attempt_log("Successful login for user \"$tmp_login_user_db\"");
                session_start();
                $_SESSION['account_id'] = $account_id;
                $_SESSION['username'] = Util::sanitize($tmp_login_user, "htmlspecialchars");
                $_SESSION['email'] = $email;
                $_SESSION['api_token'] = $api_token;
    
                //Prevent session hijacking and fixation
                $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];

                header("Location: ./");
            } else {
                //Account exists but password is incorrect
                $handle_failed_login();
            }
        } else {
            //Account does not exist
            password_verify($tmp_login_pass, "");
            $handle_failed_login();
        }
    }
?>
    </body>
</html>