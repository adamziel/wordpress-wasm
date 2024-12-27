<?php
/**
 * Push a brand-new branch "my-playground" with one file "playground.txt"
 * containing "WordPress Playground is cool", over Git’s HTTP smart protocol.
 */

require_once __DIR__ . '/../data-liberation/bootstrap.php';
require_once __DIR__ . '/secrets.php';

function deflate_raw(string $content): string {
    $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
    return deflate_add($context, $content, ZLIB_FINISH);
}

class WP_Git_Pack_Encoder {

    // Helper: Build a barebones pack with the given objects (no compression).
    // Objects must be in an order that satisfies dependencies if you skip deltas.
    static public function build_pack(array $objects): string {
        // PACK header: 4-byte signature, 4-byte version=2, 4-byte object count
        $pack = "PACK";
        $pack .= pack("N", 2);              // version
        $pack .= pack("N", count($objects)); // number of objects
    
        foreach ($objects as $obj) {
            if(
                $obj['type'] === WP_Git_Pack_Index::OBJECT_TYPE_TREE &&
                is_array($obj['content'])
            ) {
                $obj['content'] = WP_Git_Pack_Encoder::encode_tree_bytes($obj['content']);
            }
            $pack .= self::object_header($obj['type'], strlen($obj['content']));
            $pack .= deflate_raw($obj['content']);
        }
    
        // Then append a 20-byte trailing pack checksum (SHA1 of all preceding bytes).
        $packSha = sha1($pack, true);
        return $pack . $packSha;
    }

    static public function object_header(int $type, int $size): string {
        // First byte: type in bits 4-6, size bits 0-3
        $firstByte = $size & 0b1111;
        $firstByte |= ($type & 0b111) << 4;
        // Continuation bit 7 if needed
        if($size > 15 ) {
            $firstByte |= 0b10000000;
        }
        
        // Get remaining size bits after first 4 bits
        $remainingSize = $size >> 4;
        
        // Build result starting with first byte
        $result = chr($firstByte);
        // Add continuation bytes if needed
        while ($remainingSize > 0) {
            // Set continuation bit if we have more bytes
            $byte = $remainingSize & 0b01111111;
            $remainingSize >>= 7;
            if($remainingSize > 0) {
                $byte |= 0b10000000;
            }
            
            $result .= chr($byte);
        }
        
        return $result;
    }

    /**
     * @param $tree array{
     *     array {
     *         $mode: string,
     *         $name: string,
     *         $sha1: string,
     *     }
     * }
     */
    static public function encode_tree_bytes($tree) {
        $tree_bytes = '';
        foreach ($tree as $value) {
            $tree_bytes .= $value['mode'] . " " . $value['name'] . "\0" . hex2bin($value['sha1']);
        }
        return $tree_bytes;
    }

    static public function create_object($basic_data) {
        $object = [
            'ofs' => 0,
            'type' => $basic_data['type'],
            'reference' => null,
            'header_offset' => 0,
        ];
        switch($basic_data['type']) {
            case WP_Git_Pack_Index::OBJECT_TYPE_BLOB:
                $object['content'] = $basic_data['content'];
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $basic_data['content']));
                break;
            case WP_Git_Pack_Index::OBJECT_TYPE_TREE:
                $object['content'] = [];
                foreach($basic_data['content'] as $value) {
                    $object['content'][$value['name']] = $value;
                }
                ksort($object['content']);
                $encoded_bytes = self::encode_tree_bytes($object['content']);
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $encoded_bytes));
                break;
            case WP_Git_Pack_Index::OBJECT_TYPE_COMMIT:
                $object['content'] = $basic_data['content'];
                $object['tree'] = $basic_data['tree'];
                $object['oid'] = sha1(self::wrap_object($basic_data['type'], $basic_data['content']));
                break;
        }
        return $object;
    }

    static public function wrap_object($type, $object) {
        $length = strlen($object);
        $type_name = WP_Git_Pack_Index::OBJECT_NAMES[$type];
        return "$type_name $length\x00" . $object;
    }

    // Helper: encode a single pkt-line
    static public function encode_packet_line(string $payload): string {
        $length = strlen($payload) + 4;
        return sprintf("%04x", $length) . $payload;
    }
    
    // Helper: produce a flush packet (0000)
    static public function encode_flush(): string {
        return "0000";
    }
    
}

// Very naive GET
function httpGet(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return null;
    }
    return $result;
}

// Very naive POST
function httpPost(string $url, string $data, array $headers = []): ?string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $hdr = [];
    foreach ($headers as $h) {
        $hdr[] = $h;
    }
    // We also need "Git-Protocol: version=2" for some servers, or not, depending on the server
    // $hdr[] = 'Git-Protocol: version=2';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);

    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return null;
    }
    return $result;
}


/**
 * Represents a set of changes to be applied to a data store.
 */
class WP_Changeset {
	/**
	 * Created files.
     * @var array<string, string>
	 */
	public $create;

	/**
	 * Updated files.
	 * @var array<string, string>
	 */
	public $update;

	/**
	 * Deleted files.
	 * @var array<string>
	 */
	public $delete;

    public function __construct($create = [], $update = [], $delete = []) {
        $this->create = $create;
        $this->update = $update;
        $this->delete = $delete;
    }
};

class WP_Git_Utils {

    private const DELETE_PLACEHOLDER = 'DELETE_PLACEHOLDER';

    /**
     * Computes Git objects needed to commit a changeset.
     * 
     * @param WP_Git_Pack_Index $oldIndex The index containing existing objects
     * @param WP_Changeset $changeset The changes to commit
     * @return string The Git objects with type, content and SHA
     */
    public function compute_push_objects(
        WP_Git_Pack_Index $index,
        WP_Changeset $changeset,
        $branchName,
        $parent_hash = null
    ) {
        $new_index = [];

        $new_tree = new stdClass();
        foreach (array_merge($changeset->create, $changeset->update) as $path => $content) {
            $new_blob = WP_Git_Pack_Encoder::create_object([
                'type' => WP_Git_Pack_Index::OBJECT_TYPE_BLOB,
                'content' => $content,
            ]);
            $new_index[] = $new_blob;
            $this->set_oid($new_tree, $path, $new_blob['oid']);
        }
        
        foreach ($changeset->delete as $path) {
            $this->set_oid($new_tree, $path, self::DELETE_PLACEHOLDER);
        }

        if(!$parent_hash) {
            $parent_hash = "0000000000000000000000000000000000000000";
        }
        $parent = '';
        if($parent_hash) {
            $parent = "parent $parent_hash\n";
        }
        $root_tree = $this->backfill_trees($index, $new_index, $new_tree, '/');
        $commit = WP_Git_Pack_Encoder::create_object([
            'type' => WP_Git_Pack_Index::OBJECT_TYPE_COMMIT,
            'content' => sprintf(
                "tree %s\n{$parent}author %s\ncommitter %s\n\n%s\n",
                $root_tree['oid'],
                "John Doe <john@example.com> " . time() . " +0000",
                "John Doe <john@example.com> " . time() . " +0000",
                "Hello!"
            ),
            'tree' => $root_tree['oid'],
        ]);
        $commitSha = $commit['oid'];
        $new_index[] = $commit;
        // Make $new_index unique by 'oid' column
        $seen_oids = [];
        $new_index = array_filter($new_index, function($obj) use (&$seen_oids) {
            if (isset($seen_oids[$obj['oid']])) {
                return false;
            }
            $seen_oids[$obj['oid']] = true;
            return true;
        });
        // $new_index = array_reverse($new_index);

        var_dump($new_index);

        $pktPush = WP_Git_Pack_Encoder::encode_packet_line("$parent_hash $commitSha refs/heads/$branchName\0report-status force-update\n");
        $pktPush .= WP_Git_Pack_Encoder::encode_flush(); // "0000"
        $pktPush .= WP_Git_Pack_Encoder::build_pack($new_index);
        $pktPush .= WP_Git_Pack_Encoder::encode_flush(); // "0000"
    
        return $pktPush;
    }

    private function backfill_trees(WP_Git_Pack_Index $current_index, &$new_index, $subtree_delta, $subtree_path = '/') {
        $subtree_path = ltrim($subtree_path, '/');
        $new_tree_content = [];

        $indexed_tree = $current_index->get_by_path($subtree_path);
        if($indexed_tree) {
            foreach($indexed_tree['content'] as $object) {
                // Backfill the unchanged objects from the currently indexed subtree.
                $name = $object['name'];
                if(!isset($subtree_delta->children[$name])) {
                    $new_tree_content[$name] = $object;
                }
            }
        }

        // Index changed and new objects in the current subtree.
        foreach($subtree_delta->children as $name => $subtree_child) {
            // Ignore any deleted objects.
            if(isset($subtree_child->oid) && $subtree_child->oid === self::DELETE_PLACEHOLDER) {
                continue;
            }

            // Index blobs
            switch($subtree_child->type) {
                case WP_Git_Pack_Index::OBJECT_TYPE_BLOB:
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
                        'name' => $name,
                        'sha1' => $subtree_child->oid,
                    ];
                    break;
                case WP_Git_Pack_Index::OBJECT_TYPE_TREE:
                    $subtree_object = $this->backfill_trees($current_index, $new_index, $subtree_child, $subtree_path . '/' . $name);
                    $new_tree_content[$name] = [
                        'mode' => WP_Git_Pack_Index::FILE_MODE_DIRECTORY,
                        'name' => $name,
                        'sha1' => $subtree_object['oid'],
                    ];
                    break;
            }
        }

        $new_tree_object = WP_Git_Pack_Encoder::create_object([
            'type' => WP_Git_Pack_Index::OBJECT_TYPE_TREE,
            'content' => $new_tree_content,
        ]);

        $new_index[] = $new_tree_object;
        return $new_tree_object;
    }

    private function set_oid($root_tree, $path, $oid) {
        $blob = new stdClass();
        $blob->type = WP_Git_Pack_Index::OBJECT_TYPE_BLOB;
        $blob->oid = $oid;

        $subtree_path = dirname($path);
        if($subtree_path === '.') {
            $subtree = $root_tree;
        } else {
            $subtree = $this->get_subtree($root_tree, $subtree_path);
        }
        $filename = basename($path);
        $subtree->children[$filename] = $blob;
    }

    private function get_subtree($root_tree, $path) {
        $path = trim($path, '/');
        $segments = explode('/', $path);
        $subtree = $root_tree;
        foreach ($segments as $segment) {
            if (!isset($subtree->children[$segment])) {
                $new_subtree = new stdClass();
                $new_subtree->type = WP_Git_Pack_Index::OBJECT_TYPE_TREE;
                $new_subtree->children = [];
                $subtree->children[$segment] = $new_subtree;
            }
            $subtree = $subtree->children[$segment];
        }
        return $subtree;
    }

}

$client = new WP_Git_Client("https://github.com/adamziel/pantheon-playground-demo.git");
$head_hash = $client->fetchRefs('HEAD')['HEAD'];
$index = $client->list_objects($head_hash);

$utils = new WP_Git_Utils();
$push_bytes = $utils->compute_push_objects(
    $index,
    new WP_Changeset(
        [
            // Weird! This only works if these paths are sorted alphabetically. Do I have to sort pack data in a particular order?
            // Perhaps topological sort matters?
            // Also, the content of each file must be differnet. I suspect Github rejects duplicate blobs?
            'wp-content/plugin/light-mode-another-two.css' => '/* Light uu mode */',
            // 'wp-content/dark-mode.css' => '/* Dark mode */',
            // 'wp-admin/tzeme/dark-mode3.css' => '/* Dark mode xx */',
            // 'wp-content/azeme/dark-mode4.css' => '/* Dark mode xxx */',
            // 'wp-content/ztra/dark-mode.css' => '/* differddent */',
        ],
        [],
        [
            'wp-content/dark-mode.css',
            '0',
            '1'
        ]
    ),
    'main',
    $head_hash
);

// $pack_data = substr($push_bytes, strpos($push_bytes, "PACK"));
// $new_index = WP_Git_Pack_Index::from_pack_data($pack_data);
// var_dump($new_index);

// die('......');
$repoUrl = "https://" . GITHUB_TOKEN . "@github.com/adamziel/pantheon-playground-demo.git";

// 6) POST this to "/git-receive-pack"
$url = rtrim($repoUrl, '.git').'.git/git-receive-pack';
$response = httpPost($url, $push_bytes, [
    'Content-Type: application/x-git-receive-pack-request',
    'Accept: application/x-git-receive-pack-result',
]);

// 7) Check the response. If it contains "unpack ok" and "ok refs/heads/my-playground",
//    we succeeded. Otherwise, there’s an error message in side-band or progress 
//    channels. We’ll just echo it here for debugging.
echo "Push response:\n$response\n";
die();

// -----------------------------------------------------------------------------
// Example usage
try {
    // Use authorization header instead of basic auth in URL for better security
    pushSingleFileOverHttp(
        "https://" . GITHUB_TOKEN . "@github.com/adamziel/pantheon-playground-demo.git",
        "my-playground",
        // Use 40 zeros to create a new branch
        // "0000000000000000000000000000000000000000"
        "b7a8ab2605e6d7fbec140cba3d28e94d31d2a6aa"
        // "main",
        // "a9abffe7d324a4b9343eaad3f5e4b35c584448b6"
    );
} catch (Exception $e) {
    echo "Error: ", $e->getMessage(), "\n";
}



//--------


// $readme_md = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_BLOB,
//     'content' => '## This is a Readme file',
// ]);
// $index_html = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_BLOB,
//     'content' => '<!DOCTYPE html><html><body><h1>Hello, world!</h1></body></html>',
// ]);
// $style_css = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_BLOB,
//     'content' => 'body { background-color: red; }',
// ]);

// $html_pages = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_TREE,
//     'content' => [
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'readme.md',
//             'sha1' => $readme_md['oid'],
//         ],
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'index.html',
//             'sha1' => $index_html['oid'],
//         ],
//     ],
// ]);

// $theme_tree = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_TREE,
//     'content' => [
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'style.css',
//             'sha1' => $style_css['oid'],
//         ],
//     ],
// ]);

// $wp_content_tree = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_TREE,
//     'content' => [
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'html-pages',
//             'sha1' => $html_pages['oid'],
//         ],
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'theme',
//             'sha1' => $theme_tree['oid'],
//         ],
//     ],
// ]);

// $root_tree = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_TREE,
//     'content' => [
//         [
//             'mode' => WP_Git_Pack_Index::FILE_MODE_REGULAR_NON_EXECUTABLE,
//             'name' => 'wp-content',
//             'sha1' => $wp_content_tree['oid'],
//         ],
//     ],
// ]);

// $commit = create_object([
//     'type' => WP_Git_Pack_Index::OBJECT_TYPE_COMMIT,
//     'content' => 'Hello, world!',
//     'tree' => $root_tree['oid'],
// ]);


// $push_bytes = $utils->compute_push_objects(
//     new WP_Git_Pack_Index([
//         $commit,
//         $root_tree,
//         $wp_content_tree,
//         $html_pages,
//         $theme_tree,
//         $style_css,
//         $index_html,
//         $readme_md,
//     ]),
//     new WP_Changeset(
//         [
//             'wp-content/theme/light-mode.css' => '/* Light mode */',
//             'wp-content/theme/dark-mode.css' => '/* Dark mode */',
//         ]
//     ),
//     'my-new-branch'
// );

