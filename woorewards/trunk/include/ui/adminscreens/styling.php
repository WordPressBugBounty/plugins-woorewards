<?php

namespace LWS\WOOREWARDS\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Styling
{
	const SIZE_PATTERN = '^[ \t]*(initial|inherit|revert|revert-layer|unset|((\d*\.)?\d+((cm)|(mm)|(in)|(px)|(pt)|(pc)|(Q)|(em)|(ex)|(ch)|(rem)|(vw)|(vh)|(vmin)|(vmax)|(%)){1}(([\t ]+-?(\d*\.)?\d+)((cm)|(mm)|(in)|(px)|(pt)|(pc)|(Q)|(em)|(ex)|(ch)|(rem)|(vw)|(vh)|(vmin)|(vmax)|(%))){0,3}))[\t ]*$';
	const FONT_SIZE_PATTERN = '^[ \t]*(initial|inherit|revert|revert-layer|unset|(\d*\.)?\d+((cm)|(mm)|(in)|(px)|(pt)|(pc)|(Q)|(em)|(ex)|(ch)|(rem)|(vw)|(vh)|(vmin)|(vmax)|(%)))[\t ]*$';

	static function getTab($withRoot=false, $withScript=true)
	{
		$tab = array(
			'id'	=> 'styling',
			'title'	=>  __("Styling", 'woorewards-lite'),
			'icon'	=> 'lws-icon-inkpot',
			'groups' => array(
				'blocks'  => self::getGroupBlocks($withRoot, true, false),
				'buttons' => self::getGroupButtons(false, true, false),
			),
		);
		if ($withScript) {
			$tab['function'] = function() {
				if ($style = \LWS\WOOREWARDS\Ui\AdminScreens\Styling::getInline())
					echo $style;
			};
			self::enqueueScripts();
		}
		return $tab;
	}

	public static function enqueueScripts()
	{
		if (\is_admin()) {
			// wp_enqueue_script should not be called too soon
			if (\did_action('admin_enqueue_scripts')) {
				\wp_enqueue_script('wr-style-preview', LWS_WOOREWARDS_JS . '/styledemo.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
			} else {
				\add_action('admin_enqueue_scripts', function() {
					\wp_enqueue_script('wr-style-preview', LWS_WOOREWARDS_JS . '/styledemo.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
				});
			}
		}
	}

	public static function getGroupBlocks($withRoot=false, $loadValues=false, $withScript=true)
	{
		$preview = <<<EOT
<div class='wr_style_preview' style='background-color:#fff;user-select:none;cursor:pointer;border:1px solid #eee;border-radius:4px;padding:20px;display:flex;justify-content:center; align-items:center;'>
	<div class='wr-wrapper'>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi quis diam in orci vestibulum varius ut eu urna. Nullam eros purus, congue ut sollicitudin a, ultricies a felis.</div>
</div>
EOT;

		$group = array(
			'id'     => 'blocks',
			'icon'   => 'lws-icon-grid-interface',
			'title'  => __("Blocks", 'woorewards-lite'),
			'color'  => '#425981',
			'class'  => 'half',
			'text'   => __("Set the styling of wrapping elements used on front-end elements and shortcodes", 'woorewards-lite'),
			'fields' => array(
				'--wr-block-border-width' => array(
					'id'    => 'lws_wr_styling[--wr-block-border-width]',
					'title' => __('Border Width', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '0px',
						'tooltips'    => sprintf(__("Define the blocks border width. Set 0px for no border. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'--wr-block-border-style' => array(
					'id'    => 'lws_wr_styling[--wr-block-border-style]',
					'title' => __('Border Style', 'woorewards-lite'),
					'type'  => 'lacselect',
					'extra' => array(
						'mode'     => 'select',
						'maxwidth' => '220px',
						'source'   => array(
							array('value' => '',       'label' => __("Inherit", 'woorewards-lite')),
							array('value' => 'none',   'label' => __("None", 'woorewards-lite')),
							array('value' => 'solid',  'label' => __("Solid", 'woorewards-lite')),
							array('value' => 'hidden', 'label' => __("Hidden", 'woorewards-lite')),
							array('value' => 'dotted', 'label' => __("Dotted", 'woorewards-lite')),
							array('value' => 'dashed', 'label' => __("Dashed", 'woorewards-lite')),
							array('value' => 'double', 'label' => __("Double", 'woorewards-lite')),
							array('value' => 'groove', 'label' => __("Groove", 'woorewards-lite')),
							array('value' => 'ridge',  'label' => __("Ridge", 'woorewards-lite')),
							array('value' => 'inset',  'label' => __("Inset", 'woorewards-lite')),
							array('value' => 'outset', 'label' => __("Outset", 'woorewards-lite')),
						),
					)
				),
				'--wr-block-border-radius' => array(
					'id'    => 'lws_wr_styling[--wr-block-border-radius]',
					'title' => __('Border Radius', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '0px',
						'tooltips'    => sprintf(__("Define the blocks border radius. Set 0px for no radius. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'--wr-block-border-color' => array(
					'id'    => 'lws_wr_styling[--wr-block-border-color]',
					'title' => __('Border Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks border color", 'woorewards-lite'),
					)
				),
				'--wr-block-background-color' => array(
					'id'    => 'lws_wr_styling[--wr-block-background-color]',
					'title' => __('Background Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks background color", 'woorewards-lite'),
					)
				),
				'--wr-block-font-size' => array(
					'id'    => 'lws_wr_styling[--wr-block-font-size]',
					'title' => __('Font Size', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '15',
						'placeholder' => 'inherit',
						'pattern'     => self::FONT_SIZE_PATTERN,
					)
				),
				'--wr-block-font-color' => array(
					'id'    => 'lws_wr_styling[--wr-block-font-color]',
					'title' => __('Text Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the text color", 'woorewards-lite'),
					)
				),
				'--wr-block-padding' => array(
					'id'    => 'lws_wr_styling[--wr-block-padding]',
					'title' => __('Padding', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '10px',
						'tooltips'    => sprintf(__("Define the blocks inner padding. Set 0px for no padding. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'preview' => array(
					'id'    => 'block-preview',
					'title' => __('Preview', 'woorewards-lite'),
					'type'  => 'custom',
					'extra' => array(
						'content' => $preview,
					)
				)
			),
		);
		if ($loadValues)
			self::loadFields($group);
		if ($withScript)
			self::enqueueScripts();
		return self::addRootField($group, $withRoot);
	}

	public static function getGroupButtons($withRoot=false, $loadValues=false, $withScript=true)
	{
		$preview = <<<EOT
<div class='wr_style_preview' style='background-color:#fff;user-select:none;cursor:pointer;border:1px solid #eee;border-radius:4px;padding:20px;display:flex;justify-content:center; align-items:center;'>
	<div class='wr-button'>Button Text</div>
</div>
EOT;

		$group = array(
			'id'     => 'buttons',
			'icon'   => 'lws-icon-click',
			'title'  => __("Buttons", 'woorewards-lite'),
			'color'  => '#425981',
			'class'  => 'half',
			'text'   => __("Set the styling of buttons used on front-end elements and shortcodes", 'woorewards-lite'),
			'fields' => array(
				'--wr-button-border-width' => array(
					'id'    => 'lws_wr_styling[--wr-button-border-width]',
					'title' => __('Button Border Width', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '0px',
						'tooltips'    => sprintf(__("Define the blocks border width. Set 0px for no border. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'--wr-button-border-style' => array(
					'id'    => 'lws_wr_styling[--wr-button-border-style]',
					'title' => __('Button Border Style', 'woorewards-lite'),
					'type'  => 'lacselect',
					'extra' => array(
						'mode'     => 'select',
						'maxwidth' => '220px',
						'source'   => array(
							array('value' => '',       'label' => __("Inherit", 'woorewards-lite')),
							array('value' => 'none',   'label' => __("None", 'woorewards-lite')),
							array('value' => 'solid',  'label' => __("Solid", 'woorewards-lite')),
							array('value' => 'hidden', 'label' => __("Hidden", 'woorewards-lite')),
							array('value' => 'dotted', 'label' => __("Dotted", 'woorewards-lite')),
							array('value' => 'dashed', 'label' => __("Dashed", 'woorewards-lite')),
							array('value' => 'double', 'label' => __("Double", 'woorewards-lite')),
							array('value' => 'groove', 'label' => __("Groove", 'woorewards-lite')),
							array('value' => 'ridge',  'label' => __("Ridge", 'woorewards-lite')),
							array('value' => 'inset',  'label' => __("Inset", 'woorewards-lite')),
							array('value' => 'outset', 'label' => __("Outset", 'woorewards-lite')),
						),
					)
				),
				'--wr-button-border-radius' => array(
					'id'    => 'lws_wr_styling[--wr-button-border-radius]',
					'title' => __('Button Border Radius', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '0px',
						'tooltips'    => sprintf(__("Define the blocks border radius. Set 0px for no radius. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'--wr-button-border-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-border-color]',
					'title' => __('Button Border Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks border color", 'woorewards-lite'),
					)
				),
				'--wr-button-background-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-background-color]',
					'title' => __('Button Background Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks background color", 'woorewards-lite'),
					)
				),
				'--wr-button-font-size' => array(
					'id'    => 'lws_wr_styling[--wr-button-font-size]',
					'title' => __('Button Font Size', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '15',
						'placeholder' => 'inherit',
						'pattern'     => self::FONT_SIZE_PATTERN,
					)
				),
				'--wr-button-font-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-font-color]',
					'title' => __('Button Text Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the text color", 'woorewards-lite'),
					)
				),
				'--wr-button-border-over-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-border-over-color]',
					'title' => __('Mouseover Border Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks border color on mouse over", 'woorewards-lite'),
					)
				),
				'--wr-button-background-over-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-background-over-color]',
					'title' => __('Mouseover Background Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the blocks background color on mouse over", 'woorewards-lite'),
					)
				),
				'--wr-button-font-over-color' => array(
					'id'    => 'lws_wr_styling[--wr-button-font-over-color]',
					'title' => __('Mouseover Text Color', 'woorewards-lite'),
					'type'  => 'color',
					'extra' => array(
						'gizmo'    => true,
						'tooltips' => __("Define the text color on mouse over", 'woorewards-lite'),
					)
				),
				'--wr-button-padding' => array(
					'id'    => 'lws_wr_styling[--wr-button-padding]',
					'title' => __('Button Padding', 'woorewards-lite'),
					'type'  => 'input',
					'extra' => array(
						'gizmo'       => true,
						'size'        => '30',
						'placeholder' => '10px',
						'tooltips'    => sprintf(__("Define the button inner padding. Set 0px for no padding. You can set up to 4 values. %s works, %s also works", 'woorewards-lite'), "<b>2px</b>", "<b>0px 1px 0px 2px</b>"),
						'pattern'     => self::SIZE_PATTERN,
					)
				),
				'preview' => array(
					'id'    => 'button-preview',
					'title' => __('Preview', 'woorewards-lite'),
					'type'  => 'custom',
					'extra' => array(
						'content' => $preview,
					)
				)
			),
		);
		if ($loadValues)
			self::loadFields($group);
		if ($withScript)
			self::enqueueScripts();
		return self::addRootField($group, $withRoot);
	}

	protected static function loadFields(array &$group)
	{
		$style = self::getValues();
		foreach ($group['fields'] as $key => &$field) {
			if (isset($style[$key]))
				$field['extra']['value'] = $style[$key];
		}
		return $group;
	}

	protected static function addRootField(array $group, $append=true)
	{
		if ($append) {
			// that field is only for WP whitelist declaration
			// values are spread in all other fields and managed as a unique array
			$group['fields']['root'] = array(
				'id'    => 'lws_wr_styling',
				'title' => '',
				'type'  => 'custom',
				'extra' => array(
					'hidden'  => true,
					'content' => '',
				)
			);
		}
		return $group;
	}

	public static function hasValues()
	{
		$style = \get_option('lws_wr_styling');
		return ($style && \is_array($style));
	}

	/** @return array */
	public static function getValues()
	{
		static $style = false;
		if (false === $style) {
			$style = self::filterValues(\get_option('lws_wr_styling'));
		}
		return $style;
	}

	/** @return string HTML <style> ... </style> */
	public static function getInline()
	{
		static $style = false;
		if (false === $style) {
			$style = self::valuesToInlineCSS(self::getValues());
		}
		return $style;
	}

	public static function valuesToInlineCSS($values)
	{
		$style = '';
		if ($values && \is_array($values)) {
			foreach ($values as $key => &$value) {
				$value = sprintf('%s: %s;', $key, \strlen(\trim($value)) ? $value : 'inherit');
			}
			$style = sprintf(
				"<style id='lws-woorewards-blocks-inline-css'>:root{\n\t%s\n}</style>",
				implode("\n\t", $values)
			);
		}
		return $style;
	}

	public static function filterValues($values)
	{
		$default = array(
			'--wr-block-border-width'           => '',
			'--wr-block-border-style'           => '',
			'--wr-block-border-radius'          => '',
			'--wr-block-border-color'           => '',
			'--wr-block-background-color'       => '',
			'--wr-block-font-size'              => '',
			'--wr-block-font-color'             => '',
			'--wr-block-padding'                => '',
			'--wr-button-border-width'          => '',
			'--wr-button-border-style'          => '',
			'--wr-button-border-radius'         => '',
			'--wr-button-border-color'          => '',
			'--wr-button-background-color'      => '',
			'--wr-button-font-size'             => '',
			'--wr-button-font-color'            => '',
			'--wr-button-border-over-color'     => '',
			'--wr-button-background-over-color' => '',
			'--wr-button-font-over-color'       => '',
			'--wr-button-padding'               => '',
		);
		if ($values && \is_array($values)) {
			$values = \array_intersect_key(
				\wp_parse_args($values, $default), $default
			);
		} else {
			$values = $default;
		}
		return $values;
	}
}