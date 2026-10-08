<?php

defined('ABSPATH') || exit;

final class Smitharia_Image_Filter
{
    private const META_KEY = '_smitharia_filtered_path';
    private const STATUS_META_KEY = '_smitharia_filtered_status';
    private const ATTEMPTS_META_KEY = '_smitharia_filtered_attempts';
    private const ERROR_META_KEY = '_smitharia_filtered_error';
    private const GENERATED_AT_META_KEY = '_smitharia_filtered_generated_at';
    private const LOCK_META_KEY = '_smitharia_filtered_lock';
    private const CRON_HOOK = 'smitharia_generate_filtered_image';
    private const MAX_ATTEMPTS = 3;
    private const LOCK_TIMEOUT = 900;
    private const BATCH_SIZE = 5;

    public static function init(): void
    {
        add_filter('wp_generate_attachment_metadata', array(__CLASS__, 'queue_after_upload'), 20, 2);
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_queued_attachment'), 10, 1);
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

    public static function queue_after_upload(array $metadata, int $attachment_id): array
    {
        self::queue_attachment($attachment_id);

        return $metadata;
    }

    /**
     * Queue one attachment without making the upload request wait for Imagick.
     */
    public static function queue_attachment(int $attachment_id, int $delay = 5, bool $reset_attempts = true): bool
    {
        if (!self::is_supported_attachment($attachment_id)) {
            return false;
        }

        $args = array($attachment_id);
        if (wp_next_scheduled(self::CRON_HOOK, $args) !== false) {
            return true;
        }

        if ($reset_attempts) {
            delete_post_meta($attachment_id, self::ATTEMPTS_META_KEY);
            delete_post_meta($attachment_id, self::ERROR_META_KEY);
        }

        update_post_meta($attachment_id, self::STATUS_META_KEY, 'queued');
        $scheduled = wp_schedule_single_event(
            time() + max(1, $delay),
            self::CRON_HOOK,
            $args,
            true
        );

        if (is_wp_error($scheduled) || !$scheduled) {
            $message = is_wp_error($scheduled)
                ? $scheduled->get_error_message()
                : 'WordPress could not schedule the image job.';
            update_post_meta($attachment_id, self::STATUS_META_KEY, 'failed');
            update_post_meta($attachment_id, self::ERROR_META_KEY, $message);
            error_log('Smitharia filtered image queue: ' . $message);
            return false;
        }

        return true;
    }

    public static function process_queued_attachment(int $attachment_id): void
    {
        if (!self::is_supported_attachment($attachment_id)) {
            return;
        }

        if (!self::acquire_lock($attachment_id)) {
            self::schedule_retry($attachment_id, 60);
            return;
        }

        $attempts = (int) get_post_meta($attachment_id, self::ATTEMPTS_META_KEY, true) + 1;
        update_post_meta($attachment_id, self::ATTEMPTS_META_KEY, $attempts);
        update_post_meta($attachment_id, self::STATUS_META_KEY, 'processing');

        // If PHP terminates unexpectedly, this event recovers the stale lock later.
        wp_schedule_single_event(
            time() + self::LOCK_TIMEOUT + 60,
            self::CRON_HOOK,
            array($attachment_id)
        );

        $result = self::generate_for_attachment($attachment_id);

        self::release_lock($attachment_id);
        wp_clear_scheduled_hook(self::CRON_HOOK, array($attachment_id));

        if (!is_wp_error($result)) {
            return;
        }

        error_log('Smitharia filtered image: ' . $result->get_error_message());
        if ($attempts < self::MAX_ATTEMPTS) {
            self::schedule_retry($attachment_id, 60 * (2 ** ($attempts - 1)));
        }
    }

    /**
     * @return string|WP_Error Relative path below wp-content/uploads.
     */
    public static function generate_for_attachment(int $attachment_id)
    {
        update_post_meta($attachment_id, self::STATUS_META_KEY, 'processing');

        if (!extension_loaded('imagick')) {
            return self::record_failure($attachment_id, new WP_Error('smitharia_no_imagick', 'Imagick extension is not available.'));
        }

        $source = get_attached_file($attachment_id);
        if (!$source || !is_readable($source)) {
            return self::record_failure($attachment_id, new WP_Error('smitharia_missing_source', 'Source image is not readable.'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/jpeg', 'image/png'), true)) {
            return self::record_failure($attachment_id, new WP_Error('smitharia_unsupported_type', 'Only JPEG and PNG files are supported.'));
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return self::record_failure($attachment_id, new WP_Error('smitharia_upload_error', $uploads['error']));
        }

        $source_normalized = wp_normalize_path($source);
        $base_normalized = trailingslashit(wp_normalize_path($uploads['basedir']));
        if (strpos($source_normalized, $base_normalized) !== 0) {
            return self::record_failure($attachment_id, new WP_Error('smitharia_invalid_source', 'Source image is outside the uploads directory.'));
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
            return self::record_failure($attachment_id, new WP_Error('smitharia_create_directory', 'Could not create the filtered image directory.'));
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
            return self::record_failure($attachment_id, new WP_Error('smitharia_imagick_error', $exception->getMessage()));
        }

        update_post_meta($attachment_id, self::META_KEY, $relative_output);
        update_post_meta($attachment_id, self::STATUS_META_KEY, 'complete');
        update_post_meta($attachment_id, self::GENERATED_AT_META_KEY, time());
        delete_post_meta($attachment_id, self::ERROR_META_KEY);

        return $relative_output;
    }

    private static function is_supported_attachment(int $attachment_id): bool
    {
        if (get_post_type($attachment_id) !== 'attachment') {
            return false;
        }

        return in_array(
            get_post_mime_type($attachment_id),
            array('image/jpeg', 'image/png'),
            true
        );
    }

    private static function record_failure(int $attachment_id, WP_Error $error): WP_Error
    {
        update_post_meta($attachment_id, self::STATUS_META_KEY, 'failed');
        update_post_meta($attachment_id, self::ERROR_META_KEY, $error->get_error_message());
        return $error;
    }

    private static function schedule_retry(int $attachment_id, int $delay): void
    {
        update_post_meta($attachment_id, self::STATUS_META_KEY, 'queued');
        $args = array($attachment_id);
        if (wp_next_scheduled(self::CRON_HOOK, $args) !== false) {
            return;
        }

        $scheduled = wp_schedule_single_event(
            time() + max(1, $delay),
            self::CRON_HOOK,
            $args,
            true
        );
        if (is_wp_error($scheduled) || !$scheduled) {
            $message = is_wp_error($scheduled)
                ? $scheduled->get_error_message()
                : 'WordPress could not schedule an image retry.';
            update_post_meta($attachment_id, self::STATUS_META_KEY, 'failed');
            update_post_meta($attachment_id, self::ERROR_META_KEY, $message);
        }
    }

    private static function acquire_lock(int $attachment_id): bool
    {
        $locked_at = (int) get_post_meta($attachment_id, self::LOCK_META_KEY, true);
        if ($locked_at && $locked_at > (time() - self::LOCK_TIMEOUT)) {
            return false;
        }

        if ($locked_at) {
            delete_post_meta($attachment_id, self::LOCK_META_KEY);
        }

        return add_post_meta($attachment_id, self::LOCK_META_KEY, time(), true) !== false;
    }

    private static function release_lock(int $attachment_id): void
    {
        delete_post_meta($attachment_id, self::LOCK_META_KEY);
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
        $counts = self::get_status_counts();
        ?>
        <div class="wrap">
            <h1>Smitharia filtered images</h1>
            <p>新規画像は保存後にバックグラウンド処理されます。通常は再生成ボタンを押す必要はありません。</p>
            <p>
                自動処理：待機中 <?php echo esc_html((string) $counts['queued']); ?>件 ／
                実行中 <?php echo esc_html((string) $counts['processing']); ?>件 ／
                完了 <?php echo esc_html((string) $counts['complete']); ?>件 ／
                失敗 <?php echo esc_html((string) $counts['failed']); ?>件
            </p>
            <p>問題がある場合のみ、JPEG・PNGの元画像からfiltered画像を5件ずつ再生成します。</p>
            <button type="button" class="button button-primary" id="smitharia-regenerate">再生成を開始</button>
            <p id="smitharia-regenerate-status" aria-live="polite"></p>
        </div>
        <?php
    }

    /**
     * @return array<string, int>
     */
    private static function get_status_counts(): array
    {
        $counts = array(
            'queued' => 0,
            'processing' => 0,
            'complete' => 0,
            'failed' => 0,
        );
        $attachment_ids = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));

        foreach ($attachment_ids as $attachment_id) {
            $status = get_post_meta((int) $attachment_id, self::STATUS_META_KEY, true);
            if (!$status && get_post_meta((int) $attachment_id, self::META_KEY, true)) {
                $status = 'complete';
            }
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
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
