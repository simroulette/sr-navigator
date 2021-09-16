<?
// ===================================================================
// Sim Roulette -> SR-Organizer functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
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
	setlog('[sim_link:'.$dev.'] Start');
	$time_limit=time()+$data['time_limit'];
	$sleep=$data['sleep'];
	$p=explode(',',$place);
	$place=array();
	$cards=array(0,0);
	$cnt=0;
	foreach ($p AS $d)
	{
		$d=explode('-',$d);
		$place[$d[0]][$d[1]]=1;
		if (!$cards[0] && $d[0]==1)
		{
			$cards[0]=$d[1];
		}
		if (!$cards[1] && $d[0]==2)
		{
			$cards[1]=$d[1];
		}
		$cnt++;
	}

	sr_answer_clear($dev);
	$done=array();
        $GLOBALS['time_correct']=0;

	sr_command($dev,'modem[1]>card:'.$cards[0].'&&modem[2]>card:'.$cards[1]);
	sr_command($dev,'modem>select:1',30);
	sleep(5);

	for ($counter=0;$counter<$cnt;$counter++)
	{
		$modem=1;
		setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.');
		setlog('[sim_link:'.$dev.'] Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if ($counter==0)
		{
			$activation=sr_command($dev,'modem>activation',50);
			setlog('[sim_link:'.$dev.'] Get activation:'.$activation);
			$activation=explode(';',$activation);
			$pl=$cards[0];
			$place[1][$cards[0]]=2;
			$place[2][$cards[1]]=2;
		}
		else
		{
			$stop=1;
			if (($counter % 2) != 0)
			{
				for ($i=$cards[0];$i<9;$i++)
				{
					$cards[0]++;
					if ($place[1][$cards[0]]){$stop=0;break;}
				}
				$activation=sr_command($dev,'modem>check:2',30);
				$activation=explode(';',$activation);
				if (!$stop && ($activation[0]=='1' || $activation[0]=='5'))
				{
					$place[1][$cards[0]]=2;
					sr_command($dev,'modem[1]>card:'.$cards[0]);
				}
				else
				{
					if ($cards[0]<9)
					{
						sr_command($dev,'modem[1]>card:'.$cards[0]);
					}
					$activation=sr_command($dev,'modem>activation:2',50);
					$activation=explode(';',$activation);
					$activation[0]=$activation[2];
					$activation[1]=$activation[3];
				}
				sr_command($dev,'modem>select:2',30);
				$pl=$cards[1];
				$modem=2;
			}
			else
			{
				for ($i=$cards[0];$i<9;$i++)
				{
					$cards[1]++;
					if ($place[2][$cards[1]]){$stop=0;break;}
				}
				$activation=sr_command($dev,'modem>check:1',30);
				$activation=explode(';',$activation);
				if (!$stop && ($activation[0]=='1' || $activation[0]=='5'))
				{
					$place[2][$cards[1]]=2;
					sr_command($dev,'modem[2]>card:'.$cards[1]);
				}
				else
				{
					if ($cards[1]<9)
					{
						sr_command($dev,'modem[2]>card:'.$cards[1]);
					}
					$activation=sr_command($dev,'modem>activation:1',50);
					$activation=explode(';',$activation);
				}
				sr_command($dev,'modem>select:1',30);
				$pl=$cards[0];
			}
		}

		if ($activation[0]=='1' || $activation[0]=='5')
		{
			br($dev,'act_'.$actId.'_stop');
			$a=explode(';',$func);
			$status=array();
			$done=array();
			for ($k=0;$k<count($a);$k++)
			{
				if (!$done[$k])
				{
					$f=$a[$k]; 
					$GLOBALS['adata']='';
					if ($activation[0]=='5'){$roaming=1;} else {$roaming=0;}
					$answer=$f($dev,$modem,$pl,$adata,$activation[1],$roaming);
					if ($GLOBALS['adata'])
					{
						mysqli_query($db, 'UPDATE `actions` SET `data`="'.serialize($GLOBALS['adata']).'" WHERE `id`='.(int)$actId); 
						setlog('[sim_link:'.$dev.'] Action DATA update!'); // Обновлены данные задачи
					}
					if ($answer)
					{
						$done[$k]=1;
					}
				}
			}
			setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
			if ($answer)
			{
				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
			}
			else
			{
				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
			}
			if ($counter+1==$cnt)
			{
				return;
			}
		}
		else if ($activation[0]=='3' && $GLOBALS['set_data']['code_block'])
		{
			setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
			// Clearing a place in the database | Очищаем место в БД
			$qry="DELETE FROM `cards` WHERE
			`place`='".($place=remove_zero($place))."'";
			mysqli_query($db,$qry);

			// Saving the number | Сохраняем номер
			$qry="REPLACE INTO `cards` SET
			`number`='".$modem.'-'.$pl."',
			`place`='".$modem.'-'.$pl."',
			`device`=".(int)$dev.",
			`operator`=0,
			`time_number`='".time()."',
			`time`='".time()."'";
			mysqli_query($db,$qry);

			mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
			setlog('[sim_link:'.$dev.'] Done!'); // Готово
		} 
		else
		{
			mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1 WHERE `id`='.(int)$actId); 
			setlog('[sim_link:'.$dev.'] Done!'); // Готово
		}
	}
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start');
	$smsTime=array();
	sr_answer_clear($dev);

	$com=array();
	if ($modems[1][1]<0)
	{
		$com[]='modem[1]>card:'.$modems[1][0]; 
	}
	if ($modems[2][1]<0)
	{
		$com[]='modem[2]>card:'.$modems[2][0]; 
	}
	$com[]='modem>on';
	sr_command($dev,implode('&&',$com)); 

	setlog('[1] -> '.print_r($modems,1).'link_36');

	$timer[1]=time()+120;
	$timer[2]=time()+120;
	$reconnect[1]=1;
	$reconnect[2]=1;

	while (1)
	{
		$step=sr_command($dev,'modem>sms:0');
		$answer=sr_answer($dev,$step,30);
		$smsBuf=$answer=explode('#3#',$answer);
		$error="";
		if ($answer!="1")
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+COPS?##all##1'); // Мониторинг подключения к сотовой сети
				$answer=explode('#1#',sr_answer($dev,$step,30));

				$modems[1][1]=-2;
				$modems[2][1]=-2;

				$timer[1]=time()+30;
				$timer[2]=time()+30;

				for ($i=0;$i<count($answer);$i++)
				{
					$a=explode('##',$answer[$i]);
			                preg_match('!"(.*)"!Us', $answer[$i], $test);
					if ($a[0] && $test[1])
					{
						$modems[$a[0]][1]=1;
						$timer[$a[0]]=time()+15;
					}
					elseif ($a[1])
					{
						if ((int)$a[0])
						{
							$error.=$a[0].';';
							if (!$reconnect[$a[0]]){$timer[$a[0]]=time()+15;}
							$reconnect[$a[0]]=0;
						}
					}
				}

				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 

				}
				$getCops=5;
			}
			$getCops--;
			foreach ($smsBuf AS $data)
			{
				$data=explode('##',$data);
				$m=$data[0][0];
				if ($data[1]=='E') // Ошибка
				{
					setlog('[online_mode:'.$dev.'] Error receiving SMS!');
				}
				else
				{
					for ($i=1;$i<count($data);$i++)
					{
						$sms='';
						setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i]); // Получена SMS
						if ($data[$i])
						{
							$smsNum=explode(',',$data[$i]);
							$smsNum=$smsNum[0];
							setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum); // Подготовка SMS
				
							$raw=explode("\n",$data[$i]);
							$sms=$pdu->pduToText($raw[1]);
							setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1)); // Подготовка SMS
			
							if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$m."-".$modems[$m][0]."' AND `device`=".$dev)) 
							{
								if ($row = mysqli_fetch_assoc($result))
								{
                      								if (trim($sms['userDataHeader']))
									{
										$qry="`header`='".trim($sms['userDataHeader'])."'";
										$smsNum=0;
									}
									else
									{
										$qry="`done`=1";
									}
					        	
									// Saving to the database | Сохранение в БД
									$qry="INSERT INTO `sms_incoming` SET
									`number`='".$row['number']."',
									`sender`='".$sms['number']."',
									`time`=".$sms['unixTimeStamp'].",
									`modified`=".time().",
									`txt`='".$sms['message']."',
									".$qry;
									mysqli_query($db,$qry);
									setlog('[online_mode:'.$dev.'] '.$qry);
									setlog('[online_mode:'.$dev.'] SMS saved'); // SMS сохранена
						    			if ($GLOBALS['set_data']['email'])
									{
										setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
									}
								}
							}
							else
							{
								setlog('[online_mode:'.$dev.'] SIM card not found in the database!'); // СИМ-карта не найдена в БД
								return($out);
							}
						}
						setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$place[1]);
						if ($smsNum>7)
						{
							setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card');
							sr_command($dev,'modem>select:'.$m.'&&modem>send:AT+CMGDA=5'); // Удаление всех SMS с SIM-карты
						$smsNum=0;
						}
					}
				}
			}
			if ($error)
			{
				setlog('[online_mode:'.$dev.'] Errors: '.$error);
			}
		}
		if ($result = mysqli_query($db, "SELECT time FROM `modems` WHERE `device`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				if ($row['time']<time()-20)
				{
					setlog('[2] -> '.print_r($modems,1).'link_36');
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."' WHERE `device`=".$dev);
				}
			}
			else
			{
				if (flagGet($dev,'stop'))
				{
					flagDelete($dev,'stop');
				}
				setlog('[online_mode:'.$dev.'] Early exit!'); // Досрочный выход
				exit();
			}			
		}
		br($dev);
	}
}

function dev_init($dev)
{
//	$dev		Device ID
	global $db;
	$answer=sr_command($dev,'dev:mode=navigator',20);
	if ($answer=='Navigator mode ON')
	{
		sr_command($dev,'save');		
		$qry="UPDATE `devices` SET `title`=`model`,`init`=".time()." WHERE `id`=".$dev;
		mysqli_query($db,$qry);
		return(1);
	}
	return(0);
}

?>