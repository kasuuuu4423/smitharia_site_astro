<?php
define('ABSPATH', __DIR__);
$saved_meta = array('thumbnail' => true, 'is_recommend' => true);
function metadata_exists($type, $id, $key) {
    return isset($GLOBALS['saved_meta'][$key]);
}
require dirname(__DIR__) . '/includes/class-smitharia-acf-rest-api.php';
function expect($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}
$fields = array_map(static function ($name) { return array('name' => $name); }, array('thumbnail', 'is_recommend', 'limited', 'internal_secret'));
$resource = array('type' => 'post', 'sub_type' => 'post', 'id' => 42);
$result = Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET');
expect(array_column($result, 'name') === array('thumbnail', 'is_recommend'), 'GET keeps saved false values and omits unset/default/private fields');
expect(Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'HEAD') === $result, 'HEAD has the same disclosure boundary as GET');
$result = Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'POST');
expect(array_column($result, 'name') === array('thumbnail', 'is_recommend', 'limited'), 'POST can save previously unset allowed fields');
$GLOBALS['saved_meta'] = array();
expect(Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET') === $result, 'Empty posts preserve legacy default behavior');
$resource['sub_type'] = 'other';
expect(Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET') === $fields, 'Unrelated post types retain their own ACF policy');
$resource = array('type' => 'user', 'sub_type' => 'post', 'id' => 42);
expect(Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET') === $fields, 'User resources are not treated as posts');
$preference = array('key' => 'group_65e7715d64eec', 'show_in_rest' => 0);
expect(Smitharia_ACF_REST_API::enable_preference_rest($preference)['show_in_rest'] === 1, 'Known Preference group becomes available through native REST');
$other = array('key' => 'private_group', 'show_in_rest' => 0);
expect(Smitharia_ACF_REST_API::enable_preference_rest($other) === $other, 'Other private groups remain private');
$resource = array('type' => 'post', 'sub_type' => 'preference', 'id' => 1);
$fields = array_map(static function ($name) { return array('name' => $name); }, array('about_image', 'about_description', 'about_image_description'));
expect(array_column(Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET'), 'name') === array('about_image', 'about_description'), 'Preference exposes only fields used by the public site');
expect(Smitharia_ACF_REST_API::use_standard_format('light') === 'standard', 'Native serialization formats images as configured arrays/URLs');

function acf_add_local_field_group($group) {
    $GLOBALS['project_field_group'] = $group;
}
require dirname(__DIR__) . '/includes/class-smitharia-project-fields.php';
Smitharia_Project_Fields::register_fields();
$group = $GLOBALS['project_field_group'];
$resource = array('type' => 'post', 'sub_type' => 'post', 'id' => 42);
$fields = array_merge($group['fields'], array(array('name' => 'internal_secret')));
$writable = Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'POST');
expect(count($writable) === count($group['fields']), 'Every registered project field can be saved through native ACF REST without exposing private fields');
$GLOBALS['saved_meta'] = array('project_scope_en' => true, 'description_en' => true);
$readable = Smitharia_ACF_REST_API::filter_rest_fields($fields, $resource, 'GET');
expect(array_column($readable, 'name') === array('project_scope_en', 'description_en'), 'English-only posts expose saved translations and omit unset Japanese fields');
expect($group['show_in_rest'] === 1 && $group['location'][0][0]['value'] === 'post', 'Bilingual project fields are available on the existing posts resource');
