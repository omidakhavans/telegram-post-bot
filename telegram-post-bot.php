<?php
/**
 * Plugin Name: Telegram Post Bot
 * Description: Submit WordPress posts via Telegram.
 * Version: 0.1.0
 * Author: Omid
 * Requires PHP: 8.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Telegram\Bot\Api;
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable( __DIR__ );
    $dotenv->safeLoad();
} catch ( Exception $e ) {
    error_log( 'Telegram Post Bot: Failed to load .env file - ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

define( 'TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?: ''  );
define( 'TELEGRAM_AUTHORIZED_USERS', $_ENV['TELEGRAM_AUTHORIZED_USERS'] ?: ''  );

add_action( 'rest_api_init', 'register_telegram_webhook_route' );

/**
 * Registers the Telegram webhook REST API endpoint.
 *
 * @return void
 */
function register_telegram_webhook_route() {
    register_rest_route(
        'telegram/v1',
        '/webhook/',
        array(
            'methods'             => 'POST',
            'callback'            => 'handle_telegram_update',
            'permission_callback' => '__return_true',
        )
    );
}

/**
 * Handles incoming Telegram messages and processes user input for post creation.
 *
 * @param WP_REST_Request $request The REST API request object.
 *
 * @return WP_REST_Response REST response indicating success or failure.
 */
function handle_telegram_update( WP_REST_Request $request ) {
    if ( empty( TELEGRAM_BOT_TOKEN ) ) {
        return new WP_REST_Response( esc_html__( 'Bot token missing', 'telegram-post-bot' ), 400 );
    }

    $authorized_users = array_map( 'absint', explode( ',', TELEGRAM_AUTHORIZED_USERS ) );
    $update = $request->get_json_params();
    $message = $update['message'] ?? null;

    if ( ! $message ) {
        return new WP_REST_Response( esc_html__( 'No message received', 'telegram-post-bot' ), 400 );
    }

    $chat_id = absint( $message['chat']['id'] );
    $user_id = absint( $message['from']['id'] );
    $text    = sanitize_text_field( $message['text'] ?? '' );

    if ( ! in_array( $user_id, $authorized_users, true ) ) {
        send_telegram_message( $chat_id, esc_html__( 'Unauthorized user.', 'telegram-post-bot' ) );
        return new WP_REST_Response( esc_html__( 'Unauthorized user', 'telegram-post-bot' ), 403 );
    }

	if ("/start" === $text ) {
		$keyboard = [
			'keyboard' => [
				[['text' => '/post'], ['text' => '/endsession']]
			],
			'resize_keyboard' => true,
			'one_time_keyboard' => false
		];

		send_telegram_message( $chat_id, esc_html__( 'Welcome! Use the menu below to navigate: /post - Begin a new session /endsession - Cancel the current session.', 'telegram-post-bot' ), array('reply_markup' => json_encode($keyboard)) );

		return new WP_REST_Response('Session started', 200);
	}

	if ($text === "/endsession") {
        delete_transient("telegram_post_{$user_id}");
		send_telegram_message( $chat_id, esc_html__( 'Session ended. Use /start to begin again.', 'telegram-post-bot' ) );

        return new WP_REST_Response('Session ended', 200);
    }

    $user_state = get_transient("telegram_post_{$user_id}") ?: [];

    if ( "/post" === $text ) {
        send_telegram_message( $chat_id, esc_html__( 'Send the post title.', 'telegram-post-bot' ) );
    } elseif ( ! isset( $user_state['title'] ) ) {
        $user_state['title'] = $text;
        set_transient( "telegram_post_{$user_id}", $user_state, HOUR_IN_SECONDS );
        send_telegram_message( $chat_id, esc_html__( 'Title saved. Now, send tags (comma-separated).', 'telegram-post-bot' ) );
    } elseif ( ! isset( $user_state['tags'] ) ) {
        $user_state['tags'] = array_map( 'sanitize_text_field', explode( ',', $text ) );
        set_transient( "telegram_post_{$user_id}", $user_state, HOUR_IN_SECONDS );
        send_telegram_message( $chat_id, esc_html__( 'Tags saved. Now, send a category.', 'telegram-post-bot' ) );
    } elseif ( ! isset( $user_state['category'] ) ) {
        $user_state['category'] = $text;
        set_transient( "telegram_post_{$user_id}", $user_state, HOUR_IN_SECONDS );
        send_telegram_message( $chat_id, esc_html__( 'Category saved. Now, send the content.', 'telegram-post-bot' ) );
    } elseif ( ! isset( $user_state['content'] ) ) {
        $user_state['content'] = $text;
        set_transient( "telegram_post_{$user_id}", $user_state, HOUR_IN_SECONDS );
        send_telegram_message( $chat_id, esc_html__( 'Content saved. Type publish to submit your post.', 'telegram-post-bot' ) );
    } elseif ( 'publish' === strtolower( $text ) ) {
        create_wordpress_post( $user_state, $chat_id );
        delete_transient( "telegram_post_{$user_id}" );
        return new WP_REST_Response( esc_html__( 'Post submitted and session ended', 'telegram-post-bot' ), 200 );
    } else {
        send_telegram_message( $chat_id, esc_html__( 'Invalid command. Type publish to submit your post.', 'telegram-post-bot' ) );
    }

    return new WP_REST_Response( esc_html__( 'Request processed', 'telegram-post-bot' ), 200 );
}

/**
 * Creates a new WordPress post from user input.
 *
 * @param array $data The user-provided post data.
 * @param int   $chat_id The Telegram chat ID.
 *
 * @return void
 */
function create_wordpress_post( $data, $chat_id ) {
	$category_name = sanitize_text_field( $data['category'] );
	$category_slug = sanitize_title( $category_name );

	// Check if category exists.
	$category = get_category_by_slug( $category_slug );

	if ( !$category ) {
		$category_id = wp_insert_term( $category_name, 'category', array( 'slug' => $category_slug ) );

		if ( is_wp_error( $category_id ) ) {
			send_telegram_message( $chat_id, esc_html__( 'Error creating category: ', 'telegram-post-bot' ) . $category_id->get_error_message() );
			return;
		}

		$category_id = $category_id['term_id'];
	} else {
		$category_id = $category->term_id;
	}

    $post_id = wp_insert_post(
        array(
            'post_title'    => sanitize_text_field( $data['title'] ),
            'post_content'  => wp_kses_post( $data['content'] ),
            'post_status'   => 'draft',
            'post_type'     => 'post',
            'tags_input'    => array_map( 'sanitize_text_field', $data['tags'] ),
            'post_category' => ( $category_id ) ? array( $category_id ) : array(),
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        send_telegram_message( $chat_id, esc_html__( 'Error creating post: ', 'telegram-post-bot' ) . $post_id->get_error_message() );
        return;
    }

    send_telegram_message( $chat_id, esc_html__( 'Post submitted successfully! View: ', 'telegram-post-bot' ) . esc_url( get_permalink( $post_id ) ) );
}

/**
 * Sends a message to a Telegram chat.
 *
 * @param int    $chat_id Telegram chat ID.
 * @param string $message The message to send.
 *
 * @return void
 */
function send_telegram_message( $chat_id, $message, $data = array() ) {
    if ( empty( TELEGRAM_BOT_TOKEN ) ) {
        return;
    }

    $telegram = new Api( TELEGRAM_BOT_TOKEN );

    try {
        $telegram->sendMessage(
            array(
                'chat_id' => absint( $chat_id ),
                'text'    => $message,
				extract($data)
            )
        );
    } catch ( Exception $e ) {
        error_log( 'Telegram Post Bot: Failed to send message - ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
