#!/usr/bin/env python

import os

def raise_exit(msg = None):
    if msg is not None:
        print(msg)
    raise SystemExit

def create_tables(env_vars, db_var_keys):
    try:
        import psycopg2
    except ImportError:
        raise_exit("ERROR: Could not import psycopg2, perhaps it's not installed?")
    
    host, db_name, username, password = db_var_keys
    conn = psycopg2.connect(
        host=env_vars[host],
        dbname=env_vars[db_name],
        user=env_vars[username],
        password=env_vars[password]
    )
    dir_name = os.path.dirname(os.path.abspath(__file__))
    with conn.cursor() as cursor, open(f"{dir_name}/sql/create.sql", "r") as f:
        cursor.execute(f.read())
    
    conn.commit()
    conn.close()

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
                raise_exit("Abort.")
    
    for key in env_var_keys:
        value = input(f"{key}: ").strip()
        env_vars[key] = value
    
    with open(env_file_path, "w+") as f:
        for key, value in env_vars.items():
            f.write(f"{key}={value}\n")
    
    print(f"Successfully wrote to {env_file_name} file.")
    while True:
        answer = input("(Requires psycopg2) Create necessary tables in database? [y/n] ")
        if answer == "y":
            create_tables(env_vars, env_var_keys[:4])
            print("Tables successfully created.")
            break
        elif answer == "n":
            raise_exit()

if __name__ == "__main__":
    main()