<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// Copyright 2015-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// "Login form" plugin

function plugin_loginform_inline() : string
{
	$logout_param = '?plugin=basicauthlogout';

	return '<a href="'.htmlspecialchars(get_base_uri().$logout_param, ENT_COMPAT, 'UTF-8').'">Log out</a>';
}

function plugin_loginform_convert() : string
{
	return '<div>'.plugin_basicauthlogout_inline().'</div>';
}

function plugin_loginform_action() : array
{
	global $auth_user;
	global $auth_type;
	global $_loginform_messages;

	$page = (isset($_GET['page'])) ? ($_GET['page']) : ('');
	$pcmd = (isset($_GET['pcmd'])) ? ($_GET['pcmd']) : ('');
	$url_after_login = (isset($_GET['url_after_login'])) ? ($_GET['url_after_login']) : ('');
	$page_after_login = $page;

	if (!$url_after_login) {
		$page_after_login = $page;
	}

	$action_url = get_base_uri().'?plugin=loginform&page='.rawurlencode($page).(($url_after_login) ? ('&url_after_login='.rawurlencode($url_after_login)) : ('')).(($page_after_login) ? ('&page_after_login='.rawurlencode($page_after_login)) : (''));
	$username = (isset($_POST['username'])) ? ($_POST['username']) : ('');
	$password = (isset($_POST['password'])) ? ($_POST['password']) : ('');
	$isset_user_credential = ($username) || ($password);

	if (($username) && ($password) && (form_auth($username, $password))) {
		// Sign in successfully completed
		form_auth_redirect($url_after_login, $page_after_login);

		// or 'return FALSE;' - Don't double check for FORM_AUTH
		exit;
	}

	if ($pcmd === 'logout') {
		// logout
		switch ($auth_type) {
			case AUTH_TYPE_BASIC:
				header('WWW-Authenticate: Basic realm="Please cancel to log out"');
				http_response_code(401);

				break;

			case AUTH_TYPE_FORM:
			case AUTH_TYPE_EXTERNAL:
			case AUTH_TYPE_SAML:
			default:
				$_SESSION = [];
				session_regenerate_id(true);
				session_destroy();

				break;
		}

		$auth_user = '';

		return ['msg'=>'Log out', 'body'=>'Logged out completely<br /><a href="'.get_page_uri($page).'">'.$page.'</a>'];
	} else {
		// login
		ob_start(); ?>
<style>
  .loginformcontainer {
    text-align: center;
  }
  .loginform table {
    margin-top: 1em;
	margin-left: auto;
	margin-right: auto;
  }
  .loginform tbody td {
    padding: .5em;
  }
  .loginform .label {
    text-align: right;
  }
  .loginform .login-button-container {
    text-align: right;
  }
  .loginform .loginbutton {
    margin-top: 1em;
  }
  .loginform .errormessage {
    color: red;
  }
</style>
<div class="loginformcontainer">
	<form name="loginform" class="loginform" action="<?php echo htmlspecialchars($action_url, ENT_COMPAT, 'UTF-8'); ?>" method="post">
		<div>
			<table style="border:0">
				<tbody>
					<tr>
						<td class="label"><label for="_plugin_loginform_username"><?php echo htmlspecialchars($_loginform_messages['username'], ENT_COMPAT, 'UTF-8'); ?></label></td>
						<td><input type="text" name="username" value="<?php echo htmlspecialchars($username, ENT_COMPAT, 'UTF-8'); ?>" id="_plugin_loginform_username"></td>
					</tr>
					<tr>
						<td class="label"><label for="_plugin_loginform_password"><?php echo htmlspecialchars($_loginform_messages['password'], ENT_COMPAT, 'UTF-8'); ?></label></td>
						<td><input type="password" name="password" id="_plugin_loginform_password"></td>
					</tr>
<?php if ($isset_user_credential) { ?>
					<tr>
						<td></td>
						<td class="errormessage"><?php echo $_loginform_messages['invalid_username_or_password']; ?></td>
					</tr>

<?php } ?>
					<tr>
						<td></td>
						<td class="login-button-container"><input type="submit" value="<?php echo htmlspecialchars($_loginform_messages['login'], ENT_COMPAT, 'UTF-8'); ?>" class="loginbutton"></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div></div>
	</form>
</div>
<?php
		$body = ob_get_contents();
		ob_end_clean();

		return ['msg'=>$_loginform_messages['login'], 'body'=>$body];
	}
}
