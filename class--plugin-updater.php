<?php

namespace Skeletor;

use \stdClass;

class Plugin_Updater {
    /**
     * The repo's "name" from package.json
     *
     * @var string
     */
    public $plugin_slug;

    /**
     * SemVer version number of the plugin
     *
     * @var string
     */
    public $version;

    /**
     * String key used to store transient update data
     *
     * @var string
     */
    public $cache_key;

    /**
     * @var bool
     */
    public $cache_allowed;

    /**
     * URL for the raw package.json file on Bitbucket, should look like
     * 'https://bitbucket.org/madebymandy/[name]/raw/HEAD/package.json'.
     *
     * @var string
     */
    public $info_url;

    /**
     * Plugin Updater constructor based on the plugin slug, version, and
     * package.json.
     *
     * @param string $slug
     * @param string $version
     * @param string $info_url
     */
    public function __construct($slug, $version, $info_url) {
        $this->info_url = $info_url;
        $this->plugin_slug = $slug;
        $this->version = $version;
        $this->cache_key = sprintf('%s_update', $this->plugin_slug);
        $this->cache_allowed = true;

        add_filter('plugins_api', [$this, 'plugins_api_get_info'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'update']);
        add_action('upgrader_process_complete', [$this, 'purge'], 10, 2);
        add_filter('upgrader_post_install', [$this, 'upgrader_post_install'], PHP_INT_MAX, 3);
    }

    /**
     * Returns a bool indicating whether or not the passed thing is a bad
     * HTTP response.
     *
     * @param mixed $response
     * @return bool
     */
    function _is_bad_response($response) {
        if (is_wp_error($response)) {
            return true;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        return empty($body);
    }

    /**
     * Fetch the package.json file for the plugin (unless we have it cached)
     * and return it as an stdClass
     *
     * @return mixed
     */
    function request_plugin_info() {
        $response = get_transient($this->cache_key);

        if ($response === false || !$this->cache_allowed) {
            $response = wp_remote_get(
                $this->info_url,
                [
                    'timeout' => 10,
                    'headers' => ['Accept' => 'application/json'],
                ],
            );

            if ($this->_is_bad_response($response)) {
                return null;
            }

            set_transient($this->cache_key, $response, DAY_IN_SECONDS);
        }

        $response = json_decode(wp_remote_retrieve_body($response));

        return $response;
    }

    /**
     * Maps the package.json file data to a stdClass that matches the
     * "plugin info" schema
     *
     * @param stdClass $package_json
     * @return stdClass
     */
    function info($package_json) {
        $out = new stdClass();

        $out->slug = isset($package_json->name) ? $package_json->name : '';
        $out->version = isset($package_json->version) ? $package_json->version : '';

        $out->name = isset($package_json->plugin_data->title) ? $package_json->plugin_data->title : '';
        $out->tested = isset($package_json->plugin_data->tested) ? $package_json->plugin_data->tested : '';
        $out->requires = isset($package_json->plugin_data->requires) ? $package_json->plugin_data->requires : '';
        $out->author = isset($package_json->plugin_data->author) ? $package_json->plugin_data->author : '';
        $out->author_profile = isset($package_json->plugin_data->author_profile) ? $package_json->plugin_data->author_profile : '';
        $out->download_link = isset($package_json->plugin_data->download_url) ? $package_json->plugin_data->download_url : '';
        $out->trunk = isset($package_json->plugin_data->download_url) ? $package_json->plugin_data->download_url : '';
        $out->requires_php = isset($package_json->plugin_data->requires_php) ? $package_json->plugin_data->requires_php : '';
        $out->last_updated = isset($package_json->plugin_data->last_updated) ? $package_json->plugin_data->last_updated : '';


        $out->sections = [
            'description'  => isset($package_json->description) ? $package_json->description : '',
        ];

        return $out;
    }

    /**
     * Maps the package.json file data to a stdClass that matches the
     * "plugin update info" schema
     *
     * @param stdClass $package_json
     * @return stdClass
     */
    function update_info($package_json) {
        $ret = new stdClass();

        $ret->slug = $this->plugin_slug;
        $ret->plugin = $this->plugin_slug;
        $ret->new_version = isset($package_json->version) ? $package_json->version : '';
        $ret->tested = isset($package_json->plugin_data->tested) ? $package_json->plugin_data->tested : '';
        $ret->package = isset($package_json->plugin_data->download_url) ? $package_json->plugin_data->download_url : '';

        return $ret;
    }

    /**
     * Filter on 'plugins_api' so that this plugin calls our custom
     * request_plugin_info function instead of wordpress.org
     *
     * @param false|object|array $response
     * @param string $action
     * @param object $args
     * @return false|object|array
     */
    public function plugins_api_get_info($response, $action, $args) {
        if ($action !== 'plugin_information') {
            return $response;
        }

        if ($args->slug !== $this->plugin_slug) {
            return $response;
        }

        $res = $this->request_plugin_info();

        if (!$res) {
            return $response;
        }

        return $this->info($res);
    }

    /**
     * Filter on 'site_transient_update_plugins' to fetch our custom
     * update_info object instead of using wordpress.org.
     *
     * @param stdClass $transient
     * @return stdClass
     */
    public function update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $info = $this->request_plugin_info();

        if (
            $info
            && version_compare($this->version, $info->version, '<')
            && version_compare($info->plugin_data->requires, get_bloginfo('version'), '<=')
            && version_compare($info->plugin_data->requires_php, PHP_VERSION, '<')
        ) {
            $update_info = $this->update_info($info);
            $transient->response[$update_info->plugin] = $update_info;
        }

        return $transient;
    }

    /**
     * Action bound to upgrader_process_complete to purge the cached info
     * after a plugin update.
     *
     * @param \WP_Upgrader $upgrader
     * @param array $options
     * @return void
     */
    public function purge($upgrader, $options) {
        if (
            $this->cache_allowed
            && 'update' === $options['action']
            && 'plugin' === $options['type']
        ) {
            // just clean the cache when new plugin version is installed
            delete_transient($this->cache_key);
        }
    }

    /**
     * The bitbucket zip extracts to a folder that has madebymandy at the
     * beginning and the commit hash on the end. This post install hook is
     * used to correct that to just the repo name.
     *
     * @param bool $response
     * @param array $hook_extra
     * @param array $result
     * @return bool
     */
    function upgrader_post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin'])) {
            return $response;
        }

        $plugin = $hook_extra['plugin'];

        if ($plugin !== $this->plugin_slug) {
            return $response;
        }

        if (!isset($hook_extra['temp_backup']['slug'])) {
            return $response;
        }

        $slug = $hook_extra['temp_backup']['slug'];
        $pattern = '/madebymandy-' . $slug . '-[0-9a-f]+\/?$/i';

        if (!isset($result['remote_destination'])) {
            return $response;
        }

        $remote_destination = $result['remote_destination'];

        if (preg_match($pattern, $remote_destination)) {
            $new_remote_destination = preg_replace($pattern, "{$slug}/", $remote_destination);

            $result = rename($remote_destination, $new_remote_destination);
        }

        return $response;
    }
}
