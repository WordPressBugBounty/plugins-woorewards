<?php

namespace LWS\WOOREWARDS\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();


/** Provide a widget to let display points depending on pool selection. */
class PointsDisplayer extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;

	public static function install()
	{
		self::register(__CLASS__);
		$me = new self(false);
		\add_shortcode('wr_show_points', array($me, 'showPoints'));
		/** Backwards compatibility */
		\add_shortcode('wr_simple_points', array($me, 'showPointsOnly'));

		\add_filter('lws_adminpanel_stygen_content_get_' . 'wr_display_points', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-show-points', LWS_WOOREWARDS_CSS . '/templates/displaypoints.css?stygen=lws_woorewards_displaypoints_template', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-show-points');
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget = true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_pointsdisplayer',
				__("MyRewards Points Displayer", 'woorewards-lite'),
				array(
					'description' => __("Let your customers see their points.", 'woorewards-lite')
				)
			);
		}
	}

	/** ensure all required fields exist. */
	function update($new_instance, $old_instance)
	{
		$new_instance = $this->parseArgs($new_instance, true);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards Show Points - title", $new_instance['description']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards Show Points - details", $new_instance['more_details_url']);
		return $new_instance;
	}

	protected function defaultArgs()
	{
		return array(
			'title'				=> '',
			'description'		=> '',
			'more_details_url'	=> '',
			'show_currency'		=> ''
		);
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($instance, $withPoolArgs = false)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());
		if (!isset($instance['system'])) {
			if (isset($instance['pool_name']))
				$instance['system'] = $instance['pool_name'];
			else if (isset($instance['pool']))
				$instance['system'] = $instance['pool'];
		}
		if ($withPoolArgs)
			$instance = \array_merge(array('system' => '', 'shared' => '', 'force' => ''), $instance);
		return $instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = $this->parseArgs($instance, true);

		// title
		$this->eformFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-lite'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Current Points", "frontend", 'woorewards-lite'))
		);

		// description
		$this->eformFieldText(
			$this->get_field_id('description'),
			__("Header", 'woorewards-lite'),
			$this->get_field_name('description'),
			\esc_attr($instance['description']),
			\esc_attr(\get_option('lws_woorewards_displaypoints_title'))
		);

		// detail page redirect button
		if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) {
			$options = array();
			foreach (\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool)
				$options[$pool->getName()] = $pool->getOption('display_title');

			// pool
			$this->eformFieldSelect(
				$this->get_field_id('system'),
				__("Select a Loyalty System", 'woorewards-lite'),
				$this->get_field_name('system'),
				$options,
				$instance['system']
			);

			// force display
			$this->eformFieldCheckbox(
				$this->get_field_id('force'),
				__("Force display for all users", 'woorewards-lite'),
				$this->get_field_name('force'),
				\esc_attr($instance['force'])
			);


			$this->eformFieldText(
				$this->get_field_id('more_details_url'),
				__("A <i>More details</i> page URL", 'woorewards-lite'),
				$this->get_field_name('more_details_url'),
				\esc_attr($instance['more_details_url']),
				\esc_attr(\LWS\Adminpanel\Tools\Conveniences::isWC() ? \wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount')) : '')
			);
		}
		// display currency
		$this->eformFieldCheckbox(
			$this->get_field_id('show_currency'),
			__("Display the Points currency", 'woorewards-lite'),
			$this->get_field_name('show_currency'),
			\esc_attr($instance['show_currency'])
		);
	}

	/**	If no 'description' set, use those defined in stygen.
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 *	@see showPoints()
	 * 	Display the widget,
	 *	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());
		$atts = $instance;
		$atts['title'] = $instance['description'];
		$content = $this->showPoints($atts);
		if ($content) {
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', $instance['title'] ? $instance['title'] : _x("Current Points", "frontend", 'woorewards-lite'), $instance);
			echo $args['after_title'];
			echo $content;
			echo $args['after_widget'];
		}
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$snippet = $this->showPoints();
		$this->stygen = false;
		return $snippet;
	}

	/** @brief shortcode [wr_show_points]
	 *	Display a stylable point presentation for current user.
	 *	MyRewards Standard : Automatically display the prefab pool points
	 *	MyRewards Pro : Display points for the selected pool or stack
	 *	@code
	 *	[wr_show_points system="<systemname>" title="<my Own Title>" force="<force>" more_details_url="<more details button url>"]
	 *	system = loyalty system
	 *  force  = don't check if user has rights on the pool
	 *	@endcode */
	public function showPoints($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_show_points');
		if ($userId || $this->stygen) {
			$atts = $this->parseArgs($atts);

			if ($this->stygen) {
				$pointstotal = rand(42, 128) . __('Points', 'woorewards-lite');
				$poolname = '';
			} else {
				$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
				if (!($pools && $pools->count()))
					return false;

				$this->enqueueScripts();
				// We're only interested in the first pool
				$pool = $pools->first();
				$pointstotal = $pool->getPoints($userId);
				$poolname = $pool->getName();

				if ($atts['show_currency']) {
					$pointstotal = \LWS_WooRewards::formatPointsWithSymbol($pointstotal, $poolname);
				}
			}

			$displaytitle = !empty($atts['title']) ? $atts['title'] : \get_option('lws_woorewards_displaypoints_title', '');
			if (!$this->stygen) {
				$displaytitle = \apply_filters('wpml_translate_single_string', $displaytitle, 'Widgets', "WooRewards Show Points - title");
			}
			if ($displaytitle || $this->stygen) {
				$displaytitle = <<<EOT
				<div class='lwss_selectable lwss_modify lws-displaypoints-label' data-id='lws_woorewards_displaypoints_title' data-type='Header'>
					<div class='lwss_modify_content'>{$displaytitle}</div>
				</div>
EOT;
			}

			$details = '';
			if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) {

				if (!$atts['more_details_url']) {
					$atts['more_details_url'] = \apply_filters('lws_woorewards_displaypoints_detail_url', '', $poolname, $pointstotal, $this->stygen);
				}
				if ($atts['more_details_url']) {
					$href = ($this->stygen ? '#' : \esc_attr($atts['more_details_url']));
					$label = \lws_get_option('lws_woorewards_button_more_details', __("More Details", 'woorewards-lite'));
					if (!$this->stygen) {
						$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "WooRewards Show Points - details");
					}

					$details = <<<EOT
					<div class='lwss_selectable lws-displaypoints-bcont' data-type='Button Line'>
						<a class='lwss_selectable lwss_modify lws-displaypoints-button' data-id='lws_woorewards_button_more_details' data-type='Button' href='{$href}'>
							<span class='lwss_modify_content'>{$label}</span>
						</a>
					</div>
EOT;
				}
			}
			$content = <<<EOT
			<div class='lwss_selectable lws-displaypoints-main' data-type='Main Div'>
				{$displaytitle}
				<div class='lwss_selectable lws-displaypoints-points' data-type='Points'>{$pointstotal}</div>
				{$details}
			</div>
EOT;
		} else {
			$content = \lws_get_option('lws_wooreward_showpoints_nouser', __("Please log in if you want to see your loyalty points", 'woorewards-lite'));
		}
		return $content;
	}

	/** @brief shortcode [wr_simple_points]
	 *	Display a simple point value for current user.
	 *	option raw='on' to return raw value, without span tag.
	 *	usage: All attributes are optionnal
	 *	@code
	 *	[wr_simple_points system="<poolname>"]
	 *	@endcode */
	public function showPointsOnly($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_simple_points');
		if ($userId) {
			$atts = $this->parseArgs($atts);
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			$pool = $pools ? $pools->first() : false;
			if ($pool) {
				$pointstotal = $pool->getPoints($userId);
				$pointstotal = \apply_filters('lws_woorewards_shortcode_show_points', $pointstotal, $atts);
				if (isset($atts['raw']) && $atts['raw']) {
					$content = $pointstotal;
				} else {
					$class = \esc_attr($pool->getName());
					$content = "<span class='lws-wr-simple-points lws-wr-simple-points-{$class}'>{$pointstotal}</span>";
				}
			}
		}
		return $content;
	}
}
