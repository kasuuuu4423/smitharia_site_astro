<?php

defined('ABSPATH') || exit;

final class Smitharia_GitHub_Dispatch
{
    private const DEFAULT_REPOSITORY = 'kasuuuu4423/smitharia_site_astro';
    private const DEFAULT_EVENT_TYPE = 'after_saving_wordpress';
    private static bool $queued = false;
    private static int $post_id = 0;

    public static function init(): void
    {
        add_action('save_post', array(__CLASS__, 'queue_after_save'), 20, 3);
        add_action('before_delete_post', array(__CLASS__, 'queue_before_delete'), 20, 2);
        add_action('shutdown', array(__CLASS__, 'dispatch_if_queued'));
        add_action('admin_notices', array(__CLASS__, 'render_configuration_notice'));
    }

    public static function queue_after_save(int $post_id, WP_Post $post, bool $update): void
    {
        unset($update);

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, self::supported_post_types(), true)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }

        self::$queued = true;
        self::$post_id = $post_id;
    }

    public static function queue_before_delete(int $post_id, WP_Post $post): void
    {
        if ($post->post_status !== 'publish') {
            return;
        }
        if (!in_array($post->post_type, self::supported_post_types(), true)) {
            return;
        }

        self::$queued = true;
        self::$post_id = $post_id;
    }

    public static function dispatch_if_queued(): void
    {
        if (!self::$queued) {
            return;
        }

        $token = self::read_token();
        if (is_wp_error($token)) {
            error_log('Smitharia GitHub dispatch: ' . $token->get_error_message());
            return;
        }

        $repository = defined('SMITHARIA_GITHUB_REPOSITORY')
            ? SMITHARIA_GITHUB_REPOSITORY
            : self::DEFAULT_REPOSITORY;
        $event_type = defined('SMITHARIA_GITHUB_EVENT_TYPE')
            ? SMITHARIA_GITHUB_EVENT_TYPE
            : self::DEFAULT_EVENT_TYPE;

        $response = wp_remote_post(
            'https://api.github.com/repos/' . $repository . '/dispatches',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $token,
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'smitharia-wordpress',
                ),
                'body' => wp_json_encode(array(
                    'event_type' => $event_type,
                    'client_payload' => array(
                        'post_id' => self::$post_id,
                        'site_url' => home_url('/'),
                    ),
                )),
                'data_format' => 'body',
            )
        );

        if (is_wp_error($response)) {
            error_log('Smitharia GitHub dispatch: ' . $response->get_error_message());
            return;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 204) {
            error_log(sprintf('Smitharia GitHub dispatch returned HTTP %d.', $status));
        }
    }

    public static function render_configuration_notice(): void
    {
        if (!current_user_can('manage_options') || !is_wp_error(self::read_token())) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Smitharia Core: GitHub dispatch token file is missing or unreadable.', 'smitharia-core')
            . '</p></div>';
    }

    /**
     * @return string|WP_Error
     */
    private static function read_token()
    {
        if (defined('SMITHARIA_GITHUB_TOKEN') && is_string(SMITHARIA_GITHUB_TOKEN) && SMITHARIA_GITHUB_TOKEN !== '') {
            return trim(SMITHARIA_GITHUB_TOKEN);
        }

        if (!defined('SMITHARIA_GITHUB_TOKEN_FILE')) {
            return new WP_Error('smitharia_missing_token_config', 'SMITHARIA_GITHUB_TOKEN_FILE is not configured.');
        }

        $token_file = SMITHARIA_GITHUB_TOKEN_FILE;

        if (!is_readable($token_file)) {
            return new WP_Error('smitharia_missing_token', 'GitHub token file is missing or unreadable.');
        }

        $token = trim((string) file_get_contents($token_file));
        if ($token === '') {
            return new WP_Error('smitharia_empty_token', 'GitHub token file is empty.');
        }

        return $token;
    }

    private static function supported_post_types(): array
    {
        return apply_filters('smitharia_dispatch_post_types', array('post', 'member', 'preference'));
    }
}
