# Mundo Wap CakePHP Test Application for Juniors

## Installation

1. Create the `.env.app` and the `.env.db` files according to the related `.env.*.example` files.
2. Build the docker image with the bellow command:
    ```bash
    docker-compose build --force-rm
    ```
3. To enter the application command line, execute the bellow command:
    ```bash
    docker-compose run --rm app bash
    ```
4. At the application command line, install the application dependencies with the bellow command:
    ```bash
    composer install
    ```
5. To exit the application command line, execute the bellow command:
    ```bash
    exit
    ```
6. Execute the bellow command to start the application:
    ```bash
    docker-compose up -d --remove-orphans
    ```
7. Execute the bellow command to stop the application:
    ```bash
    docker-compose down
    ```

## Usage
The application should be accessible at `http://localhost:13001`.

The database should be accessible at `http://localhost:3306`.

## XDebug
Set the `XDEBUG_SESSION` key at the request cookies.

At your IDE, point the `app` project directory to the `/var/www/html` absolute path on server.

## Additional information
The database structure should be created according to the `db_structure.sql` file.

Click [here](https://bit.ly/MWDevTestPHP) to see the test specifications, requirements and instructions. 

If your implementation does not use CSRF authentication, you should remove the `Cake\Http\Middleware\CsrfProtectionMiddleware` at the `App\Application::middleware` method.