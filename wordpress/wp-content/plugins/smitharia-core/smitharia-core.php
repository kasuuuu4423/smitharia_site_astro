<?php
/**
 * Plugin Name: Smitharia Core
 * Description: Smitharia 固有の画像生成、REST API、GitHub Actions 通知をまとめます。
 * Version: 1.5.0
 * Author: smitharia
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('SMITHARIA_CORE_VERSION', '1.5.0');
define('SMITHARIA_CORE_DIR', plugin_dir_path(__FILE__));
define('SMITHARIA_CORE_URL', plugin_dir_url(__FILE__));

require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-image-filter.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-rest-api.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-acf-rest-api.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-project-fields.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-project-defaults.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-headless.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-limited-access.php';
require_once SMITHARIA_CORE_DIR . 'includes/class-smitharia-github-dispatch.php';

Smitharia_Image_Filter::init();
Smitharia_REST_API::init();
Smitharia_ACF_REST_API::init();
Smitharia_Project_Fields::init();
Smitharia_Project_Defaults::init();
Smitharia_Headless::init();
Smitharia_Limited_Access::init();
Smitharia_GitHub_Dispatch::init();

register_activation_hook(__FILE__, array('Smitharia_Image_Filter', 'activate'));
