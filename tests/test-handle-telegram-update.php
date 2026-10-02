<?php

use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests for the handle_telegram_update function.
 *
 * These tests rely on TELEGRAM_BOT_TOKEN and TELEGRAM_AUTHORIZED_USERS being set
 * via <env> variables in phpunit.xml.dist.
 * Default values used for most tests:
 * TELEGRAM_BOT_TOKEN = "dummy-test-token"
 * TELEGRAM_AUTHORIZED_USERS = "999999"
 */
class Test_Handle_Telegram_Update extends WP_UnitTestCase {

    protected $test_user_id = 12345;
    protected $test_chat_id = 123;
    protected $transient_key;

    public function setUp(): void {
        parent::setUp();
        // Ensure the REST server is initialized
        if ( ! isset( $GLOBALS['wp_rest_server'] ) || ! $GLOBALS['wp_rest_server'] instanceof WP_REST_Server ) {
            $GLOBALS['wp_rest_server'] = new WP_REST_Server;
            do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
        }

        $this->transient_key = "telegram_post_" . $this->test_user_id;

        // Activate mock for send_telegram_message
        $GLOBALS['mock_send_telegram_message'] = true;
        $GLOBALS['mock_send_telegram_message_calls'] = [];

        // Activate mock for create_wordpress_post
        $GLOBALS['mock_create_wordpress_post'] = true;
        $GLOBALS['mock_create_wordpress_post_calls'] = [];
    }

    public function tearDown(): void {
        // Clean up any transients that might have been set for the test user.
        delete_transient( $this->transient_key );
        delete_transient( 'telegram_post_100' );   // Legacy, ensure cleaned if used elsewhere
        delete_transient( 'telegram_post_999999' ); // Legacy, ensure cleaned if used elsewhere

        // Deactivate mock for send_telegram_message
        unset($GLOBALS['mock_send_telegram_message']);
        unset($GLOBALS['mock_send_telegram_message_calls']);

        // Deactivate mock for create_wordpress_post
        unset($GLOBALS['mock_create_wordpress_post']);
        unset($GLOBALS['mock_create_wordpress_post_calls']);

        // Clean up the global server instance if we created it.
        // unset( $GLOBALS['wp_rest_server'] ); // Be cautious with this, might affect other tests if run in same process.

        parent::tearDown();
    }

    /**
     * Test for the scenario where no Telegram bot token is defined.
     *
     * @INFO: This test's ability to *actually* simulate an empty TELEGRAM_BOT_TOKEN
     * is limited because the constant is defined when the plugin loads via bootstrap,
     * using $_ENV values (likely set by phpunit.xml.dist).
     * If TELEGRAM_BOT_TOKEN is "dummy-test-token" (not empty), this test will not pass
     * the `if ( empty( TELEGRAM_BOT_TOKEN ) )` check in `handle_telegram_update`.
     * Thus, this test verifies the *logic* that *would* apply if the token were empty,
     * but it will likely fail in the current setup as the token is present.
     * A true test of this condition requires either modifying the plugin to make the token
     * check more flexible or using advanced constant manipulation tools.
     */
    public function test_handle_telegram_update_no_token_logic() {
        // If we could force TELEGRAM_BOT_TOKEN to be empty for this test:
        // $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        // $response = handle_telegram_update( $request );
        // $this->assertInstanceOf( WP_REST_Response::class, $response );
        // $this->assertEquals( 400, $response->get_status() );
        // $this->assertEquals( 'Bot token missing', $response->get_data()['message'] );

        // For now, this test serves as a placeholder to acknowledge the challenge.
        // It will likely fail or test a different path because TELEGRAM_BOT_TOKEN is not empty.
        if (defined('TELEGRAM_BOT_TOKEN') && empty(TELEGRAM_BOT_TOKEN)) {
            $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
            $response = handle_telegram_update( $request );
            $this->assertInstanceOf( WP_REST_Response::class, $response );
            $this->assertEquals( 400, $response->get_status() );
            $this->assertEquals( 'Bot token missing', $response->get_data()['message'] );
        } else {
            $this->markTestSkipped(
                'Skipping test_handle_telegram_update_no_token_logic: TELEGRAM_BOT_TOKEN is not empty. ' .
                'This highlights the difficulty in testing the "empty token from definition" scenario without plugin modification or advanced constant manipulation. ' .
                'Current token: "' . (defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : 'NOT DEFINED') . '"'
            );
        }
    }

    public function test_handle_telegram_update_unauthorized_user() {
        // Assumes TELEGRAM_BOT_TOKEN is "dummy-test-token" (non-empty)
        // Assumes TELEGRAM_AUTHORIZED_USERS is "12345,999999" (from phpunit.xml.dist)
        // This test uses a user ID NOT in the authorized list.
        $unauthorized_user_id = 67890;

        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],   // Chat ID for sending response
                'from' => [ 'id' => $unauthorized_user_id ], // This user is not authorized
                'text' => '/start'
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 403, $response->get_status() );
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals( 'Unauthorized user', $data['message'] );

        // Check that send_telegram_message was called with the "Unauthorized user" message
        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]); // chat_id
        $this->assertEquals('Unauthorized user.', $last_call[1]); // message
    }

    public function test_handle_telegram_update_no_message_in_request() {
        // Assumes TELEGRAM_BOT_TOKEN is "dummy-test-token" (non-empty)

        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        // Intentionally not setting body_params to simulate no 'message'
        $request->set_body_params( [] );


        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 400, $response->get_status() );
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals( 'No message received', $data['message'] );
    }

    public function test_handle_telegram_update_start_command() {
        // User $this->test_user_id (12345) is authorized via phpunit.xml.dist
        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => '/start'
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( ['message' => 'Session started. Send /post to create a new post or /endsession to stop.'], $response->get_data() );

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]); // chat_id
        $this->assertStringContainsString('Welcome! Use /post to start creating a new post.', $last_call[1]); // message
        $this->assertArrayHasKey('reply_markup', $last_call[2]);
        $this->assertArrayHasKey('keyboard', $last_call[2]['reply_markup']);
    }

    public function test_handle_telegram_update_endsession_command() {
        // User $this->test_user_id (12345) is authorized
        set_transient( 'telegram_post_' . $this->test_user_id, ['title' => 'some title'], HOUR_IN_SECONDS );

        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => '/endsession'
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertFalse( get_transient( 'telegram_post_' . $this->test_user_id ) );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( ['message' => 'Session ended.'], $response->get_data() );

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertEquals('Session ended. All stored data for this post has been cleared.', $last_call[1]);
    }

    public function test_handle_telegram_update_post_command_receives_title() {
        // User $this->test_user_id (12345) is authorized

        // 1. Send /post command
        $request_post = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request_post->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => '/post'
            ]
        ] );

        $response_post = handle_telegram_update( $request_post );

        $this->assertInstanceOf( WP_REST_Response::class, $response_post );
        $this->assertEquals( 200, $response_post->get_status() );
        // Expect no specific message in response data for /post itself if it just prompts for title via Telegram
        
        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call_post = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call_post[0]);
        $this->assertEquals('Send the post title.', $last_call_post[1]);

        // 2. Send title
        $post_title = 'My Awesome Post';
        $request_title = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request_title->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => $post_title
            ]
        ] );

        $response_title = handle_telegram_update( $request_title );
        $this->assertInstanceOf( WP_REST_Response::class, $response_title );
        $this->assertEquals( 200, $response_title->get_status() );
        // Expect no specific message in response data for title submission if it just prompts for tags via Telegram

        $transient_data = get_transient( 'telegram_post_' . $this->test_user_id );
        $this->assertIsArray($transient_data);
        $this->assertEquals( $post_title, $transient_data['title'] );
        $this->assertEquals( 'awaiting_tags', $transient_data['state'] );


        $this->assertCount(2, $GLOBALS['mock_send_telegram_message_calls']); // One for /post, one for title
        $last_call_title = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call_title[0]);
        $this->assertEquals('Title saved. Now, send tags (comma-separated) or /notags if you prefer.', $last_call_title[1]);
    }

    public function test_handle_telegram_update_post_flow_receives_tags() {
        $initial_data = ['title' => 'Test Title', 'state' => 'awaiting_tags'];
        set_transient($this->transient_key, $initial_data, HOUR_IN_SECONDS);

        $tags_string = "tag1, tag2, tag3";
        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => $tags_string
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertEquals('Tags saved. Now, send a category.', $last_call[1]);

        $transient_data = get_transient($this->transient_key);
        $this->assertIsArray($transient_data);
        $this->assertEquals('Test Title', $transient_data['title']);
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $transient_data['tags']);
        $this->assertEquals('awaiting_category', $transient_data['state']);
    }

    public function test_handle_telegram_update_post_flow_receives_category() {
        $initial_data = [
            'title' => 'Test Title',
            'tags' => ['tag1', 'tag2'],
            'state' => 'awaiting_category'
        ];
        set_transient($this->transient_key, $initial_data, HOUR_IN_SECONDS);

        $category_name = "My Test Category";
        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => $category_name
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertEquals('Category saved. Now, send the content.', $last_call[1]);

        $transient_data = get_transient($this->transient_key);
        $this->assertIsArray($transient_data);
        $this->assertEquals($category_name, $transient_data['category']);
        $this->assertEquals('awaiting_content', $transient_data['state']);
    }

    public function test_handle_telegram_update_post_flow_receives_content() {
        $initial_data = [
            'title' => 'Test Title',
            'tags' => ['tag1', 'tag2'],
            'category' => 'My Test Category',
            'state' => 'awaiting_content'
        ];
        set_transient($this->transient_key, $initial_data, HOUR_IN_SECONDS);

        $content = "This is the amazing content of my post.";
        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => $content
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertEquals('Content saved. Type publish to submit your post, or /cancel to discard.', $last_call[1]);

        $transient_data = get_transient($this->transient_key);
        $this->assertIsArray($transient_data);
        $this->assertEquals($content, $transient_data['content']);
        $this->assertEquals('awaiting_publish_confirmation', $transient_data['state']);
    }

    public function test_handle_telegram_update_post_flow_publishes_post() {
        $post_data = [
            'title' => 'Final Post Title',
            'tags' => ['final', 'tags'],
            'category' => 'Final Category',
            'content' => 'This is the final post content.',
            'state' => 'awaiting_publish_confirmation'
        ];
        set_transient($this->transient_key, $post_data, HOUR_IN_SECONDS);

        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => 'publish' // Case-insensitive check is in plugin
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( ['message' => 'Post submitted and session ended. Post ID: 12345'], $response->get_data() );


        $this->assertNotEmpty($GLOBALS['mock_create_wordpress_post_calls']);
        $last_wp_post_call = end($GLOBALS['mock_create_wordpress_post_calls']);
        $this->assertEquals($post_data, $last_wp_post_call[0]); // Submitted data
        $this->assertEquals($this->test_chat_id, $last_wp_post_call[1]); // Chat ID

        $this->assertFalse(get_transient($this->transient_key), 'Transient should be deleted after publishing.');

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_send_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_send_call[0]);
        $this->assertStringContainsString('Post "Final Post Title" submitted successfully! Post ID: 12345', $last_send_call[1]);
    }

    public function test_handle_telegram_update_post_flow_invalid_command_mid_publish() {
        $post_data = [
            'title' => 'Almost Ready Post',
            'tags' => ['almost', 'there'],
            'category' => 'Almost Category',
            'content' => 'This post is almost ready.',
            'state' => 'awaiting_publish_confirmation'
        ];
        set_transient($this->transient_key, $post_data, HOUR_IN_SECONDS);

        $request = new WP_REST_Request( 'POST', '/telegram/v1/webhook/' );
        $request->set_body_params( [
            'message' => [
                'chat' => [ 'id' => $this->test_chat_id ],
                'from' => [ 'id' => $this->test_user_id ],
                'text' => 'random text not publish'
            ]
        ] );

        $response = handle_telegram_update( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );
        // No specific response data for invalid command, handled by Telegram message

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertEquals('Invalid command. Type publish to submit your post, or /cancel to discard.', $last_call[1]);

        $transient_data = get_transient($this->transient_key);
        $this->assertEquals($post_data, $transient_data, 'Transient data should remain unchanged after invalid command.');
    }

}
