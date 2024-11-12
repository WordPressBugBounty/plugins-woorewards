<?php
namespace LWS\Adminpanel\Tools;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Eval arithmetic expression, allow placeholder.
 *	If called several times on same instance,
 *	the options will be kept but can be overwritten.
 *
 *	Support following operators:
 *	* `+`, `-`, `*`, `/`
 *		usual arithmetic operators addition, substraction, multiplication, division.
 *	* `>` Greater than, result is 1 or 0 (if strictly greater or not)
 *	* `<` Lesser than, result is 1 or 0 (if strictly lesser or not)
 *
 *	Support following functions (e.g.: `floor @ 4.23` gives 4):
 *	`floor@` with any number to round to nearest inferior integer
 *	`ceil@` with any number to round to nearest superior integer
 *	`round@` with any number to round to nearest integer (round up if exactly midway .5)
 * 	`min@`
 * 	`max@`
 * 	`equal@`
 *
 *	Support following placeholder (needs user given in options):
 *	* `time` return the current timestamp.
 *	* `user_integer:meta_key` return a user meta cast as integer.
 *	* `user_float:meta_key` return a user meta cast as float.
 *	* `user_timestamp:meta_key` return a user meta cast in timestamp (from int value or textual date).
 *
 *	Priority is:
 *	1. placeholders
 *	2. parenthesis
 *	3. +, -
 *	4. *, /
 *	5. >, <
 *	6. @
 **/
class Expression
{
	public $lastError = false;
	public $lastResult = false;
	public $options = array();

	protected $silent = false; /// by default, errors are reported in logs
	protected $testMode = false; /// if true, placeholders return arbitrary values without reading of options and division by zero is bypassed
	protected $patternMul = '#\s*(-?[^\s@><\*/+-]+)\s*([\*/])\s*(-?[^\s@><\*/+-]+)\s*#';
	protected $patternSum = '#\s*(-?[^\s@><\*/+-]+)\s*([+-])\s*(-?[^\s@><\*/+-]+)\s*#';
	protected $patternCmp = '#\s*(-?[^\s@><\*/+-]+)\s*([><])\s*(-?[^\s@><\*/+-]+)\s*#';
	protected $patternFct = '#\s*(-?[^\s@><\*/+-]+)\s*(@)\s*(-?[^\s@,><\*/+-]+(?:\s*,\s*-?[^\s@,><\*/+-]+)*)\s*#';

	function val()
	{
		return $this->lastResult;
	}

	function err()
	{
		return $this->lastError;
	}

	/**	Provided to get points from litteral expression.
	 *	Check if expression is readable, if it fulfill the pattern.
	 *	This cannot prevent missing data in use, or divide by zero, etc.
	 *	Accept standard opterator +-/* and parenthesis.
	 *	Accept placeholders to get value from welldefined objects (@see getValueFromExpression)
	 *	@note
	 *	All placeholders are replaced by 1.
	 *	Test case cannot detect divide by zero, this test is omitted since
	 *	we cannot know what will really come from placeholders.
	 *	@endnote
	 *	@return false if not valid. */
	function isValid($expression, $options=array(), $cleanLastError=true)
	{
		$mode = $this->setTestMode(true);
		$yes = (false !== $this->getValue($expression, $options, $cleanLastError));
		$this->setTestMode($mode);
		return $yes;
	}

	/** @return true if starts like an expression.
	 *	Convenience method to make difference between usual number and expression. */
	static function isExpr($value)
	{
		return ('=' == \substr(\trim($value), 0, 1));
	}

	/**	Provided to get points from litteral expression.
	 *	An expression have to starts with =
	 *	If none, the round is not even applied.
	 *	Accept standard opterator +-/* and parenthesis.
	 *	Accept placeholders to get value from welldefined objects (some need to be included in option)
	 *	@param $expression (string|number) a sentence that will be translated/computed to a number
	 *	@param $this->options (array) specify behavior and additional data
	 *	* default (number) returned value if expression is not valid.
	 *	* rounding_method (bool|string):
	 * 	* * false: no rounding, keep value as computed
	 *	* * true: use general rounding option (@see option 'lws_adminpanel_expression_rounding_mode')
	 *	* * string in 'half_up', 'half_down', 'ceil', 'floor'
	 *	* user (int|WP_User) user ID or user instance, can be ignored if not required by the given expression */
	function getValue($expression, $options=array(), $cleanLastError=true)
	{
		if ($cleanLastError) {
			$this->lastError = false;
			$this->lastResult = false;
		}
		$this->setOptions($options);

		// check we can analyse the expression
		$expression = \trim($expression);
		if ('=' != substr($expression, 0, 1)) {
			if (strlen($expression)) {
				if (!is_numeric($expression)) {
					return $this->raiseError("Expect an expression or a number `{$expression}`");
				}
			} elseif (!$this->options['empty']) {
				return $this->raiseError("Expect a not empty expression or number `{$expression}`");
			}
			return ($this->lastResult = $expression);
		}
		$expression = trim(substr($expression, 1));
		if (!strlen($expression)) {
			return $this->raiseError("Empty expression `={$expression}`");
		}

		// do it
		$this->lastResult = ($this->isTestMode() ? false : $this->options['default']);
		try {
			$this->lastResult = \is_numeric($expression) ? $expression : $this->reduce($expression);
		} catch(\ArithmeticError $ae) {
			$this->raiseError("Catch arithmetic error in `={$expression}`: " . $ae->getMessage());
		} catch(\ErrorException $e) {
			$this->raiseError("Catch an error in `={$expression}`: " . $e->getMessage());
		} catch(\Exception $e) {
			$this->raiseError("Bad expression `={$expression}`: " . $e->getMessage());
		}

		// round it
		if (\is_numeric($this->lastResult) && false !== $this->options['round_mode']) {
			$this->lastResult = self::round(
				$this->lastResult,
				$this->options['round_mode'],
				$this->options['precision']
			);
		}
		return $this->lastResult;
	}

	/** @see getValue
	 *	Do not log errors */
	function getValueSilently($expression, $options=array(), $cleanLastError=true)
	{
		$backup = $this->setSilent(true);
		$value = $this->getValue($expression, $options, $cleanLastError);
		$this->setSilent($backup);
		return $value;
	}

	/** return a new instance of Expression */
	static function create($options=array())
	{
		$me = new self();
		$me->setOptions($options, true);
		return $me;
	}

	protected function setOptions($options=false, $reset=false)
	{
		if (!$this->options || $reset) {
			$this->options = array(
				'empty'      => true, // accept empty value and return it as is
				'default'    => false,
				'round_mode' => true,
				'precision'  => 0,
				'user'       => false,
				'sponsee'    => false,
				'unlockable' => false,
				'event'      => false,
				'order'      => false,
			);
		}
		if ($options)
			$this->options = \wp_parse_args($options, $this->options);
	}

	protected function raiseError($error)
	{
		$this->lastError = $error;
		if ($this->isTestMode()) {
			return false;
		} else {
			if (!$this->isSilent())
				error_log($error);
			return $this->options['default'];
		}
	}

	/** recursive method, handle parenthesis, handle placeholders */
	protected function reduce($expression)
	{
		// first, replace placeholders by numeric values
		$open = 0;
		while(false !== ($open = strpos($expression, '{', $open))) {
			$close = strpos($expression, '}', $open + 1);
			if (false === $close)
				throw new \Exception(sprintf(_x("Placeholder mark mismatch near `%s`", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), substr($expression, \max(0, $open - 10), $open + 3)));
			$placeholder = \trim(substr($expression, $open + 1, $close - $open - 1));
			if (!$placeholder)
				throw new \Exception(sprintf(_x("Empty placeholder near `%s`", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), substr($expression, \max(0, $open - 10), $open + 3)));
			$value = $this->replacePlaceholder($placeholder);
			if (false === $value)
				throw new \Exception(sprintf(_x("Unknown placeholder `%s`", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), $placeholder));
			$expression = (substr($expression, 0, $open) . $value . substr($expression, $close + 1));
			++$open;
		}

		// extract parenthesis then
		while(false !== ($close = strpos($expression, ')'))) {
			$open = strrpos($expression, '(', $close - strlen($expression));
			if (false === $open)
				throw new \Exception(sprintf(_x("Parenthesis mismatch near `%s`", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), substr($expression, \max(0, $close - 10), $close + 5)));
			// reduce and replace
			$value = trim(substr($expression, $open + 1, $close - $open - 1));
			if (!\is_numeric($value))
				$value = $this->reduceOperators($value);
			$expression = (substr($expression, 0, $open) . $value . substr($expression, $close + 1));
		}
		if (\is_numeric($expression))
			return $expression;
		else
			return $this->reduceOperators($expression);
	}

	/** look for known placeholders and compute it.
	 *	return false if not known placeholder */
	protected function replacePlaceholder($placeholder)
	{
		if ('time' == $placeholder) {
			return \time();
		}

		$match = false;
		if (\preg_match('/user_(integer|float|timestamp):(.*)/i', $placeholder, $match)) {
			$userId = (($this->options['user'] && \is_object($this->options['user'])) ? $this->options['user']->ID : $this->options['user']);
			$meta = \trim($match[2]);
			if (!$meta)
				throw new \Exception(sprintf(_x("Placeholder `%s` requires a meta key", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), $placeholder));
			if ($this->isTestMode()) {
				return ('timestamp' == $match[1]) ? \time() : 1;
			}

			$value = \get_user_meta($userId, $meta, true);
			if ('integer' == $match[1]) {
				return \intval($value);
			} elseif ('float' == $match[1]) {
				return \floatval($value);
			} elseif ('timestamp' == $match[1]) {
				if (\is_numeric($value)) {
					return \intval($value);
				} else {
					$value = \date_create($value);
					return $value ? $value->getTimestamp() : 0;
				}
			}
		}

		/// test mode returns arbitrary number for valid placeholder
		return \apply_filters('lws_adminpanel_expression_placeholder', false, $placeholder, $this->options, $this->isTestMode());
	}

	/** recursive method, do not handle parenthesis.
	 *	Better to test if not already numeric value before calling this. */
	protected function reduceOperators($expression)
	{
		if (!strlen($expression))
			throw new \Exception(_x("Bad expression, operand missing", 'expression', LWS_WOOREWARDS_PRO_DOMAIN));

		$expression = $this->opMul($expression);
		$expression = $this->opSum($expression);
		$expression = $this->opCmp($expression);
		$expression = $this->opFct($expression);

		// here lay only a number
		if (!\is_numeric($expression))
			throw new \Exception(sprintf(_x("Cannot be reduced `%s`", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), $expression));
		return $expression;
	}

	/** multiplication and division */
	protected function opMul($expression)
	{
		$matches = false;
		while (preg_match($this->patternMul, $expression, $matches, PREG_OFFSET_CAPTURE)) {
			if (!\is_numeric($matches[1][0]))
				$matches[1][0] = $this->reduceOperators($matches[1][0]);
			if (!\is_numeric($matches[3][0]))
				$matches[3][0] = $this->reduceOperators($matches[3][0]);

			if ('*' == $matches[2][0]) {
				$value = ($matches[1][0] * $matches[3][0]);
			} else {
				if (0 == $matches[3][0] && $this->isTestMode())
					$value = 1;
				else
					$value = ($matches[1][0] / $matches[3][0]);
			}
			$expression = (substr($expression, 0, $matches[0][1]) . $value . substr($expression, $matches[0][1] + strlen($matches[0][0])));
		}
		return $expression;
	}

	/** addition and subtraction */
	protected function opSum($expression)
	{
		$matches = false;
		while (preg_match($this->patternSum, $expression, $matches, PREG_OFFSET_CAPTURE)) {
			if (!\is_numeric($matches[1][0]))
				$matches[1][0] = $this->reduceOperators($matches[1][0]);
			if (!\is_numeric($matches[3][0]))
				$matches[3][0] = $this->reduceOperators($matches[3][0]);

			if ('+' == $matches[2][0])
				$value = ($matches[1][0] + $matches[3][0]);
			else
				$value = ($matches[1][0] - $matches[3][0]);
			$expression = (substr($expression, 0, $matches[0][1]) . $value . substr($expression, $matches[0][1] + strlen($matches[0][0])));
		}
		return $expression;
	}

	/** greater than, lesser than */
	protected function opCmp($expression)
	{
		$matches = false;
		while (preg_match($this->patternCmp, $expression, $matches, PREG_OFFSET_CAPTURE)) {
			if (!\is_numeric($matches[1][0]))
				$matches[1][0] = $this->reduceOperators($matches[1][0]);
			if (!\is_numeric($matches[3][0]))
				$matches[3][0] = $this->reduceOperators($matches[3][0]);

			if ('>' == $matches[2][0])
				$value = (($matches[1][0] > $matches[3][0]) ? 1 : 0);
			else
				$value = (($matches[1][0] < $matches[3][0]) ? 1 : 0);
			$expression = (substr($expression, 0, $matches[0][1]) . $value . substr($expression, $matches[0][1] + strlen($matches[0][0])));
		}
		return $expression;
	}

	/** rounding functions, allow others thanks to filter 'lws_adminpanel_expression_function' */
	protected function opFct($expression)
	{
		$matches = false;
		while (preg_match($this->patternFct, $expression, $matches, PREG_OFFSET_CAPTURE)) {
			$fct = \strtolower($matches[1][0]);
			$args = $matches[3][0];
			$value = false;

			// known
			if ('floor' == $fct) {
				$value = \floor(\is_numeric($args) ? $args : $this->reduceOperators($args));
			} elseif ('ceil' == $fct)  {
				$value = \ceil(\is_numeric($args) ? $args : $this->reduceOperators($args));
			} elseif ('round' == $fct)  {
				$value = \round(\is_numeric($args) ? $args : $this->reduceOperators($args));
			} elseif ('abs' == $fct)  {
				$value = \abs(\is_numeric($args) ? $args : $this->reduceOperators($args));
			} elseif ('not' == $fct)  {
				$value = ((\is_numeric($args) ? $args : $this->reduceOperators($args)) ? 0 : 1);
			}

			if (false === $value) {
				// allow several args comma separated
				$args = explode(',', $args);
				foreach ($args as $i => $arg) {
					$arg = \trim($arg);
					if (\is_numeric($arg))
						$args[$i] = $arg;
					elseif ("'" == \substr($arg, 0, 1))
						$args[$i] = \trim(\trim($arg, "'"));
					else
						$this->reduceOperators($arg);
				}

				// known with several args
				if ('date' == $fct) {
					if (2 == count($args)) {
						$value = \date_create()->setTimestamp((int)$this->reduceOperators($args[0]))->format($args[1]);
					}
				} elseif ('equal' == $fct) {
					$value = 1;
					for ($i = 1 ; $i < count($args) ; $i++) {
						if ($args[0] != $args[$i]) {
							$value = 0;
							break;
						}
					}
				} elseif ('min' == $fct) {
					$value = \min($args);
				} elseif ('max' == $fct) {
					$value = \max($args);
				} elseif ('mod' == $fct) {
					if (2 == count($args)) {
						$value = ($args[0] % $args[1]);
					}
				}

				// perhaps a third party
				if (false === $value) {
					/// @param result false means unsupported function, or number
					/// @param the function name
					/// @param array of argument, should be all numbers
					$value = \apply_filters('lws_adminpanel_expression_function', false, $fct, $args, $this->options);
					if (false === $value)
						throw new \Exception(sprintf(_x("Unknown function `%s` or missing arguments", 'expression', LWS_WOOREWARDS_PRO_DOMAIN), $matches[0][0]));
				}
			}

			// replace
			$expression = (substr($expression, 0, $matches[0][1]) . $value . substr($expression, $matches[0][1] + strlen($matches[0][0])));
		}
		return $expression;
	}

	/* @param $mode true to use general rounding option (@see option 'lws_adminpanel_expression_rounding_mode')
	 * or a string in 'half_up', 'half_down', 'ceil', 'floor' */
	static function round($number, $mode=true, $precision=0)
	{
		if (true === $mode)
			$mode = \get_option('lws_adminpanel_expression_rounding_mode', 'half_down');

		switch ($mode) {
			case 'half_up':
				return \round($number, $precision, PHP_ROUND_HALF_UP);
			case 'half_down':
				return \round($number, $precision, PHP_ROUND_HALF_DOWN);
			case 'ceil':
				if (0 == $precision)
					return \ceil($number);
				else {
					$shift = \pow(10, $precision);
					if ($precision > 0)
						return \ceil($number * $shift) / $shift;
					else
						return \ceil($number / $shift) * $shift;
				}
			case 'floor':
				if (0 == $precision)
					return \floor($number);
				else {
					$shift = \pow(10, $precision);
					if ($precision > 0)
						return \floor($number * $shift) / $shift;
					else
						return \floor($number / $shift) * $shift;
				}
		}
		return $number;
	}

	public static function install()
	{
		$me = new self();
		\add_filter('lws_adminpanel_expression', array($me, 'getValue'), 10, 2);
		\add_filter('lws_adminpanel_expression_silent', array($me, 'getValueSilently'), 10, 2);
		\add_filter('lws_adminpanel_validate_expression', array($me, 'isValid'), 10, 2);
	}

	/** @return bool mode status before the change. */
	function setTestMode($yes)
	{
		$old = $this->testMode;
		$this->testMode = $yes;
		return $old;
	}

	function isTestMode()
	{
		return $this->testMode;
	}

	/** @return bool silent status before the change. */
	function setSilent($yes)
	{
		$old = $this->silent;
		$this->silent = $yes;
		return $old;
	}

	function isSilent()
	{
		return $this->silent;
	}
}
