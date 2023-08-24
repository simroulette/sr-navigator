<?

// Connecting to a database | Подключение к БД
if ($host && $username && $userpass && $dbname)
{
	$db = mysqli_connect(
	$host,  
	$username,
	$userpass,
	$dbname); 

	mysqli_set_charset($db, 'utf8');
}

if (!$db) 
{
	if (file_exists('_install.php'))
	{
		include('_install.php');
	}
	else
	{
		printf("Unable to connect to the database. Error code: %s\n", mysqli_connect_error()); // Невозможно подключиться к базе данных.
	}
	exit;
}

$qry="SELECT * FROM `values`";
$result = mysqli_query($db,$qry);
while ($row = mysqli_fetch_array($result))
{
	$GLOBALS['set_data'][$row['name']]=$row['value'];
}

// Logout of an authorized user | Выход авторизованного пользователя
if ($_GET['mode']=='logout')
{
	SetCookie("srlogin", "", 0, "/");
	SetCookie("srpass", "", 0, "/");
	header('location:index.php');
	exit();
}
// Authorization | Авторизация
if ($_SERVER['DOCUMENT_ROOT'] && ($GLOBALS['set_data']['admin_login'] && ($_COOKIE['srlogin']!=$GLOBALS['set_data']['admin_login'] || $_COOKIE['srpass']!=md5($GLOBALS['set_data']['admin_pass']))))
{
	include('_login.php');
	exit();
}

// Output of the header WEB page
// Вывод верха страницы
function sr_header($title,$win='')
{
//	$title		Page title
//	$win		Modal window

	global $db;
?><html>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<meta content="width=device-width, initial-scale=1.0, user-scalable=no" name="viewport"><meta name="theme-color" content="#3b485d">
<title><?=$title?></title>
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
<link rel="apple-touch-icon" sizes="57x57" href="icons/apple-touch-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="icons/apple-touch-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="icons/apple-touch-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="icons/apple-touch-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="icons/apple-touch-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="icons/apple-touch-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="icons/apple-touch-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="icons/apple-touch-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon-180x180.png">
<link rel="icon" type="image/png" href="icons/favicon-32x32.png" sizes="32x32">
<link rel="icon" type="image/png" href="icons/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/png" href="icons/android-chrome-192x192.png" sizes="192x192">
<meta name="msapplication-square70x70logo" content="icons/smalltile.png" />
<meta name="msapplication-square150x150logo" content="icons/mediumtile.png" />
<meta name="msapplication-square310x310logo" content="icons/largetile.png" />
<link rel="stylesheet" type="text/css" href="sr/style.css?v=19" />
<link rel="stylesheet" type="text/css" href="sr/modal.css" />
<link rel="stylesheet" type="text/css" href="sr/font.css" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<script src="sr/main.js?v=19" type="text/javascript"></script>
<body style="position: relative;">
<div class="preloader">
<img src="sr/lloading.gif" class="lloading">
</div>
<div class="dm-overlay"<? if ($win){?> id="<?=$win?>"<? } ?>>
    <div class="dm-table">
        <div class="dm-cell">
            <div class="dm-modal">
                <div class="dm-head">
                <h3></h3>
                <i class="icon-cancel dm-close win-close"></i>
		</div>
                <div class="dm-body">
        	    <div class="dm-content">

	            </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="desktop"></div>

<div id="menu_cont"></div>
<table height="100%" width="100%">
<tr class="status"><td width="1%" style="background:#171717;border-bottom: 1px solid #505050;" class="sidebar"></td><td id="status"></td></tr><tr><td bgcolor="#363636" class="sidebar"><a href="index.php"><img src="sr/logo.svg" class="logo" title="На главную страницу"></a></td><td class="head"><div class="mobilemenu" id="m" onclick="menuToggle(this)"><div class="bar1"></div><div class="bar2"></div><div class="bar3"></div></div><? if ($GLOBALS['set_data']['admin_login']){?><div class="sidebar" style="float: right; margin-right: 50px;"><?=$GLOBALS['set_data']['admin_login'] ?>: <a href="index.php?mode=logout">Выход</a>&nbsp;</div><? } ?></td></tr><tr><td height=99% class="sidebar panel" valign="top">
<div id="menu">
<?
	if (!$GLOBALS['sv_owner_id'])
	{
/*
		$menu=explode("\n",$GLOBALS['set_data']['main_menu']);
		$m=array();
		foreach ($menu as $data)
		{
			$a=explode(' - ',$data);
			$m[$a[0]]=$a[1];
		}
		foreach ($m as $name=>$link)
		{
			$link=explode(';',trim($link));
			$url=explode('?',$link[0]);
			if (strpos($_SERVER['REQUEST_URI'],$url[0])!==false){$a=' active';} else {$a='';}
			if ($link[1] && $a){$link[1]='class="'.$link[1].$a.'" ';} elseif ($link[1]){$link[1]='class="'.$link[1].'" ';} elseif ($a){$link[1]='class="'.$a.'" ';}
			echo '<div '.$link[1].'onclick="document.location=\''.$link[0].'\';">'.trim($name).'</div>';
		}
*/
		$menu=explode("\n",$GLOBALS['set_data']['main_menu']);
		$m=array();
		foreach ($menu as $data)
		{
			$a=explode(' - ',$data);
			$m[$a[0]]=$a[1];
		}
		foreach ($m as $name=>$link)
		{
			$link=explode(';',trim($link));
			$url=explode('?',$link[0]);
			if (strpos($_SERVER['REQUEST_URI'],$url[0])!==false){$a=' active';} else {$a='';}
			if ($link[1] && $a){$link[1]='class="'.$link[1].$a.'" ';} elseif ($link[1]){$link[1]='class="'.$link[1].'" ';} elseif ($a){$link[1]='class="'.$a.'" ';}
			echo '<a href="'.$link[0].'" '.$link[1].'>'.trim($name).'</a>';
		}

	}
?>
</div>
</div></td><td valign="top"><em id="help" class="help" title="Помощь" onclick="help();"></em>
<h1><?=$title?></h1>
<?
	if ($sv_contact==';'){include('contacts.php');}
	if ($_SERVER['REQUEST_URI']!='/navigator/migration.php' && $sv_license=='pro'){include('license.php');}
}

function sr_footer()
{
	global $logpage,$bottom_menu,$sv_helpdesc;
	if ($logpage){exit();}
	if ($sv_helpdesc){
?>
<div id="helpInf" style="position: absolute;z-index: 100000;"></div>
<div id="helpInfDesc" style="position: absolute;z-index: 100000;"><h2>Контекстная помощь</h2>на каждой странице расскажет как пользоваться панелью SR-Navigator
<br><br>
<span onclick="helpdesc()" class="link width">Спасибо, понятно</a></span>
</div>
<script>
helpInfo();
</script>
<? 
} 
?><br><br></td></tr>
<tr><td class="bottom sidebar"><a href="https://sim-roulette.com">Sim Roulette</a> © 2016 — <?=date('Y')?></td>
<td class="bottom" align="right"><? if ($GLOBALS['set_data']['admin_login']){ ?><div class="extinfo" style="float: left;">
<?=$GLOBALS['set_data']['admin_login'];?>: <a href="index.php?mode=logout">Выход</a></div><? } ?></td>
</tr>
</table>

<? if (count($bottom_menu)){?>

<div class="navbar" id="botNavbar">
<? foreach ($bottom_menu AS $data)
{
	echo $data.'
';
}
?>
  <a href="javascript:void(0);" class="icon" onclick="menuOpen()">▲</a>
</div>
<? } ?>
</body>
</html><?
}

function ring_notification($number,$sender,$time,$txt)
{
	global $db;
	if ((int)$sender){$sender='+'.$sender;}
	$msg='Входящий вызов с номера ☎️ '.$sender.'
на номер +'.$number.' в '.date('H:i:s',$time);	

	if ($GLOBALS['set_data']['email'])
	{
		email2user($GLOBALS['set_data']['email'],'Входящий вызов',$msg,'','Входящий вызов','','','','');
	}
}

function sms_notification($number,$email,$sender,$time,$txt)
{
	global $db;
	if ((int)$sender){$sender='+'.$sender;}
	$msg=$sender.' 📨 +'.$number.' • '.date('H:i:s',$time).'
'.trim($txt);	

	$qry="SELECT * FROM `values` WHERE `name`='email'";
	$result = mysqli_query($db,$qry);
	if ($row = mysqli_fetch_array($result))
	{
		if ($row['value'])
		{
			$GLOBALS['set_data'][$row['name']]=$row['value'];
		}
	}

	if ($GLOBALS['set_data']['email'])
	{
		email2user($GLOBALS['set_data']['email'],'Новая SMS',$msg,'','Новая SMS','','','','');
	}
	// Чистим старые СМС
	$qry='DELETE FROM `sms_incoming` WHERE `time`<'.(time()-86400*7); 
	mysqli_query($db, $qry);
}

function telegram_send($method,$param)
{
}

?>