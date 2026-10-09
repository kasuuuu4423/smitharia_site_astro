<?php
define('ABSPATH', __DIR__);
$upload_dir = sys_get_temp_dir() . '/smitharia-image-test-' . uniqid();
mkdir($upload_dir);
file_put_contents($upload_dir . '/generated.jpg', 'fixture');
$stored_path = 'generated.jpg';
$updated_meta = array();
$query_args = array();
$response = null;
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class WP_Query {
    public $posts = array(884);
    public $found_posts = 170;
    public function __construct($args) { $GLOBALS['query_args'] = $args; }
}
function check_ajax_referer($action, $field) {}
function current_user_can($capability) { return true; }
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) { return $GLOBALS['stored_path']; }
function wp_upload_dir() { return array('basedir' => $GLOBALS['upload_dir'], 'error' => false); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
function update_post_meta($id, $key, $value) { $GLOBALS['updated_meta'][$key] = $value; }
function get_attached_file($id) { return false; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_send_json_success($data) { $GLOBALS['response'] = $data; }
require dirname(__DIR__) . '/includes/class-smitharia-image-filter.php';
function expect($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'OK: ' . $message . PHP_EOL;
}
try {
    $_POST['offset'] = 15;
    Smitharia_Image_Filter::ajax_regenerate_images();
    expect($query_args['posts_per_page'] === 1, 'Each request processes at most one attachment');
    expect($updated_meta === array(), 'Existing generated files are skipped without changing status');
    expect($response['processed'] === 16 && $response['nextOffset'] === 16, 'Skipping a file still advances the cursor');
    expect(!$response['done'], 'Skipping one file does not finish the remaining attachments');
    $stored_path = 'missing.jpg';
    Smitharia_Image_Filter::ajax_regenerate_images();
    expect(isset($updated_meta['_smitharia_filtered_status']), 'A missing generated file is processed again');
    expect(count($response['errors']) === 1, 'Generation failures are returned instead of silently skipped');
} finally {
    unlink($upload_dir . '/generated.jpg');
    rmdir($upload_dir);
}
