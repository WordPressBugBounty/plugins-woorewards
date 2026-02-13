<?php

namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) exit();


/** Lets customer choose an Icon in the defined icons font */
class IconPicker extends \LWS\Adminpanel\Pages\Field
{
	public static function compose($id, $extra = null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function html()
	{
		/** Use LWS Icons File by default */
		$file = $this->getExtraValue('file', LWS_ADMIN_PANEL_CSS . '/lws_icons.css');
		$prefix = $this->getExtraValue('prefix', 'lws-icon-');
		$selectors = array();

		$content = \file_get_contents($file);
		if (!$content) {
			if (defined('WP_DEBUG') && WP_DEBUG) error_log("<h1>No icons CSS file found or no content</h1><h2>{$file}</h2>"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		} else {
			$selectors = $this->getIconSelectors($content, $prefix);
			if (!$selectors) {
				if (defined('WP_DEBUG') && WP_DEBUG) error_log("<h1>No icons found in CSS file</h1><h2>{$file}</h2>"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		$value = $this->readOption();
		$icons = '';
		foreach ((array)$selectors as $selector) {
			$class = (($selector == $value) ? ' selected' : '');
			$icons .= "<div class='lwsip_icon_choice lwsip-icon-value {$selector}{$class}' data-value='{$selector}'></div>";
		}

		\wp_enqueue_script('lws-icon-picker');
		$filled = ($value ? ' filled' : '');

		$position = $this->getExtraValue('position', 'below');

?>
		<div class='lws-icon-picker lwsip_master'>
			<input
				type='hidden'
				class='lws_adminpanel_icon_value lws-force-confirm'
				name='<?php echo esc_attr($this->m_Id) ?>'
				value='<?php echo esc_attr($value) ?>'
			/>
			<div class='lwsip-wrapper'>
				<div class='lwsip-main'>
					<div class='lwsip-show-icon <?php echo esc_attr($value) . ($value ? ' filled' : '') ?>'>
						<div class='remove-btn lws-icon-cross'></div>
					</div>
					<div class='lwsip-popup-btn lwsip_button'><?php \esc_html_e("Pick an Icon", 'woorewards') ?></div>
				</div>
				<div class='lwsip-popup-wrapper hidden <?php echo esc_attr($position) ?>'>
					<div class='lwsip-popup'>
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $icons
						?>
					</div>
				</div>
			</div>
		</div>
<?php
	}

	private function getIconSelectors($css, $iconpattern = 'lws-icon-')
	{
		$matches = false;
		$pattern = '/\.(?<selector>' . $iconpattern . '[^:]+)::before/m';

		if (\preg_match_all($pattern, $css, $matches)) {
			return $matches['selector'];
		} else {
			return array();
		}
	}
}