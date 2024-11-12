<?php
namespace LWS\WOOREWARDS\Ui\Woocommerce;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add our own metabox to show WooRewards relevant order notes. */
class OrderNote
{
	public static function install()
	{
		// $postType = \LWS\Adminpanel\Tools\Conveniences::isHPOS() ? 'shop_order' : \wc_get_page_screen_id('shop-order'); // 'woocommerce_page_wc-orders';
		\add_action('add_meta_boxes', function($screen='shop_order', $post=false){
			if (\LWS\WOOREWARDS\Ui\Woocommerce\OrderNote::isOrderRelative($post)) {
				$me = new \LWS\WOOREWARDS\Ui\Woocommerce\OrderNote();
				\add_meta_box(
					'woorewards-order-notes',
					__('Loyalty system notes', 'woorewards-lite'),
					array($me, 'eContent'),
					$screen,
					'side', 'default'
				);
			}
		}, 1000, 2); // let wc at above
		add_action('admin_enqueue_scripts', function(){
			\wp_register_style('lws-wr-ordernote', LWS_WOOREWARDS_CSS . '/ordernote.min.css', array(), LWS_WOOREWARDS_VERSION);
		});
	}

	protected static function isOrderRelative($post)
	{
		if (!$post)
			return false;
		elseif (\is_a($post, '\WC_Order'))
			return true;
		elseif (\is_a($post, '\WP_Post') && 'shop_order' == $post->post_type)
			return true;
		else
			return false;
	}

	/**	echo box content
	 * @param $post (\WP_Post|\WC_Order) $post Post or order object. */
	public function eContent($post)
	{
		$orderId = (\is_a($post, '\WC_Order') ? $post->get_id() : $post->ID);
		$notes = \LWS\WOOREWARDS\Core\OrderNote::get($orderId);
		\wp_enqueue_style('lws-wr-ordernote');
		if ($notes) {
			$content = '';
			foreach ($notes as $note) {
				$css = \implode(' ', \apply_filters('lws_woorewards_metabox_order_note_class', array('lws-wr-note'), $note));
				$row = <<<EOT
<li rel="%1\$s" class="{$css}">
	<div class="lws-wr-note-content">%4\$s</div>
	<p class="meta">
		<abbr class="exact-date" title="%2\$s">%3\$s</abbr>
	</p>
</li>
EOT;
				$date = \wc_string_to_datetime($note->comment_date);
				$content .= \apply_filters('lws_woorewards_metabox_order_note_item', sprintf(
					$row,
					\absint($note->comment_ID),
					\esc_attr($date->date('Y-m-d H:i:s')),
					\esc_html(sprintf(
						__('%1$s at %2$s', 'woorewards-lite'),
						$date->date_i18n(\wc_date_format()),
						$date->date_i18n(\wc_time_format())
					)),
					\wpautop(\wptexturize(\wp_kses_post($note->comment_content)))
				), $note);
			}
			echo "<ul class='woorewards-notes'>{$content}</ul>";
		} else {
			echo sprintf(
				'<ul class="woorewards-notes"><li class="no-items">%s</li></ul>',
				__( 'There are no notes yet.', 'woorewards-lite')
			);
		}
	}
}