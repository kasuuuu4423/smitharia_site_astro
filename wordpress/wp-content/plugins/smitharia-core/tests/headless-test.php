<?php
define('ABSPATH', __DIR__);
$admin = false;
$ajax = false;
$cron = false;
$moderator = false;
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wp_doing_cron() { return $GLOBALS['cron']; }
function current_user_can($capability) { return $GLOBALS['moderator']; }
function nocache_headers() {}
function wp_die($message, $title, $options) { throw new RuntimeException($message, $options['response']); }
class WP_Error {
    public $code;
    public $data;
    public function __construct($code, $message, $data) { $this->code = $code; $this->data = $data; }
}
class Test_Request {
    private $route;
    private $comment_id;
    public function __construct($route, $comment_id = 0) { $this->route = $route; $this->comment_id = $comment_id; }
    public function get_route() { return $this->route; }
    public function get_param($name) { return $name === 'id' ? $this->comment_id : null; }
}
require dirname(__DIR__) . '/includes/class-smitharia-headless.php';
function expect($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'OK: ' . $message . PHP_EOL;
}
try {
    Smitharia_Headless::block_public_pages();
    throw new RuntimeException('Public template was not blocked');
} catch (RuntimeException $error) {
    expect($error->getCode() === 404, 'Public templates return 404');
}
foreach (array('admin', 'ajax', 'cron') as $surface) {
    $GLOBALS[$surface] = true;
    Smitharia_Headless::block_public_pages();
    $GLOBALS[$surface] = false;
    expect(true, $surface . ' remains accessible');
}
define('REST_REQUEST', true);
Smitharia_Headless::block_public_pages();
expect(true, 'REST requests are not blocked as public templates');
foreach (array('/wp/v2/comments', '/wp/v2/comments/4') as $route) {
    $result = Smitharia_Headless::protect_comment_api(null, null, new Test_Request($route));
    expect($result instanceof WP_Error && $result->data['status'] === 403, 'Anonymous comment API denied: ' . $route);
}
$existing_error = new WP_Error('existing_error', '', array('status' => 401));
expect(Smitharia_Headless::protect_comment_api($existing_error, null, new Test_Request('/wp/v2/posts')) === $existing_error, 'Other API errors and endpoints are preserved');
$moderator = true;
expect(Smitharia_Headless::protect_comment_api(null, null, new Test_Request('/wp/v2/comments')) === null, 'Moderators can still read stored comments');
expect(Smitharia_Headless::reject_rest_comment_creation(new stdClass(), new Test_Request('/wp/v2/comments')) instanceof WP_Error, 'Moderators also cannot create REST comments');
$stored_comment = new stdClass();
expect(Smitharia_Headless::reject_rest_comment_creation($stored_comment, new Test_Request('/wp/v2/comments/4', 4)) === $stored_comment, 'Stored comments can still be moderated');
expect(Smitharia_Headless::reject_new_comments(1) instanceof WP_Error, 'Traditional comment creation is rejected');
$methods = Smitharia_Headless::remove_pingback_methods(array('pingback.ping' => 'callback', 'pingback.extensions.getPingbacks' => 'callback', 'wp.getPosts' => 'callback'));
expect(array_keys($methods) === array('wp.getPosts'), 'Pingbacks disabled without altering unrelated XML-RPC methods');
expect(Smitharia_Headless::closed_status() === 'closed', 'Future posts default to closed discussions');
