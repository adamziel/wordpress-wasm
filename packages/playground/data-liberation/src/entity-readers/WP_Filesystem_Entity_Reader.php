<?php

class WP_Filesystem_Entity_Reader extends WP_Entity_Reader {

    private $filesystem;
    private $post_tree;
    private $entities = array();
    private $current_entity;
    private $post_type;
    private $finished = false;

	public function __construct( $filesystem, $options = array() ) {
		$this->filesystem = $filesystem;
        $this->post_type = $options['post_type'] ?? 'page';
        $this->post_tree = WP_Filesystem_To_Post_Tree::create(
            $this->filesystem,
            array (
                'first_post_id' => 2,
                'filter_pattern' => '#\.(?:md|html|xhtml)$#',
                'index_file_pattern' => '#^index\.[a-z]+$#',
            )
        );
	}

    public function get_last_error(): ?string {
        // @TODO: Implement this.
        return null;
    }

    public function get_entity() {
        return $this->current_entity;
    }

    public function is_finished(): bool {
        return $this->finished;
    }

    public function next_entity(): bool {
        while(true) {
            while(count($this->entities) > 0) {
                $this->current_entity = array_shift( $this->entities );
                return true;
            }

            if( ! $this->post_tree->next_node() ) {
                $this->finished = true;
                return false;
            }

            $source_content_converter = null;
            $post_tree_node = $this->post_tree->get_current_node();
            if($post_tree_node['type'] === 'file') {
                $content = $this->filesystem->read_file($post_tree_node['local_file_path']);
                $extension = pathinfo($post_tree_node['local_file_path'], PATHINFO_EXTENSION);
                switch($extension) {
                    case 'md':
                        $converter = new WP_Markdown_To_Blocks( $content );
                        $source_content_converter = 'md';
                        break;
                    case 'xhtml':
                        $converter = new WP_HTML_To_Blocks( WP_XML_Processor::create_from_string( $content ) );
                        $source_content_converter = 'xhtml';
                        break;
                    case 'html':
                    default:
                        $converter = new WP_HTML_To_Blocks( WP_HTML_Processor::create_fragment( $content ) );
                        $source_content_converter = 'html';
                        break;
                }

                if( false === $converter->convert() ) {
                    throw new Exception('Failed to convert Markdown to blocks');
                }
                $markup = $converter->get_block_markup();
                $metadata = $converter->get_all_metadata();
            } else {
                $markup = '';
                $metadata = array();
                // @TODO: Accept an option to set what should we default to.
                $source_content_converter = 'html';
            }

            $reader = new WP_Block_Markup_Entity_Reader(
                $markup,
                $metadata,
                $post_tree_node['post_id']
            );
            while($reader->next_entity()) {
                $entity = $reader->get_entity();
                $data = $entity->get_data();
                if( $entity->get_type() === 'post' ) {
                    $data['id'] = $post_tree_node['post_id'];
                    $data['guid'] = $post_tree_node['local_file_path'];
                    $data['post_parent'] = $post_tree_node['parent_id'];
                    $data['post_title'] = $data['post_title'] ?? null;
                    $data['post_status'] = 'publish';
                    $data['post_type'] = $this->post_type;
                    if ( ! $data['post_title'] ) {
                        $data['post_title'] = WP_Import_Utils::slug_to_title( basename( $post_tree_node['local_file_path'] ) );
                    }
                    $entity = new WP_Imported_Entity( $entity->get_type(), $data );
                }
                $this->entities[] = $entity;
            }
        
            // Also emit:
            $additional_meta = array(
                'local_file_path' => $post_tree_node['local_file_path'],
                'source_type' => $post_tree_node['type'],
                'source_content_converter' => $source_content_converter,
            );
            foreach($additional_meta as $key => $value) {
                $this->entities[] = new WP_Imported_Entity(
                    'post_meta',
                    array(
                        'post_id' => $post_tree_node['post_id'],
                        'key' => $key,
                        'value' => $value,
                    )
                );
            }
        }
    }
}
