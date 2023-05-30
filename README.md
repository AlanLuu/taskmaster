# Task Master
A simple web application that keeps track of all your tasks that are yet to be started, in progress, or completed.

# Setup
Requirements:
- PHP 8.1+ with the PostgreSQL extension enabled
- Python 3.x to run the configuration scripts
- [Composer](https://getcomposer.org/) to install necessary PHP dependencies

First, clone this repository:
```
git clone https://github.com/AlanLuu/taskmaster.git
cd taskmaster
```

Next, configure the necessary variables and set up the database schema:
```
python setup/env.py
python setup/tables.py
```
The prompts will guide you through the process.

**NOTE**: you will have to install the necessary Python packages if you wish to set up the database schema automatically:
```
pip install -r setup/requirements.txt
```
You may install these dependencies into a virtual environment and run the setup scripts from that environment. To do that:
```
python -m venv venv
source venv/bin/activate
pip install -r setup/requirements.txt
python setup/env.py
python setup/tables.py
```
After this, the virtual environment is no longer needed:
```
deactivate
rm -rf venv
```

Next, install necessary dependencies using Composer:
```
composer install
```
If you don't have Composer installed, run the following commands to install dependencies:
```
wget https://getcomposer.org/download/latest-stable/composer.phar
chmod +x composer.phar
php composer.phar install
```

Finally, to start the server, run the following command:
```
./start
```
A PHP development server will be started on port `8000` by default. You can also specify a different port number to listen on:
```
./start 5000
```
Then open a web browser and visit `http://localhost:PORT`, where `PORT` is `8000` by default. If you specified a different port number, replace `PORT` with that port number.

**NOTE**: If you encounter the error `Call to undefined function pg_connect()`, you must install the `php-pgsql` package. On Debian/Ubuntu, run the following command:
```
sudo apt install php-pgsql
```
Then restart the server.