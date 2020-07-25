<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// auth.php
// Copyright 2003-2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Authentication related functions

define('PKWK_PASSPHRASE_LIMIT_LENGTH', 512);

/////////////////////////////////////////////////
// Auth type

define('AUTH_TYPE_NONE', 0);
define('AUTH_TYPE_BASIC', 1);
define('AUTH_TYPE_EXTERNAL', 2);
define('AUTH_TYPE_FORM', 3);

define('AUTH_TYPE_EXTERNAL_REMOTE_USER', 4);
define('AUTH_TYPE_EXTERNAL_X_FORWARDED_USER', 5);
define('AUTH_TYPE_SAML', 6);

// Passwd-auth related ----

function pkwk_login(string $pass = '') : bool
{
	global $adminpass;

	if ((!PKWK_READONLY) && (isset($adminpass)) && (pkwk_hash_compute($pass, $adminpass) === $adminpass)) {
		return true;
	} else {
		// Blocking brute force attack
		sleep(2);

		return false;
	}
}

// Compute RFC2307 'userPassword' value, like slappasswd (OpenLDAP)
// $phrase : Pass-phrase
// $scheme : Specify '{scheme}' or '{scheme}salt'
// $prefix : Output with a scheme-prefix or not
// $canonical : Correct or Preserve $scheme prefix
function pkwk_hash_compute(string $phrase = '', string $scheme = '{x-php-md5}', bool $prefix = true, bool $canonical = false)
{
	if ((!is_string($phrase)) || (!is_string($scheme))) {
		return false;
	}

	if (strlen($phrase) > PKWK_PASSPHRASE_LIMIT_LENGTH) {
		die('pkwk_hash_compute(): malicious message length');
	}

	// With a {scheme}salt or not
	$matches = [];

	if (preg_match('/^(\{.+\})(.*)$/', $scheme, $matches)) {
		$scheme = $matches[1];
		$salt = $matches[2];
	} elseif ($scheme != '') {
		// Cleartext
		$scheme = '';

		$salt = '';
	}

	// Compute and add a scheme-prefix
	switch (strtolower($scheme)) {
		// PHP crypt()
		case '{x-php-crypt}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-crypt}') : ($scheme)) : ('')).(($salt != '') ? (crypt($phrase, $salt)) : (crypt($phrase)));

			break;

		// PHP md5()
		case '{x-php-md5}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-md5}') : ($scheme)) : ('')).md5($phrase);

			break;

		// PHP sha1()
		case '{x-php-sha1}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-sha1}') : ($scheme)) : ('')).sha1($phrase);

			break;

		// PHP sha256
		case '{x-php-sha256}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-sha256}') : ($scheme)) : ('')).hash('sha256', $phrase);

			break;

		// PHP sha384
		case '{x-php-sha384}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-sha384}') : ($scheme)) : ('')).hash('sha384', $phrase);

			break;

		// PHP sha512
		case '{x-php-sha512}':
			$hash = (($prefix) ? (($canonical) ? ('{x-php-sha512}') : ($scheme)) : ('')).hash('sha512', $phrase);

			break;

		// LDAP CRYPT
		case '{crypt}':
			$hash = (($prefix) ? (($canonical) ? ('{CRYPT}') : ($scheme)) : ('')).(($salt != '') ? (crypt($phrase, $salt)) : (crypt($phrase)));

			break;

		// LDAP MD5
		case '{md5}':
			$hash = (($prefix) ? (($canonical) ? ('{MD5}') : ($scheme)) : ('')).base64_encode(pkwk_hex2bin(md5($phrase)));

			break;

		// LDAP SMD5
		case '{smd5}':
			// MD5 Key length = 128bits = 16bytes
			$salt = ($salt != '') ? (substr(base64_decode($salt, true), 16)) : (substr(crypt(''), -8));
			$hash = (($prefix) ? (($canonical) ? ('{SMD5}') : ($scheme)) : ('')).base64_encode(pkwk_hex2bin(md5($phrase.$salt)).$salt);

			break;

		// LDAP SHA
		case '{sha}':
			$hash = (($prefix) ? (($canonical) ? ('{SHA}') : ($scheme)) : ('')).base64_encode(pkwk_hex2bin(sha1($phrase)));

			break;

		// LDAP SSHA
		case '{ssha}':
			// SHA-1 Key length = 160bits = 20bytes
			$salt = ($salt != '') ? (substr(base64_decode($salt, true), 20)) : (substr(crypt(''), -8));
			$hash = (($prefix) ? (($canonical) ? ('{SSHA}') : ($scheme)) : ('')).base64_encode(pkwk_hex2bin(sha1($phrase.$salt)).$salt);

			break;

		// LDAP SHA256
		case '{sha256}':
			$hash = (($prefix) ? (($canonical) ? ('{SHA256}') : ($scheme)) : ('')).base64_encode(hash('sha256', $phrase, true));

			break;

		// LDAP SSHA256
		case '{ssha256}':
			// SHA-2 SHA-256 Key length = 256bits = 32bytes
			$salt = ($salt != '') ? (substr(base64_decode($salt, true), 32)) : (substr(crypt(''), -8));
			$hash = (($prefix) ? (($canonical) ? ('{SSHA256}') : ($scheme)) : ('')).base64_encode(hash('sha256', $phrase.$salt, true).$salt);

			break;

		// LDAP SHA384
		case '{sha384}':
			$hash = (($prefix) ? (($canonical) ? ('{SHA384}') : ($scheme)) : ('')).base64_encode(hash('sha384', $phrase, true));

			break;

		// LDAP SSHA384
		case '{ssha384}':
			// SHA-2 SHA-384 Key length = 384bits = 48bytes
			$salt = ($salt != '') ? (substr(base64_decode($salt, true), 48)) : (substr(crypt(''), -8));
			$hash = (($prefix) ? (($canonical) ? ('{SSHA384}') : ($scheme)) : ('')).base64_encode(hash('sha384', $phrase.$salt, true).$salt);

			break;

		// LDAP SHA512
		case '{sha512}':
			$hash = (($prefix) ? (($canonical) ? ('{SHA512}') : ($scheme)) : ('')).base64_encode(hash('sha512', $phrase, true));

			break;

		// LDAP SSHA512
		case '{ssha512}':
			// SHA-2 SHA-512 Key length = 512bits = 64bytes
			$salt = ($salt != '') ? (substr(base64_decode($salt, true), 64)) : (substr(crypt(''), -8));
			$hash = (($prefix) ? (($canonical) ? ('{SSHA512}') : ($scheme)) : ('')).base64_encode(hash('sha512', $phrase.$salt, true).$salt);

			break;

		// LDAP CLEARTEXT and just cleartext
		case '{cleartext}':
		case '':
			$hash = (($prefix) ? (($canonical) ? ('{CLEARTEXT}') : ($scheme)) : ('')).$phrase;

			break;

		// Invalid scheme
		default:
			$hash = false;

			break;
	}

	return $hash;
}

// LDAP related functions

function _pkwk_ldap_escape_callback(array $matches) : string
{
	return sprintf('\\%02x', ord($matches[0]));
}

function pkwk_ldap_escape_filter($value)
{
	if (function_exists('ldap_escape')) {
		return ldap_escape($value, false, LDAP_ESCAPE_FILTER);
	}

	return preg_replace_callback('/[\\\\*()\0]/', '_pkwk_ldap_escape_callback', $value);
}

function pkwk_ldap_escape_dn($value)
{
	if (function_exists('ldap_escape')) {
		return ldap_escape($value, false, LDAP_ESCAPE_DN);
	}

	return preg_replace_callback('/[\\\\,=+<>;"#]/', '_pkwk_ldap_escape_callback', $value);
}

// Basic-auth related ----

// Check edit-permission
function check_editable(string $page, bool $auth_enabled = true, bool $exit_on_fail = true) : bool
{
	global $_title_cannotedit;
	global $_msg_unfreeze;

	if ((edit_auth($page, $auth_enabled, $exit_on_fail)) && (is_editable($page))) {
		// Editable
		return true;
	} else {
		// Not editable
		if ($exit_on_fail === false) {
			// Without exit
			return false;
		} else {
			// With exit
			$title = str_replace('$1', htmlsc(strip_bracket($page)), $_title_cannotedit);
			$body = $title;

			if (is_freeze($page)) {
				$body .= '(<a href="'.get_base_uri().'?cmd=unfreeze&amp;page='.rawurlencode($page).'">'.$_msg_unfreeze.'</a>)';
			}

			$page = str_replace('$1', make_search($page), $_title_cannotedit);
			catbody($title, $page, $body);

			exit;
		}
	}
}

/**
 * Whether the page is readable from current user or not.
 */
function is_page_readable(string $page) : bool
{
	global $read_auth_pages;

	return _is_page_accessible($page, $read_auth_pages);
}

/**
 * Whether the page is writable from current user or not.
 */
function is_page_writable(string $page) : bool
{
	global $edit_auth_pages;

	return _is_page_accessible($page, $edit_auth_pages);
}

/**
 * Get whether a current auth user can access the page.
 *
 * @param $page page name
 * @param $auth_pages pagepattern -> groups map
 *
 * @return true if a current user can access the page
 */
function _is_page_accessible(string $page, array $auth_pages) : bool
{
	global $auth_method_type;
	global $auth_user_groups;
	global $auth_user;

	// Checked by:
	$target_str = '';

	if ($auth_method_type == 'pagename') {
		// Page name
		$target_str = $page;
	} elseif ($auth_method_type == 'contents') {
		// Its contents
		$target_str = implode('', get_source($page));
	}

	$user_list = [];

	foreach ($auth_pages as $key=>$val) {
		if (preg_match($key, $target_str)) {
			$user_list = array_merge($user_list, explode(',', $val));
		}
	}

	if (empty($user_list)) {
		// No limit
		return true;
	}

	if (!$auth_user) {
		// Current user doesen't yet log in.
		return false;
	}

	if (count(array_intersect($auth_user_groups, $user_list)) === 0) {
		return false;
	}

	return true;
}

/**
 * Ensure the page is readable, or show Login UI.
 *
 * @param $page page
 */
function ensure_page_readable(string $page) : bool
{
	global $read_auth;
	global $read_auth_pages;
	global $_title_cannotread;

	if (!$read_auth) {
		return true;
	}

	return basic_auth($page, true, true, $read_auth_pages, $_title_cannotread);
}

/**
 * Ensure the page is writable, or show Login UI.
 *
 * @param $page page
 */
function ensure_page_writable(string $page) : bool
{
	global $edit_auth;
	global $edit_auth_pages;
	global $_title_cannotedit;

	if (!$edit_auth) {
		return true;
	}

	return basic_auth($page, true, true, $edit_auth_pages, $_title_cannotedit);
}

/**
 * Check a page is readable or not, show Auth UI in some cases.
 *
 * @param $page page name
 * @param $auth_enabled true if auth is available (Normally true)
 * @param $exit_on_fail  (Normally true)
 *
 * @return true if the page is readable
 */
function check_readable(string $page, bool $auth_enabled = true, bool $exit_on_fail = true) : bool
{
	return read_auth($page, $auth_enabled, $exit_on_fail);
}

function edit_auth(string $page, bool $auth_enabled = true, bool $exit_on_fail = true) : bool
{
	global $edit_auth;
	global $edit_auth_pages;
	global $_title_cannotedit;

	return ($edit_auth) ? (basic_auth($page, $auth_enabled, $exit_on_fail, $edit_auth_pages, $_title_cannotedit)) : (true);
}

function read_auth(string $page, bool $auth_enabled = true, bool $exit_on_fail = true) : bool
{
	global $read_auth;
	global $read_auth_pages;
	global $_title_cannotread;

	return ($read_auth) ? (basic_auth($page, $auth_enabled, $exit_on_fail, $read_auth_pages, $_title_cannotread)) : (true);
}

/**
 * Authentication.
 *
 * @param $page page name
 * @param $auth_enabled true if auth is available
 * @param $exit_on_fail Show forbidden message and stop all following processes
 * @param $auth_pages accessible users -> pages pattern map
 * @param $title_cannot forbidden message
 */
function basic_auth(string $page, bool $auth_enabled, bool $exit_on_fail, array $auth_pages, string $title_cannot) : bool
{
	global $auth_users;
	global $_msg_auth;
	global $auth_user;
	global $auth_type;
	global $g_query_string;

	$is_accessible = _is_page_accessible($page, $auth_pages);

	if ($is_accessible) {
		return true;
	} else {
		// Auth failed
		pkwk_common_headers();

		if (($auth_enabled) && (!$auth_user)) {
			if (AUTH_TYPE_BASIC === $auth_type) {
				header('WWW-Authenticate: Basic realm="'.$_msg_auth.'"');
				http_response_code(401);
			} elseif (AUTH_TYPE_FORM === $auth_type) {
				if ($g_query_string === null) {
					$url_after_login = get_base_uri();
				} else {
					$url_after_login = get_base_uri().'?'.$g_query_string;
				}

				$loginurl = get_base_uri().'?plugin=loginform&page='.rawurlencode($page).'&url_after_login='.rawurlencode($url_after_login);
				http_response_code(302);
				header('Location: '.$loginurl);
			} elseif ((AUTH_TYPE_EXTERNAL === $auth_type) || (AUTH_TYPE_SAML === $auth_type)) {
				if ($g_query_string === null) {
					$url_after_login = get_base_uri(PKWK_URI_ABSOLUTE);
				} else {
					$url_after_login = get_base_uri(PKWK_URI_ABSOLUTE).'?'.$g_query_string;
				}

				$loginurl = get_auth_external_login_url($page, $url_after_login);
				http_response_code(302);
				header('Location: '.$loginurl);
			}
		}

		if ($exit_on_fail) {
			$title = str_replace('$1', htmlsc(strip_bracket($page)), $title_cannot);
			$body = $title;
			$page = str_replace('$1', make_search($page), $title_cannot);
			catbody($title, $page, $body);

			exit;
		}

		return false;
	}
}

/**
 * Send 401 if client send a invalid credentials.
 *
 * @return true if valid, false if invalid credentials
 */
function ensure_valid_auth_user() : bool
{
	global $auth_type;
	global $auth_users;
	global $_msg_auth;
	global $auth_user;
	global $auth_groups;
	global $auth_user_groups;
	global $auth_user_fullname;
	global $ldap_user_account;
	global $read_auth;
	global $edit_auth;

	if (($read_auth) || ($edit_auth)) {
		switch ($auth_type) {
			case AUTH_TYPE_BASIC:
			case AUTH_TYPE_FORM:
			case AUTH_TYPE_EXTERNAL:
			case AUTH_TYPE_EXTERNAL_REMOTE_USER:
			case AUTH_TYPE_EXTERNAL_X_FORWARDED_USER:
			case AUTH_TYPE_SAML:
				break;

			default:
				// $auth_type is not valid, Set form auth as default
				$auth_type = AUTH_TYPE_FORM;

				break;
		}
	}

	$auth_dynamic_groups = null;

	switch ($auth_type) {
		case AUTH_TYPE_BASIC:
			if (isset($_SERVER['PHP_AUTH_USER'])) {
				$user = $_SERVER['PHP_AUTH_USER'];

				if (in_array($user, array_keys($auth_users), true)) {
					if (pkwk_hash_compute($_SERVER['PHP_AUTH_PW'], $auth_users[$user]) === $auth_users[$user]) {
						$auth_user = $user;
						$auth_user_fullname = $auth_user;
						$auth_user_groups = get_groups_from_username($user);

						return true;
					}
				}

				header('WWW-Authenticate: Basic realm="'.$_msg_auth.'"');
				http_response_code(401);
			}

			$auth_user = '';
			$auth_user_groups = [];

			// no auth input
			return true;

		case AUTH_TYPE_FORM:
		case AUTH_TYPE_EXTERNAL:
		case AUTH_TYPE_SAML:
			session_start();
			$user = '';
			$fullname = '';
			$dynamic_groups = [];

			if (isset($_SESSION['authenticated_user'])) {
				$user = $_SESSION['authenticated_user'];

				if ((isset($_SESSION['authenticated_user_fullname'])) && (isset($_SESSION['dynamic_member_groups']))) {
					$fullname = $_SESSION['authenticated_user_fullname'];
					$dynamic_groups = $_SESSION['dynamic_member_groups'];
				} else {
					$fullname = $user;

					if ((($auth_type === AUTH_TYPE_EXTERNAL) || ($auth_type === AUTH_TYPE_SAML)) && ($ldap_user_account)) {
						$ldap_user_info = ldap_get_simple_user_info($user);

						if ($ldap_user_info) {
							$fullname = $ldap_user_info['fullname'];
							$dynamic_groups = $ldap_user_info['dynamic_member_groups'];
						}
					}

					$_SESSION['authenticated_user_fullname'] = $fullname;
					$_SESSION['dynamic_member_groups'] = $dynamic_groups;
				}
			}

			$auth_user = $user;
			$auth_user_fullname = $fullname;
			$auth_dynamic_groups = $dynamic_groups;

			break;

		case AUTH_TYPE_EXTERNAL_REMOTE_USER:
			$auth_user = $_SERVER['REMOTE_USER'];
			$auth_user_fullname = $auth_user;

			break;

		case AUTH_TYPE_EXTERNAL_X_FORWARDED_USER:
			$auth_user = $_SERVER['HTTP_X_FORWARDED_USER'];
			$auth_user_fullname = $auth_user;

			break;

		// AUTH_TYPE_NONE
		default:
			$auth_user = '';
			$auth_user_fullname = '';

			break;
	}

	$auth_user_groups = get_groups_from_username($auth_user);

	if (($auth_dynamic_groups) && (is_array($auth_dynamic_groups))) {
		$auth_user_groups = array_values(array_merge($auth_user_groups, $auth_dynamic_groups));
	}

	// is not basic auth
	return true;
}

/**
 * Return group name array whose group contains the user.
 *
 * Result array contains reserved 'valid-user' group for all authenticated user
 *
 * @global array $auth_groups
 *
 * @param string $user
 *
 * @return array
 */
function get_groups_from_username(string $user) : array
{
	global $auth_groups;

	if ($user !== '') {
		$groups = [];

		foreach ($auth_groups as $group=>$users) {
			$sp = explode(',', $users);

			if (in_array($user, $sp, true)) {
				$groups[] = $group;
			}
		}

		// Implicit group that has same name as user itself
		$groups[] = $user;

		// 'valid-user' group for
		$valid_user = 'valid-user';

		if (!in_array($valid_user, $groups, true)) {
			$groups[] = $valid_user;
		}

		return $groups;
	}

	return [];
}

/**
 * Get authenticated user name.
 *
 * @global type $auth_user
 *
 * @return type
 */
function get_auth_user() : string
{
	global $auth_user;

	return $auth_user;
}

/**
 * Sign in with username and password.
 *
 * @param string username
 * @param string password
 *
 * @return true is sign in is OK
 */
function form_auth(string $username, string $password)
{
	global $ldap_user_account;
	global $auth_users;

	$user = $username;

	if ($ldap_user_account) {
		// LDAP account
		return ldap_auth($username, $password);
	} else {
		// Defined users in pukiwiki.ini.php
		if (in_array($user, array_keys($auth_users), true)) {
			if (pkwk_hash_compute($password, $auth_users[$user]) === $auth_users[$user]) {
				session_start();

				// require: PHP5.1+
				session_regenerate_id(true);

				$_SESSION['authenticated_user'] = $user;
				$_SESSION['authenticated_user_fullname'] = $user;

				return true;
			}
		}
	}

	return false;
}

function ldap_auth(string $username, string $password) : bool
{
	global $ldap_server;
	global $ldap_base_dn;
	global $ldap_bind_dn;
	global $ldap_bind_password;

	$ldapconn = ldap_connect($ldap_server);

	if ($ldapconn) {
		ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

		if (preg_match('#\$login\b#', $ldap_bind_dn)) {
			// Bind by user credential
			$username_esc = pkwk_ldap_escape_dn($username);
			$bind_dn_user = preg_replace('#\$login\b#', $username_esc, $ldap_bind_dn);
			$ldap_bind_user = ldap_bind($ldapconn, $bind_dn_user, $password);

			if ($ldap_bind_user) {
				$user_info = get_ldap_user_info($ldapconn, $username, $ldap_base_dn);

				if ($user_info) {
					$ldap_groups = get_ldap_groups_with_user($ldapconn, $username, $user_info['is_ad']);

					// require: PHP5.1+
					session_regenerate_id(true);

					$_SESSION['authenticated_user'] = $user_info['uid'];
					$_SESSION['authenticated_user_fullname'] = $user_info['fullname'];
					$_SESSION['dynamic_member_groups'] = $ldap_groups;

					return true;
				}
			}
		} else {
			// Bind by bind dn
			$ldap_bind = ldap_bind($ldapconn, $ldap_bind_dn, $ldap_bind_password);

			if ($ldap_bind) {
				$user_info = get_ldap_user_info($ldapconn, $username, $ldap_base_dn);

				if ($user_info) {
					$ldap_bind_user2 = ldap_bind($ldapconn, $user_info['dn'], $password);

					if ($ldap_bind_user2) {
						$ldap_groups = get_ldap_groups_with_user($ldapconn, $username, $user_info['is_ad']);

						// require: PHP5.1+
						session_regenerate_id(true);

						$_SESSION['authenticated_user'] = $user_info['uid'];
						$_SESSION['authenticated_user_fullname'] = $user_info['fullname'];
						$_SESSION['dynamic_member_groups'] = $ldap_groups;

						return true;
					}
				}
			}
		}
	}

	return false;
}

// Get LDAP user info via bind DN
function ldap_get_simple_user_info(string $username)
{
	global $ldap_server;
	global $ldap_base_dn;
	global $ldap_bind_dn;
	global $ldap_bind_password;

	$ldapconn = ldap_connect($ldap_server);

	if ($ldapconn) {
		ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
		// Bind by bind dn
		$ldap_bind = ldap_bind($ldapconn, $ldap_bind_dn, $ldap_bind_password);

		if ($ldap_bind) {
			$user_info = get_ldap_user_info($ldapconn, $username, $ldap_base_dn);

			if ($user_info) {
				$ldap_groups = get_ldap_groups_with_user($ldapconn, $username, $user_info['is_ad']);
				$user_info['dynamic_member_groups'] = $ldap_groups;

				return $user_info;
			}
		}
	}

	return false;
}

/**
 * Search user and get 'dn', 'uid', 'fullname' and 'mail'.
 *
 * @param type $ldapconn
 * @param type $username
 * @param type $base_dn
 *
 * @return bool
 */
function get_ldap_user_info($ldapconn, string $username, string $base_dn)
{
	$username_esc = pkwk_ldap_escape_filter($username);
	$filter = '(|(uid='.$username_esc.')(sAMAccountName='.$username_esc.'))';
	$result1 = ldap_search($ldapconn, $base_dn, $filter, ['dn', 'uid', 'cn', 'samaccountname', 'displayname', 'mail']);
	$entries = ldap_get_entries($ldapconn, $result1);

	if (!isset($entries[0])) {
		return false;
	}

	$info = $entries[0];

	if (isset($info['dn'])) {
		$user_dn = $info['dn'];
		$cano_username = $username;
		$is_active_directory = false;

		if (isset($info['uid'][0])) {
			$cano_username = $info['uid'][0];
		} elseif (isset($info['samaccountname'][0])) {
			$cano_username = $info['samaccountname'][0];
			$is_active_directory = true;
		}

		$cano_fullname = $username;

		if (isset($info['displayname'][0])) {
			$cano_fullname = $info['displayname'][0];
		} elseif (isset($info['cn'][0])) {
			$cano_fullname = $info['cn'][0];
		}

		return
		[
			'dn'=>$user_dn,
			'uid'=>$cano_username,
			'fullname'=>$cano_fullname,
			'mail'=>$info['mail'][0],
			'is_ad'=>$is_active_directory,
		];
	}

	return false;
}

/**
 * Redirect after login. Need to assing location or page.
 *
 * @param type $location
 * @param type $page
 */
function form_auth_redirect(string $location, string $page) : void
{
	http_response_code(302);

	if ($location) {
		header('Location: '.$location);
	} else {
		$url = get_page_uri($page, PKWK_URI_ROOT);
		header('Location: '.$url);
	}
}

/**
 * Get External Auth log-in URL.
 */
function get_auth_external_login_url(string $page, string $url_after_login) : string
{
	global $auth_external_login_url_base;

	$sep = '&';

	if (strpos($auth_external_login_url_base, '?') === false) {
		$sep = '?';
	}

	$url = $auth_external_login_url_base.$sep.'page='.rawurlencode($page).'&url_after_login='.rawurlencode($url_after_login);

	return $url;
}

function get_auth_user_prefix() : string
{
	global $ldap_user_account;
	global $auth_type;
	global $auth_provider_user_prefix_default;
	global $auth_provider_user_prefix_ldap;
	global $auth_provider_user_prefix_external;
	global $auth_provider_user_prefix_saml;

	$user_prefix = '';

	switch ($auth_type) {
		case AUTH_TYPE_BASIC:
			$user_prefix = $auth_provider_user_prefix_default;

			break;

		case AUTH_TYPE_EXTERNAL:
		case AUTH_TYPE_EXTERNAL_REMOTE_USER:
		case AUTH_TYPE_EXTERNAL_X_FORWARDED_USER:
			$user_prefix = $auth_provider_user_prefix_external;

			break;

		case AUTH_TYPE_SAML:
			$user_prefix = $auth_provider_user_prefix_saml;

			break;

		case AUTH_TYPE_FORM:
			if ($ldap_user_account) {
				$user_prefix = $auth_provider_user_prefix_ldap;
			} else {
				$user_prefix = $auth_provider_user_prefix_default;
			}

			break;

		default:
			break;
	}

	return $user_prefix;
}

function get_ldap_related_groups() : array
{
	global $read_auth_pages;
	global $edit_auth_pages;
	global $auth_provider_user_prefix_ldap;

	$ldap_groups = [];

	foreach ($read_auth_pages as $pattern=>$groups) {
		$sp_groups = explode(',', $groups);

		foreach ($sp_groups as $group) {
			if (strpos($group, $auth_provider_user_prefix_ldap) === 0) {
				$ldap_groups[] = $group;
			}
		}
	}

	foreach ($edit_auth_pages as $pattern=>$groups) {
		$sp_groups = explode(',', $groups);

		foreach ($sp_groups as $group) {
			if (strpos($group, $auth_provider_user_prefix_ldap) === 0) {
				$ldap_groups[] = $group;
			}
		}
	}

	$ldap_groups_unique = array_values(array_unique($ldap_groups));

	return $ldap_groups_unique;
}

/**
 * Get LDAP groups user belongs to.
 *
 * @param resource $ldapconn
 * @param string $user
 * @param bool $is_ad
 *
 * @return array
 */
function get_ldap_groups_with_user($ldapconn, string $user, bool $is_ad) : array
{
	global $auth_provider_user_prefix_ldap;
	global $ldap_base_dn;

	$related_groups = get_ldap_related_groups();

	if (count($related_groups) == 0) {
		return [];
	}

	$gfilter = '(|';

	foreach ($related_groups as $group_full) {
		$g = substr($group_full, strlen($auth_provider_user_prefix_ldap));
		$gfilter .= sprintf('(cn=%s)', pkwk_ldap_escape_filter($g));

		if ($is_ad) {
			$gfilter .= sprintf('(sAMAccountName=%s)', pkwk_ldap_escape_filter($g));
		}
	}

	$gfilter .= ')';
	$result_g = ldap_search($ldapconn, $ldap_base_dn, $gfilter, ['dn', 'uid', 'cn', 'samaccountname']);
	$entries = ldap_get_entries($ldapconn, $result_g);

	if (!isset($entries[0])) {
		return false;
	}

	if (!$entries) {
		return [];
	}

	$entry_count = $entries['count'];
	$group_list = [];

	for ($i = 0; $i < $entry_count; $i++) {
		$group_name = $entries[$i]['cn'][0];

		if ($is_ad) {
			$group_name = $entries[$i]['samaccountname'][0];
		}

		$group_list[] =
		[
			'name'=>$group_name,
			'dn'=>$entries[$i]['dn'],
		];
	}

	$groups_member = [];
	$groups_nonmember = [];

	foreach ($group_list as $gp) {
		$fmt = '(&(uid=%s)(memberOf=%s))';

		if ($is_ad) {
			// LDAP_MATCHING_RULE_IN_CHAIN: Active Directory specific rule
			$fmt = '(&(sAMAccountName=%s)(memberOf:1.2.840.113556.1.4.1941:=%s))';
		}

		$user_gfilter = sprintf($fmt, pkwk_ldap_escape_filter($user), pkwk_ldap_escape_filter($gp['dn']));
		$result_e = ldap_search($ldapconn, $ldap_base_dn, $user_gfilter, ['dn'], 0, 1);
		$user_e = ldap_get_entries($ldapconn, $result_e);

		if ((isset($user_e['count'])) && ($user_e['count'] > 0)) {
			$groups_member[] = $gp['name'];
		} else {
			$groups_nonmember[] = $gp['name'];
		}
	}

	$groups_full = [];

	foreach ($groups_member as $g) {
		$groups_full[] = $auth_provider_user_prefix_ldap.$g;
	}

	return $groups_full;
}
