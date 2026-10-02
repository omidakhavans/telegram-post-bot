<?php

use WP_UnitTestCase;

/**
 * Tests for the create_wordpress_post function.
 */
class Test_Create_Wordpress_Post extends WP_UnitTestCase {

    protected $test_chat_id;

    public function setUp(): void {
        parent::setUp();
        $this->test_chat_id = 7890;

        // Activate mock for send_telegram_message
        // This mock is defined in tests/bootstrap.php
        $GLOBALS['mock_send_telegram_message'] = true;
        $GLOBALS['mock_send_telegram_message_calls'] = [];
    }

    public function tearDown(): void {
        // Deactivate mock for send_telegram_message
        unset($GLOBALS['mock_send_telegram_message']);
        unset($GLOBALS['mock_send_telegram_message_calls']);

        parent::tearDown();
        // WP_UnitTestCase usually handles post/term cleanup,
        // but specific cleanup can be added here if necessary.
    }

    public function test_create_wordpress_post_success_new_category() {
        $data = [
            'title'    => 'Test Post New Cat ' . time(), // Add time to ensure uniqueness
            'content'  => 'Some amazing content for the new category post.',
            'tags'     => ['newTag1', 'newTag2'],
            'category' => 'A Freshly Made Category'
        ];

        $post_id = create_wordpress_post($data, $this->test_chat_id);

        $this->assertIsInt($post_id, 'Function should return an integer post ID.');
        $this->assertGreaterThan(0, $post_id, 'Post ID should be greater than 0.');

        $created_post = get_post($post_id);
        $this->assertInstanceOf(WP_Post::class, $created_post, 'Could not retrieve the created post.');
        $this->assertEquals($data['title'], $created_post->post_title);
        $this->assertEquals($data['content'], $created_post->post_content);
        $this->assertEquals('draft', $created_post->post_status);

        $category = get_term_by('name', $data['category'], 'category');
        $this->assertInstanceOf(WP_Term::class, $category, 'Category was not created.');
        $this->assertTrue(has_term($data['category'], 'category', $created_post), 'Post is not in the correct category.');

        $assigned_tags = wp_get_post_tags($post_id);
        $this->assertCount(count($data['tags']), $assigned_tags, 'Incorrect number of tags assigned.');
        $assigned_tag_names = wp_list_pluck($assigned_tags, 'name');
        foreach ($data['tags'] as $expected_tag) {
            $this->assertContains($expected_tag, $assigned_tag_names);
        }

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls'], 'send_telegram_message was not called.');
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]); // chat_id
        $this->assertStringStartsWith('Post "' . $data['title'] . '" created successfully!', $last_call[1]); // message
        $this->assertStringContainsString(get_permalink($post_id), $last_call[1]); // Check if permalink is in the message
    }

    public function test_create_wordpress_post_success_existing_category() {
        $existing_category_name = 'My Existing Test Category';
        $existing_category = self::factory()->category->create_and_get(['name' => $existing_category_name]);
        $this->assertInstanceOf(WP_Term::class, $existing_category, 'Failed to create existing category for test setup.');

        $data = [
            'title'    => 'Test Post Existing Cat ' . time(), // Add time for uniqueness
            'content'  => 'Content for the existing category post.',
            'tags'     => ['existingTag1', 'anotherTag'],
            'category' => $existing_category_name
        ];

        $post_id = create_wordpress_post($data, $this->test_chat_id);

        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id);

        $created_post = get_post($post_id);
        $this->assertInstanceOf(WP_Post::class, $created_post);
        $this->assertEquals($data['title'], $created_post->post_title);
        $this->assertEquals($data['content'], $created_post->post_content);
        $this->assertEquals('draft', $created_post->post_status);

        $this->assertTrue(has_term($existing_category->term_id, 'category', $created_post), 'Post is not in the existing category.');

        $assigned_tags = wp_get_post_tags($post_id);
        $this->assertCount(count($data['tags']), $assigned_tags);
        $assigned_tag_names = wp_list_pluck($assigned_tags, 'name');
        foreach ($data['tags'] as $expected_tag) {
            $this->assertContains($expected_tag, $assigned_tag_names);
        }

        $this->assertNotEmpty($GLOBALS['mock_send_telegram_message_calls']);
        $last_call = end($GLOBALS['mock_send_telegram_message_calls']);
        $this->assertEquals($this->test_chat_id, $last_call[0]);
        $this->assertStringStartsWith('Post "' . $data['title'] . '" created successfully!', $last_call[1]);
        $this->assertStringContainsString(get_permalink($post_id), $last_call[1]);
    }

    public function test_create_wordpress_post_error_wp_insert_term_skipped() {
        $this->markTestSkipped(
            'Skipping test for wp_insert_term failure: Mocking WordPress core function wp_insert_term is complex ' .
            'and not implemented in the current test setup. This would require a library like Patchwork or php-mock.'
        );
    }

    public function test_create_wordpress_post_error_wp_insert_post_skipped() {
        $this->markTestSkipped(
            'Skipping test for wp_insert_post failure: Mocking WordPress core function wp_insert_post is complex ' .
            'and not implemented in the current test setup. This would require a library like Patchwork or php-mock.'
        );
    }
}
