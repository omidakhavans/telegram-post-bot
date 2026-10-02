<?php

// Try to determine the WordPress tests directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    // Try to locate it if the plugin is in a standard WP dev setup
    // e.g. /path/to/wordpress-develop/src/wp-content/plugins/telegram-post-bot/
    // then $_tests_dir could be /path/to/wordpress-develop/tests/phpunit/
    // This part might need adjustment based on the execution environment.
    // For now, we'll assume it might be a few levels up, then into a standard WP dev checkout.
    // A more robust solution often involves `composer require --dev yoast/wp-test-utils` or similar
    // which provides a more reliable way to bootstrap.
    // Given the current constraints, let's try a relative path common in some setups.
    $_try_path = dirname( __DIR__, 3 ) . '/tests/phpunit'; // Assumes plugin is in wp-content/plugins/your-plugin
    if ( file_exists( $_try_path . '/includes/functions.php' ) ) {
        $_tests_dir = $_try_path;
    } else {
        // Fallback if not found - this will likely cause tests to fail to load WP env
         error_log('WP_TESTS_DIR environment variable is not set. Attempted path: ' . $_try_path); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

// Mock for error_log for testing purposes
// This needs to be defined *before* the plugin itself is loaded,
// and ideally only when WP_TESTS_PHPUNIT is defined.
if (!function_exists('error_log') && defined('WP_TESTS_PHPUNIT')) {
    /**
     * Mock version of error_log.
     * Records calls if $GLOBALS['mock_error_log'] is true.
     */
    function error_log($message) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if (isset($GLOBALS['mock_error_log']) && $GLOBALS['mock_error_log'] === true) {
            if (!isset($GLOBALS['mock_error_log_calls'])) {
                $GLOBALS['mock_error_log_calls'] = [];
            }
            $GLOBALS['mock_error_log_calls'][] = $message;
            return true;
        }
        // Fallback to the original error_log function if the mock is not active.
        // Note: This might require careful handling if the original error_log is namespaced or aliased.
        // For a simple global function, this direct call should work.
        return \error_log($message);
    }
}

// If WP_TESTS_DIR is still not set, try another common structure
if ( ! $_tests_dir && getenv( 'WP_PHPUNIT_DIR' ) ) {
    $_tests_dir = getenv( 'WP_PHPUNIT_DIR' );
}


if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find tests/phpunit/includes/functions.php, try setting the WP_TESTS_DIR environment variable.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Mock for send_telegram_message for testing purposes
// This needs to be defined *before* the plugin itself is loaded.
if (!function_exists('send_telegram_message')) {
    /**
     * Mock version of send_telegram_message.
     * Records calls if $GLOBALS['mock_send_telegram_message'] is true.
     */
    function send_telegram_message($chat_id, $message, $data = array()) {
        if (isset($GLOBALS['mock_send_telegram_message']) && $GLOBALS['mock_send_telegram_message'] === true) {
            if (!isset($GLOBALS['mock_send_telegram_message_calls'])) {
                $GLOBALS['mock_send_telegram_message_calls'] = [];
            }
            $GLOBALS['mock_send_telegram_message_calls'][] = func_get_args();
            // Return true to simulate a successful API call, as the original function likely does.
            return true;
        }
        // Fallback if called without the mock being active (e.g., during setup or if not in a test).
        // This indicates a potential issue in test setup or unexpected usage.
        error_log("Mocked send_telegram_message called unexpectedly for chat_id: $chat_id"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        // Return what the original function might return on failure or if it's void.
        // Returning false if the original might indicate failure.
        return false;
    }
}

// Mock for create_wordpress_post for testing purposes
// This needs to be defined *before* the plugin itself is loaded.
if (!function_exists('create_wordpress_post')) {
    /**
     * Mock version of create_wordpress_post.
     * Records calls if $GLOBALS['mock_create_wordpress_post'] is true.
     */
    function create_wordpress_post($data, $chat_id) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if (isset($GLOBALS['mock_create_wordpress_post']) && $GLOBALS['mock_create_wordpress_post'] === true) {
            if (!isset($GLOBALS['mock_create_wordpress_post_calls'])) {
                $GLOBALS['mock_create_wordpress_post_calls'] = [];
            }
            $GLOBALS['mock_create_wordpress_post_calls'][] = func_get_args();
            // Return a dummy post ID, as the original function might.
            return 12345; // Dummy post ID
        }
        // Fallback if called without the mock being active
        error_log("Mocked create_wordpress_post called unexpectedly for chat_id: $chat_id"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        return new WP_Error('mock_not_active', 'create_wordpress_post mock was not active.');
    }
}


/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/telegram-post-bot.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Ensure the REST API is loaded for route registration tests
if (function_exists('rest_get_server')) {
    $GLOBALS['wp_rest_server'] = rest_get_server();
}

echo "WordPress testing environment loaded. Plugin bootstrapped.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

?>
