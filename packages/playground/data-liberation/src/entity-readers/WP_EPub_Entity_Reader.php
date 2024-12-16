<?php

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
 * * OEBPS folder stores the book content .(opf, ncx, html, svg, png, css, etc. files) 
 */
// class WP_EPub_Entity_Reader extends WP_Entity_Reader {



// }
