<?php
    declare(strict_types = 1);
    require_once "util.php";

    //Variables for the status of tasks
    $todo = "t";
    $in_progress = "p";
    $completed = "c";
    $inner_todo = "inner_t";
    $inner_in_progress = "inner_p";
    $inner_completed = "inner_c";

    session_start();
    if (isset($_SESSION['username'])) {
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

        //Prevent resubmission of POST request on page refresh
        $refresh = fn() => header("Location: ./");

        $session_user = $_SESSION['username']; //This variable has already been sanitized
        $session_user_db = Util::sanitize($session_user, "strtolower",
            "html_entity_decode", fn($str) => preg_replace("/[^A-Za-z0-9]/", "", $str));
        $website_name = WEBSITE_NAME;
        echo <<< _END
        <!DOCTYPE html>
        <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
                <meta name="theme-color" content="#66c4a8">
                <title>$website_name</title>
                <link href="resources/index.css" rel="stylesheet" type="text/css">
                <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
                <style>
                    #$todo p, #$in_progress p, #$completed p {
                        font-size: 20px;
                    }
                    #$todo, #$in_progress {
                        float: left;
                        width: 33%;
                        word-wrap: break-word;
                    }
                    #$completed {
                        float: right;
                        width: 33%;
                        word-wrap: break-word;
                    }
                    #$todo button, #$in_progress button, #$completed button {
                        margin-top: 10px;
                    }
                    @supports (-webkit-touch-callout: none) {
                        @media (min-width: 600px) and (max-width: 961px) {
                            #$todo {
                                margin-left: 40px;
                            }
                        }
                    }
                    @media screen and (min-width: 961px) {
                        #$in_progress > div {
                            text-align: center;
                        }
                    }
                    @media (min-width: 600px) and (max-width: 961px) {
                        #$in_progress {
                            margin-left: 10px;
                        }
                        #$completed {
                            width: 25%;
                            float: left;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="userbanner">
                    <p>Logged in as <strong>$session_user</strong></p>
                    <div class="center">
                        <a href="logout.php">Log out</a> | <a href="settings.php">Settings</a>
                    </div>
                </div> <br> <br>

                <noscript>
                    <h2 class="center">
                        Warning: JavaScript is disabled. You can still record your tasks using the forms below but tasks can't be viewed without enabling JavaScript.
                    </h2> <br>
                </noscript>

                <div class="center table">
                    <form method="post" action="index.php" enctype="multipart/form-data">
                        <input class="input" type="text" placeholder="Enter task:" name="entertask" required>
                        <select name="task_status">
                            <option value="todo">To-do</option>
                            <option value="in_progress">In progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <input class="button" type="submit" name="action" value="Add task">
                    </form> <br>

                    <p class="center">Or upload a text file with multiple tasks on each line</p> <br>

                    <form method="post" action="index.php" enctype="multipart/form-data">
                        <input type="file" name="uploadfile">
                        <select name="task_status_2">
                            <option value="todo">To-do</option>
                            <option value="in_progress">In progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <input class="button" type="submit" name="action" value="Add tasks">
                    </form>
                    <p class="infotext" id="infomsg"></p>
                </div> <hr>

                <div class="taskscontainer" id="$todo">
                    <h2><u>To-do</u></h2>
                    <div class="taskscrollbox" id="$inner_todo"></div>
                </div>
                <div class="taskscontainer" id="$in_progress">
                    <h2 style="margin-right: 10px;"><u>In progress</u></h2>
                    <div class="taskscrollbox" id="$inner_in_progress"></div>
                </div>
                <div class="taskscontainer" id="$completed">
                    <h2><u>Completed</u></h2>
                    <div class="taskscrollbox" id="$inner_completed"></div>
                </div>
        _END;
    } else {
        //Redirects the user to the login page if not logged in yet
        switch ($_SERVER['REQUEST_METHOD']) {
            case GET:
                Util::log("login.php.txt", "Accessed login page", true);
                header("Location: logout.php");
                break;
            case POST:
                echo "Unauthorized";
                http_response_code(401);
                break;
        }
        die();
    }
    
    $conn = Util::get_conn();

    function insert_content(string $username, string $task, string $task_status): void {
        global $conn;
        pg_prepare($conn, "insertContent", "INSERT INTO content(task, task_status, username) VALUES($1, $2, $3)");
        pg_execute($conn, "insertContent", array($task, $task_status, $username));
    }

    function display_content(): void {
        global $conn, $session_user_db, $todo, $in_progress, $completed,
            $inner_todo, $inner_in_progress, $inner_completed;

        pg_prepare($conn, "content", "SELECT * FROM content WHERE username = $1 ORDER BY task_id ASC");
        $result = pg_execute($conn, "content", array($session_user_db));
        $row = pg_fetch_row($result);
        if ($row) {
            echo "\n";
            echo <<< _END
                    <script type="module">
                        import { displayTask as d } from "./resources/tasks.js";
                        
            _END;
            while ($row) {
                [$task_id, $user_task, $user_task_status] = $row;
                switch ($user_task_status) {
                    case $todo:
                        echo "d('$user_task','$inner_todo',$task_id,0);";
                        break;
                    case $in_progress:
                        echo "d('$user_task','$inner_in_progress',$task_id,0);";
                        break;
                    case $completed:
                        echo "d('$user_task','$inner_completed',$task_id,0);";
                        break;
                }
                $row = pg_fetch_row($result);
            }
            echo <<< _END

                    </script>
            _END;
        } else {
            echo <<< _END

                    <script type="module" src="resources/tasks.js"></script>
            _END;
        }
    }

    //Tasks from input text box
    if (isset($_POST['entertask'])) {
        $task = trim($_POST['entertask']); //Remove whitespace from start and end of input
        if ($task) { //Only insert task when it is not blank
            switch ($_POST['task_status'] ?? null) {
                case "in_progress":
                    insert_content($session_user_db, $task, $in_progress);
                    break;
                case "completed":
                    insert_content($session_user_db, $task, $completed);
                    break;
                case "todo":
                default:
                    insert_content($session_user_db, $task, $todo);
                    break;
            }
        }
        $refresh();
    }

    //Tasks from file upload
    if (isset($_FILES['uploadfile'])) {
        $file_name = $_FILES['uploadfile']['name'];
        $tmp_file_name = $_FILES['uploadfile']['tmp_name'];
        $file_path = "./" . $file_name;
        $file_extension = substr($file_name, strrpos($file_name, ".") + 1);

        switch ($file_extension) {
            case "csv":
            case "log":
            case "txt": {
                move_uploaded_file($tmp_file_name, $file_path);
                $fp = fopen($file_path, "r");
                $tasks = []; //Keep track of file input

                switch ($_POST['task_status'] ?? null) {
                    case "in_progress":
                        $div_to_insert_at = $in_progress;
                        break;
                    case "completed":
                        $div_to_insert_at = $completed;
                        break;
                    case "todo":
                    default:
                        $div_to_insert_at = $todo;
                        break;
                }

                //Function to strip newline characters and add to tasks array
                $process = function(string $line) use(&$tasks, $div_to_insert_at, $session_user_db): void {
                    $line = Util::sanitize(rtrim($line), "htmlspecialchars", "addslashes");
                    if ($line) {
                        //Add array inside array
                        $tasks[] = [$line, $div_to_insert_at, $session_user_db];
                    }
                };

                //Process each element
                if ($file_extension === "csv") {
                    while ($fields = fgetcsv($fp)) {
                        foreach ($fields as $line) {
                            $process($line);
                        }
                    }
                } else {
                    while ($line = fgets($fp)) {
                        $process($line);
                    }
                }

                //(task, task_status, username) is 3 entries
                if (count($tasks) > 0) {
                    pg_prepare($conn, "insertMultiple",
                        "INSERT INTO content(task, task_status, username) VALUES"
                        . Util::build_insert_params(count($tasks), 3));
                    pg_execute($conn, "insertMultiple", array_merge(...$tasks));
                }

                fclose($fp);
                unlink($file_path);
                break;
            }
            default: {
                Util::notify_user("File type not supported");
                break;
            }
        }
        $refresh();
    }

    display_content();

    /*
        Utilizes data sent from fetch to delete a task from the database
        when the user clicks the delete button next to a task
    */
    if (isset($_POST['task_to_delete'])) {
        $task_id_to_delete = $_POST['task_id'];

        pg_prepare($conn, "delete", "DELETE FROM content WHERE task_id = $1");
        pg_execute($conn, "delete", array($task_id_to_delete));
    }

    /*
        Utilizes data sent from fetch to rename a task
    */
    if (isset($_POST['task_to_rename']) && isset($_POST['new_task_name'])) {
        $task_id_to_rename = $_POST['task_id'];
        $new_task_name = $_POST['new_task_name'];

        pg_prepare($conn, "rename", "UPDATE content SET task = $1 WHERE task_id = $2");
        pg_execute($conn, "rename", array($new_task_name, $task_id_to_rename));
    }

    /*
        Utilizes data sent from fetch to mark a task as completed
        or as in-progress
    */
    if (isset($_POST['task_to_mark_complete']) || isset($_POST['task_to_mark_in_progress'])) {
        $task_id_to_mark_complete = $_POST['task_id'];

        pg_prepare($conn, "mark_complete", "UPDATE content SET task_status = $1 WHERE task_id = $2");
        pg_execute($conn, "mark_complete", array(
            isset($_POST['task_to_mark_complete']) ? $completed : $in_progress,
            $task_id_to_mark_complete
        ));
    }

    echo <<< _END

        </body>
    </html>
    _END;