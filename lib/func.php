<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// func.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// General functions

// URI type enum

/** Relative path. */
define('PKWK_URI_RELATIVE', 0);

/** Root relative URI. */
define('PKWK_URI_ROOT', 1);

/** Absolute URI. */
define('PKWK_URI_ABSOLUTE', 2);

function pkwk_log(string $message) : void
{
	static $dateTimeExists;

	$log_filepath = 'log/error.log.php';

	if (!isset($dateTimeExists)) {
		$dateTimeExists = class_exists('DateTime');
		error_log("<?php\n", 3, $log_filepath);
	}

	if ($dateTimeExists) {
		// for PHP5.2+
		$d = \DateTime::createFromFormat('U.u', sprintf('%6F', microtime(true)));
		$timestamp = substr($d->format('Y-m-d H:i:s.u'), 0, 23);
	} else {
		$timestamp = date('Y-m-d H:i:s');
	}

	error_log($timestamp.' '.$message."\n", 3, $log_filepath);
}

/*
 * Get LTSV safe string - Remove tab and newline chars.
 *
 * @param $s target string
 */
function get_ltsv_value(string $s) : string
{
	if (!$s) {
		return '';
	}

	return preg_replace('#[\t\r\n]#', '', $s);
}

/**
 * Write update_log on updating contents.
 *
 * @param $page page name
 * @param $diff_content diff expression
 */
function pkwk_log_updates(string $page, string $diff_content) : void
{
	global $auth_user;
	global $logging_updates;
	global $logging_updates_log_dir;

	$log_dir = $logging_updates_log_dir;
	$timestamp = time();
	$ymd = gmdate('Ymd', $timestamp);
	$difflog_file = $log_dir.'/diff.'.$ymd.'.log';
	$ltsv_file = $log_dir.'/update.'.$ymd.'.log';
	$d =
	[
		'time'=>gmdate('Y-m-d H:i:s', $timestamp),
		'uri'=>$_SERVER['REQUEST_URI'],
		'method'=>$_SERVER['REQUEST_METHOD'],
		'remote_addr'=>$_SERVER['REMOTE_ADDR'],
		'user_agent'=>$_SERVER['HTTP_USER_AGENT'],
		'page'=>$page,
		'user'=>$auth_user,
		'diff'=>$diff_content,
	];

	if ((file_exists($log_dir)) && (defined('JSON_UNESCAPED_UNICODE'))) {
		// require: PHP5.4+
		$line = json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
		file_put_contents($difflog_file, $line, FILE_APPEND|LOCK_EX);
		$keys = ['time', 'uri', 'method', 'remote_addr', 'user_agent', 'page', 'user'];
		$ar2 = [];

		foreach ($keys as $k) {
			$ar2[] = $k.':'.get_ltsv_value($d[$k]);
		}

		$ltsv = implode($ar2, "\t")."\n";
		file_put_contents($ltsv_file, $ltsv, FILE_APPEND|LOCK_EX);
	}
}

/**
 * ctype_digit that supports PHP4+.
 *
 * PHP official document says PHP4 has ctype_digit() function.
 * But sometimes it doen't exists on PHP 4.1.
 */
function pkwk_ctype_digit(?string $s) : bool
{
	static $ctype_digit_exists;

	if (!isset($ctype_digit_exists)) {
		$ctype_digit_exists = function_exists('ctype_digit');
	}

	if ($ctype_digit_exists) {
		return ctype_digit($s);
	}

	return (preg_match('/^[0-9]+$/', $s)) ? (true) : (false);
}

function is_interwiki(string $str)
{
	global $InterWikiName;

	return preg_match('/^'.$InterWikiName.'$/', $str);
}

function is_pagename(string $str) : bool
{
	global $BracketName;

	$is_pagename = (!is_interwiki($str)) && (preg_match('/^(?!\/)'.$BracketName.'$(?<!\/$)/', $str)) && (!preg_match('#(^|/)\.{1,2}(/|$)#', $str));

	if (defined('SOURCE_ENCODING')) {
		switch (SOURCE_ENCODING) {
			case 'UTF-8':
				$pattern = '/^(?:[\x00-\x7F]|(?:[\xC0-\xDF][\x80-\xBF])|(?:[\xE0-\xEF][\x80-\xBF][\x80-\xBF]))+$/';

				break;

			case 'EUC-JP':
				$pattern = '/^(?:[\x00-\x7F]|(?:[\x8E\xA1-\xFE][\xA1-\xFE])|(?:\x8F[\xA1-\xFE][\xA1-\xFE]))+$/';

				break;

			default:
				break;
		}

		if ((isset($pattern)) && ($pattern != '')) {
			$is_pagename = (($is_pagename) && (preg_match($pattern, $str)));
		}
	}

	return $is_pagename;
}

function is_url(string $str, bool $only_http = false)
{
	$scheme = ($only_http) ? ('https?') : ('https?|ftp|news');

	return preg_match('/^('.$scheme.')(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]*)$/', $str);
}

// If the page exists
function is_page(?string $page, bool $clearcache = false) : bool
{
	if ($clearcache) {
		clearstatcache();
	}

	return file_exists(get_filename($page));
}

function is_editable(string $page) : bool
{
	global $cantedit;
	static $is_editable = [];

	if (!isset($is_editable[$page])) {
		$is_editable[$page] = (is_pagename($page)) && (!is_freeze($page)) && (!in_array($page, $cantedit, true));
	}

	return $is_editable[$page];
}

function is_freeze(string $page, bool $clearcache = false) : bool
{
	global $function_freeze;
	static $is_freeze = [];

	if ($clearcache === true) {
		$is_freeze = [];
	}

	if (isset($is_freeze[$page])) {
		return $is_freeze[$page];
	}

	if ((!$function_freeze) || (!is_page($page))) {
		$is_freeze[$page] = false;

		return false;
	} else {
		if (!($fp = fopen(get_filename($page), 'rb'))) {
			die('is_freeze(): fopen() failed: '.htmlsc($page));
		}

		if (!flock($fp, LOCK_SH)) {
			die('is_freeze(): flock() failed');
		}

		rewind($fp);
		$buffer = fread($fp, 1000);

		if (!flock($fp, LOCK_UN)) {
			die('is_freeze(): flock() failed');
		}

		if (!fclose($fp)) {
			die('is_freeze(): fclose() failed: '.htmlsc($page));
		}

		$is_freeze[$page] = (bool) (preg_match('/^#freeze$/m', $buffer));

		return $is_freeze[$page];
	}
}

// Handling $non_list
// $non_list will be preg_quote($str, '/') later.
function check_non_list(string $page = '')
{
	global $non_list;
	static $regex;

	if (!isset($regex)) {
		$regex = '/'.$non_list.'/';
	}

	return preg_match($regex, $page);
}

// Auto template
function auto_template(string $page) : string
{
	global $auto_template_func;
	global $auto_template_rules;

	if (!$auto_template_func) {
		return '';
	}

	$body = '';
	$matches = [];

	foreach ($auto_template_rules as $rule=>$template) {
		$rule_pattrn = '/'.$rule.'/';

		if (!preg_match($rule_pattrn, $page, $matches)) {
			continue;
		}

		$template_page = preg_replace($rule_pattrn, $template, $page);

		if (!is_page($template_page)) {
			continue;
		}

		$body = implode('', get_source($template_page));

		// Remove fixed-heading anchors
		$body = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m', '$1$2', $body);

		// Remove '#freeze'
		$body = preg_replace('/^#freeze\s*$/m', '', $body);

		$count = count($matches);

		for ($i = 0; $i < $count; $i++) {
			$body = str_replace('$'.$i, $matches[$i], $body);
		}

		break;
	}

	return $body;
}

function _mb_convert_kana__enable(string $str, string $option) : string
{
	return mb_convert_kana($str, $option, SOURCE_ENCODING);
}

function _mb_convert_kana__none(string $str, string $option) : string
{
	return $str;
}

// Expand all search-words to regexes and push them into an array
function get_search_words(array $words = [], bool $do_escape = false) : array
{
	static $init;
	static $mb_convert_kana;
	static $pre;
	static $post;
	static $quote = '/';

	if (!isset($init)) {
		// function: mb_convert_kana() is for Japanese code only
		if ((LANG == 'ja') && (function_exists('mb_convert_kana'))) {
			$mb_convert_kana = '_mb_convert_kana__enable';
		} else {
			$mb_convert_kana = '_mb_convert_kana__none';
		}

		if (SOURCE_ENCODING == 'EUC-JP') {
			// Perl memo - Correct pattern-matching with EUC-JP
			// http://www.din.or.jp/~ohzaki/perl.htm#JP_Match (Japanese)
			$pre = '(?<!\x8F)';
			$post = '(?=(?:[\xA1-\xFE][\xA1-\xFE])*'. // JIS X 0208
				'(?:[\x00-\x7F\x8E\x8F]|\z))';     // ASCII, SS2, SS3, or the last
		} else {
			$post = '';
			$pre = '';
		}

		$init = true;
	}

	if (!is_array($words)) {
		$words = [$words];
	}

	// Generate regex for the words
	$regex = [];

	foreach ($words as $word) {
		$word = trim($word);

		if ($word == '') {
			continue;
		}

		// Normalize: ASCII letters = to single-byte. Others = to Zenkaku and Katakana
		$word_nm = $mb_convert_kana($word, 'aKCV');
		$nmlen = mb_strlen($word_nm, SOURCE_ENCODING);

		// Each chars may be served ...
		$chars = [];

		for ($pos = 0; $pos < $nmlen; $pos++) {
			$char = mb_substr($word_nm, $pos, 1, SOURCE_ENCODING);

			// Just normalized one? (ASCII char or Zenkaku-Katakana?)
			$or = [preg_quote((($do_escape) ? (htmlsc($char)) : ($char)), $quote)];

			if (strlen($char) == 1) {
				// An ASCII (single-byte) character
				foreach ([strtoupper($char), strtolower($char)] as $_char) {
					if ($char != '&') {
						$or[] = preg_quote($_char, $quote);
					} // As-is?
					$ascii = ord($_char);

					// As an entity reference?
					$or[] = sprintf('&#(?:%d|x%x);', $ascii, $ascii);

					// As Zenkaku?
					$or[] = preg_quote($mb_convert_kana($_char, 'A'), $quote);
				}
			} else {
				// NEVER COME HERE with mb_substr(string, start, length, 'ASCII')
				// A multi-byte character

				// As Hiragana?
				$or[] = preg_quote($mb_convert_kana($char, 'c'), $quote);

				// As Hankaku-Katakana?
				$or[] = preg_quote($mb_convert_kana($char, 'k'), $quote);
			}

			// Regex for the character
			$chars[] = '(?:'.implode('|', array_unique($or)).')';
		}

		// For the word
		$regex[$word] = $pre.implode('', $chars).$post;
	}

	// For all words
	return $regex;
}

function get_passage_date_html_span(string $date_atom) : string
{
	return '<span class="page_passage" data-mtime="'.$date_atom.'"></span>';
}

function get_passage_mtime_html_span(int $mtime) : string
{
	$date_atom = get_date_atom($mtime);

	return get_passage_date_html_span($date_atom);
}

/**
 * Get passage span html.
 *
 * @param $page
 */
function get_passage_html_span(string $page) : string
{
	$date_atom = get_page_date_atom($page);

	return get_passage_date_html_span($date_atom);
}

function get_link_passage_class() : string
{
	return 'link_page_passage';
}

/**
 * Get page link general attributes.
 *
 * @param $page
 *
 * @return ['data_mtime'=>page mtime or null, 'class'=>additinal classes]
 */
function get_page_link_a_attrs(string $page) : array
{
	global $show_passage;

	if ($show_passage) {
		$pagemtime = get_page_date_atom($page);

		return
		[
			'data_mtime'=>$pagemtime,
			'class'=>get_link_passage_class(),
		];
	}

	return
	[
		'data_mtime'=>'',
		'class'=>'',
	];
}

/**
 * Get page link general attributes from filetime.
 *
 * @param $filetime
 *
 * @return ['data_mtime'=>page mtime or null, 'class'=>additinal classes]
 */
function get_filetime_a_attrs(int $filetime) : array
{
	global $show_passage;

	if ($show_passage) {
		$pagemtime = get_date_atom($filetime + LOCALZONE);

		return
		[
			'data_mtime'=>$pagemtime,
			'class'=>get_link_passage_class(),
		];
	}

	return
	[
		'data_mtime'=>'',
		'class'=>'',
	];
}

// 'Search' main function
function do_search(string $word, string $type = 'AND', bool $non_format = false, string $base = '') : string
{
	global $whatsnew;
	global $non_list;
	global $search_non_list;
	global $_msg_andresult;
	global $_msg_orresult;
	global $_msg_notfoundresult;
	global $search_auth;
	global $show_passage;

	$retval = [];

	// AND:true OR:false
	$b_type = ($type == 'AND');

	$keys = get_search_words(preg_split('/\s+/', $word, -1, PREG_SPLIT_NO_EMPTY));

	foreach ($keys as $key=>$value) {
		$keys[$key] = '/'.$value.'/S';
	}

	$pages = get_existpages();

	// Avoid
	if ($base != '') {
		$pages = preg_grep('/^'.preg_quote($base, '/').'/S', $pages);
	}

	if (!$search_non_list) {
		$pages = array_diff($pages, preg_grep('/'.$non_list.'/S', $pages));
	}

	$pages = array_flip($pages);
	unset($pages[$whatsnew]);

	$count = count($pages);

	foreach (array_keys($pages) as $page) {
		$b_match = false;

		// Search for page name
		if (!$non_format) {
			foreach ($keys as $key) {
				$b_match = preg_match($key, $page);

				if ($b_type xor $b_match) {
					break;
				} // OR
			}

			if ($b_match) {
				continue;
			}
		}

		// Search auth for page contents
		if (($search_auth) && (!check_readable($page, false, false))) {
			unset($pages[$page]);
			$count--;

			continue;
		}

		// Search for page contents
		foreach ($keys as $key) {
			$body = get_source($page, true, true, true);
			$b_match = preg_match($key, remove_author_header($body));

			if ($b_type xor $b_match) {
				break;
			} // OR
		}

		if ($b_match) {
			continue;
		}

		// Miss
		unset($pages[$page]);
	}

	if ($non_format) {
		return array_keys($pages);
	}

	$r_word = rawurlencode($word);
	$s_word = htmlsc($word);

	if (empty($pages)) {
		return str_replace('$1', $s_word, str_replace('$3', $count, $_msg_notfoundresult));
	}

	ksort($pages, SORT_STRING);

	$retval = '<ul>'."\n";

	foreach (array_keys($pages) as $page) {
		$r_page = rawurlencode($page);
		$s_page = htmlsc($page);
		$passage = ($show_passage) ? (' '.get_passage_html_span($page)) : ('');
		$retval .= "\t".'<li><a href="'.get_base_uri().'?cmd=read&amp;page='.$r_page.'&amp;word='.$r_word.'">'.$s_page.'</a>'.$passage.'</li>'."\n";
	}

	$retval .= '</ul>'."\n";

	$retval .= str_replace('$1', $s_word, str_replace('$2', count($pages), str_replace('$3', $count, (($b_type) ? ($_msg_andresult) : ($_msg_orresult)))));

	return $retval;
}

// Argument check for program
function arg_check(string $str) : bool
{
	global $vars;

	return (isset($vars['cmd'])) && (strpos($vars['cmd'], $str) === 0);
}

function _pagename_urlencode_callback(array $matches) : string
{
	return urlencode($matches[0]);
}

function pagename_urlencode(string $page) : string
{
	return preg_replace_callback('|[^/:]+|', '_pagename_urlencode_callback', $page);
}

// Encode page-name
function encode(?string $str) : string
{
	$str = (string) ($str);

	return ($str === '') ? ('') : (strtoupper(bin2hex($str)));
	// Equal to strtoupper(join('', unpack('H*0', $key)));
	// But PHP 4.3.10 says 'Warning: unpack(): Type H: outside of string in ...'
}

// Decode page name
function decode(string $str) : string
{
	return pkwk_hex2bin($str);
}

// Inversion of bin2hex()
function pkwk_hex2bin(string $hex_string) : string
{
	// preg_match : Avoid warning : pack(): Type H: illegal hex digit ...
	// (string)   : Always treat as string (not int etc). See BugTrack2/31
	return (preg_match('/^[0-9a-f]+$/i', $hex_string)) ? (pack('H*', (string) ($hex_string))) : ($hex_string);
}

// Remove [[ ]] (brackets)
function strip_bracket(string $str) : string
{
	$match = [];

	if (preg_match('/^\[\[(.*)\]\]$/', $str, $match)) {
		return $match[1];
	} else {
		return $str;
	}
}

// Create list of pages
function page_list(array $pages, string $cmd = 'read', bool $withfilename = false) : string
{
	global $list_index;
	global $_msg_symbol;
	global $_msg_other;
	global $pagereading_enable;

	$script = get_base_uri();

	// ソートキーを決定する。 ' ' < '[a-zA-Z]' < 'zz'という前提。
	$symbol = ' ';
	$other = 'zz';

	$retval = '';

	if ($pagereading_enable) {
		mb_regex_encoding(SOURCE_ENCODING);
		$readings = get_readings($pages);
	}

	$matches = [];
	$list = [];

	// Shrink URI for read
	if ($cmd == 'read') {
		$href = $script.'?';
	} else {
		$href = $script.'?cmd='.$cmd.'&amp;page=';
	}

	uasort($pages, 'strnatcmp');

	foreach ($pages as $file=>$page) {
		$r_page = pagename_urlencode($page);
		$s_page = htmlsc($page, ENT_QUOTES);
		$str = "\t\t\t".'<li><a href="'.$href.$r_page.'">'.$s_page.'</a> '.get_pg_passage($page);

		if ($withfilename) {
			$s_file = htmlsc($file);
			$str .= "\n\t\t\t\t".'<ul><li>'.$s_file.'</li></ul>'."\n\t\t\t";
		}

		$str .= '</li>';

		// WARNING: Japanese code hard-wired
		if ($pagereading_enable) {
			if (mb_ereg('^([A-Za-z])', mb_convert_kana($page, 'a'), $matches)) {
				$head = strtoupper($matches[1]);
			} elseif ((isset($readings[$page])) && (mb_ereg('^([ァ-ヶ])', $readings[$page], $matches))) { // here
				$head = $matches[1];
			} elseif (mb_ereg('^[ -~]|[^ぁ-ん亜-熙]', $page)) { // and here
				$head = $symbol;
			} else {
				$head = $other;
			}
		} else {
			$head = (preg_match('/^([A-Za-z])/', $page, $matches)) ? (strtoupper($matches[1])) : ((preg_match('/^([ -~])/', $page)) ? ($symbol) : ($other));
		}

		$list[$head][$page] = $str;
	}

	uksort($list, 'strnatcmp');

	$cnt = 0;
	$arr_index = [];
	$retval .= '<ul>'."\n";

	foreach ($list as $head=>$sub_pages) {
		if ($head === $symbol) {
			$head = $_msg_symbol;
		} elseif ($head === $other) {
			$head = $_msg_other;
		}

		if ($list_index) {
			$cnt++;
			$arr_index[] = '<a id="top_'.$cnt.'" href="#head_'.$cnt.'"><strong>'.$head.'</strong></a>';
			$retval .= "\t".'<li><a id="head_'.$cnt.'" href="#top_'.$cnt.'"><strong>'.$head.'</strong></a>'."\n\t\t".'<ul>'."\n";
		}

		$retval .= implode("\n", $sub_pages);

		if ($list_index) {
			$retval .= "\n\t\t".'</ul>'."\n\t".'</li>'."\n";
		}
	}

	$retval .= '</ul>'."\n";

	if (($list_index) && ($cnt > 0)) {
		$top = [];

		while (!empty($arr_index)) {
			$top[] = implode(' | '."\n", array_splice($arr_index, 0, 16))."\n";
		}

		$retval = '<div id="top" style="text-align:center">'."\n".implode('<br />', $top).'</div>'."\n".$retval;
	}

	return $retval;
}

// Show text formatting rules
function catrule() : string
{
	global $rule_page;

	if (!is_page($rule_page)) {
		return '<p>Sorry, page \''.htmlsc($rule_page).'\' unavailable.</p>';
	} else {
		return convert_html(get_source($rule_page));
	}
}

// Show (critical) error message
function die_message(string $msg) : void
{
	$page = 'Runtime error';
	$title = $page;
	$body = <<<EOD
<h3>Runtime error</h3>
<strong>Error message : {$msg}</strong>
EOD;

	pkwk_common_headers();

	if ((defined('SKIN_FILE')) && (file_exists(SKIN_FILE)) && (is_readable(SKIN_FILE))) {
		catbody($title, $page, $body);
	} else {
		$charset = 'utf-8';

		if (defined('CONTENT_CHARSET')) {
			$charset = CONTENT_CHARSET;
		}

		header("Content-Type: text/html; charset={$charset}");
		echo <<<EOD
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset={$charset}">
		<title>{$title}</title>
	</head>
	<body>
{$body}
	</body>
</html>
EOD;
	}

	exit;
}

// Have the time (as microtime)
function getmicrotime() : float
{
	[$usec, $sec] = explode(' ', microtime());

	return ((float) ($sec)) + ((float) ($usec));
}

// Elapsed time by second
//define('MUTIME', getmicrotime());
function elapsedtime() : string
{
	$at_the_microtime = MUTIME;

	return sprintf('%01.03f', getmicrotime() - $at_the_microtime);
}

// Get the date
function get_date(string $format, int $timestamp = null) : string
{
	$format = preg_replace('/(?<!\\\)T/', preg_replace('/(.)/', '\\\$1', ZONE), $format);

	$time = ZONETIME + (($timestamp !== null) ? ($timestamp) : (UTIME));

	return date($format, $time);
}

// Format date string
function format_date(int $val, bool $paren = false) : string
{
	global $date_format;
	global $time_format;
	global $weeklabels;

	$val += ZONETIME;

	$date = date($date_format, $val).' ('.$weeklabels[date('w', $val)].') '.date($time_format, $val);

	return ($paren) ? ('('.$date.')') : ($date);
}

/**
 * Format date in DATE_ATOM format.
 */
function get_date_atom(int $timestamp) : string
{
	// Compatible with DATE_ATOM format
	// return date(DATE_ATOM, $timestamp);
	$zmin = abs(LOCALZONE / 60);

	return date('Y-m-d\TH:i:s', $timestamp).sprintf('%s%02d:%02d', ((LOCALZONE < 0) ? ('-') : ('+')), $zmin / 60, $zmin % 60);
}

// Get short string of the passage, 'N seconds/minutes/hours/days/years ago'
function get_passage(int $time, bool $paren = true) : string
{
	static $units = ['m'=>60, 'h'=>24, 'd'=>1];

	// minutes
	$time = max(0, (UTIME - $time) / 60);

	foreach ($units as $unit=>$card) {
		if ($time < $card) {
			break;
		}

		$time /= $card;
	}

	$time = floor($time).$unit;

	return ($paren) ? ('('.$time.')') : ($time);
}

// Hide <input type="(submit|button|image)"...>
function drop_submit(string $str) : string
{
	return preg_replace('/<input([^>]+)type="(submit|button|image)"/i', '<input$1type="hidden"', $str);
}

// Generate AutoLink patterns (thx to hirofummy)
function get_autolink_pattern(array $pages, int $min_length) : array
{
	global $WikiName;
	global $nowikiname;

	$config = new Config('AutoLink');
	$config->read();
	$ignorepages = $config->get('IgnoreList');
	$forceignorepages = $config->get('ForceIgnoreList');
	unset($config);
	$auto_pages = array_merge($ignorepages, $forceignorepages);

	foreach ($pages as $page) {
		if ((preg_match('/^'.$WikiName.'$/', $page)) ? ($nowikiname) : (strlen($page) >= $min_length)) {
			$auto_pages[] = $page;
		}
	}

	if (empty($auto_pages)) {
		$result_a = '(?!)';
		$result = $result_a;
	} else {
		$auto_pages = array_unique($auto_pages);
		sort($auto_pages, SORT_STRING);

		$auto_pages_a = array_values(preg_grep('/^[A-Z]+$/i', $auto_pages));
		$auto_pages = array_values(array_diff($auto_pages, $auto_pages_a));

		$result = get_autolink_pattern_sub($auto_pages, 0, count($auto_pages), 0);
		$result_a = get_autolink_pattern_sub($auto_pages_a, 0, count($auto_pages_a), 0);
	}

	return [$result, $result_a, $forceignorepages];
}

function get_autolink_pattern_sub(array $pages, int $start, int $end, int $pos) : string
{
	if ($end == 0) {
		return '(?!)';
	}

	$result = '';
	$j = 0;
	$i = 0;
	$count = 0;
	$x = (mb_strlen($pages[$start]) <= $pos);

	if ($x) {
		$start++;
	}

	for ($i = $start; $i < $end; $i = $j) {
		$char = mb_substr($pages[$i], $pos, 1);

		for ($j = $i; $j < $end; $j++) {
			if (mb_substr($pages[$j], $pos, 1) != $char) {
				break;
			}
		}

		if ($i != $start) {
			$result .= '|';
		}

		if ($i >= ($j - 1)) {
			$result .= str_replace(' ', '\\ ', preg_quote(mb_substr($pages[$i], $pos), '/'));
		} else {
			$result .= str_replace(' ', '\\ ', preg_quote($char, '/')).get_autolink_pattern_sub($pages, $i, $j, $pos + 1);
		}

		$count++;
	}

	if (($x) || ($count > 1)) {
		$result = '(?:'.$result.')';
	}

	if ($x) {
		$result .= '?';
	}

	return $result;
}

// Get AutoAlias value
function get_autoalias_right_link(string $alias_name) : string
{
	$pairs = get_autoaliases();

	// A string: Seek the pair
	if (isset($pairs[$alias_name])) {
		return $pairs[$alias_name];
	}

	return '';
}

// Load setting pairs from AutoAliasName
function get_autoaliases() : array
{
	global $aliaspage;
	global $autoalias_max_words;
	static $pairs;

	if (!isset($pairs)) {
		$pairs = [];
		$pattern = <<<'EOD'
\[\[                # open bracket
((?:(?!\]\]).)+)>   # (1) alias name
((?:(?!\]\]).)+)    # (2) alias link
\]\]                # close bracket
EOD;
		$postdata = implode('', get_source($aliaspage));
		$matches = [];
		$count = 0;
		$max = max($autoalias_max_words, 0);

		if (preg_match_all('/'.$pattern.'/x', $postdata, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $key=>$value) {
				if ($count == $max) {
					break;
				}

				$name = trim($value[1]);

				if (!isset($pairs[$name])) {
					$count++;
					$pairs[$name] = trim($value[2]);
				}

				unset($matches[$key]);
			}
		}
	}

	return $pairs;
}

/**
 * Get propery URI of this script.
 *
 * @param $uri_type relative or absolute option
 *        PKWK_URI_RELATIVE, PKWK_URI_ROOT or PKWK_URI_ABSOLUTE
 */
function get_base_uri(int $uri_type = PKWK_URI_RELATIVE) : string
{
	$base_type = pkwk_base_uri_type_stack_peek();
	$type = max($base_type, $uri_type);

	switch ($type) {
		case PKWK_URI_RELATIVE:
			return pkwk_script_uri_base(PKWK_URI_RELATIVE);

		case PKWK_URI_ROOT:
			return pkwk_script_uri_base(PKWK_URI_ROOT);

		case PKWK_URI_ABSOLUTE:
			return pkwk_script_uri_base(PKWK_URI_ABSOLUTE);

		default:
			die_message('Invalid uri_type in get_base_uri()');

			//Not reached
			return '';
	}
}

/**
 * Get URI of the page.
 *
 * @param page page name
 * @param $uri_type relative or absolute option
 *        PKWK_URI_RELATIVE, PKWK_URI_ROOT or PKWK_URI_ABSOLUTE
 */
function get_page_uri(string $page, int $uri_type = PKWK_URI_RELATIVE) : string
{
	global $defaultpage;

	if ($page === $defaultpage) {
		return get_base_uri($uri_type);
	}

	return get_base_uri($uri_type).'?'.pagename_urlencode($page);
}

// Get absolute-URI of this script
function get_script_uri() : string
{
	return get_base_uri(PKWK_URI_ABSOLUTE);
}

/**
 * Get or initialize Script URI.
 *
 * @param $uri_type relative or absolute potion
 *        PKWK_URI_RELATIVE, PKWK_URI_ROOT or PKWK_URI_ABSOLUTE
 * @param $initialize true if you initialize URI
 * @param $uri_set URI set manually
 */
function pkwk_script_uri_base(int $uri_type, bool $initialize = null, string $uri_set = null) : string
{
	global $script_directory_index;
	static $initialized = false;
	static $uri_absolute;
	static $uri_root;
	static $uri_relative;

	if (!$initialized) {
		if ((isset($initialize)) && ($initialize)) {
			if (isset($uri_set)) {
				$uri_absolute = $uri_set;
			} else {
				$uri_absolute = guess_script_absolute_uri();
			}

			// Support $script_directory_index (cut 'index.php')
			if (isset($script_directory_index)) {
				$slash_index = '/'.$script_directory_index;
				$len = strlen($slash_index);

				if (substr($uri_absolute, -1 * $len) === $slash_index) {
					$uri_absolute = substr($uri_absolute, 0, strlen($uri_absolute) - $len + 1);
				}
			}

			$elements = parse_url($uri_absolute);
			$uri_root = $elements['path'];

			if (substr($uri_root, -1) === '/') {
				$uri_relative = './';
			} else {
				$pos = mb_strrpos($uri_root, '/');

				if ($pos >= 0) {
					$uri_relative = substr($uri_root, $pos + 1);
				} else {
					$uri_relative = $uri_root;
				}
			}

			$initialized = true;
		} else {
			die_message('Script URI must be initialized in pkwk_script_uri_base()');
		}
	}

	switch ($uri_type) {
		case PKWK_URI_RELATIVE:
			return $uri_relative;

		case PKWK_URI_ROOT:
			return $uri_root;

		case PKWK_URI_ABSOLUTE:
			return $uri_absolute;

		default:
			die_message('Invalid uri_type in pkwk_script_uri_base()');

			//Not reached
			return '';
	}
}

/**
 * Create uri_type context.
 *
 * @param $uri_type relative or absolute option
 *        PKWK_URI_RELATIVE, PKWK_URI_ROOT or PKWK_URI_ABSOLUTE
 */
function pkwk_base_uri_type_stack_push(int $uri_type) : void
{
	_pkwk_base_uri_type_stack(false, true, $uri_type);
}

/**
 * Stop current active uri_type context.
 */
function pkwk_base_uri_type_stack_pop() : void
{
	_pkwk_base_uri_type_stack(false, false);
}

/**
 * Get current active uri_type status.
 */
function pkwk_base_uri_type_stack_peek() : int
{
	$type = _pkwk_base_uri_type_stack(true, false);

	if ($type === null) {
		return PKWK_URI_RELATIVE;
	} elseif ($type === PKWK_URI_ABSOLUTE) {
		return PKWK_URI_ABSOLUTE;
	} elseif ($type === PKWK_URI_ROOT) {
		return PKWK_URI_ROOT;
	} else {
		return PKWK_URI_RELATIVE;
	}
}

/**
 * uri_type context internal function.
 *
 * @param $peek is peek action or not
 * @param $push push(true) or pop(false) on not peeking
 * @param $uri_type uri_type on push and non-peeking
 *
 * @return $uri_type uri_type for peeking
 */
function _pkwk_base_uri_type_stack(bool $peek, bool $push, int $uri_type = null)
{
	static $uri_types = [];

	if ($peek) {
		// Peek: get latest value
		if (count($uri_types) === 0) {
			return;
		} else {
			return $uri_types[0];
		}
	} else {
		if ($push) {
			// Push $uri_type
			if (count($uri_types) === 0) {
				array_unshift($uri_types, $uri_type);
			} else {
				$prev_type = $uri_types[0];

				if ($uri_type >= $prev_type) {
					array_unshift($uri_types, $uri_type);
				} else {
					array_unshift($uri_types, $prev_type);
				}
			}
		} else {
			// Pop $uri_type
			return array_shift($uri_types);
		}
	}
}

/**
 * Guess Script Absolute URI.
 *
 * SERVER_PORT: $_SERVER['SERVER_PORT'] converted in init.php
 * SERVER_NAME: $_SERVER['SERVER_NAME'] converted in init.php
 */
function guess_script_absolute_uri() : string
{
	$port = SERVER_PORT;
	$is_ssl = ((isset($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] === 'on')) || ((isset($_SERVER['REQUEST_SCHEME'])) && ($_SERVER['REQUEST_SCHEME'] === 'https'));

	if ($is_ssl) {
		$host = 'https://'.SERVER_NAME.(($port == 443) ? ('') : (':'.$port));
	} else {
		$host = 'http://'.SERVER_NAME.(($port == 80) ? ('') : (':'.$port));
	}

	$uri_elements = parse_url($host.$_SERVER['REQUEST_URI']);

	return $host.$uri_elements['path'];
}

// Remove null(\0) bytes from variables
//
// NOTE: PHP had vulnerabilities that opens "hoge.php" via fopen("hoge.php\0.txt") etc.
// [PHP-users 12736] null byte attack
// http://ns1.php.gr.jp/pipermail/php-users/2003-January/012742.html
//
// 2003-05-16: magic quotes gpcの復元処理を統合
// 2003-05-21: 連想配列のキーはbinary safe
//
function input_filter($param)
{
	if (is_array($param)) {
		return array_map('input_filter', $param);
	} else {
		return str_replace("\0", '', $param);
	}
}

// Compat for 3rd party plugins. Remove this later
function sanitize($param)
{
	return input_filter($param);
}

// Explode Comma-Separated Values to an array
function csv_explode(string $separator, string $string) : array
{
	$matches = [];
	$retval = [];

	$_separator = preg_quote($separator, '/');

	if (!preg_match_all('/("[^"]*(?:""[^"]*)*"|[^'.$_separator.']*)'.$_separator.'/', $string.$separator, $matches)) {
		return [];
	}

	foreach ($matches[1] as $str) {
		$len = strlen($str);

		if (($len > 1) && ($str[0] == '"') && ($str[$len - 1] == '"')) {
			$str = str_replace('""', '"', substr($str, 1, -1));
		}

		$retval[] = $str;
	}

	return $retval;
}

// Implode an array with CSV data format (escape double quotes)
function csv_implode(string $glue, array $pieces) : string
{
	$_glue = ($glue != '') ? ('\\'.$glue[0]) : ('');
	$arr = [];

	foreach ($pieces as $str) {
		if (preg_match('/["'."\n\r".$_glue.']/', $str)) {
			$str = '"'.str_replace('"', '""', $str).'"';
		}

		$arr[] = $str;
	}

	return implode($glue, $arr);
}

// Sugar with default settings
function htmlsc(string $string = '', int $flags = ENT_COMPAT, string $charset = CONTENT_CHARSET) : string
{
	// htmlsc()
	return htmlspecialchars($string, $flags, $charset);
}

/**
 * Get JSON string with htmlspecialchars().
 */
function htmlsc_json($obj) : string
{
	// json_encode: PHP 5.2+
	// JSON_UNESCAPED_UNICODE: PHP 5.4+
	// JSON_UNESCAPED_SLASHES: PHP 5.4+
	if (defined('JSON_UNESCAPED_UNICODE')) {
		return htmlsc(json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
	}

	return '';
}

/**
 * Get redirect page name on Page Redirect Rules.
 *
 * This function returns exactly false if it doesn't need redirection.
 * So callers need check return value is false or not.
 *
 * @param $page page name
 *
 * @return new page name or false
 */
function get_pagename_on_redirect(string $page)
{
	global $page_redirect_rules;

	foreach ($page_redirect_rules as $rule=>$replace) {
		if (preg_match($rule, $page)) {
			if (is_string($replace)) {
				$new_page = preg_replace($rule, $replace, $page);
			} elseif ((is_object($replace)) && (is_callable($replace))) {
				$new_page = preg_replace_callback($rule, $replace, $page);
			} else {
				die_message('Invalid redirect rule: '.$rule.'=>'.$replace);
			}

			if ($page !== $new_page) {
				return $new_page;
			}
		}
	}

	return false;
}

/**
 * Redirect from an old page to new page.
 *
 * This function returns true when a redirection occurs.
 * So callers need check return value is false or true.
 * And if it is true, then you have to exit PHP script.
 *
 * @return bool Inticates a redirection occurred or not
 */
function manage_page_redirect() : bool
{
	global $vars;

	if (isset($vars['page'])) {
		$page = $vars['page'];
	}

	$new_page = get_pagename_on_redirect($page);

	if ($new_page != false) {
		header('Location: '.get_page_uri($new_page, PKWK_URI_ROOT));

		return true;
	}

	return false;
}

//// Compat ////

// is_a --  Returns true if the object is of this class or has this class as one of its parents
// (PHP 4 >= 4.2.0)
if (!function_exists('is_a')) {
	function is_a(string $class, string $match) : bool
	{
		if (empty($class)) {
			return false;
		}

		$class = (is_object($class)) ? (get_class($class)) : ($class);

		if (strtolower($class) == strtolower($match)) {
			return true;
		} else {
			// Recurse
			return is_a(get_parent_class($class), $match);
		}
	}
}

// array_fill -- Fill an array with values
// (PHP 4 >= 4.2.0)
if (!function_exists('array_fill')) {
	function array_fill(int $start_index, int $num, $value) : array
	{
		$ret = [];

		while ($num-- > 0) {
			$ret[$start_index++] = $value;
		}

		return $ret;
	}
}

// md5_file -- Calculates the md5 hash of a given filename
// (PHP 4 >= 4.2.0)
if (!function_exists('md5_file')) {
	function md5_file(string $filename)
	{
		if (!file_exists($filename)) {
			return false;
		}

		$fd = fopen($filename, 'rb');

		if ($fd === false) {
			return false;
		}

		$data = fread($fd, filesize($filename));
		fclose($fd);

		return md5($data);
	}
}

// sha1 -- Compute SHA-1 hash
// (PHP 4 >= 4.3.0, PHP5)
if (!function_exists('sha1')) {
	if (extension_loaded('mhash')) {
		function sha1(string $str) : string
		{
			return bin2hex(mhash(MHASH_SHA1, $str));
		}
	}
}
