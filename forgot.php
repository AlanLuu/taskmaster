<?php
    declare(strict_types = 1);
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception; //Used internally by PHPMailer; do not remove
    require_once "util.php";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>Forgot Password? - <?= WEBSITE_NAME ?></title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
<?php if (CAPTCHA_ENABLED): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif ?>
        <script type="module">
            import { CAPTCHA_ENABLED, showSubmitLoadingIcon, verifyCaptcha } from "./resources/util.js";
            const [form] = document.getElementsByTagName("form");
            form.addEventListener("submit", e => {
                e.preventDefault();
                const infoMsg = document.getElementById("infomsg");
                if (CAPTCHA_ENABLED ? verifyCaptcha() : true) {
                    infoMsg.textContent = "";
                    showSubmitLoadingIcon();
                    form.submit();
                } else if (CAPTCHA_ENABLED) {
                    infoMsg.textContent = "Captcha verification failed";
                }
            });
        </script>
    </head>
    <body>
        <h2>Forgot password?</h2>
        <form method="post" action="forgot.php" enctype="multipart/form-data">
            <div class="inputgroup">
                <label for="resetuser">Username:</label>
                <input class="input" id="resetuser" type="text" name="resetuser" required> <br> <br>
                <label for="resetemail">Email associated with account:</label>
                <input class="input" id="resetemail" type="text" name="resetemail" required>
            </div>
<?php if (CAPTCHA_ENABLED): ?>
            <div class="inputgroup">
                <div class="g-recaptcha" data-sitekey="<?= CAPTCHA_SITE_TOKEN ?>"></div>
            </div>
<?php endif ?>
            <input class="button" type="submit" name="action" value="Submit">
            <img src="resources/loading.gif" class="loading_gif hidden">
        </form>
        <p class="infotext" id="infomsg">
<?php
    if (isset($_COOKIE['forgotformsent'])) {
        echo "If the entered information was correct an email has been sent with further instructions.";
    } else if (isset($_COOKIE['captchafail'])) {
        echo "Captcha verification failed";
        setcookie("captchafail", "");
    } else if (isset($_COOKIE['blankform'])) {
        echo "Error: one or more fields is missing";
        setcookie("blankform", "");
    }
?>
        </p>
        <p>Remember your password? <a href="login.php">Log In</a></p>
<?php
    function send_mail(array $options): void {
        if (!MAIL_HOST || !MAIL_USERNAME || !MAIL_PASSWORD) {
            if (!MAIL_HOST) {
                Util::debug_log("MAIL_HOST is not set");
            }
            if (!MAIL_USERNAME) {
                Util::debug_log("MAIL_USERNAME is not set");
            }
            if (!MAIL_PASSWORD) {
                Util::debug_log("MAIL_PASSWORD is not set");
            }
            return;
        }
        $mail = new PHPMailer(true);
        $show_msg = function(string $msg): void {
            echo $msg;
            Util::debug_log($msg);
        };
        $add_contents = function(string $key, callable $add_content) use(&$options): void {
            if ($options[$key]) {
                foreach ($options[$key] as $option) {
                    $add_content($option);
                }
            } else {
                $add_content($options[$key]);
            }
        };
        $option_keys = [
            "cc" => fn($email) => $mail->addCC($email),
            "to" => fn($email) => $mail->addAddress($email)
        ];
        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->Port = MAIL_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->setFrom(MAIL_USERNAME, WEBSITE_NAME);
            foreach ($option_keys as $key => $add_content_callback) {
                $add_contents($key, $add_content_callback);
            }
            $mail->Subject = $options["subject"] ?? "";
            $mail->Body = $options["body"] ?? "";
            if ($mail->send()) {
                $show_msg("Message has been sent.");
            } else {
                $show_msg("send() returned false: Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            }
        } catch (Exception $_) {
            $show_msg("Caught Exception: Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
    
    if (isset($_COOKIE['forgotformsent'])) {
        $conn = Util::get_conn();
        $user = Util::sanitize($_COOKIE['resetuser'],
            fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));
        $email = $_COOKIE['resetemail'];
        setcookie("forgotformsent", "");
        setcookie("resetuser", "");
        setcookie("resetemail", "");
        
        pg_prepare($conn, "email",
            "SELECT email, passwords.account_id FROM passwords
            INNER JOIN emails ON passwords.account_id = emails.account_id
            WHERE passwords.username = $1"
        );
        $result = pg_execute($conn, "email", array($user));
        $db_contents = pg_fetch_row($result);
        if (([$db_email] = $db_contents ?: [null]) && $email === $db_email) {
            [, $db_id] = $db_contents;

            ($generate_token = function() use(&$generate_token, &$conn, $db_id): void {
                $date = new DateTime("+15 minutes", PACIFIC);
                $date_str = $date->format("Y-m-d H:i:s");
                $token = Util::rand_token(15);
                try {
                    @Util::query("INSERT INTO password_reset_tokens(account_id, token, expiry) VALUES($db_id, '$token', '$date_str')", $conn);
                } catch (DatabaseException $_) {
                    pg_prepare($conn, "delete_token", "DELETE FROM password_reset_tokens WHERE account_id = $1");
                    pg_execute($conn, "delete_token", array($db_id));
                    $generate_token();
                };
            })();

            // send_mail([
            //     "to" => $db_email,
            //     "subject" => "Password reset for " . WEBSITE_NAME,
            //     "body" => "Test email body."
            // ]);
        }
    }
    if (isset($_POST['resetuser']) && isset($_POST['resetemail'])) {
        if (!Util::verify_captcha()) {
            setcookie("captchafail", "1");
        } else {
            $post_user = $_POST['resetuser'];
            $post_email = $_POST['resetemail'];
            if ($post_user && $post_email) {
                setcookie("forgotformsent", "1");
                setcookie("resetuser", $post_user);
                setcookie("resetemail", $post_email);
            } else {
                setcookie("blankform", "1");
            }
        }
        header("Location: forgot.php");
    }
?>
    </body>
</html>