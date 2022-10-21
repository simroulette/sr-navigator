<?
// ===================================================================
// Sim Roulette -> Running tasks every minute
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$license='free';
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
	INNER JOIN `devices` d ON m.`device`=d.`id` 
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
			elseif ($row['model']=='SR-Box-Bank'){include($root."_sr-box-bank.php");}
			elseif ($row['model']=='SR-Box-2'){include($root."_sr-box-2-bank.php");}
			elseif ($row['model']=='SR-Box-2-Bank'){include($root."_sr-box-2-bank.php");}
			elseif ($row['model']=='SR-Box-8-Smart'){include($root."_sr-organizer-smart.php");}
			elseif ($row['model']=='SR-Board'){include($root."_sr-board.php");}
			elseif ($row['model']=='SR-Organizer'){include($root."_sr-organizer.php");}
			elseif ($row['model']=='SR-Organizer-Smart'){include($root."_sr-organizer-smart.php");}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'){include($root."_sr-nano.php");}
			flagDelete($row['device'],'stop');
			$modemTime=time();
			setlog('[CRON:'.$row['device'].'] Model >> '.$row['model'],'link_'.$row['device']); // Агрегатор не отвечает
//					online_mode($row['device'], $curRow, implode(',',$mod), unserialize($row['data']), $modemTime);


					get_values();

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
					setlog('[CRON:'.$row['device'].'] The device does not respond!','link_'.$row['device']); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					online_mode($row['device'], $curRow, implode(',',$mod), $modemTime, unserialize($row['data']));
//setlog('!!!'.$modemTime,'link_'.$row['device']);
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
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".$modemTime." WHERE `device`=".$row['device']); 
//				mysqli_query($db, "REPLACE INTO `modems` SET `device`=".$row['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
				$answer=sr_command($row['device'],'answer>clear',30); 
				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond!','link_'.$row['device']); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					online_mode($row['device'], $modems, $modemTime);
				}
			}
			elseif ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
			{
				setlog('[CRON:'.$row['device'].'] SMART','link_'.$row['device']); // Агрегатор не отвечает
				$modems=unserialize($row['modems']);
				$mod=array();
			        foreach ($modems AS $key =>$data)
				{
					if ($data[1]!='1'){$data[1]=-1;}
					$modems[$key]=$data;
				}
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".$modemTime." WHERE `device`=".$row['device']); 
				$answer=sr_command_smart($row['device'],'.answer.clear:sign=test','test',30); 
				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond! [smart]','link_'.$row['device']); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
				}
				else
				{
					online_mode($row['device'], $modems, $modemTime);
				}
			}
			else
			{
				setlog('[CRON:'.$row['device'].'] OTHER','link_'.$row['device']); // Агрегатор не отвечает
				flagSet($row['device'],'cron'); // Setting the employment flag | Установка флага занятости
				$modems=unserialize($row['modems']);
				$modem[1]=-1;
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."', `time`=".$modemTime." WHERE `device`=".$row['device']); 
//				mysqli_query($db, "REPLACE INTO `modems` SET `device`=".$row['device'].", `modems`='".serialize($modems)."', `time`=".time()); 

				sr_answer_clear($row['device']);
				$answer=sr_command($row['device'],'answer>clear',30); 

				if (strpos($answer,'error:')!==false)
				{
					setlog('[CRON:'.$row['device'].'] The device does not respond!','link_'.$row['device']); // Агрегатор не отвечает
					mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".$row['device']); 
					flagDelete($row['device'],'cron');
//					setlog('[CRON-'.$stime.'] *** ONLINE =>'.$row['device'].' Флаг удален!!!!!!!!!!!!!!!','link_2');
				}
				else
				{
					getSerial($row['device'],$row['serial_time']);
//					setlog('[CRON-'.$stime.'] +++ ONLINE =>'.$row['device'],'link_2');
					online_mode($row['device'], $modems, $modemTime, unserialize($row['data']));
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

//$qry="SELECT d.* FROM `actions` a INNER JOIN `devices` d ON a.`device`=d.`id` AND d.`id`<>33 WHERE a.`status`='waiting'".$qry;
$qry="SELECT d.* FROM `actions` a 
INNER JOIN `devices` d ON a.`device`=d.`id` 
WHERE a.`status`='waiting'".$qry;

setlog('***********************'.$qry,'task');

// Checking for actions | Проверка наличия задач
if ($result = mysqli_query($db, $qry))
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$dev=$row['id'];
		$subResult=mysqli_query($db, "SELECT count(id) AS count FROM `actions` WHERE `device`=".$dev);
		$subRow = mysqli_fetch_assoc($subResult);
		if ($subRow['count']==1) // Почему-то было так!
//		if ($subRow['count']>0)
		{
			flagDelete($dev,'cron');
                }
//		unlink($root.'flags/stop_'.$dev);
		flagDelete($dev,'stop');
//setlog(' IF STOP?','task');

		if (flagGet($row['device'],'stop')!=1 && !flagGet($dev,'cron'))
		{
//setlog(' NON STOP?','task');
			if ($row['model']=='SR-Train'){include($root."_sr-train.php");}
			elseif ($row['model']=='SR-Box-8'){include($root."_sr-box.php");}
			elseif ($row['model']=='SR-Box-Bank'){include($root."_sr-box-bank.php");}
			elseif ($row['model']=='SR-Box-2'){include($root."_sr-box-2-bank.php");}
			elseif ($row['model']=='SR-Box-2-Bank'){include($root."_sr-box-2-bank.php");}
			elseif ($row['model']=='SR-Board'){include($root."_sr-board.php");}
			elseif ($row['model']=='SR-Organizer'){include($root."_sr-organizer.php");}
			elseif ($row['model']=='SR-Organizer-Smart'){include($root."_sr-organizer-smart.php");}
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
log_clear();
autoStop();

?>