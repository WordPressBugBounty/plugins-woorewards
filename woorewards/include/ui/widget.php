<?php

namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Provide a widget to let display rewards.
 * Can be used as a Widget, a Shortcode [lws_rewards] or a Guttenberg block (soon).
 * Rewards can be filtered by pool
 * For a looged in user, we can filter only the unlockable ones. */
class Widget extends \WP_Widget
{
	static protected function register($className)
	{
		\add_action('widgets_init', function () use ($className) {
			\register_widget($className);
		});
	}

	/** echo a form select line @param $options (array) value=>text */
	protected function eFormFieldSelect($id, $label, $name, $options, $value)
	{
		$input = "<select class='widefat' id='$id' name='$name'>";
		foreach ($options as $v => $txt) {
			$selected = $v == $value ? ' selected' : '';
			$input .= "<option value='$v'$selected>$txt</option>";
		}
		$input .= "</select>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form text line */
	protected function eFormFieldText($id, $label, $name, $value, $placeholder = '', $type = 'text')
	{
		$input = "<input class='widefat' id='$id' name='$name' type='$type' value='$value' placeholder='$placeholder'/>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form radio line */
	protected function eFormFieldRadio($id, $label, $name, $options, $value)
	{
		$input = '';
		foreach ($options as $v => $txt) {
			$selected = $v == $value ? ' checked' : '';
			$input .= "<input type='radio' style='margin:0 5px 0 15px;' name='$name' value='$v'$selected>$txt";
		}
		$this->eFormField($id, $label, $input);
	}

	/** echo a checkbox */
	protected function eFormFieldCheckbox($id, $label, $name, $value)
	{
		$selected = $value ? ' checked' : '';
		$input = "<input class='checkbox' id='$id' name='$name' type='checkbox' $selected/>";
		echo "<p>$input<label for='$id'>$label</label></p>";
	}

	/** echo a form entry line */
	protected function eFormField($id, $label, $input)
	{
		echo "<p><label for='$id'>$label</label>$input</p>";
	}
}
