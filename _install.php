<?
// ===================================================================
// Sim Roulette -> Installer
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

if ($_POST['save'])
{
	if (!file_exists($_POST['root'].'/_install.php'))
	{
		$error='Неверный путь к скрипту!';
	}
	else
	{
		$txt=file_get_contents('api/handler.php');
		$txt=str_replace('$root="[path]"','$root="'.$_POST['root'].'/"',$txt);
		file_put_contents('api/handler.php',$txt);
		$txt=file_get_contents('cron.php');
		$txt=str_replace('$root="[path]"','$root="'.$_POST['root'].'/"',$txt);
		file_put_contents('cron.php',$txt);
		$txt=file_get_contents('link.php');
		$txt=str_replace('$root="[path]"','$root="'.$_POST['root'].'/"',$txt);
		file_put_contents('link.php',$txt);

		$txt=file_get_contents('_config.php');
		$txt=str_replace('$host="'.$host.'"','$host="'.$_POST['host'].'"',$txt);
		$txt=str_replace('$username="'.$username.'"','$username="'.$_POST['username'].'"',$txt);
		$txt=str_replace('$userpass="'.$userpass.'"','$userpass="'.$_POST['userpass'].'"',$txt);
		$txt=str_replace('$dbname="'.$dbname.'"','$dbname="'.$_POST['dbname'].'"',$txt);
		file_put_contents('_config.php',$txt);

		if ($_POST['host'] && $_POST['username'] && $_POST['userpass'] && $_POST['dbname'])
		{
			$db = mysqli_connect(
			$_POST['host'],  
			$_POST['username'],
			$_POST['userpass'],
			$_POST['dbname']); 
		}
		
		if (!$db) 
		{
			$error='Нет соединения с БД!';
			$host=$_POST['host'];  
			$username=$_POST['username'];
			$userpass=$_POST['userpass'];
			$dbname=$_POST['dbname']; 
		}
		else
		{

			mysqli_set_charset($db, 'utf8');

			$qry=explode("\n\n",str_replace("\r","",file_get_contents('install.dat')));
			foreach ($qry as $data)
			{
				if (!mysqli_query($db, $data))
				{
					$error='Не удалось создать таблицу!';
					$txt=file_get_contents('_config.php');
					$txt=str_replace('$userpass="'.$_POST['userpass'].'"','$userpass=""',$txt);
					file_put_contents('_config.php',$txt);
					$userpass=$_POST['userpass'];
					break;
				}
			}
		}
	}
}

sr_header("Установка SR Navigator");
?>
<br>
<?
if ($_POST['save'] && !$error)
{
	unlink('_install.php');
?>
<div class="status_ok">SR Navigator успешно установлен!</div>
Пропишите в планировщике CRONTAB ежеминутный запуск файла <b><?=__DIR__?>/cron.php</b>
<br><br>	
<a href="index.php">Начать работу</a>
<?
}
if (!$_POST['save'] || $error)
{
?>
<em>Системные требования: PHP 5.3 и выше, MySQL</em>
<br><br>
<?
	if ($error)
	{
?>
<div class="status_error">При установке произошла ошибка: <?=$error?></div>
<?
	}
?>
<form method="post">
<h3>Настройки сервера</h3>
Путь к каталогу со скриптом на сервере
<br>
<input type="text" name="root" value="<?=__DIR__?>" maxlength="200">
<br>
<h3>Настройки MySQL</h3>
Хост
<br>
<input type="text" name="host" value="<?=$host?>" maxlength="100">
<br><br>
Имя пользователя
<br>
<input type="text" name="username" value="<?=$username?>" maxlength="100">
<br><br>
Пароль
<br>
<input type="text" name="userpass" value="<?=$userpass?>" maxlength="100">
<br><br>
Название Базы Данных
<br>
<input type="text" name="dbname" value="<?=$dbname?>" maxlength="100">
<br><br>
<input type="submit" name="save" value="Установить" style="padding: 10px;">
</form>
<?
}
sr_footer();
?>

