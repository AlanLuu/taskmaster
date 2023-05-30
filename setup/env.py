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
        print("ERROR: Could not import psycopg2, perhaps it's not installed?")
        raise_exit("After installing that package, run tables.py to continue setting up the tables.")
    
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
    
    env_var_db_keys = (
        "TASK_APP_DB_HOST",
        "TASK_APP_DB_NAME",
        "TASK_APP_DB_USERNAME",
        "TASK_APP_DB_PASSWORD"
    )
    env_var_other_keys = (
        "TASK_APP_CAPTCHA_SITE_TOKEN",
        "TASK_APP_CAPTCHA_SECRET_TOKEN"
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
    
    print(f"Successfully wrote to {env_file_name} file.")
    while True:
        answer = input("(Requires psycopg2) Create necessary tables in database? [y/n] ")
        if answer == "y":
            create_tables(env_vars, env_var_db_keys)
            print("Tables successfully created.")
            break
        elif answer == "n":
            print("Exiting without creating necessary tables...")
            raise_exit("If you change your mind, run tables.py to create them (requires psycopg2).")

if __name__ == "__main__":
    main()