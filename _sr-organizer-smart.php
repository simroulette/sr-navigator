<?
// ===================================================================
// Sim Roulette -> SR-Organizer functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

// Container function: Selecting a row, connecting contacts, powering modems, checking connections, and performing the following functions                        
// Функция-контейнер: Выбор ряда, подключение контактов, включение модемов, проверка связи и выполнение перечисленных функций
function sim_link($dev, $data, $curRow, $place, $actId, $func, $adata)
{
//	$dev		Device ID
//	$data		Array with additional data from device	
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
//	$actId          Action ID
//	$func     	List of functions to perform
//	$adata		Array with additional data from action	

	global $root,$db;
	setlog('[sim_link:'.$dev.'] Start Organizer Smart','link_'.$dev);

	$time_limit=time()+$data['time_limit'];
	$sleep=$data['sleep'];

	if ($data['modems']==1)
	{
		$modem=1;
	}
	else
	{
		$modem=ord($place[0])-64;
	}
	sr_command_smart($dev,'modem'.$modem.'.card:'.(ord($place[1])-48));
//	sleep(20);
	mysqli_query($db, 'UPDATE `devices_state` SET `result`="-1" WHERE `dev`="modem'.$modem.'" AND `device_id`='.$dev);

	while ($time_limit+$GLOBALS['time_correct']>time())
	{
		sleep(2);
		if ($result_smart = mysqli_query($db, 'SELECT * FROM `devices_state` WHERE `dev`="modem'.$modem.'" AND `device_id`='.$dev)) 
		{
			if ($row_smart = mysqli_fetch_assoc($result_smart))
			{
				$d=unserialize($row_smart['data']);
				$m=$row_smart['result'];
				$i=$d->iccid;
				$c=$d->card;
				if (!$o=$d->operator)
				{
					$o=$d->name;
				}
			}
		}
		if ($m!=-1){break;}
		br($dev,'act_'.$actId.'_stop');
		br($dev);
	}
	if ($m!=1 && $m!=5 && ($m!=3 && $GLOBALS['set_data']['code_reg']!=2))
	{
		mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.$place.':'.$m.',") WHERE `id`='.(int)$actId);
		if ($time_limit+$GLOBALS['time_correct']>time())
		{
			setlog('[sim_link:'.$dev.'] Error!'); // Ошибка
		}
		else
		{
			setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
		}
		return;
	}

	$a=explode(';',$func);
	$done=0;
	sleep(15); // Ждем пока модем будет готов
	for ($k=0;$k<count($a);$k++)
	{
		$f=$a[$k].'_smart'; 
		$GLOBALS['adata']='';
		if ($m=='5'){$roaming=1;} else {$roaming=0;}
		$answer=$f($dev,'SR-Organizer',$modem,$place,$i,$adata,$o,$roaming);
		if ($GLOBALS['adata'])
		{
			mysqli_query($db, 'UPDATE `actions` SET `data`="'.serialize($GLOBALS['adata']).'" WHERE `id`='.(int)$actId); 
			setlog('[sim_link:'.$dev.'] Action DATA update!'); // Обновлены данные задачи
		}
		if ($answer)
		{
			$done++;
		}
		setlog('Answer:'.$answer,'link_'.$dev); 
	}
	if ($done)
	{
		mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
	}
	else
	{
		mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.$place.':11,") WHERE `id`='.(int)$actId);
	}
	setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems, $modemTime)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db,$pdu,$db;

	setlog('[online_mode:'.$dev.'] Start Organizer Smart','link_'.$dev);

	flagDelete($dev,'review_timer');

	while (1)
	{
		if (flagGet($dev,'stop'))
		{
			flagDelete($dev,'stop');
			setlog('[online_mode:'.$dev.'] Early exit!','com_'.$dev); // Досрочный выход
			exit();
		}
		$b=flagGet($dev,'review');
		$t=flagGet($dev,'review_timer',1);
		if (!$t)
		{
			flagSet($dev,'review_timer');
			flagSet($dev,'review_step',1);
			$t=time();
		}
setlog('Flag:'.$b.' Period:'.$dat['review'].' ElTime:'.(($t+$data['review'])-time()),'link_'.$dev);

		if ($data['review_start'] && !$b && flagGet($dev,'command',1)+$data['review_start']<time())
		{
			flagSet($dev,'review');
			$b=1;
		}			
		if ($b && $t+$data['review']<time())
		{
			flagSet($dev,'review_timer');
/*
			if (!$t) // Начинаем
			{
				$step=1;
			}
			else
			{
*/
				$step=flagGet($dev,'review_step');
/*
				if (!$step){$step=1;}
			}
*/
			if ($data['modems']==3 && $data['lines']==3)
			{
				sr_command_smart($dev,'modem1.card:'.$step.'&&modem2.card:'.$step.'&&modem3.card:'.$step);
				$step++;
				if ($step==9){$step=1;}
			}
			else if ($data['modems']==1 && $data['lines']==3)
			{
				sr_command_smart($dev,'modem1.card:'.$step);
				$step++;
				if ($step==25){$step=1;}
			}
			else if ($data['modems']==1 && $data['lines']==2)
			{
				sr_command_smart($dev,'modem1.card:'.$step);
				$step++;
				if ($step==17){$step=1;}
			}
			flagSet($dev,'review_step',$step);
		}
		br($dev);
		sleep(3);
	}
}

function dev_init($dev, $actId)
{
//	$dev		Device ID
	global $db;
	setlog('[dev_init:'.$dev.'] Start Organizer Smart','link_'.$dev);
	$answer=sr_command_smart($dev,'.set.dev.type:sign=SRN_init','SRN_init',20);
	setlog('>>>'.print_r($answer,1),'link_'.$dev);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sleep(10);
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
	sr_command_smart($dev,'set.dev.mode:manual');		
	$answer=json_decode($answer);
	if ($answer->result>51)
	{
		if ($result = mysqli_query($db, "SELECT `title`,`data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$data=unserialize($row['data']);
				if ($answer->result==55)
				{
					$data['lines']=3;
					$data['modems']=3;
					$model='SR-Organizer-24-3';
					sr_command_smart($dev,'modem1.send:AT+CLIP=1&&modem2.send:AT+CLIP=1&&modem3.send:AT+CLIP=1');
					sleep(15);
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
					sr_command_smart($dev,'modem1.send:AT&W0&&modem2.send:AT&W0&&modem3.send:AT&W0');
					sleep(15);
					$init='modem1.card:1&&modem2.card:1&&modem3.card:1';
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
				}
				if ($answer->result==54)
				{
					$data['lines']=3;
					$data['modems']=1;
					$model='SR-Organizer-24-1';
					sr_command_smart($dev,'modem.send:AT+CLIP=1');
					sleep(15);
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
					sr_command_smart($dev,'modem.send:AT&W0');
					sleep(15);
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
					$init='modem.card:1';
				}
				if ($answer->result==52)
				{
					$data['lines']=2;
					$data['modems']=1;
					$model='SR-Organizer-16-1';
					sr_command_smart($dev,'modem.send:AT+CLIP=1');
					sleep(15);
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
					sr_command_smart($dev,'modem.send:AT&W0');
					sleep(15);
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+15 WHERE `id`='.(int)$actId);
					$init='modem.card:1';
				}

				sr_command_smart($dev,'display:FE&&set.dev.mode:smart&&'.$init.'&&.save:sign=init','init',30);		
				mysqli_query($db, 'UPDATE `actions` SET `count`=1,`progress`=1,`success`=1 WHERE `id`='.(int)$actId);

				if ($row['title']=='[init]')
				{
					$qry="UPDATE `devices` SET `title`='".$model."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				else
				{
					$qry="UPDATE `devices` SET `init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				mysqli_query($db,$qry);

				return(1);
			}
		}
	}
	return(0);
}

?>
