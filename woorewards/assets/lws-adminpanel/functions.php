<?php
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

if( !function_exists('lws_admin_has_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_has_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		return isset($notices[$key]);
	}
}

if( !function_exists('lws_admin_delete_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_delete_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		if( isset($notices[$key]) )
		{
			unset($notices[$key]);
			\update_site_option('lws_adminpanel_notices', $notices);
		}
	}
}

if( !function_exists('lws_admin_add_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_add_notice($key, $message, $options=array())
	{
		$options['message'] = $message;
		\update_site_option('lws_adminpanel_notices', array_merge(get_site_option('lws_adminpanel_notices', array()), array($key => $options)));
	}
}

if( !function_exists('lws_admin_add_notice_once') )
{
	/** @see lws_admin_add_notice */
	function lws_admin_add_notice_once($key, $message, $options=array())
	{
		$options['once'] = true;
		lws_admin_add_notice($key, $message, $options);
	}
}

if( !function_exists('lws_get_value') )
{
	/** @return $value if not empty, else return $default. */
	function lws_get_value($value, $default='')
	{
		return empty($value) ? $default : $value;
	}
}

if( !function_exists('lws_get_option') )
{
	/** @return mixed \get_option($option) if not empty, else return $default. */
	function lws_get_option($option, $default='')
	{
		return \lws_get_value(\get_option($option), $default);
	}
}

if( !function_exists('lws_get_tooltips_html') )
{
	/** @return mixed \get_option($option) if not empty, else return $default. */
	function lws_get_tooltips_html($content, $cssClass='', $id='')
	{
		if( !empty($cssClass) )
			$cssClass = (' ' . $cssClass);

		$attr = '';
		if( !empty($id) )
			$attr = " id='" . \esc_attr($id) . "'";

		$retour = "<div class='lws_tooltips_button$cssClass lws-icon lws-icon-question'$attr>";
		$retour .= "<div class='lws_tooltips_wrapper' style='display:none'>";
		$retour .= "<div class='lws_tooltips_arrow'><div class='lws_tooltips_arrow_inner'></div></div>";
		$retour .= "<div class='lws_tooltips_content'>$content</div></div></div>";
		return $retour;
	}
}

if (!function_exists('lws_color_luminance'))
{
	/** Lightens/darkens a given colour (hex format), returning the altered colour in hex format.7
	 * @param string $color Colour as hexadecimal (with or without hash), or as function rgb, rgba, hsl, hsla.
	 * @param float percent float $percent Decimal ( 0.2 = lighten by 20%(), -0.4 = darken by 40%() )
	 * @param float alpha float [0,1]
	 * @return string Lightened/Darkend colour as hexadecimal (with hash);
	 */
	function lws_color_luminance($color, $percent=0, $alpha=false)
	{
		$start = \strtolower(\substr($color, 0, 3));
		if ('hsl' == $start) {
			// HSL
			$hsla = false;
			$pattern = '/hsl(?<f>a)?\s*\(\s*(?<h>[-+]?\d*(?:\.\d*)?(?:deg|rad|grad|turn)?)\s*[\s,]\s*(?<s>\d*(?:\.\d*)?%)\s*[\s,]\s*(?<l>\d*(?:\.\d*)?%)\s*(?:[\s,\/]\s*(?<a>\d*(?:\.\d*)?%?)\s*)?\)/i';
			if (\preg_match($pattern, $color, $hsla)) {
				if ($percent != 0) {
					$hsla['l'] = (float)\rtrim($hsla['l'], '%');
					$hsla['l'] = \min(100, \max(0, \round($hsla['l'] + ($hsla['l'] * $percent), 2)));
					$hsla['l'] .= '%';
				}
				if (false === $alpha && isset($hsla['a']) && \is_string($hsla['a']) && \strlen($hsla['a'])) {
					$alpha = $hsla['a'];
				}
				if (false !== $alpha) {
					$color = sprintf('hsla(%s, %s, %s, %s)', $hsla['h'], $hsla['s'], $hsla['l'], $alpha);
				} else {
					$color = sprintf('hsl(%s, %s, %s)', $hsla['h'], $hsla['s'], $hsla['l']);
				}
			}
		} elseif ('rgb' == $start) {
			// RGB
			$rgba = false;
			$pattern = '/rgb(?<f>a)?\s*\(\s*(?<r>\d*(?:\.\d*)?%?)\s*[\s,]\s*(?<g>\d*(?:\.\d*)?%?)\s*[\s,]\s*(?<b>\d*(?:\.\d*)?%?)\s*(?:[\s,\/]\s*(?<a>\d*(?:\.\d*)?)%?\s*)?\)/i';
			if (\preg_match($pattern, $color, $rgba)) {
				if (0 != $percent) {
					foreach (array('r', 'g', 'b') as $c) {
						if ('%' == \substr($rgba[$c], -1)) $rgba[$c] = (255.0 * (float)\substr($rgba[$c], 0, -1) / 100.0);
						$rgba[$c] = \min(255, \max(0, \round($rgba[$c] + ($rgba[$c] * $percent), 2)));
					}
				}
				if (false === $alpha && isset($rgba['a']) && \is_string($rgba['a']) && \strlen($rgba['a'])) {
					$alpha = $rgba['a'];
				}
				if (false !== $alpha) {
					$color = sprintf('rgba(%s, %s, %s, %s)', $rgba['r'], $rgba['g'], $rgba['b'], $alpha);
				} else {
					$color = sprintf('rgb(%s, %s, %s)', $rgba['r'], $rgba['g'], $rgba['b']);
				}
			}
		} else {
			// Litteral or Hexa
			if ('#' != \substr($color, 0, 1)) {
				static $colorNames = array(
					'antiquewhite'         => '#FAEBD7',
					'aqua'                 => '#00FFFF',
					'aquamarine'           => '#7FFFD4',
					'beige'                => '#F5F5DC',
					'black'                => '#000000',
					'blue'                 => '#0000FF',
					'brown'                => '#A52A2A',
					'cadetblue'            => '#5F9EA0',
					'chocolate'            => '#D2691E',
					'cornflowerblue'       => '#6495ED',
					'crimson'              => '#DC143C',
					'darkblue'             => '#00008B',
					'darkgoldenrod'        => '#B8860B',
					'darkgreen'            => '#006400',
					'darkmagenta'          => '#8B008B',
					'darkorange'           => '#FF8C00',
					'darkred'              => '#8B0000',
					'darkseagreen'         => '#8FBC8F',
					'darkslategray'        => '#2F4F4F',
					'darkviolet'           => '#9400D3',
					'deepskyblue'          => '#00BFFF',
					'dodgerblue'           => '#1E90FF',
					'firebrick'            => '#B22222',
					'forestgreen'          => '#228B22',
					'fuchsia'              => '#FF00FF',
					'gainsboro'            => '#DCDCDC',
					'gold'                 => '#FFD700',
					'gray'                 => '#808080',
					'green'                => '#008000',
					'greenyellow'          => '#ADFF2F',
					'hotpink'              => '#FF69B4',
					'indigo'               => '#4B0082',
					'khaki'                => '#F0E68C',
					'lavenderblush'        => '#FFF0F5',
					'lemonchiffon'         => '#FFFACD',
					'lightcoral'           => '#F08080',
					'lightgoldenrodyellow' => '#FAFAD2',
					'lightgreen'           => '#90EE90',
					'lightsalmon'          => '#FFA07A',
					'lightskyblue'         => '#87CEFA',
					'lightslategray'       => '#778899',
					'lightyellow'          => '#FFFFE0',
					'lime'                 => '#00FF00',
					'limegreen'            => '#32CD32',
					'magenta'              => '#FF00FF',
					'maroon'               => '#800000',
					'mediumaquamarine'     => '#66CDAA',
					'mediumorchid'         => '#BA55D3',
					'mediumseagreen'       => '#3CB371',
					'mediumspringgreen'    => '#00FA9A',
					'mediumvioletred'      => '#C71585',
					'midnightblue'         => '#191970',
					'mintcream'            => '#F5FFFA',
					'moccasin'             => '#FFE4B5',
					'navy'                 => '#000080',
					'olive'                => '#808000',
					'orange'               => '#FFA500',
					'orchid'               => '#DA70D6',
					'palegreen'            => '#98FB98',
					'palevioletred'        => '#D87093',
					'peachpuff'            => '#FFDAB9',
					'pink'                 => '#FFC0CB',
					'powderblue'           => '#B0E0E6',
					'purple'               => '#800080',
					'red'                  => '#FF0000',
					'royalblue'            => '#4169E1',
					'salmon'               => '#FA8072',
					'seagreen'             => '#2E8B57',
					'sienna'               => '#A0522D',
					'silver'               => '#C0C0C0',
					'skyblue'              => '#87CEEB',
					'slategray'            => '#708090',
					'springgreen'          => '#00FF7F',
					'steelblue'            => '#4682B4',
					'tan'                  => '#D2B48C',
					'teal'                 => '#008080',
					'thistle'              => '#D8BFD8',
					'turquoise'            => '#40E0D0',
					'violetred'            => '#D02090',
					'white'                => '#FFFFFF',
					'yellow'               => '#FFFF00',
					'aliceblue'            => '#f0f8ff',
					'azure'                => '#f0ffff',
					'bisque'               => '#ffe4c4',
					'blanchedalmond'       => '#ffebcd',
					'blueviolet'           => '#8a2be2',
					'burlywood'            => '#deb887',
					'chartreuse'           => '#7fff00',
					'coral'                => '#ff7f50',
					'cornsilk'             => '#fff8dc',
					'cyan'                 => '#00ffff',
					'darkcyan'             => '#008b8b',
					'darkgray'             => '#a9a9a9',
					'darkgrey'             => '#a9a9a9',
					'darkkhaki'            => '#bdb76b',
					'darkolivegreen'       => '#556b2f',
					'darkorchid'           => '#9932cc',
					'darksalmon'           => '#e9967a',
					'darkslateblue'        => '#483d8b',
					'darkslategrey'        => '#2f4f4f',
					'darkturquoise'        => '#00ced1',
					'deeppink'             => '#ff1493',
					'dimgray'              => '#696969',
					'dimgrey'              => '#696969',
					'floralwhite'          => '#fffaf0',
					'ghostwhite'           => '#f8f8ff',
					'goldenrod'            => '#daa520',
					'grey'                 => '#808080',
					'honeydew'             => '#f0fff0',
					'indianred'            => '#cd5c5c',
					'ivory'                => '#fffff0',
					'lavender'             => '#e6e6fa',
					'lawngreen'            => '#7cfc00',
					'lightblue'            => '#add8e6',
					'lightcyan'            => '#e0ffff',
					'lightgray'            => '#d3d3d3',
					'lightgrey'            => '#d3d3d3',
					'lightpink'            => '#ffb6c1',
					'lightseagreen'        => '#20b2aa',
					'lightslategrey'       => '#778899',
					'lightsteelblue'       => '#b0c4de',
					'linen'                => '#faf0e6',
					'mediumblue'           => '#0000cd',
					'mediumpurple'         => '#9370db',
					'mediumslateblue'      => '#7b68ee',
					'mediumturquoise'      => '#48d1cc',
					'mistyrose'            => '#ffe4e1',
					'navajowhite'          => '#ffdead',
					'oldlace'              => '#fdf5e6',
					'olivedrab'            => '#6b8e23',
					'orangered'            => '#ff4500',
					'palegoldenrod'        => '#eee8aa',
					'paleturquoise'        => '#afeeee',
					'papayawhip'           => '#ffefd5',
					'peru'                 => '#cd853f',
					'plum'                 => '#dda0dd',
					'rosybrown'            => '#bc8f8f',
					'saddlebrown'          => '#8b4513',
					'sandybrown'           => '#f4a460',
					'seashell'             => '#fff5ee',
					'slateblue'            => '#6a5acd',
					'slategrey'            => '#708090',
					'snow'                 => '#fffafa',
					'tomato'               => '#ff6347',
					'violet'               => '#ee82ee',
					'wheat'                => '#f5deb3',
					'whitesmoke'           => '#f5f5f5',
					'yellowgreen'          => '#9acd32',
				);
				$index = \strtolower($color);
				if (isset($colorNames[$index]))
					$color = $colorNames[$index];
			}

			if ('#' == \substr($color, 0, 1)) {
				// validate hex string
				$hex = \preg_replace('/[^0-9a-f]/i', '', $color);
				if ($hex) {
					if (\strlen($hex) < 6) { // minified format: double each char
						$chars = \str_split($hex);
						$hex = '';
						foreach ($chars as $c) $hex .= ($c . $c);
					}

					if (0 == $percent) {
						$color = ('#' . \substr($hex, 0, 6));
					} else {
						$color = '#';
						// convert to decimal and change luminosity
						for ($i = 0; $i < 3; $i++) {
							$dec = \hexdec(\substr($hex, $i * 2, 2));
							$dec = (int)\min(255, \max(0, \round($dec + ($dec * $percent))));
							$color .= \str_pad(\dechex($dec), 2, 0, STR_PAD_LEFT);
						}
					}
					if (false !== $alpha) {
						$color .= \str_pad(\dechex((int)\round(255.0 * $alpha)), 2, 0, STR_PAD_LEFT);
					} elseif (\strlen($hex) > 6) {
						$color .= \substr($hex, 6, 8);
					}
				}
			}
		}

		return $color;
	}
}

if (!function_exists('lws_get_theme_colors'))
{
	/** Returns a string containing 5 colors which are variations of the given color */
	function lws_get_theme_colors($name, $color = '')
	{
		if (empty($color)) {
			$colorstring = (
				$name . ':#999999;' .
				$name . '-light:#bbbbbb;' .
				$name . '-lighter:#dddddd;' .
				$name . '-alpha:#dddddd40;' .
				$name . '-dark:#666666;' .
				$name . '-darker:#333333;'
			);
		} else {
			$colorstring = (
				$name . ':' . $color . ';' .
				$name . '-light:'   . \lws_color_luminance($color, 0.4)    . ';' .
				$name . '-lighter:' . \lws_color_luminance($color, 0.8)    . ';' .
				$name . '-alpha:'   . \lws_color_luminance($color, 0, .25) . ';' .
				$name . '-dark:'    . \lws_color_luminance($color, -0.25)  . ';' .
				$name . '-darker:'  . \lws_color_luminance($color, -0.5)   . ';'
			);
		}

		return $colorstring;
	}
}

if( !function_exists('lws_array_to_html') )
{
	/** @deprecated version 5.4.0 @see \LWS\Adminpanel\Tools\Conveniences::array2html() */
	function lws_array_to_html(array $descr, $default='')
	{
		$gutenberg = false;
		if( isset($descr['wp']) ){
			$gutenberg = $descr['wp'];
			unset($descr['wp']);
		}

		$bal = $default;
		if( isset($descr['tag']) ){
			$bal = $descr['tag'];
			unset($descr['tag']);
		}
		$bal = explode(' ', $bal, 2);
		$tag = strtolower($bal[0]);
		$args = count($bal) > 1 ? (' '.$bal[1]) : '';

		$join = "\n";
		if( isset($descr['join']) ){
			$join = $descr['join'];
			unset($descr['join']);
		}

		if( 'li' == $tag )
		{
			$span = count($descr) > 1 ? 'strong' : 'span';
			foreach( $descr as $index => $item ){
				if( !\is_array($item) )
					$descr[$index] = array('tag' => $span, $item);
				$span = 'span';
			}
		}
		else if( 'ul' == $tag && !isset($descr['cast']) )
		{
			$descr['cast'] = 'li';
		}

		$cast = false;
		if( isset($descr['cast']) ){
			$cast = $descr['cast'];
			unset($descr['cast']);
		}

		foreach( $descr as $index => $item ){
			if( false !== $cast ){
				if( !\is_array($item) )
					$item = array('tag'=>$cast, $item);
				else if( !isset($item['tag']) )
					$item['tag'] = $cast;
			}
			if( \is_array($item) ){
				$descr[$index] = lws_array_to_html($item, $default);
			}
		}

		$html = implode($join, $descr);
		if( $tag )
			$html = sprintf("<{$tag}{$args}>%s</{$tag}>", $html);
		if ($gutenberg)
			$html = sprintf("<!-- wp:%s -->%s<!-- /wp:%s -->", $gutenberg, $html, $gutenberg);
		return $html;
	}
}