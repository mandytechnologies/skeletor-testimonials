<?php
namespace Mandy\Skeletor_Testimonials\Posttype;

use Mandy\Skeletor_Testimonials;

// make sure the required prerequisite class exists
if (!class_exists('\\Mandy\\Custom_Post_Type')) {
	$plugin_data = Skeletor_Testimonials::instance()->get_info();
	error_log(sprintf('%s : Missing the mandy-custom-post-types plugin', $plugin_data['Name']));
	add_action( 'admin_notices', function() use ($plugin_data){
		?>
		<div class="error notice">
			<p><?php printf('🚨 <strong>%s</strong> requires the Mandy Custom Post Types plugin to add the testimonial posttype.', $plugin_data['Name']); ?></p>
		</div>
		<?php

	});
	return;
}

class Testimonial extends \Mandy\Custom_Post_Type {
	/** @var string */
	static $name = Skeletor_Testimonials::POSTTYPE;

	/** @var array */
	const TINYMCE_TOOLBAR = [
		'bold',
		'italic',
		'underline',
		'link',
		'unlink',
		'pastetext',
	];

	/** @var string */
	static $placeholder_text = 'Enter a descriptive testimonial name';

	/** @var array */
	static $labels = [
		'menu_name' => 'Testimonials',
		'singular'  => 'Testimonial',
		'plural'    => 'Testimonials',
	];


	/** @var array */
	static $options = [
		'has_archive'       => 'testimonials',
		'public'            => false,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => false,
		'hierarchical'      => false,
		'menu_position'     => 20,
		'menu_icon'         => 'dashicons-testimonial',
		'rewrite'           => [
			'slug'       => 'testimonial',
			'with_front' => false,
		],
		'supports'          => [
			'title',
			'custom-fields',
			'thumbnail',
			'revisions',
			'editor'
		],
	];

	/** @var array */
	static $taxonomies = [];

	/**
	 * key/value pairs should be slug => label. If the slug matches a
	 * taxonomy then the column should automatically populate with terms from
	 * that taxonomy. If not, implement an admin_column_{slug}($column, $post)
	 * function in this class that echoes out what the column should contain.
	 *
	 * @var array
	 **/
	/** @var array */
	static $admin_columns = [
		'name'      => 'Attributee Name',
		'cpt_title' => 'Attributee Title',
	];

	public static function admin_column_name($column, $post) {
		echo get_post_meta($post->ID, 'name', true);
	}

	public static function admin_column_cpt_title($column, $post) {
		echo get_post_meta($post->ID, 'title', true);
	}

	/**
	 * Passed into acf_add_local_field_group() during the acf/init action.
	 * Leave the location paramter out, it will automatically be set for you!
	 *
	 * @var array
	 */
	static $field_group = [
		'key'      => 'Skeletor_Testimonials__group_testimonial_options',
		'title'    => 'Testimonial Options',
		'fields'   => [
			[
				'key'   => 'field_testimonial_cpt_name',
				'label' => 'Attributee Name',
				'type'  => 'text',
				'name'  => 'name',
			],
			[
				'key'   => 'field_testimonial_cpt_title',
				'label' => 'Attributee Title',
				'type'  => 'text',
				'name'  => 'title',
			],
			[
				'key'   => 'field_testimonial_cpt_company',
				'label' => 'Company',
				'type'  => 'text',
				'name'  => 'company',
			],
			[
				'key'           => 'field_testimonial_cpt_video',
				'label'         => 'Video Clip',
				'name'          => 'video',
				'type'          => 'link',
				'default_value' => [
					'title'  => 'Watch Video',
					'url'    => '#',
					'target' => '_blank',
				],
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'vtl_testimonial',
				],
			],
		],
		'style'    => 'seamless',
	];

	static function disable_block_editor($current_status, $post_type) {
		if ($post_type === self::$name) {
			return false;
		}

		return $current_status;
	}

	static function tiny_mce_before_init($tinymce) {
		if (get_post_type() !== self::$name) {
			return $tinymce;
		}

		for ($i=1; $i<=4; $i++) {
			if (!isset($tinymce["toolbar{$i}"])) {
				continue;
			}

			if ($i == 1) {
				$toolbar = self::TINYMCE_TOOLBAR;
			} else {
				$toolbar = [];
			}

			$tinymce["toolbar{$i}"] = implode(',', $toolbar);
		}

		return $tinymce;
	}

	public static function get_post_testimonials($post = null) : array {
		$post = get_post($post);
		if (!isset($post->ID) || !$post->ID) {
			return [];
		}

		return get_posts([
			'post_type'   => self::$name,
			'post_status' => 'publish',
			'meta_query'  => [
				[
					'value'   => "\"{$post->ID}\";",
					'compare' => 'LIKE',
					'key'     => 'related_posts',
				],
			],
		]);
	}

	/**
	 * Remove the 'Add Media' and 'Add Form' buttons from TinyMCE. We don't
	 * want images in the testimonial text!
	 *
	 * @param array $settings
	 * @return array
	 */
	static function editor_settings($settings) {
		if (get_post_type() !== self::$name) {
			return $settings;
		}

		$settings['media_buttons'] = false;

		return $settings;
	}

    /** @var array */
    public static $admin_columns_to_remove = ['wpseo-score', 'wpseo-score-readability'];

	/** @var array */
	static $options_field_group = [];

	/**
	 * registers the posttype
	 *
	 * hooks into skeletor_resource_center_register_posttype
	 * if the filter returns false, the posttype will not be registered
	 *
	 * @return void
	 */
	public static function initialize() {
		if (apply_filters('skeletor_testimonials_register_posttypes', true)) {
			parent::initialize();
		}

		add_filter('wp_editor_settings', [__CLASS__, 'editor_settings']);
		add_filter('tiny_mce_before_init', [__CLASS__, 'tiny_mce_before_init'], PHP_INT_MAX);
		add_filter('use_block_editor_for_post_type', [__CLASS__, 'disable_block_editor'], 10, 2 );
	}
}

\Mandy\Skeletor_Testimonials\Posttype\Testimonial::initialize();
