<?php

defined('ABSPATH') || exit;

final class Smitharia_Image_Filter
{
    private const META_KEY = '_smitharia_filtered_path';
    private const BATCH_SIZE = 5;

    public static function init(): void
    {
        add_filter('wp_generate_attachment_metadata', array(__CLASS__, 'generate_after_upload'), 20, 2);
        add_action('admin_menu', array(__CLASS__, 'register_tools_page'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('wp_ajax_smitharia_regenerate_images', array(__CLASS__, 'ajax_regenerate_images'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_field'));
    }

    public static function activate(): void
    {
        $uploads = wp_upload_dir();
        if (empty($uploads['error'])) {
            wp_mkdir_p(trailingslashit($uploads['basedir']) . 'filtered');
        }
    }

    public static function generate_after_upload(array $metadata, int $attachment_id): array
    {
        $result = self::generate_for_attachment($attachment_id);
        if (is_wp_error($result)) {
            error_log('Smitharia filtered image: ' . $result->get_error_message());
        }

        return $metadata;
    }

    /**
     * @return string|WP_Error Relative path below wp-content/uploads.
     */
    public static function generate_for_attachment(int $attachment_id)
    {
        if (!extension_loaded('imagick')) {
            return new WP_Error('smitharia_no_imagick', 'Imagick extension is not available.');
        }

        $source = get_attached_file($attachment_id);
        if (!$source || !is_readable($source)) {
            return new WP_Error('smitharia_missing_source', 'Source image is not readable.');
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/jpeg', 'image/png'), true)) {
            return new WP_Error('smitharia_unsupported_type', 'Only JPEG and PNG files are supported.');
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('smitharia_upload_error', $uploads['error']);
        }

        $source_normalized = wp_normalize_path($source);
        $base_normalized = trailingslashit(wp_normalize_path($uploads['basedir']));
        if (strpos($source_normalized, $base_normalized) !== 0) {
            return new WP_Error('smitharia_invalid_source', 'Source image is outside the uploads directory.');
        }

        $relative_source = ltrim(substr($source_normalized, strlen($base_normalized)), '/');
        $relative_dir = dirname($relative_source);
        $filename = wp_basename($relative_source);
        $relative_output = 'filtered/'
            . (($relative_dir === '.') ? '' : trailingslashit($relative_dir))
            . 'filtered-'
            . $filename;
        $destination = trailingslashit($uploads['basedir']) . $relative_output;

        if (!wp_mkdir_p(dirname($destination))) {
            return new WP_Error('smitharia_create_directory', 'Could not create the filtered image directory.');
        }

        try {
            $image = new Imagick($source);
            $image->setIteratorIndex(0);

            $noise = new Imagick();
            $noise->newImage($image->getImageWidth(), $image->getImageHeight(), 'gray');
            $noise->addNoiseImage(Imagick::NOISE_POISSON, Imagick::CHANNEL_ALL);
            $noise->addNoiseImage(Imagick::NOISE_POISSON, Imagick::CHANNEL_ALL);
            $noise->addNoiseImage(Imagick::NOISE_POISSON, Imagick::CHANNEL_ALL);
            $noise->adaptiveResizeImage($noise->getImageWidth() * 2, $noise->getImageHeight() * 2);
            $noise->extentImage($image->getImageWidth(), $image->getImageHeight(), 0, 0);
            $noise->setImageColorspace(Imagick::COLORSPACE_GRAY);

            $image->levelImage(0.8, 0.5, 40000);
            $image->posterizeImage(8, false);
            $image->gaussianBlurImage(10, 10);
            $image->compositeImage($noise, Imagick::COMPOSITE_MULTIPLY, 0, 0);
            $image->minifyImage();
            $image->setImageCompressionQuality(80);
            $image->stripImage();

            if (!$image->writeImage($destination)) {
                throw new RuntimeException('Imagick could not write the filtered image.');
            }

            $noise->clear();
            $noise->destroy();
            $image->clear();
            $image->destroy();
        } catch (Throwable $exception) {
            return new WP_Error('smitharia_imagick_error', $exception->getMessage());
        }

        update_post_meta($attachment_id, self::META_KEY, $relative_output);

        return $relative_output;
    }

    public static function register_tools_page(): void
    {
        add_management_page(
            'Smitharia Images',
            'Smitharia Images',
            'manage_options',
            'smitharia-images',
            array(__CLASS__, 'render_tools_page')
        );
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'tools_page_smitharia-images') {
            return;
        }

        wp_enqueue_script(
            'smitharia-images-admin',
            SMITHARIA_CORE_URL . 'assets/admin.js',
            array(),
            SMITHARIA_CORE_VERSION,
            true
        );
        wp_localize_script('smitharia-images-admin', 'smithariaImages', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smitharia_regenerate_images'),
        ));
    }

    public static function render_tools_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Smitharia filtered images</h1>
            <p>JPEG・PNGの元画像からfiltered画像を5件ずつ安全に再生成します。</p>
            <button type="button" class="button button-primary" id="smitharia-regenerate">再生成を開始</button>
            <p id="smitharia-regenerate-status" aria-live="polite"></p>
        </div>
        <?php
    }

    public static function ajax_regenerate_images(): void
    {
        check_ajax_referer('smitharia_regenerate_images', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $offset = isset($_POST['offset']) ? max(0, absint($_POST['offset'])) : 0;
        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => self::BATCH_SIZE,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ));

        $errors = array();
        foreach ($query->posts as $attachment_id) {
            $result = self::generate_for_attachment((int) $attachment_id);
            if (is_wp_error($result)) {
                $errors[] = sprintf('#%d: %s', $attachment_id, $result->get_error_message());
            }
        }

        $processed = $offset + count($query->posts);
        wp_send_json_success(array(
            'processed' => $processed,
            'total' => (int) $query->found_posts,
            'nextOffset' => $processed,
            'done' => $processed >= (int) $query->found_posts,
            'errors' => $errors,
        ));
    }

    public static function register_rest_field(): void
    {
        register_rest_field('attachment', 'smitharia_filtered_url', array(
            'get_callback' => static function (array $attachment): ?string {
                $relative_path = get_post_meta((int) $attachment['id'], self::META_KEY, true);
                if (!$relative_path) {
                    return null;
                }

                $uploads = wp_upload_dir();
                return trailingslashit($uploads['baseurl']) . ltrim($relative_path, '/');
            },
            'schema' => array(
                'description' => 'Generated Smitharia filtered image URL.',
                'type' => array('string', 'null'),
                'context' => array('view', 'edit'),
                'readonly' => true,
            ),
        ));
    }
}
