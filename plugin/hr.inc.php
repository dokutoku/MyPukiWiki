<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// $Id: hr.inc.php,v 1.4 2005/01/22 03:34:17 henoheno Exp $
//
// Horizontal rule plugin

function plugin_hr_convert() : string
{
	return '<hr class="short_line" />';
}
