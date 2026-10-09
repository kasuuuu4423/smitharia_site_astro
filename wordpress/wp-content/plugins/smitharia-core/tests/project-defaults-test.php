<?php
define('ABSPATH', __DIR__);
define('SMITHARIA_CORE_DIR', dirname(__DIR__) . '/');
$meta = array(837 => array('project_summary' => '入力済みの紹介', 'description' => '元の説明', 'credit' => '元のクレジット'));
$options = array();
function get_option($name, $fallback) { return $GLOBALS['options'][$name] ?? $fallback; }
function update_option($name, $value, $autoload) { $GLOBALS['options'][$name] = $value; }
function get_post_type($id) { return $id === 447 ? 'page' : 'post'; }
function get_post_status($id) { return $id === 771 ? 'private' : 'publish'; }
function current_user_can($capability, $id) { return true; }
function get_post_meta($id, $name, $single) { return $GLOBALS['meta'][$id][$name] ?? ''; }
function metadata_exists($type, $id, $name) { return isset($GLOBALS['meta'][$id][$name]); }
function update_field($key, $value, $id) {
    $name = substr($key, strlen('field_smitharia_'));
    if (!array_key_exists($name, $GLOBALS['options']['smitharia_project_defaults_backup'][$id] ?? array())) {
        throw new RuntimeException('Backup must exist before writing.');
    }
    $GLOBALS['meta'][$id][$name] = $value;
    return true;
}
function expect($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'OK: ' . $message . PHP_EOL;
}
require SMITHARIA_CORE_DIR . 'includes/class-smitharia-project-defaults.php';
expect(count(Smitharia_Project_Defaults::get_defaults()) === 45, 'Initial copy covers 45 source-verified public works');
list($posts, $fields) = Smitharia_Project_Defaults::apply_defaults();
expect($posts === 43 && $fields > 0, 'Only published posts receive initial fields');
expect(!isset($meta[771]) && !isset($meta[447]), 'Private posts and other post types are not modified');
expect($meta[837]['project_summary'] === '入力済みの紹介', 'Existing input is preserved');
expect($meta[837]['description'] === '元の説明' && $meta[837]['credit'] === '元のクレジット', 'Original description and credits are preserved');
expect(!isset($meta[559]['project_scope']), 'Unknown responsibilities are not fabricated');
expect($meta[837]['project_summary_en'] !== '' && $meta[837]['project_consultation_en'] !== '', 'English initial copy is saved alongside Japanese');
expect(Smitharia_Project_Defaults::apply_defaults() === array(0, 0), 'Repeating the operation does not overwrite any saved fields');
