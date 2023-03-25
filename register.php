<?php
    declare(strict_types = 1);
    require_once "util.php";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Sign Up - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
<?php if (CAPTCHA_ENABLED): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif ?>
        <script type="module" src="resources/register.js"></script>
    </head>
    <body>
        <h2>Sign up for <?= WEBSITE_NAME ?></h2>
        <p class="infotext" id="infomsg"></p>

        <form method="post" action="register.php" enctype="multipart/form-data">
            <div class="inputgroup">
                <label for="signupuser">Username:</label> <br>
                <input class="input" id="signupuser" type="text" name="signupuser">
                <span class="infotext" id="infotextuser"></span>
            </div>
            <div class="inputgroup">
                <label for="signupuser">Password:</label> <br>
                <input class="input" id="signuppass" type="password" name="signuppass">
                <span class="infotext" id="infotextpass"></span>
            </div>
            <div class="inputgroup">
                <label for="signupuser">Confirm Password:</label> <br>
                <input class="input" id="signuppass2" type="password" name="signuppass2">
                <span class="infotext" id="infotextpass2"></span>
            </div>
            <div class="inputgroup">
                <label for="signupuser">Email (Optional, used for resetting password)</label> <br>
                <input class="input" id="signupemail" type="text" name="signupemail">
                <span class="infotext" id="infotextemail"></span>
            </div>
<?php if (CAPTCHA_ENABLED): ?>
            <div class="inputgroup">
                <div class="g-recaptcha" data-sitekey="<?= CAPTCHA_SITE_TOKEN ?>"></div>
                <span class="infotext" id="infotextcaptcha"></span>
            </div>
<?php endif ?>
            <input class="button" type="submit" name="action" value="Sign Up">
        </form>
        <p><strong>By signing up, you agree to the following <a href="privacy.php">privacy policy</a>.</strong></p>
        <p>Already have an account? <a href="login.php">Log In</a></p>
<?php
    //This code handles the users signing up on the website
    if (isset($_POST['signupuser']) && isset($_POST['signuppass']) && isset($_POST['signuppass2'])) {
        if (!Util::verify_captcha()) {
            echo <<< _END
                <script>document.getElementById("infotextcaptcha").textContent = "Captcha verification failed";</script>
            _END;
            die();
        }
        
        $conn = Util::get_conn();

        //Remove leading/ending whitespace and special characters from username
        $tmp_signup_user = Util::sanitize($_POST['signupuser'], "strtolower",
            fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));
        $tmp_signup_pass = $_POST['signuppass'];
        $tmp_signup_pass_2 = $_POST['signuppass2'];
        $tmp_signup_email = trim($_POST['signupemail']) ?: null;

        //Server side validation
        $username_error = Util::validate_username($tmp_signup_user);
        $password_error = Util::validate_password($tmp_signup_pass);
        $confirm_pass = $tmp_signup_pass === $tmp_signup_pass_2;
        $email_error = Util::validate_email($tmp_signup_email);
        if ($username_error || $password_error || !$confirm_pass || $email_error) {
            echo <<< _END
                <script>document.getElementById("infotextuser").textContent = "$username_error";</script>
            _END;
            echo <<< _END
                <script>document.getElementById("infotextpass").textContent = "$password_error";</script>
            _END;
            if (!$confirm_pass) {
                echo <<< _END
                    <script>document.getElementById("infotextpass2").textContent = "Passwords do not match";</script>
                _END;
            } else {
                echo <<< _END
                    <script>document.getElementById("infotextpass2").textContent = "";</script>
                _END;
            }
            echo <<< _END
                <script>document.getElementById("infotextemail").textContent = "$email_error";</script>
            _END;
            die();
        }

        //Hash password
        $hashed_pass = Util::password_hash($tmp_signup_pass);

        //Check to see if username is already taken
        pg_prepare($conn, "check_account", "SELECT account_id FROM passwords WHERE username = $1");
        $check_account_result = pg_execute($conn, "check_account", array($tmp_signup_user));
        if (!pg_fetch_row($check_account_result)) { //Username is not taken
            $user_entered_email = $tmp_signup_email !== NULL;

            //If user entered an email, check to see is email is already
            //associated with an account
            if ($user_entered_email) {
                pg_prepare($conn, "check_email", "SELECT account_id FROM emails WHERE email = $1");
                $check_email_result = pg_execute($conn, "check_email", array($tmp_signup_email));
                if (pg_fetch_row($check_email_result)) {
                    //Email is already associated with an account
                    Util::notify_user_and_die("This email address already has an account associated with it. Please specify a different email address.");
                }
            }

            //Insert username and hashed password
            pg_prepare($conn, "passwords", "INSERT INTO passwords(username, pass) VALUES($1, $2)");
            pg_execute($conn, "passwords", array($tmp_signup_user, $hashed_pass));

            //Insert optional email
            //Down here, email is not already associated with an account
            pg_prepare($conn, "account_id", "SELECT account_id FROM passwords WHERE username = $1");
            [$id] = pg_fetch_row(pg_execute($conn, "account_id", array($tmp_signup_user)));
            if ($user_entered_email) {
                pg_prepare($conn, "emails", "INSERT INTO emails(account_id, email) VALUES($1, $2)");
                pg_execute($conn, "emails", array($id, $tmp_signup_email));
            }

            $api_token = Util::rand_token(15);
            pg_prepare($conn, "api_token", "INSERT INTO api_tokens(account_id, token) VALUES($1, $2)");
            pg_execute($conn, "api_token", array($id, $api_token));
        } else {
            Util::notify_user_and_die("Username is already taken. Please choose another username.");
        }

        //Automatically log the newly created account in
        Util::log("registrations.txt", "Account created with username \"$tmp_signup_user\"");
        session_start();
        $_SESSION['account_id'] = $id;
        $_SESSION['username'] = Util::sanitize($_POST['signupuser'], "htmlspecialchars");
        $_SESSION['email'] = Util::sanitize($tmp_signup_email, "htmlspecialchars");
        $_SESSION['api_token'] = $api_token;
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];
        header("Location: ./");
    }
?>
    </body>
</html>