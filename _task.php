<?
// ===================================================================
// Sim Roulette -> The distribution of tasks between devices
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

setlog('[TASK] Start');

if ($result = mysqli_query($db, "SELECT c.*,a.`action`,a.`id` AS `id`,a.`pool_id`,a.`card_number`,a.`data` AS adata, d.data FROM `actions` a INNER JOIN `devices` d ON d.`id`=a.`device` LEFT JOIN `card2action` c ON c.`action`=a.`id` WHERE a.`device`=".$dev." ORDER BY a.`id`,c.`row`,c.`place`")) 
{
	$action=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$action)
		{
			$qry="UPDATE `actions` SET `status`='inprogress' WHERE `id`=".$row['id'];
			mysqli_query($db,$qry);
		}
		if ($action && $action!=$row['id']) // Following action | Следующее действие
		{
			$qry='DELETE FROM `card2action` WHERE `action`='.$action;
			mysqli_query($db,$qry);
			$qry='DELETE FROM `actions` WHERE `id`='.$action;
			mysqli_query($db,$qry);
			setlog('[TASK:'.$dev.'] Deleting the action #'.$action); // Агрегатор не отвечает
			unlink($root.'flags/cron_'.$dev);
		}
		sr_command_clear($dev); // Clearing the command buffer | Очиcтка буфера команд
		$answer=sr_command($dev,'version',60); 
		if (strpos($answer,'error:')!==false)
		{
			setlog('[TASK:'.$dev.'] The device does not respond!'); // Агрегатор не отвечает
			unlink($root.'flags/cron_'.$dev);
			exit();
		}
		if (strpos($row['action'],'dev_')!==false)
		{
			$f=$row['action'];
			$f($dev);
		}
		else
		{
			sim_link($dev,unserialize($row['data']),$row['row'],$row['place'],$row['id'],$row['action'],unserialize($row['adata']));
			$pool_id=$row['pool_id'];
			$card_number=$row['card_number'];
			if ($pool_id) // Change of status | Смена статуса
			{
				$qry="UPDATE `pools` SET `status`='inprogress' WHERE `id`=".$pool_id;
			}
			elseif ($card_number)
			{
				$qry="UPDATE `cards` SET `status`='inprogress' WHERE `number`='".$card_number."'";
			}

		}
		if ($action!=$row['id'])
		{
			if ($pool_id) // Change of status | Смена статуса
			{
				$qry="UPDATE `pools` SET `status`='inprogress' WHERE `id`=".$pool_id;
			}
			elseif ($card_number)
			{
				$qry="UPDATE `cards` SET `status`='inprogress' WHERE `number`='".$card_number."'";
			}
			mysqli_query($db,$qry);
		}
		$action=$row['id'];
	}
	if ($action)
	{
		$qry='DELETE FROM `card2action` WHERE `action`='.$action;
		mysqli_query($db,$qry);
		$qry='DELETE FROM `actions` WHERE `id`='.$action;
		mysqli_query($db,$qry);
		setlog('[TASK:'.$dev.'] Deleting the action #'.$action); // Агрегатор не отвечает

		if ($pool_id) // Change of status | Смена статуса
		{
			$qry="UPDATE `pools` SET `status`='free' WHERE `id`=".$pool_id;
		}
		else
		{
			$qry="UPDATE `cards` SET `status`='free' WHERE `number`='".$card_number."'";
		}
		mysqli_query($db,$qry);
	}
}

unlink($root."flags/cron_".$dev);
setlog('[TASK] Finish');
?>