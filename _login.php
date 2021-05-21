<?
// ===================================================================
// Sim Roulette -> Authorization
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

if ($_POST['login'])
{
	if ($_POST['login']!=$GLOBALS['set_data']['admin_login'] || $_POST['pass']!=$GLOBALS['set_data']['admin_pass'])
	{
		$qry="SELECT `login` FROM `staff` WHERE `login`='".$_POST['login']."' AND md5(`pass`) = '".MD5($_POST['pass'])."'";
		if ($result = mysqli_query($db, $qry))
		{ 
			if ($row = mysqli_fetch_array($result))
			{
				SetCookie("srlogin", $_POST['login'], time()+86400*365, "/");
				SetCookie("srpass", md5($_POST['pass']), time()+86400*365, "/");
				header('location:online.php');
				exit();
			}
		}

		$msg = "<div class=\"login_error\">Неверный пароль или логин</div>";
	}
	else
	{
		SetCookie("srlogin", $_POST['login'], time()+86400*365, "/");
		SetCookie("srpass", md5($_POST['pass']), time()+86400*365, "/");
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