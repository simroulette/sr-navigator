<?
// ===================================================================
// Sim Roulette -> Functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

Error_Reporting(~E_ALL & ~E_NOTICE & ~E_DEPRECATED);

include($root."_config.php");
include($root."_hardware.php");
$set_data['flags']=array();

// Connecting to a database | Подключение к БД
if ($host && $username && $userpass && $dbname)
{
	$db = mysqli_connect(
	$host,  
	$username,
	$userpass,
	$dbname); 
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
	SetCookie("login", "", 0, "/");
	SetCookie("pass", "", 0, "/");
	header('location:index.php');
	exit();
}

// Authorization | Авторизация
if ($_SERVER['DOCUMENT_ROOT'] && $set_data['admin_login'] && ($_COOKIE['login']!=$set_data['admin_login'] || $_COOKIE['pass']!=md5($set_data['admin_pass'])))
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

	if ($result = mysqli_query($db, 'SELECT login FROM `users`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
        		$user=$row['login'];
		}
	}
?><html>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<meta content="width=device-width, initial-scale=1.0, user-scalable=no" name="viewport"><meta name="theme-color" content="#2b60b7">
<title><?=$title?></title>
<link rel="stylesheet" type="text/css" href="sr/style.css" />
<link rel="stylesheet" type="text/css" href="sr/modal.css" />
<link rel="stylesheet" type="text/css" href="sr/font.css" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<script src="sr/main.js" type="text/javascript"></script>
<body>

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

<div id="menu_cont"></div>
<table height=100% width=100%>
<tr class="status"><td width="1%" style="background:#171717;border-bottom: 1px solid #505050;" class="sidebar"></td><td id="status"></td></tr><tr><td bgcolor="#363636" class="sidebar"><a href="index.php"><img src="sr/logo.gif" class="logo" title="На главную страницу"></a></td><td class="head"><div class="mobilemenu" id="m" onclick="menuToggle(this)"><div class="bar1"></div><div class="bar2"></div><div class="bar3"></div></div><? if ($GLOBALS['set_data']['admin_login']){?><div class="sidebar" style="float: right;"><?=$GLOBALS['set_data']['admin_login']?> [<a href="index.php?mode=logout">выход</a>]</div><? } ?></td></tr><tr><td height=99% class="sidebar panel" valign="top">
<div id="menu">
<?
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
?>
</div>
</div></td><td valign="top">
<h1><?=$title?></h1>
<?
}

// Output of the footer WEB page
// Вывод низа страницы
function sr_footer()
{
?></td></tr>
<tr><td class="bottom sidebar">© <a href="http://x0.ru">X0 Systems</a>, 2016 — <?=date('Y')?></td>
<td class="bottom" align="right"><? if ($GLOBALS['set_data']['admin_login']){ ?><div class="extinfo" style="float: left;"><?=$GLOBALS['set_data']['admin_login']?> [<a href="index.php?mode=logout">выход</a>]</div><? } ?></td>
</tr>
</table>
</body>
</html><?
}

// Output a table of modem statuses
// Вывод таблицы статусов модемов
function onlineTable($dev)
{
//	$dev		Device ID

	global $db;

	if ($dev)
	{
		if ($result = mysqli_query($db, 'SELECT m.*,d.model FROM `modems` m INNER JOIN `devices` d ON d.id='.$dev.' WHERE `device`='.$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);

				if ($row['model']=='SR-Train')
				{
					foreach ($modems AS $key => $status)
					{
						$curRow=$status[0];
						if ($status[1]==-3)
						{
							$st='выключен';
							$bg='CCCCCC';
						}
						elseif ($status[1]==-2)
						{
							$st='не&nbsp;активен';
							$bg='FF9900';
						}
						elseif ($status[1]==-1)
						{
							$st='включение';
							$bg='99CCFF';
						}
						elseif ($status[1]==1)
						{
							$st='активен';
							$bg='82b013';
						}
						elseif ($status[1]==2)
						{
							$st='поиск сети...';
							$bg='FF9900';
						}
						elseif ($status[1]==3)
						{
							$st='сеть не доступна';
							$bg='FF0000';
						}
						elseif ($status[1]==4)
						{
							$st='ошибка';
							$bg='FF0000';
						}
						elseif ($status[1]==0)
						{
							$st='ошибка';
							$bg='FF0000';
						}
						if (hexdec($bg)>8388607){$color='000';} else {$color='FFF';}
						$realPlace=$place=$status[0].'-'.$key;
						if ($key>8){$place=($status[0]+3).'-'.($key-8);}
						$places[]="'".$place."'";
						$table[$key]=array(
						'num'=>$key,
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
					}
					if (count($places))
					{
						if ($result = mysqli_query($db, 'SELECT c.*,o.title FROM `cards` c LEFT JOIN `operators` o ON o.`id`=c.`operator` WHERE c.`device`='.$row['device'].' AND c.`place` IN ('.implode(',',$places).') ORDER BY c.`place`')) 
						{
							while ($row = mysqli_fetch_assoc($result))
							{
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$row['title'];
								$numb[]='"'.$row['number'].'"';
							}
						}
					}
				}
				else
				{
					if ($modems[1]==-3)
					{
						$st='выключен';
						$bg='CCCCCC';
					}
					elseif ($modems[1]==-2)
					{
						$st='не&nbsp;активен';
						$bg='FF9900';
					}
					elseif ($modems[1]==-1)
					{
						$st='включение';
						$bg='99CCFF';
					}
					elseif ($modems[1]==1)
					{
						$st='активен';
						$bg='82b013';
					}
					elseif ($modems[1]==2)
					{
						$st='поиск сети...';
						$bg='FF9900';
					}
					elseif ($modems[1]==3)
					{
						$st='сеть не доступна';
						$bg='FF0000';
					}
					elseif ($modems[1]==4)
					{
						$st='ошибка';
						$bg='FF0000';
					}
					elseif ($modems[1]==0)
					{
						$st='ошибка';
						$bg='FF0000';
					}
					if (hexdec($bg)>8388607){$color='000';} else {$color='FFF';}
					$table[0]=array(
					'num'=>1,
					'place'=>$modems[0],
					'status'=>$st,
					'bg'=>$bg,
					'color'=>$color,
					);

					if ($result = mysqli_query($db, 'SELECT c.*,o.title FROM `cards` c LEFT JOIN `operators` o ON o.`id`=c.`operator` WHERE c.`device`='.$row['device']." AND c.`place`='".$modems[0]."'")) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$numbers[$row['place']]=$row['number'];
							$operators[$row['place']]=$row['title'];
							$numb[]='"'.$row['number'].'"';
						}
					}
					$curRow=$modems[0];
				}
			}
		}
		if (count($table))
		{
			$s='
<table class="table table_small">
<tr>
	<th class="sidebar">№</th>
	<th style="text-align:center;">Место</th>
	<th style="text-align:center;">Номер</th>
	<th class="sidebar">Оператор</th>
	<th style="text-align:center;">Статус</th>
</tr>';         
			$n=0;
			foreach ($table as $key=>$data)
			{
				if (!$numbers[$data['place']]){$numbers[$data['place']]='—';} else 
				{
					$prefix='+'.substr($numbers[$data['place']],0,1);
					$num=substr($numbers[$data['place']],1,255);
					$numbers[$data['place']]='<span class="note2 light" onclick="copy(\''.$numbers[$data['place']].'\');soundClick();">'.$prefix.'</span><span class="note2" onclick="copy(\''.$num.'\');soundClick();">'.$num.'</span>';
				}
				$s.='
<tr>
	<td class="sidebar" align="right">'.$data['num'].'</td>
	<td align="center"><span onclick="winOpen(this)" class="but_win" data-id="win_action" data-title="Управление номером '.strip_tags($numbers[$data['place']]).'" data-type="ajax_online_card_action.php?number='.strip_tags($numbers[$data['place']]).'&modem='.$key.'" data-height="400" data-width="600">'.$data['place'].'</span></td>
	<td align="center">'.$numbers[$data['place']].'</td>
	<td class="sidebar" align="center">'.$operators[$data['place']].'</td>
	<td id="status_'.$data['num'].'"';
	if ($data['color']){$s.=' style="color: #'.$data['color'].';background:#'.$data['bg'].'"';} 
	$s.=' align="center">'.$data['status'].'</td>
</tr>';
			}
$s.='</table>';
			return(array($s,$numb,$curRow,count($table)));
		}
	}
}

// Output of received SMS
// Вывод полученных SMS
function onlineView($numb)
{
//	$numb		Array with phone numbers to receive SMS for

	global $db;
	if ($result = mysqli_query($db, 'SELECT * FROM `sms_incoming` WHERE `number` IN ('.implode(',',$numb).') ORDER BY `id` DESC LIMIT 10')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$number='+'.$row['number'];
			$txt=$row['txt'];
			$txt=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$txt);
			$time=$row['time'];
			$sender=$row['sender'];
			if (!$id){$id=$row['id']+1;}
			$s.='<div class="term_answer_item"><div class="answer_left answer_head" style="width: 100px;">'.date('H:i:s d.m',$time).'</div><div class="answer_head">'.$sender.'</div><div class="answer_left answer_fix">'.$number.'</div><div style="margin-left: 120px;">'.$txt.'</div></div>';
		}
	}
	return(array($s,$id));
}

// Processing SMS messages before saving them to the database
// Обработка SMS перед сохранением в БД
function sms_prep($txt)
{
	$txt=preg_replace('!\+CMTI: ".*",{d}!Us','',$txt);
	$txt=str_replace("\n",'',$txt);
	$txt=str_replace("\r",'',$txt);
	$txt=str_replace('{space}',' ',$txt);
	$txt=str_replace('FEFF',' ',$txt);
	$txt=str_replace('00AB','«',$txt);
	$txt=str_replace('00BB','»',$txt);
	$txt=str_replace(' 2013 ',' – ',$txt);
	return($txt);
}

// Deleting leading zeros in the SR-Nano disk space designation
// Удаление лидирующих нулей в обозначение места на диске SR-Nano
function remove_zero($place)
{
//	$place 		Place on SR-Nano
	$l=$place[0];
	if (ord($l>57) || (strlen($place)==2 && $place[1]=='0')){return($place);}
	return($l.(int)substr($place,1,3));
}

// Deleting old flags
// Удаление старых флагов
function clear_flags($time=86400)
{
//	$time		Delete files created earlier X seconds ago

	global $root;
	$dir=$root.'flags';
	if($OpenDir=opendir($dir))
	{
		while(($file=readdir($OpenDir))!==false)
		{
			if ($file != "." && $file != "..")
			{
				$defTime=intval(time()-filectime("{$dir}/{$file}"));
				if ($defTime>$time)
				{
					 unlink("{$dir}/{$file}");
			        }
			}
		}
	}
	closedir($OpenDir); 
}

// Checking the emergency exit flag
// Проверка флага аварийного выхода
function br($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	global $root;
	if (file_exists($root.'flags/'.$file.'_'.$dev))
	{
		setlog('[DEVICE:'.$dev.'] Emergency exit!');
		unlink($root.'flags/'.$file.'_'.$dev);
		unlink($root.'flags/cron_'.$dev);
		exit();
	}
}

// Check for the flag
// Проверка наличия флага
function ts($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	global $root;
	if (file_exists($root.'flags/'.$file.'_'.$dev))
	{
		return(1);
	}
	return(0);
}

// Preparing the balance
// Форматирование баланса
function balance_out($balance,$sign='+')
{
//	$balance	Balance
//	$sign		Sign before the number

	if ($balance>0){$b=$a;}
	$balance=str_replace('.',',',$balance);
	$cent=explode(',',$cent);
	$cent[1]=substr($cent[1].'00',0,2);
	return($b.str_replace(',',"'",number_format($balance)).'.'.$cent[1]);
}


// Preparing the number
// Форматирование числа
function num_out($num) // Вывод числа
{
//	$num		Number

	$a=explode(',',round($num,2));
	$b=str_replace(',',"'",number_format($a[0])).'.'.$a[1];
	return(rtrim($b,'.'));
}

// Writing to a log file
// Запись в лог файл
function setlog($data,$file='sr')
{
//	$data		Text string
//	$file		Filename

	global $root;
	if (!$GLOBALS['set_data']['log_size']){return;}
	if ($GLOBALS['set_data']['log_size']>-1 && filesize($root.'logs/'.$file.'.log')>$GLOBALS['set_data']['log_size']*2*1024)
	{
		$txt=explode("\n",file_get_contents($root.'logs/'.$file.'.log'));
		for ($i=count($txt);$i>0;$i--)
		{
			if (trim($txt[$i]))
			{
				$t=$txt[$i]."\n".$t;
			}
			if (strlen($t)>$GLOBALS['set_data']['log_size']*1024){break;}
		}
		file_put_contents($root.'logs/'.$file.'.log',$t);
	}
	$f=fopen($root.'logs/'.$file.'.log', "a"); 
	fwrite($f,date('H:i:s d.m.Y').' '.$data."\n");
	fclose($f);
}
?>