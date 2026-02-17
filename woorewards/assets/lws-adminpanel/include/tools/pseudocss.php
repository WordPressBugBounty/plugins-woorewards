<?php
namespace LWS\Adminpanel\Tools;
if( !defined( 'ABSPATH' ) ) exit();

/**
 * Provide an easy way to let user customize predefined CSS.
 *
 * * The source files (.lwss) :
 * It is a standard CSS file we changed the extension .css to .lwss
 * New css prperties can be insterted in the content
 * which will result in css value replacement.
 *
 * These properties are: $type, $help, $id, $ref
 * CSS Syntax:
 * @code
 * $type: color|font|border|button|div
 * @endcode
 * This property enable custom value edition. The type allows the presentation of editor in admin pages.
 * Then standard css values are replaced by those set in the editor.
 * Standard css property:value couple are used as default values.
 * @note the kind of color background-color|border-color|color is defined by the first css property of this kind met.
 * @note type button have 4 additionnal new properties: hover-background-color, hover-color, active-background-color, active-color
 * They will be moved in a new section with selectors :hover and :active to manage the behavior of a button.
 *
 * @code
 * $help: "any text"
 * @endcode
 * (Optional) Display a text above the editor in admin pages.
 * @note help value MUST be quoted!
 *
 * @code
 * $id: a_uniq_slug
 * @endcode
 * (Optional) Default, the css selector is used as id, a specific one can be set by this way.
 *
 * @code
 * $ref: an_id
 * @endcode
 * Instead of duplicate an editor or repeating values, copy another section identfied with the $id property.
 *
 * * Access methods :
 * In the example below, we assume a site example.com with
 * a plugin named my-plugin providing
 * a file my-style.lwss in its root directory.
 *
 * * * lwss interpretation is available as an ajax service, the response is a complete css file.
 * @code
 * http://www.example.com/wp-admin/admin-ajax.php?action=lws_pseudocss&url=my-plugin/my-style.lwss
 * @endcode
 *
 *
 * * Usually, it is the plugin which register the stylesheet and let wordpress deal with it.
 * Just do it as usual, the interpretation is transparent.
 * @code
 * wp_enqueue_style( 'my-style', dirname(__FILE__)."/my-style.lwss", array() );
 * @endcode
 * The other way with StyGen is to add an url argument stygen with the admin field id at end of the path
 * If an option is saved, the css is replaced.
 * @code
 * wp_enqueue_style( 'my-style', dirname(__FILE__)."/my-style.lwss?stygen=my_field", array() );
 * @endcode
 * In the same way, but saved option is merged to original css.
 * @code
 * wp_enqueue_style( 'my-style', dirname(__FILE__)."/my-style.lwss?themer=my_field", array() );
 * @endcode
 *
 * * Display custom css properties edition :
 * lwss is fully integratel in lws-adminpanel configuration array
 * as you add pages, groups and fields.
 * Use the convenient function lwss_to_fields which return a fields array
 * based on lwss content.
 * @code
 * lws_register_pages( array(
 * 		'id' => "my-plugin",
 * 		'groups' =>	array(
 * 			'title' => __("Custom css", 'my-text-domain'),
 * 			'fields' => lwss_to_fields(dirname(__FILE__)."/my-style.lwss", 'my-text-domain')
 * 		)
 * ) );
 * @endcode
 * In the generated admin page, the html fields (<input>...) can be retreive
 * since tey have the attribute data-css with the relative css property
 * when data-lwss attirbute contains the default value.
 *
 * * Translation:
 * Since the lwss was loaded once by lws-adminpanel (in your browser, go to your plugin administration page
 * where you put the lwss cutom values edition fields),
 * a php file is generated in the same directory as your lwss file with the same basename.
 * It contains the translation array for the lwss balises ids.
 * If you generate the .pot file (poedit, makepot.php...), it will get these translation texts.
 * The texts are composed by lwss-{a hash based on lwss url}-{balise id},
 * the translation context indicate the lwss url.
 * If such a php file is not generated, check the server writing rights to the directory.
 * If the lwss changed and you want to regenerate a new php translation file, simply remove the previous one.
 */
class CSSSection
{
	public $ID;
	public $Selector;
	public $Defaults;
	public $Type;
	public $Help;
	public $Ref;
	public $Values;
	public $Title = '';
	public $Important = false;
	public $isAtRule = false;
	public $subSections = [];

	public function __construct($selector, $defaults=array(), $id='', $type='', $help='', $ref='', $important=array())
	{
		$this->ID = $id;
		$this->Selector = $selector;
		$this->Defaults = $defaults;
		$this->Type = $type;
		$this->Help = $help;
		$this->Ref = $ref;
		$this->Values = array();
		$this->Title = '';
		$this->Important = $important;
		$this->isAtRule = \substr($selector, 0, 1) === '@';
	}

	public function isEditable()
	{
		return !empty($this->Type);
	}

	public function merge($defaults, $type='', $help='', $ref='', $important=array())
	{
		if( is_array($defaults) && !empty($defaults) )
			$this->Defaults = array_merge($this->Defaults, $defaults);
		if( is_string($type) && !empty($type) )
			$this->Type = $type;
		if( is_string($help) && !empty($help) )
			$this->Help = $help;
		if( is_string($ref) && !empty($ref) )
			$this->Ref = $ref;
		if( is_array($important) && !empty($important) )
			$this->Important = array_merge($this->Important, $important);
	}

	public function title()
	{
		return empty($this->Title) ? (empty($this->ID) ? $this->Selector : $this->ID) : $this->Title;
	}

	public function mergeValues($section)
	{
		$this->Values = array_merge($this->Values, $section->Values);
	}

	/** @return array values as an array of css parameters, eg. array('color'=>'#fff')
	 * @param $getDefaults if false, any read value is merge on defaults. */
	public function details($getDefaults=true)
	{
		if( $getDefaults )
			return $this->Defaults;
		else
			return array_merge($this->Defaults, $this->Values);
	}

	public function savedValues()
	{
		return $this->Values;
	}

	public function toString(string $indent='')
	{
		$str = '';
		$props = array_merge($this->Defaults, $this->Values);
		$hover = '';
		$active = '';
		foreach( $props as $k => &$v )
		{
			if( in_array($k, $this->Important) )
				$v .= ' !important';
			if( substr($k, 0, 6) === 'hover-' )
				$hover .= substr($k, 6) . ":$v;";
			else if( substr($k, 0, 7) === 'active-' )
				$active .= substr($k, 7) . ":$v;";
			else
				$str .= "$k:$v;";
		}
		foreach ($this->subSections as $subSection) {
			$str .= $subSection->toString($indent . "\t");
		}

		$css = $indent . $this->Selector;
		$css .= ($this->subSections ? " {\n" : '{');
		$css .= $str;
		$css .= ($this->subSections ? " \n{$indent}}" : '}');

		if( $this->Type == 'button' )
		{
			$css .= "\n" . $indent . $this->Selector . ':hover{' . $hover . '}';
			$css .= "\n" . $indent . $this->Selector . ':active{' . $active . '}';
		}
		return $css . "\n";
	}

	public function flatten()
	{
		foreach ($this->subSections as &$subSection) $subSection->flatten();
		$this->subSections = \array_values($this->subSections);
	}
}

/** LongWatch Stylesheet */
class PseudoCss
{
	const EXT = '.lwss';
	const ARG = 'url';
	const ACT = 'lws_pseudocss';

	protected $Sections = array();
	protected $Url = '';
	protected $Buffer = '';
	protected $MasterID = '';
	protected $Fonts = array();

	/** explore the lwss pseudocss file to create customizable values edition fields.
	 * @param $url the path to .lwss file.
	 * @param $textDomain the text-domain to use for wordpress translation of field ID to human readable title.
	 * @return array of field to use in pages descrption array. */
	public static function toFieldArray($url, $textDomain)
	{
		$fields = array();
		$me = new PseudoCss();
		$balises = $me->extract($url, $textDomain, true);
		add_action( 'updated_option', array( $me, 'revokeCache'), 10, 3 );

		foreach( $balises as $id => $balise )
		{
			if( $balise->isEditable() )
			{
				// actually 5 type implemented for css: border, font, *color, text, button.
				if( $balise->Type == 'button' )
					$balise->Type = 'cssbutton';
				if( !array_key_exists($balise->Type, \LWS\Adminpanel\Pages\Field::types()) )
					$balise->Type = "text"; // as for html input, if type is not managed, use a text field

				$fields[] = array(
					'id' => $id,
					'title' => $balise->title(),
					'type' => $balise->Type,
					'extra' => array(
						'defaults' => $balise->details(true),
						'values' => $balise->savedValues(),//details(false),
						'source' => $me->MasterID,
						'help' => $balise->Help
					)
				);
			}
		}

		if( !empty($fields) ) // reset button
		{
			$fields = array_merge( array(array(
					'id' => $me->MasterID,
					'title' => __("Default CSS values", 'lws-adminpanel'),
					'type' => 'button',
					'extra' => array(
						'master' => $me->MasterID,
						'class' => 'lwss-reset-btn',
						'text' => _x("Reset", "Default css values", 'lws-adminpanel')
						//,'help' => _x("", "Help about reset css to default values", 'lws-adminpanel')
					)
				)),
				$fields
			);
		}
		return $fields;
	}

	/** prepare ajax and add some hook */
	public static function install()
	{
		$me = new PseudoCss();
		add_action( 'wp_ajax_'.self::ACT, array( $me, 'tryRequest') );
		add_action( 'wp_ajax_nopriv_'.self::ACT, array( $me, 'tryRequest') );
		add_filter( 'style_loader_src', array($me, 'trackPseudoCss'), 20, 2 );
		add_filter( 'stygen_inline_style', array($me, 'inlineCss'), 10, 3 );
		add_filter( 'themer_inline_style', array($me, 'inlineTheme'), 10, 3 );
	}

	/** run from post value, usually from ajax request */
	public function tryRequest()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if( isset($_REQUEST[self::ARG]) )
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = \esc_url_raw(\sanitize_text_field(wp_unslash($_REQUEST[self::ARG])));
			$this->toCss($url);
		}
	}

	/** If a pseudocss is given as GET argument, try interpretation.
	 * provided for experiment purpose. */
	public static function tryGet()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if( isset($_GET[self::ARG]) )
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = esc_url_raw(\sanitize_text_field(wp_unslash($_GET[self::ARG])));
			$me = new PseudoCss();
			$me->toCss($url);
		}
	}

	/** add_action( 'updated_option', array( $this, 'revokeCache'), 10, 3 ); */
	public function revokeCache($option, $oldvalue, $_newvalue)
	{
		if( substr($option, 0, strlen($this->MasterID)) == $this->MasterID )
		{
			$cached = new Cache( $this->MasterID . 'cached.css' );
			$cached->del();
			$this->keepLastUsedFont($_newvalue);
		}
	}

	protected function keepLastUsedFont($cssValue, $limit=3)
	{
		if( ($userId = get_current_user_id()) > 0 )
		{
			++$limit;
			$props = explode(';', $cssValue);
			foreach( $props as $prop )
			{
				$e = explode(':', $prop, 2);
				if( trim($e[0]) == 'font-family' && count($e) > 1 )
				{
					$font = $e[1] . '|';
					$last = get_user_meta($userId, 'lwss-last-used-fonts', true);
					if( empty($last) )
						$last = '';
					$last = str_ireplace($font, '', $last) . $font;
					$tmp = explode('|', $last);
					$last = implode('|', array_splice($tmp, -$limit, $limit));
					update_user_meta($userId, 'lwss-last-used-fonts', $last);
					break;
				}
			}
		}
	}

	/** @return array of Balise
	 * @param string $textDomain the text-domain of the current plugin for translation,
	 * let it false to do not care about titles. */
	public function extract($url, $textDomain=false, $loadValues=true)
	{
		if( $this->fileIsValid($url) )
		{
			if( $this->interpret() )
			{
				//if( $textDomain !== false && is_string($textDomain) && !empty($textDomain) )
					//$this->prepareTitles($textDomain);
				if( $loadValues )
					$this->loadValues();
				return $this->Sections;
			}
		}
		else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Cannot extract balise from pseudo-css $url"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return array();
	}

	public function fromString($css)
	{
		$buffer = preg_replace('/\/\*.*\*\//Us', '', $css);
		if( $this->build($buffer) )
			return $this->Sections;
		else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Cannot extract balise from pseudo-css string"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return array();
	}

	public function getBalises()
	{
		foreach ($this->Sections as $section) $section->flatten();
		return $this->Sections;
	}

	/** a hash based on pseudocss url. */
	public static function buildMasterID($url)
	{
		$me = new PseudoCss();
		if( $me->fileIsValid($url) )
			return substr($me->MasterID, 0, strlen($me->MasterID)-1);
		else
			return '';
	}

	/** Look at database for css value and if any, replace the default ones. */
	public function loadValues()
	{
		if( defined( 'ABSPATH' ) ) // require to be in wordpress
		{
			global $wpdb;
			// phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$vals = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $this->MasterID.'%'), ARRAY_N);
			if( is_array($vals) )
			{
				foreach( $vals as $v )
				{
					$k = $v[0];
					if( isset($this->Sections[$k]) )
						$this->Sections[$k]->Values = $this->cssPropsToArray($v[1]);
				}
			}
		}
	}

	/** @param bool $addUnknown not used  */
	public function merge($pseudocss, $addUnknown=true)
	{
		if( is_a($pseudocss, __CLASS__) )
		{
			$rlen = strlen($pseudocss->MasterID);
			foreach( $pseudocss->getBalises() as $prop => $val )
			{
				$prop = $this->MasterID . substr($prop, $rlen);
				if( isset($this->Sections[$prop]) )
					$this->Sections[$prop]->Values = $val->details(false);
				else
					$this->Sections[$prop] = $val;
			}
		}
		else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(__CLASS__ . __FUNCTION__ . " expect a " . __CLASS__ . " instance as argument."); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return $this->Sections;
	}

	public function loadTitlesAndHelp($textDomain, $titleFile)
	{
		// load it
		include($titleFile);
		if( isset($pseudoCssTitles) && is_array($pseudoCssTitles) )
		{
			foreach( $pseudoCssTitles as $id => $title )
			{
				if( array_key_exists($id, $this->Sections) )
					$this->Sections[$id]->Title = $title;
			}
		}
		if( isset($pseudoCssHelps) && is_array($pseudoCssHelps) )
		{
			foreach( $pseudoCssHelps as $id => $help )
			{
				if( array_key_exists($id, $this->Sections) )
					$this->Sections[$id]->Help = $help;
			}
		}
	}

	/** @see inlineCss but force merge */
	public function inlineTheme($style, $src, $fieldId)
	{
		return $this->inlineCss($style, $src, $fieldId, true);
	}

	/** @code
	 * $mystyle = apply_filters('stygen_inline_style', '', $cssFilePath, $fieldId);
	 * @endcode
	 * @param $style (in/out) append loaded style and return it
	 * @param $src the css file path
	 * @param $fieldId the ID of the adminpanel field stygen
	 * @return string html <style> */
	public function inlineCss($style, $src, $fieldId, $merge=false)
	{
		$cached = new Cache(sanitize_key($fieldId) . '-cached.css');
		if( empty($css = $cached->pop()) )
		{
			$pseudocss = new self();
			if( !empty($pseudocss->extract($this->relevantUrlPart($src), false, false)) )
			{
				$value = \get_option($fieldId, false);
				if( $value === false )
				{
					$css = $pseudocss->getFontfaces();
					$css .= $pseudocss->getFinalCSS();
				}
				else
				{
					$value = base64_decode($value);
					if( !empty($value) )
					{
						$loading = new PseudoCss();
						$loading->fromString($value);
						if( $merge )
						{
							$pseudocss->merge($loading, false);
							$css = $pseudocss->getFontfaces();
							$css .= $pseudocss->getFinalCSS();
						}
						else
						{
							$css = $loading->getFontfaces();
							if( empty($css) )
								$css = $pseudocss->getFontfaces();
							$css .= $loading->getFinalCSS();
						}
					}
				}
				$cached->put($css);
			}
		}
		if( !empty($css) )
			$style .= $css;
		return $style;
	}

	/** hook 'style_loader_src',
	 * when wp_enqueue_style is used on a .lwss,
	 * we replace it by a call to the interpreter.
	 * Or if the css path is followed by a stygen (or themer) argument with stygen (or themer) admin field id,
	 * we get or create a cache file and redirect url to it.
	 * difference between stygen and themer is themer force a merge with option content
	 * when stygen replace the css by the option content by default. */
	public function trackPseudoCss($src, $handle, $merge=false)
	{
		$part = explode('?', $src, 2);
		$args = array();
		$query = \wp_parse_url((string)$src, PHP_URL_QUERY);
		if ($query)
			\parse_str($query, $args);

		$key = isset($args['stygen']) ? \sanitize_key($args['stygen']) : '';
		if( empty($key) )
		{
			$key = isset($args['themer']) ? \sanitize_key($args['themer']) : '';
			$merge = true;
		}

		if( !empty($key) )
		{
			if( !empty($value = \get_option($key, false)) )
			{
				$cached = new Cache($key . '-cached.css');
				if( !$cached->isValid() )
				{
					$pseudocss = new self();
					//if( $value === false || $merge )
						$pseudocss->extract($this->relevantUrlPart($part[0]), false, false);

					if( $value === false )
					{
						$css = $pseudocss->getFontfaces();
						$css .= $pseudocss->getFinalCSS();
					}
					else
					{
						$css = base64_decode($value);
						if( !empty($css) )
						{
							$loading = new PseudoCss();
							$loading->fromString($css);
							if( $merge )
							{
								$pseudocss->merge($loading, false);
								$css = $pseudocss->getFontfaces();
								$css .= $pseudocss->getFinalCSS();
							}
							else
							{
								$css = $loading->getFontfaces();
								if( empty($css) )
									$css = $pseudocss->getFontfaces();
								$css .= $loading->getFinalCSS();
							}
						}
					}

					$cached->put($css);
				}
				$src = $cached->url();
			}
			if( $ts = \get_option($key.'_adm_ts') )
				$src = \add_query_arg(array($ts=>''), $src);
		}
		else if( $this->hasExtention($part[0], false) )
		{
			$args['action'] = self::ACT;
			$args[self::ARG] = $this->relevantUrlPart($part[0]);
			$src = \add_query_arg($args, \admin_url('/admin-ajax.php'));
		}
		return $src;
	}

	/** @return string the url part relevant for pseudocss (the one to pass to fileIsValid) */
	public function relevantUrlPart($url)
	{
		$base = plugins_url();
		if( substr($url, 0, strlen($base)) )
			$url = substr($url, strlen($base));
		return $url;
	}

	/** read the file.
	 * Fill Sections array.
	 * @return true if succeed. */
	protected function interpret()
	{
		$ok = false;
		$buffer = @file_get_contents($this->Url);
		if( $buffer !== false )
		{
			// remove comments
			$buffer = preg_replace('/\/\*.*\*\//Us', '', $buffer);
			$ok = $this->build($buffer);
		}
		else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log( "Read error of {$this->Url}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return 	$ok;
	}

	/** read the pseudo-css content in $buffer and grab the balise info. */
	protected function build($buffer)
	{
		try {
			$pos = 0;
			$this->Sections = \array_merge($this->Sections, $this->_build($buffer, $pos));
		} catch (\Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log($e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}
		return true;
	}

	/**
	 * @throws \Exception
	 */
	protected function _build(string $buffer, int &$pos): array
	{
		$flat = [];
		$match = [];
		$startPattern = '/([^\s][^\{]*){/Us';
		$endPattern = '/((?:[^\}\'"]|(?:"(?:[^"\\\\]|\\\\.)*")|(?:\'(?:[^\'\\\\]|\\\\.)*\'))*)(?:\}|$)/Us';

		while( preg_match($startPattern, $buffer, $match, PREG_OFFSET_CAPTURE, $pos) )
		{
			$selector = trim($match[1][0]);

			if (\substr($selector, 0, 1) === '}') {
				// end of media query
				$shift = strlen($selector) - \strlen(ltrim(substr($selector, 1)));
				$pos += $shift;
				return $flat;
			}

			// move cursor after the selector
			$pos = strlen($match[0][0]) + $match[0][1];

			if (\substr($selector, 0, 1) === '@') {
				// start of media query, look inside
				$media = new CSSSection($selector);
				$media->subSections = $this->_build($buffer, $pos);
				$flat[$selector] = $media;

			} elseif (preg_match($endPattern, $buffer, $match, PREG_OFFSET_CAPTURE, $pos) ) {
				// css properties
				$pos = strlen($match[0][0]) + $match[0][1];
				$props = $this->extractProps($match[1][0], $selector);
				if ($props) {
					if (isset($flat[$props->uid])) {
						$flat[$props->uid]->merge($props->props, $props->type, $props->help, $props->ref, $props->important);
					} else {
						$flat[$props->uid] = new CSSSection($selector, $props->props, $props->id, $props->type, $props->help, $props->ref, $props->important);
					}
				}
			} else {
				throw new \Exception(\esc_html("Cannot find selector property end '}' in:{$this->Url}\nafter :\n$selector {" . substr($buffer, $pos-1, 128)));
			}
		}
		return $flat;
	}

	/** harvest properties in a CSS section */
	private function extractProps($section, $selector)
	{
		$props = $this->cssPropsToArray($section);
		if (!$props) return false;
		$data = (object) [
			'props'     => $props,
			'id'        => '',
			'help'      => '',
			'type'      => '',
			'ref'       => '',
			'important' => [],
			'$uid'      => '',
		];
		foreach ($props as $k => &$v) {
			if( $k == '$id' ) $data->id = $v;
			else if( $k == '$type' ) $data->type = $v;
			else if( $k == '$help' ) $data->help = $v;
			else if( $k == '$ref' ) $data->ref = $v;
			else if( substr($v, -10) === '!important' ) {
				$data->important[] = $k;
				$v = trim(substr($v, 0, strlen($v)-10));
			}
		}
		$data->uid = $this->MasterID . $data->type . '-' . preg_replace('/[^[:alnum:]_-]/i', '_', urlencode($data->id ?: $selector));
		return $data;
	}

	protected function cssPropsToArray($str)
	{
		$props = array();
		$match = array();
		$str = trim($str);
		while( !empty($str) )
		{
			if( preg_match('/((?:[^;\'"]|(?:"(?:[^"\\\\]|\\\\.)*")|(?:\'(?:[^\'\\\\]|\\\\.)*\'))*)(?:;|$)/Us', $str, $match, PREG_OFFSET_CAPTURE) )
			{
				$str = trim(substr($str, strlen($match[0][0]) + $match[0][1]));
				$e = explode(':', $match[1][0], 2);
				if( count($e) >= 2 )
					$props[trim($e[0])] = trim($e[1]);
			}
			else
			{
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("Cannot read a css property in:{$this->Url}\nnear :\n" . $str); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				break;
			}
		}
		return $props;
	}

	protected function getStandardFont()
	{
		static $fonts = null;
		if( is_null($fonts) )
		{
			$path = LWS_ADMIN_PANEL_PATH . '/js/resources/standard_fonts.json';
			$json = json_decode( file_get_contents($path), false );
			$fonts = array();
			foreach( $json->items as $font )
				$fonts[trim($font->family)] = $font;
		}
		return $fonts;
	}

	protected function isStandardFont($family)
	{
		$fonts = $this->getStandardFont();
		foreach( explode(',', $family) as $name )
		{
			if( isset($fonts[trim($name)]) )
				return true;
		}
		return false;
	}

	protected function readFont($balise)
	{
		$d = $balise->details(false);
		if( array_key_exists('font-family', $d) )
		{
			$origin = 'google';
			if( strpos($balise->Selector, '.lwss_selectable') === false )
				$origin = 'local';
			elseif ('lws-' == \substr(\ltrim($d['font-family'], '\'"'), 0, 4))
				$origin = 'local';
			else if( $this->isStandardFont($d['font-family']) )
				$origin = 'standard';

			$this->addFont($origin, $d['font-family'], (array_key_exists('font-weight', $d) ? $d['font-weight'] : ''));
		}
	}

	/** We need to load google font family at top of our generated css. */
	protected function getFontfaces()
	{
		$css = '';
		if( function_exists('\wp_remote_get') )
		{
			foreach( $this->Sections as $b )
			{
				$this->readFont($b);
			}
			foreach( $this->Fonts as $font )
			{
				if( !empty($font['api']) )
				{
					$query = $this->fontQueryArgs($font);
					$response = \wp_remote_get( $query );
					$code = wp_remote_retrieve_response_code($response);
					if( $code < 400 )
					{
						$css .= wp_remote_retrieve_body( $response );
						$css .= "\n";
					}
					else {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log("Font face query return an error $code : $query"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
					}
				}
			}
		}
		return $css;
	}

	protected function addFont($origin, $family, $weight)
	{
		if( !array_key_exists($origin, $this->Fonts) )
		{
			$this->Fonts[$origin] = array('api' => '', 'list' => array());
			if( $origin == 'google' )
				$this->Fonts[$origin]['api'] = 'https://fonts.googleapis.com/css?family=';
		}

		if( !empty($weight) )
			$this->Fonts[$origin]['list'][$family][$weight] = $weight;
		else if( !isset($this->Fonts[$origin]['list'][$family]) )
			$this->Fonts[$origin]['list'][$family] = array();
	}

	protected function fontQueryArgs($font)
	{
		$families = array();
		foreach( $font['list'] as $family => $weights )
		{
			if( empty($weights) )
				$families[] = $family;
			else
				$families[] = $family . ':' . implode(',', $weights);
		}
		return esc_url_raw($font['api'] . implode('|', $families));
	}

	/** echo out what was interpreted.
	 * then exit(0) */
	protected function echoAndQuit($safe_css)
	{
		header("Content-type: text/css");
		header("X-Content-Type-Options: nosniff"); /// @see https://blogs.msdn.microsoft.com/ie/2008/09/02/ie8-security-part-vi-beta-2-update/
		echo $safe_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		die();
	}

	protected function getFinalCSS()
	{
		$refs = array();
		$css = '';
		foreach( $this->Sections as $id => &$balise )
		{
			if( !empty($balise->ID) )
				$refs[$balise->ID] = $balise;
			if( !empty($balise->Ref) && array_key_exists($balise->Ref, $refs) )
				$balise->mergeValues($refs[$balise->Ref]);
			$css .= $balise->toString();
		}
		return $css;
	}

	/** @param string url a valid path to the pseudo-css. */
	protected function toCss($url)
	{
		if( $this->fileIsValid($url) )
		{
			$cached = new Cache( $this->MasterID . 'cached.css' );
			if( !empty($css = $cached->pop()) )
			{
				$this->echoAndQuit($css);
			}
			else
			{
				if( $this->interpret() )
				{
					$this->loadValues();
					$css = $this->getFontfaces();
					$css .= $this->getFinalCSS();
					$cached->put($css);
					$this->echoAndQuit($css);
				}
				else
				{
					$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])) : 'HTTP/1.1';
					@header($protocol . ' 500 Internal Server Error', true, 500);
				}
			}
		}
		else {
			$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])) : 'HTTP/1.1';
			@header($protocol . ' 403 Forbidden', true, 403);
		}
		die();
	}

	protected function hasExtention($url, $allowCSS=true)
	{
		 if( substr($url, -strlen(self::EXT)) == self::EXT )
			 return true;
		else if( $allowCSS && substr($url, -strlen('.css')) == '.css' )
			return true;
		return false;
	}

	protected function fileIsValid($url)
	{
		if( !is_string($url) ) return false;
		if (!$this->hasExtention($url)) return false;
		$this->Url = $url;
		// fix relative path
		if (!@file_exists($this->Url) && defined('WP_CONTENT_DIR'))
			$this->Url = self::absPath(WP_CONTENT_DIR, $url);
		if (!@file_exists($this->Url) && defined('WP_PLUGIN_DIR'))
			$this->Url = self::absPath(WP_PLUGIN_DIR, $url);

		if (@file_exists($this->Url))
		{
			if( false === ($tmpUrl = realpath($this->Url)) )
				return false;
			else
				$this->Url = $tmpUrl;
		}

		if( defined('WP_CONTENT_DIR') )
		{
			// never go up to wp-content (avoid getting out of the site)
			$query = realpath(WP_CONTENT_DIR);
			if( substr($this->Url, 0, strlen($query)) !== $query )
				return false;
		}

		if (@file_exists($this->Url))
		{
			$this->MasterID = 'lwss-' . hash('crc32', $this->Url) . '-';
			return true;
		}
		else
		{
			$this->MasterID = 'lwss-[file_not_found]-';
			return false;
		}
	}

	protected static function absPath($base, $rel)
	{
		if( substr($rel, 0, 1) == '/' || substr($base,  -1) == '/' )
			return $base . $rel;
		else
		return $base . '/' . $rel;
	}
}
