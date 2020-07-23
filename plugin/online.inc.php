<?php
declare(strict_types=1);

// $Id: online.inc.php,v 1.12 2007/02/10 06:21:53 henoheno Exp $
// Copyright (C)
//   2002-2005, 2007 PukiWiki Developers Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Online plugin -- Just show the number 'users-on-line'

// Count users in N seconds
define('PLUGIN_ONLINE_TIMEOUT', 60 * 5);

// ----

// List of 'IP-address|last-access-time(seconds)'
define('PLUGIN_ONLINE_USER_LIST', COUNTER_DIR.'user.dat');

// Regex of 'IP-address|last-access-time(seconds)'
define('PLUGIN_ONLINE_LIST_REGEX', '/^([^\|]+)\|([0-9]+)$/');

function plugin_online_convert() : string
{
	return plugin_online_itself(0);
}

function plugin_online_inline() : string
{
	return plugin_online_itself(1);
}

function plugin_online_itself(int $type = 0) : string
{
	static $count;
	static $result;
	static $base;

	if (!isset($count)) {
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$host = &$_SERVER['REMOTE_ADDR'];
		} else {
			$host = '';
		}

		// Try read
		if (plugin_online_check_online($count, $host)) {
			$result = true;
		} else {
			// Write
			$result = plugin_online_sweep_records($host);
		}
	}

	if ($result) {
		// Integer
		return (string) ($count);
	} else {
		if (!isset($base)) {
			$base = basename(PLUGIN_ONLINE_USER_LIST);
		}

		$error = '"COUNTER_DIR/'.$base.'" not writable';

		if ($type == 0) {
			$error = '#online: '.$error.'<br />'."\n";
		} else {
			$error = '&online: '.$error.';';
		}

		// String
		return $error;
	}
}

// Check I am already online (recorded and not time-out)
// & $count == Number of online users
function plugin_online_check_online(int &$count, string $host = '') : bool
{
	if (!pkwk_touch_file(PLUGIN_ONLINE_USER_LIST)) {
		return false;
	}

	// Open
	$fp = @fopen(PLUGIN_ONLINE_USER_LIST, 'r');

	if ($fp == false) {
		return false;
	}

	set_file_buffer($fp, 0);

	// Init
	$count = 0;
	$found = false;
	$matches = [];

	flock($fp, LOCK_SH);

	// Read
	while (!feof($fp)) {
		$line = fgets($fp, 512);

		if ($line === false) {
			continue;
		}

		// Ignore invalid-or-outdated lines
		if ((!preg_match(PLUGIN_ONLINE_LIST_REGEX, $line, $matches)) || (($matches[2] + PLUGIN_ONLINE_TIMEOUT) <= UTIME) || ($matches[2] > UTIME)) {
			continue;
		}

		$count++;

		if ((!$found) && ($matches[1] == $host)) {
			$found = true;
		}
	}

	flock($fp, LOCK_UN);

	if (!fclose($fp)) {
		return false;
	}

	if ((!$found) && ($host != '')) {
		$count++;
	} // About you

	return $found;
}

// Cleanup outdated records, Add/Replace new record, Return the number of 'users in N seconds'
// NOTE: Call this when plugin_online_check_online() returnes false
function plugin_online_sweep_records(string $host = '')
{
	// Open
	$fp = @fopen(PLUGIN_ONLINE_USER_LIST, 'r+');

	if ($fp == false) {
		return false;
	}

	set_file_buffer($fp, 0);

	flock($fp, LOCK_EX);

	// Read to check
	$lines = @file(PLUGIN_ONLINE_USER_LIST);

	if ($lines === false) {
		$lines = [];
	}

	// Need modify?
	$count = count($lines);
	$line_count = $count;
	$matches = [];
	$dirty = false;

	for ($i = 0; $i < $line_count; $i++) {
		if ((!preg_match(PLUGIN_ONLINE_LIST_REGEX, $lines[$i], $matches)) || (($matches[2] + PLUGIN_ONLINE_TIMEOUT) <= UTIME) || ($matches[2] > UTIME) || ($matches[1] == $host)) {
			// Invalid or outdated or invalid date
			unset($lines[$i]);

			$count--;
			$dirty = true;
		}
	}

	if ($host != '') {
		// Add new, at the top of the record
		array_unshift($lines, strtr($host, "\n", '').'|'.UTIME."\n");
		$count++;
		$dirty = true;
	}

	if ($dirty) {
		// Write
		if (!ftruncate($fp, 0)) {
			return false;
		}

		rewind($fp);
		fwrite($fp, implode('', $lines));
	}

	flock($fp, LOCK_UN);

	if (!fclose($fp)) {
		return false;
	}

	// Number of lines == Number of users online
	return $count;
}
