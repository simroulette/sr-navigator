<?
// ===================================================================
// Sim Roulette -> Running tasks every minute
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$root="[path]";
include($root.'_func.php');

$qry='';
$dev=array();
setlog('[CRON] Start');
clear_flags(); // Deleting old flags | Удаление старых флагов

// Checking for online actions | Проверка наличия online-задач
if ($result = mysqli_query($db, "SELECT m.*,d.`model`,d.`id` AS device FROM `modems` m INNER JOIN `devices` d ON m.`device`=d.`id`")) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!file_exists($root."flags/cron_".$row['device']))
		{
			if ($row['model']=='SR-Train'){include($root."_sr-train.php");}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'){include($root."_sr-nano.php");}
			unlink($root.'flags/stop_'.$row['device']);
			$modems=unserialize($row['modems']);
			$mod=array();
		        foreach ($modems AS $key =>$data)
			{
				$data[1]=-1;
				$mod[]=$key;
				$curRow=$data[0];
				$modems[$key]=$data;
			}
			mysqli_query($db, "REPLACE INTO `modems` SET `device`=".$row['device'].", `modems`='".serialize($modems)."', `time`=".time()); 

			$answer=sr_command($row['device'],'version',30); 
			if (strpos($answer,'error:')!==false)
			{
				setlog('[CRON:'.$row['device'].'] The device does not respond!'); // Устройство не отвечает
				exit();
			}
			file_put_contents($root."flags/cron_".$row['device'],1); // Setting the employment flag | Установка флага занятости

			online_mode($row['device'], $curRow, implode(',',$mod));
		}
	        $dev[]=$row['device'];
	}
}

if (count($dev))
{
	$qry=' AND a.device NOT IN('.implode(',',$dev).')';
}

// Checking for actions | Проверка наличия задач
if ($result = mysqli_query($db, "SELECT d.* FROM `actions` a INNER JOIN `devices` d ON a.`device`=d.`id` WHERE a.`status`<>'inprogress'".$qry))
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$dev=$row['id'];
		$subResult=mysqli_query($db, "SELECT count(id) AS count FROM `actions` WHERE a.`device`=".$dev);
		$subRow = mysqli_fetch_assoc($subResult);
		if ($subRow['count']==1)
		{
			unlink($root.'flags/cron_'.$dev);
                }
		unlink($root.'flags/stop_'.$dev);

		if (!file_exists($root."flags/cron_".$row['id']))
		{
			if ($row['model']=='SR-Train'){include($root."_sr-train.php");}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'){include($root."_sr-nano.php");}
			file_put_contents($root."flags/cron_".$row['id'],1); // Setting the employment flag | Установка флага занятости
			include("_task.php");
			exit();
		}
	}
}

setlog('[CRON] Finish');

?>