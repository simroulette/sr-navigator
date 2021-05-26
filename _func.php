<?
// ===================================================================
// Sim Roulette -> Functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

Error_Reporting(~E_ALL & ~E_NOTICE & ~E_DEPRECATED);

include($root.'_config.php');
include($root.'_hardware.php');
require($root.'pdu/Pdu/Pdu.php'); 
require($root.'pdu/Utf8/Utf8.php'); 
require($root.'pdu/Exception/InvalidArgumentException.php');
$pdu = Application\Pdu\Pdu::getInstance();
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

mysqli_set_charset($db, 'utf8');

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
if ($_SERVER['DOCUMENT_ROOT'] && $GLOBALS['set_data']['admin_login'] && ($_COOKIE['srlogin']!=$GLOBALS['set_data']['admin_login'] || $_COOKIE['srpass']!=md5($GLOBALS['set_data']['admin_pass'])))
{
	$qry="SELECT `id`,`name`,`pool`,`login` FROM `staff` WHERE `login`='".$_COOKIE['srlogin']."' AND md5(`pass`) = '".$_COOKIE['srpass']."'";
	if ($result = mysqli_query($db, $qry))
	{ 
		if ($row = mysqli_fetch_array($result))
		{
			$GLOBALS['sv_owner_id']=$row['login'];
			$GLOBALS['sv_pool']=$row['pool'];
			$GLOBALS['sv_user_id']=1;
			$GLOBALS['sv_staff_id']=$row['id'];
			$GLOBALS['sv_realname']=$row['name'];

			if (strpos($_SERVER['REQUEST_URI'],'online.php')===false && strpos($_SERVER['REQUEST_URI'],'ajax')===false)
			{
				header('location:online.php');
				exit();
			}
		}
		else
		{
			include('_login.php');
			exit();
		}
	}
	else
	{
		include('_login.php');
		exit();
	}
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
<table height="100%" width="100%">
<tr class="status"><td width="1%" style="background:#171717;border-bottom: 1px solid #505050;" class="sidebar"></td><td id="status"></td></tr><tr><td bgcolor="#363636" class="sidebar"><a href="index.php"><img src="sr/logo.gif" class="logo" title="На главную страницу"></a></td><td class="head"><div class="mobilemenu" id="m" onclick="menuToggle(this)"><div class="bar1"></div><div class="bar2"></div><div class="bar3"></div></div><? if ($GLOBALS['sv_user_id']){?><div class="sidebar" style="float: right;"><?=($GLOBALS['sv_owner_id']?$GLOBALS['sv_owner_id']:$GLOBALS['set_data']['admin_login'])?> [<a href="index.php?mode=logout">выход</a>]</div><? } ?></td></tr><tr><td height=99% class="sidebar panel" valign="top">

<div id="menu">
<?
	if (!$GLOBALS['sv_owner_id'])
	{
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
	}
?>
</div>
</div></td><td valign="top" style="position: relative;"><em class="help" title="Помощь" onclick="help();"></em>
<h1><?=$title?></h1>
<?
}

// Output of the footer WEB page
// Вывод низа страницы
function sr_footer()
{
?><br></td></tr>
<tr><td class="bottom sidebar">© <a href="http://x0.ru">X0 Systems</a>, 2016 — <?=srdate('Y')?></td>
<td class="bottom" align="right"><? if ($sv_user_id){?><div class="extinfo" style="float: left;"><?=($GLOBALS['sv_owner_id']?$GLOBALS['sv_owner_id']:$GLOBALS['set_data']['admin_login'])?> [<a href="index.php?mode=logout">выход</a>]</div><? } ?></td>
</tr>
</table>
</body>
</html><?
}

function statusAtr($dev,$status)
{
	// Проверка на Online
	$access=flagGet($dev,'answer',1);
	if ($access+60<time())
	{
		$st='Offline';
		$bg='FF0000';
		$color='FFFFFF';
		if (!$access)
		{
			$st.=': ∞';
		} 
		else 
		{
			$st.=': '.time_calc(time()-$access);
		}
	}
	else
	{
		$color='000';
		if ($status==-3)
		{
			$st='выключен';
			$bg='CCCCCC';
		}
		elseif ($status==-2)
		{
			$st='не&nbsp;активен';
			$bg='FF9900';
		}
		elseif ($status==-1)
		{
			$st='включение';
			$bg='99CCFF';
		}
		elseif ($status==1)
		{
			$st='активен';
			$bg='82b013';
		}
		elseif ($status==2)
		{
			$st='регистрация';
			$bg='eab50e';
		}
		elseif ($status==3)
		{
			$st='сеть недоступна';
			$bg='FF0000';
		}
		elseif ($status==4)
		{
			$st='ошибка';
			$bg='FF0000';
			$color='FFF';
		}
		elseif ($status==0)
		{
			$st='ошибка';
			$bg='FF0000';
			$color='FFF';
		}
	}
	return(array($color,$bg,$st));
}

function statusAtrApi($dev,$status)
{
	// Проверка на Online
	$access=flagGet($dev,'answer',1);
	if ($access+60<time())
	{
		$st='OFFLINE';
	}
	else
	{
		if ($status==-3 || $status==-1 || $status==2)
		{
			$st='CONNECTING';
		}
		elseif ($status==-2 || $status==3 || $status==4 || !$status)
		{
			$st='ERROR';
		}
		elseif ($status==1)
		{
			$st='WAIT_SMS';
		}
	}
	return($st);
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
				$oper=array();
				$operators=array();
				if ($result2 = mysqli_query($db, 'SELECT * FROM `operators`
				ORDER BY `title`')) 
				{
					while ($row2 = mysqli_fetch_assoc($result2))
					{
						$oper[$row2['name']]=$row2['title'];
					}
				}

				$modems=unserialize($row['modems']);


				if ($row['model']=='SR-Train')
				{
					foreach ($modems AS $key => $status)
					{
						$curRow=$status[0];

                                                $ar=statusAtr($dev,$status[1]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						
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
						if ($result = mysqli_query($db, 'SELECT c.* FROM `cards` c WHERE c.`device`='.$row['device'].' AND c.`place` IN ('.implode(',',$places).') ORDER BY c.`place`')) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$oper[$row['operator']];
								$names[$row['place']]=$row['title'];
								$numb[]='"'.$row['number'].'"';
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}
				else if ($row['model']=='SR-Organizer')
				{
					$n=1;
					for ($i=1;$i<9;$i++)
					{
						$table[$i]=array(
						'num'=>$i,
						'place'=>'1-'.$i,
						'status'=>$st,
						'bg'=>'DDD',
						'status'=>'—',
						'color'=>'000000',
						);
					}
					for ($i=1;$i<9;$i++)
					{
						$table[8+$i]=array(
						'num'=>(8+$i),
						'place'=>'2-'.$i,
						'status'=>$st,
						'bg'=>'DDD',
						'status'=>'—',
						'color'=>'000000',
						);
					}
					foreach ($modems AS $key => $status)
					{
                                                $ar=statusAtr($dev,$status[1]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$place=$n.'-'.$status[0];
						$places[]="'".$place."'";
						$table[($n-1)*8+$status[0]]=array(
						'num'=>((($n-1)*8)+$status[0]),
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if (count($places))
					{
						$qry='SELECT c.* FROM `cards` c WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';
						if ($result = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$oper[$row['operator']];
								$names[$row['place']]=$row['title'];
								$numb[]='"'.$row['number'].'"';
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}
				else if ($row['model']=='SR-Box-8')
				{
					foreach ($modems AS $key => $status)
					{
						$curRow=$status[0];

                                                $ar=statusAtr($dev,$status[1]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						
						$realPlace=$place=$status[0].'-'.$key;
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
						if ($result = mysqli_query($db, 'SELECT c.* FROM `cards` c WHERE c.`device`='.$row['device'].' AND c.`place` IN ('.implode(',',$places).') ORDER BY c.`place`')) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$oper[$row['operator']];
								$names[$row['place']]=$row['title'];
								$numb[]='"'.$row['number'].'"';
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}
				else
				{
                                        $ar=statusAtr($dev,$modems[1]);
				        $color=$ar[0];
					$bg=$ar[1];
					$st=$ar[2];
					$table[0]=array(

					'num'=>1,
					'place'=>$modems[0],
					'status'=>$st,
					'bg'=>$bg,
					'color'=>$color,
					);

					if ($result = mysqli_query($db, 'SELECT c.* FROM `cards` c WHERE c.`device`='.$row['device']." AND c.`place`='".$modems[0]."'")) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$numbers[$row['place']]=$row['number'];
							$operators[$row['place']]=$oper[$row['operator']];
							$names[$row['place']]=$row['title'];
							$numb[]='"'.$row['number'].'"';
						}
						else
						{
							$numb[]='';
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
	<th class="sidebar">Имя</th>
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
	<td class="sidebar">'.($names[$data['place']]?$names[$data['place']]:'—').'</td>
	<td align="center">'.($data['status']!='—'?'<span onclick="winOpen(this)" class="but_win" data-id="win_action" data-title="Управление номером '.strip_tags($numbers[$data['place']]).'" data-type="ajax_online_card_action.php?number='.strip_tags($numbers[$data['place']]).'&modem='.$key.'" data-height="400" data-width="600">'.$data['place']:$data['place']).'</span></td>
	<td align="center">'.$numbers[$data['place']].'</td>
	<td class="sidebar" align="center">'.($operators[$data['place']]?$operators[$data['place']]:'—').'</td>
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
	if ($result = mysqli_query($db, 'SELECT * FROM `sms_incoming` WHERE `number` IN ('.implode(',',$numb).') AND `done`=1 ORDER BY `id` DESC LIMIT 10')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$number='+'.$row['number'];
			$txt=$row['txt'];
			$txt=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$txt);
			$txt=preg_replace("/(([a-z]+:\/\/)?(?:[a-zа-я0-9@:_-]+\.)+[a-zа-я0-9]{2,4}(?(2)|\/).*?)([-.,:]?(?:\\s|\$))/is",'<a href="$1" target="_blank"><b>$1</b></a>$3', $txt);
			$time=$row['time'];
			$sender=$row['sender'];
			if (!$id){$id=$row['id']+1;}
			$s.='<div class="term_answer_item"><div class="answer_left answer_head" style="width: 100px;">'.srdate('H:i:s d.m',$time).'</div><div class="answer_head">'.$sender.'</div><div class="answer_left answer_fix">'.$number.'</div><div style="margin-left: 120px;">'.$txt.'</div></div>';
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
	if (ord($l)<58)
	{
		return($place);
	}
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

// Autoformazione form fields
// Автоформирование полей формы
function auto_field($title,$name,$type,$desc='',$data='',$comment='')
{
//	$name 		Field name
//	$type		Field type
//	$data		Field data
	$str= '<div style="margin-bottom: 7px;"><span title="'.$desc.'">'.$title.'</span>';
	if ((int)$type)
	{
		$str.= '<input type="text" id="'.$name.'" name="'.$name.'" maxlength="'.$type.'" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='minitxt')
	{
		$str.= '<textarea name="'.$name.'" id="'.$name.'" class="form-control" maxlength="1000" style="height:70px;">'.$data.'</textarea>';
	}
	elseif ($type=='txt')
	{
		$str.= '<textarea name="'.$name.'" id="'.$name.'" class="form-control" maxlength="100000" style="height:200px;">'.$data.'</textarea>';
	}
	elseif ($type=='url')
	{
		$str.= '<input type="text" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='email')
	{
		$str.= '<input type="email" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='digit')
	{
		$str.= '<input type="number" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='number')
	{
		$str.= '<input type="number" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='radio')
	{
		if ($data){$c=' checked';$d='';} else {$d=' checked';$c='';}
		$str.= 'On <input type="radio" name="'.$name.'" id="'.$name.'" value="1"'.$c.'>&nbsp;&nbsp;&nbsp;';
		$str.= 'Off <input type="radio" name="'.$name.'" id="'.$name.'" value="0"'.$d.'>';
	}
	elseif ($type=='check')
	{
		$str.= '<input type="checkbox" id="'.$name.'" name="'.$name.'" class="make-switch" value="1" data-on-color="success" data-off-color="danger"';
		if ($data){$str.= 'checked';}
		$str.= '>';
	}
	$str.= '</div>';
	if ($comment)
	{
		$str.= '<div class="help_block">'.$comment.'</div>';
	}
	return($str);
}

// Checking the emergency exit flag
// Проверка флага аварийного выхода
function br($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	if (flagGet($dev,'stop'))
	{
		setlog('[DEVICE:'.$dev.'] Emergency exit!');
		flagSet($dev,'stop',2);
		flagDelete($dev,'cron');
		exit();
	}

	if (flagGet($dev,$file))
	{
		setlog('[DEVICE:'.$dev.'] ['.$file.'] exit!');
		flagDelete($dev,$file);
		flagDelete($dev,'stop');
		flagDelete($dev,'cron');
		exit();
	}
}

// Check for the flag
// Проверка наличия флага
function ts($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	setlog('Вызов отмененной функции ts','error');
}

// Preparing the balance
// Форматирование баланса
function balance_out($balance,$sign='+')
{
//	$balance	Balance
//	$sign		Sign before the number

	if ($balance>0){$b=$a;}
	$balance=str_replace('.',',',$balance);
	$cent=explode(',',$balance);
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

function time_calc($time)
{
	$m=round($time/60);
	if ($m==0)
	{
		return('∞');
	}
	$h=0;
	while ($m>59)
	{
		$h++;
		$m-=60;
	}
	while ($h>23)
	{
		$d++;
		$h-=24;
	}
	if (!$m){$m='';} else {$m.='м';}
	if ($d)
	{
		return($d.'д '.$h.'ч '.$m);
	}
	else if ($h)
	{
		return($h.'ч '.$m);
	}
	return($m);
}

function flagSet($dev,$name,$value=1)
{
//	$dev		Device ID

	global $db;

	mysqli_query($db, 'REPLACE INTO `flags` SET `name`="'.$name.'", `device`='.(int)$dev.', `value`='.(int)$value.', `time`='.time());
}

function flagGet($dev,$name,$time=0)
{
//	$dev		Device ID

	global $db;

	if ($result = mysqli_query($db, 'SELECT `time`,`value` FROM `flags` WHERE `device`='.(int)$dev.' AND `name`="'.$name.'"')) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			if ($time)
			{
				return($row['time']);
			}
			else
			{
				return($row['value']);
			}
		}
	}
}

function flagDelete($dev,$name)
{
//	$dev		Device ID

	global $db;

	mysqli_query($db, 'DELETE FROM `flags` WHERE `name`="'.$name.'" AND `device`='.(int)$dev);
}

function srtime($format='',$time=0)
{
	if ($format[0]=='d'){$format='d.m.Y';}
	elseif ($format[0]=='t'){$format='H:i';}
	elseif ($format[0]=='s'){$format='H:i:s';}
	elseif ($format[0]=='a'){$format='d.m.Y H:i:s';}
	else {$format='d.m.Y H:i';}
	return(srdate($format,$time));
}

function srdate($format,$time=0)
{
        if (!$time){$time=time();}
	if ($GLOBALS['sv_timezone']!=255)
	{
		$time=$time+3600*($GLOBALS['sv_timezone']-3);
	}
	return(date($format,$time));
}

function operator($operator)
{
	$operator=explode(' ',strtoupper($operator));
	return($operator[0]);	
}

function operator_select()
{
	global $db;
	$operators=array();

	$qry='SELECT * FROM `operators` ORDER BY `user_id` DESC, CHAR_LENGTH(`name`) DESC';
	if ($result = mysqli_query($db, $qry)) 
	{
		$name='';
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($name!=$row['name'])
			{
				$operators[]=$row['name'];
			}
			$name=$row['name'];
		}
	}
	if ($result = mysqli_query($db, 'SELECT c.`id`,c.`operator` FROM `cards` c')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			foreach ($operators AS $name)
			{
				if (strpos($row['operator'],$name)!==false)
				{ 			
					$qry="UPDATE `cards` SET `operator`='".$row['operator']."' WHERE `id`=".$row['id'];
					mysqli_query($db,$qry);
				}
			}			
		}
	}
}

function trim_number($number)
{
	$number=str_replace('-','',$number);
	$number=str_replace('(','',$number);
	$number=str_replace(')','',$number);
	$number=str_replace(' ','',$number);
	return($number);
}

function trim_balance($balance)
{
        preg_match('!(minus|-)!i', $balance, $minus);
        preg_match('!([0-9]{1,5})([\.|\,])*([0-9]{1,2})*!', $balance, $test);
	$balance=str_replace(',','.',trim(trim($test[1].$test[2].$test[3],'.')));
	if ($minus[1]){$balance=$balance*-1;}
	return($balance);
}

?>