#!/usr/bin/env python3

import os

def raise_exit(msg = None):
    if msg is not None:
        print(msg)
    raise SystemExit

def main():
    env_file_name = ".env"
    env_file_path = ""
    if os.getcwd().endswith("/setup"):
        env_file_path += "../"
    env_file_path += env_file_name
    
    env_var_db_keys = (
        "TASK_APP_DB_HOST",
        "TASK_APP_DB_NAME",
        "TASK_APP_DB_USERNAME",
        "TASK_APP_DB_PASSWORD"
    )
    env_var_other_keys = (
        "TASK_APP_CAPTCHA_SITE_TOKEN",
        "TASK_APP_CAPTCHA_SECRET_TOKEN",
        "TASK_APP_MAIL_HOST",
        "TASK_APP_MAIL_PORT",
        "TASK_APP_MAIL_USERNAME",
        "TASK_APP_MAIL_PASSWORD",
    )
    env_var_keys = env_var_db_keys + env_var_other_keys
    env_vars = {}

    print(f"Setting up {env_file_name} file...")
    if os.path.isfile(env_file_path):
        while True:
            answer = input("WARNING: file already exists! Overwrite it? [y/n] ").lower()
            if answer == "y":
                os.remove(env_file_path)
                break
            elif answer == "n":
                raise_exit("Abort.")
    
    print()
    print("To leave a variable blank, just press the enter key.")
    for key in env_var_keys:
        value = input(f"{key}: ").strip()
        env_vars[key] = value
    
    with open(env_file_path, "w+") as f:
        for key, value in env_vars.items():
            f.write(f"{key}={value}\n")
    
    print(f"Successfully wrote variables to {env_file_name} file.")

if __name__ == "__main__":
    main()