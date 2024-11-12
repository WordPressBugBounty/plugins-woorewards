<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** With PointStack, help to add information in history.
 * Contains a lot of conveniency methods.
 * All setters and trace method return the object itself to be chained.
 *
 * That class implements a just-in-time translation features.
 * Since current user langage could be different at reading time
 * than reason generation time.
 * The PointStack reading method could call the underscore translation functions at reading
 * if reason is set properly (with sentance and arg as array with a domain)
 * @see LWS\WOOREWARDS\Core\Trace::setReason() */
class Trace
{
	public $referral     = ''; /// origin
	public $providerId = false; /// origin2
	public $orderId    = null;
	public $blogId     = false;
	public $reason     = '';

	function __construct($values=array())
	{
		$this->referral   = ''; /// origin
		$this->providerId = false; /// origin2
		$this->orderId    = null;
		$this->blogId     = false;
		$this->reason     = '';

		foreach( $values as $key=>$value )
		{
			switch($key)
			{
				case 'origin'  : $this->setOrigin($value);   break;
				case 'order'   : $this->setOrder($value);    break;
				case 'provider': $this->setProvider($value); break;
				case 'blog'    : $this->setBlog($value);     break;
				case 'reason'  : $this->setReason($value);   break;
			}
		}
	}

	static function byOrder($order)
	{
		$inst = new self();
		return $inst->setOrder($order);
	}

	static function byOrigin($origin)
	{
		$inst = new self();
		return $inst->setOrigin($origin);
	}

	/** @see setReason() */
	static function byReason($reason, $domain='')
	{
		$inst = new self();
		return $inst->setReason($reason, $domain);
	}

	function &setOrigin($origin)
	{
		if( $origin && \is_object($origin) && \method_exists($origin, 'getId') )
			$this->referral = $origin->getId();
		else
			$this->referral = $origin;
		return $this;
	}

	function &setOrder($order)
	{
		if( \is_a($order, '\WC_Order') )
			$this->orderId = $order->get_id();
		else
			$this->orderId = intval($order);
		return $this;
	}

	function &setProvider($user)
	{
		if( \is_a($user, '\WP_User') )
			$this->providerId = $user->ID;
		else
			$this->providerId = intval($user);
		return $this;
	}

	function &setBlog($blogId)
	{
		$this->blogId = intval($blogId);
		return $this;
	}

	function getBlog()
	{
		return $this->blogId !== false ? \absint($this->blogId) : \get_current_blog_id();
	}

	/** Define the label of the operation.
	 * @param $domain (string|false) the text domain of the reason string.
	 * Allow a just-in-time translation (call __() the method @see read()).
	 * @param $reason (string|array) if array, assume as sprintf arguments.
	 * Take care to declare your string for translation somewhere anyway.
	 * Call the one of the WordPress underscore function,
	 * so PoEdit/WPML can extract it,
	 * for example in a never call part of code.
	 */
	function &setReason($reason, $domain='')
	{
		$this->reason = self::serializeReason($reason, $domain);
		return $this;
	}

	/** @param $reason (string, array) if array first item is the string pattern, then sprintf arguments
	 *	@param $domain (string) text domain to be used for translation
	 *	@return (string) a serialize array to save in db.
	 * */
	static function serializeReason($reason, $domain='')
	{
		if( is_array($reason) )
		{
			if( $domain )
			{
				$reason[] = $domain;
				$reason = serialize($reason);
			}
			else
				$reason = self::reasonToString($reason, false);
		}
		else if( $domain )
		{
			$reason = serialize(array($reason, $domain));
		}
		return $reason;
	}

	static function unserializeReason($raw)
	{
		$reason = @unserialize($raw);
		if( $reason && is_array($reason) )
			$raw = self::reasonToString($reason, true);
		return $raw;
	}

	static function reasonToString($args, $translate=true)
	{
		$format = array_shift($args);
		if( $translate && $args )
		{
			$domain = array_pop($args);
			$format = __($format, $domain);
		}
		if( $args )
			$format = vsprintf($format, $args);
		return $format;
	}

	static function toString($trace)
	{
		if( \is_a($trace, '\LWS\WOOREWARDS\Core\Trace') )
			return self::unserializeReason($trace->reason);
		else if( is_array($trace) )
			return self::reasonToString($trace);
		else
			return $trace;
	}
}
