#!/usr/bin/env python

import os

def main():
    env_file_name = ".env"
    env_file_path = ""
    if os.getcwd().endswith("/setup"):
        env_file_path += "../"
    env_file_path += env_file_name
    
    env_var_keys = (
        "TASK_APP_DB_HOST",
        "TASK_APP_DB_NAME",
        "TASK_APP_DB_USERNAME",
        "TASK_APP_DB_PASSWORD",
        "TASK_APP_CAPTCHA_SITE_TOKEN",
        "TASK_APP_CAPTCHA_SECRET_TOKEN"
    )
    env_vars = {}

    print(f"Setting up {env_file_name} file...")
    if os.path.isfile(env_file_path):
        while True:
            answer = input("WARNING: file already exists! Overwrite it? [y/n] ").lower()
            if answer == "y":
                os.remove(env_file_path)
                break
            elif answer == "n":
                print("Abort.")
                raise SystemExit
    
    for key in env_var_keys:
        value = input(f"{key}: ").strip()
        env_vars[key] = value
    
    with open(env_file_path, "w+") as f:
        for key, value in env_vars.items():
            f.write(f"{key}={value}\n")
    
    print(f"Successfully wrote to {env_file_name} file.")

if __name__ == "__main__":
    main()