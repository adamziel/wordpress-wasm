<?php

require_once __DIR__ . "/../bootstrap.php";

if ( $argc < 2 ) {
	echo "Usage: php script.php <command> --file <input-file> --current-site-url <current site url> --new-site-url <target url>\n";
	echo "Commands:\n";
	echo "  list_urls: List all the URLs found in the input file.\n";
	echo "  migrate_urls: Migrate all the URLs found in the input file from the current site to the target site.\n";
	exit( 1 );
}

$command = $argv[1];
$options = [];

for ( $i = 2; $i < $argc; $i ++ ) {
	if ( str_starts_with( $argv[ $i ], '--' ) && isset( $argv[ $i + 1 ] ) ) {
		$options[ substr( $argv[ $i ], 2 ) ] = $argv[ $i + 1 ];
		$i ++;
	}
}

if ( ! isset( $options['file'] ) ) {
	echo "The file option is required.\n";
	exit( 1 );
}

$inputFile = $options['file'];
if ( ! file_exists( $inputFile ) ) {
	echo "The file $inputFile does not exist.\n";
	exit( 1 );
}
$block_markup = file_get_contents( $inputFile );

// @TODO: Decide – should the current site URL be always required to
//        populate $base_url?
$base_url = $options['current-site-url'] ?? 'https://playground.internal';
$p        = new WP_Block_Markup_Url_Processor( $block_markup, $base_url );

switch ( $command ) {
	case 'list_urls':
		echo "URLs found in the markup:\n\n";
		wp_list_urls_in_block_markup( [ 'block_markup' => $block_markup, 'base_url' => $base_url ]);
		echo "\n";
		break;
	case 'migrate_urls':
		if ( ! isset( $options['current-site-url'] ) ) {
			echo "The --current-site-url option is required for the migrate_urls command.\n";
			exit( 1 );
		}
		if ( ! isset( $options['new-site-url'] ) ) {
			echo "The --new-site-url option is required for the migrate_urls command.\n";
			exit( 1 );
		}

		echo "Replacing $base_url with " . $options['new-site-url'] . " in the input.\n\n";
		if (!is_dir('./assets')) {
			mkdir('./assets/', 0777, true);
		}
		$result = wp_rewrite_urls( array(
			'block_markup' => $block_markup,
			'base_url' => $base_url,
			'current-site-url' => $options['current-site-url'],
			'new-site-url' => $options['new-site-url'],
		) );
		if(!is_string($result)) {
			echo "Error! \n";
			print_r($result);
			exit( 1 );
		}
		echo $result;
		break;
}

function wp_list_urls_in_block_markup( $options ) {
	$block_markup = $options['block_markup'];
	$base_url     = $options['base_url'] ?? 'https://playground.internal';
	$p            = new WP_Block_Markup_Url_Processor( $block_markup, $base_url );
	while ( $p->next_url() ) {
		// Skip empty relative URLs.
		if ( ! trim( $p->get_raw_url() ) ) {
			continue;
		}
		echo '* ';
		switch ( $p->get_token_type() ) {
			case '#tag':
				echo 'In <' . $p->get_tag() . '> tag attribute "' . $p->get_inspected_attribute_name() . '": ';
				break;
			case '#block-comment':
				echo 'In a ' . $p->get_block_name() . ' block attribute "' . $p->get_block_attribute_key() . '": ';
				break;
			case '#text':
				echo 'In #text: ';
				break;
		}
		echo $p->get_raw_url() . "\n";
	}
}
