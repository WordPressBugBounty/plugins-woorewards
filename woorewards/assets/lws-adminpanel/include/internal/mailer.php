<?php
namespace LWS\Adminpanel\Internal;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Manage mail formating and sending.
 *
	* Send a mail with direct content
		 * @param string user mail,
		 * @param string a slug to define the mail content,
		 * @param object content object with {subject:string, body:string, style:string} containing simlpe text, html and css.
		 * @param data array any relevant data required by shortcodes
		\do_action('lws_mail_send_raw', $email, $slug, $settings, $data);

	* Add new email shortcodes
		\add_filter('lws_adminpanel_mail_shortcodes_' . $slug,
			function($text, string $tag, array $args, string $content, array $data, string $email, array $users) {...},
		10, 7);

	*	Apply mail dedicated shortcodes (if you want recursive shortcodes on given contents)
		 * @param $text string text to work on
		 * @param $slug string mail template
		 * @param $data array anything
		 * @param $email string recepient
		 * @param $users array of \WP_users
		\apply_filters('lws_mail_send_do_shortcodes', $content, $slug, $data, $email, $users);
 *
 * Legacy (includes an admin page conveniency)
 *
 *	To send a mail, use the action 'lws_mail_send' with parameters email, template_name, data.
 *
 *	You must add a filter to set mail settings with hook 'lws_mail_settings_' . $template_name.
 *	@see defaultSettings about values to return.
 *
 *	You must add a filter to define mail body with hook 'lws_mail_body_' . $template_name.
 *	Second argument is data given to 'lws_mail_send'.
 *	If a WP_Error is given instead, assume it is a demo (usually for stygen).
 *
 *	To get a mail settings value, use the filter 'lws_mail_snippet'.
 *
 *	@note
 *	During dev, notes that mailbox should prevent your image display if their url contains 127.0.0.1
 *
 *	This class use singleton.
 *
 * Settings array for a single mail is:
 *	* 'domain' => '', // groups several mail template with few commun settings. Must be a hard coded text.
 *	* 'settings_domain_name' => '', // name display in admin settings screen.
 *	* 'settings_name' => '', // name display in admin settings screen.
 *  * 'settings' => '', // settings reference, used for translation. Must be a hard coded text.
 *	* 'about' => '', // describe the purpose of this mail to the admin settings screen.
 *	* 'infomessage' => '', // replace the default help on to of stygen
 *	* 'subject' => '', // subject of the mail.
 *	* 'title' => '', // set at top of mail body.
 *	* 'header' => '', // presentation text in the body.
 *	* 'demo_file_path' => false, // path to a php/html file with a fake content for styling purpose.
 *	* 'css_file_url' => false, // url to a css file.
 *	* 'subids' => (string|array) inline editable text id.
 *	* 'fields' => array(), // (array of field array) as for lws_register_pages, add extra fields in mail settings.
 *	* 'footer' => '', // set at end of mail body.
 *	* 'headerpic' => false, // media ID of a picture set at the very top of the mail.
 *	* 'logo_url' => '' // <img> html code build from 'headerpic'
 *	* 'bcc_admin' => false // (boolval|string) send a blind copy to specified email (or admin if true or 'on'). Let choice to user with a field ['id' => 'lws_mail_bcc_admin_'.$template, 'type' => 'box']
 *
 *	Uninstall mails settings with:
 * @code
	foreach( array('lws_domain') as $domain )
	{
		$mailprefix = "lws_mail_{$domain}_attribute_";
		delete_option($mailprefix.'headerpic');
		delete_option($mailprefix.'footer');
	}
	foreach( array('lws_template1', 'lws_template2') as $template )
	{
		delete_option('lws_mail_subject_'.$template);
		delete_option('lws_mail_preheader_'.$template);
		delete_option('lws_mail_template_'.$template);
		delete_option('lws_mail_title_'.$template);
		delete_option('lws_mail_header_'.$template);
		delete_option('lws_mail_bcc_admin_'.$template);
	}
 * @endcode
 **/
class Mailer
{
	private $Parsedown = null;
	public $settings   = array();
	public $trSettings = array();
	public $altBody    = false;

	/** $coupon_id (array|id) an array of coupon post id.
	 * That function switch langage the time it formats and send the email
	 * @see https://wpml.org/documentation/support/sending-emails-with-wpml/ */
	function sendMail($email, $template, $data=null)
	{
		do_action('wpml_switch_language_for_email', $email); // switch to user language before format email

		$settings = $this->getSettings($template, true);
		$settings = $this->translateSettings($template, $settings);

		$settings['user_email'] = $email;
		$settings = (array)\apply_filters('lws_mail_arguments_' . $template, $settings, $data);

		$headers = array('Content-Type: text/html; charset=UTF-8');
		$from = $this->getMailProvider($template);
		if ($from) $headers[] = $from;

		if( isset($settings['bcc_admin']) && !empty($settings['bcc_admin']) )
		{
			$admMail = $settings['bcc_admin'];
			if( (true === $admMail) || ('on' == $admMail) )
				$admMail = \get_option('admin_email');
			if( \is_email($admMail) )
				$headers[] = 'Bcc: ' . $admMail;
		}

		$mail = (object)[
			'users'    => $this->getUsers($email),
			'emails'   => $email,
			'template' => $template,
			'settings' => $settings,
			'data'     => $data,
		];

		$this->altBody = true;
		\wp_mail(
			$email,
			$this->applyShortcodes($settings['subject'], $mail),
			$this->applyShortcodes($this->getContent($template, $settings, $data), $mail),
			\apply_filters('lws_mail_headers_' . $template, $headers, $data)
		);
		$this->altBody = false;

		do_action('wpml_restore_language_from_email');
	}

	/** @return string from mail header */
	private function getMailProvider($template)
	{
		$from = '';
		$fromEMail = \sanitize_email(\get_option('woocommerce_email_from_address'));
		if ($fromEMail) {
			$fromName = \wp_specialchars_decode(\esc_html(\get_option('woocommerce_email_from_name')), ENT_QUOTES);
			if ($fromName) {
				$from = sprintf('From: %s <%s>', $fromName, $fromEMail);
			} else {
				$from = 'From: ' . $fromEMail;
			}
		}
		return \apply_filters('lws_adm_mail_header_from', $from, $template);
	}

	/** Send a mail with direct content
		 * @param string user mail,
		 * @param string a slug to define the mail content,
		 * @param object content object with {subject:string, body:string, style:string} containing simlpe text, html and css.
		 * @param data array any relevant data required by shortcodes */
	public function sendRaw(string $targetEmail, string $slug, object $content, array $data)
	{
		$headers = ['Content-Type: text/html; charset=UTF-8'];
		$from = $this->getMailProvider($slug);
		if ($from) $headers[] = $from;

		$body = <<<EOT
<!DOCTYPE html><html xmlns='http://www.w3.org/1999/xhtml'>
<head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' /></head>
<body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' offset='0'>
EOT;
		$body .= $this->inlineCSS($content->body, $content->style);
		$body .= '</body></html>';

		$users = $this->getUsers($targetEmail);

		$this->altBody = true;
		\wp_mail(
			$targetEmail,
			$this->applyShortcodes2($content->subject, $slug, $data, $targetEmail, $users),
			$this->applyShortcodes2($body, $slug, $data, $targetEmail, $users),
			\apply_filters('lws_mail_headers_' . $slug, $headers, $data)
		);
		$this->altBody = false;
	}

	public function applyShortcodes2($text, $slug, $data, $email, $users)
	{
		$doubleQuote = '"(?:[^"]|(?<=\\\\)")*"';
		$simpleQuote = "'(?:[^']|(?<=\\\\)')*'";
		$pattern = "/(?<!\\[)\\[(\\w+)((?:\\s+\\w+=(?:{$doubleQuote}|{$simpleQuote}))*)\\]/m";

		$offset = 0;
		$newtext = '';
		while (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			// get before
			$newtext .= \substr($text, $offset, $matches[0][1] - $offset);
			// go forward
			$offset = $matches[0][1] + \strlen($matches[0][0]);

			/// 0: full, 1: key, 2: args
			$full = $matches[0][0];
			$tag = \strtolower($matches[1][0]);
			$args = \shortcode_parse_atts(\ltrim((string)$matches[2][0]));
			$content = '';

			// is a shortcode with a content
			$ending = "/(?<!\\[)\\[\\/{$tag}\\]/m";
			if (preg_match($ending, $text, $closing, PREG_OFFSET_CAPTURE, $offset)) {
				$content = \substr($text, $offset, $closing[0][1] - $offset);
				$offset = $closing[0][1] + \strlen($closing[0][0]);
				$full .= $content . $closing[0][0];
			}

			// apply shortcodes, any customs
			$value = \apply_filters('lws_adminpanel_mail_shortcodes_' . $slug, false, $tag, $args, $content, $data, $email, $users);
			if (false  !== $value) {
				$newtext .= $value;
			} else switch ($tag) {
				// generics shortcodes
				case 'user_name':
					$newtext .=  \LWS\Adminpanel\Internal\Mailer::getUserNames($users);
					break;
				case 'site_link':
					$newtext .=  $this->getSiteLink($args, $content, $slug, $data, $email, $users);
					break;
				default:
					/// @param string $match[1] the code of the shortcode
					/// @param string \ltrim($match[2]) the raw arguments, @see \wp_parse_args() to parse them before use
					/// @param string a slug to define the mail content,
					/// @param data array any relevant data required by shortcodes
					/// @param string user mail,
					$value = \apply_filters('lws_adminpanel_mail_shortcodes2', false, $tag, $args, $content, $slug, $data, $email, $users);
					if (false  !== $value) {
						$newtext .= $value;
					} else {
						$newtext .= $full;
					}
					break;
			}
		}
		return $newtext .=\substr($text, $offset);
	}

	private function applyShortcodes($text, $mail)
	{
		$doubleQuote = '"(?:[^"]|(?<=\\\\)")*"';
		$simpleQuote = "'(?:[^']|(?<=\\\\)')*'";
		$pattern = "/(?<!\\[)\\[(\\w+)((?:\\s+\\w+=(?:{$doubleQuote}|{$simpleQuote}))*)\\]/m";
		return \preg_replace_callback($pattern, function(array $match) use ($mail) {
			/// 0: full, 1: key, 2: args
			$match[1] = \strtolower($match[1]);
			if ('user_name' === $match[1]) {
				return \LWS\Adminpanel\Internal\Mailer::getUserNames($mail->users);
			} else {
				/// @param string $match[1] the code of the shortcode
				/// @param string \ltrim($match[2]) the raw arguments, @see \wp_parse_args() to parse them before use
				/// @param object $mail all details about mail [array users, string emails, string template, array settings, mixed data]
				return \apply_filters('lws_adminpanel_mail_shortcodes', $match[1], \ltrim($match[2]), $mail);
			}
		}, $text);
	}

	private function getUsers($email)
	{
		if (\is_string($email)) $email = \array_map('\trim', \explode(',', $email));
		if (\is_array($email)) {
			return \array_filter(\array_map(function($email) {
				return \get_user_by('email', $email);
			}, $email), function($u) {
				return $u && $u->ID;
			});
		}
		return [];
	}

	public static function getUserNames(array $users): string
	{
		$names = [];
		foreach ($users as $user) {
			/** @var \WP_User $user */
			$name = $user->user_login;
			if( $user->display_name )
				$name = $user->display_name;
			else if( $user->user_nicename )
				$name = $user->user_nicename;
			$names[$user->ID] = $name;
		}
		return \implode(', ', $names);
	}

	private function getSiteLink($args, $content, $slug, $data, $email, $users)
	{
		$args = \wp_parse_args($args, [
			'path' => '',
			'text' => '',
		]);

		if ($args['text']) $args['text'] = \htmlentities2($args['text']);
		elseif ($content) $args['text'] = $this->applyShortcodes2($content, $slug, $data, $email, $users);
		else $args['text'] = \htmlentities2(\get_bloginfo('name'));

		return sprintf(
			'<a href="%s" target="_blank" title="%s">%s</a>',
			\esc_attr(\site_url($args['path'])),
			\esc_attr(\get_bloginfo('description')),
			$args['text']
		);
	}

	/**	@return array to set in admin page registration as 'groups', each item representing a group array.
	 *	@param $templates array of template names. */
	function settingsGroup($templates)
	{
		$mails = array();

		if( !is_array($templates) )
		{
			if( is_string($templates) )
				$templates = array($templates);
			else
				return $mails;
		}

		foreach( $this->groupsByDomain($templates) as $domain => $settings )
		{
			$mails['D_'.$domain] = $this->buildDomainSettingsGroup($domain, $settings['name']);

			foreach( $settings['settings'] as $template => $args )
				$mails[$template] = $this->buildTemplateSettingsGroup($template, $args);
		}
		return $mails;
	}

	/** @return mixed mail settings property.
	 * @param $value (string) default value.
	 * @param $template (string) the mail template name we are looking for.
	 * @param $key (string) the property name @see defaultSettings */
	function settingsData($value, $template, $key)
	{
		$settings = $this->getSettings($template, true);
		if( isset($settings[$key]) && !empty($settings[$key]) )
			$value = $settings[$key];
		return $value;
	}

	protected static function defaultSettings()
	{
		return array(
			'domain' => '', // groups several mail template with few commun settings. Must be a hard coded text.
			'settings_domain_name' => '', // name display in admin settings screen.
			'settings_name' => '', // name display in admin settings screen.
			'icon' => '', // icon displayed in admin group.
			'settings' => '', // settings reference, used for translation. Must be a hard coded text.
			'about' => '', // describe the purpose of this mail to the admin settings screen.
			'infomessage' => '', // replace the default help on to of stygen
			'subject' => '', // subject of the mail.
			'preheader' => '', // excerpt of the mail.
			'title' => '', // set at top of mail body.
			'header' => '', // presentation text in the body.
			'demo_file_path' => false, // path to a php/html file with a fake content for styling purpose.
			'css_file_url' => false, // url to a css file.
			'fields' => array(), // (array of field array) as for lws_register_pages, add extra fields in mail settings.
			'footer' => '', // set at end of mail body.
			'headerpic' => false, // media ID of a picture set at the very top of the mail.
			'logo_url' => '' // <img> html code build from 'headerpic'
		);
	}

	function parsedown($txt)
	{
		if (null === $this->Parsedown) {
			require_once LWS_ADMIN_PANEL_ASSETS . '/Parsedown.php';
			$this->Parsedown = new \LWS\Adminpanel\Parsedown();
			$this->Parsedown->setBreaksEnabled(true);
		}
		return $this->Parsedown->text($txt);
	}

	static function instance()
	{
		static $_instance = null;
		if( $_instance == null )
			$_instance = new self();
		return $_instance;
	}

	protected function __construct()
	{
		$this->settings = array();
		$this->trSettings = array();

		/** Send a mail with direct content
		 * @param string user mail,
		 * @param string a slug to define the mail content,
		 * @param object content object with {subject:string, body:string, style:string} containing simlpe text, html and css.
		 * @param data array any relevant data required by shortcodes */
		add_action('lws_mail_send_raw', array($this, 'sendRaw'), 10, 4);

		/**
		 * @param $text string text to work on
		 * @param $slug string mail template
		 * @param $data array anything
		 * @param $email string recepient
		 * @param $users array of \WP_users */
		add_action('lws_mail_send_do_shortcodes', array($this, 'applyShortcodes2'), 10, 5);

		/** Send a mail
		 * @param string user mail,
		 * @param string mail_template,
		 * @param array data (whatever is needed by your template) pass to hook 'lws_woorewards_mail_body_' . $template */
		add_action('lws_mail_send', array($this, 'sendMail'), 10, 3);
		/** return the settings piece of data.
		 * @param false (not used)
		 * @param string template_name
		 * @param mixed settings key (as title, header...) @see defaultSettings */
		add_filter('lws_mail_snippet', array($this, 'settingsData'), 10, 3);

		add_filter('lws_markdown_parse', array($this, 'parsedown'));

		$this->altBody = false;
		add_action('phpmailer_init', array($this, 'addAltBody'), 9, 1);
	}

	protected function translateSettings($template, &$settings)
	{
		if( !isset($this->trSettings[$template]) )
			$this->trSettings[$template] = array();

		$local = \get_locale();
		$force = !isset($this->trSettings[$template][$local]);
		if (\apply_filters('lws_adminpanel_mail_force_settings_translate', $force, $template, $local, $settings)) {
			$this->trSettings[$template][$local] = $settings;

			$translations = array(
				'subject'   => "{$settings['domain']} mail - {$settings['settings']} - Subject",
				'preheader' => "{$settings['domain']} mail - {$settings['settings']} - Preheader",
				'title'     => "{$settings['domain']} mail - {$settings['settings']} - Title",
				'header'    => "{$settings['domain']} mail - {$settings['settings']} - Header",
				'footer'    => "{$settings['domain']} mail - Footer",
			);

			foreach( $translations as $k => $label )
			{
				if( isset($settings[$k]) )
				{
					$this->trSettings[$template][$local][$k] = \apply_filters(
						'wpml_translate_single_string',
						$settings[$k],
						'Widgets',
						\ucfirst($label)
					);
				}
			}
		}
		return $this->trSettings[$template][$local];
	}

	protected function getSettings($template, $loadValues=false, $reset=false)
	{
		if( !isset($this->settings[$template]) || $reset )
		{
			$this->settings[$template] = apply_filters('lws_mail_settings_' . $template, self::defaultSettings());
			if( !(isset($this->settings[$template]['settings']) && $this->settings[$template]['settings']) )
				$this->settings[$template]['settings'] = $this->settings[$template]['settings_name'];
		}

		if( $loadValues && (!isset($this->settings[$template]['loaded']) || !$this->settings[$template]['loaded']) )
		{
			$value = trim(\get_option('lws_mail_subject_'.$template));
			if( !empty($value) ) $this->settings[$template]['subject'] = $value;
			$value = trim(\get_option('lws_mail_preheader_'.$template));
			if( !empty($value) ) $this->settings[$template]['preheader'] = $value;
			$value = trim(\get_option('lws_mail_title_'.$template));
			if( !empty($value) ) $this->settings[$template]['title'] = $value;
			$value = trim(\get_option('lws_mail_header_'.$template));
			if( !empty($value) ) $this->settings[$template]['header'] = $value;

			$domain = !empty($this->settings[$template]['domain']) ? $this->settings[$template]['domain'] : $template;
			$value = trim(\get_option("lws_mail_{$domain}_attribute_footer"));
			if( !empty($value) ) $this->settings[$template]['footer'] = $value;

			$value = intval(\get_option("lws_mail_{$domain}_attribute_headerpic"));
			if( !empty($value) ) $this->settings[$template]['headerpic'] = $value;
			if( !empty($this->settings[$template]['headerpic']) )
			{
				$value = \wp_get_attachment_image($this->settings[$template]['headerpic'], 'small');
				if( !empty($value) ) $this->settings[$template]['logo_url'] = $value;
			}

			if( !isset($this->settings[$template]['bcc_admin']) )
				$this->settings[$template]['bcc_admin'] = \get_option('lws_mail_bcc_admin_'.$template);

//			$this->settings[$template]['title']   = $this->Parsedown->text($this->settings[$template]['title']);

			$this->settings[$template]['loaded'] = true;
		}

		return $this->settings[$template];
	}

	protected function getContent($template, &$settings, &$data)
	{
		$style = '';
		if( !empty($settings['css_file_url']) )
			$style = \apply_filters('stygen_inline_style', '', $settings['css_file_url'], 'lws_mail_template_'.$template);

		return $this->content($template, $settings, $data, $style);
	}

	protected function content($template, &$settings, &$data, $style='')
	{
		$html = "<!DOCTYPE html><html xmlns='http://www.w3.org/1999/xhtml'>";
		$html .= "<head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";
		if( !empty($style) && ! class_exists('Pelago\\Emogrifier'))
			$html .= "<style>$style</style>";
		$html .= "</head><body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' offset='0'>";
		if( isset($settings['preheader']) && $settings['preheader'] )
		{
			$preheader = "<span class='preheader' style='display:none !important;'>{$settings['preheader']}</span>";
			$html .= \apply_filters('lws_mail_preheader_' . $template, $preheader, $data, $settings);
		}
		$html .= $this->banner($template, $data, $settings);
		$html .= \apply_filters('lws_mail_body_' . $template, '', $data, $settings);
		$html .= $this->footer($template, $data, $settings);
		$html .= "</body></html>";

		$html = \apply_filters('lws_adminpanel_mail_body', $html, $template, $settings, $data, $style);
		$html = $this->inlineCSS($html, $style);
		return $html;
	}

	protected function inlineCSS($html, $style ='')
	{
		$done = false;
		if(class_exists('WooCommerce') && class_exists('DOMDocument')) {
			if(class_exists('Pelago\\Emogrifier\\CssInliner')) {
				try {
					$inliner = \Pelago\Emogrifier\CssInliner::fromHtml($html)->inlineCss($style);
					$document = $inliner->getDomDocument();
					\Pelago\Emogrifier\HtmlProcessor\HtmlPruner::fromDomDocument($document)->removeElementsWithDisplayNone();

					$html = \Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter::fromDomDocument($document)
						->convertCssToVisualAttributes()
						->render();

					$done = true;
				} catch (\Exception $e) {
					$logger = \wc_get_logger();
					$logger->error($e->getMessage(), array('source' => 'emogrifier'));
				}
			} elseif(class_exists('Pelago\\Emogrifier')) {
				$emogrifier = new \Pelago\Emogrifier($html, $style);
				$content    = $emogrifier->emogrify();
				$html_prune = \Pelago\Emogrifier\HtmlProcessor\HtmlPruner::fromHtml( $content );
				$html_prune -> removeElementsWithDisplayNone();
				$html       = $html_prune->render();
				$done = true;
			}
		}
		if (!$done) {
			$html = ('<style type="text/css">' . $style . '</style>' . $html);
		}
		return $html;
	}

	/** Ask for a mail content with placeholder data.
	 * Do not embed any style.
	 * provided for class-stygen.php */
	function getDemo($template)
	{
		$settings = $this->getSettings($template, true);
		$data = new \WP_Error('gizmo', __("This is a test."));
		return $this->content($template, $settings, $data);
	}

	protected function banner($template, $data, $settings)
	{
		$html = <<<EOT
	<div class='lwss_selectable lws-mail-wrapper' style='width:100%; height:100%' data-type='Email Wrapper'>
	<center>
		<center>{$settings['logo_url']}</center>
		<table class='lwss_selectable lws-main-conteneur $template' data-type='Main Border'>
			<thead>
				<tr>
					<td class='lwss_selectable lws-top-cell lwss_modify $template' data-id='lws_mail_title_$template' data-type='Title'>
						<div class='lwss_modify_content'>{$settings['title']}</div>
					</td>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class='lwss_selectable lws-middle-cell lwss_modify $template' data-id='lws_mail_header_$template' data-type='Header'>
						<div class='lwss_modify_content'>{$settings['header']}</div>
					</td>
				</tr>
EOT;
		return apply_filters('lws_mail_head_' . $template, $html, $settings, $data);
	}

	protected function footer($template, $data, $settings)
	{
		$html = <<<EOT
			</tbody>
			<tfoot>
				<tr>
					<td class='lwss_selectable lws-bottom-cell $template' data-type='Footer'>{$settings['footer']}</td>
				</tr>
				</tfoot>
		</table>
	</center>
	</div>
EOT;
		return apply_filters('lws_mail_foot_' . $template, $html, $settings, $data);
	}

	protected function groupsByDomain($templates)
	{
		$domains = array();
		foreach($templates as $template)
		{
			$settings = $this->getSettings($template, false, true);
			if( empty($settings['domain']) )
			{
				if( !isset($domains[$template]) || empty($domains[$template]['name']) )
					$domains[$template]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$template]['settings'][$template] = $settings;
			}
			else
			{
				$domain = $settings['domain'];
				if( !isset($domains[$domain]) || empty($domains[$domain]['name']) )
					$domains[$domain]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$domain]['settings'][$template] = $settings;
			}
		}
		return $domains;
	}

	protected function buildDomainSettingsGroup($domain, $title)
	{
		$prefix = "lws_mail_{$domain}_attribute_";

		return array(
			'id' => 'lws_mail_d_' . $domain,
			'icon' => 'lws-icon-letter',
			'title' => empty($title) ? __("Email Settings", 'lws-adminpanel') : sprintf(__("%s Email Settings", 'lws-adminpanel'), $title),
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/kb/wr-email-header-and-footer/'),
			'text'=> __("Once you've finished the email settings, <b>save your changes</b><br/>You will then see the result in the style editor below<br/>Select the elements you wish to change and have fun!", 'lws-adminpanel'),
			'fields' => array(
				array(
					'type'  => 'media',
					'title' => __("Header picture", 'lws-adminpanel'),
					'id'    => $prefix.'headerpic',
					'extra' => array(
						'size' => 'medium',
					)
				),
				array(
					'type'  => 'wpeditor',
					'title' => __("Footer text", 'lws-adminpanel'),
					'id'    => $prefix.'footer',
					'extra' => array(
						'editor_height' => 30,
						'wpml' => \ucfirst("{$domain} mail - Footer"),
					)
				)
			)
		);
	}

	protected function buildTemplateSettingsGroup($template, $settings)
	{
		$mailId = 'lws_mail_t';
		if( isset($settings['domain']) )
			$mailId .= '_' . $settings['domain'];
		$mailId .= '_' . $template;

		$mail = array(
			'id'    => $mailId,
			'icon'  => $settings['icon'],
			'title' => $settings['settings_name'] ? $settings['settings_name'] : __("Email details", 'lws-adminpanel'),
			'text'  => $settings['about'] ? $settings['about'] : '',
			'fields' => array(
				array(
					'id'    => 'lws_mail_subject_'.$template,
					'title' => __("Subject", 'lws-adminpanel'),
					'type'  => 'text',
					'extra' => array(
						'maxlength'   => 350,
						'placeholder' => $settings['subject'],
						'size'        => '40',
						'wpml'        => \ucfirst("{$settings['domain']} mail - {$settings['settings']} - Subject"),
					)
				),
				array(
					'id'    => 'lws_mail_preheader_'.$template,
					'title' => __("Preheader", 'lws-adminpanel'),
					'type'  => 'text',
					'extra' => array(
						'maxlength'   => 350,
						'placeholder' => $settings['preheader'],
						'size'        => '40',
						'wpml'        => \ucfirst("{$settings['domain']} mail - {$settings['settings']} - Preheader"),
					)
				),
			)
		);

		static $addBCC = null;
		if (null === $addBCC) {
			$addBCC = (bool)\get_option('lws_adminpanel_mailer_add_bcc_field', false);
		}
		if ($addBCC) {
			$mail['fields']['bcc_admin'] = array(
				'id' => 'lws_mail_bcc_admin_' . $template,
				'type' => 'input',
				'extra' => array('type' => 'email'),
				'title' => __("Blind carbon copy to (bcc)", 'lws-adminpanel'),
			);
		}

		if (isset($settings['doclink'])) {
			$mail['extra'] = array('doclink' => $settings['doclink']);
		}

		if( isset($settings['fields']) && is_array($settings['fields']) && !empty($settings['fields']) )
			$mail['fields'] = array_merge($mail['fields'], $settings['fields']);

		if( !empty($settings['css_file_url']) )
		{
			$extra = array(
				'template' => $template,
				'html' => !empty($settings['demo_file_path']) ? $settings['demo_file_path'] : false,
				'css' => $settings['css_file_url'],
				'purpose' => 'mail'
			);
			if( isset($settings['subids']) && !empty($settings['subids']) )
				$extra['subids'] = is_array($settings['subids']) ? $settings['subids'] : array($settings['subids']);
			$extra['subids']['lws_mail_title_'.$template]  = \ucfirst("{$settings['domain']} mail - {$settings['settings']} - Title");
			$extra['subids']['lws_mail_header_'.$template] = \ucfirst("{$settings['domain']} mail - {$settings['settings']} - Header");

			$mail['fields'][] = array(
				'id' => 'lws_mail_template_'.$template,
				'type' => 'stygen',
				'extra' => $extra
			);
		}

		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_'.$template,
			'title' => __("Receiver Email", 'lws-adminpanel'),
			'type' => 'text',
			'extra' => array(
				'help' => __("Test your email to see how it looks", 'lws-adminpanel'),
				'noconfirm' => true,
				'size' => '40'
			)
		);
		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_btn_'.$template,
			'title' => __("Send test email", 'lws-adminpanel'),
			'type' => 'button',
			'extra' => array('callback' => array($this, 'test'))
		);

		return $mail;
	}

	function test($id, $data)
	{
		$base = 'lws_adminpanel_mail_tester_btn_';
		$len = strlen($base);
		if( substr($id, 0, $len) == $base && !empty($template=substr($id,$len)) && isset($data['lws_adminpanel_mail_tester_'.$template]) )
		{
			$email = sanitize_email($data['lws_adminpanel_mail_tester_'.$template]);
			if( \is_email($email) )
			{
				do_action('lws_mail_send', $email, $template, new \WP_Error());
				return __("Test email sent.", 'lws-adminpanel');
			}
			else
				return __("Test email is not valid.", 'lws-adminpanel');
		}
		return false;
	}

	/** add a plain text version of our email */
	function addAltBody($phpmailer)
	{
		if( !$this->altBody )
			return;
		if( $phpmailer->ContentType === 'text/plain' )
			return;
		$phpmailer->AltBody = \LWS\Adminpanel\Tools\Conveniences::htmlToPlain($phpmailer->Body);
	}
}