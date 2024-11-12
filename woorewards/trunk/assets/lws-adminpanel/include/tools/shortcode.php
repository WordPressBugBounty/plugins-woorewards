<?php
namespace LWS\Adminpanel\Tools;

/** Maker: generate shortcode text to include in post.
 *	Helper tricks is used for replace in text feature.
 *	@note
 *	$shortcode = new \LWS\Adminpanel\Tools\Shortcode(
			'wr_transactional_points_expiration',
			array('system'  => 'test',),
			"Content inside tags\n",
			'helper-demo'
		);
		$original = "..."; // imagine a post content
		$modified = $shortcode->replaceOrAdd(
			$original,
			true,
			"<h1>A title above</h1>\n%s\n" // note the %s in the string
		);
 *	@endnote
 */
class Shortcode
{
	private $_tag      = '';
	private $_attrs    = array();
	private $_content  = '';
	private $_helper   = '';
	private $_sep      = ',';
	private $_changed  = false;
	private $_found    = array();

	public function __construct($tag='', $attrs=array(), $content='', $helper='')
	{
		$this->_tag     = $tag;
		$this->_attrs   = $attrs;
		$this->_content = $content;
		$this->_helper  = $helper;
	}

	/** Format the shortcode with the given attributes.
	 *	If a helper is defined, html comments surround the shortcode.
	 *	@param $surrounding (string) must includes a `%s`, the shortcode is embedded in that text with sprintf. */
	public function toText($surrounding='%s')
	{
		$txt = $this->getTag();
		foreach ($this->getAttributes() as $name => $value) {
			$txt .= sprintf(' %s="%s"', $name, \esc_attr(
				\is_array($value) ? \implode((string)$this->getAttributeValuesSeparator(), $value) : $value
			));
		}
		$txt = "[{$txt}]";
		if ($this->_content) {
			$txt .= "{$this->_content}[/{$this->_tag}]";
		}
		if ($surrounding) {
			$txt = sprintf($surrounding, $txt);
		}
		if ($this->_helper) {
			$txt = "<!-- @lws_shortcode_begin:{$this->_helper} -->{$txt}<!-- @lws_shortcode_end:{$this->_helper} -->";
		}
		return $txt;
	}

	/** Look for that shorcode, and replace its text by our.
	 *	@param $source (string) the original text
	 *	@param $mergeAttrs (bool) read found shortcode and
	 *	merge new on them attributes before replace the text,
	 *	means new attributes (from $this) prevals
	 *	but not overriden old one (from $source) are preserved.
	 *	@return (string) the modified text. */
	public function replace(string $source, $mergeAttrs=true, $surrounding='%s')
	{
		$this->_changed = false;
		$shortcode =& $this;
		$modified = \preg_replace_callback(
			$this->getNeedle(), function ($match) use ($mergeAttrs, $surrounding, &$shortcode) {
				if ($mergeAttrs && isset($match['attrs'])) {
					$match['attrs'] = \LWS\Adminpanel\Tools\Shortcode::parseAtts($match['attrs']);
					$shortcode->mergeAttributes($match['attrs']);
				}
				$shortcode->_setSourceChanged();
				return $shortcode->toText($surrounding);
			}, $source, 1, $this->_changed
		);
		return (NULL === $modified) ? $source : $modified;
	}

	/** Look for that shorcode, and remove the *first occurence* of
	 *	that part of text. */
	public function remove(string $source)
	{
		$this->_changed = false;
		$modified = \preg_replace(
			$this->getNeedle(), '', $source, 1, $this->_changed
		);
		return (NULL === $modified) ? $source : $modified;
	}

	/** Look for that shorcode, read it and merge attributes in this.
	 *	@param $source (string) the original text
	 *	@param $mergeAttrs (bool) read found shortcode and
	 *	merge new on them attributes before replace the text,
	 *	means new attributes (from $this) prevals
	 *	but not overriden old one (from $source) are preserved.
	 *	@return (bool) true if found. */
	public function find(string $source, $mergeAttrs=true)
	{
		$this->_found = array();
		if (\preg_match($this->getNeedle(), $source, $this->_found)) {
			if (isset($this->_found['attrs'])) {
				$this->_found['attrs'] = self::parseAtts($this->_found['attrs']);
				if ($mergeAttrs) {
					$this->mergeAttributes($this->_found['attrs']);
				}
			}
			return true;
		}
		return false;
	}

	/** If the shortcode cannot be found, append it in the text.
	 *	@param $surrounding (string) must includes a `%s`, if added,
	 *	the shortcode is embedded in that text with sprintf.
	 *	@see replace()
	 *	@return (string) the modified text. */
	public function replaceOrAdd(string $source, $mergeAttrs=true, $surrounding='%s')
	{
		$source = $this->replace($source, $mergeAttrs, $surrounding);
		if (!$this->_changed)
			$source .= $this->toText($surrounding);
		return $source;
	}

	/**	Convenience: update the given post.
	 *	@see replaceOrAdd
	 *	@return mixed The post ID on success. The value 0 or WP_Error on failure. */
	public function replaceOrAddInPost($postId, $mergeAttrs=true, $surrounding='%s')
	{
		$post = \is_object($postId) ? $postId : \get_post($postId);
		if (!($post && $post->ID))
			return false;
		return \wp_update_post(array(
			'ID'            => $post->ID,
			'post_content'  => $this->replaceOrAdd($post->post_content, $mergeAttrs, $surrounding),
		), true);
	}

	/**	Convenience: update the given post.
	 *	@see remove
	 *	@return mixed The post ID on success. The value 0 or WP_Error on failure. */
	public function removeInPost($postId)
	{
		$post = \is_object($postId) ? $postId : \get_post($postId);
		if (!($post && $post->ID))
			return false;
		return \wp_update_post(array(
			'ID'            => $post->ID,
			'post_content'  => $this->remove($post->post_content),
		), true);
	}

	public function setTag($tag)
	{
		$this->_tag = $tag;
		return $this;
	}

	public function getTag()
	{
		return $this->_tag;
	}

	public function setContent($c)
	{
		$this->_content = $c;
		return $this;
	}

	public function getContent()
	{
		return $this->_content;
	}

	/** default is comma.
	 *	If a separator is set, mergeAttribuet will be able to
	 *	merge a same attribute values if assumed as a list of value.
	 *	That attribute value must be given as an array, not a composed string. */
	public function setAttributeValuesSeparator($sep)
	{
		$this->_sep = $sep;
		return $this;
	}

	public function getAttributeValuesSeparator()
	{
		return $this->_sep;
	}

	/** replace or merge attributes of the shortcode.
	 *	@param $attrs (array) attribute_name => value. */
	public function setAttributes($attrs, $fullReplace=true)
	{
		$this->_attrs = \wp_parse_args($attrs, $fullReplace ? array() : $this->_attrs);
		return $this;
	}

	public function addAttribute($name, $value, $replace=true)
	{
		if ($replace || !$this->hasAttribute($name)) {
			$this->_attrs[$name] = $value;
		}
		return $this;
	}

	/** If the attribute exists:
	 *	-	If it is an array and a separator is defined (default is comma)
	 *		new attribute is splitted, then all values are merge in a single array,
	 *		when values unicity is checked,
	 *	- attribute is replaced.
	 *	Else existant attributes are preserved. */
	public function mergeAttributes($attrs)
	{
		foreach (\wp_parse_args($attrs) as $name => $value) {
			if (isset($this->_attrs[$name]) && \is_array($this->_attrs[$name]) && $this->_sep) {
				// preserves spaces in values but does not include them in compare
				$test = \array_fill_keys(\array_map('\trim', $this->_attrs[$name]), true);
				if (!\is_array($value))
					$value = \explode($this->_sep, $value);
				foreach ($value as $v) {
					if (!isset($test[\trim($v)]))
						$this->_attrs[$name][] = $v;
				}
			} else {
				$this->_attrs[$name] = $value;
			}
		}
		return $this;
	}

	public function hasAttribute($name, $value=null, $testValue=false)
	{
		if (!isset($this->_attrs[$name]))
			return false;
		elseif ($testValue && $this->_attrs[$name] != $value)
			return false;
		else
			return true;
	}

	public function getAttribute($name, $default=null)
	{
		return isset($this->_attrs[$name]) ? $this->_attrs[$name] : $default;
	}

	public function getAttributes()
	{
		return $this->_attrs;
	}

	public function delAttribute($name, $value=null, $testValue=false)
	{
		if (!isset($this->_attrs[$name]))
			return false;
		if ($testValue && $this->_attrs[$name] != $value)
			return false;
		unset($this->_attrs[$name]);
		return true;
	}

	/** used to make a diff during `replace` process. */
	public function setHelper($h)
	{
		$this->_helper = $h;
		return $this;
	}

	/** @see setHelper */
	public function getHelper()
	{
		return $this->_helper;
	}

	public function getNeedle()
	{
		$attrPattern = '(?:\s+[\w-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'))+';
		$tag = \preg_quote($this->getTag());
		$txt = (
			'(?<!\[)\[\s*' . $tag . '(?<attrs>' . $attrPattern . ')]'
			. '(?:(?<content>.*)\[\/' . $tag . '])?'
		);
		if ($this->getHelper()) {
			$helper = \preg_quote($this->getHelper());
			$txt = sprintf(
				'<!-- @lws_shortcode_begin:%s -->(?<before>.*)%s(?<after>.*)<!-- @lws_shortcode_end:%s -->',
				$helper, $txt, $helper
			);
		}
		return "/{$txt}/is";
	}

	static public function parseAtts($str)
	{
		$pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)/';
		$atts    = array();
		$str     = \preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $str);
		if (\preg_match_all($pattern, $str, $match, PREG_SET_ORDER)) {
			foreach ($match as $m) {
				if (!empty($m[1])) {
					$atts[\strtolower($m[1])] = \stripcslashes($m[2]);
				} elseif (!empty($m[3])) {
					$atts[\strtolower($m[3])] = \stripcslashes($m[4]);
				} elseif (!empty($m[5])) {
					$atts[\strtolower($m[5])] = \stripcslashes($m[6]);
				}
			}
		}
		return $atts;
	}

	/** Internal purpose */
	public function _setSourceChanged($changed=true)
	{
		$this->_changed = $changed;
		return $this;
	}

	/** @return true if last call
	 *	to one of replace*() or remove*() methods
	 *	changed something. */
	public function isSourceChanged($clear=false)
	{
		if ($clear && $this->_changed) {
			$this->_changed = false;
			return true;
		}
		return (bool)$this->_changed;
	}

	/** @return false or object [shortcode: new Shorcode instance, surrounding: string]
	 *	Need a call to @see find() first */
	public function getLatestFind($clear=false)
	{
		$found = false;
		if ($this->_found) {
			$found = (object)array(
				'shortcode'   => new self(
					$this->getTag(),
					isset($this->_found['attrs']) ? $this->_found['attrs'] : array(),
					isset($this->_found['content']) ? $this->_found['content'] : '',
					$this->getHelper()
				),
				'surrounding' => (
					(isset($this->_found['before']) ? $this->_found['before'] : '')
					. '%s' .
					(isset($this->_found['after']) ? $this->_found['after'] : '')
				),
			);
		}
		if ($clear) {
			$this->_found = array();
		}
		return $found;
	}
}