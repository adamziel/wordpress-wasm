<?php

use PHPUnit\Framework\TestCase;

class WPEPubEntityReaderTests extends TestCase {

    /**
     * @dataProvider epub_byte_reader_data_provider
     */
    public function test_entity_reader( $reader ) {
        $zip = new \WordPress\Zip\WP_Zip_Filesystem( $reader );
        $reader = new \WP_EPub_Entity_Reader( $zip );
        $entities = [];
        while ( $reader->next_entity() ) {
            $data = $reader->get_entity()->get_data();
            $entities[] = [
                'type' => $reader->get_entity()->get_type(),
                'data' => $data,
            ];
        }
        $this->assertNull( $reader->get_last_error() );
        $this->assertEquals( 3, count($entities) );
        $this->assertGreaterThan( 100, strlen($entities[0]['data']['content']) );
        $this->assertGreaterThan( 1000, strlen($entities[1]['data']['content']) );
        $this->assertGreaterThan( 1000, strlen($entities[2]['data']['content']) );
        echo $entities[2]['data']['content'];
    }

    public function epub_byte_reader_data_provider() {
        return [
            'Local file' => [
                \WordPress\ByteReader\WP_File_Reader::create( __DIR__ . '/fixtures/epub-entity-reader/childrens-literature.epub' )
            ],
            'Remote file' => [
                \WordPress\ByteReader\WP_Remote_File_Ranged_Reader::create( 'https://github.com/IDPF/epub3-samples/releases/download/20230704/childrens-literature.epub' )
            ],
        ];
    }

}
