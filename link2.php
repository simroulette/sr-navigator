<?
// ===================================================================
// Sim Roulette -> Connection with SIM Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$out='RESTART';

if (!flagGet($id,'connect') || flagGet($id,'connect',1)+$data['carrier_limit']>time())
{
	// Receiving a command that should be send to the device | Получение команды, которую надо отправить на устройство
	$qry='SELECT *,unix_timestamp(time) AS time FROM `link_outgoing` WHERE `device`='.(int)$id.' ORDER BY `id` LIMIT 1';
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db,"DELETE FROM `link_outgoing` WHERE `id`=".$row['id']);
			$out=$row['command'];
			if (!flagGet($id,'connect'))
			{
				flagSet($id,'connect');
				flagSet($id,'connect_delay');
			}
		}
		else
		{
			$out='REQUEST';
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
	$data=json_decode($_GET['data']);
	if ($data->dev && $data->event)
	{
// SMS
		if ($data->dev && $data->event=='sms')
		{
			$place='';
			if ($data->dev=="modem3")
			{
				$place='C'.$data->card;
			}
			else if ($data->dev=="modem2")
			{
				$place='B'.$data->card;
			}
			else
			{
				if ($data->card>16)
				{
					$place='C'.$data->card;
				}
				else if ($data->card>8)
				{
					$place='B'.$data->card;
				}
				else
				{
					$place='A'.$data->card;
				}
			}
			if (!$data->number){$data->number=-1;}
			// Ищем номер карты
			$qry='SELECT `id`,`number`,`email` FROM `cards` WHERE (`iccid`="'.$data->iccid.'" OR `number`="'.$data->number.'" OR `place`="'.$place.'") AND `device`='.$id;
			if ($result = mysqli_query($db, $qry)) 
			{
				if ($row=mysqli_fetch_assoc($result))
				{
					$t=explode(' ',$data->time); //01.03.22 12:43:53
					$y=explode('.',$t[0]);
					$t=explode(':',$t[1]);
					mktime($t[0],$t[1],$t[2],$y[1],$y[0],$y[2]);
					sms_save('',$row['number'],$row['email'],'',$data->sender,time(),str_replace("<CR>","\n",$data->result),$row['id']);

				}
			}
		}
// Event
		$qry="REPLACE INTO `devices_events` SET
		`device_id`='".$id."',
		`dev`='".$data->dev."',
		`event`='".$data->event."',
		`result`='".$data->result."',
		`time`=".time().",
		`data`='";
		unset($data->dev);
		unset($data->result);
		$qry.=serialize($data)."'";
		mysqli_query($db,$qry);
	}
	if ($data->dev && $data->type=='state')
	{
		$qry="REPLACE INTO `devices_state` SET
		`device_id`='".$id."',
		`dev`='".$data->dev."',
		`result`='".$data->result."',
		`time`=".time().",
		`data`='";
		unset($data->dev);
		unset($data->result);
		$qry.=serialize($data)."'";
		mysqli_query($db,$qry);
	}
	if ($data->sign){$sign="`sign`='".$data->sign."',";}
	$uniq=",`uniq`='".rand(1111,9999).rand(1111,9999)."'";
	$qry="INSERT `link_incoming` SET
	`device`='".$id."',
	`step`=".(int)$_GET['step'].",
	".$sign."
	`answer`='".$_GET['data']."'".$uniq;
	mysqli_query($db,$qry);
	flagDelete($id,'connect');
	flagDelete($id,'connect_delay');
	flagSet($id,'request');
}
if (strpos($_GET['data'],'+CLIP:')!==false)
{
	$number='';
	preg_match('!"(.*)"!Uis', $_GET['data'], $test);
	if ($test[1])
	{
		if ($result = mysqli_query($db, 'SELECT `numbers` FROM `modems` WHERE `device`='.$id)) 
		{
			if ($row=mysqli_fetch_assoc($result))
			{
				$number=$msg['number']=$row['numbers'];
			}
		}
		$msg['type']='RING';
		$msg['time']=time();
		$msg['data']=$test[1];
		mysqli_query($db,"UPDATE `devices` SET `msg`='".serialize($msg)."' WHERE `id`=".$id);
		if (!$number)
		{
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
		}
		if ($result = mysqli_query($db, 'SELECT `id` FROM `call_incoming` WHERE `device`='.$id.' AND `time`>'.(time()-30)." ORDER BY `id` LIMIT 1")) 
		{
			if (!mysqli_fetch_assoc($result))
			{
				mysqli_query($db,"INSERT INTO `call_incoming` SET `number`='".$number."', `incoming`='".str_replace('+','',$test[1])."', `time`=".time().",`device`=".$id);
				ring_notification($number,str_replace('+','',$test[1]),time());
			}
		}
	}
}
setlog('IN > '.stripslashes($_GET['data']).' OUT > '.$out,'link_'.$id);
?>