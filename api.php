<?php
    declare(strict_types = 1);
    require_once "util.php";

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=utf-8");

    $err_json = [
        "errors" => []
    ];
    $api_log = Util::create_logger("api.php.txt");
    $api_error = function(int $error_code, mixed $error_msg) use(&$api_log): void {
        $err_json["errors"][] = $error_msg;
        $api_log("API access failed: $error_msg");
        Util::respond_http_and_die($error_code, $err_json);
    };
    $is_get_request = $_SERVER['REQUEST_METHOD'] === GET;
    session_start();
    if (!$is_get_request || !($token = $_GET['token'] ?? $_SESSION['api_token'] ?? null)) {
        if ($is_get_request) {
            $api_error(401, "Missing or incorrect token");
        } else {
            $api_error(405, "Method not allowed");
        }
    }

    //Check if token is valid and get the account id associated with the token
    //If an account is already logged in, get the account id from $_SESSION
    if (isset($_GET['token']) && $token === $_GET['token']) {
        $conn = Util::get_conn();
        pg_prepare($conn, "verify_token", "SELECT account_id FROM api_tokens WHERE token = $1");
        $result = pg_fetch_row(pg_execute($conn, "verify_token", array($token)));
        if (!$result) {
            $api_error(401, "Missing or incorrect token");
        }
        [$account_id] = $result;
        $api_log("API access using token successful");
    } else if ($token === $_SESSION['api_token'] && isset($_SESSION['account_id'])) {
        $account_id = $_SESSION['account_id'];
    } else { //Should never happen
        $api_error(500, "Internal server error: session variables not set");
    }

    $conn = $conn ?? Util::get_conn();

    //Map internal representations of task statuses to a more human-readable format
    define("TASK_STATUSES", [
        "t" => "TODO",
        "p" => "In progress",
        "c" => "Completed"
    ]);

    //If an account is already logged in, get the username and email from $_SESSION
    //Otherwise, query the database for this info
    if (isset($_SESSION['username']) && isset($_SESSION['email']) && !isset($_GET['token'])) {
        $username = Util::sanitize($_SESSION['username'], "strtolower",
            "html_entity_decode", fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));
        $email = $_SESSION['email'];
    } else {
        pg_prepare($conn, "get_username", 
            "SELECT username, email FROM passwords
            LEFT JOIN emails ON passwords.account_id = emails.account_id
            WHERE passwords.account_id = $1"
        );
        [$username, $email] = pg_fetch_row(pg_execute($conn, "get_username", array($account_id)));
    }

    pg_prepare($conn, "get_tasks", "SELECT * FROM content WHERE username = $1 ORDER BY task_id ASC");
    $task_result = pg_execute($conn, "get_tasks", array($username));
    $json_response = [
        "account_id" => (int) $account_id,
        "username" => $username,
        "email" => $email,
        "tasks" => []
    ];
    while ($task = pg_fetch_assoc($task_result)) {
        $task["task_id"] = (int) $task["task_id"];
        $task["task_status"] = TASK_STATUSES[$task["task_status"]];
        $task["task_name"] = $task["task"];
        unset($task["task"]);
        unset($task["username"]);
        $json_response["tasks"][] = $task;
    }
    echo json_encode($json_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);