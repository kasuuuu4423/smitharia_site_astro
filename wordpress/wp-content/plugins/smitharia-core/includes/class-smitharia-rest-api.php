<?php

defined('ABSPATH') || exit;

final class Smitharia_REST_API
{
    public static function init(): void
    {
        add_filter('rest_post_collection_params', array(__CLASS__, 'register_collection_params'));
        add_filter('rest_post_query', array(__CLASS__, 'filter_posts_query'), 10, 2);
    }

    public static function register_collection_params(array $params): array
    {
        $params['is_recommend'] = array(
            'description' => 'Return posts marked as recommended.',
            'type' => 'boolean',
            'default' => false,
        );
        $params['limited'] = array(
            'description' => 'Control whether limited posts are included.',
            'type' => 'string',
            'enum' => array('include', 'exclude', 'only'),
            'default' => 'include',
        );

        return $params;
    }

    public static function filter_posts_query(array $args, WP_REST_Request $request): array
    {
        $meta_query = isset($args['meta_query']) && is_array($args['meta_query'])
            ? $args['meta_query']
            : array();

        if (filter_var($request->get_param('is_recommend'), FILTER_VALIDATE_BOOLEAN)) {
            $meta_query[] = array(
                'key' => 'is_recommend',
                'value' => '1',
                'compare' => '=',
            );
        }

        $limited = $request->get_param('limited');
        if ($limited === 'exclude') {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'limited',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'limited',
                    'value' => '1',
                    'compare' => '!=',
                ),
            );
        } elseif ($limited === 'only') {
            $meta_query[] = array(
                'key' => 'limited',
                'value' => '1',
                'compare' => '=',
            );
        }

        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }
}
