<?php

use PHPUnit\Framework\TestCase;
use WordPress\Filesystem\WP_Local_Filesystem;

class WPStaticFileSyncTests extends TestCase {

    private $filesystem;

    public function setUp(): void {
        $this->filesystem = new WP_Local_Filesystem(__DIR__ . '/static-files-tests/');
    }

    public function tearDown(): void {
        // $this->filesystem->rmdir('/', ['recursive' => true]);
    }

    public function test_flatten_parent_if_needed_moves_lone_file_one_level_up() {
        $this->setup_directory_tree([
            'pride-and-prejudice' => [
                'index.md' => 'Test parent',
            ],
        ]);

        $sync = new WP_Static_File_Sync($this->filesystem);
        $this->assertTrue($sync->flatten_parent_if_needed('/pride-and-prejudice/index.md'));

        $fs = $this->filesystem;
        $this->assertFalse($fs->exists('/pride-and-prejudice'));
        $this->assertTrue($fs->is_file('/pride-and-prejudice.md'));
    }

    public function test_flatten_parent_if_needed_does_not_move_file_if_parent_is_not_empty() {
        $this->setup_directory_tree([
            'pride-and-prejudice' => [
                'index.md' => 'Test parent',
                'other.md' => 'Test other',
            ],
        ]);

        $sync = new WP_Static_File_Sync($this->filesystem);
        $this->assertTrue($sync->flatten_parent_if_needed('/pride-and-prejudice/index.md'));

        $fs = $this->filesystem;
        $this->assertTrue($fs->exists('/pride-and-prejudice'));
        $this->assertTrue($fs->is_file('/pride-and-prejudice/index.md'));
        $this->assertTrue($fs->is_file('/pride-and-prejudice/other.md'));
    }

    public function test_flatten_parent_if_needed_acts_on_a_directory_path() {
        $this->setup_directory_tree([
            'pride-and-prejudice' => [
                'index.md' => 'Test parent',
            ],
        ]);

        $sync = new WP_Static_File_Sync($this->filesystem);
        $this->assertTrue($sync->flatten_parent_if_needed('/pride-and-prejudice'));

        $fs = $this->filesystem;
        $this->assertFalse($fs->exists('/pride-and-prejudice'));
        $this->assertTrue($fs->is_file('/pride-and-prejudice.md'));
    }

    public function test_ensure_is_directory_index_creates_an_index_file_if_needed() {
        $this->setup_directory_tree([
            'pride-and-prejudice.md' => 'Pride and Prejudice',
        ]);

        $sync = new WP_Static_File_Sync($this->filesystem);
        $this->assertEquals(
            '/pride-and-prejudice/index.md',
            $sync->ensure_is_directory_index('/pride-and-prejudice.md')
        );

        $fs = $this->filesystem;
        $this->assertFalse($fs->is_file('/pride-and-prejudice.md'));
        $this->assertTrue($fs->is_file('/pride-and-prejudice/index.md'));
        $this->assertEquals('Pride and Prejudice', $fs->get_contents('/pride-and-prejudice/index.md'));
    }

    public function test_ensure_is_directory_index_returns_the_index_file_if_it_already_exists() {
        $this->setup_directory_tree([
            'pride-and-prejudice' => [
                'index.md' => 'Pride and Prejudice',
            ],
        ]);

        $sync = new WP_Static_File_Sync($this->filesystem);
        $this->assertEquals('/pride-and-prejudice/index.md', $sync->ensure_is_directory_index('/pride-and-prejudice/index.md'));

        $fs = $this->filesystem;
        $this->assertTrue($fs->is_file('/pride-and-prejudice/index.md'));
        $this->assertEquals('Pride and Prejudice', $fs->get_contents('/pride-and-prejudice/index.md'));
    }

    private function setup_directory_tree($structure, $path_so_far = '/') {
        $filesystem = $this->filesystem;
        if($path_so_far === '/') {
            if($filesystem->exists('/')) {
                // Reset the root directory
                if(false === $filesystem->rmdir('/', ['recursive' => true])) {
                    throw new Exception('Failed to remove directory /');
                }
            }
            if(false === $filesystem->mkdir('/')) {
                throw new Exception('Failed to create directory /');
            }
        }
        foreach($structure as $name => $content) {
            $path = rtrim($path_so_far, '/') . '/' . $name;
            if(is_array($content)) {
                if(false === $filesystem->mkdir($path)) {
                    throw new Exception('Failed to create directory ' . $path);
                }
                $this->setup_directory_tree($content, $path);
            } else {
                if(false === $filesystem->put_contents($path, $content)) {
                    throw new Exception('Failed to create file ' . $path);
                }
            }
        }
    }

}
