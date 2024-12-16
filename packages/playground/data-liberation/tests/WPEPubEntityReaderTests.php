<?php

use PHPUnit\Framework\TestCase;

class WPEPubEntityReaderTests extends TestCase {

    public function test_entity_reader() {
        $zip = new \WordPress\Zip\WP_Zip_Filesystem( 
            \WordPress\ByteReader\WP_File_Reader::create( __DIR__ . '/fixtures/epub-entity-reader/childrens-literature.epub' ) 
        );
        $reader = new \WP_EPub_Entity_Reader( $zip, 1 );
        $entities = [];
        while ( $reader->next_entity() ) {
            $data = $reader->get_entity()->get_data();
            if(isset($data['content'])) {
                $data['content'] = $this->normalize_markup( $data['content'] );
            }
            $entities[] = [
                'type' => $reader->get_entity()->get_type(),
                'data' => $data,
            ];
        }
        $this->assertEquals( 3, count($entities) );
        $this->assertGreaterThan( 1000, strlen($entities[0]['data']['content']) );
    }

    private function normalize_markup( $markup ) {
        $processor = new WP_HTML_Processor( $markup );
        $serialized = $processor->serialize();
        if(str_ends_with($serialized, "</body></html>")) {
            $serialized = substr($serialized, 0, strlen("</body></html>"));
        }
        return $serialized;
    }

}
