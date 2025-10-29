<?php

namespace Mandy;

/**
 * Plugin Name:           QB - Testimonials
 * Plugin URI:            https://github.com/mandytechnologies/skeletor-testimonials
 * Description:           Adds testimonials posttype and block to be used in conjunction with the Skeletor theme
 * Version:               1.0.0
 * Requires PHP:          7.0
 * Requires at least:     6.1.0
 * Tested up to:          6.8.2
 * Author:                Quick Build
 * Author URI:            https://www.quickbuildwebsite.com/
 * License:               GPLv2 or later
 * License URI:           https://www.gnu.org/licenses/
 * Text Domain:           qb-testimonials
 * 
 * @package           Skeletor_Testimonials
 */

if (!class_exists('\Mandy\Skeletor_Testimonials')) {
    class Skeletor_Testimonials {
        /**
         * @var string the posttype slug targetted by the plugin for the template logic
         */
        const POSTTYPE = 'vtl_testimonial';

        /**
         * @var string a slug for the plugin. Will be used for classnames, et al.
         */
        const SLUG = 'skeletor-testimonials';

        /**
         * @var string the maximum number of related posts to show
         */
        const RELATED_POSTS_COUNT = 2;

        /**
         * The single instance of the class.
         *
         * @var    object
         * @access private
         * @since  0.1.0
         */
        private static $instance;

        /**
         * The main plugin file.
         *
         * @var    string
         * @access public
         * @since  0.1.0
         */
        public $file;

        /**
         * The main directory of the plugin
         *
         * @var string
         * @access public
         * @since  0.1.1
         */
        public $directory;

        public static function instance() {
            if (!isset(self::$instance) || !(self::$instance instanceof Skeletor_Testimonials)) {
                self::$instance = new Skeletor_Testimonials;
            }

            return self::$instance;
        }

        /**
         * handler for retrieving this plugin's info via the readme block
         *
         * @return array
         */
        public function get_info() {
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            return \get_plugin_data($this->file);
        }

        public function __construct() {
            $this->directory = \plugin_dir_path(__FILE__);
            $this->file = __FILE__;

            $this->_add_handlers();
            $this->_add_includes();
            $this->_include_block_definitions();

            \add_action('after_setup_theme', [__CLASS__, 'add_posttypes']);

            register_activation_hook(__FILE__, [__CLASS__, 'plugin_activated']);
        }

        /**
         * since we are adding a posttype
         *
         * @return void
         */
        public static function plugin_activated() {
            // if we're not registering the posttype, we shouldn't need to flush the rewrite rules
            if (apply_filters('skeletor_testimonials_register_posttypes', true)) {
                flush_rewrite_rules();
            }
        }

        /**
         * hooked into `after_setup_theme`
         *
         * we wait until after_setup_theme so that we can insure
         * that the required plugin (mandy custom post types)
         * is already instantiated
         *
         * @return void
         */
        public static function add_posttypes() {
            // if we're not registering the posttypes, we don't need to move forward with this function
            if (apply_filters('skeletor_testimonials_register_posttypes', true) !== true) {
                return;
            }

            require_once self::instance()->directory . 'includes/posttypes/class-testimonial.php';
        }


        protected function _add_handlers() {
            \add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
            \add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_block_editor_assets']);
            \add_filter('mandy_mustache_view_path', [__CLASS__, 'add_mustache_view_path']);
        }

        /**
         * pull in all the necessary class files from the plugin
         *
         * @return void
         */
        protected function _add_includes() {
            // add here if needed
        }

        /**
         * pull in our block definitions
         *
         * @return void
         */
        protected function _include_block_definitions() {
            if (!class_exists('\Mandy\Skeletor_Block')) {
                \error_log(__CLASS__ . ' : Missing the Skeletor_Block plugin');

                \add_action('admin_notices', function () {
                    $plugin_data = self::instance()->get_info();
?>
                    <div class="error notice">
                        <p><?php printf('🚨 <strong>%s</strong> requires the Skeletor Block plugin to add blocks to the Block Editor.', $plugin_data['Name']); ?></p>
                    </div>
<?php

                });
                return;
            }

            require_once($this->directory . 'includes/blocks/testimonial-card.php');
        }

        /**
         * enqueue any front end assets needed for this plugin
         *
         * @return void
         */
        public static function enqueue_frontend_assets() {
            self::_enqueue_script_file();
            self::_enqueue_style_file();
        }

        /**
         * a singular function for enqueuing the front-end JS
         * needed for proper execution of this plugin
         *
         * @return void
         */
        private static function _enqueue_script_file() {
            $fileslug = 'skeletor-testimonials'; // the asset name without the extension
            $asset = require_once(sprintf('%s/build/%s.asset.php', self::instance()->directory, $fileslug));
            if (!$asset) {
                return;
            }

            \wp_enqueue_script(
                'skeletor_testimonials',
                sprintf('%s/build/%s.js', \plugin_dir_url(self::instance()->file), $fileslug),
                $asset['dependencies'],
                $asset['version'],
                true
            );
        }
        /**
         * a singular function for enqueuing the front-end CSS
         * needed for base styling of this plugin
         *
         * @return void
         */
        private static function _enqueue_style_file() {
            $css_file_path = self::instance()->directory . '/build/skeletor-testimonials.css';
            if (file_exists($css_file_path)) {
                \wp_enqueue_style(
                    'skeletor_testimonials',
                    plugin_dir_url(self::instance()->file) . '/build/skeletor-testimonials.css',
                    [],
                    filemtime($css_file_path)
                );
            }
        }

        /**
         * enqueue any assets needed for the backend
         *
         * @return void
         */
        public static function enqueue_block_editor_assets() {
            if (($screen = get_current_screen()) && $screen->is_block_editor) {
                self::_enqueue_style_file();
            }
        }

        /**
         * hooks into mandy_mustache_view_path filter
         * to add the local view path
         *
         * @param array $paths
         * @return array
         */
        public static function add_mustache_view_path($paths) {
            $local_mustache_path = self::instance()->directory . 'views';
            $local_mustache_path = \apply_filters('skeletor_testimonials_view_path', $local_mustache_path);
            if (!in_array($local_mustache_path, $paths, true)) {
                $paths[] = $local_mustache_path;
            }

            return $paths;
        }
    }

    Skeletor_Testimonials::instance();
}

define('MANDY_TESTIMONIALS_VERSION', '1.0.0');

require 'plugin-update-checker/plugin-update-checker.php';

$update_checker = \Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/mandytechnologies/skeletor-testimonials',
	__FILE__,
	'skeletor-testimonials'
);

require_once( 'includes/class-plugin.php' );
