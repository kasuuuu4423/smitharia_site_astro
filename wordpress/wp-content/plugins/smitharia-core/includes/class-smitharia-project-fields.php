<?php
/**
 * 作品詳細の日英併記用フィールドを登録する。
 */

defined('ABSPATH') || exit;

class Smitharia_Project_Fields {
    public static function init() {
        add_action('acf/init', array(__CLASS__, 'register_fields'));
    }

    public static function register_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $fields = array();
        $pairs = array(
            'project_summary' => array('一言紹介', '何をつくった作品か、1〜2文で記入してください。'),
            'project_scope' => array('担当範囲', 'この作品で実際に担当した仕事を記入してください。'),
            'project_approach' => array('制作の詳細', '使用した仕組み、技術、制作方法など、作品を具体的に説明する内容を記入してください。'),
            'project_consultation' => array('相談できること', '作品を見た方が、新しい企画で依頼できる制作内容を記入してください。例：MVやライブで音と光を連動させるシステムの開発・設置。'),
        );

        foreach ($pairs as $name => $settings) {
            foreach (array('' => '日本語', '_en' => 'English') as $suffix => $language) {
                $field_name = $name . $suffix;
                $fields[] = array(
                    'key' => 'field_smitharia_' . $field_name,
                    'label' => $settings[0] . ' / ' . $language,
                    'name' => $field_name,
                    'type' => 'textarea',
                    'instructions' => $settings[1] . ' 未入力の場合は表示しません。',
                    'required' => 0,
                    'rows' => 4,
                    'new_lines' => '',
                );
            }
        }

        $fields[] = array(
            'key' => 'field_smitharia_project_title_en',
            'label' => '作品タイトル / English',
            'name' => 'project_title_en',
            'type' => 'text',
            'instructions' => '英語タイトルが必要な場合に入力してください。既存タイトルの下に表示します。',
            'required' => 0,
        );
        $fields[] = array(
            'key' => 'field_smitharia_description_en',
            'label' => '作品説明 / English',
            'name' => 'description_en',
            'type' => 'textarea',
            'instructions' => '既存の説明欄に英語を併記済みの場合は空欄にしてください。入力すると既存の説明の下に追加表示します。',
            'required' => 0,
            'rows' => 6,
            'new_lines' => '',
        );
        $fields[] = array(
            'key' => 'field_smitharia_credit_en',
            'label' => 'クレジット / English',
            'name' => 'credit_en',
            'type' => 'textarea',
            'instructions' => '既存のクレジット欄に英語を併記済みの場合は空欄にしてください。入力すると既存のクレジットの下に追加表示します。',
            'required' => 0,
            'rows' => 6,
            'new_lines' => '',
        );

        acf_add_local_field_group(array(
            'key' => 'group_smitharia_project_details',
            'title' => '作品詳細 / Project details',
            'fields' => $fields,
            'location' => array(array(array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'post',
            ))),
            'show_in_rest' => 1,
        ));
    }
}
