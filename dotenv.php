<?php
    declare(strict_types = 1);
    if (count(get_included_files()) === 1) {
        header("HTTP/1.1 404 Not Found");
        die();
    }

    class DotEnv {
        private string $env_file_name;
        private array $env_vars;
        private bool $modify_env_file;
        private bool $using_dot_env;
        private static string $env_var_separator = "=";

        public function __construct(bool $using_dot_env = true, string $env_file_name = ".env") {
            $this->env_vars = [];
            $this->modify_env_file = false;
            $this->using_dot_env = $using_dot_env;
            if ($using_dot_env) {
                $this->env_file_name = $env_file_name;
                $env_lines = @file($env_file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!$env_lines) {
                    $class_name = get_class();
                    die("$class_name: cannot open file \"$env_file_name\" or it doesn't exist");
                }
                foreach ($env_lines as $line) {
                    $equal_sign_pos = strpos($line, self::$env_var_separator);
                    $key = rtrim(substr($line, 0, $equal_sign_pos));
                    $value = ltrim(substr($line, $equal_sign_pos + 1));
                    $this->env_vars[$key] = $value;
                }
            } else {
                $this->env_file_name = "";
            }
        }

        public function delete(string $key): void {
            if ($this->using_dot_env) {
                unset($this->env_vars[$key]);
                if ($this->modify_env_file) {
                    $env_lines = file($this->env_file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $fp = fopen($this->env_file_name, "w");
                    foreach ($env_lines as $line) {
                        $equal_sign_pos = strpos($line, self::$env_var_separator);
                        $line_key = rtrim(substr($line, 0, $equal_sign_pos));
                        if ($key !== $line_key) {
                            fwrite($fp, "$line\n");
                        }
                    }
                    fclose($fp);
                }
            }
            putenv($key);
        }

        public function get(string $key): mixed {
            return $this->using_dot_env
                ? ($this->env_vars[$key] ?? null)
                : (getenv($key) ?? null);
        }

        public function get_env_file_name(): string {
            return $this->env_file_name;
        }

        public function get_keys_by_value(mixed $value): array {
            $arr = [];
            foreach ($this->env_vars as $env_key => $env_value) {
                if ($value === $env_value) {
                    $arr[] = $env_key;
                }
            }
            return $arr;
        }

        public function indexOf(string $key): int {
            return array_search($key, array_keys($this->env_vars)) ?: -1;
        }

        public function is_modifying_env_file(): bool {
            return $this->modify_env_file;
        }

        public function is_using_dot_env(): bool {
            return $this->using_dot_env;
        }

        public function put_envs(): bool {
            foreach ($this->env_vars as $key => $value) {
                if (!putenv("$key=$value")) {
                    return false;
                }
            }
            return true;
        }

        public function set(string $key, mixed $value): void {
            if ($this->using_dot_env) {
                $this->env_vars[$key] = $value;
                if ($this->modify_env_file) {
                    $fp = fopen($this->env_file_name, "w");
                    fwrite($fp, $this->to_env_format());
                    fclose($fp);
                }
            }
            putenv("$key=$value");
        }

        public function set_modify_env_file(bool $modify_env_file): void {
            $this->modify_env_file = $modify_env_file;
        }

        public function set_using_dot_env(bool $using_dot_env): void {
            $this->using_dot_env = $using_dot_env;
        }

        private function to_env_format(): string {
            $str = "";
            foreach ($this->env_vars as $key => $value) {
                $str .= $key . self::$env_var_separator . $value . "\n";
            }
            return $str;
        }

        public function __toString(): string {
            return var_export($this, true);
        }
    }