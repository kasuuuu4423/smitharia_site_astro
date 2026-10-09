<?php
/**
 * 既存作品の説明・クレジットから作成した初期入力を管理画面で反映する。
 */
defined('ABSPATH') || exit;

class Smitharia_Project_Defaults {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_page'));
    }

    public static function register_page() {
        add_management_page('作品詳細の初期入力', '作品詳細の初期入力', 'manage_options', 'smitharia-project-defaults', array(__CLASS__, 'render_page'));
    }

    public static function get_defaults() {
        $rows = json_decode(file_get_contents(SMITHARIA_CORE_DIR . 'data/project-details.json'), true);
        if (!is_array($rows)) {
            throw new RuntimeException('作品の初期入力データを読み込めません。');
        }
        $defaults = array();
        foreach ($rows as $id => $row) {
            $defaults[(int) $id] = array(
                'project_summary' => $row[0],
                'project_summary_en' => $row[1],
                'project_scope' => $row[2],
                'project_scope_en' => $row[3],
                'project_consultation' => '企画の内容や、依頼したい制作範囲をお聞かせください。',
                'project_consultation_en' => 'Tell us about your project and the production work you would like to commission.',
            );
        }
        $defaults[837]['project_consultation'] = 'MVやライブで音と光を連動させたい場合、演出に合わせたシステムの開発・設置をご相談いただけます。';
        $defaults[837]['project_consultation_en'] = 'Get in touch about developing and installing systems that synchronize sound and light for music videos or live performances.';
        $defaults[837]['description_en'] = 'For Jene’s music video “Shall We Dance,” we developed and installed a real-time lighting system. The music is analyzed across 22 frequency bands, and their levels control 22 lights as well as the lights inside a refrigerator and microwave.';
        $defaults[771]['project_consultation'] = 'ライブや大会の映像制作、当日の映像演出、進行に合わせたタイマーなどのシステム開発をご相談いただけます。';
        $defaults[771]['project_consultation_en'] = 'Get in touch about event videos, live visuals and systems such as timers tailored to your event.';
        $defaults[447]['project_consultation'] = '展覧会の企画、グラフィック制作、展示に必要な技術対応をご相談いただけます。';
        $defaults[447]['project_consultation_en'] = 'Get in touch about exhibition planning, graphic design and technical support for installations.';
        return $defaults;
    }

    public static function apply_defaults() {
        $changed_posts = 0;
        $changed_fields = 0;
        $backup = get_option('smitharia_project_defaults_backup', array());
        foreach (self::get_defaults() as $id => $fields) {
            if (get_post_type($id) !== 'post' || get_post_status($id) !== 'publish' || !current_user_can('edit_post', $id)) {
                continue;
            }
            $changed = false;
            foreach ($fields as $name => $value) {
                $current = get_post_meta($id, $name, true);
                if (trim((string) $current) !== '' || trim($value) === '') {
                    continue;
                }
                if (!isset($backup[$id][$name])) {
                    $backup[$id][$name] = array(
                        'exists' => metadata_exists('post', $id, $name),
                        'value' => $current,
                    );
                    // 書き込み前に元の値を保存する。本文・画像・クレジットは変更しない。
                    update_option('smitharia_project_defaults_backup', $backup, false);
                }
                if (update_field('field_smitharia_' . $name, $value, $id)) {
                    $changed = true;
                    $changed_fields++;
                }
            }
            if ($changed) {
                $changed_posts++;
            }
        }
        return array($changed_posts, $changed_fields);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('この操作を行う権限がありません。');
        }
        echo '<div class="wrap"><h1>作品詳細の初期入力</h1>';
        if (!function_exists('update_field')) {
            echo '<p>ACFを有効にしてください。</p></div>';
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('smitharia-project-defaults');
            list($posts, $fields) = self::apply_defaults();
            echo '<div class="notice notice-success"><p>' . esc_html($posts . '作品・' . $fields . '項目を反映しました。') . '</p></div>';
        }
        echo '<p>既存の作品説明・クレジットをもとに、公開作品45件の日英紹介と確認できる担当範囲を入力します。入力済みの項目、本文、画像、既存説明、クレジット、公開状態は変更しません。</p>';
        echo '<p>反映前の値は smitharia_project_defaults_backup オプションに保存します。</p>';
        echo '<table class="widefat striped"><thead><tr><th>作品</th><th>一言紹介</th><th>担当範囲</th></tr></thead><tbody>';
        foreach (self::get_defaults() as $id => $fields) {
            if (get_post_type($id) !== 'post' || get_post_status($id) !== 'publish' || !current_user_can('edit_post', $id)) {
                continue;
            }
            echo '<tr><td>' . esc_html(get_the_title($id)) . '</td><td>' . esc_html($fields['project_summary']) . '</td><td>' . esc_html($fields['project_scope']) . '</td></tr>';
        }
        echo '</tbody></table><form method="post">';
        wp_nonce_field('smitharia-project-defaults');
        submit_button('空欄の項目に初期入力を反映');
        echo '</form></div>';
    }
}
