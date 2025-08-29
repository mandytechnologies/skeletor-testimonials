<?php

namespace Mandy\Skeletor_Testimonials\Blocks;

use \Mandy\Skeletor_Block;
use \Mandy\Skeletor_Testimonials;

if (!class_exists('\Mandy\Skeletor_Block')) {
    return;
}

class Testimonial_Card extends Skeletor_Block {
    public static $title = 'Testimonial Card';
    public static $name = 'testimonial_card';

	/** @var array */
	public static $field_group = [
		[
			'label'      => 'Testimonial Post',
			'name'       => 'object',
			'type'       => 'post_object',
			'post_type'  => Skeletor_Testimonials::POSTTYPE,
			'allow_null' => 0,
		],
    ];

    /** @var array */
    public static $inner_blocks = [];

    /** @var array */
    public static $block_settings = [
        'description' => 'A card to display details from a post in the Testimonials Posttype.',
    ];

	/**
	 * get the 'post' from the object property
	 * 
	 * if not set or empty, check if the global post
	 * is a 'post' posttype and if so use that
	 *
	 * @param array $block_data
	 * @return WP_Post|Integer
	 */
	protected static function _get_post($block_data) {
		global $post;
		if (isset($block_data['object']) && $block_data['object']) {
			return $block_data['object'];
		}

		if (get_post_type($post) !== Skeletor_Testimonials::POSTTYPE) {
			return;
		}
		
		return $post;
	}

    protected static function _get_id($block_data) {
		$post_id = self::_get_post($block_data);
		if (!$post_id) {
			return null;
		}

		if (is_int($post_id)) {
			// do nothing we're good
		} elseif (is_object($post_id)) {
			$post_id = $post_id->ID;
		} else {
			// what even is it?!?
			$post_id = null;
		}

        return $post_id;
    }

    public static function block_class($class_list, $block_data) {
        // add generic class so various cards can cascade
        $class_list[] = 'testimonial-card';

        // if we don't have the post, then bail
        if (!isset($block_data['object']) || empty($block_data['object'])) {
            return $class_list;
        }

        $post = \get_post($block_data['object']);
        if (!$post) {
            return $class_list;
        }

        if ($post_type = \get_post_type($post)) {
            $class_list[] = sprintf('post-type-%s', $post_type);
        }

        if (!\get_post_meta($post->ID, '_thumbnail_id', true)) {
            $class_list[] = 'no-thumbnail';
        }

        return $class_list;
    }

    protected static function _get_featured_image($id, $size = 'large', $additional_container_classes = []) {
        $post_thumb_id = \get_post_thumbnail_id($id);
        $post_thumb_atts = [];
		$focal_point = false;

        if (class_exists('\Mandy\SkeletorFeaturedImageFocalPoint')) {
            $focal_point = \get_post_meta($id, \Mandy\SkeletorFeaturedImageFocalPoint::FOCAL_POINT, true);
            if ($focal_point) {
                $post_thumb_atts['style'] = sprintf(
                    'object-position: %s%% %s%%',
                    100 * $focal_point['x'],
                    100 * $focal_point['y']
                );
            }
        }

        $container_classes = array_merge(
            ['wp-block-image'],
            $additional_container_classes
        );
        $container_classes[] = sprintf('size-%s', $size);

        $img = sprintf(
            '<figure class="%s">%s</figure>',
            implode(' ', $container_classes),
            \wp_get_attachment_image($post_thumb_id, $size, false, $post_thumb_atts)
        );

        return \render_block([
            'blockName'    => 'core/image',
            'attrs'        => [
                'id'         => $post_thumb_id,
                'sizeSlug'   => $size,
                'focalPoint' => $focal_point,
            ],
            'innerHTML'    => $img,
            'innerContent' => [$img],
        ]);
    }

    public static function before_render($block_data) : array {
        $id = self::_get_id($block_data);
        // if we have no post, bail
        if (!$id) {
            return $block_data;
        }

        // if the posttype isn't of an blog post, bail
        $post_type = \get_post_type($id);
        if ($post_type !== Skeletor_Testimonials::POSTTYPE) {
            return $block_data;
        }

        $block_data['has_object'] = true;

		$block_data['name'] = get_post_meta($id, 'name', true);
		$block_data['title'] = get_post_meta($id, 'title', true);
		$block_data['company'] = get_post_meta($id, 'company', true);
		$url = get_post_meta($id, 'video', true);

        if ($image = self::_get_featured_image($id)) {
            $block_data['image'] = $image;
        }

		$block_data['testimonial'] = \get_the_content(null, false, $id);

		$button_classes = ['wp-block-button', 'testimonial-action-button', 'video-link', 'is-content-justification-center', 'is-layout-flex'];
		/**
		 * filter: `skeletor_testimonial_card_button_classes`
		 * @param array $classes
		 * @param int $post_id The ID of the testimonial post
		 */
		$button_classes = \apply_filters('skeletor_testimonial_card_button_classes', $button_classes, $id);
		if (!is_array($button_classes)) {
			$button_classes = [$button_classes];
		}
		$block_data['button_class_string'] = implode(' ', $button_classes);

		if ($url) {
			$block_data['cta'] = [
				'url'   => \is_admin() ? '#' : $url['url'],
				'title' => $url['title'],
				'class' => implode(' ', ['wp-block-button__link']),
			];
		}

		$block_data['notification_message'] = 'Select a testimonial post to display. This section will not display on the front-end of the website.';

        return $block_data;
    }
}

\add_action('after_setup_theme', ['\\Mandy\\Skeletor_Testimonials\\Blocks\\Testimonial_Card', 'init']);
