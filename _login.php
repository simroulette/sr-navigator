<?
// ===================================================================
// Sim Roulette -> Authorization
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

if ($_POST['login'])
{

	if ($_POST['login']!=$GLOBALS['set_data']['admin_login'] || $_POST['pass']!=$GLOBALS['set_data']['admin_pass'])
	{
		$msg = "<div class=\"login_error\">Неверный пароль или логин</div>";
	}
	else
	{
		SetCookie("login", $_POST['login'], time()+86400*365, "/");
		SetCookie("pass", md5($_POST['pass']), time()+86400*365, "/");
		header('location:index.php');
		exit();
	}
}
?><html>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<meta content="width=device-width, initial-scale=1.0, user-scalable=no" name="viewport">
<meta content="width=800, initial-scale=1.0, user-scalable=no" name="viewport">
<meta name="viewport" content="width=800">
<meta name="theme-color" content="#2b60b7">
<title>SR Navigator</title>
<link rel="stylesheet" type="text/css" href="sr/style.css" />
<link rel="stylesheet" type="text/css" href="sr/font.css" />
<body>

<table height="100%" width="100%" class="login_table">
<tr><td align="center" colspan="4" height="99%">
<div class="login_div">
<img src="sr/logo.gif" width="150" border="0">
<?=$msg?><form method='POST'>
<br><input type='text' name='login' value="<?=$_POST['login']?>" placeholder='Логин'><br>
<br><input type='password' name='pass' placeholder='Пароль'><br>
<br><input type='submit' name='SUBMIT' value='Авторизоваться'></form>
</div>
<?
sr_footer();
?>