<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// rename.inc.php
// Copyright 2002-2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Rename plugin: Rename page-name and related data
//
// Usage: http://path/to/index.php?plugin=rename[&refer=page_name]

define('PLUGIN_RENAME_LOGPAGE', ':RenameLog');

function plugin_rename_action() : array
{
	global $whatsnew;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits this');
	}

	$method = plugin_rename_getvar('method');

	if ($method == 'regex') {
		$src = plugin_rename_getvar('src');

		if ($src == '') {
			return plugin_rename_phase1();
		}

		$src_pattern = '/'.preg_quote($src, '/').'/';
		$arr0 = preg_grep($src_pattern, plugin_rename_get_existpages());

		if ((!is_array($arr0)) || (empty($arr0))) {
			return plugin_rename_phase1('nomatch');
		}

		$dst = plugin_rename_getvar('dst');
		$arr1 = preg_replace($src_pattern, $dst, $arr0);

		foreach ($arr1 as $page) {
			if (!is_pagename($page)) {
				return plugin_rename_phase1('notvalid');
			}
		}

		return plugin_rename_regex($arr0, $arr1);
	} else {
		// $method == 'page'
		$page = plugin_rename_getvar('page');
		$refer = plugin_rename_getvar('refer');

		if ($refer == '') {
			return plugin_rename_phase1();
		} elseif (!plugin_rename_is_page($refer)) {
			return plugin_rename_phase1('notpage', $refer);
		} elseif ($refer === $whatsnew) {
			return plugin_rename_phase1('norename', $refer);
		} elseif (($page == '') || ($page === $refer)) {
			return plugin_rename_phase2();
		} elseif (!is_pagename($page)) {
			return plugin_rename_phase2('notvalid');
		} else {
			return plugin_rename_refer();
		}
	}
}

// 変数を取得する
function plugin_rename_getvar($key) : string
{
	global $vars;

	return (isset($vars[$key])) ? ($vars[$key]) : ('');
}

// エラーメッセージを作る
function plugin_rename_err(string $err, string $page = '') : string
{
	global $_rename_messages;

	if ($err == '') {
		return '';
	}

	$body = $_rename_messages['err_'.$err];

	if (is_array($page)) {
		$tmp = '';

		foreach ($page as $_page) {
			$tmp .= '<br />'.$_page;
		}

		$page = $tmp;
	}

	if ($page != '') {
		$body = sprintf($body, htmlsc($page));
	}

	$msg = sprintf($_rename_messages['err'], $body);

	return $msg;
}

//第一段階:ページ名または正規表現の入力
function plugin_rename_phase1(string $err = '', string $page = '') : array
{
	global $_rename_messages;

	$script = get_base_uri();
	$msg = plugin_rename_err($err, $page);
	$refer = plugin_rename_getvar('refer');
	$method = plugin_rename_getvar('method');

	$radio_page = '';
	$radio_regex = '';

	if ($method == 'regex') {
		$radio_regex = ' checked="checked"';
	} else {
		$radio_page = ' checked="checked"';
	}

	$select_refer = plugin_rename_getselecttag($refer);

	$s_src = htmlsc(plugin_rename_getvar('src'));
	$s_dst = htmlsc(plugin_rename_getvar('dst'));

	$ret = [];
	$ret['msg'] = $_rename_messages['msg_title'];
	$ret['body'] = <<<EOD
{$msg}
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="rename" />
		<input type="radio" name="method" id="_p_rename_page" value="page"{$radio_page} />
		<label for="_p_rename_page">{$_rename_messages['msg_page']}:</label>{$select_refer}<br />
		<input type="radio" name="method" id="_p_rename_regex" value="regex"{$radio_regex} />
		<label for="_p_rename_regex">{$_rename_messages['msg_regex']}:</label><br />
		<label for="_p_rename_from">From:</label><br />
		<input type="text" name="src" id="_p_rename_from" size="80" value="{$s_src}" /><br />
		<label for="_p_rename_to">To:</label><br />
		<input type="text" name="dst" id="_p_rename_to" size="80" value="{$s_dst}" /><br />
		<input type="submit" value="{$_rename_messages['btn_next']}" /><br />
	</div>
</form>
EOD;

	return $ret;
}

//第二段階:新しい名前の入力
function plugin_rename_phase2(string $err = '') : array
{
	global $_rename_messages;

	$script = get_base_uri();
	$msg = plugin_rename_err($err);
	$page = plugin_rename_getvar('page');
	$refer = plugin_rename_getvar('refer');

	if ($page == '') {
		$page = $refer;
	}

	$msg_related = '';
	$related = plugin_rename_getrelated($refer);

	if (!empty($related)) {
		$msg_related = '<label for="_p_rename_related">'.$_rename_messages['msg_do_related'].'</label><input type="checkbox" name="related" id="_p_rename_related" value="1" checked="checked" /><br />';
	}

	$msg_rename = sprintf($_rename_messages['msg_rename'], make_pagelink($refer));
	$s_page = htmlsc($page);
	$s_refer = htmlsc($refer);

	$ret = [];
	$ret['msg'] = $_rename_messages['msg_title'];
	$ret['body'] = <<<EOD
{$msg}
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="rename" />
		<input type="hidden" name="refer" value="{$s_refer}" />
		{$msg_rename}<br />
		<label for="_p_rename_newname">{$_rename_messages['msg_newname']}:</label>
		<input type="text" name="page" id="_p_rename_newname" size="80" value="{$s_page}" /><br />
		{$msg_related}
		<input type="submit" value="{$_rename_messages['btn_next']}" /><br />
	</div>
</form>
EOD;

	if (!empty($related)) {
		$ret['body'] .= "\n".'<hr />'."\n".'<p>'.$_rename_messages['msg_related'].'</p>'."\n".'<ul>'."\n";
		sort($related, SORT_STRING);

		foreach ($related as $name) {
			$ret['body'] .= "\t".'<li>'.make_pagelink($name).'</li>'."\n";
		}

		$ret['body'] .= '</ul>';
	}

	return $ret;
}

//ページ名と関連するページを列挙し、phase3へ
function plugin_rename_refer() : array
{
	$page = plugin_rename_getvar('page');
	$refer = plugin_rename_getvar('refer');

	$pages[encode($refer)] = encode($page);

	if (plugin_rename_getvar('related') != '') {
		$from = strip_bracket($refer);
		$to = strip_bracket($page);

		foreach (plugin_rename_getrelated($refer) as $_page) {
			$pages[encode($_page)] = encode(str_replace($from, $to, $_page));
		}
	}

	return plugin_rename_phase3($pages);
}

//正規表現でページを置換
function plugin_rename_regex(array $arr_from, array $arr_to) : array
{
	$exists = [];

	foreach ($arr_to as $page) {
		if (is_page($page)) {
			$exists[] = $page;
		}
	}

	if (!empty($exists)) {
		return plugin_rename_phase1('already', $exists);
	} else {
		$pages = [];

		foreach ($arr_from as $refer) {
			$pages[encode($refer)] = encode(array_shift($arr_to));
		}

		return plugin_rename_phase3($pages);
	}
}

function plugin_rename_phase3(array $pages) : array
{
	global $_rename_messages;

	$script = get_base_uri();
	$input = '';
	$msg = '';
	$files = plugin_rename_get_files($pages);

	$exists = [];

	foreach ($files as $_page=>$arr) {
		foreach ($arr as $old=>$new) {
			if (file_exists($new)) {
				$exists[$_page][$old] = $new;
			}
		}
	}

	$pass = plugin_rename_getvar('pass');

	if (($pass != '') && (pkwk_login($pass))) {
		plugin_rename_proceed($pages, $files, $exists);
	} elseif ($pass != '') {
		$msg = plugin_rename_err('adminpass')."\n";
	}

	$method = plugin_rename_getvar('method');

	if ($method == 'regex') {
		$s_src = htmlsc(plugin_rename_getvar('src'));
		$s_dst = htmlsc(plugin_rename_getvar('dst'));
		$msg .= $_rename_messages['msg_regex'].'<br />'."\n";
		$input .= '<input type="hidden" name="method" value="regex" />'."\n";
		$input .= '<input type="hidden" name="src" value="'.$s_src.'" />'."\n";
		$input .= '<input type="hidden" name="dst" value="'.$s_dst.'" />'."\n";
	} else {
		$s_refer = htmlsc(plugin_rename_getvar('refer'));
		$s_page = htmlsc(plugin_rename_getvar('page'));
		$s_related = htmlsc(plugin_rename_getvar('related'));
		$msg .= $_rename_messages['msg_page'].'<br />'."\n";
		$input .= '<input type="hidden" name="method" value="page" />'."\n";
		$input .= '<input type="hidden" name="refer" value="'.$s_refer.'" />'."\n";
		$input .= '<input type="hidden" name="page" value="'.$s_page.'" />'."\n";
		$input .= '<input type="hidden" name="related" value="'.$s_related.'" />'."\n";
	}

	if (!empty($exists)) {
		$msg .= $_rename_messages['err_already_below'].'<ul>'."\n";

		foreach ($exists as $page=>$arr) {
			$msg .= "\t".'<li>'.make_pagelink(decode($page));
			$msg .= $_rename_messages['msg_arrow'];
			$msg .= htmlsc(decode($pages[$page]));

			if (!empty($arr)) {
				$msg .= "\n\t\t".'<ul>'."\n";

				foreach ($arr as $ofile=>$nfile) {
					$msg .= "\t\t\t".'<li>'.$ofile.$_rename_messages['msg_arrow'].$nfile.'</li>'."\n";
				}

				$msg .= "\t\t".'</ul>'."\n";
			}

			$msg .= '</li>'."\n";
		}

		$msg .= '</ul>'."\n".'<hr />'."\n";

		$input .= '<input type="radio" name="exist" value="0" checked="checked" />'.$_rename_messages['msg_exist_none'].'<br />'."\n";
		$input .= '<input type="radio" name="exist" value="1" />'.$_rename_messages['msg_exist_overwrite'].'<br />'."\n";
	}

	$ret = [];
	$ret['msg'] = $_rename_messages['msg_title'];
	$ret['body'] = '<p>'.((!empty($msg)) ? ("\n\t".$msg) : ('')).'</p>';
	$ret['body'] .= <<<EOD
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="plugin" value="rename" />
		{$input}
		<label for="_p_rename_adminpass">{$_rename_messages['msg_adminpass']}</label>
		<input type="password" name="pass" id="_p_rename_adminpass" value="" />
		<input type="submit" value="{$_rename_messages['btn_submit']}" />
	</div>
</form>
<p>{$_rename_messages['msg_confirm']}</p>
EOD;

	ksort($pages, SORT_STRING);
	$ret['body'] .= '<ul>'."\n";

	foreach ($pages as $old=>$new) {
		$ret['body'] .= "\t".'<li>'.make_pagelink(decode($old)).$_rename_messages['msg_arrow'].htmlsc(decode($new)).'</li>'."\n";
	}

	$ret['body'] .= '</ul>'."\n";

	return $ret;
}

function plugin_rename_get_files(array $pages) : array
{
	$files = [];
	$dirs = [BACKUP_DIR, DIFF_DIR, DATA_DIR];

	if (exist_plugin_convert('attach')) {
		$dirs[] = UPLOAD_DIR;
	}

	if (exist_plugin_convert('counter')) {
		$dirs[] = COUNTER_DIR;
	}

	// and more ...

	$matches = [];

	foreach ($dirs as $path) {
		$dir = opendir($path);

		if (!$dir) {
			// TODO: !== false or die()?
			continue;
		}

		while (($file = readdir($dir)) !== false) {
			if (($file == '.') || ($file == '..')) {
				continue;
			}

			foreach ($pages as $from=>$to) {
				// TODO: preg_quote()?
				$pattern = '/^'.str_replace('/', '\/', $from).'([._].+)$/';

				if (preg_match($pattern, $file, $matches)) {
					$newfile = $to.$matches[1];
					$files[$from][$path.$file] = $path.$newfile;
				}
			}
		}
	}

	return $files;
}

function plugin_rename_proceed(array $pages, array $files, array $exists) : void
{
	global $now;
	global $_rename_messages;

	if (plugin_rename_getvar('exist') == '') {
		foreach ($exists as $key=>$arr) {
			unset($files[$key]);
		}
	}

	set_time_limit(0);

	foreach ($files as $page=>$arr) {
		foreach ($arr as $old=>$new) {
			if ((isset($exists[$page][$old])) && ($exists[$page][$old])) {
				unlink($new);
			}

			rename($old, $new);
		}

		// linkデータベースを更新する BugTrack/327 arino
		$new_page = $pages[$page];
		links_update(decode($page));
		links_update(decode($new_page));
	}

	// Rename counter
	$pages_decoded = [];

	foreach ($pages as $old=>$new) {
		$pages_decoded[decode($old)] = decode($new);
	}

	if (exist_plugin('counter')) {
		plugin_counter_page_rename($pages_decoded);
	}

	$postdata = get_source(PLUGIN_RENAME_LOGPAGE);
	$postdata[] = '*'.$now."\n";

	if (plugin_rename_getvar('method') == 'regex') {
		$postdata[] = '-'.$_rename_messages['msg_regex']."\n";
		$postdata[] = '--From:[['.plugin_rename_getvar('src').']]'."\n";
		$postdata[] = '--To:[['.plugin_rename_getvar('dst').']]'."\n";
	} else {
		$postdata[] = '-'.$_rename_messages['msg_page']."\n";
		$postdata[] = '--From:[['.plugin_rename_getvar('refer').']]'."\n";
		$postdata[] = '--To:[['.plugin_rename_getvar('page').']]'."\n";
	}

	if (!empty($exists)) {
		$postdata[] = "\n".$_rename_messages['msg_result']."\n";

		foreach ($exists as $page=>$arr) {
			$postdata[] = '-'.decode($page).$_rename_messages['msg_arrow'].decode($pages[$page])."\n";

			foreach ($arr as $ofile=>$nfile) {
				$postdata[] = '--'.$ofile.$_rename_messages['msg_arrow'].$nfile."\n";
			}
		}

		$postdata[] = '----'."\n";
	}

	foreach ($pages as $old=>$new) {
		$postdata[] = '-'.decode($old).$_rename_messages['msg_arrow'].decode($new)."\n";
	}

	// 更新の衝突はチェックしない。

	// ファイルの書き込み
	page_write(PLUGIN_RENAME_LOGPAGE, implode('', $postdata));

	// Refresh RecentChanges / Delete cache/recent.dat
	delete_recent_changes_cache();

	//リダイレクト
	$page = plugin_rename_getvar('page');

	if ($page == '') {
		$page = PLUGIN_RENAME_LOGPAGE;
	}

	pkwk_headers_sent();
	header('Location: '.get_page_uri($page, PKWK_URI_ROOT));

	exit;
}

function plugin_rename_getrelated(string $page) : array
{
	$related = [];
	$pages = plugin_rename_get_existpages();
	$pattern = '/(?:^|\/)'.preg_quote(strip_bracket($page), '/').'(?:\/|$)/';

	foreach ($pages as $name) {
		if ($name === $page) {
			continue;
		}

		if (preg_match($pattern, $name)) {
			$related[] = $name;
		}
	}

	return $related;
}

function plugin_rename_getselecttag(string $page) : string
{
	global $whatsnew;

	$pages = [];

	foreach (plugin_rename_get_existpages() as $_page) {
		if ($_page === $whatsnew) {
			continue;
		}

		$selected = ($_page === $page) ? (' selected') : ('');
		$s_page = htmlsc($_page);
		$pages[$_page] = '<option value="'.$s_page.'"'.$selected.'>'.$s_page.'</option>';
	}

	ksort($pages, SORT_STRING);
	$list = implode("\n\t", $pages);

	return <<<EOD
<select name="refer">
	<option value=""></option>
	{$list}
</select>
EOD;
}

/**
 * List exist pages and deleted pages.
 */
function plugin_rename_get_existpages() : array
{
	$list1 = array_values(get_existpages());
	$list2 = array_values(get_existpages(DIFF_DIR, '.txt'));
	$list3 = array_values(get_existpages(BACKUP_DIR, '.txt'));
	$list4 = array_values(get_existpages(BACKUP_DIR, '.gz'));
	$list5 = array_values(get_existpages(COUNTER_DIR, '.count'));
	$wholelist = array_merge($list1, $list2, $list3, $list4, $list5);

	return array_unique($wholelist);
}

/**
 * Return where the page exists or existed.
 */
function plugin_rename_is_page(string $page) : bool
{
	$enc = encode($page);

	if (is_page($page)) {
		return true;
	}

	if (file_exists(DIFF_DIR.$enc.'.txt')) {
		return true;
	}

	if (file_exists(BACKUP_DIR.$enc.'.txt')) {
		return true;
	}

	if (file_exists(BACKUP_DIR.$enc.'.gz')) {
		return true;
	}

	if (file_exists(COUNTER_DIR.$enc.'.count')) {
		return true;
	}

	return false;
}

/**
 * Setup initial pages (:RenameLog).
 */
function plugin_rename_setup_initial_pages() : void
{
	if (!is_page(PLUGIN_RENAME_LOGPAGE)) {
		// Create :RenameLog
		$body = "#freeze\n// :RenameLog (rename plugin)\n";
		page_write(PLUGIN_RENAME_LOGPAGE, $body);
	}
}
