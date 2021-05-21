<?
// ===================================================================
// Sim Roulette -> The distribution of tasks between devices
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

setlog('[TASK] Start');

if ($result = mysqli_query($db, "SELECT c.*,a.`action`,a.`id` AS `id`,a.`pool_id`,a.`card_number`,a.`data` AS `adata`,a.`progress`, d.`data` FROM `actions` a INNER JOIN `devices` d ON d.`id`=a.`device` LEFT JOIN `card2action` c ON c.`action`=a.`id` WHERE a.`status`<>'suspension' AND a.`status`<>'suspended' AND a.`device`=".$dev." ORDER BY a.`id`,c.`row`,c.`place`")) 
{
	$action=0;
	$progress=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		if ($action && $action!=$row['id']) // Following action | Следующее действие
		{
			$progress=0;
			action_close($row['id']);
			$qry='DELETE FROM `card2action` WHERE `action`='.$action;
			mysqli_query($db,$qry);
			$qry='DELETE FROM `actions` WHERE `id`='.$action;
			mysqli_query($db,$qry);
			setlog('[TASK:'.$dev.'] Deleting the action #'.$action); 
		}
		if ($progress<$row['progress']) // Подматываем задачу после остановки
		{
			$progress++;
		}
		else
		{
			sr_command_clear($dev); // Clearing the command buffer | Очиcтка буфера команд
			if (!$action)
			{
				$qry="UPDATE `actions` SET `status`='inprogress',`time`=".time()."-`timer` WHERE `id`=".$row['id'];
				mysqli_query($db,$qry);
				sr_command($dev,'card>null');
			}
			while (1)
			{
				setlog('[TASK:'.$dev.'] Start action #'.$row['id'].' ('.$stime.')');
				$step=sr_command($dev,'answer>clear');
				setlog('[TASK:'.$dev.'] Command: answer>clear Step: '.$step);
				if (!$step)
				{
					setlog('[TASK:'.$dev.'] Answer empty. Emergency exit!');
					exit();
				}
				$answer=sr_answer($dev,$step,30);
				setlog('[TASK:'.$dev.'] Answer: '.$answer);
				if (strpos($answer,'error:')!==false)
				{
					setlog('[TASK:'.$dev.'] Action ['.$row['id'].']: '.$row['action'].' - The device does not respond!'); // Агрегатор не отвечает
					br($dev,'act_'.$row['id'].'_stop');
					br($dev,'stop_'.$dev);
				}
				else
				{
					break;
				}
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
// Проверяем не приостановлена ли задача
			if ($result2 = mysqli_query($db, "SELECT id FROM `actions` a WHERE `id`=".$row['id']." AND `status`='suspension'")) 
			{
				if ($row2 = mysqli_fetch_assoc($result2))
				{
					mysqli_query($db, "UPDATE `actions` SET `timer`=".time()."-`time`, `status`='suspended' WHERE `id`=".$row['id']);
					setlog('[TASK:'.$dev.'] Action #'.$action.' suspended'); // Задача приостановлена
					$action='';
					flagDelete($dev,'cron');
					exit();
				}
			}
		}
	}
	if ($action)
	{
		action_close($action);
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

flagDelete($dev,'cron');
setlog('[TASK] Finish');
?>