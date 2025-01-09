<?php

function wp_git_get_all_descendant_oids_in_tree( WP_Git_Repository $repository, $tree_oid ) {
	if ( false === $repository->read_object( $tree_oid ) ) {
		return false;
	}
	$oids  = array( $tree_oid );
	$trees = array( $tree_oid );

	while ( ! empty( $trees ) ) {
		$tree_hash = array_pop( $trees );
		if ( ! $repository->read_object( $tree_hash ) ) {
			_doing_it_wrong( 'wp_git_get_all_descendant_oids_in_tree', 'Failed to read object: ' . $tree_hash, '1.0.0' );
			return false;
		}
		$tree = $repository->get_parsed_tree();
		foreach ( $tree as $object ) {
			$oids[] = $object['sha1'];
			if ( $object['mode'] === WP_Git_Pack_Processor::FILE_MODE_DIRECTORY ) {
				$trees[] = $object['sha1'];
			}
		}
	}
	return $oids;
}

function wp_git_get_parsed_commit( WP_Git_Repository $repository, $commit_oid ) {
	if ( false === $repository->read_object( $commit_oid ) ) {
		_doing_it_wrong( 'wp_git_get_parsed_commit', 'Failed to read object: ' . $commit_oid, '1.0.0' );
		return false;
	}
	if ( $repository->get_type() !== WP_Git_Pack_Processor::OBJECT_TYPE_COMMIT ) {
		_doing_it_wrong( 'wp_git_get_parsed_commit', 'Object was not a commit in find_objects_added_in: ' . $repository->get_type(), '1.0.0' );
		return false;
	}
	return $repository->get_parsed_commit();
}

function wp_git_is_null_oid( $oid ) {
	return $oid === null || $oid === WP_Git_Repository::NULL_OID;
}
