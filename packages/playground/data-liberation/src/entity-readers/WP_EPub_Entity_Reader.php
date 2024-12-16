<?php

use WordPress\Zip\WP_Zip_Filesystem;

/**
 * https://www.w3.org/AudioVideo/ebook/
 * 
 * An EPUB Publication is transported as a single file (a "portable document") that contains:
 * * a Package Document (OPF file) which specifies all the Publication's constituent content documents and their required resources, defines a reading order  and associates Publication-level metadata and navigation information.
 *    * A metadata element including and/or referencing metadata applicable to the entire Publication and particular resources within it.
 *    * A manifest element: identifies (via IRI) and describes (via MIME media type) the set of resources that constitute the EPUB Publication.
 *    * A spine element : defines the default reading order of the Publication. (An ordered list of Publication Resources (EPUB Content Documents).
 *    * A Bindings element defines a set of custom handlers for media types not supported by EPUB3. If the Reading System cannot support the specific media type, it could use scripting fallback if supported.
 * * all Content Documents
 * * all other required resources for processing the Publication.
 * 
 * The OCF Container is packaged into a physical single ZIP file containing:
 * * Mime Type file: application/epub+zip.
 * * META-INF folder (container file which points to the location of the .opf file), signatures, encryption, rights, are xml files
 * * OEBPS folder stores the book content .(opf, ncx, html, svg, png, css, etc. files) 
 */
class WP_EPub_Entity_Reader extends WP_Entity_Reader {

    protected $zip;
    protected $finished = false;
    protected $current_post_id;
    protected $remaining_html_files;
    protected $current_html_reader;

    public function __construct( WP_Zip_Filesystem $zip, $first_post_id = 1 ) {
        $this->zip = $zip;
        $this->current_post_id = $first_post_id;
    }

    public function next_entity() {
        // If we're finished, we're finished.
        if( $this->finished ) {
            return false;
        }

        if( null === $this->remaining_html_files ) {
            $path_found = false;
            foreach( ['/OEBPS', '/EPUB'] as $path ) {
                if( $this->zip->is_dir( $path ) ) {
                    $path_found = true;
                    break;
                }
            }
            if( ! $path_found ) {
                _doing_it_wrong(
                    __METHOD__,
                    'The EPUB file did not contain any HTML files.',
                    '1.0.0'
                );
                $this->finished = true;
                return false;
            }
            $files = $this->zip->ls( $path );
            if( false === $files ) {
                _doing_it_wrong(
                    __METHOD__,
                    'The EPUB file did not contain any HTML files.',
                    '1.0.0'
                );
                $this->finished = true;
                return false;
            }
            $this->remaining_html_files = [];
            foreach( $files as $file ) {
                if( str_ends_with( $file, '.xhtml' ) || str_ends_with( $file, '.html' ) ) {
                    $this->remaining_html_files[] = $path . '/' . $file;
                }
            }
        }

        while( true ) {
            if ( null !== $this->current_html_reader ) {
                if(
                    ! $this->current_html_reader->is_finished() && 
                    $this->current_html_reader->next_entity()
                ) {
                    return true;
                }
                if($this->current_html_reader->get_last_error()) {
                    var_dump('The EPUB file did not contain any HTML files.');
                    _doing_it_wrong(
                        __METHOD__,
                        'The EPUB file did not contain any HTML files.',
                        '1.0.0'
                    );
                    $this->finished = true;
                    return false;
                }
            }

            if( count( $this->remaining_html_files ) === 0 ) {
                $this->finished = true;
                return false;
            }
    
            $html_file = array_shift( $this->remaining_html_files );
            $html = $this->zip->read_file( $html_file );
            $this->current_html_reader = new WP_HTML_Entity_Reader(
                $html,
                $this->current_post_id
            );
            $this->current_post_id++;
        }

        return false;
    }

    public function get_entity() {
        return $this->current_html_reader->get_entity();
    }

    public function is_finished(): bool {
        return $this->finished;
    }

    public function get_last_error(): ?string {
        return null;
    }

}
