<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// $Id: versionlist.inc.php,v 1.17 2007/05/12 08:37:38 henoheno Exp $
// Copyright (C)
//	 2002-2006 PukiWiki Developers Team
//	 2002      S.YOSHIMURA GPL2 yosimura@excellence.ac.jp
// License: GPL v2
//
// Listing cvs revisions of files

function plugin_versionlist_action() : array
{
	global $_title_versionlist;

	if (PKWK_SAFE_MODE) {
		die_message('PKWK_SAFE_MODE prohibits this');
	}

	return ['msg'=>$_title_versionlist, 'body'=>plugin_versionlist_convert()];
}

function plugin_versionlist_convert() : string
{
	if (PKWK_SAFE_MODE) {
		// Show nothing
		return '';
	}

	// 探索ディレクトリ設定
	$SCRIPT_DIR = ['./'];

	if (LIB_DIR != './') {
		array_push($SCRIPT_DIR, LIB_DIR);
	}

	if ((DATA_HOME != './') && (DATA_HOME != LIB_DIR)) {
		array_push($SCRIPT_DIR, DATA_HOME);
	}

	array_push($SCRIPT_DIR, PLUGIN_DIR, SKIN_DIR);

	$comments = [];

	foreach ($SCRIPT_DIR as $sdir) {
		if (!$dir = @dir($sdir)) {
			// die_message('directory '.$sdir.' is not found or not readable.');
			continue;
		}

		while ($file = $dir->read()) {
			if (!preg_match('/\\.(php|lng|css|js)$/i', $file)) {
				continue;
			}

			$data = implode('', file($sdir.$file));
			$comment = ['file'=>htmlsc($sdir.$file), 'rev'=>'', 'date'=>''];

			if (preg_match('/\$Id: (.+),v (\d+\.\d+) (\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})/', $data, $matches)) {
//				$comment['file'] = htmlsc($sdir.$matches[1]);
				$comment['rev'] = htmlsc($matches[2]);
				$comment['date'] = htmlsc($matches[3]);
			}

			$comments[$sdir.$file] = $comment;
		}

		$dir->close();
	}

	if (count($comments) == 0) {
		return '';
	}

	ksort($comments, SORT_STRING);
	$retval = '';

	foreach ($comments as $comment) {
		$retval .= <<<EOD

		<tr>
			<td>{$comment['file']}</td>
			<td align="right">{$comment['rev']}</td>
			<td>{$comment['date']}</td>
		</tr>
EOD;
	}

	return <<<EOD
<table border="1">
	<thead>
		<tr>
			<th>filename</th>
			<th>revision</th>
			<th>date</th>
		</tr>
	</thead>
	<tbody>
{$retval}
	</tbody>
</table>
EOD;
}
