<?php
namespace LWS\Adminpanel\Internal;
if( !defined( 'ABSPATH' ) ) exit();

class Ajax
{
	static function install()
	{
		new self();
	}

	private function __construct()
	{
		/// when not connected user, hook is 'wp_ajax_nopriv_' . $hook
		if( defined('DOING_AJAX') )
		{
			add_action( 'wp_ajax_lws_adminpanel_googlefontlist', array( $this, 'googleFontList') );
			add_action( 'wp_ajax_lws_adminpanel_standardfontlist', array( $this, 'standardFontList') );

			add_action( 'wp_ajax_lws_adminpanel_get_posts', array( $this, 'getPosts') );
			add_action( 'wp_ajax_lws_adminpanel_get_post_types', array( $this, 'getPostTypes') );
			add_action( 'wp_ajax_lws_adminpanel_get_users', array( $this, 'getUsers') );
			add_action( 'wp_ajax_lws_adminpanel_get_taxonomy', array( $this, 'getTaxonomy') );
			add_action( 'wp_ajax_lws_adminpanel_get_roles', array( $this, 'getRoles') );
			add_action( 'wp_ajax_lws_adminpanel_get_media_sizes', array( $this, 'getMediaSizes') );
			add_action( 'wp_ajax_lws_adminpanel_get_order_status', array( $this, 'getOrderStatus') );

			add_action( 'wp_ajax_lws_adminpanel_forget_notice', array( $this, 'permanentDismiss') );

			\add_action( 'wp_ajax_lws_adminpanel_wc_products_and_variations_list', array( $this, 'getWCProductsAndVariations') );
		}
		add_filter( 'lws_autocomplete_compose_page', array( $this, 'composePage') );
		add_filter( 'lws_autocomplete_compose_user', array( $this, 'composeUser') );
		add_filter( 'lws_autocomplete_compose_taxonomy', array( $this, 'composeTaxonomy') );
	}

	public function permanentDismiss()
	{
		if( isset($_GET['key']) && !empty($_GET['key']) )
		{
			$key = sanitize_text_field($_GET['key']);
			\update_site_option(
				'lws_adminpanel_notices',
				array_filter(
					(array)\get_site_option('lws_adminpanel_notices', array()),
					function($k)use($key){return $key!=$k;},
					ARRAY_FILTER_USE_KEY
				)
			);
		}
	}

	/** @param $_REQUEST['term'] (string) filter on post_title
	 * @param $_REQUEST['spec'] (array, json base64 encoded /optional) @see specToFilter.
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getPosts()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}

		$fields = array('post_author', 'post_date', 'post_date_gmt', 'post_title', 'post_status', 'comment_status', 'post_name', 'post_modified', 'post_modified_gmt', 'post_parent', 'guid', 'post_type', 'post_mime_type');
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$where = $this->specToFilter($fields);

		if( !empty($term) )
		{
			if( $fromValue )
				$where[] = ("ID IN (" . implode(',', $term) . ")");
			else
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$term%"); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( !empty($where) )
			$sql .= " WHERE " . implode(' AND ', $where);

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}

		$results = $wpdb->get_results($sql, OBJECT_K);
		$results = \array_filter($results, function($row) {
			return \is_post_publicly_viewable($row->value) || \current_user_can('read_post', $row->value);
		});
		wp_send_json($results); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	/* Lists all registered post types, including custom post types */
	public function getPostTypes()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}

		$typeslist = array();
		$types = \get_post_types(array(), 'objects');
		foreach ( $types as $type )
		{
			if($type->public)
			{
				$typeslist[$type->name] = array('value'=>$type->name, 'label'=> $type->label);
			}
		};
		\wp_send_json($typeslist);
	}

	public function getOrderStatus()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}
		\wp_send_json(\LWS\Adminpanel\Tools\Conveniences::getOrderStatusList(true));
	}

	// return Media Sizes
	public function getMediaSizes()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}
		\wp_send_json(\LWS\Adminpanel\Tools\MediaHelper::getMediaSizes());
	}

	/** @param $_REQUEST['term'] (string) filter on post_title
	 * @param $_REQUEST['spec'] (array, json base64 encoded /optional) @see specToFilter.
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getUsers()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}
		if (!\current_user_can('list_users')) {
			\wp_send_json_error('Unauthorized reading');
		}

		$fields = array('user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name');
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$where = $this->specToFilter($fields);

		if( !empty($term) )
		{
			if( $fromValue )
				$where[] = ("ID IN (" . implode(',', $term) . ")");
			else
			{
				$term = "%$term%";
				$where[] = $wpdb->prepare("(user_login LIKE %s OR user_nicename LIKE %s OR user_email LIKE %s OR display_name LIKE %s)", $term, $term, $term, $term);
			}
		}

		$sql = "SELECT ID as value, user_login as label, concat(user_login, ' - ', display_name, ' - ', user_email) as html FROM {$wpdb->users}";
		if( !empty($where) )
			$sql .= " WHERE " . implode(' AND ', $where);

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}

		wp_send_json($wpdb->get_results($sql, OBJECT_K)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @param $_REQUEST['term'] (string) filter on role name */
	public function getRoles()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}

		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = '';
		if( isset($_REQUEST['term']) )
		{
			if( $fromValue )
			{
				$term = array_map('trim', array_map('sanitize_text_field', is_array($_REQUEST['term']) ? $_REQUEST['term'] : array($_REQUEST['term'])));
			}
			else
			{
				$term = trim(sanitize_text_field($_REQUEST['term']));
			}
		}

		$roles = array_map('\translate_user_role', \wp_roles()->get_names());
		asort($roles,  SORT_LOCALE_STRING | SORT_FLAG_CASE);

		$results = array();
		foreach( $roles as $value => $label)
		{
			if( !($ok = empty($term)) )
				$ok = ($fromValue ? in_array($value, $term) : (false !== strpos($label, $term)));

			if( $ok )
				$results[$value] = array('value' => $value, 'label' => $label);
		}

		wp_send_json($results);
	}

	/** @param $_REQUEST['term'] (string) filter on taxonomy id, slug or name.
	 * @param $_REQUEST['spec'] (array, json base64 encoded /optional) @see specToFilter.
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getTaxonomy()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}

		$fields = array('taxonomy', 'parent', 'slug', 'name', 'term_group', 'count', 'description');
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$where = $this->specToFilter($fields);

		if( !empty($term) )
		{
			if( $fromValue )
				$where[] = ("t.term_id IN (" . implode(',', $term) . ")");
			else
			{
				$term = "%$term%";
				$where[] = $wpdb->prepare("(t.name LIKE %s OR t.slug LIKE %s)", $term, $term); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}
		}

		$sql = "SELECT t.term_id as value, t.name as label FROM {$wpdb->terms} as t";
		$sql .= " INNER JOIN {$wpdb->term_taxonomy} as x ON t.term_id=x.term_id";
		if( !empty($where) )
			$sql .= " WHERE " . implode(' AND ', $where);

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}

		wp_send_json($wpdb->get_results($sql, OBJECT_K)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	/** autocomplete/lac compliant.
	 * Search wp_post(wc_product & variations) on id (or name if fromValue is false or missing).
	 * @see hook 'lws_woorewards_wc_products_and_variations_list'.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getWCProductsAndVariations()
	{
		if (!(isset($_REQUEST['lac']) && \wp_verify_nonce($_REQUEST['lac'], 'lws_lac_model'))) {
			\wp_send_json_error('Unauthorized access');
		}

		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$spec = array();

		global $wpdb;
		$sql = array(
			'SELECT' => "SELECT p.ID as value, p.post_title as label",
			'FROM' => "FROM {$wpdb->posts} as p",
			'JOIN' => '',
			'WHERE' => '',
			'GROUP' => '',
		);

		if ($fromValue) {
			$sql['WHERE'] = " WHERE p.ID IN (" . implode(',', $term) . ")";
		} else {
			$sql['SELECT'] .= ", GROUP_CONCAT(v.ID SEPARATOR ',') as variations";
			$sql['GROUP'] = "GROUP BY p.ID";
			$sql['JOIN'] = "LEFT JOIN {$wpdb->posts} as v ON p.ID=v.post_parent AND v.post_type='product_variation'";
			$sql['WHERE'] = "WHERE p.post_type='product' AND (p.post_status='publish' OR p.post_status='private')";

			if ($term) {
				$search = trim($term, "%");
				$sql['WHERE'] .= $wpdb->prepare(" AND p.post_title LIKE %s", "%$search%");
			}
		}

		$sql = $this->pager(implode(' ', $sql), 'p.post_title');
		$products = $wpdb->get_results($sql);
		if ($products) {
			for ($i=0 ; $i<count($products) ; $i++) {
				$product = &$products[$i];
				if (isset($product->variations) && $product->variations) {
					$grp = (object)array(
						'value'      => $product->value . 'g',
						'label'      => $product->label,
						'variations' => false,
					);
					$grp->group = $wpdb->get_results(<<<EOT
SELECT p.ID as value, CONCAT('#', p.ID, ' - ', p.post_title) as label FROM {$wpdb->posts} as p
WHERE p.post_type='product_variation' AND (p.post_status='publish' OR p.post_status='private')
AND ID IN ({$product->variations})
ORDER BY p.post_title ASC
EOT
					);
					\array_splice($products, $i+1, 0, array($grp));
				}
			}
		}
		\wp_send_json($products);
	}

	/** @return $sql with order by and limit appended. */
	protected function pager($sql, $orderBy='', $dir='ASC')
	{
		if( !empty($orderBy) )
			$sql .= " ORDER BY {$orderBy} {$dir}";

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}
		return $sql;
	}

	/** @param $readAsIdsArray (bool) true if term is an array of ID or false if term is a string
	 *	@param $prefix (string) remove this prefix at start of term values.
	 *	@param $_REQUEST['term'] (string) filter on post_title or if $readAsIdsArray (array of int) filter on ID.
	 *	@return array|string an array of int if $readAsIdsArray, else a string. */
	private function getTerm($readAsIdsArray, $prefix='', $keySanitize='\intval', $valueSanitize='\sanitize_text_field')
	{
		$len = strlen($prefix);
		$term = '';
		if( isset($_REQUEST['term']) )
		{
			if( $readAsIdsArray )
			{
				if( is_array($_REQUEST['term']) )
				{
					$term = array();
					foreach( $_REQUEST['term'] as $t )
					{
						if( $len > 0 && substr($t, 0, $len) == $prefix )
							$t = substr($t, $len);
						$term[] = \call_user_func($keySanitize, $t);
					}
				}
				else
					$term = array(\call_user_func($keySanitize, $_REQUEST['term']));
			}
			else
				$term = \call_user_func($valueSanitize, trim($_REQUEST['term']));
		}
		return $term;
	}

	/** @param $fields allowed sql fields @return (array) sql filters
	 * @param $_REQUEST['spec'] (array, json base64 encoded /optional) to filter posts, $field => data,
	 * where data can be a string value for = comparison or an array(value, operator). */
	private function specToFilter($fields)
	{
		$where = array();
		if( isset($_REQUEST['spec']) )
		{
			$spec =  json_decode(base64_decode($_REQUEST['spec']), true);
			if( is_array($spec) )
			{
				$where = \LWS\Adminpanel\Pages\Field\Autocomplete::specToFilter($spec, $fields);
			}
		}
		return $where;
	}

	public function composePage($extra)
	{
		global $wpdb;
		$extra['spec'] = array(
			'post_type'=>'page',
			'post_status'=>'publish'
		);
		$extra['prebuild'] = array(
			'value' => 'ID',
			'label' => 'post_title',
			'from' => $wpdb->posts,
			'orderby' => 'post_date DESC'
		);
		$extra['ajax'] = 'lws_adminpanel_get_posts';
		return $extra;
	}

	public function composeUser($extra)
	{
		global $wpdb;
		$extra['prebuild'] = array(
			'value' => 'ID',
			'label' => 'user_login',
			'detail' => "concat(user_login, ' &lt;<i>', user_email, '</i>&gt;')",
			'from' => $wpdb->users
		);
		$extra['ajax'] = 'lws_adminpanel_get_users';
		return $extra;
	}

	public function composeTaxonomy($extra)
	{
		global $wpdb;
		$extra['prebuild'] = array(
			'value' => 't.term_id',
			'label' => 't.name',
			'join' => " INNER JOIN {$wpdb->term_taxonomy} as x ON t.term_id=x.term_id",
			'from' => "{$wpdb->terms} as t"
		);
		$extra['ajax'] = 'lws_adminpanel_get_taxonomy';
		return $extra;
	}

	/** echo a json with the list of published pages.
	 * * accept all=1 (optional) get all fonts, sort alpha,
	 * else sort by popularity and truncate to $limit firsts
	 * * limit={number} result count when query only most popular fonts.
	 * When only popular is returned, the last used font are added in the json.
	 *  */
	public function googleFontList()
	{
		$limit = 20;
		if( isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) && (0 < $_REQUEST['limit']) )
			$limit = intval($_REQUEST['limit']);
		$all = isset($_REQUEST['all']) && $_REQUEST['all'] == 1;
		$cached = new \LWS\Adminpanel\Tools\Cache('googlefontlist-'.($all?'all':'pop').'.json');

		$json = json_decode( $cached->pop() );
		if( empty($json) )
			$json = $this->getFontsFromGoogle($all ? 'alpha' : 'popularity');

		if( empty($json) || !isset($json->items) || is_null($json->items) )
		{
			wp_die();
		}
		else
		{
			$cached->put( json_encode($json) );
			if( !$all )
				$this->filterFonts($json, $limit);
			wp_send_json($json);
		}
	}

	public function standardFontList()
	{
		$json = null;
		$path = LWS_ADMIN_PANEL_PATH . '/js/resources/standard_fonts.json';
		$json = json_decode( file_get_contents($path), true );
		wp_send_json($json);
	}

	private function getFontsFromGoogle($sort)
	{
		$json = null;
		$url = 'https://www.googleapis.com/webfonts/v1/webfonts?key=' . $this->googleApiKey();
		$url .= ('&sort=' . $sort);
		$response = wp_remote_get( esc_url_raw( $url ) );
		$json = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty($json) || !isset($json->items) || is_null($json->items) )
		{
			error_log("Google API return an error. Key used is " . $this->googleApiKey());
			error_log( print_r($json,true) );
			$json = null;
		}

		return $json;
	}

	private function filterFonts(&$json, $limit)
	{
		$lastFonts = array();
		if( ($userId = get_current_user_id()) > 0 )
		{
			$last = get_user_meta($userId, 'lwss-last-used-fonts', true);
			if( !empty($last) )
				$lastFonts = explode('|', $last);
		}

		$lastDic = array(); // get full definition
		for( $index=0 ; $index<count($json->items) ; ++$index )
		{
			$font = $json->items[$index];
			if( false !== array_search($font->family, $lastFonts) )
			{
				$lastDic[$font->family] = $font;
				unset($json->items[$index]);
			}
		}

		$json->lastUsedCount = count($lastDic);
		foreach( $lastFonts as $font ) // re-insert in order
		{
			if( array_key_exists($font, $lastDic) )
				array_unshift($json->items, $lastDic[$font]);
		}

		// ten most popular only, with last used by user at first
		$json->items = array_slice($json->items, 0, $limit, true);
	}

	/** @see LWS\Adminpanel\Pages\FieldGoogleAPIsKey */
	private function googleApiKey()
	{
		$val = get_option('lws-private-google-api-key', '');
		return (!empty($val) ? $val : 'AIzaSyB857on4-LsALSXyTA4GB6kHN_ZdXY_z8c');
	}
}