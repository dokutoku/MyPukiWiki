<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// memo.inc.php
// Copyright 2002-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Memo box plugin

// Columns of textarea
define('MEMO_COLS', 60);

// Rows of textarea
define('MEMO_ROWS', 5);

function plugin_memo_action() : array
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

	$memo_body = preg_replace('/'."\r".'/', '', $vars['msg']);
	$memo_body = str_replace("\n", '\n', $memo_body);

	// Escape double quotes
	$memo_body = str_replace('"', '&#x22;', $memo_body);

	// Escape commas
	$memo_body = str_replace(',', '&#x2c;', $memo_body);

	$postdata_old = get_source($vars['refer']);
	$postdata = '';
	$memo_no = 0;

	foreach ($postdata_old as $line) {
		if (preg_match('/^#memo\\(?.*\\)?$/i', $line)) {
			if ($memo_no == $vars['memo_no']) {
				$postdata .= '#memo('.$memo_body.')'."\n";
				$line = '';
			}

			$memo_no++;
		}

		$postdata .= $line;
	}

	$postdata_input = $memo_body."\n";

	$body = '';

	if (md5(get_source($vars['refer'], true, true)) !== $vars['digest']) {
		$title = $_title_collided;
		$body = $_msg_collided."\n";

		$s_refer = htmlspecialchars($vars['refer'], ENT_COMPAT, 'UTF-8');
		$s_digest = htmlspecialchars($vars['digest'], ENT_COMPAT, 'UTF-8');
		$s_postdata_input = htmlspecialchars($postdata_input, ENT_COMPAT, 'UTF-8');
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

	$retvars['msg'] = &$title;
	$retvars['body'] = &$body;

	$vars['page'] = $vars['refer'];

	return $retvars;
}

function plugin_memo_convert(string ...$data) : string
{
	global $vars;
	global $digest;
	global $_btn_memo_update;
	static $numbers = [];

	if (!isset($numbers[$vars['page']])) {
		$numbers[$vars['page']] = 0;
	}

	$memo_no = $numbers[$vars['page']]++;

	// Care all arguments
	$data = implode(',', $data);

	// Unescape commas
	$data = str_replace('&#x2c;', ',', $data);

	// Unescape double quotes
	$data = str_replace('&#x22;', '"', $data);

	$data = htmlspecialchars(str_replace('\n', "\n", $data), ENT_COMPAT, 'UTF-8');

	if (PKWK_READONLY) {
		$_script = '';
		$_submit = '';
	} else {
		$_script = get_base_uri();
		$_submit = '<input type="submit" name="memo" value="'.$_btn_memo_update.'" />';
	}

	$s_page = htmlspecialchars($vars['page'], ENT_COMPAT, 'UTF-8');
	$s_digest = htmlspecialchars($digest, ENT_COMPAT, 'UTF-8');
	$s_cols = MEMO_COLS;
	$s_rows = MEMO_ROWS;
	$data = str_replace("\n", '&NewLine;', $data);
	$string = <<<EOD
<form action="{$_script}" method="post" class="memo">
	<div>
		<input type="hidden" name="memo_no" value="{$memo_no}" />
		<input type="hidden" name="refer" value="{$s_page}" />
		<input type="hidden" name="plugin" value="memo" />
		<input type="hidden" name="digest" value="{$s_digest}" />
		<textarea name="msg" rows="{$s_rows}" cols="{$s_cols}">{$data}</textarea><br />
		{$_submit}
	</div>
</form>
EOD;

	return $string;
}
