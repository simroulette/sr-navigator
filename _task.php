<?
// ===================================================================
// Sim Roulette -> The distribution of tasks between devices
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

setlog('[TASK] Start > DEV:'.$dev);

$qry="SELECT c.*,a.`action`,a.`id` AS `id`,a.`pool_id`,a.`card_number`,a.`data` AS `adata`,a.`progress`, d.`data`,d.`model` FROM `actions` a 
INNER JOIN `devices` d ON d.`id`=a.`device` 
LEFT JOIN `card2action` c ON c.`action`=a.`id` 
WHERE a.`status`<>'suspension' AND a.`status`<>'suspended' AND a.`status`<>'preparing' AND a.`device`=".$dev." ORDER BY a.`id`,c.`row`,c.`place`";
//setlog('!!! Task: '.$qry,'task');

if ($taskResult = mysqli_query($db, $qry)) 
{
setlog('!!! Action: '.$action.' ACTION_START: !!!','link_'.$dev);

	$action=0;
	$progress=0;
	while ($taskRow = mysqli_fetch_assoc($taskResult))
	{
setlog('TASK RESULT:'.print_r($taskRow,1),'task');
		if ($result2 = mysqli_query($db, "SELECT `id` FROM `actions` WHERE `id`=".$taskRow['id'])) 
		{
			if (!mysqli_fetch_assoc($result2))
			{
				setlog('[TASK:'.$dev.'] No action!');
				exit();
			}
		}

		if ($action && $action!=$taskRow['id']) // Following action | Следующее действие
		{
			$progress=0;
			action_close($taskRow['id']);
			$qry='DELETE FROM `card2action` WHERE `action`='.$action;
			mysqli_query($db,$qry);
			$qry='DELETE FROM `actions` WHERE `id`='.$action;
			mysqli_query($db,$qry);
			setlog('[TASK:'.$dev.'] Deleting the action #'.$action); 
		}
		if ($progress<$taskRow['progress']) // Подматываем задачу после остановки
		{
			$progress+=count(explode(',',$taskRow['place']));
//setlog(count(explode(',',$taskRow['place'])).'->'.$progress."<".$taskRow['progress']);
		}
		else
		{
			setlog('[TASK:'.$dev.'] COMMANDS CLEAR','link_'.$dev); 
			sr_command_clear($dev); // Clearing the command buffer | Очиcтка буфера команд
			if (!$action)
			{
				$qry="UPDATE `actions` SET `status`='inprogress',`time`=".time()."-`timer` WHERE `id`=".$taskRow['id'];
				mysqli_query($db,$qry);
//				if (($taskRow['model']=='SR-Nano-500' || $taskRow['model']=='SR-Nano-1000') && $taskRow['action']!='dev_init')
				if (($taskRow['model']=='SR-Nano-500' || $taskRow['model']=='SR-Nano-1000') && (strpos($taskRow['action'],'dev_')===false))
				{
					sr_command($dev,'card>null'); // 25.08.2021
				}
				elseif ($taskRow['model']!='SR-Organizer-Smart' && $taskRow['action']!='dev_init')
				{
					sr_command($dev,'answer>clear');
					setlog('!!!!!!!!!!![TEST1]!!!!!!!!!!!!!','link_'.$dev);
				}
			}
			while (1)
			{
				setlog('[TASK:'.$dev.'] Start action #'.$taskRow['id'].' ('.$stime.')');

				if ($taskRow['model']=='SR-Organizer-Smart')
				{
					$answer=sr_command_smart($dev,'.answer.clear:sign=SRN_test','SRN_test',30); 
				}
				else
				{
					$step=sr_command($dev,'answer>clear');
					setlog('!!!!!!!!!!![TEST2]!!!!!!!!!!!!!','link_'.$dev);
					setlog('[TASK:'.$dev.'] Command: answer>clear Step: '.$step);
					if (!$step)
					{
						setlog('[TASK:'.$dev.'] Answer empty. Emergency exit!','link_'.$dev);
						exit();
					}
					$answer=sr_answer($dev,$step,30);
					setlog('[TASK:'.$dev.'] Answer: '.$answer);
				}
				if (strpos($answer,'error:')!==false)
				{
					setlog('[TASK:'.$dev.'] Action ['.$taskRow['id'].']: '.$taskRow['action'].' - The device does not respond!'); // Агрегатор не отвечает
					br($dev,'act_'.$taskRow['id'].'_stop');
					br($dev,'stop_'.$dev);
				}
				else
				{
					break;
				}
			}

			if (strpos($taskRow['action'],'dev_')!==false)
			{
				$f=$taskRow['action'];
//setlog($f,'link_'.$dev);
				$f($dev,$taskRow['id']);
			}
			else
			{
				if ($taskRow['model']=='SR-Box-Bank' || $taskRow['model']=='SR-Board')
				{
					if (strpos($taskRow['place'],'A')!==false ||
					strpos($taskRow['place'],'B')!==false ||
					strpos($taskRow['place'],'C')!==false ||
					strpos($taskRow['place'],'D')!==false ||
					strpos($taskRow['place'],'E')!==false ||
					strpos($taskRow['place'],'F')!==false ||
					strpos($taskRow['place'],'G')!==false ||
					strpos($taskRow['place'],'H')!==false)
					{
						$taskRow['row']=array();
						$cards=explode(',',$taskRow['place']);
						foreach ($cards AS $a)						
						{
							if ($a[0]=='A'){$taskRow['row'][0]=substr($a,1,255);}
							elseif ($a[0]=='B'){$taskRow['row'][1]=substr($a,1,255);}
							elseif ($a[0]=='C'){$taskRow['row'][2]=substr($a,1,255);}
							elseif ($a[0]=='D'){$taskRow['row'][3]=substr($a,1,255);}
							elseif ($a[0]=='E'){$taskRow['row'][4]=substr($a,1,255);}
							elseif ($a[0]=='F'){$taskRow['row'][5]=substr($a,1,255);}
							elseif ($a[0]=='G'){$taskRow['row'][6]=substr($a,1,255);}
							elseif ($a[0]=='H'){$taskRow['row'][7]=substr($a,1,255);}
						}
						$taskRow['place']='1,2,3,4,5,6,7,8';
					}
				}

setlog('simlink');
				sim_link($dev,unserialize($taskRow['data']),$taskRow['row'],$taskRow['place'],$taskRow['id'],$taskRow['action'],unserialize($taskRow['adata']));

				$pool_id=$taskRow['pool_id'];
				$card_number=$taskRow['card_number'];
				if ($pool_id) // Change of status | Смена статуса
				{
					$qry="UPDATE `pools` SET `status`='inprogress' WHERE `id`=".$pool_id;
				}
				elseif ($card_number)
				{
					$qry="UPDATE `cards` SET `status`='inprogress' WHERE `number`='".$card_number."'";
				}
			}
			if ($action!=$taskRow['id'])
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
			$action=$taskRow['id'];
// Проверяем не приостановлена ли задача
			if ($result2 = mysqli_query($db, "SELECT id FROM `actions` a WHERE `id`=".$taskRow['id']." AND `status`='suspension'")) 
			{
				if ($row2 = mysqli_fetch_assoc($result2))
				{
					mysqli_query($db, "UPDATE `actions` SET `timer`=".time()."-`time`, `status`='suspended' WHERE `id`=".$taskRow['id']);
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
setlog('!!! Action: '.$action.' ACTION_STOP:2 !!!','link_'.$dev);
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
