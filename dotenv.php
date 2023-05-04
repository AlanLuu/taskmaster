<?php
    declare(strict_types = 1);
    class DotEnv {
        private string $env_file_name;
        private array $env_vars;
        private bool $using_dot_env;
        private static string $env_var_separator = "=";

        public function __construct(bool $using_dot_env = true, string $env_file_name = ".env") {
            $this->env_vars = [];
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
            $this->using_dot_env = $using_dot_env;
        }

        public function delete(string $key): bool {
            if ($this->using_dot_env) {
                unset($this->env_vars[$key]);
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
                return true;
            } else {
                return putenv($key);
            }
        }

        public function get(string $key): mixed {
            return $this->using_dot_env
                ? ($this->env_vars[$key] ?? null)
                : (getenv($key) ?? null);
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

        public function put_envs(): bool {
            foreach ($this->env_vars as $key => $value) {
                if (!putenv("$key=$value")) {
                    return false;
                }
            }
            return true;
        }

        public function set(string $key, mixed $value): bool {
            if ($this->using_dot_env) {
                $this->env_vars[$key] = $value;
                $fp = fopen($this->env_file_name, "w");
                fwrite($fp, $this->to_env_format());
                fclose($fp);
                return true;
            } else {
                return putenv("$key=$value");
            }
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