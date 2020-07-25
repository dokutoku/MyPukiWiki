<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// counter.inc.php
// Copyright
//   2002-2019 PukiWiki Development Team
//   2002 Y.MASUI GPL2 http://masui.net/pukiwiki/ masui@masui.net
// License: GPL2
//
// Counter plugin (per page)

// Counter file's suffix
define('PLUGIN_COUNTER_SUFFIX', '.count');

// Use Database (1) or not (0)
define('PLUGIN_COUNTER_USE_DB', 0);

// Database Connection setting
define('PLUGIN_COUNTER_DB_CONNECT_STRING', 'sqlite:counter/counter.db');
define('PLUGIN_COUNTER_DB_USERNAME', '');
define('PLUGIN_COUNTER_DB_PASSWORD', '');

global $plugin_counter_db_options;

// For MySQL
// $plugin_counter_db_options = [PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8mb4 COLLATE utf8mb4_bin"];
$plugin_counter_db_options = null;

define('PLUGIN_COUNTER_DB_TABLE_NAME_PREFIX', '');

if (PLUGIN_COUNTER_USE_DB) {
	ini_set('default_socket_timeout', '2');
}

// Report one
function plugin_counter_inline(string ...$args) : string
{
	global $vars;

	// BugTrack2/106: Only variables can be passed by reference from PHP 5.0.5

	// with array_shift()
	$arg = strtolower(array_shift($args));

	switch ($arg) {
		case '':
			$arg = 'total';
			// FALLTHROUGH

		case 'total':
		case 'today':
		case 'yesterday':
			$counter = plugin_counter_get_count($vars['page']);

			return (string) ($counter[$arg]);

		default:
			return '&counter([total|today|yesterday]);';
	}
}

// Report all
function plugin_counter_convert() : string
{
	global $vars;

	$counter = plugin_counter_get_count($vars['page']);

	return <<<EOD
<div class="counter">Counter:   {$counter['total']}, today:     {$counter['today']}, yesterday: {$counter['yesterday']}</div>
EOD;
}

// Return a summary
function plugin_counter_get_count(string $page) :array
{
	global $vars;
	global $plugin_counter_db_options;
	static $counters = [];
	static $default;

	$page_counter_t = PLUGIN_COUNTER_DB_TABLE_NAME_PREFIX.'page_counter';

	if (!isset($default)) {
		$default =
		[
			'total'=>0,
			'date'=>get_date('Y/m/d'),
			'today'=>0,
			'yesterday'=>0,
			'ip'=>'',
		];
	}

	if (!is_page($page)) {
		return $default;
	}

	if (isset($counters[$page])) {
		return $counters[$page];
	}

	// Set default
	$counters[$page] = $default;
	$modify = false;
	$c = &$counters[$page];

	if (PLUGIN_COUNTER_USE_DB) {
		if (SOURCE_ENCODING !== 'UTF-8') {
			die('counter.inc.php: Database counter is only available in UTF-8 mode');
		}

		$is_new_page = false;

		try {
			$pdo = new PDO(PLUGIN_COUNTER_DB_CONNECT_STRING, PLUGIN_COUNTER_DB_USERNAME, PLUGIN_COUNTER_DB_PASSWORD, $plugin_counter_db_options);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
			$stmt = $pdo->prepare("SELECT total, update_date,
   today_viewcount, yesterday_viewcount, remote_addr
 FROM {$page_counter_t}
 WHERE page_name = ?"
			);
			$stmt->execute([$page]);
			$r = $stmt->fetch();

			if ($r === false) {
				$is_new_page = true;
			} else {
				$c['ip'] = $r['remote_addr'];
				$c['date'] = $r['update_date'];
				$c['yesterday'] = (int) ($r['yesterday_viewcount']);
				$c['today'] = (int) ($r['today_viewcount']);
				$c['total'] = (int) ($r['total']);
				$stmt->closeCursor();
			}
		} catch (Exception $e) {
			// Error occurred
			$db_error = '(DBError)';

			return
			[
				'total'=>$db_error,
				'date'=>$db_error,
				'today'=>$db_error,
				'yesterday'=>$db_error,
				'ip'=>$db_error,
			];
		}
	} else {
		// Open
		$file = COUNTER_DIR.encode($page).PLUGIN_COUNTER_SUFFIX;
		pkwk_touch_file($file);

		if (!($fp = fopen($file, 'r+'))) {
			die('counter.inc.php: Cannot open COUNTER_DIR/'.basename($file));
		}

		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		rewind($fp);

		// Read
		foreach (array_keys($default) as $key) {
			// Update
			$c[$key] = rtrim(fgets($fp, 256));

			if (feof($fp)) {
				break;
			}
		}
	}

	// Anothoer day?
	$remote_addr = $_SERVER['REMOTE_ADDR'];
	$count_up = false;

	if ($c['date'] != $default['date']) {
		$modify = true;
		$is_yesterday = ($c['date'] == get_date('Y/m/d', UTIME - 24 * 60 * 60));
		$c[$page]['ip'] = $remote_addr;
		$c['date'] = $default['date'];
		$c['yesterday'] = ($is_yesterday) ? ($c['today']) : (0);
		$c['today'] = 1;
		$c['total']++;
		$count_up = true;
	} elseif ($c['ip'] != $remote_addr) {
		// Not the same host
		$modify = true;
		$c['ip'] = $remote_addr;
		$c['today']++;
		$c['total']++;
		$count_up = true;
	}

	if (PLUGIN_COUNTER_USE_DB) {
		if (($modify) && ($vars['cmd'] == 'read')) {
			try {
				if ($is_new_page) {
					// Insert
					$add_stmt = $pdo->prepare("INSERT INTO {$page_counter_t}
   (page_name, total, update_date, today_viewcount,
   yesterday_viewcount, remote_addr)
 VALUES (?, ?, ?, ?, ?, ?)"
					);
					$r_add = $add_stmt->execute([$page, $c['total'], $c['date'], $c['today'], $c['yesterday'], $c['ip']]);
				} elseif ($count_up) {
					// Update on counting up 'total'
					$upd_stmt = $pdo->prepare("UPDATE {$page_counter_t}
 SET total = total + 1,
   update_date = ?,
   today_viewcount = ?,
   yesterday_viewcount = ?,
   remote_addr = ?
 WHERE page_name = ?"
					);
					$r_upd = $upd_stmt->execute([$c['date'], $c['today'], $c['yesterday'], $c['ip'], $page]);
				}
			} catch (PDOException $e) {
				foreach (array_keys($c) as $key) {
					$c[$key] .= '(DBError)';
				}
			}
		}
	} else {
		// Modify
		if (($modify) && ($vars['cmd'] == 'read')) {
			rewind($fp);
			ftruncate($fp, 0);

			foreach (array_keys($default) as $key) {
				fwrite($fp, $c[$key]."\n");
			}
		}

		// Close
		flock($fp, LOCK_UN);
		fclose($fp);
	}

	return $c;
}

function plugin_counter_get_popular_list(string $today, string $except, int $max)
{
	if (PLUGIN_COUNTER_USE_DB === 0) {
		return plugin_counter_get_popular_list_file($today, $except, $max);
	} else {
		return plugin_counter_get_popular_list_db($today, $except, $max);
	}
}

function plugin_counter_get_popular_list_file(string $today, string $except, int $max)
{
	global $whatsnew;

	$counters = [];
	$except_quote = str_replace('#', '\#', $except);

	foreach (get_existpages(COUNTER_DIR, '.count') as $file=>$page) {
		if ((($except != '') && (preg_match("#{$except_quote}#", $page))) || ($page == $whatsnew) || (check_non_list($page)) || (!is_page($page))) {
			continue;
		}

		$array = file(COUNTER_DIR.$file);
		$count = rtrim($array[0]);
		$date = rtrim($array[1]);
		$today_count = rtrim($array[2]);

		if ($today) {
			// $pageが数値に見える(たとえばencode('BBS')=424253)とき、
			// array_splice()によってキー値が変更されてしまうのを防ぐ
			// ため、キーに '_' を連結する
			if ($today == $date) {
				$counters['_'.$page] = $today_count;
			}
		} else {
			$counters['_'.$page] = $count;
		}
	}

	asort($counters, SORT_NUMERIC);

	// BugTrack2/106: Only variables can be passed by reference from PHP 5.0.5

	// with array_splice()
	$counters = array_reverse($counters, true);

	$counters = array_splice($counters, 0, $max);

	return $counters;
}

function plugin_counter_get_popular_list_db(string $today, string $except, int $max)
{
	global $whatsnew;

	$page_counter_t = PLUGIN_COUNTER_DB_TABLE_NAME_PREFIX.'page_counter';

	if ($today) {
		$order_by_c = 'today_viewcount';
	} else {
		$order_by_c = 'total';
	}

	$counters = [];

	try {
		$pdo = new PDO(PLUGIN_COUNTER_DB_CONNECT_STRING, PLUGIN_COUNTER_DB_USERNAME, PLUGIN_COUNTER_DB_PASSWORD, $plugin_counter_db_options);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

		if ($today) {
			$stmt = $pdo->prepare("SELECT page_name, total, update_date,
   today_viewcount, yesterday_viewcount
 FROM {$page_counter_t}
 WHERE update_date = ?
 ORDER BY {$order_by_c} DESC
 LIMIT ?"
			);
		} else {
			$stmt = $pdo->prepare("SELECT page_name, total, update_date,
   today_viewcount, yesterday_viewcount
 FROM {$page_counter_t}
 ORDER BY {$order_by_c} DESC
 LIMIT ?"
			);
		}

		$except_quote = str_replace('#', '\#', $except);
		$limit = $max + 100;

		if ($today) {
			$stmt->execute([$today, $limit]);
		} else {
			$stmt->execute([$limit]);
		}

		foreach ($stmt as $r) {
			$page = $r['page_name'];

			if ((($except != '') && (preg_match("#{$except_quote}#", $page))) || ($page == $whatsnew) || (check_non_list($page)) || (!is_page($page))) {
				continue;
			}

			if ($today) {
				$counters['_'.$page] = $r['today_viewcount'];
			} else {
				$counters['_'.$page] = $r['total'];
			}
		}

		$stmt->closeCursor();

		return array_splice($counters, 0, $max);
	} catch (Exception $e) {
		die('counter.inc.php: Error occurred on getting pupular pages');
	}
}

function plugin_counter_page_rename(array $pages) : void
{
	global $plugin_counter_db_options;

	if (PLUGIN_COUNTER_USE_DB !== 0) {
		$page_counter_t = PLUGIN_COUNTER_DB_TABLE_NAME_PREFIX.'page_counter';
		$pdo = new PDO(PLUGIN_COUNTER_DB_CONNECT_STRING, PLUGIN_COUNTER_DB_USERNAME, PLUGIN_COUNTER_DB_PASSWORD, $plugin_counter_db_options);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$stmt_delete = $pdo->prepare("DELETE FROM {$page_counter_t}
 WHERE page_name = ?"
		);
		$stmt_rename = $pdo->prepare("UPDATE {$page_counter_t}
 SET page_name = ?
 WHERE page_name = ?"
		);

		foreach ($pages as $old_name=>$new_name) {
			$stmt_delete->execute([$new_name]);
			$stmt_rename->execute([$new_name, $old_name]);
		}
	}
}

/**
 * php -r "include 'plugin/counter.inc.php'; plugin_counter_tool_setup_table();".
 */
function plugin_counter_tool_setup_table() : void
{
	global $plugin_counter_db_options;

	$page_counter_t = PLUGIN_COUNTER_DB_TABLE_NAME_PREFIX.'page_counter';
	$pdo = new PDO(PLUGIN_COUNTER_DB_CONNECT_STRING, PLUGIN_COUNTER_DB_USERNAME, PLUGIN_COUNTER_DB_PASSWORD, $plugin_counter_db_options);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	$r = $pdo->exec("CREATE TABLE {$page_counter_t} (page_name VARCHAR(190) PRIMARY KEY,
   total INTEGER NOT NULL,
   update_date VARCHAR(20) NOT NULL,
   today_viewcount INTEGER NOT NULL,
   yesterday_viewcount INTEGER NOT NULL,
   remote_addr VARCHAR(100)
 )"
	);
	echo "OK\n";
}
