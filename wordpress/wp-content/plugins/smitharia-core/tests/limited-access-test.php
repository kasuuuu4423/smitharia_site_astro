<?php
// Boundary tests without a running WP installation. Real WP integration must
// also be checked before deployment (hashing, nonces, REST serialization).
define('ABSPATH', __DIR__);
define('SMITHARIA_SERVICE_KEY', str_repeat('s', 32));
define('MINUTE_IN_SECONDS', 60);
$options = array();
$transients = array();
$editor = false;
function current_user_can($capability) { return $GLOBALS['editor']; }
function is_admin() { return false; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload) { $GLOBALS['options'][$key] = $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_check_password($password, $hash) { return password_verify($password, $hash); }
function wp_hash_password($password) { return password_hash($password, PASSWORD_DEFAULT); }
function get_post_meta($id, $key, $single) { return $id === 42 ? '1' : ''; }
function url_to_postid($url) { return $url === 'https://wp.example/?p=42' ? 42 : 0; }
function check_admin_referer($action) { if (empty($_POST['_wpnonce'])) throw new RuntimeException('Missing nonce'); }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function admin_url($path) { return '/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="test-nonce">'; }
function wp_readonly($value, $current = true, $display = true) { $result = $value === $current ? ' readonly="readonly"' : ''; if ($display) echo $result; return $result; }
function checked($value) { if ($value) echo ' checked="checked"'; }
function submit_button($label) { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function wp_die($message, $title, $args) { throw new RuntimeException($message, $args['response']); }
function wp_safe_redirect($url) { throw new RuntimeException('saved', 302); }
class WP_Error {
    public $code;
    public $data;
    public function __construct($code, $message, $data) { $this->code = $code; $this->data = $data; }
}
class WP_REST_Server {}
class WP_REST_Request {
    private $route;
    private $params;
    private $headers;
    public function __construct($method, $route, $params = array(), $headers = array()) { $this->route = $route; $this->params = $params; $this->headers = $headers; }
    public function get_route() { return $this->route; }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function get_header($key) { return $this->headers[$key] ?? ''; }
    public function get_query_params() { return $this->params; }
    public function set_param($key, $value) { $this->params[$key] = $value; }
}
class WP_Query {
    public $params = array();
    public function get($key) { return $this->params[$key] ?? null; }
    public function set($key, $value) { $this->params[$key] = $value; }
}
require dirname(__DIR__) . '/includes/class-smitharia-limited-access.php';
require dirname(__DIR__) . '/includes/class-smitharia-rest-api.php';
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); echo 'OK: ' . $message . PHP_EOL; }
function viewer_request($password = 'long-random-password', $id = 'client-one') {
    return new WP_REST_Request('POST', '/smitharia/v1/verify', array(), array('x-smitharia-viewer' => 'Basic ' . base64_encode($id . ':' . $password)));
}

$options['smitharia_limited_shares'] = array('client-one' => array('label' => 'Client', 'enabled' => true, 'password_hash' => wp_hash_password('long-random-password')));
expect(!Smitharia_Limited_Access::can_view_limited(), 'anonymous cannot view limited');
$query = Smitharia_REST_API::filter_posts_query(array(), new WP_REST_Request('GET', '/wp/v2/posts', array('limited' => 'include')));
expect($query['meta_query'][0]['relation'] === 'OR', 'public include query still excludes limited posts');
$query_only = Smitharia_REST_API::filter_posts_query(array(), new WP_REST_Request('GET', '/wp/v2/posts', array('limited' => 'only')));
expect($query_only === $query, 'public only query cannot disclose limited posts');
$protected = Smitharia_Limited_Access::protect_single_post(null, new WP_REST_Server(), new WP_REST_Request('GET', '/wp/v2/posts/42'));
expect($protected instanceof WP_Error && $protected->data['status'] === 404, 'direct limited REST detail returns 404');
foreach (array('/acf/v3/posts/42', '/acf/v3/posts/42/thumbnail', '/acf/v2/posts/42') as $acf_route) {
    $acf_result = Smitharia_Limited_Access::protect_single_post(null, new WP_REST_Server(), new WP_REST_Request('GET', $acf_route));
    expect($acf_result instanceof WP_Error && $acf_result->data['status'] === 404, 'limited ACF endpoint blocked: ' . $acf_route);
}
expect(Smitharia_Limited_Access::protect_single_post(null, new WP_REST_Server(), new WP_REST_Request('GET', '/wp/v2/posts/7')) === null, 'public detail remains accessible');
$embed = Smitharia_Limited_Access::protect_single_post(null, new WP_REST_Server(), new WP_REST_Request('GET', '/oembed/1.0/embed', array('url' => 'https://wp.example/?p=42')));
expect($embed instanceof WP_Error, 'limited oEmbed is blocked');
$wp_query = new WP_Query();
Smitharia_Limited_Access::exclude_public_posts($wp_query);
expect(isset($wp_query->params['meta_query'][1]['relation']), 'public WP queries and feeds exclude limited posts');
expect(Smitharia_Limited_Access::verify_viewer(viewer_request())->data['status'] === 403, 'viewer endpoint rejects requests without service secret');
$_SERVER['HTTP_X_SMITHARIA_SERVICE_KEY'] = 'incorrect-key';
$invalid_service = Smitharia_Limited_Access::protect_single_post(null, new WP_REST_Server(), new WP_REST_Request('GET', '/wp/v2/posts'));
expect($invalid_service instanceof WP_Error && $invalid_service->data['status'] === 403, 'incorrect build key fails instead of building incomplete private pages');
$_SERVER['HTTP_X_SMITHARIA_SERVICE_KEY'] = SMITHARIA_SERVICE_KEY;
expect(Smitharia_Limited_Access::verify_viewer(viewer_request()) === true, 'enabled share authenticates');
expect(Smitharia_Limited_Access::verify_viewer(viewer_request('wrong-random-password'))->data['status'] === 401, 'wrong password denied');
$options['smitharia_limited_shares']['client-one']['enabled'] = false;
expect(Smitharia_Limited_Access::verify_viewer(viewer_request())->data['status'] === 401, 'disabled share immediately denied');
$options['smitharia_limited_shares']['client-one']['enabled'] = true;
delete_transient('smitharia_share_attempts_' . hash('sha256', 'client-one'));
for ($attempt = 0; $attempt < 10; $attempt++) Smitharia_Limited_Access::verify_viewer(viewer_request('wrong-random-password'));
expect(Smitharia_Limited_Access::verify_viewer(viewer_request())->data['status'] === 429, 'ten failed attempts throttle even correct password');
delete_transient('smitharia_share_attempts_' . hash('sha256', 'client-one'));

$editor = true;
ob_start();
Smitharia_Limited_Access::render_admin_page();
$admin_html = ob_get_clean();
expect(preg_match('/id="id-new"[^>]*readonly/', $admin_html) === 0, 'new share ID remains editable in admin form');
expect(preg_match('/id="id-client-one"[^>]*readonly="readonly"/', $admin_html) === 1, 'existing share ID is readonly in admin form');
expect(str_contains($admin_html, '共有先を追加') && str_contains($admin_html, '変更を保存'), 'admin renders both create and edit forms');
expect(str_contains($admin_html, 'minlength="8"') && str_contains($admin_html, 'pattern="[!-~]{8,128}"'), 'admin form specifies the 8 to 128 character rule');
foreach (array('1234567', str_repeat('x', 129), 'Ab3 xyZ9', '日本語のパスワード') as $invalid_password) {
    $_POST = array('_wpnonce' => 'nonce', 'share_id' => 'boundary-check', 'label' => 'Boundary', 'password' => $invalid_password, 'enabled' => '1', 'creating' => '1');
    try {
        Smitharia_Limited_Access::save_share();
        throw new RuntimeException('Invalid password unexpectedly saved');
    } catch (RuntimeException $error) {
        expect($error->getCode() === 400 && !isset($options['smitharia_limited_shares']['boundary-check']), 'invalid password rejected without saving: ' . strlen($invalid_password) . ' bytes');
    }
}
foreach (array('Ab3!xyZ9', str_repeat('x', 128)) as $valid_password) {
    $_POST = array('_wpnonce' => 'nonce', 'share_id' => 'boundary-check', 'label' => 'Boundary', 'password' => $valid_password, 'enabled' => '1', 'creating' => '1');
    try { Smitharia_Limited_Access::save_share(); } catch (RuntimeException $error) { expect($error->getCode() === 302, 'valid password saved: ' . strlen($valid_password) . ' characters'); }
    expect(Smitharia_Limited_Access::verify_viewer(viewer_request($valid_password, 'boundary-check')) === true, 'saved password authenticates: ' . strlen($valid_password) . ' characters');
    unset($options['smitharia_limited_shares']['boundary-check']);
}
$_POST = array('_wpnonce' => 'nonce', 'share_id' => 'client-two', 'label' => 'Second client', 'password' => 'another-random-password', 'enabled' => '1', 'creating' => '1');
try { Smitharia_Limited_Access::save_share(); } catch (RuntimeException $error) { expect($error->getCode() === 302, 'new share saved'); }
expect($options['smitharia_limited_shares']['client-two']['password_hash'] !== $_POST['password'], 'plaintext password is not stored');
$stored_hash = $options['smitharia_limited_shares']['client-two']['password_hash'];
$_POST['creating'] = '0';
$_POST['password'] = '';
unset($_POST['enabled']);
try { Smitharia_Limited_Access::save_share(); } catch (RuntimeException $error) { expect($error->getCode() === 302, 'existing share disabled'); }
expect($options['smitharia_limited_shares']['client-two']['password_hash'] === $stored_hash, 'blank password preserves existing hash');
$editor = false;
expect(Smitharia_Limited_Access::verify_viewer(viewer_request('another-random-password', 'client-two'))->data['status'] === 401, 'second share disabled independently');
expect(Smitharia_Limited_Access::verify_viewer(viewer_request()) === true, 'first share still works');
$editor = true;
$_POST['enabled'] = '1';
$_POST['password'] = 'rotated-random-password';
try { Smitharia_Limited_Access::save_share(); } catch (RuntimeException $error) { expect($error->getCode() === 302, 'password rotated and share enabled'); }
$editor = false;
expect(Smitharia_Limited_Access::verify_viewer(viewer_request('another-random-password', 'client-two'))->data['status'] === 401, 'old password rejected after rotation');
expect(Smitharia_Limited_Access::verify_viewer(viewer_request('rotated-random-password', 'client-two')) === true, 'new password accepted');
try { Smitharia_Limited_Access::save_share(); } catch (RuntimeException $error) { expect($error->getCode() === 403, 'non-admin cannot manage shares'); }
