<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// backup.inc.php
// Copyright
//   2002-2017 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Backup plugin

// Prohibit rendering old wiki texts (suppresses load, transfer rate, and security risk)
define('PLUGIN_BACKUP_DISABLE_BACKUP_RENDERING', (PKWK_SAFE_MODE) || ((defined('PKWK_OPTIMISE')) && (PKWK_OPTIMISE)));

function plugin_backup_action() : array
{
	global $vars;
	global $do_backup;
	global $hr;
	global $_msg_backuplist;
	global $_msg_diff;
	global $_msg_nowdiff;
	global $_msg_source;
	global $_msg_backup;
	global $_msg_view;
	global $_msg_goto;
	global $_msg_deleted;
	global $_title_backupdiff;
	global $_title_backupnowdiff;
	global $_title_backupsource;
	global $_title_backup;
	global $_title_pagebackuplist;
	global $_title_backuplist;

	if (!$do_backup) {
		return [];
	}

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if ($page == '') {
		return ['msg'=>$_title_backuplist, 'body'=>plugin_backup_get_list_all()];
	}

	check_readable($page, true, true);
	$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
	$r_page = rawurlencode($page);

	$action = (isset($vars['action'])) ? ($vars['action']) : ('');

	if ($action == 'delete') {
		return plugin_backup_delete($page);
	}

	$r_action = '';
	$s_action = '';

	if ($action != '') {
		$s_action = htmlspecialchars($action, ENT_COMPAT, 'UTF-8');
		$r_action = rawurlencode($action);
	}

	$s_age = ((isset($vars['age'])) && (is_numeric($vars['age']))) ? ($vars['age']) : (0);

	if ($s_age <= 0) {
		return ['msg'=>$_title_pagebackuplist, 'body'=>plugin_backup_get_list($page)];
	}

	$script = get_base_uri();

	$body = '<ul>'."\n";
	$body .= "\t".'<li><a href="'.$script.'?cmd=backup">'.$_msg_backuplist.'</a></li>'."\n";

	$href = $script.'?cmd=backup&amp;page='.$r_page.'&amp;age='.$s_age;
	$is_page = is_page($page);

	if (($is_page) && ($action != 'diff')) {
		$body .= "\t".'<li>'.str_replace('$1', '<a href="'.$href.'&amp;action=diff">'.$_msg_diff.'</a>', $_msg_view).'</li>'."\n";
	}

	if (($is_page) && ($action != 'nowdiff')) {
		$body .= "\t".'<li>'.str_replace('$1', '<a href="'.$href.'&amp;action=nowdiff">'.$_msg_nowdiff.'</a>', $_msg_view).'</li>'."\n";
	}

	if ($action != 'source') {
		$body .= "\t".'<li>'.str_replace('$1', '<a href="'.$href.'&amp;action=source">'.$_msg_source.'</a>', $_msg_view).'</li>'."\n";
	}

	if ((!PLUGIN_BACKUP_DISABLE_BACKUP_RENDERING) && ($action)) {
		$body .= "\t".'<li>'.str_replace('$1', '<a href="'.$href.'">'.$_msg_backup.'</a>', $_msg_view).'</li>'."\n";
	}

	if ($is_page) {
		$body .= "\t".'<li>'.str_replace('$1', '<a href="'.$script.'?'.$r_page.'">'.$s_page.'</a>', $_msg_goto)."\n";
	} else {
		$body .= "\t".'<li>'.str_replace('$1', $s_page, $_msg_deleted)."\n";
	}

	$backups = get_backup($page);
	$backups_count = count($backups);

	if ($s_age > $backups_count) {
		$s_age = $backups_count;
	}

	if ($backups_count > 0) {
		$body .= "\t\t".'<ul>'."\n";

		foreach ($backups as $age=>$val) {
			$date = format_date((int) ($val['time']), true);
			$body .= ($age == $s_age) ? ("\t\t\t".'<li><em>'.$age.' '.$date.'</em></li>'."\n") : ("\t\t\t".'<li><a href="'.$script.'?cmd=backup&amp;action='.$r_action.'&amp;page='.$r_page.'&amp;age='.$age.'">'.$age.' '.$date.'</a></li>'."\n");
		}

		$body .= "\t\t".'</ul>'."\n";
	}

	$body .= "\t".'</li>'."\n";
	$body .= '</ul>'."\n";

	if ($action == 'diff') {
		$title = &$_title_backupdiff;
		$old = ($s_age > 1) ? (implode('', $backups[$s_age - 1]['data'])) : ('');
		$cur = implode('', $backups[$s_age]['data']);
		$body .= plugin_backup_diff(do_diff($old, $cur));
	} elseif ($s_action == 'nowdiff') {
		$title = &$_title_backupnowdiff;
		$old = implode('', $backups[$s_age]['data']);
		$cur = implode('', get_source($page));
		$body .= plugin_backup_diff(do_diff($old, $cur));
	} elseif ($s_action == 'source') {
		$title = &$_title_backupsource;
		$body .= '<pre translate="no">'.str_replace("\n", '&NewLine;', htmlspecialchars(implode('', $backups[$s_age]['data']), ENT_COMPAT, 'UTF-8')).'</pre>'."\n";
	} else {
		if (PLUGIN_BACKUP_DISABLE_BACKUP_RENDERING) {
			die_message('This feature is prohibited');
		} else {
			$title = &$_title_backup;
			$body .= $hr."\n".drop_submit(convert_html($backups[$s_age]['data']));
		}
	}

	return ['msg'=>str_replace('$2', $s_age, $title), 'body'=>$body];
}

// Delete backup
function plugin_backup_delete(string $page) : array
{
	global $vars;
	global $_title_backup_delete;
	global $_title_pagebackuplist;
	global $_msg_backup_deleted;
	global $_msg_backup_adminpass;
	global $_btn_delete;
	global $_msg_invalidpass;

	if (!_backup_file_exists($page)) {
		// Say "is not found"
		return ['msg'=>$_title_pagebackuplist, 'body'=>plugin_backup_get_list($page)];
	}

	$body = '';

	if (isset($vars['pass'])) {
		if (pkwk_login($vars['pass'])) {
			_backup_delete($page);

			return ['msg'=>$_title_backup_delete, 'body'=>str_replace('$1', make_pagelink($page), $_msg_backup_deleted)];
		} else {
			$body = '<p><strong>'.$_msg_invalidpass.'</strong></p>'."\n";
		}
	}

	$script = get_base_uri();
	$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
	$body .= <<<EOD
<p>{$_msg_backup_adminpass}</p>
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="cmd" value="backup" />
		<input type="hidden" name="page" value="{$s_page}" />
		<input type="hidden" name="action" value="delete" />
		<input type="password" name="pass" size="12" />
		<input type="submit" name="ok" value="{$_btn_delete}" />
	</div>
</form>
EOD;

	return	['msg'=>$_title_backup_delete, 'body'=>$body];
}

function plugin_backup_diff(string $str) : string
{
	global $_msg_addline;
	global $_msg_delline;
	global $hr;

	$ul = <<<EOD
{$hr}
<ul>
	<li>{$_msg_addline}</li>
	<li>{$_msg_delline}</li>
</ul>
EOD;

	return $ul.'<pre translate="no">'.str_replace("\n", '&NewLine;', diff_style_to_css(htmlspecialchars($str, ENT_COMPAT, 'UTF-8'))).'</pre>'."\n";
}

function plugin_backup_get_list(string $page) : string
{
	global $_msg_backuplist;
	global $_msg_diff;
	global $_msg_nowdiff;
	global $_msg_source;
	global $_msg_nobackup;
	global $_title_backup_delete;

	$script = get_base_uri();
	$r_page = rawurlencode($page);
	$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
	$retval = [];
	$retval[0] = <<<EOD
<ul>
	<li><a href="{$script}?cmd=backup">{$_msg_backuplist}</a>
		<ul>
EOD;
	$retval[1] = "\n";
	$retval[2] = <<<'EOD'
		</ul>
	</li>
</ul>
EOD;

	$backups = (_backup_file_exists($page)) ? (get_backup($page)) : ([]);

	if (empty($backups)) {
		$msg = str_replace('$1', make_pagelink($page), $_msg_nobackup);
		$retval[1] .= "\t\t\t".'<li>'.$msg.'</li>'."\n";

		return implode('', $retval);
	}

	if (!PKWK_READONLY) {
		$retval[1] .= "\t\t\t".'<li><a href="'.$script.'?cmd=backup&amp;action=delete&amp;page='.$r_page.'">';
		$retval[1] .= str_replace('$1', $s_page, $_title_backup_delete);
		$retval[1] .= '</a></li>'."\n";
	}

	$href = $script.'?cmd=backup&amp;page='.$r_page.'&amp;age=';
	$_anchor_to = '';
	$_anchor_from = '';

	foreach ($backups as $age=>$data) {
		if (!PLUGIN_BACKUP_DISABLE_BACKUP_RENDERING) {
			$_anchor_from = '<a href="'.$href.$age.'">';
			$_anchor_to = '</a>';
		}

		$date = format_date((int) ($data['time']), true);
		$author_info = '';

		if (isset($data['author'])) {
			$author_info = htmlspecialchars('by '.$data['author_fullname'].'('.$data['author'].')', ENT_COMPAT, 'UTF-8');
		}

		$retval[1] .= <<<EOD
			<li>{$_anchor_from}{$age} {$date}{$_anchor_to} [ <a href="{$href}{$age}&amp;action=diff">{$_msg_diff}</a> | <a href="{$href}{$age}&amp;action=nowdiff">{$_msg_nowdiff}</a> | <a href="{$href}{$age}&amp;action=source">{$_msg_source}</a> ] {$author_info}</li>
EOD;
	}

	return implode('', $retval);
}

// List for all pages
function plugin_backup_get_list_all(bool $withfilename = false) : string
{
	global $cantedit;

	$pages = array_diff(get_existpages(BACKUP_DIR, BACKUP_EXT), $cantedit);

	if (empty($pages)) {
		return '';
	} else {
		return page_list($pages, 'backup', $withfilename);
	}
}
