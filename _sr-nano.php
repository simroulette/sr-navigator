<?
// ===================================================================
// Sim Roulette -> SR-Nano functions
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

	sr_answer_clear($dev);
	$connect=time();
	$reconnect=0; // The count of reconnections | Счетчик переподключений
	$done=array();
        $GLOBALS['time_correct']=0;
	$block_test=3;


if ($data['activation'])
{
	while ($time_limit+$GLOBALS['time_correct']>time())
	{
		setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.');
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if ($reconnect<2)
		{
			if ($reconnect==1)
			{
				sr_command($dev,'answer>clear&&card>reposition',20);
				press($dev,1);
			}
			else
			{
				sr_command($dev,'card:'.$place,20);
				press($dev);
			}
			if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
			{
				$activation=sr_command($dev,'modem>activation',120);
			}
			else
			{
				$activation=sr_command($dev,'modem>activation',50);
			}
			setlog('[sim_link:'.$dev.'] Activation:'.$activation);
			$activation=explode(';',$activation);
		}
		else
		{
			mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1 WHERE `id`='.(int)$actId); 
			setlog('[sim_link:'.$dev.'] The SIM card does not connect!'); // СИМ-карта не подключается
			return;
		}
		$reconnect++;
		if ($activation[0]=='1' || $activation[0]=='5')
		{
			if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
			{
                                if ($data['storage'])
				{
					sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
				}
				else
				{
					sr_command($dev,'modem>send:AT+CPMS="SM","SM","SM"');
				}
				sleep(10);
			}
			elseif ($data['storage'])
			{
				sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
				sleep(10);
			}
			while ($time_limit+$GLOBALS['time_correct']>time())
			{
				br($dev,'act_'.$actId.'_stop');
				$a=explode(';',$func);

				if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
				{
					sr_command($dev,'modem>send:AT+CMGD=0,4');
					sleep(10);
				}

				$status=array();
				for ($k=0;$k<count($a);$k++)
				{
					if (!$done[$k])
					{
						$f=$a[$k]; 
						$GLOBALS['adata']='';
						if ($activation[0]=='5'){$roaming=1;} else {$roaming=0;}
						$answer=$f($dev,0,$place,$adata,$activation[2],$roaming);
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
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1, `success`=`success`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!'); // Готово
					return;
				}
				sleep(1);
				setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.');
				setlog('[sim_link:'.$dev.'] Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.');
			}
			if ($activation[0]=='3' && $GLOBALS['set_data']['code_block'] && !$block_test)
			{
				setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".($place=remove_zero($place))."'";
				mysqli_query($db,$qry);

				// Saving the number | Сохраняем номер
				$qry="REPLACE INTO `cards` SET
				`number`='".$place."',
				`place`='".$place."',
				`device`=".(int)$dev.",
				`operator`=0,
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);

				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
				return;
			} 
		}

		if ($sleep)
		{
			setlog('[sim_link:'.$dev.'] Sleep '.$sleep); // Ждем
			sleep($sleep);
		}
	}
}
else
{
	while ($time_limit+$GLOBALS['time_correct']>time())
	{
		setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.');
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if (!$reconnect || $reconnect==7)
		{
			if ($reconnect==7)
			{
				sr_command($dev,'answer>clear&&card>reposition',20);
				press($dev,1);
			}
			elseif ($reconnect==14)
			{
				setlog('[sim_link:'.$dev.'] The SIM card does not connect!'); // СИМ-карта не подключается
				return;
			}
			else
			{
				sr_command($dev,'card:'.$place,20);
				press($dev);
			}
			sr_command($dev,'modem>connect',10);
			sr_command($dev,'modem>on',10);
			$restart_time=time()+40;
			sleep(5);
		}
		$reconnect++;
		setlog('[sim_link:'.$dev.'] Getting information about Status');

		sr_command($dev,'modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
		$answer=sr_answer($dev,0,10,'+CREG');
		if ($answer=='error:no answer')
		{
			sr_command($dev,'modem>send:AT+CREG?'); // Repeated request for information about the operator | Повторный запрос информации об операторе
			$answer=sr_answer($dev,0,10,'+CREG');
		}

		if ($answer && strpos($answer,'error:')===false)
		{
			preg_match('!:(.*)OK!Uis', $answer, $test);
			$test=trim($test[1]);
			if ($test=='0,1' || $test=='0,5')
			{
				setlog('[sim_link:'.$dev.'] Status:'.$test);
				$a=explode(';',$func);
				$status=array();
				for ($k=0;$k<count($a);$k++)
				{
					if (!$done[$k])
					{
						$f=$a[$k]; 
						$GLOBALS['adata']='';
						if ($test=='0,5'){$roaming=1;} else {$roaming=0;}
						$answer=$f($dev,0,$place,$adata,'',$roaming);
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
					return;
				}
				$restart_time=time()+30;
			}
			elseif ($test=='0,3' && $GLOBALS['set_data']['code_block'] && !$block_test)
			{
				setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".($place=remove_zero($place))."'";
				mysqli_query($db,$qry);

				// Saving the number | Сохраняем номер
				$qry="REPLACE INTO `cards` SET
				`number`='".$place."',
				`place`='".$place."',
				`device`=".(int)$dev.",
				`operator`=0,
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);

				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`+`success`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
				return;
			} 
			elseif ($test=='0,2' || ($test=='0,3' && ($block_test || !$GLOBALS['set_data']['code_block'])))
			{
				if ($block_test){$block_test--;}
				$restart_time=time()+30;			
			} 
			elseif (($test=='0,0' || $test=='0,4') && $restart_time<time()+20)
			{
				$restart_time=0;			
			} 
		}

		if ($restart_time<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem');
			sr_command($dev,'modem>on&&modem>send:AT+CFUN=1,1'); // Перезапуск модема 
			$restart_time=time()+20;
		}
		if ($sleep)
		{
			setlog('[sim_link:'.$dev.'] Sleep '.$sleep); // Ждем
			sleep($sleep);
		}
	}
}
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Инициализация модема в режиме Online
function connect_online($dev,$place,$data)
{
	global $db;

	if ($data['activation'])
	{
		if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
		{
			$activation=sr_command($dev,'modem>activation',80);
		}
		else
		{
			$activation=sr_command($dev,'modem>activation',50);
		}
		setlog('[sim_link:'.$dev.'] Activation:'.$activation);
		br($dev);
		$activation=explode(';',$activation);
		press($dev);
		for ($i=0;$i<2;$i++)
		{
			if ($activation[0]=='1' || $activation[0]=='5')
			{
				$place[1]=1;
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
				if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
				{
                	                if ($data['storage'])
					{
						sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
					}
					else
					{
						sr_command($dev,'modem>send:AT+CPMS="SM","SM","SM"');
					}
					sleep(10);
				}
				elseif ($data['storage'])
				{
					sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
					sleep(10);
				}
				break;
			}
			else if ($activation[0]=='0' || $activation[0]=='4')
			{
				if ($i)
				{
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
					setlog('[online_mode:'.$dev.'] Stop with Error!');
					sleep(5);
					mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".$dev);
					setlog('[online_mode:'.$dev.'] Emergency exit - No connect!'); // Экстренный выход
					flagDelete($dev,'cron');
					exit();
				}
				sr_command($dev,'card>reposition',20);
				press($dev,1);
				$place[1]=2;
				br($dev);
				$activation=sr_command($dev,'modem>activation',50);
				setlog('[sim_link:'.$dev.'] Activation:'.$activation);
				br($dev);
				$activation=explode(';',$activation);
				if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
				{
	                                if ($data['storage'])
					{
						sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
					}
					else
					{
						sr_command($dev,'modem>send:AT+CPMS="SM","SM","SM"');
					}
					sleep(10);
				}
				elseif ($data['storage'])
				{
					sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
					sleep(10);
				}
			}
			br($dev);
			mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
		}
	}
	else
	{
		$n=1;
		for ($i=0;$i<5;$i++)
		{
			sr_command($dev,'modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
			$answer=sr_answer($dev,0,30,'+CREG');
			if ($answer && strpos($answer,'error:')===false)
			{
				preg_match('!:(.*)OK!Uis', $answer, $test);
				$test=trim($test[1]);
				setlog('[online_mode:'.$dev.'] Status: '.$test);
				if ($test=='0,1' || $test=='0,5')
				{
					$place[1]=1;
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
					break;
				}
				elseif ($i==4 && ($test=='0,0' || $test=='0,4'))
				{
					if (!$n)
					{
						$place[1]=-2;
						mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
						setlog('[online_mode:'.$dev.'] Stop with Error!');
						sleep(5);
						mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".$dev);
						setlog('[online_mode:'.$dev.'] Emergency exit - No connect!'); // Экстренный выход
						flagDelete($dev,'cron');
						exit();
					}
					$n--;
					$i=0;
					sr_command($dev,'card>reposition',20);
					press($dev,1);
					br($dev);
					sr_command($dev,'modem>connect',10);
					br($dev);
					sr_command($dev,'modem>on',10);
					br($dev);
					$place[1]=2;
				}
				elseif ($test=='0,2')
				{
					$i--;
					$place[1]=2;
				}
				else
				{
					$place[1]=2;
				}
			}
			br($dev);
			mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
		}
	}
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $place, $devData)
{
//	$dev		Device ID
//	$place 		Place on SR-Nano

	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start');
	$max_row=19;
	$smsTime=array();
	sr_answer_clear($dev);

	$place[1]=-1;
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);

	sr_command($dev,'card:'.$place[0]);
	br($dev);
	if (!$devData['activation'])
	{
		sr_command($dev,'modem>connect',10);
		br($dev);
		sr_command($dev,'modem>on',10);
		press($dev);
		br($dev);
	}
	connect_online($dev,$place,$devData); // Подключение

	$timer=0;
	$placeBuf="";
	while (1)
	{
		if ($place[1]=-2)
		{
			$place[1]=1;
		}
		if ($timer<time())
		{
			if ($devData['modem']=='SIM5320' || $devData['modem']=='SIM5360' || $devData['modem']=='SIM7100')
			{
				if ($devData['storage']==1)
				{
					$step=sr_command($dev,'modem>sms:1');
				}
				else
				{
					$step=sr_command($dev,'modem>sms:4');
				}
			}
			else
			{
				$step=sr_command($dev,'modem>sms:0');
			}
			setlog('[online_mode:'.$dev.'] Get SMS & Waiting...');
			$timer=time()+5;

			$smsBuf=$answer=sr_answer($dev,$step,30);

			setlog('[online_mode:'.$dev.'] Step: '.$step.' Answer: '.$answer);
			$error="";
			if ($answer!="1")
			{
				if ($answer=="NO RESPONSE")
				{

					$n=1;
					for ($i=0;$i<5;$i++)
					{
						$place[1]=-2;
						sr_command($dev,'modem>on&&modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
						$answer=sr_answer($dev,0,30,'+CREG');
						if ($answer && strpos($answer,'error:')===false)
						{
							preg_match('!:(.*)OK!Uis', $answer, $test);
							$test=trim($test[1]);
							setlog('[online_mode:'.$dev.'] Status: '.$test);
							if ($test=='0,1' || $test=='0,5')
							{
								$place[1]=1;
								break;
							}
							else if ($i==4 && ($test=='0,0' || $test=='0,4'))
							{
								connect_online($dev,$place); // Подключение
							}
						}
						else if (strpos($answer,'error:')!==false)
						{
							connect_online($dev,$place); // Подключение
						}
						br($dev);
					}
				}
				if ($smsBuf=='Error') // Ошибка
				{
					if ($place[1]=-2){$place[1]=0;}
					setlog('[online_mode:'.$dev.'] Error receiving SMS!');
				}

				$data=explode('##',$smsBuf);
				$sms='';
				for ($i=1;$i<count($data);$i++)
				{
					$sms='';
					setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i]); // Получена SMS
					if ($data[$i])
					{
						$raw=explode("\n",$data[$i]);
						setlog('[online_mode:'.$dev.'] SMS: '.trim($raw[1])); // Подготовка SMS
						$sms=$pdu->pduToText($raw[1]);
						setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1)); // Подготовка SMS
				
						if ($m>8){$p=$m-8;$r=$curRow+3;} else {$p=$m;$r=$curRow;}

						if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$place[0]."' AND `device`=".$dev)) 
						{
							if ($row = mysqli_fetch_assoc($result))
							{
                       						if (trim($sms['userDataHeader']))
								{
									$qry="`header`='".trim($sms['userDataHeader'])."'";
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
								setlog('[online_mode:'.$dev.'] SMS saved'); // SMS сохранена
					    			if ($GLOBALS['set_data']['email'])
								{
									setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
								}
								$smsTime[$m]=time();
							}
						}
						else
						{
							setlog('[online_mode:'.$dev.'] SIM card not found in the database!'); // СИМ-карта не найдена в БД
							return($out);
						}
					}
					setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$place[1]);
					if ($smsNum>7 && $smsTime[$m] && $smsTime[$m]<time()-60 && $place[1]==1)
					{
						setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card');
						if ($devData['modem']=='SIM5320' || $devData['modem']=='SIM5360' || $devData['modem']=='SIM7100')
						{
							sr_command($dev,'modem>send:AT+CMGD=0,4');
						}
						else
						{
							sr_command($dev,'modem>send:AT+CMGDA=5'); // Удаление всех SMS с SIM-карты
						}
						$smsNum=0;
					}
				}
				if ($sms && ($devData['modem']=='SIM5320' || $devData['modem']=='SIM5360' || $devData['modem']=='SIM7100'))
				{
					sr_command($dev,'modem>send:AT+CMGD=0,1');
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
					if ($row['time']<time()-30 && $placeBuf!=serialize($place))
					{
						$placeBuf=serialize($place);
						mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `device`=".$dev);
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

		}
		br($dev);
	}
}

?>