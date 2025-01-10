<?php

use PHPUnit\Framework\TestCase;

class WPFilesystemToPostTreeTests extends TestCase {

    public function test_with_create_index_pages_true() {
        $reader = WP_Filesystem_To_Post_Tree::create(
            new WordPress\Filesystem\WP_Local_Filesystem( __DIR__ . '/fixtures/filesystem-entity-reader' ),
            [
                'first_post_id' => 2,
                'create_index_pages' => true,
                'filter_pattern' => '#\.html$#',
                'index_file_pattern' => '#root.html#',
            ]
        );
        $posts = [];
        while ( $reader->next_node() ) {
            $posts[] = $reader->get_current_node();
        }
        $this->assertCount(3, $posts);

        // The root index page
        // Root index page
        $this->assertEquals(2, $posts[0]['post_id']);
        $this->assertNull($posts[0]['parent_id']);
        $this->assertEquals('file', $posts[0]['type']);

        // Nested directory page
        $this->assertEquals(3, $posts[1]['post_id']); 
        $this->assertEquals(2, $posts[1]['parent_id']);
        $this->assertEquals('file_placeholder', $posts[1]['type']);

        // Leaf page
        $this->assertEquals(4, $posts[2]['post_id']);
        $this->assertEquals(3, $posts[2]['parent_id']); 
        $this->assertEquals('file', $posts[2]['type']);
    }

}
