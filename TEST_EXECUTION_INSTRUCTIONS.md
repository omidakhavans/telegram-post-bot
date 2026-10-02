# Running the WordPress Unit Tests for Telegram Post Bot

This guide explains how to set up and run the PHPUnit tests for the Telegram Post Bot plugin.

## Prerequisites

1.  **PHP and Composer**: Ensure you have PHP (compatible version with WordPress and PHPUnit) and Composer installed on your system.
2.  **WordPress Development Environment**: You need a WordPress development environment. This can be:
    *   A local WordPress installation where you can check out the plugin.
    *   A clone of the official WordPress develop repository (`git clone git://develop.git.wordpress.org/`).
    The tests require access to the WordPress PHPUnit testing framework.
3.  **Environment Variable `WP_TESTS_DIR`**: The `WP_TESTS_DIR` environment variable must be set and point to the WordPress PHPUnit test library directory.
    *   If you have a clone of the WordPress develop repository, this would be its `tests/phpunit` subdirectory.
    *   Example: `export WP_TESTS_DIR=/path/to/your/wordpress-develop/tests/phpunit`
    *   This path is referenced in the plugin's `tests/bootstrap.php` file to load the WordPress testing environment.

## Setup Steps

1.  **Clone the Repository**:
    ```bash
    git clone <repository_url>
    cd telegram-post-bot 
    ```
    (Replace `<repository_url>` with the actual URL of the Telegram Post Bot plugin repository).

2.  **Navigate to Plugin Directory**:
    If you didn't `cd` in the previous step, make sure you are in the root directory of the `telegram-post-bot` plugin.

3.  **Install Dependencies**:
    Run Composer to install the plugin's dependencies and the development tools (including PHPUnit):
    ```bash
    composer install --dev
    ```

4.  **Set `WP_TESTS_DIR`**:
    Ensure the `WP_TESTS_DIR` environment variable is correctly set in your terminal session or your system's environment configuration.
    ```bash
    export WP_TESTS_DIR=/path/to/your/wordpress-develop/tests/phpunit
    ```
    Verify that this path contains `includes/functions.php` and `includes/bootstrap.php`.

## Running Tests

Once the setup is complete, you can run the tests using one of the following commands from the plugin's root directory:

1.  **Using the Composer script**:
    ```bash
    composer test
    ```
    This command executes the `test` script defined in `composer.json`, which in turn runs PHPUnit.

2.  **Directly running PHPUnit**:
    ```bash
    vendor/bin/phpunit
    ```
    This directly invokes the PHPUnit executable installed by Composer.

The tests will run, and you'll see output indicating the status of each test (pass, fail, skip). The `phpunit.xml.dist` file in the plugin's root directory configures the test execution, including which tests to run and the environment variables like `TELEGRAM_BOT_TOKEN` for the test suite.
