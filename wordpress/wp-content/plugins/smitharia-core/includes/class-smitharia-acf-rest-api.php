<?php
/**
 * ACF 標準 REST API で既存フロントエンドのレスポンスを維持する。
 */

defined('ABSPATH') || exit;

class Smitharia_ACF_REST_API {
    private const FIELDS = array(
        'post' => array(
            'thumbnail', 'credit', 'description', 'period', 'extend_column', 'extend_row', 'is_recommend', 'limited',
            'project_summary', 'project_summary_en', 'project_scope', 'project_scope_en',
            'project_approach', 'project_approach_en', 'project_consultation', 'project_consultation_en',
            'project_title_en', 'description_en', 'credit_en',
        ),
        'member' => array('name', 'english_name', 'position', 'english_position', 'heading_position', 'bio', 'english_bio', 'pic', 'limited_order'),
        'preference' => array('about_image', 'about_description'),
    );

    public static function init() {
        add_filter('acf/settings/rest_api_format', array(__CLASS__, 'use_standard_format'));
        add_filter('acf/load_field_group', array(__CLASS__, 'enable_preference_rest'));
        add_filter('acf/rest/get_fields', array(__CLASS__, 'filter_rest_fields'), 10, 3);
    }

    public static function use_standard_format($format) {
        // ACF のネイティブ処理に画像・本文の整形とアクセス判定を任せる。
        return 'standard';
    }

    public static function enable_preference_rest($field_group) {
        if (isset($field_group['key']) && $field_group['key'] === 'group_65e7715d64eec') {
            $field_group['show_in_rest'] = 1;
        }
        return $field_group;
    }

    public static function filter_rest_fields($fields, $resource, $http_method) {
        $post_type = $resource['sub_type'] ?? '';
        if (($resource['type'] ?? '') !== 'post' || !isset(self::FIELDS[$post_type])) {
            return $fields;
        }

        $allowed_fields = self::FIELDS[$post_type];
        $fields = array_values(array_filter($fields, static function ($field) use ($allowed_fields) {
            return isset($field['name']) && in_array($field['name'], $allowed_fields, true);
        }));

        $post_id = (int) ($resource['id'] ?? 0);
        if ($post_id <= 0 || !in_array($http_method, array('GET', 'HEAD'), true)) {
            // 新規保存できるよう、書き込み時は未保存フィールドを除外しない。
            return $fields;
        }

        $saved_fields = array_values(array_filter($fields, static function ($field) use ($post_id) {
            return metadata_exists('post', $post_id, $field['name']);
        }));

        // 旧 API は値が一つもない投稿では全フィールドの初期値を返していた。
        return $saved_fields ?: $fields;
    }
}
