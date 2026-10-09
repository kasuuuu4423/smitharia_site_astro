<?php
/**
 * WordPress を管理画面と REST API 用に限定する。
 */
defined('ABSPATH') || exit;

class Smitharia_Headless {
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'block_public_pages'), 0);
        add_filter('comments_open', '__return_false', PHP_INT_MAX);
        add_filter('pings_open', '__return_false', PHP_INT_MAX);
        add_filter('pre_option_default_comment_status', array(__CLASS__, 'closed_status'));
        add_filter('pre_option_default_ping_status', array(__CLASS__, 'closed_status'));
        add_filter('pre_comment_approved', array(__CLASS__, 'reject_new_comments'), PHP_INT_MAX);
        add_filter('rest_pre_insert_comment', array(__CLASS__, 'reject_rest_comment_creation'), PHP_INT_MAX, 2);
        add_filter('rest_pre_dispatch', array(__CLASS__, 'protect_comment_api'), 100, 3);
        add_filter('xmlrpc_methods', array(__CLASS__, 'remove_pingback_methods'));
    }

    public static function block_public_pages() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        nocache_headers();
        wp_die('Not Found', 'Not Found', array('response' => 404));
    }

    public static function closed_status() {
        return 'closed';
    }

    public static function reject_new_comments($comment) {
        return new WP_Error('smitharia_comments_disabled', 'コメントの受付は停止しています。', array('status' => 403));
    }

    public static function reject_rest_comment_creation($comment, $request) {
        return (int) $request->get_param('id') > 0 ? $comment : self::reject_new_comments($comment);
    }

    public static function protect_comment_api($result, $server, $request) {
        if (preg_match('#^/wp/v2/comments(?:/|$)#', $request->get_route()) && !current_user_can('moderate_comments')) {
            return self::reject_new_comments(null);
        }
        return $result;
    }

    public static function remove_pingback_methods($methods) {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
        return $methods;
    }
}
