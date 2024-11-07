<?php

class WP_Imported_Entity {

    const TYPE_POST = 'post';
    const TYPE_POST_META = 'post_meta';
    const TYPE_COMMENT = 'comment';
    const TYPE_COMMENT_META = 'comment_meta';
    const TYPE_TERM = 'term';
    const TYPE_TAG = 'tag';
    const TYPE_CATEGORY = 'category';
    const TYPE_USER = 'user';
    const TYPE_SITE_OPTION = 'site_option';

    private $type;
    private $data;

    public function __construct($type, $data) {
        $this->type = $type;
        $this->data = $data;
    }

    public function get_type() {
        return $this->type;
    }

    public function get_data() {
        return $this->data;
    }
}