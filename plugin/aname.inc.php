<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// aname.inc.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// aname plugin - Set various anchor tags
//   * With just an anchor id: <a id="key"></a>
//   * With a hyperlink to the anchor id: <a href="#key">string</a>
//   * With an anchor id and a link to the id itself: <a id="key" href="#key">string</a>
//
// NOTE: Use 'id="key"' instead of 'name="key"' at XHTML 1.1

// Check ID is unique or not (compatible: no-check)
define('PLUGIN_ANAME_ID_MUST_UNIQUE', 0);

// Max length of ID
define('PLUGIN_ANAME_ID_MAX', 40);

// Pattern of ID
define('PLUGIN_ANAME_ID_REGEX', '/^[A-Za-z][\w\-]*$/');

// Show usage
function plugin_aname_usage(bool $convert = true, string $message = '') : string
{
	if ($convert) {
		if ($message == '') {
			return '#aname(anchorID[[,super][,full][,noid],Link title])<br />';
		} else {
			return '#aname: '.$message.'<br />';
		}
	} else {
		if ($message == '') {
			return '&amp;aname(anchorID[,super][,full][,noid][,nouserselect]){[Link title]};';
		} else {
			return '&amp;aname: '.$message.';';
		}
	}
}

// #aname
function plugin_aname_convert(string ...$args) : string
{
	$convert = true;

	if (func_num_args() < 1) {
		return plugin_aname_usage($convert);
	}

	return plugin_aname_tag($args, $convert);
}

// &aname;
function plugin_aname_inline(string ...$args) : string
{
	$convert = false;

	// ONE or more
	if (func_num_args() < 2) {
		return plugin_aname_usage($convert);
	}

	// Strip anchor tags only
	$body = strip_htmltag(array_pop($args), false);

	array_push($args, $body);

	return plugin_aname_tag($args, $convert);
}

// Aname plugin itself
function plugin_aname_tag(array $args = [], bool $convert = true) : string
{
	global $vars;
	static $_id = [];

	if ((empty($args)) || ($args[0] == '')) {
		return plugin_aname_usage($convert);
	}

	$id = array_shift($args);
	$body = '';

	if (!empty($args)) {
		$body = array_pop($args);
	}

	// Option: Without id attribute
	$f_noid = in_array('noid', $args, true);

	// Option: CSS class
	$f_super = in_array('super', $args, true);

	// Option: With full(absolute) URI
	$f_full = in_array('full', $args, true);

	// Option: user-select:none;
	$f_nouserselect = in_array('nouserselect', $args, true);

	if ($body == '') {
		if ($f_noid) {
			return plugin_aname_usage($convert, 'Meaningless(No link-title with \'noid\')');
		}

		if ($f_super) {
			return plugin_aname_usage($convert, 'Meaningless(No link-title with \'super\')');
		}

		if ($f_full) {
			return plugin_aname_usage($convert, 'Meaningless(No link-title with \'full\')');
		}
	}

	if ((PLUGIN_ANAME_ID_MUST_UNIQUE) && (isset($_id[$id])) && (!$f_noid)) {
		return plugin_aname_usage($convert, 'ID already used: '.$id);
	} else {
		if (strlen($id) > PLUGIN_ANAME_ID_MAX) {
			return plugin_aname_usage($convert, 'ID too long');
		}

		if (!preg_match(PLUGIN_ANAME_ID_REGEX, $id)) {
			return plugin_aname_usage($convert, 'Invalid ID string: '.htmlspecialchars($id, ENT_COMPAT, 'UTF-8'));
		}

		// Set
		$_id[$id] = true;
	}

	if ($convert) {
		$body = htmlspecialchars($body, ENT_COMPAT, 'UTF-8');
	}

	// Insurance
	$id = htmlspecialchars($id, ENT_COMPAT, 'UTF-8');

	$class = ($f_super) ? ('anchor_super') : ('anchor');
	$attr_id = ($f_noid) ? ('') : (' id="'.$id.'"');
	$url = ($f_full) ? (get_page_uri($vars['page'])) : ('');
	$astyle = '';

	if ($body != '') {
		$href = ' href="'.$url.'#'.$id.'"';
		$title = ' title="'.$id.'"';

		if ($f_nouserselect) {
			$astyle = ' style="user-select:none;"';
		}
	} else {
		$title = '';
		$href = '';
	}

	return '<a class="'.$class.'"'.$attr_id.$href.$title.$astyle.'>'.$body.'</a>';
}
