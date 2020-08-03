<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// mail.php
// Copyright
//   2003-2017 PukiWiki Development Team
//   2003      Originally written by upk
// License: GPL v2 or (at your option) any later version
//
// E-mail related functions

// Send a mail to the administrator
function pkwk_mail_notify(string $subject, string $message, array $footer = []) : bool
{
	global $smtp_server;
	global $smtp_auth;
	global $notify_to;
	global $notify_from;
	global $notify_header;
	static $_to;
	static $_headers;
	static $_after_pop;

	// Init and lock
	if (!isset($_to)) {
		if ((!defined('PKWK_OPTIMISE')) || (!PKWK_OPTIMISE)) {
			// Validation check
			$func = 'pkwk_mail_notify(): ';
			$mail_regex = '/[^@]+@[^@]{1,}\.[^@]{2,}/';

			if (!preg_match($mail_regex, $notify_to)) {
				die($func.'Invalid $notify_to');
			}

			if (!preg_match($mail_regex, $notify_from)) {
				die($func.'Invalid $notify_from');
			}

			if ($notify_header != '') {
				$header_regex = "/\\A(?:\r\n|\r|\n)|\r\n\r\n/";

				if (preg_match($header_regex, $notify_header)) {
					die($func.'Invalid $notify_header');
				}

				if (preg_match('/^From:/im', $notify_header)) {
					die($func.'Redundant \'From:\' in $notify_header');
				}
			}
		}

		$_to = $notify_to;
		$_headers = 'X-Mailer: PukiWiki/'.S_VERSION.' PHP/'.PHP_VERSION."\r\n".'From: '.$notify_from;

		// Additional header(s) by admin
		if ($notify_header != '') {
			$_headers .= "\r\n".$notify_header;
		}

		$_after_pop = $smtp_auth;
	}

	if (($subject == '') || (($message == '') && (empty($footer)))) {
		return false;
	}

	// Subject:
	if (isset($footer['PAGE'])) {
		$subject = str_replace('$page', $footer['PAGE'], $subject);
	}

	// Footer
	if (isset($footer['REMOTE_ADDR'])) {
		$footer['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
	}

	if (isset($footer['USER_AGENT'])) {
		$footer['USER_AGENT'] = '('.UA_PROFILE.') '.UA_NAME.'/'.UA_VERS;
	}

	if (!empty($footer)) {
		$_footer = '';

		if ($message != '') {
			$_footer = "\n".str_repeat('-', 30)."\n";
		}

		foreach ($footer as $key=>$value) {
			$_footer .= $key.': '.$value."\n";
		}

		$message .= $_footer;
	}

	// Wait POP/APOP auth completion
	if ($_after_pop) {
		$result = pop_before_smtp();

		if ($result !== true) {
			die($result);
		}
	}

	ini_set('SMTP', $smtp_server);
	mb_language(LANG);

	if ($_headers == '') {
		return mb_send_mail($_to, $subject, $message);
	} else {
		return mb_send_mail($_to, $subject, $message, $_headers);
	}
}

// APOP/POP Before SMTP
function pop_before_smtp(string $pop_userid = '', string $pop_passwd = '', string $pop_server = 'localhost', int $pop_port = 110)
{
	// Always try APOP, by default
	$pop_auth_use_apop = true;

	// Always try POP for APOP-disabled server
	$must_use_apop = false;

	if (isset($GLOBALS['pop_auth_use_apop'])) {
		// Force APOP only, or POP only
		$must_use_apop = $GLOBALS['pop_auth_use_apop'];
		$pop_auth_use_apop = $GLOBALS['pop_auth_use_apop'];
	}

	// Compat: GLOBALS > function arguments
	if ((isset($GLOBALS['pop_userid'])) && ($GLOBALS['pop_userid'] !== '')) {
		$pop_userid = $GLOBALS['pop_userid'];
	}

	if ((isset($GLOBALS['pop_passwd'])) && ($GLOBALS['pop_passwd'] !== '')) {
		$pop_passwd = $GLOBALS['pop_passwd'];
	}

	if ((isset($GLOBALS['pop_server'])) && ($GLOBALS['pop_server'] !== '')) {
		$pop_server = $GLOBALS['pop_server'];
	}

	if ((isset($GLOBALS['pop_port'])) && ($GLOBALS['pop_port'] !== '')) {
		$pop_port = $GLOBALS['pop_port'];
	}

	// Check
	$die = '';
	$die .= ($pop_userid == '') ? ('pop_before_smtp(): $pop_userid seems blank'."\n") : ('');
	$die .= ($pop_server == '') ? ('pop_before_smtp(): $pop_server seems blank'."\n") : ('');
	$die .= ($pop_port == '') ? ('pop_before_smtp(): $pop_port seems blank'."\n") : ('');

	if ($die) {
		return $die;
	}

	// Connect
	$errno = 0;
	$errstr = '';
	$fp = @fsockopen($pop_server, $pop_port, $errno, $errstr, 30);

	if (!$fp) {
		return 'pop_before_smtp(): '.$errstr.' ('.$errno.')';
	}

	// Greeting message from server, may include <challenge-string> of APOP

	// 512byte max
	$message = fgets($fp, 1024);

	if (!preg_match('/^\+OK/', $message)) {
		fclose($fp);

		return 'pop_before_smtp(): Greeting message seems invalid';
	}

	$challenge = [];

	if (($pop_auth_use_apop) && ((preg_match('/<.*>/', $message, $challenge)) || ($must_use_apop))) {
		// APOP auth
		$method = 'APOP';

		if (!isset($challenge[0])) {
			// Someting worthless but variable
			$response = md5(time());
		} else {
			$response = md5($challenge[0].$pop_passwd);
		}

		fwrite($fp, 'APOP '.$pop_userid.' '.$response."\r\n");
	} else {
		// POP auth
		$method = 'POP';

		fwrite($fp, 'USER '.$pop_userid."\r\n");

		// 512byte max
		$message = fgets($fp, 1024);

		if (!preg_match('/^\+OK/', $message)) {
			fclose($fp);

			return 'pop_before_smtp(): USER seems invalid';
		}

		fwrite($fp, 'PASS '.$pop_passwd."\r\n");
	}

	// 512byte max, auth result
	$result = fgets($fp, 1024);

	$auth = preg_match('/^\+OK/', $result);

	if ($auth) {
		// STAT, trigger SMTP relay!
		fwrite($fp, 'STAT'."\r\n");

		// 512byte max
		$message = fgets($fp, 1024);
	}

	// Disconnect anyway
	fwrite($fp, 'QUIT'."\r\n");

	// 512byte max, last '+OK'
	$message = fgets($fp, 1024);

	fclose($fp);

	if (!$auth) {
		return 'pop_before_smtp(): '.$method.' authentication failed';
	} else {
		// Success
		return true;
	}
}
