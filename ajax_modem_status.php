<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
if ($_COOKIE['srn_hide']){$_GET['hide']=1;}
$s=onlineTable((int)$_GET['device'],(int)$_GET['hide']);
$numb=$s[1];
$staff=$time=flagGet($_GET['device'],'busy',1);
$staff_beginer=flagGet((int)$_GET['device'],'busy');
//if ($time)
if ($staff_beginer)
{
	$qry='SELECT `timer`,`parallel` FROM `staff` WHERE `id`='.$staff_beginer; 
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
//			$timer=flagGet($_GET['device'],'busy_timer');
		//	$time=$time+300-time();
			$time=$time+$row['timer']-time();
			$parallel=$row['parallel'];
		}
	}
}

if (!$numb || ($GLOBALS['sv_staff_id'] && !$GLOBALS['sv_staff_parallel'] && $GLOBALS['sv_staff_id']!=$staff_beginer))
{
	if ($numb && $GLOBALS['sv_staff_parallel'])
	{
		echo '#-#hide#-#'.$id.'#-#'.'#-#';
	}
	else if ($numb)
	{
		echo '#-#hide#-#'.$id.'#-#'.'#-#'.$time;
	}
	else
	{
		echo '#-#hide#-#'.$id.'#-#';
	}
	exit();
}

$s=$s[0];

$answer=$number='';
$id=$_GET['id'];

$qry='SELECT s.*,c.place FROM `sms_incoming` s 
LEFT JOIN `cards` c ON `place` IN ('.implode(',',$numb).') AND c.id=s.card_id
WHERE (c.id=s.card_id OR s.`number` IN ('.implode(',',$numb).')) AND s.`done`=1 
AND s.`id`>='.(int)$id.' ORDER BY `id` LIMIT 1';

if ($result = mysqli_query($db, $qry)) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($row['number'])
		{
			$number='+'.$row['number'];
		}
		else
		{
			$number=$row['place'];
		}
		$txt=$row['txt'];
		$txt=preg_replace('!([0-9]{4,20})!','<span class="note">$1</span>',$txt);
		$txt=preg_replace("/(([a-z]+:\/\/)?(?:[a-zа-я0-9@:_-]+\.)+[a-zа-я0-9]{2,4}(?(2)|\/).*?)([-.,:]?(?:\\s|\$))/is",'<a href="$1" target="_blank"><b>$1</b></a>$3', $txt);
		$time=$row['time'];
		$sender=$row['sender'];
		mysqli_query($db, 'UPDATE `sms_incoming` SET `readed`=1 WHERE `id`='.$row['id']); 
		$id=$row['id']+1;
	}
}
if (!$_GET['txt'])
{
	$answer=onlineView($numb);
	$id=$answer[1];
	$answer=$answer[0];
//	setlog($_GET['device'].'-'.$id,'test');
}
elseif ($number)
{
	$txt=sms_out($row['txt']);
//Akkaunt: <span class="note" onclick="copy('<span class="note" onclick="copy('555949');soundClick();">555949</span>');soundClick();"><span class="note" onclick="copy('555949');soundClick();">555949</span></span>**...<span class="note" onclick="copy('<span class="note" onclick="copy('5863');soundClick();">5863</span>');soundClick();"><span class="note" onclick="copy('5863');soundClick();">5863</span></span>) summa: <span class="note" onclick="copy('<span class="note" onclick="copy('30054');soundClick();">30054</span>');soundClick();"><span class="note" onclick="copy('30054');soundClick();">30054</span></span>.17 RUB. Poluchatel: Perevod na kartu Maste.... Kod: <span class="note" onclick="copy('<span class="note" onclick="copy('672668');soundClick();">672668</span>');soundClick();"><span class="note" onclick="copy('672668');soundClick();">672668</span></span>
//	$txt=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">xxx$1</span>',$txt);
	$answer='<div class="term_answer_item"><div class="answer_left answer_head" style="width: 120px;">'.srdate('H:i:s d.m',$time).'</div><div class="answer_head">'.$sender.'</div><div class="answer_left answer_fix">'.$number.'</div><div style="left: 140px;">'.$txt.'</div></div>';
	$sound='#-#1';
}

$msg='#-##-#';
if ($GLOBALS['sv_staff_id'])
{
	$msg='#-#'.$time;
	
//	if (flagGet($_GET['device'],'busy',0)==$GLOBALS['sv_staff_id'] || $GLOBALS['sv_staff_parallel'])
	if ($GLOBALS['sv_staff_parallel'])
	{
//		$msg.='#-#1';
		$msg='#-##-#2';
	}
	else if ($staff_beginer==$GLOBALS['sv_staff_id'])
	{
		$msg.='#-#1';
//		$msg='#-##-#1';
	}
/*
	if ($time<=0 && flagGet($_GET['device'],'busy') && !$GLOBALS['sv_staff_parallel'])
	{
		mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".(int)$_GET['device']);
		if (flagGet($_GET['device'],'cron'))
		{
			if (!flagGet($_GET['device'],'stop'))
			{
				flagSet($_GET['device'],'stop');
			}
			flagDelete($_GET['device'],'cron');
		}
		elseif (flagGet($_GET['device'],'stop',1)<time()-60)
		{
			flagDelete($_GET['device'],'stop');
		}
		flagDelete($_GET['device'],'busy');
	}
*/
}
elseif ($staff && $time>0 && !$parallel)
{
	$msg='#-#До конца сеанса <b>'.flagGet($_GET['device'],'staff').': '.$time.' сек.</b>#-#-1';
}
/*
// Ищем сообщение агрегатора
if ($result = mysqli_query($db, 'SELECT `msg` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$m=unserialize($row['msg']);
		if ($m['type']=='RING' && $m['time']>time()-10)
		{
			if ($m['number'])
			{
				if (strlen($m['number'])<7)
				{
					$incNum=' на карту <b>+'.$m['number'].'</b> с номера';
				}
				else
				{
					$incNum=' на номер <b>+'.$m['number'].'</b> с номера';
				}
			}
			else
			{
				$incNum=':';
			}
			$dev_msg='#-#'.'Входящий вызов'.$incNum.' <span class="note" onclick="copy(\''.str_replace('+','',$m['data']).'\');soundClick();">'.$m['data'].'</span><input type="button" onclick="onlineCommand(0,\'answer\',\'\',\'\');" value="Ответить" class="answer"><input type="button" onclick="onlineCommand(0,\'hangup\',\'\',\'\');" value="Сбросить" class="hangup">';
		}
	}
}
*/

// Ищем сообщение агрегатора
$qry='SELECT d.`msg`,d.`answer`,e.*,s.data AS state FROM `devices` d 
LEFT JOIN `devices_events` e ON e.`device_id`=d.`id` AND e.`event`="incomming call" AND e.`time`>'.(time()-7).'
LEFT JOIN `devices_state` s ON s.`device_id`=e.`device_id` AND s.`dev`=e.`dev`
WHERE d.`id`='.(int)$_GET['device'];
if ($result = mysqli_query($db, $qry)) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$m=unserialize($row['msg']);
		if ($row['event']=='incomming call')
		{
			$m=unserialize($row['data']);
			$st=unserialize($row['state']);
			$l=chr(substr($row['dev'],5,1)+64);
			if ($st->card>16){$l='C';}
			elseif ($st->card>8){$l='B';}
			$l.=$st->card;
			$incNum=' на карту <b>'.$l.'</b> с номера';
//			$dev_msg='#-#'.'Входящий вызов'.$incNum.' <span class="note" onclick="copy(\''.str_replace('+','',$m->number).'\');soundClick();">'.$m->number.'</span><input type="button" onclick="onlineCommand('.str_replace('modem','',$row['dev']).',\'hangup\',\'\',\'\');" value="Сброс" class="hangup">';
			$dev_msg='#-#'.'Входящий вызов'.$incNum.' <span class="note" onclick="copy(\''.str_replace('+','',$m->number).'\');soundClick();">'.$m->number.'</span><div style="margin-top: 15px;"><input type="button" onclick="onlineCommand('.str_replace('modem','',$row['dev']).',\'answer\',\'\',\'\');" value="Ответить" class="answer"><input type="button" onclick="onlineCommand('.str_replace('modem','',$row['dev']).',\'hangup\',\'\',\'\');" value="Сбросить" class="hangup"></div><div class="clr"><span></span></div>';
		}
		else if ($m['type']=='RING' && $m['time']>time()-10 && $row['answer']<time()-20)
		{
			if ($m['number'])
			{
				if (strlen($m['number'])<7)
				{
					$incNum=' на карту <b>+'.$m['number'].'</b> с номера';
				}
				else
				{
					$incNum=' на номер <b>+'.$m['number'].'</b> с номера';
				}
			}
			else
			{
				$incNum=':';
			}
//			$dev_msg='#-#'.'Входящий вызов'.$incNum.' <span class="note" onclick="copy(\''.str_replace('+','',$m['data']).'\');soundClick();">'.$m['data'].'</span><input type="button" onclick="onlineCommand('.(int)$m['modem'].',\'hangup\',\'\',\'\');" value="Сброс" class="hangup">';
			$dev_msg='#-#'.'Входящий вызов'.$incNum.' <span class="note" onclick="copy(\''.str_replace('+','',$m['data']).'\');soundClick();">'.$m['data'].'</span><div style="margin-top: 15px;"><input type="button" onclick="onlineCommand('.(int)$m['modem'].',\'answer\',\'\',\'\');" value="Ответить" class="answer"><input type="button" onclick="onlineCommand('.(int)$m['modem'].',\'hangup\',\'\',\'\');" value="Сбросить" class="hangup"></div><div class="clr"><span></span></div>';
		}
	}
}

$flags=(int)flagGet($_GET['device'],'review').';';

echo $flags.'#-#'.$s.'#-#'.$id.'#-#'.$answer.$sound.$msg.$dev_msg;
?>
