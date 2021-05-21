<?
// ===================================================================
// Sim Roulette -> Running tasks every minute
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$stime=time();
if ((int)$argv[1]){sleep($argv[1]);}

$cron=1;

$root="[path]";
include($root.'_func.php');

$qry='';
$dev=array();
setlog('[CRON] Start');
clear_flags(); // Deleting old flags | Удаление старых флагов

// Checking for online actions | Проверка наличия online-задач
$qry="SELECT m.*,d.`model`,d.`id` AS device,d.`data`,d.`serial_time` FROM `modems` m 
	INNER JOIN `devices` d ON m.`device`=d.`id` AND d.`id`<>33 
	LEFT JOIN `flags` f ON f.`device`=d.`id` AND f.`name`='cron' 
	WHERE f.`name` IS NULL
	ORDER BY d.`pause`
";
if ($result = mysqli_query($db, $qry)) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (flagGet($row['device'],'stop')!=1)// && !flagGet($row['device'],'cron'))
		{
			flagSet($row['device'],'cron'); // Setting the employment flag | Установка флага занятости
			if ($row['model']=='SR-Train'){include($root."_sr-train.php");}
			elseif ($row['model']=='SR-Box-8'){include($root."_sr-box.php");}
			elseif ($row['model']=='SR-Organizer'){include($root."_sr-organizer.php");}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'){include($root."_sr-nano.php");}
			flagDelete($row['device'],'stop');
			if ($row['model']=='SR-Train' || $row['model']=='SR-Box-8')
			{
				$modems=unserialize($row['modems']);
				$mod=array();
			        foreach ($modems AS $key =>$data)
				{
					$data[1]=-1;
					$mod[]=$key;
					$curRow=$data[0];
					$modems[$key]=$data;
				}
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".time()." WHERE `device`=".$row['device']); 
				$answer=sr_command($row['device'],'answer>clear',30); 
				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond!'); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					online_mode($row['device'], $curRow, implode(',',$mod));
				}
			}
			elseif ($row['model']=='SR-Organizer')
			{
				$modems=unserialize($row['modems']);
				$mod=array();
			        foreach ($modems AS $key =>$data)
				{
					if ($data[1]!='1'){$data[1]=-1;}
					$modems[$key]=$data;
				}
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".time()." WHERE `device`=".$row['device']); 
				$answer=sr_command($row['device'],'answer>clear',30); 
				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond!'); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					online_mode($row['device'], $modems);
				}
			}
			else
			{
				flagSet($row['device'],'cron'); // Setting the employment flag | Установка флага занятости
				$modems=unserialize($row['modems']);
				$modem[1]=-1;
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".time()." WHERE `device`=".$row['device']); 

				sr_answer_clear($row['device']);
				$answer=sr_command($row['device'],'answer>clear',30); 

				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond!'); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					getSerial($row['device'],$row['serial_time']);
					online_mode($row['device'], $modems, unserialize($row['data']));
				}

			}
		}
	        $dev[]=$row['device'];
	}
}

$qry='';
if (count($dev))
{
	$qry=' AND a.device NOT IN('.implode(',',$dev).')';
}

$qry="SELECT d.* FROM `actions` a INNER JOIN `devices` d ON a.`device`=d.`id` AND d.`id`<>33 WHERE a.`status`='waiting'".$qry;

// Checking for actions | Проверка наличия задач
if ($result = mysqli_query($db, $qry))
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$dev=$row['id'];
		$subResult=mysqli_query($db, "SELECT count(id) AS count FROM `actions` WHERE `device`=".$dev);
		$subRow = mysqli_fetch_assoc($subResult);
		if ($subRow['count']==1)
		{
			flagDelete($dev,'cron');
                }
		flagDelete($dev,'stop');

		if (flagGet($row['device'],'stop')!=1 && !flagGet($dev,'cron'))
		{
			if ($row['model']=='SR-Train'){include($root."_sr-train.php");}
			elseif ($row['model']=='SR-Box-8'){include($root."_sr-box.php");}
			elseif ($row['model']=='SR-Organizer'){include($root."_sr-organizer.php");}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'){include($root."_sr-nano.php");}
			$uniq=rand(11111111,99999999);
			flagSet($dev,'cron'); // Setting the employment flag | Установка флага занятости
			include("_task.php");
			exit();
		}
	}
}

setlog('[CRON] Finish');
// Монитор SMS на предмет необходимости склеивать
sms_monitor();
autoStop();

?>