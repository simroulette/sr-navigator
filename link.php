<?
// ===================================================================
// Sim Roulette -> Connection with SIM Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$_SERVER['DOCUMENT_ROOT']='';
$root="[path]";
include($root.'_func.php');

if (!$_GET['token'] || strlen($_GET['token'])!=10){exit();} // Token verification | Проверка токена

// Getting the device ID | Получение ID агрегатора
if ($result = mysqli_query($db, "SELECT * FROM `devices` WHERE `token_remote`='".(int)$_GET['token']."'")) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$id=$row['id'];
		$data=unserialize($row['data']);
	}
	else
	{
		setlog('Device not identified!','link_error');  // Устройство не опознано
		exit(); 
	}
}

$out='RESTART';

if (!flagGet($id,'connect') || flagGet($id,'connect',1)+$data['carrier_limit']>time())
{
	// Receiving a command that should be send to the device | Получение команды, которую надо отправить на устройство
	if ($result = mysqli_query($db, 'SELECT *,unix_timestamp(time) AS time FROM `link_outgoing` WHERE `device`='.(int)$id." ORDER BY `id` LIMIT 1")) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db,"DELETE FROM `link_outgoing` WHERE `id`=".$row['id']);
			$out=$row['step'].'#!#'.$row['command'];
			if (!flagGet($id,'connect'))
			{
				flagSet($id,'connect');
				flagSet($id,'connect_delay');
			}
		}
		else
		{
			$out='0#!#REQUEST';
		}
		echo '{data}'.$out;
		flagSet($id,'answer');
	}
}
elseif (flagGet($id,'connect') && flagGet($id,'connect',1)+$data['carrier_limit']*2.3<time())
{
	setlog('Ready to restart! ('.(time()-flagGet($id,'connect',1)).') '.$_GET['data'],'link_'.$id);
	flagDelete($id,'connect');
	flagDelete($id,'connect_delay');
	exit();
}
elseif (flagGet($id,'connect') && $_GET['data']=='REQUEST')
{
	setlog('Does not respond! ('.(time()-flagGet($id,'connect',1)).') '.$_GET['data'],'link_'.$id);
	exit();
} 

// Saving the response received from the device | Сохранение полученного от агрегатора ответа
if ($_GET['data']!='REQUEST')
{
	if (!$_GET['step']){$uniq=",`uniq`='".rand(1111,9999).rand(1111,9999)."'";} else {$uniq="";} // If there is an out-of-order response from the device, we generate a random number to save as a unique response in the table | Если внеочередной ответ агрегатора - генерируем случайное число, чтобы сохранить в таблице как уникальный ответ
	$qry="INSERT `link_incoming` SET
	`device`='".$id."',
	`step`=".(int)$_GET['step'].",
	`answer`='".$_GET['data']."'".$uniq;
	mysqli_query($db,$qry);
	flagDelete($id,'connect');
	flagDelete($id,'connect_delay');
	flagSet($id,'request');
}
if (strpos($_GET['data'],'+CLIP:')!==false)
{
	preg_match('!"(.*)"!Uis', $_GET['data'], $test);
	if ($test[1])
	{
		$msg['type']='RING';
		$msg['time']=time();
		$msg['data']=$test[1];
		mysqli_query($db,"UPDATE `devices` SET `msg`='".serialize($msg)."' WHERE `id`=".$id);
		if ($result = mysqli_query($db, 'SELECT `modems` FROM `modems` WHERE `device`='.$id)) 
		{
			if ($row=mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
				if ($result = mysqli_query($db, 'SELECT `number` FROM `cards` WHERE `place`="'.$modems[0].'" AND `device`='.$id)) 
				{
					if ($row=mysqli_fetch_assoc($result))
					{
						$number=$row['number'];
					}
				}
			}
		}
		if ($result = mysqli_query($db, 'SELECT `id` FROM `call_incoming` WHERE `device`='.$id.' AND `time`>'.(time()-20)." ORDER BY `id` LIMIT 1")) 
		{
			if (!mysqli_fetch_assoc($result))
			{
				mysqli_query($db,"INSERT INTO `call_incoming` SET `number`='".$number."', `incoming`='".str_replace('+','',$test[1])."', `time`=".time().",`device`=".$id);
			}
		}
	}
}
setlog('IN > '.$_GET['step'].' | '.stripslashes($_GET['data']).' OUT > '.$step.' | '.$out,'link_'.$id);
?>