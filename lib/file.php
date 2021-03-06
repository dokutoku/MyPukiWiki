<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// file.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// File related functions

// RecentChanges
define('PKWK_MAXSHOW_ALLOWANCE', 10);
define('PKWK_MAXSHOW_CACHE', 'recent.dat');

// AutoLink
define('PKWK_AUTOLINK_REGEX_CACHE', 'autolink.dat');

// AutoAlias
define('PKWK_AUTOALIAS_REGEX_CACHE', 'autoalias.dat');

/**
 * Get source(wiki text) data of the page.
 *
 * @param $page page name
 * @param $lock lock
 * @param $join true: return string, false: return array of string
 * @param $raw true: return file content as-is
 *
 * @return false if error occurerd
 */
function get_source(string $page = null, bool $lock = true, bool $join = false, bool $raw = false)
{
	// File is not found
	//$result = null;

	$result = ($join) ? ('') : ([]);
	// Compat for "implode('', get_source($file))",
	// -- this is slower than "get_source($file, true, true)"
	// Compat for foreach(get_source($file) as $line) {} not to warns

	$path = get_filename($page);

	if (file_exists($path)) {
		if ($lock) {
			$fp = @fopen($path, 'r');

			if ($fp === false) {
				return false;
			}

			flock($fp, LOCK_SH);
		}

		if ($join) {
			// Returns a value
			$size = filesize($path);

			if ($size === false) {
				$result = false;
			} elseif ($size == 0) {
				$result = '';
			} else {
				$result = fread($fp, $size);

				if ($result !== false) {
					if ($raw) {
						return $result;
					}

					// Removing Carriage-Return
					$result = str_replace("\r", '', $result);
				}
			}
		} else {
			// Returns an array
			$result = file($path);

			if ($result !== false) {
				// Removing Carriage-Return
				$result = str_replace("\r", '', $result);
			}
		}

		if ($lock) {
			flock($fp, LOCK_UN);
			@fclose($fp);
		}
	}

	return $result;
}

// Get last-modified filetime of the page
function get_filetime(string $page) : int
{
	return (is_page($page)) ? (filemtime(get_filename($page)) - LOCALZONE) : (0);
}

/**
 * Get last-modified filemtime (plain value) of the page.
 *
 * @param $page
 */
function get_page_date_atom(string $page) : string
{
	if (is_page($page)) {
		return get_date_atom(filemtime(get_filename($page)));
	}

	return '';
}

// Get physical file name of the page
function get_filename(?string $page) : string
{
	return DATA_DIR.encode($page).'.txt';
}

// Put a data(wiki text) into a physical file(diff, backup, text)
function page_write(string $page, string $postdata, bool $notimestamp = false) : void
{
	global $autoalias;
	global $aliaspage;

	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	$postdata = make_str_rules($postdata);
	$timestamp_to_keep = null;

	if ($notimestamp) {
		$timestamp_to_keep = get_filetime($page);
	}

	$text_without_author = remove_author_info($postdata);
	$postdata = add_author_info($text_without_author, $timestamp_to_keep);
	$is_delete = empty($text_without_author);

	// Do nothing when it has no changes
	$oldpostdata = (is_page($page)) ? (implode('', get_source($page))) : ('');
	$oldtext_without_author = remove_author_info($oldpostdata);

	if ((!$is_delete) && ($text_without_author === $oldtext_without_author)) {
		// Do nothing on updating with unchanged content
		return;
	}

	// Create and write diff
	$diffdata = do_diff($oldpostdata, $postdata);
	file_write(DIFF_DIR, $page, $diffdata);

	// Create backup
	// Is $postdata null?
	make_backup($page, $is_delete, $postdata);

	// Create wiki text
	file_write(DATA_DIR, $page, $postdata, $notimestamp, $is_delete);

	links_update($page);

	// Update autoalias.dat (AutoAliasName)
	if (($autoalias) && ($page === $aliaspage)) {
		update_autoalias_cache_file();
	}
}

// Modify original text with user-defined / system-defined rules
function make_str_rules(string $source) : string
{
	global $str_rules;
	global $fixed_heading_anchor;

	$lines = explode("\n", $source);
	$count = count($lines);

	$modify = true;
	$multiline = 0;
	$matches = [];

	for ($i = 0; $i < $count; $i++) {
		// Modify directly
		$line = &$lines[$i];

		// Ignore null string and preformatted texts
		if (($line == '') || ($line[0] == ' ') || ($line[0] == "\t")) {
			continue;
		}

		// Modify this line?
		if ($modify) {
			if ((!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) && ($multiline == 0) && (preg_match('/#[^{]*(\{\{+)\s*$/', $line, $matches))) {
				// Multiline convert plugin start
				$modify = false;

				// Set specific number
				$multiline = strlen($matches[1]);
			}
		} else {
			if ((!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) && ($multiline != 0) && (preg_match('/^\}{'.$multiline.'}\s*$/', $line))) {
				// Multiline convert plugin end
				$modify = true;
				$multiline = 0;
			}
		}

		if ($modify === false) {
			continue;
		}

		// Replace with $str_rules
		foreach ($str_rules as $pattern=>$replacement) {
			$line = preg_replace('/'.$pattern.'/', $replacement, $line);
		}

		// Adding fixed anchor into headings
		if (($fixed_heading_anchor) && (preg_match('/^(\*{1,3}.*?)(?:\[#([A-Za-z][\w-]*)\]\s*)?$/', $line, $matches)) && ((!isset($matches[2])) || ($matches[2] == ''))) {
			// Generate unique id
			$anchor = generate_fixed_heading_anchor_id($matches[1]);
			$line = rtrim($matches[1]).' [#'.$anchor.']';
		}
	}

	// Multiline part has no stopper
	if ((!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) && ($modify === false) && ($multiline != 0)) {
		$lines[] = str_repeat('}', $multiline);
	}

	return implode("\n", $lines);
}

/**
 * Add author plugin text for wiki text body.
 *
 * @param string $wikitext
 * @param int $timestamp_to_keep Set null when not to keep timestamp
 */
function add_author_info(string $wikitext, ?int $timestamp_to_keep) : string
{
	global $auth_user;
	global $auth_user_fullname;

	$author = preg_replace('/"/', '', $auth_user);
	$fullname = $auth_user_fullname;

	if ((!$fullname) && ($author)) {
		// Fullname is empty, use $author as its fullname
		$fullname = preg_replace('/^[^:]*:/', '', $author);
	}

	$datetime_to_keep = '';

	if ($timestamp_to_keep !== null) {
		$datetime_to_keep .= ';'.get_date_atom($timestamp_to_keep + LOCALZONE);
	}

	$displayname = preg_replace('/"/', '', $fullname);
	$user_prefix = get_auth_user_prefix();
	$author_text = sprintf('#author("%s","%s","%s")', get_date_atom(UTIME + LOCALZONE).$datetime_to_keep, (($author) ? ($user_prefix.$author) : ('')), $displayname)."\n";

	return $author_text.$wikitext;
}

function remove_author_info(string $wikitext) : string
{
	return preg_replace('/^\s*#author\([^\n]*(\n|$)/m', '', $wikitext);
}

/**
 * Remove author line from wikitext.
 */
function remove_author_header(string $wikitext) : string
{
	$start = 0;

	while (($pos = strpos($wikitext, "\n", $start)) != false) {
		$line = substr($wikitext, $start, $pos);
		$m = null;

		if (preg_match('/^#author\(/', $line, $m)) {
			// fond #author line, Remove this line only
			if ($start === 0) {
				return substr($wikitext, $pos + 1);
			} else {
				return substr($wikitext, 0, $start - 1).substr($wikitext, $pos + 1);
			}
		} elseif (preg_match('/^#freeze(\W|$)/', $line, $m)) {
			// Found #freeze still in header
		} else {
			// other line, #author not found
			return $wikitext;
		}

		$start = $pos + 1;
	}

	return $wikitext;
}

/**
 * Get author info from wikitext.
 */
function get_author_info(string $wikitext)
{
	$start = 0;

	while (($pos = strpos($wikitext, "\n", $start)) != false) {
		$line = substr($wikitext, $start, $pos);
		$m = null;

		if (preg_match('/^#author\(/', $line, $m)) {
			return $line;
		} elseif (preg_match('/^#freeze(\W|$)/', $line, $m)) {
			// Found #freeze still in header
		} else {
			// other line, #author not found
			return;
		}

		$start = $pos + 1;
	}
}

/**
 * Get updated datetime from author.
 */
function get_update_datetime_from_author(string $author_line)
{
	$m = null;

	if (preg_match('/^#author\(\"([^\";]+)(?:;([^\";]+))?/', $author_line, $m)) {
		if ($m[2]) {
			return $m[2];
		} elseif ($m[1]) {
			return $m[1];
		}
	}
}

// Generate ID
function generate_fixed_heading_anchor_id(string $seed) : string
{
	// A random alphabetic letter + 7 letters of random strings from md5()
	return chr(mt_rand(ord('a'), ord('z'))).substr(md5(uniqid(substr($seed, 0, 100), true)), mt_rand(0, 24), 7);
}

// Read top N lines as an array
// (Use PHP file() function if you want to get ALL lines)
function file_head(string $file, int $count = 1, bool $lock = true, int $buffer = 8192)
{
	$array = [];

	$fp = @fopen($file, 'r');

	if ($fp === false) {
		return false;
	}

	set_file_buffer($fp, 0);

	if ($lock) {
		flock($fp, LOCK_SH);
	}

	rewind($fp);
	$index = 0;

	while (!feof($fp)) {
		$line = fgets($fp, $buffer);

		if ($line != false) {
			$array[] = $line;
		}

		if (++$index >= $count) {
			break;
		}
	}

	if ($lock) {
		flock($fp, LOCK_UN);
	}

	if (!fclose($fp)) {
		return false;
	}

	return $array;
}

// Output to a file
function file_write(string $dir, string $page, string $str, bool $notimestamp = false, bool $is_delete = false) : void
{
	global $_msg_invalidiwn;
	global $notify;
	global $notify_diff_only;
	global $notify_subject;
	global $whatsdeleted;
	global $maxshow_deleted;

	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	if (($dir != DATA_DIR) && ($dir != DIFF_DIR)) {
		die('file_write(): Invalid directory');
	}

	$page = strip_bracket($page);
	$file = $dir.encode($page).'.txt';
	$file_exists = file_exists($file);

	// ----
	// Delete?

	if (($dir == DATA_DIR) && ($is_delete)) {
		// Page deletion
		if (!$file_exists) {
			// Ignore null posting for DATA_DIR
			return;
		}

		// Update RecentDeleted (Add the $page)
		add_recent($page, $whatsdeleted, '', $maxshow_deleted);

		// Remove the page
		unlink($file);

		// Update RecentDeleted, and remove the page from RecentChanges
		lastmodified_add($whatsdeleted, $page);

		// Clear is_page() cache
		is_page($page, true);

		return;
	} elseif (($dir == DIFF_DIR) && ($str === " \n")) {
		// Ignore null posting for DIFF_DIR
		return;
	}

	// ----
	// File replacement (Edit)

	if (!is_pagename($page)) {
		die_message(str_replace('$1', htmlspecialchars($page, ENT_COMPAT, 'UTF-8'), str_replace('$2', 'WikiName', $_msg_invalidiwn)));
	}

	$str = rtrim(preg_replace('/'."\r".'/', '', $str))."\n";
	$timestamp = (($file_exists) && ($notimestamp)) ? (filemtime($file)) : (false);

	if (!($fp = fopen($file, 'a'))) {
		die('fopen() failed: '.htmlspecialchars(basename($dir).'/'.encode($page).'.txt', ENT_COMPAT, 'UTF-8').'<br />'."\n".'Maybe permission is not writable or filename is too long');
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);
	ftruncate($fp, 0);
	rewind($fp);
	fwrite($fp, $str);
	flock($fp, LOCK_UN);
	fclose($fp);

	if ($timestamp) {
		pkwk_touch_file($file, $timestamp);
	}

	// Optional actions
	if ($dir == DATA_DIR) {
		// Update RecentChanges (Add or renew the $page)
		if ($timestamp === false) {
			lastmodified_add($page);
		}

		// Command execution per update
		if ((defined('PKWK_UPDATE_EXEC')) && (PKWK_UPDATE_EXEC)) {
			system(PKWK_UPDATE_EXEC.' > /dev/null &');
		}
	} elseif (($dir == DIFF_DIR) && ($notify)) {
		if ($notify_diff_only) {
			$str = preg_replace('/^[^-+].*\n/m', '', $str);
		}

		$footer['ACTION'] = 'Page update';
		$footer['PAGE'] = $page;
		$footer['URI'] = get_page_uri($page, PKWK_URI_ABSOLUTE);
		$footer['USER_AGENT'] = true;
		$footer['REMOTE_ADDR'] = true;

		if (!pkwk_mail_notify($notify_subject, $str, $footer)) {
			die('pkwk_mail_notify(): Failed');
		}
	}

	if ($dir === DIFF_DIR) {
		pkwk_log_updates($page, $str);
	}

	// Clear is_page() cache
	is_page($page, true);
}

// Update RecentDeleted
function add_recent(string $page, string $recentpage, string $subject = '', int $limit = 0) : void
{
	if ((PKWK_READONLY) || ($limit == 0) || ($page == '') || ($recentpage == '') || (check_non_list($page))) {
		return;
	}

	// Load
	$matches = [];
	$lines = [];

	foreach (get_source($recentpage) as $line) {
		if (preg_match('/^-(.+) - (\[\[.+\]\])$/', $line, $matches)) {
			$lines[$matches[2]] = $line;
		}
	}

	$_page = '[['.$page.']]';

	// Remove a report about the same page
	if (isset($lines[$_page])) {
		unset($lines[$_page]);
	}

	// Add
	array_unshift($lines, '-'.format_date(UTIME).' - '.$_page.htmlspecialchars($subject, ENT_COMPAT, 'UTF-8')."\n");

	// Get latest $limit reports
	$lines = array_splice($lines, 0, $limit);

	// Update
	if (!($fp = fopen(get_filename($recentpage), 'w'))) {
		die_message('Cannot write page file '.htmlspecialchars($recentpage, ENT_COMPAT, 'UTF-8').'<br />Maybe permission is not writable or filename is too long');
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);
	rewind($fp);
	fwrite($fp, '#freeze'."\n");

	// :)
	fwrite($fp, '#norelated'."\n");

	fwrite($fp, implode('', $lines));
	flock($fp, LOCK_UN);
	fclose($fp);
}

// Update PKWK_MAXSHOW_CACHE itself (Add or renew about the $page) (Light)
// Use without $autolink
function lastmodified_add(string $update = '', string $remove = '') : void
{
	global $maxshow;
	global $whatsnew;
	global $autolink;

	// AutoLink implimentation needs everything, for now
	if ($autolink) {
		// Try to (re)create ALL
		put_lastmodified();

		return;
	}

	if ((($update == '') || (check_non_list($update))) && ($remove == '')) {
		// No need
		return;
	}

	$file = CACHE_DIR.PKWK_MAXSHOW_CACHE;

	if (!file_exists($file)) {
		// Try to (re)create ALL
		put_lastmodified();

		return;
	}

	// Open
	pkwk_touch_file($file);

	if (!($fp = fopen($file, 'r+'))) {
		die_message('Cannot open CACHE_DIR/'.PKWK_MAXSHOW_CACHE);
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);

	// Read (keep the order of the lines)
	$matches = [];
	$recent_pages = [];

	foreach (file_head($file, $maxshow + PKWK_MAXSHOW_ALLOWANCE, false) as $line) {
		if (preg_match('/^([0-9]+)\t(.+)/', $line, $matches)) {
			$recent_pages[$matches[2]] = $matches[1];
		}
	}

	// Remove if it exists inside
	if (isset($recent_pages[$update])) {
		unset($recent_pages[$update]);
	}

	if (isset($recent_pages[$remove])) {
		unset($recent_pages[$remove]);
	}

	// Add to the top: like array_unshift()
	if ($update != '') {
		$recent_pages = [$update=>get_filetime($update)] + $recent_pages;
	}

	// Check
	$abort = count($recent_pages) < $maxshow;

	if (!$abort) {
		// Write
		ftruncate($fp, 0);
		rewind($fp);

		foreach ($recent_pages as $_page=>$time) {
			fwrite($fp, $time."\t".$_page."\n");
		}
	}

	flock($fp, LOCK_UN);
	fclose($fp);

	if ($abort) {
		// Try to (re)create ALL
		put_lastmodified();

		return;
	}

	// ----
	// Update the page 'RecentChanges'

	$recent_pages = array_splice($recent_pages, 0, $maxshow);
	$file = get_filename($whatsnew);

	// Open
	pkwk_touch_file($file);

	if (!($fp = fopen($file, 'r+'))) {
		die_message('Cannot open '.htmlspecialchars($whatsnew, ENT_COMPAT, 'UTF-8'));
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);

	// Recreate
	ftruncate($fp, 0);
	rewind($fp);

	foreach ($recent_pages as $_page=>$time) {
		fwrite($fp, '-'.htmlspecialchars(format_date((int) ($time)), ENT_COMPAT, 'UTF-8').' - [['.htmlspecialchars($_page, ENT_COMPAT, 'UTF-8').']]'."\n");
	}

	// :)
	fwrite($fp, '#norelated'."\n");

	flock($fp, LOCK_UN);
	fclose($fp);
}

// Re-create PKWK_MAXSHOW_CACHE (Heavy)
function put_lastmodified() : void
{
	global $maxshow;
	global $whatsnew;
	global $autolink;

	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	// Get WHOLE page list
	$pages = get_existpages();

	// Check ALL filetime
	$recent_pages = [];

	foreach ($pages as $page) {
		if (($page !== $whatsnew) && (!check_non_list($page))) {
			$recent_pages[$page] = get_filetime($page);
		}
	}

	// Sort decending order of last-modification date
	arsort($recent_pages, SORT_NUMERIC);

	// Cut unused lines
	//array_splice() will break integer keys in hashtable
	$count = $maxshow + PKWK_MAXSHOW_ALLOWANCE;
	$_recent = [];

	foreach ($recent_pages as $key=>$value) {
		unset($recent_pages[$key]);
		$_recent[$key] = $value;

		if (--$count < 1) {
			break;
		}
	}

	$recent_pages = &$_recent;

	// Re-create PKWK_MAXSHOW_CACHE
	$file = CACHE_DIR.PKWK_MAXSHOW_CACHE;
	pkwk_touch_file($file);

	if (!($fp = fopen($file, 'r+'))) {
		die_message('Cannot open CACHE_DIR/'.PKWK_MAXSHOW_CACHE);
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);
	ftruncate($fp, 0);
	rewind($fp);

	foreach ($recent_pages as $page=>$time) {
		fwrite($fp, $time."\t".$page."\n");
	}

	flock($fp, LOCK_UN);
	fclose($fp);

	// Create RecentChanges
	$file = get_filename($whatsnew);
	pkwk_touch_file($file);

	if (!($fp = fopen($file, 'r+'))) {
		die_message('Cannot open '.htmlspecialchars($whatsnew, ENT_COMPAT, 'UTF-8'));
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);
	ftruncate($fp, 0);
	rewind($fp);

	foreach (array_keys($recent_pages) as $page) {
		$time = $recent_pages[$page];
		$s_lastmod = htmlspecialchars(format_date($time), ENT_COMPAT, 'UTF-8');
		$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
		fwrite($fp, '-'.$s_lastmod.' - [['.$s_page.']]'."\n");
	}

	// :)
	fwrite($fp, '#norelated'."\n");

	flock($fp, LOCK_UN);
	fclose($fp);

	// For AutoLink
	if ($autolink) {
		autolink_pattern_write(CACHE_DIR.PKWK_AUTOLINK_REGEX_CACHE, get_autolink_pattern($pages, $autolink));
	}
}

/**
 * Get recent files.
 *
 * @return array of (file=>time)
 */
function get_recent_files() : array
{
	$recentfile = CACHE_DIR.PKWK_MAXSHOW_CACHE;
	$lines = file($recentfile);

	if (!$lines) {
		return [];
	}

	$files = [];

	foreach ($lines as $line) {
		[$time, $file] = explode("\t", rtrim($line));
		$files[$file] = $time;
	}

	return $files;
}

/**
 * Update RecentChanges page / Invalidate recent.dat.
 */
function delete_recent_changes_cache() : void
{
	$file = CACHE_DIR.PKWK_MAXSHOW_CACHE;
	unlink($file);
}

// update autolink data
function autolink_pattern_write(string $filename, int $autolink_pattern) : void
{
	[$pattern, $pattern_a, $forceignorelist] = (string) ($autolink_pattern);

	if (!($fp = fopen($filename, 'w'))) {
		die_message('Cannot open '.$filename);
	}

	set_file_buffer($fp, 0);
	flock($fp, LOCK_EX);
	rewind($fp);
	fwrite($fp, $pattern."\n");
	fwrite($fp, $pattern_a."\n");
	fwrite($fp, implode("\t", $forceignorelist)."\n");
	flock($fp, LOCK_UN);
	fclose($fp);
}

// Update AutoAlias regex cache
function update_autoalias_cache_file() : void
{
	// Disable (0), Enable (min-length)
	global $autoalias;

	$aliases = get_autoaliases();

	if (empty($aliases)) {
		// Remove
		@unlink(CACHE_DIR.PKWK_AUTOALIAS_REGEX_CACHE);
	} else {
		// Create or Update
		autolink_pattern_write(CACHE_DIR.PKWK_AUTOALIAS_REGEX_CACHE, get_autolink_pattern(array_keys($aliases), $autoalias));
	}
}

// Get elapsed date of the page
function get_pg_passage(string $page, bool $sw = true) : string
{
	global $show_passage;

	if (!$show_passage) {
		return '';
	}

	$time = get_filetime($page);
	$pg_passage = ($time != 0) ? (get_passage($time)) : ('');

	return ($sw) ? ('<small>'.$pg_passage.'</small>') : (' '.$pg_passage);
}

// Last-Modified header
function header_lastmod(string $page = null) : void
{
	global $lastmod;

	if (($lastmod) && (is_page($page))) {
		pkwk_headers_sent();
		header('Last-Modified: '.date('D, d M Y H:i:s', get_filetime($page)).' GMT');
	}
}

// Get a list of encoded files (must specify a directory and a suffix)
function get_existfiles(string $dir = DATA_DIR, string $ext = '.txt') : array
{
	$aryret = [];
	$pattern = '/^(?:[0-9A-F]{2})+'.preg_quote($ext, '/').'$/';

	if (!($dp = @opendir($dir))) {
		die_message($dir.' is not found or not readable.');
	}

	while (($file = readdir($dp)) !== false) {
		if (preg_match($pattern, $file)) {
			$aryret[] = $dir.$file;
		}
	}

	closedir($dp);

	return $aryret;
}

/**
 * Get/Set pagelist cache enabled for get_existpages().
 *
 * @param $newvalue Set true when the system can cache the page list
 *
 * @return true if can use page list cache
 */
function is_pagelist_cache_enabled(bool $newvalue = null)
{
	static $cache_enabled = null;

	if ($newvalue !== null) {
		$cache_enabled = $newvalue;

		// Return nothing on setting newvalue call
		return;
	}

	if ($cache_enabled === null) {
		return false;
	}

	return $cache_enabled;
}

// Get a page list of this wiki
function get_existpages(string $dir = DATA_DIR, string $ext = '.txt') : array
{
	// Cached wikitext page list
	static $cached_list = null;

	$use_cache = false;

	if (($dir === DATA_DIR) && ($ext === '.txt') && (is_pagelist_cache_enabled())) {
		// Use pagelist cache for "wiki/*.txt" files
		if ($cached_list !== null) {
			return $cached_list;
		}

		$use_cache = true;
	}

	$aryret = [];
	$pattern = '/^((?:[0-9A-F]{2})+)'.preg_quote($ext, '/').'$/';

	if (!($dp = @opendir($dir))) {
		die_message($dir.' is not found or not readable.');
	}

	$matches = [];

	while (($file = readdir($dp)) !== false) {
		if (preg_match($pattern, $file, $matches)) {
			$aryret[$file] = decode($matches[1]);
		}
	}

	closedir($dp);

	if ($use_cache) {
		$cached_list = $aryret;
	}

	return $aryret;
}

// Get PageReading(pronounce-annotated) data in an array()
function get_readings() : array
{
	global $pagereading_enable;
	global $pagereading_kanji2kana_converter;
	global $pagereading_kanji2kana_encoding;
	global $pagereading_chasen_path;
	global $pagereading_kakasi_path;
	global $pagereading_config_page;
	global $pagereading_config_dict;

	$pages = get_existpages();

	$readings = [];

	foreach ($pages as $page) {
		$readings[$page] = '';
	}

	$deletedPage = false;
	$matches = [];

	foreach (get_source($pagereading_config_page) as $line) {
		$line = rtrim($line);

		if (preg_match('/^-\[\[([^]]+)\]\]\s+(.+)$/', $line, $matches)) {
			if (isset($readings[$matches[1]])) {
				// This page is not clear how to be pronounced
				$readings[$matches[1]] = $matches[2];
			} else {
				// This page seems deleted
				$deletedPage = true;
			}
		}
	}

	// If enabled ChaSen/KAKASI execution
	if ($pagereading_enable) {
		// Check there's non-clear-pronouncing page
		$unknownPage = false;

		foreach ($readings as $page=>$reading) {
			if ($reading == '') {
				$unknownPage = true;

				break;
			}
		}

		// Execute ChaSen/KAKASI, and get annotation
		if ($unknownPage) {
			switch (strtolower($pagereading_kanji2kana_converter)) {
				case 'chasen':
					if (!file_exists($pagereading_chasen_path)) {
						die_message('ChaSen not found: '.$pagereading_chasen_path);
					}

					$tmpfname = tempnam(realpath(CACHE_DIR), 'PageReading');

					if (!($fp = fopen($tmpfname, 'w'))) {
						die_message('Cannot write temporary file "'.$tmpfname.'".'."\n");
					}

					foreach ($readings as $page=>$reading) {
						if ($reading != '') {
							continue;
						}

						fwrite($fp, mb_convert_encoding($page."\n", $pagereading_kanji2kana_encoding, 'UTF-8'));
					}

					fclose($fp);

					$chasen = $pagereading_chasen_path.' -F %y '.$tmpfname;
					$fp = popen($chasen, 'r');

					if ($fp === false) {
						unlink($tmpfname);
						die_message('ChaSen execution failed: '.$chasen);
					}

					foreach ($readings as $page=>$reading) {
						if ($reading != '') {
							continue;
						}

						$line = fgets($fp);
						$line = mb_convert_encoding($line, 'UTF-8', $pagereading_kanji2kana_encoding);
						$line = rtrim($line);
						$readings[$page] = $line;
					}

					pclose($fp);

					if (!unlink($tmpfname)) {
						die_message('Temporary file can not be removed: '.$tmpfname);
					}

					break;

				case 'kakasi':
				case 'kakashi':
					if (!file_exists($pagereading_kakasi_path)) {
						die_message('KAKASI not found: '.$pagereading_kakasi_path);
					}

					$tmpfname = tempnam(realpath(CACHE_DIR), 'PageReading');

					if (!($fp = fopen($tmpfname, 'w'))) {
						die_message('Cannot write temporary file "'.$tmpfname.'".'."\n");
					}

					foreach ($readings as $page=>$reading) {
						if ($reading != '') {
							continue;
						}

						fwrite($fp, mb_convert_encoding($page."\n", $pagereading_kanji2kana_encoding, 'UTF-8'));
					}

					fclose($fp);

					$kakasi = $pagereading_kakasi_path.' -kK -HK -JK < '.$tmpfname;
					$fp = popen($kakasi, 'r');

					if ($fp === false) {
						unlink($tmpfname);
						die_message('KAKASI execution failed: '.$kakasi);
					}

					foreach ($readings as $page=>$reading) {
						if ($reading != '') {
							continue;
						}

						$line = fgets($fp);
						$line = mb_convert_encoding($line, 'UTF-8', $pagereading_kanji2kana_encoding);
						$line = rtrim($line);
						$readings[$page] = $line;
					}

					pclose($fp);

					if (!unlink($tmpfname)) {
						die_message('Temporary file can not be removed: '.$tmpfname);
					}

					break;

				case 'none':
					$matches = [];
					$replacements = [];
					$patterns = [];

					foreach (get_source($pagereading_config_dict) as $line) {
						$line = rtrim($line);

						if (preg_match('|^ /([^/]+)/,\s*(.+)$|', $line, $matches)) {
							$patterns[] = $matches[1];
							$replacements[] = $matches[2];
						}
					}

					foreach ($readings as $page=>$reading) {
						if ($reading != '') {
							continue;
						}

						$readings[$page] = $page;

						foreach ($patterns as $no=>$pattern) {
							$readings[$page] = mb_convert_kana(mb_ereg_replace($pattern, $replacements[$no], $readings[$page]), 'aKCV');
						}
					}

					break;

				default:
					die_message('Unknown kanji-kana converter: '.$pagereading_kanji2kana_converter.'.');

					break;
			}
		}

		if (($unknownPage) || ($deletedPage)) {
			// Sort by pronouncing(alphabetical/reading) order
			asort($readings, SORT_STRING);

			$body = '';

			foreach ($readings as $page=>$reading) {
				$body .= '-[['.$page.']] '.$reading."\n";
			}

			page_write($pagereading_config_page, $body);
		}
	}

	// Pages that are not prounouncing-clear, return pagenames of themselves
	foreach ($pages as $page) {
		if ($readings[$page] == '') {
			$readings[$page] = $page;
		}
	}

	return $readings;
}

// Get a list of related pages of the page
function links_get_related(string $page) : array
{
	global $vars;
	global $related;
	static $links = [];

	if (isset($links[$page])) {
		return $links[$page];
	}

	// If possible, merge related pages generated by make_link()
	$links[$page] = ($page === $vars['page']) ? ($related) : ([]);

	// Get repated pages from DB
	$links[$page] += links_get_related_db($vars['page']);

	return $links[$page];
}

// _If needed_, re-create the file to change/correct ownership into PHP's
// NOTE: Not works for Windows
function pkwk_chown(string $filename, bool $preserve_time = true) : bool
{
	// PHP's UID
	static $php_uid;

	if (!isset($php_uid)) {
		if (extension_loaded('posix')) {
			// Unix
			$php_uid = posix_getuid();
		} else {
			// Windows
			$php_uid = 0;
		}
	}

	// Lock for pkwk_chown()
	$lockfile = CACHE_DIR.'pkwk_chown.lock';

	if (!($flock = fopen($lockfile, 'a'))) {
		die('pkwk_chown(): fopen() failed for: CACHEDIR/'.basename(htmlspecialchars($lockfile, ENT_COMPAT, 'UTF-8')));
	}

	if (!flock($flock, LOCK_EX)) {
		die('pkwk_chown(): flock() failed for lock');
	}

	// Check owner
	if (!($stat = stat($filename))) {
		die('pkwk_chown(): stat() failed for: '.basename(htmlspecialchars($filename, ENT_COMPAT, 'UTF-8')));
	}

	if ($stat[4] === $php_uid) {
		// NOTE: Windows always here

		// Seems the same UID. Nothing to do
		$result = true;
	} else {
		$tmp = $filename.'.'.getmypid().'.tmp';

		// Lock source $filename to avoid file corruption
		// NOTE: Not 'r+'. Don't check write permission here
		if (!($ffile = fopen($filename, 'r'))) {
			die('pkwk_chown(): fopen() failed for: '.basename(htmlspecialchars($filename, ENT_COMPAT, 'UTF-8')));
		}

		// Try to chown by re-creating files
		// NOTE:
		//   * touch() before copy() is for 'rw-r--r--' instead of 'rwxr-xr-x' (with umask 022).
		//   * (PHP 4 < PHP 4.2.0) touch() with the third argument is not implemented and retuns null and Warn.
		//   * @unlink() before rename() is for Windows but here's for Unix only
		if (!flock($ffile, LOCK_EX)) {
			die('pkwk_chown(): flock() failed');
		}

		$result = (touch($tmp)) && (copy($filename, $tmp)) && (($preserve_time) ? ((touch($tmp, $stat[9], $stat[8])) || (touch($tmp, $stat[9]))) : (true)) && (rename($tmp, $filename));

		if (!flock($ffile, LOCK_UN)) {
			die('pkwk_chown(): flock() failed');
		}

		if (!fclose($ffile)) {
			die('pkwk_chown(): fclose() failed');
		}

		if ($result === false) {
			@unlink($tmp);
		}
	}

	// Unlock for pkwk_chown()
	if (!flock($flock, LOCK_UN)) {
		die('pkwk_chown(): flock() failed for lock');
	}

	if (!fclose($flock)) {
		die('pkwk_chown(): fclose() failed for lock');
	}

	return $result;
}

// touch() with trying pkwk_chown()
function pkwk_touch_file(string $filename, $time = false, $atime = false) : bool
{
	// Is the owner incorrected and unable to correct?
	if ((!file_exists($filename)) || (pkwk_chown($filename))) {
		if ($time === false) {
			$result = touch($filename);
		} elseif ($atime === false) {
			$result = touch($filename, $time);
		} else {
			$result = touch($filename, $time, $atime);
		}

		return $result;
	} else {
		die('pkwk_touch_file(): Invalid UID and (not writable for the directory or not a flie): '.htmlspecialchars(basename($filename), ENT_COMPAT, 'UTF-8'));
	}
}

/**
 * Lock-enabled file_get_contents.
 */
function pkwk_file_get_contents(string $filename) : string
{
	if (!file_exists($filename)) {
		return false;
	}

	$fp = fopen($filename, 'rb');
	flock($fp, LOCK_SH);
	$file = file_get_contents($filename);
	flock($fp, LOCK_UN);

	return $file;
}

/**
 * Prepare some cache files for convert_html().
 *
 * * Make cache/autolink.dat if needed
 */
function prepare_display_materials() : void
{
	global $autolink;

	if ($autolink) {
		// Make sure 'cache/autolink.dat'
		$file = CACHE_DIR.PKWK_AUTOLINK_REGEX_CACHE;

		if (!file_exists($file)) {
			// Re-create autolink.dat
			put_lastmodified();
		}
	}
}

/**
 * Prepare page related links and references for links_get_related().
 */
function prepare_links_related(string $page) : void
{
	global $defaultpage;

	$enc_defaultpage = encode($defaultpage);

	if (file_exists(CACHE_DIR.$enc_defaultpage.'.rel')) {
		return;
	}

	if (file_exists(CACHE_DIR.$enc_defaultpage.'.ref')) {
		return;
	}

	$enc_name = encode($page);

	if (file_exists(CACHE_DIR.$enc_name.'.rel')) {
		return;
	}

	if (file_exists(CACHE_DIR.$enc_name.'.ref')) {
		return;
	}

	$pattern = '/^((?:[0-9A-F]{2})+)(\.ref|\.rel)$/';
	$dir = CACHE_DIR;

	if (!($dp = @opendir($dir))) {
		die_message('CACHE_DIR/ is not found or not readable.');
	}

	$rel_ref_ready = false;
	$count = 0;

	while (($file = readdir($dp)) !== false) {
		if (preg_match($pattern, $file, $matches)) {
			if ($count++ > 5) {
				$rel_ref_ready = true;

				break;
			}
		}
	}

	closedir($dp);

	if (!$rel_ref_ready) {
		if (count(get_existpages()) < 50) {
			// Make link files automatically only if page count < 50.
			// Because large number of update links will cause PHP timeout.
			links_init();
		}
	}
}
