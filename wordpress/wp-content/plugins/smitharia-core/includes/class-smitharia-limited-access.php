<?php

defined('ABSPATH') || exit;

final class Smitharia_Limited_Access
{
    private const OPTION = 'smitharia_limited_shares';
    private static bool $viewer_authorized = false;

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
        add_action('admin_post_smitharia_save_share', array(__CLASS__, 'save_share'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        // ACF to REST API registers routes on this filter and returns null.
        // Run afterwards so it cannot erase the access-denied response.
        add_filter('rest_pre_dispatch', array(__CLASS__, 'protect_single_post'), PHP_INT_MAX, 3);
        add_filter('rest_post_dispatch', array(__CLASS__, 'disable_rest_cache'), 10, 3);
        add_action('pre_get_posts', array(__CLASS__, 'exclude_public_posts'));
    }

    public static function has_service_access(): bool
    {
        $key = defined('SMITHARIA_SERVICE_KEY') ? SMITHARIA_SERVICE_KEY : '';
        $provided = $_SERVER['HTTP_X_SMITHARIA_SERVICE_KEY'] ?? '';
        return is_string($key) && strlen($key) >= 32 && is_string($provided) && hash_equals($key, $provided);
    }

    public static function can_view_limited(): bool
    {
        return self::$viewer_authorized || current_user_can('edit_posts') || self::has_service_access();
    }

    public static function exclusion_query(): array
    {
        return array(
            'relation' => 'OR',
            array('key' => 'limited', 'compare' => 'NOT EXISTS'),
            array('key' => 'limited', 'value' => '1', 'compare' => '!='),
        );
    }

    public static function exclude_public_posts(WP_Query $query): void
    {
        if (is_admin() || self::can_view_limited()) {
            return;
        }
        $post_type = $query->get('post_type');
        if ($post_type && $post_type !== 'any' && !in_array('post', (array) $post_type, true)) {
            return;
        }
        $existing = $query->get('meta_query');
        $query->set('meta_query', array('relation' => 'AND', is_array($existing) ? $existing : array(), self::exclusion_query()));
    }

    public static function protect_single_post($result, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (preg_match('#^/(?:wp/v2|acf/v[23])/posts(?:/|$)#', $request->get_route())
            && !empty($_SERVER['HTTP_X_SMITHARIA_SERVICE_KEY']) && !self::has_service_access()) {
            return new WP_Error('service_forbidden', 'Invalid service key.', array('status' => 403));
        }
        if (self::can_view_limited()) {
            return $result;
        }
        $post_id = 0;
        if (preg_match('#^/(?:wp/v2|acf/v[23])/posts/(\d+)(?:/|$)#', $request->get_route(), $match)) {
            $post_id = (int) $match[1];
        } elseif ($request->get_route() === '/oembed/1.0/embed') {
            $post_id = url_to_postid((string) $request->get_param('url'));
        }
        if ($post_id && get_post_meta($post_id, 'limited', true) === '1') {
            return new WP_Error('rest_post_invalid_id', 'Invalid post ID.', array('status' => 404));
        }
        return $result;
    }

    public static function disable_rest_cache($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (preg_match('#^/(?:smitharia/v1/|wp/v2/(?:posts|search)(?:/|$)|acf/v[23]/posts(?:/|$))#', $request->get_route())) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
            $response->header('X-Smitharia-Limited-Protection', '1');
            $response->header('Vary', 'X-Smitharia-Service-Key, X-Smitharia-Viewer, Authorization');
        }
        return $response;
    }

    public static function register_routes(): void
    {
        register_rest_route('smitharia/v1', '/verify', array(
            'methods' => 'POST',
            'permission_callback' => array(__CLASS__, 'verify_viewer'),
            'callback' => static function () {
                return new WP_REST_Response(array('authenticated' => true));
            },
        ));
        register_rest_route('smitharia/v1', '/limited-posts', array(
            'methods' => 'GET',
            'permission_callback' => array(__CLASS__, 'verify_viewer'),
            'callback' => array(__CLASS__, 'get_limited_posts'),
        ));
    }

    public static function verify_viewer(WP_REST_Request $request)
    {
        if (!self::has_service_access()) {
            return new WP_Error('service_forbidden', 'Service unavailable.', array('status' => 403));
        }
        $authorization = $request->get_header('x-smitharia-viewer');
        if (strlen($authorization) > 1024 || !preg_match('/^Basic ([A-Za-z0-9+\/]+={0,2})$/i', $authorization, $match)) {
            return new WP_Error('viewer_unauthorized', 'Invalid credentials.', array('status' => 401));
        }
        $decoded = base64_decode($match[1], true);
        if (!is_string($decoded) || !preg_match('/^([A-Za-z0-9_-]{3,64}):([\x21-\x7e]{16,128})$/D', $decoded, $credentials)) {
            return new WP_Error('viewer_unauthorized', 'Invalid credentials.', array('status' => 401));
        }
        $share_id = $credentials[1];
        $rate_key = 'smitharia_share_attempts_' . hash('sha256', $share_id);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 10) {
            return new WP_Error('viewer_rate_limited', 'Try again in 15 minutes.', array('status' => 429));
        }
        $shares = get_option(self::OPTION, array());
        $share = $shares[$share_id] ?? null;
        if (!is_array($share) || empty($share['enabled']) || !wp_check_password($credentials[2], $share['password_hash'])) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            return new WP_Error('viewer_unauthorized', 'Invalid credentials.', array('status' => 401));
        }
        delete_transient($rate_key);
        return true;
    }

    public static function get_limited_posts(WP_REST_Request $request)
    {
        // Delegate validation and ACF serialization to the existing posts API.
        $internal_request = new WP_REST_Request('GET', '/wp/v2/posts');
        $allowed = array('per_page', 'page', 'categories', 'is_recommend', 'limited');
        foreach ($request->get_query_params() as $key => $value) {
            if (!in_array($key, $allowed, true) || !is_scalar($value)) {
                return new WP_Error('invalid_query', 'Invalid query.', array('status' => 400));
            }
            $internal_request->set_param($key, $value);
        }
        self::$viewer_authorized = true;
        try {
            return rest_do_request($internal_request);
        } finally {
            self::$viewer_authorized = false;
        }
    }

    public static function register_admin_page(): void
    {
        add_management_page('限定公開の共有先', '限定公開の共有先', 'manage_options', 'smitharia-limited-shares', array(__CLASS__, 'render_admin_page'));
    }

    public static function save_share(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('操作する権限がありません。', '', array('response' => 403));
        }
        check_admin_referer('smitharia_save_share');
        $share_id = isset($_POST['share_id']) && is_string($_POST['share_id']) ? wp_unslash($_POST['share_id']) : '';
        $label = isset($_POST['label']) && is_string($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
        $shares = get_option(self::OPTION, array());
        $existing = $shares[$share_id] ?? null;
        $creating = isset($_POST['creating']) && $_POST['creating'] === '1';
        if (!preg_match('/^[A-Za-z0-9_-]{3,64}$/D', $share_id) || $label === '' || strlen($label) > 240) {
            wp_die('共有先名とIDを確認してください。IDは半角英数字・ハイフン・アンダースコアの3〜64文字です。', '', array('response' => 400));
        }
        if (($creating && $existing !== null) || (!$creating && $existing === null)) {
            wp_die('このIDは登録済み、または編集対象が存在しません。共有先一覧から操作し直してください。', '', array('response' => 400));
        }
        if (($password !== '' && !preg_match('/^[\x21-\x7e]{16,128}$/D', $password)) || ($existing === null && $password === '')) {
            wp_die('パスワードは空白を含まない半角英数字・記号の16〜128文字で入力してください。', '', array('response' => 400));
        }
        if ($creating && count($shares) >= 200) {
            wp_die('共有先は200件まで登録できます。', '', array('response' => 400));
        }
        $shares[$share_id] = array(
            'label' => $label,
            'enabled' => $enabled,
            'password_hash' => $password !== '' ? wp_hash_password($password) : $existing['password_hash'],
        );
        update_option(self::OPTION, $shares, false);
        delete_transient('smitharia_share_attempts_' . hash('sha256', $share_id));
        wp_safe_redirect(admin_url('tools.php?page=smitharia-limited-shares&saved=1'));
        exit;
    }

    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $shares = get_option(self::OPTION, array());
        ?>
        <div class="wrap">
            <h1>限定公開の共有先</h1>
            <p>全共有先が同じ限定作品を閲覧できます。アクセス先は <code>https://smitharia.com/limited/</code> です。</p>
            <p>パスワードはハッシュ化して保存します。保存後は表示できません。変更・無効化は次のアクセスから反映されます。</p>
            <?php if (!defined('SMITHARIA_SERVICE_KEY') || strlen((string) SMITHARIA_SERVICE_KEY) < 32) : ?>
                <div class="notice notice-error"><p>認証サービスの接続キーが未設定です。wp-config.phpにSMITHARIA_SERVICE_KEYを設定してください。</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['saved']) && $_GET['saved'] === '1') : ?>
                <div class="notice notice-success"><p>共有先を保存しました。</p></div>
            <?php endif; ?>
            <h2>共有先を追加</h2>
            <?php self::render_share_form('', array('label' => '', 'enabled' => true)); ?>
            <h2>登録済みの共有先</h2>
            <?php if (!$shares) : ?><p>共有先はまだ登録されていません。</p><?php endif; ?>
            <?php foreach ($shares as $share_id => $share) : ?>
                <hr>
                <h3><?php echo esc_html($share['label']); ?>（<?php echo $share['enabled'] ? '有効' : '無効'; ?>）</h3>
                <?php self::render_share_form((string) $share_id, $share); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_share_form(string $share_id, array $share): void
    {
        $creating = $share_id === '';
        $suffix = $creating ? 'new' : $share_id;
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="smitharia_save_share">
            <input type="hidden" name="creating" value="<?php echo $creating ? '1' : '0'; ?>">
            <?php wp_nonce_field('smitharia_save_share'); ?>
            <table class="form-table" role="presentation">
                <tr><th><label for="label-<?php echo esc_attr($suffix); ?>">共有先名</label></th><td><input class="regular-text" id="label-<?php echo esc_attr($suffix); ?>" name="label" value="<?php echo esc_attr($share['label']); ?>" required maxlength="80"></td></tr>
                <tr><th><label for="id-<?php echo esc_attr($suffix); ?>">閲覧用ID</label></th><td><input class="regular-text" id="id-<?php echo esc_attr($suffix); ?>" name="share_id" value="<?php echo esc_attr($share_id); ?>" pattern="[A-Za-z0-9_-]{3,64}" minlength="3" maxlength="64" autocomplete="off" required <?php wp_readonly(!$creating); ?>><p class="description">半角英数字・ハイフン・アンダースコア。登録後は変更できません。</p></td></tr>
                <tr><th><label for="password-<?php echo esc_attr($suffix); ?>">パスワード</label></th><td><input class="regular-text" type="password" id="password-<?php echo esc_attr($suffix); ?>" name="password" minlength="16" maxlength="128" pattern="[!-~]{16,128}" autocomplete="new-password" <?php echo $creating ? 'required' : ''; ?>><p class="description">空白を含まない半角16〜128文字。<?php echo $creating ? 'パスワード管理アプリなどで生成し、共有先へ渡す内容を保存前に控えてください。' : '変更するときだけ入力してください。空欄なら現在のパスワードを維持します。'; ?></p></td></tr>
                <tr><th>アクセス</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($share['enabled'])); ?>>有効にする</label></td></tr>
            </table>
            <?php submit_button($creating ? '共有先を追加' : '変更を保存'); ?>
        </form>
        <?php
    }
}
