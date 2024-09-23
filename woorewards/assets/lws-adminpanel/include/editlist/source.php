<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();

/** As post, display a list of item with on-the-fly edition. */
abstract class Source
{
	const ACTION_CELL_KEY = 'lws_ap_editlist_item_actions';

	/** The edition inputs.
	 *	input[name] should refers to all $line array keys (use input[type='hidden'] for not editable elements).
	 * Readonly element can be displayed using <span data-name='...'></span> but this one will not be send
	 * back at validation, display is its only prupose (name can be the same as an hidden input if you want return)
	 *	@return string with the form content. */
	abstract function input();

	/**	@return array with the column which must be displayed in the list.
	 *	array ( $key => array($label [, $col_width]) )
	 * The width (eg. 10% or 45px) to apply to column is optionnal. */
	abstract function labels();

	/**	get the list content and return it as an array.
	 * @param $limit an instance of RowLimit class or null if deactivated (if EditList::setPageDisplay(false) called).
	 *	@return array of line array. array( array( key => value ) ) */
	abstract function read($limit);

	/**	Save one edited line. If the index is not found, this function must create a new record.
	 * @param $row (array) the edited item to save.
	 * @return bool|array|\LWS\Adminpanel\EditList\UpdateResult|\WP_Error On success return the updated line, if failed, return false or a \WP_Error instance to add details. */
	abstract function write( $row );

	/**	Delete one edited line.
	 * @param $row (array) the item to remove.
	 * @return bool|\LWS\Adminpanel\EditList\UpdateResult|\WP_Error true if succeed. */
	abstract function erase( $row );

	/** this function to return the total number of record in source.
	 * @return null|integer|string the record count or -1 if not implemented or unavailable. */
	public function total()
	{
		return -1;
	}

	/** Override this function to specify default values (array) for a new edition form. */
	public function defaultValues()
	{
		return "";
	}

	/** Override to add a title line over the popup dialog. */
	public function getPopupTitle()
	{
		return __("Settings", 'lws-adminpanel');
	}

	/** @return array LAC source format [[value, label], etc.]
	 *	If any, columnns that support a sort. */
	public function getSortColumns()
	{
		return array();
	}

	/**	@return false|string
	 *	If a sort is selected. @see getSortColumns() */
	public function getSortValue($guid)
	{
		$sortId = 'sort_' . $guid;
		return isset($_REQUEST[$sortId]) ? \sanitize_key($_REQUEST[$sortId]) : false;
	}

	/**	@return bool
	 *	If a sort is selected. @see getSortColumns() */
	public function isSortDescsending($guid)
	{
		$descId = 'desc_' . $guid;
		return (isset($_REQUEST[$descId]) && 'on' == $_REQUEST[$descId]);
	}

	/** @deprecated use 'lws_adminpanel_arg_parse' filter instead.
	 * @see \LWS\Adminpanel\Tools\ArgParser */
	public static function invalidArray(&$array, $format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		return \LWS\Adminpanel\Tools\ArgParser::invalidArray($array, $format, $strictFormat, $strictArray, $translations);
	}

	/** Common default style if a cell links to an edition page. */
	public function coatTitleToEditButton($text, $redirectTo, $style='')
	{
		$style = ('lws-editlist-title-edit-btn ' . \ltrim($style));
		$button = "<a href='{$redirectTo}' class='{$style}'>$text</a>";
		return $button;
	}

	/** Common default style for a button in the Action column */
	public function coatActionButton($text, $icon='lws-icon-settings-gear', $tag='div', $args=array())
	{
		$args['class'] = isset($args['class']) ? rtrim($args['class']) : '';
		$args['class'] .= " editlist-btn {$icon}";
		if( in_array(strtolower($tag), array('a', 'span')) )
			$args['class'] .= " custom";

		foreach( $args as $k => &$v )
			$v = sprintf('%s="%s"', $k, $v);
		$args = implode(' ', $args);
		return "<{$tag} {$args}><div class='btn-descr'>{$text}</div></{$tag}>";
	}

	/** @return array of button html for the Action cell */
	public function getActionButtonsContents($data, $rowId, $editlistSlug, $mode)
	{
		$buttons = array();

		if ($mode) {
			$ph = apply_filters(
				'lws_ap_editlist_item_action_names_' . $editlistSlug,
				array(
					\LWS\Adminpanel\EditList\Modes::MOD => __('Quick Edit', 'lws-adminpanel'),
					\LWS\Adminpanel\EditList\Modes::DUP => __('Copy', 'lws-adminpanel'),
					\LWS\Adminpanel\EditList\Modes::DEL => __('Delete', 'lws-adminpanel'),
				),
				$rowId,
				$data
			);

			if ($mode & \LWS\Adminpanel\EditList\Modes::MOD) {
				$buttons['mod'] = "<div class='editlist-btn mod lws-icon-edit'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::MOD]}</div></div>";
			}
			if ($mode & \LWS\Adminpanel\EditList\Modes::DUP) {
				$buttons['dup'] = "<div class='editlist-btn dup lws-icon-copy'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::DUP]}</div></div>";
			}
			if ($mode & \LWS\Adminpanel\EditList\Modes::DEL) {
				$buttons['del'] = "<div class='editlist-btn del lws-icon-bin'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::DEL]}</div></div>";
			}
		}
		
		return apply_filters('lws_ap_editlist_item_actions_' . $editlistSlug, $buttons, $rowId, $data);
	}

	/**	@param array $buttons comes from @see getActionButtonsContents()
	 *	@return string the final content of the Action cell */
	public function flatActionButtons($buttons)
	{
		if ($buttons) {
			$buttons = implode('', $buttons);
			return "<div class='lws-editlist-action-button lws-icon-menu-5'><div class='editlist-actions-popup hidden'><div class='lws-el-buttons-wrapper'>{$buttons}</div></div></div>";
		} else {
			return '';
		}
	}
}