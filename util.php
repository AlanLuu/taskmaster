<?php
    declare(strict_types = 1);
    
    /**
     * https://www.php.net/manual/en/function.ob-start.php
     * 
     * This function will turn output buffering on. While output buffering is active
     * no output is sent from the script (other than headers), instead the output is
     * stored in an internal buffer.
     * 
     * The contents of this internal buffer may be copied into a string variable using
     * ob_get_contents(). To output what is stored in the internal buffer, use ob_end_flush().
     * Alternatively, ob_end_clean() will silently discard the buffer contents.
     * 
     * If output buffering is still active when the script ends, PHP outputs the contents
     * automatically.
     */
    ob_start();
    
    //Some useful constants
    const ADMIN_ACCOUNT_IDS = [1];
    const BCRYPT_COST = 12;
    const GET = "GET";
    const GLOBAL_LOGGING_ENABLED = false;
    const POST = "POST";
    const WEBSITE_NAME = "Task Master";
    define("PACIFIC", new DateTimeZone("America/Los_Angeles"));

    //Database credentials
    define("DB_HOST", getenv("TASK_APP_DB_HOST"));
    define("DB_PORT", getenv("TASK_APP_DB_PORT") ?: "5432");
    define("DB_NAME", getenv("TASK_APP_DB_NAME"));
    define("DB_USERNAME", getenv("TASK_APP_DB_USERNAME"));
    define("DB_PASSWORD", getenv("TASK_APP_DB_PASSWORD"));
    if (!DB_HOST || !DB_NAME || !DB_USERNAME || !DB_PASSWORD) {
        $env_error_msg = "";
        if (!DB_HOST) {
            $env_error_msg .= "DB_HOST env var not set" . "<br>";
        }
        if (!DB_NAME) {
            $env_error_msg .= "DB_NAME env var not set" . "<br>";
        }
        if (!DB_USERNAME) {
            $env_error_msg .= "DB_USERNAME env var not set" . "<br>";
        }
        if (!DB_PASSWORD) {
            $env_error_msg .= "DB_PASSWORD env var not set" . "<br>";
        }
        die($env_error_msg);
    }

    /*
     * Technically optional; if TASK_APP_CAPTCHA_SECRET_TOKEN is unset then CAPTCHA
     * functionality will simply be disabled without affecting any other important
     * website functionality.
     */
    define("CAPTCHA_SECRET_TOKEN", getenv("TASK_APP_CAPTCHA_SECRET_TOKEN"));
    setcookie("captcha_enabled", CAPTCHA_SECRET_TOKEN ? "1" : "0");

    /**
     * Util class with helper functions
     */
    final class Util {
        private function __construct() {}

        /**
         * If the page has an element with an textContent attribute, this function
         * can be used to modify its textContent attribute with a specified message
         */
        public static function notify_user(mixed $msg, string $id = "infomsg"): void {
            echo <<< _END
                
                <script>document.querySelector("#$id").textContent = "$msg";</script>
            _END;
        }

        /**
         * Same as above function but also stops execution of current script
         */
        public static function notify_user_and_die(mixed $msg, string $id = "infomsg"): void {
            self::notify_user($msg, $id);
            echo <<< _END
                
                </body>
            </html>
            _END;
            die();
        }

        /**
         * Used for responding with a specific HTTP code and displaying an
         * informative message to the user before terminating the script.
         * If $content is an array, responds with the array in JSON form.
         * 
         * @param int $code HTTP code
         * @param mixed $content the message to display
         * @return void
         */
        public static function respond_http_and_die(int $code, mixed $content): void {
            http_response_code($code);
            echo is_array($content) ? json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $content;
            die();
        }

        /**
         * Call this function to verify the result of a CAPTCHA on a page.
         * 
         * @return bool true if the user had successfully completed the CAPTCHA
         * on the page where this function was called, false otherwise.
         */
        public static function verify_captcha(): bool {
            if (!CAPTCHA_SECRET_TOKEN) {
                return true;
            }
            $user_token = $_POST['g-recaptcha-response'] ?? null;
            if (!$user_token) {
                return false;
            }
            $url = "https://www.google.com/recaptcha/api/siteverify?secret="
                . CAPTCHA_SECRET_TOKEN . "&response=$user_token";
            $response = json_decode(file_get_contents($url));
            return $response->success;
        }

        /**
         * Convenience function for hashing a password without
         * having to manually specify the algorithm and cost
         *
         * @param string $password the plaintext password to hash
         * @return string the hashed password
         */
        public static function password_hash(string $password): string {
            return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        }

        /**
         * I'm a little teapot,
         * Short and stout.
         * Here is my handle,
         * Here is my spout.
         * When I get all steamed up,
         * Hear me shout:
         * "Tip me over and pour me out!"
         * 
         * Redirects to teapot.php, which returns HTTP code 418.
         * 
         * @return void
         */
        public static function im_a_little_teapot(): void {
            header("Location: teapot.php");
            die();
        }

        /**
         * Sanitize a string using an array of callback functions specified by the
         * caller
         */
        public static function sanitize(?string $content, callable ...$funcs): ?string {
            if ($content === null) return null;
            foreach ($funcs as $func) {
                $content = $func($content);
            }
            return $content;
        }

        /**
         * Useful for generating random tokens.
         * NOTE: This function returns a string that is twice the length passed in.
         * Example: Util::rand_token(15) returns a string with a length of 30.
         * 
         * @param int $length
         * @return string a string with twice the length that was passed in
         */
        public static function rand_token(int $length): string {
            return bin2hex(random_bytes($length));
        }

        /**
         * Helper function for creating a file for logging
         */
        private static function create_file_if_not_exists(string $file_path): void {
            if (!file_exists($file_path)) {
                //If a directory is specified and it doesn't exist, attempt to create it
                $directory_name = substr($file_path, 0, strrpos($file_path, "/"));
                if ($directory_name && !is_dir($directory_name)) {
                    mkdir($directory_name);
                }
                touch($file_path);
            }
        }

        /**
         * Logs content to a file specified by $file_path. If the file
         * doesn't exist, it will be created.
         * 
         * @param string $file_path the file to log to
         * @param mixed $content the content to log
         * @param bool $always_log [optional] if true, will log the content
         * without considering whether global logging is explicitly disabled
         * @return void
         */
        public static function log(string $file_path, mixed $content, bool $always_log = false): void {
            if (!GLOBAL_LOGGING_ENABLED && !$always_log) return;
            $date = new DateTime("now", PACIFIC);
            $now = $date->format("Y-m-d H:i:s");
            $file_path = strpos($file_path, "/") === false ? "./logs/$file_path" : $file_path;
            self::create_file_if_not_exists($file_path);

            $ip = self::get_user_IP();
            $fp = fopen($file_path, "a");
            fwrite($fp, "[$now] [$ip] $content\n");
            fclose($fp);
        }

        /**
         * Provides a way to log content without having to type out
         * the same file path over and over
         * 
         * @param string $file_path the file to log to
         * @return Closure a closure that takes in the content to log
         * as `$content` and calls `Util::log($file_path, $content)`
         */
        public static function create_logger(string $file_path): Closure {
            /**
             * @param mixed $content the content to log
             * @param bool $bypass [optional] if true, will log the content
             * without considering whether global logging is explicitly disabled
             * @return void
             */
            return function(mixed $content, bool $bypass = false) use(&$file_path): void {
                self::log($file_path, $content, $bypass);
            };
        }

        /**
         * Logs the user's IP address and how many times the IP has visited
         * a specific page in the format "IP, count" to the file specified
         * by $file_path. If the file doesn't exist, it will be created.
         * 
         * @param string $file_path the file to log to
         * @return void
         */
        public static function log_ip_count(string $file_path): void {
            if (!GLOBAL_LOGGING_ENABLED) return;
            self::create_file_if_not_exists($file_path);

            //Read each line of file into array before clearing the original
            //file with fopen
            $file = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fp = fopen($file_path, "w");
            $user_ip = self::get_user_IP();
            $found_ip = false; //Has the user's IP been logged before?
            foreach ($file as $line) {
                //Remove all spaces, split by comma
                $line = str_replace(" ", "", $line);
                $line_arr = explode(",", $line);
                
                if (count($line_arr) === 2) {
                    [$file_ip, $count] = $line_arr;
                    $count = (int) $count;

                    //Validate IP format
                    if (filter_var($file_ip, FILTER_VALIDATE_IP)) {
                        //IP has been logged before
                        if ($file_ip === $user_ip) {
                            $found_ip = true;
                            $count++;
                        }

                        fwrite($fp, "$file_ip, $count\n");
                    }
                }
            }

            //IP has not been logged before
            if (!$found_ip) {
                fwrite($fp, "$user_ip, 1\n");
            }
            fclose($fp);
        }

        /**
         * Useful for debugging by logging the values of PHP variables
         * to the browser's JS console
         */
        public static function console_log(mixed $data = ""): void {
            echo "<script>";
            echo "console.log(" . json_encode($data) . ");";
            echo "</script>";
        }

        /**
         * @return string the client's IP address as a string
         */
        public static function get_user_IP(): string {
            $ip_address = $_SERVER['HTTP_CLIENT_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['HTTP_X_FORWARDED']
                ?? $_SERVER['HTTP_X_CLUSTER_CLIENT_IP']
                ?? $_SERVER['HTTP_FORWARDED_FOR']
                ?? $_SERVER['HTTP_FORWARDED']
                ?? $_SERVER['REMOTE_ADDR']
                ?? "0.0.0.0";
            if ($ip_address === "::1") $ip_address = "127.0.0.1";
            return $ip_address;
        }

        /**
         * Returns a PostgreSQL database connection instance
         * 
         * @param int $flags [optional] additional flags to specify - see
         * https://www.php.net/manual/en/function.pg-connect.php
         * for more information about these flags
         * @return PgSql\Connection
         * @throws DatabaseException if the connection to the database fails
         */
        public static function get_conn(int $flags = 0): PgSql\Connection {
            static $conn_str;
            if ($conn_str === null) {
                $conn_str = sprintf("host = %s port = %s dbname = %s user = %s password = %s",
                    DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);
            }
            $result = pg_connect($conn_str, $flags);
            if (!$result) {
                throw new DatabaseException("Database connection failed");
            }
            return $result;
        }

        /**
         * Convenience method for querying the database
         * 
         * @param string $query the SQL query to execute
         * @param PgSql\Connection $conn [optional] the database connection object,
         * creates a temporary one for the current query if not specified
         * @return PgSql\Result
         * @throws DatabaseException if the connection to the database fails
         */
        public static function query(string $query, PgSql\Connection $conn = null): PgSql\Result {
            /*
                If a second call is made to pg_connect() with the same connection_string
                as an existing connection, the existing connection will be returned 
                unless PGSQL_CONNECT_FORCE_NEW is passed as a flag.
                https://www.php.net/manual/en/function.pg-connect.php
            */
            $result = pg_query($conn ?? self::get_conn(), $query);
            if (!$result) {
                throw new DatabaseException("Query failed");
            }
            return $result;
        }

        /**
         * The second parameter of PHP's `substr` function is NOT the ending index,
         * rather it is the number of characters to return. This function serves
         * as a wrapper around `substr` that acts like the traditional substring
         * function mostly found in languages such as Java.
         * 
         * @param string $string The input string
         * @param int $start The starting index, inclusive
         * @param int $end The ending index, exclusive
         * @return string The specified substring or an empty string on failure
         */
        public static function substring(string $string, int $start, int $end): string {
            if ($start < 0 || $start > $end) {
                return "";
            }
            return substr($string, $start, $end - $start);
        }

        /**
         * Constructs part of a prepared statement for insertion into a PostgreSQL database
         * @param int $num_rows number of rows to insert
         * @param int $num_entries number of elements in each row
         * @return string a string of the form
         * "($1, $2, ..., $num_entries), ..., ($num_rows - 1, $num_rows)"
         */
        public static function build_insert_params(int $num_rows, int $num_entries): string {
            $params = "";
            $count = 1;
            for ($i = 1; $i <= $num_rows; $i++) {
                $params .= "(";
                for ($j = 1; $j <= $num_entries; $j++) {
                    $params .= "\$$count";
                    if ($j < $num_entries) {
                        $params .= ", ";
                    }
                    $count++;
                }
                $params .= ")";
                if ($i < $num_rows) {
                    $params .= ", ";
                }
            }
            return $params;
        }

        /**
         * Returns the full URL of the current page, optionally replacing
         * the current PHP file name with a specified string
         * 
         * @param string $str [optional] the specified string
         * @return string the full URL, or if $str is specified, "FULL_URL/$str"
         */
        public static function full_url(string $str = ""): string {
            $url = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? "http") . "://"
                . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if (!$str) {
                return $url;
            } else {
                return substr($url, 0, strrpos($url, "/") + 1) . $str;
            }
        }

        /**
         * If the username is valid, returns an empty string, otherwise
         * returns a nonempty string with details regarding why the username
         * isn't valid
         */
        public static function validate_username(string $username): string {
            $username_max_chars = 15;
            $username = preg_replace("/\s+/", "", $username); //Remove whitespace
    
            if (!$username) {
                return "Username cannot be blank";
            }
    
            if (strlen($username) > $username_max_chars) {
                return "Username must be $username_max_chars characters or less";
            }
    
            return "";
        }

        /**
         * If the password is valid, returns an empty string, otherwise
         * returns a nonempty string with details regarding why the password
         * isn't valid
         */
        public static function validate_password(string $password): string {
            $password_min_chars = 2;
    
            if (!$password) {
                return "Password cannot be blank";
            }
    
            if (strlen($password) < $password_min_chars) {
                return "Password must be at least $password_min_chars character" . ($password_min_chars !== 1 ? "s" : "") . " long";
            }
    
            //Password regex taken from here: https://stackoverflow.com/a/21456918
            $password_regex = "/^(?=.*[A-Za-z])(?=.*\\d)[A-Za-z\\d]{" . $password_min_chars . ",}$/";
            if (!preg_match($password_regex, $password)) {
                return "Password must contain at least 1 letter and 1 number";
            }
    
            return "";
        }

        /**
         * If the email address is valid, returns an empty string, otherwise
         * returns a nonempty string with details regarding why the email address
         * isn't valid
         */
        public static function validate_email(?string $email): string {
            if ($email !== NULL && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return "Invalid email format";
            }
            return "";
        }
    }

    /**
     * Exception thrown if any database operation fails
     */
    final class DatabaseException extends Exception {}