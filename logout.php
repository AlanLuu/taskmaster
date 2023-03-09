<?php
    declare(strict_types = 1);
    
    //Destroy user session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_start();
        $_SESSION = []; //Clear all session values
        session_destroy();
    }

    //Unset all session cookies
    //https://stackoverflow.com/a/2310591
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(";", $_SERVER['HTTP_COOKIE']);
        foreach ($cookies as $cookie) {
            [$name] = explode("=", $cookie);
            $name = trim($name);
            setcookie($name, "", time() - 1000);
            setcookie($name, "", time() - 1000, "/");
        }
    }

    //User changed their password
    if (isset($_GET['password_changed']) && $_GET['password_changed'] === "1") {
        setcookie("password_changed", "1");
    }

    header("Location: login.php");