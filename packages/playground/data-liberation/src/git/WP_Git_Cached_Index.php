<?php

use WordPress\Filesystem\WP_Abstract_Filesystem;

class WP_Git_Cached_Index {

    private $fs;

    public function __construct(
        WP_Abstract_Filesystem $fs
    ) {
        $this->fs = $fs;
        if(!$this->fs->is_dir('objects')) {
            $this->fs->mkdir('objects');
        }
        if(!$this->fs->is_dir('refs')) {
            $this->fs->mkdir('refs');
        }
        if(!$this->fs->is_dir('refs/heads')) {
            $this->fs->mkdir('refs/heads');
        }
    }

    /**
     * @TODO: Streaming read
     */
    public function get_object($oid) {
        $contents = $this->fs->read_file($this->get_object_path($oid));
        return WP_Git_Pack_Processor::inflate($contents);
    }

    public function set_head($head, $oid) {
        if($head !== 'HEAD' && !str_starts_with($head, 'refs/heads/')) {
            _doing_it_wrong(__METHOD__, 'Invalid head: ' . $head);
            return false;
        }
        return $this->fs->put_contents($head, $oid);
    }

    public function get_head($head='HEAD') {
        if($head === 'HEAD') {
            $head_contents = $this->fs->read_file('HEAD');
            if(strpos($head_contents, 'ref: ') !== 0) {
                return null;
            }
            $head = trim(substr($head_contents, 5));
        } else if(!str_starts_with($head, 'refs/heads/')) {
            _doing_it_wrong(__METHOD__, 'Invalid head: ' . $head);
            return false;
        }
        return trim($this->fs->read_file($head));
    }

    public function add_object($type, $content) {
        $oid = sha1(self::wrap_git_object($type, $content));
        $oid_path = $this->get_object_path($oid);
        $oid_dir = dirname($oid_path);
        if(!$this->fs->is_dir($oid_dir)) {
            $this->fs->mkdir($oid_dir, true);
        }
        $success = $this->fs->put_contents(
            $this->get_object_path($oid),
            WP_Git_Pack_Processor::deflate($content)
        );
        if(!$success) {
            return false;
        }
        return $oid;
    }

    private function get_object_path($oid) {
        return 'objects/' . $oid[0] . $oid[1] . '/' . substr($oid, 2);
    }

    static private function wrap_git_object($type, $object) {
        $length = strlen($object);
        $type_name = WP_Git_Pack_Processor::OBJECT_NAMES[$type];
        return "$type_name $length\x00" . $object;
    }

} 