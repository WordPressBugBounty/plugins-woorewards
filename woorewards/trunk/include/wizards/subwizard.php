<?php
namespace LWS\WOOREWARDS\Wizards;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Sub wizard must implement this. */
abstract class Subwizard
{
	protected $wizard = null;
	protected $color  = false;

	function __construct(\LWS\Adminpanel\Wizard &$wizard)
	{
		$this->wizard = &$wizard;
		$this->init();
	}

	protected function getValue(&$data, $field, $path=false, $default=null)
	{
		return $this->wizard->getValue($data, $field, $path, $default);
	}

	protected function getDataValue(&$data, $field, $path, &$exists)
	{
		return $this->wizard->getDataValue($data, $field, $path, $exists);
	}

	protected function getData()
	{
		return $this->wizard->getData();
	}

	protected function getColor()
	{
		if (false === $this->color) {
			return $this->wizard->getColor();
		}
		return $this->color;
	}

	protected function init()
	{
		return;
	}

	abstract function getHierarchy();
	abstract function getStepTitle($slug);
	abstract function getPage($slug, $mode='');
	abstract function submit(&$data);

	/** Override that method to check user input before continue on next page.
	 * @param $step (string) the page slug
	 * @param $submit (IN/OUT array) user input to test. Since passed by ref, you can strip some value in the same time.
	 * On problem one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		return true;
	}

	/** float (support dot or comma) */
	function isFloat(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(array(' ', ','), array('', '.'), trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else
			return \preg_match('/^-?([0-9]+(\.[0-9]*)?)|(([0-9]*\.)?[0-9]+)$/', $submit[$key]);
	}

	/** float (support dot or comma)
	 * min excluded, max included */
	function isFloatInRangeEI(&$submit, $key, $minEx, $maxIn, $canBeOmitted=true, $default='')
	{
		if( $this->isFloat($submit, $key, $canBeOmitted, $default) )
		{
			if( strlen($submit[$key]) )
			{
				$f = floatval($submit[$key]);
				return ($minEx < $f && $f <= $maxIn);
			}
			else
				return $canBeOmitted;
		}
		else
			return false;
	}

	/** float (support dot or comma)
	 * min included, max included */
	function isFloatInRangeII(&$submit, $key, $minIn, $maxIn, $canBeOmitted=true, $default='')
	{
		if( $this->isFloat($submit, $key, $canBeOmitted, $default) )
		{
			if( strlen($submit[$key]) )
			{
				$f = floatval($submit[$key]);
				return ($minIn <= $f && $f <= $maxIn);
			}
			else
				return $canBeOmitted;
		}
		else
			return false;
	}

	/** float greater or equal than zero (support dot or comma) */
	function isFloatGE0(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(array(' ', ','), array('', '.'), trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else
			return \preg_match('/^([0-9]+(\.[0-9]*)?)|(([0-9]*\.)?[0-9]+)$/', $submit[$key]);
	}

	/** float greater than zero */
	function isFloatGT0(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(array(' ', ','), array('', '.'), trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else if( \preg_match('/^([0-9]+(\.[0-9]*)?)|(([0-9]*\.)?[0-9]+)$/', $submit[$key]) )
			return floatval($submit[$key]) > 0.0;
		else
			return false;
	}

	/** integer */
	function isInt(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(' ', '', trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else
			return \preg_match('/^-?[0-9]+$/', $submit[$key]);
	}

	/** float (support dot or comma)
	 * min excluded, max included */
	function isIntInRangeEI(&$submit, $key, $minEx, $maxIn, $canBeOmitted=true, $default='')
	{
		if( $this->isInt($submit, $key, $canBeOmitted, $default) )
		{
			if( strlen($submit[$key]) )
			{
				$i = intval($submit[$key]);
				return ($minEx < $i && $i <= $maxIn);
			}
			else
				return $canBeOmitted;
		}
		else
			return false;
	}

	/** float (support dot or comma)
	 * min included, max included */
	function isIntInRangeII(&$submit, $key, $minIn, $maxIn, $canBeOmitted=true, $default='')
	{
		if( $this->isInt($submit, $key, $canBeOmitted, $default) )
		{
			if( strlen($submit[$key]) )
			{
				$i = intval($submit[$key]);
				return ($minIn <= $i && $i <= $maxIn);
			}
			else
				return $canBeOmitted;
		}
		else
			return false;
	}

	/** integer greater or equal than zero */
	function isIntGE0(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(' ', '', trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else
			return \preg_match('/^[0-9]+$/', $submit[$key]);
	}

	/** integer greater than zero */
	function isIntGT0(&$submit, $key, $canBeOmitted=true, $default='')
	{
		if( isset($submit[$key]) )
			$submit[$key] = str_replace(' ', '', trim($submit[$key]));
		else
			$submit[$key] = $default;
		if( $canBeOmitted && !strlen($submit[$key]) )
			return true;
		else
			return \preg_match('/^[0-9]*[1-9][0-9]*$/', $submit[$key]);
	}
}
