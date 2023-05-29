#!/usr/bin/env python

import os

def main():
    try:
        import psycopg2
    except ImportError:
        print("ERROR: Could not import psycopg2, perhaps it's not installed?")
        raise SystemExit
    
    env_file_name = ".env"
    dir_name = os.path.dirname(os.path.abspath(__file__))
    with open(f"{dir_name.removesuffix('setup')}{env_file_name}", "r") as f:
        lines = [line.rstrip().split("=")[1] for line in f][:4]
    
    host, db_name, username, password = lines
    conn = psycopg2.connect(
        host=host,
        dbname=db_name,
        user=username,
        password=password
    )
    with conn.cursor() as cursor, open(f"{dir_name}/sql/create.sql", "r") as f:
        cursor.execute(f.read())
    
    conn.commit()
    conn.close()
    print("Tables successfully created.")

if __name__ == "__main__":
    main()