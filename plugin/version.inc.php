<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// $Id: version.inc.php,v 1.8 2005/01/29 02:07:58 henoheno Exp $
//
// Show PukiWiki version

function plugin_version_convert()
{
	if (PKWK_SAFE_MODE) {
		// Show nothing
		return '';
	}

	return '<p>'.S_VERSION.'</p>';
}

function plugin_version_inline()
{
	if (PKWK_SAFE_MODE) {
		// Show nothing
		return '';
	}

	return S_VERSION;
}
