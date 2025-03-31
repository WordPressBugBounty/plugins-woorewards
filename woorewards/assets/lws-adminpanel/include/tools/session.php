<?php
namespace LWS\Adminpanel\Tools;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Convenience class.
 *	User session
 */
class Session
{
	private $data = false;
	private $dirty = false;
	private $user = false;
	private $sent = false;
	private $created = true;

	const OBF = true;

	static public function set($key, $value)
	{
		$me = $GLOBALS['lwssession_user'];
		$me->maybeLoad();
		$me->dirty = true;
		$me->data[$key] = \maybe_serialize($value);
		$me->maybeSend();
	}

	static public function get($key, $fallback=false)
	{
		$GLOBALS['lwssession_user']->maybeLoad();
		if (isset($GLOBALS['lwssession_user']->data[$key])) {
			return \maybe_unserialize($GLOBALS['lwssession_user']->data[$key]);
		} else {
			return $fallback;
		}
	}

	static public function has($key): bool
	{
		$GLOBALS['lwssession_user']->maybeLoad();
		return isset($GLOBALS['lwssession_user']->data[$key]);
	}

	static public function remove($key)
	{
		$me = $GLOBALS['lwssession_user'];
		$me->maybeLoad();
		if (isset($me->data[$key])) {
			$me->dirty = true;
			unset($me->data[$key]);
		}
		$me->maybeSend();
	}

	static public function install()
	{
		$me = new self();
		$GLOBALS['lwssession_user'] = $me;
		\add_action('shutdown', [$me, 'save'], 200);
		\add_action('wp_logout', [$me, 'clean']);
		$me->maybeRepeat();
	}

	/** Force start a session.
	 * Call this if you are sure you will use it later,
	 * but too late to place the session cookie. */
	static public function start()
	{
		$GLOBALS['lwssession_user']->maybeSend();
	}

	/** repeat the session cookie if any */
	public function maybeRepeat()
	{
		if ($this->hasUser()) {
			$this->maybeSend();
		}
	}

	private function maybeSend()
	{
		if (!$this->sent) {
			$this->send();
		}
	}

	public function send()
	{
		$this->sent = true;
		if (!\headers_sent()) {
			\setcookie(
				$this->getCookieName(),
				self::OBF ? \base64_encode($this->getUser()) : $this->getUser(),
				$this->getExpiry(),
				COOKIEPATH ? COOKIEPATH : '/',
				COOKIE_DOMAIN
			);
		} else {
			error_log('LWS send session: header already sent!');
		}
	}

	public function clean()
	{
		$this->data = false;
		$this->user = false;
		$this->dirty = false;
		$this->sent = false;
		$this->created = false;
		if (!\headers_sent()) {
			\setcookie(
				$this->getCookieName(),
				'',
				0,
				COOKIEPATH ? COOKIEPATH : '/',
				COOKIE_DOMAIN
			);
		}
	}

	public function save()
	{
		if ($this->dirty && false !== $this->data) {
			$this->maybeCreateTable();

			global $wpdb;
			$table = $this->getTableName();
			$wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT INTO {$table} (`id`, `value`, `expiry`) VALUES (%s, %s, %d)
				ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `expiry` = VALUES(`expiry`)",
				$this->getUser(),
				maybe_serialize($this->data),
				$this->getExpiry()
			));

			$this->deleteOld();
		} elseif ($this->hasTable()) {
			$this->deleteOld();
		}
	}

	private function deleteOld()
	{
		global $wpdb;
		$table = $this->getTableName();
		$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE `expiry` < %d", \time()));
	}

	private function hasTable()
	{
		global $wpdb;
		$table = $this->getTableName();
		$query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
		return $wpdb->get_var($query) === $table;
	}

	private function maybeCreateTable()
	{
		if (!$this->hasTable()) {
			global $wpdb;
			$charset = $wpdb->get_charset_collate();
			$table = $this->getTableName();
			$wpdb->query("CREATE TABLE {$table} (
`id` VARCHAR(20) NOT NULL, `expiry` INT(20), `value` TEXT,
PRIMARY KEY id  (id), KEY `expiry` (`expiry`)
) {$charset};");
		}
	}

	private function maybeLoad()
	{
		if (false === $this->data) {
			$this->load();
		}
	}

	private function load()
	{
		$this->data = [];
		$this->dirty = false;
		if ($this->hasTable()) {
		 global $wpdb;
		 $table = $this->getTableName();
		 $data = $wpdb->get_var($wpdb->prepare("SELECT `value` FROM {$table} WHERE `id` = %s", $this->getUser()));
		 $this->data = $data ? (array)\maybe_unserialize($data) : [];
		}
		return $this->data;
	}

	private function getTableName(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'lwssession';
	}

	private function getCookieName(): string
	{
		return 'lwsadm_session_' . COOKIEHASH;
	}

	private function getExpiry(): int
	{
		return \apply_filters('lwsadm_session_expiring', \time() + (DAY_IN_SECONDS * 2));
	}

	private function readUser()
	{
		$user = false;
		$name = $this->getCookieName();
		if (isset($_COOKIE[$name]) && $_COOKIE[$name]) {
			// read from cookie if any
			if (self::OBF) $user = \sanitize_key((string)\base64_decode((string)$_COOKIE[$name]));
			else $user = \sanitize_key((string)$_COOKIE[$name]);
			// valid format
			if (!\in_array(\substr($user, 0, 2), ['l_', 'g_'], true)) {
				$user = false;
			}
		}
		return $user;
	}

	private function hasUser(): bool
	{
		$this->getUser();
		return !$this->created;
	}

	private function getUser(): string
	{
		if (false === $this->user) {
			$this->created = false;
			$this->user = $this->readUser();
			$read = $this->user;
			if (!$this->user) $this->user = $this->createUser();
		}
		return $this->user;
	}

	private function createUser(): string
	{
		if (false === $this->user) {
			$this->created = true;
			$u = \get_current_user_id();
			if ($u) {
				$this->user = ('l_' . $u);
			} else {
				$this->user = ('g_' . \wp_create_nonce('lwsadm_session_guest' . \rand()));
			}
		}
		return $this->user;
	}
}
