<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// vote.inc.php
// Copyright 2002-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Vote box plugin

function plugin_vote_action() : array
{
	global $vars;
	global $cols;
	global $rows;
	global $_title_collided;
	global $_msg_collided;
	global $_title_updated;
	global $_vote_plugin_votes;

	$script = get_base_uri();

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	$postdata_old = get_source($vars['refer']);

	$vote_no = 0;
	$vote_str = '';
	$postdata_input = '';
	$postdata = '';
	$body = '';
	$title = '';
	$matches = [];

	foreach ($postdata_old as $line) {
		if ((!preg_match('/^#vote(?:\((.*)\)(.*))?$/i', $line, $matches)) || ($vote_no++ != $vars['vote_no'])) {
			$postdata .= $line;

			continue;
		}

		$args = explode(',', $matches[1]);
		$lefts = (isset($matches[2])) ? ($matches[2]) : ('');

		foreach ($args as $arg) {
			$cnt = 0;

			if (preg_match('/^(.+)\[(\d+)\]$/', $arg, $matches)) {
				$arg = $matches[1];
				$cnt = $matches[2];
			}

			$e_arg = encode($arg);

			if ((!empty($vars['vote_'.$e_arg])) && ($vars['vote_'.$e_arg] == $_vote_plugin_votes)) {
				$cnt++;
			}

			$votes[] = $arg.'['.$cnt.']';
		}

		$vote_str = '#vote('.@implode(',', $votes).')'.$lefts."\n";
		$postdata_input = $vote_str;
		$postdata .= $vote_str;
	}

	if (md5(get_source($vars['refer'], true, true)) !== $vars['digest']) {
		$title = $_title_collided;

		$s_refer = htmlspecialchars($vars['refer'], ENT_COMPAT, 'UTF-8');
		$s_digest = htmlspecialchars($vars['digest'], ENT_COMPAT, 'UTF-8');
		$s_postdata_input = htmlspecialchars($postdata_input, ENT_COMPAT, 'UTF-8');
		$s_postdata_input = str_replace("\n", '&NewLine;', $s_postdata_input);
		$body = <<<EOD
{$_msg_collided}
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

	$vars['page'] = $vars['refer'];

	return ['msg'=>$title, 'body'=>$body];
}

function plugin_vote_convert(string ...$args) : string
{
	global $vars;
	global $digest;
	global $_vote_plugin_choice;
	global $_vote_plugin_votes;
	static $number = [];

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	// Vote-box-id in the page
	if (!isset($number[$page])) {
		// Init
		$number[$page] = 0;
	}

	$vote_no = $number[$page]++;

	if (!func_num_args()) {
		return '#vote(): No arguments<br />'."\n";
	}

	if (PKWK_READONLY) {
		$_script = '';
		$_submit = 'hidden';
	} else {
		$_script = get_base_uri();
		$_submit = 'submit';
	}

	$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
	$s_digest = htmlspecialchars($digest, ENT_COMPAT, 'UTF-8');

	$body = <<<EOD
<form action="{$_script}" method="post">
	<table cellspacing="0" cellpadding="2" class="style_table" summary="vote">
		<tr>
			<td align="left" class="vote_label" style="padding-left:1em;padding-right:1em"><strong>{$_vote_plugin_choice}</strong>
				<input type="hidden" name="plugin" value="vote" />
				<input type="hidden" name="refer" value="{$s_page}" />
				<input type="hidden" name="vote_no" value="{$vote_no}" />
				<input type="hidden" name="digest" value="{$s_digest}" />
			</td>
			<td align="center" class="vote_label"><strong>{$_vote_plugin_votes}</strong></td>
		</tr>

EOD;

	$tdcnt = 0;
	$matches = [];

	foreach ($args as $arg) {
		$cnt = 0;

		if (preg_match('/^(.+)\[(\d+)\]$/', $arg, $matches)) {
			$arg = $matches[1];
			$cnt = $matches[2];
		}

		$e_arg = encode($arg);

		$link = make_link($arg);

		$cls = ($tdcnt++ % 2) ? ('vote_td1') : ('vote_td2');

		$body .= <<<EOD
		<tr>
			<td align="left" class="{$cls}" style="padding-left:1em;padding-right:1em;">{$link}</td>
			<td align="right" class="{$cls}">{$cnt}&nbsp;&nbsp;
				<input type="{$_submit}" name="vote_{$e_arg}" value="{$_vote_plugin_votes}" class="submit" />
			</td>
		</tr>

EOD;
	}

	$body .= <<<'EOD'
	</table>
</form>

EOD;

	return $body;
}
