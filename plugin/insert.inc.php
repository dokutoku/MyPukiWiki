<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// insert.inc.php
// Copyright 2002-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Text inserting box plugin

// Columns of textarea
define('INSERT_COLS', 70);

// Rows of textarea
define('INSERT_ROWS', 5);

// Order of insertion (1:before the textarea, 0:after)
define('INSERT_INS', 1);

function plugin_insert_action() : array
{
	global $vars;
	global $cols;
	global $rows;
	global $_title_collided;
	global $_msg_collided;
	global $_title_updated;

	$script = get_base_uri();

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	if ((!isset($vars['msg'])) || ($vars['msg'] == '')) {
		return [];
	}

	$vars['msg'] = preg_replace('/'."\r".'/', '', $vars['msg']);
	$insert = ($vars['msg'] != '') ? ("\n".$vars['msg']."\n") : ('');

	$postdata = '';
	$postdata_old = get_source($vars['refer']);
	$insert_no = 0;

	foreach ($postdata_old as $line) {
		if (!INSERT_INS) {
			$postdata .= $line;
		}

		if (preg_match('/^#insert$/i', $line)) {
			if ($insert_no == $vars['insert_no']) {
				$postdata .= $insert;
			}

			$insert_no++;
		}

		if (INSERT_INS) {
			$postdata .= $line;
		}
	}

	$postdata_input = $insert."\n";

	$body = '';

	if (md5(get_source($vars['refer'], true, true)) !== $vars['digest']) {
		$title = $_title_collided;
		$body = $_msg_collided."\n";

		$s_refer = htmlsc($vars['refer']);
		$s_digest = htmlsc($vars['digest']);
		$s_postdata_input = htmlsc($postdata_input);
		$s_postdata_input = str_replace("\n", '&NewLine;', $s_postdata_input);

		$body .= <<<EOD
<form action="{$script}?cmd=preview" method="post">
	<div>
		<input type="hidden" name="refer" value="{$s_refer}" />
		<input type="hidden" name="digest" value="{$s_digest}" />
		<textarea name="msg" rows="{$rows}" cols="{$cols}" id="textarea">{$s_postdata_input}</textarea><br />
	</div>
</form>
EOD;
	} else {
		page_write($vars['refer'], $postdata);

		$title = $_title_updated;
	}

	$retvars['msg'] = $title;
	$retvars['body'] = $body;

	$vars['page'] = $vars['refer'];

	return $retvars;
}

function plugin_insert_convert() : string
{
	global $vars;
	global $digest;
	global $_btn_insert;
	static $numbers = [];

	$script = get_base_uri();

	if (PKWK_READONLY) {
		// Show nothing
		return '';
	}

	if (!isset($numbers[$vars['page']])) {
		$numbers[$vars['page']] = 0;
	}

	$insert_no = $numbers[$vars['page']]++;

	$s_page = htmlsc($vars['page']);
	$s_digest = htmlsc($digest);
	$s_cols = INSERT_COLS;
	$s_rows = INSERT_ROWS;
	$string = <<<EOD
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="insert_no" value="{$insert_no}" />
		<input type="hidden" name="refer" value="{$s_page}" />
		<input type="hidden" name="plugin" value="insert" />
		<input type="hidden" name="digest" value="{$s_digest}" />
		<textarea name="msg" rows="{$s_rows}" cols="{$s_cols}"></textarea><br />
		<input type="submit" name="insert" value="{$_btn_insert}" />
	</div>
</form>
EOD;

	return $string;
}
