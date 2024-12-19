<?php

use PHPUnit\Framework\TestCase;

class WPDirectoryTreeEntityReaderTests extends TestCase {

    public function test_with_create_index_pages_true() {
        $reader = WP_Directory_Tree_Entity_Reader::create(
            new WordPress\Filesystem\WP_Filesystem(),
            [
                'root_dir' => __DIR__ . '/fixtures/directory-tree-entity-reader',
                'first_post_id' => 2,
                'create_index_pages' => true,
                'allowed_extensions' => ['html'],
                'index_file_patterns' => ['#root.html#'],
                'markup_converter_factory' => function($markup) {
                    return new WP_HTML_To_Blocks( WP_HTML_Processor::create_fragment( $markup ) );
                },
            ]
        );
        $entities = [];
        while ( $reader->next_entity() ) {
            $entities[] = $reader->get_entity();
        }
        $this->assertCount(3, $entities);

        // The root index page
        $this->assertEquals(2, $entities[0]->get_data()['post_id']);
        $this->assertEquals('Root', $entities[0]->get_data()['post_title']);
        $this->assertEquals(null, $entities[0]->get_data()['post_parent']);

        $this->assertEquals(3, $entities[1]->get_data()['post_id']);
        $this->assertEquals('Nested', $entities[1]->get_data()['post_title']);
        $this->assertEquals(2, $entities[1]->get_data()['post_parent']);

        $this->assertEquals(4, $entities[2]->get_data()['post_id']);
        $this->assertEquals('Page 1', $entities[2]->get_data()['post_title']);
        $this->assertEquals(3, $entities[2]->get_data()['post_parent']);
    }

    public function test_with_create_index_pages_false() {
        $reader = WP_Directory_Tree_Entity_Reader::create(
            new WordPress\Filesystem\WP_Filesystem(),
            [
                'root_dir' => __DIR__ . '/fixtures/directory-tree-entity-reader',
                'first_post_id' => 2,
                'create_index_pages' => false,
                'allowed_extensions' => ['html'],
                'index_file_patterns' => ['#root.html#'],
                'markup_converter_factory' => function($markup) {
                    return new WP_HTML_To_Blocks( WP_HTML_Processor::create_fragment( $markup ) );
                },
            ]
        );
        $entities = [];
        while ( $reader->next_entity() ) {
            $entities[] = $reader->get_entity();
        }
        $this->assertCount(2, $entities);

        // The root page
        $this->assertEquals(2, $entities[0]->get_data()['post_id']);
        $this->assertEquals('Root', $entities[0]->get_data()['post_title']);
        $this->assertEquals(null, $entities[0]->get_data()['post_parent']);

        // The nested page
        $this->assertEquals(3, $entities[1]->get_data()['post_id']);
        $this->assertEquals('Page 1', $entities[1]->get_data()['post_title']);
        $this->assertEquals(2, $entities[1]->get_data()['post_parent']);
    }
}
