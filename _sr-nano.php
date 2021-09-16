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
			setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','com_'.$dev);
			setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','com_'.$dev);
			br($dev,'act_'.$actId.'_stop');
			br($dev);
			if ($reconnect<3)
			{
				if (!$reconnect)
				{
					$answer=sr_command($dev,'card:'.$place,60);
					press($dev);
				}
				else
				{
					$answer=sr_command($dev,'answer>clear&&card>reposition',60);
					press($dev,1);
				}
				if (!$answer)
				{
					setlog('[sim_link:'.$dev.'] Positioning error!','com_'.$dev); // Лимит времени исчерпан
					$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':101") WHERE `id`='.(int)$actId;
					mysqli_query($db, $qry); 
				}
				if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
				{
					$activation=sr_command($dev,'modem>activation',120);
				}
				else
				{
					$activation=sr_command($dev,'modem>activation',70);
				}
				setlog('[sim_link:'.$dev.'] Activation:'.$activation,'com_'.$dev);
				$activation=explode(';',$activation);
			}
			else
			{
				$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':'.$activation[0].'") WHERE `id`='.(int)$actId;
				mysqli_query($db, $qry); 
				setlog('[sim_link:'.$dev.'] The SIM card does not connect!','com_'.$dev); // СИМ-карта не подключается
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
								setlog('[sim_link:'.$dev.'] Action DATA update!','com_'.$dev); // Обновлены данные задачи
							}
							if ($answer && strlen($answer)>1)
							{
								actionReport($actId,$a[$k].': '.$place.' -> '.$answer);
								$time_limit=0;
							}
							else if ($answer)
							{
								$done[$k]=1;
							}
						}
					}
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'com_'.$dev);
					if ($answer)
					{
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1, `success`=`success`+1 WHERE `id`='.(int)$actId); 
						setlog('[sim_link:'.$dev.'] Done!','com_'.$dev); // Готово
						return;
					}
					sleep(1);
					setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','com_'.$dev);
					setlog('[sim_link:'.$dev.'] Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','com_'.$dev);
				}
				if ($activation[0]=='3' && $GLOBALS['set_data']['code_block'] && !$block_test)
				{
					setlog('[sim_link:'.$dev.'] SIM card is blocked!','com_'.$dev); // СИМ-карта заблокирована
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
					setlog('[sim_link:'.$dev.'] Done!','com_'.$dev); // Готово
					return;
				} 
			}
			
			if ($sleep)
			{
				setlog('[sim_link:'.$dev.'] Sleep '.$sleep,'com_'.$dev); // Ждем
				sleep($sleep);
			}
		}
	}
	else    	
	{
		while ($time_limit+$GLOBALS['time_correct']>time())
		{
			setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','com_'.$dev);
			setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','com_'.$dev);
			br($dev,'act_'.$actId.'_stop');
			br($dev);
			if (!$reconnect || $reconnect==7)
			{
				if ($reconnect==7)
				{
					$answer=sr_command($dev,'answer>clear&&card>reposition',60);
					press($dev,1);
				}
				elseif ($reconnect==14)
				{
					setlog('[sim_link:'.$dev.'] The SIM card does not connect!','com_'.$dev); // СИМ-карта не подключается
					return;
				}
				else
				{
					$answer=sr_command($dev,'card:'.$place,60);
					press($dev);
				}
				if (!$answer)
				{
					setlog('[sim_link:'.$dev.'] Positioning error!','com_'.$dev); // Лимит времени исчерпан
					$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':101") WHERE `id`='.(int)$actId;
					mysqli_query($db, $qry); 
					return;
				}	
				sr_command($dev,'modem>connect',10);
				sr_command($dev,'modem>on',10);
				$restart_time=time()+40;
				sleep(5);
			}
			$reconnect++;
			setlog('[sim_link:'.$dev.'] Getting information about Status','com_'.$dev);
		
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
					setlog('[sim_link:'.$dev.'] Status:'.$test,'com_'.$dev);
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
								setlog('[sim_link:'.$dev.'] Action DATA update!','com_'.$dev); // Обновлены данные задачи
							}
							if ($answer && strlen($answer)>1)
							{
								actionReport($actId,$a[$k].': '.$place.' -> '.$answer);
								$time_limit=0;
							}
							elseif ($answer)
							{
								$done[$k]=1;
							}
						}
					}
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'com_'.$dev);
					if ($answer)
					{
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
						setlog('[sim_link:'.$dev.'] Done!','com_'.$dev); // Готово
						return;
					}
					$restart_time=time()+30;
				}
				elseif ($test=='0,3' && $GLOBALS['set_data']['code_block'] && !$block_test)
				{
					setlog('[sim_link:'.$dev.'] SIM card is blocked!','com_'.$dev); // СИМ-карта заблокирована
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
					setlog('[sim_link:'.$dev.'] Done!','com_'.$dev); // Готово
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
				setlog('[sim_link:'.$dev.'] Restarting the modem','com_'.$dev);
				sr_command($dev,'modem>on&&modem>send:AT+CFUN=1,1'); // Перезапуск модема 
				$restart_time=time()+20;
			}
			if ($sleep)
			{
				setlog('[sim_link:'.$dev.'] Sleep '.$sleep,'com_'.$dev); // Ждем
				sleep($sleep);
			}
		}
	}
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
	if ($time_limit==0)
	{
		setlog('[sim_link:'.$dev.'] Break!','com_'.$dev); // Досрочный выход с ошибкой
	}
	else
	{
		setlog('[sim_link:'.$dev.'] The time limit is reached!','com_'.$dev); // Лимит времени исчерпан
	}
}

// Инициализация модема в режиме Online
function connect_online($dev,$place,$data,$modemTime,$begin=0)
{
	global $db;

	if ($begin)
	{
		$place[1]=2;
		mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
	}
	$out=1;
	if ($data['activation'] && $begin)
	{
		if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
		{
			$activation=sr_command($dev,'modem>activation',150);
		}
		else
		{
			$activation=sr_command($dev,'modem>activation',60);
		}
		setlog('[sim_link:'.$dev.'] Activation:'.$activation,'com_'.$dev);
		br($dev);
		$activation=explode(';',$activation);
		press($dev);
		for ($i=0;$i<2;$i++)
		{
			if ($activation[0]=='1' || $activation[0]=='5')
			{
				$place[1]=1;
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
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
					sr_command($dev,'AT+CLIP=1');		
					$answer=sr_answer($dev,0,20,'AT+CLIP');
					sr_command($dev,'AT+CMGF=0');		
					$answer=sr_answer($dev,0,20,'AT+CMGF');
				}
				elseif ($data['storage'])
				{
					sr_command($dev,'modem>send:AT+CPMS="ME","ME","ME"');
					sleep(10);
				}
				return($out); // Выходим, статус модемов уже сохранили
			}
			else
			{
				if ($i)
				{
					if ($activation[0]=='2')
					{
						$place[1]=2;
					}
					else
					{
						$place[1]=-2;
					}
					$out=0;
					break;
				}
				if ($begin)
				{
					sr_command($dev,'card>reposition',300);
					press($dev,1);
				}
				br($dev);
				$activation=sr_command($dev,'modem>activation',60);
				setlog('[sim_link:'.$dev.'] Activation:'.$activation,'com_'.$dev);
				br($dev);
				$activation=explode(';',$activation);
			}
			br($dev);
		}
	}
	else
	{
		$n=1;
		for ($i=0;$i<5;$i++)
		{
			if ($begin)
			{
				sr_command($dev,'modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
			}
			else
			{
				sr_command($dev,'modem>on&&modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
			}
			$answer=sr_answer($dev,0,30,'+CREG');
			if ($answer && strpos($answer,'error:')===false)
			{
				preg_match('!:(.*)OK!Uis', $answer, $test);
				$test=trim($test[1]);
				setlog('[online_mode:'.$dev.'] Status: '.$test,'com_'.$dev);
				if ($test=='0,1' || $test=='0,5')
				{
					$place[1]=1;
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
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
					return($out); // Выходм, статус модемов уже сохранили
				}
				elseif ($i==4 && ($test=='0,0' || $test=='0,4'))
				{
					if (!$n)
					{
						$place[1]=-2;
						$out=0;
						break;
					}
					$n--;
					$i=0;
					sr_command($dev,'card>reposition',300);
					press($dev,1);
					br($dev);
					sr_command($dev,'modem>connect',10);
					br($dev);
					sr_command($dev,'modem>on',10);
					br($dev);
				}
				elseif ($test=='0,2')
				{
					$place[1]=2;
					$i--;
				}
			}
			br($dev);
		}
	}
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
	return($out);
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $place, $modemTime, $devData)
{
//	$dev		Device ID
//	$place 		Place on SR-Nano

	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start','com_'.$dev);
	$smsTime=array();
	sr_answer_clear($dev);

	$place[1]=-1;
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);

	sr_command($dev,'card:'.$place[0],300);
	
	br($dev);
	if (!$devData['activation'])
	{
		sr_command($dev,'modem>connect',10);
		br($dev);
		sr_command($dev,'modem>on',10);
		press($dev);
		br($dev);
	}
	$status=connect_online($dev,$place,$devData,$modemTime,1); // Подключение
	$placeBuf=serialize($place);
	$getCops=4;

	while (1)
	{
		setlog('[online_mode:'.$dev.'] Status:'.$status,'com_'.$dev);
		if ($status)
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
			setlog('[online_mode:'.$dev.'] Get SMS & Waiting...','com_'.$dev);

			$smsBuf=$answer=sr_answer($dev,$step,30);
			br($dev);

			setlog('[online_mode:'.$dev.'] Step: '.$step.' Answer: '.$answer,'com_'.$dev);
			$error="";
			if ($answer!="1" || $getCops<=0)
			{
				if ($getCops<=0 || $answer=="NO RESPONSE")
				{
					$status=connect_online($dev,$place,$devData,$modemTime); // Подключение
					$getCops=4;
				}
				if ($smsBuf=='Error') // Ошибка
				{
					$place[1]=0;
					setlog('[online_mode:'.$dev.'] Error receiving SMS!','com_'.$dev);
				}

				$data=explode('##',$smsBuf);
				$sms='';
				for ($i=1;$i<count($data);$i++)
				{
					$sms='';
					setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i],'com_'.$dev); // Получена SMS
					if ($data[$i])
					{
						$raw=explode("\n",$data[$i]);
						setlog('[online_mode:'.$dev.'] SMS: '.trim($raw[1]),'com_'.$dev); // Подготовка SMS
						$sms=$pdu->pduToText($raw[1]);
						setlog($raw[1],'sms');
						setlog(print_r($sms,1),'sms');
						setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1),'com_'.$dev); // Подготовка SMS
				
						if ($m>8){$p=$m-8;$r=$curRow+3;} else {$p=$m;$r=$curRow;}
						        
						// Если номер есть
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
									setlog('[online_mode:'.$dev.'] SMS sent to E-mail','com_'.$dev); // SMS отправлена на E-mail
								}
								$smsTime[$m]=time();
							}
						}
						else  // Если номера нет
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
							`number`='".$place[0]."',
							`place`='".$place[0]."',
							`sender`='".$sms['number']."',
							`time`=".$sms['unixTimeStamp'].",
							`modified`=".time().",
							`txt`='".$sms['message']."',
							".$qry;
							mysqli_query($db,$qry);
							setlog('[online_mode:'.$dev.'] SMS saved'); // SMS сохранена
				    			if ($GLOBALS['set_data']['email'])
							{
								setlog('[online_mode:'.$dev.'] SMS sent to E-mail','com_'.$dev); // SMS отправлена на E-mail
							}
							$smsTime[$m]=time();
						}
					}
					setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$place[1],'com_'.$dev);
					if ($smsNum>7 && $smsTime[$m] && $smsTime[$m]<time()-60 && $place[1]==1)
					{
						setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card','com_'.$dev);
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
			}
			$getCops--;
			if ($placeBuf!=serialize($place))
			{
				br($dev);
				$placeBuf=serialize($place);
				mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
			}
		}
		else
		{
			$status=connect_online($dev,$place,$devData,$modemTime); // Подключение
		}
		if (flagGet($dev,'stop'))
		{
			flagDelete($dev,'stop');
			setlog('[online_mode:'.$dev.'] Early exit!','com_'.$dev); // Досрочный выход
			exit();
		}			
		br($dev);
	}
}

function dev_init($dev)
{
//	$dev		Device ID
	global $db;
	$answer=sr_command($dev,'modem>on',30);
	if ($answer==1)
	{
		if ($result = mysqli_query($db, "SELECT `data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$data=unserialize($resRow['data']);
				sr_command($dev,'AT+GMM');		
				$answer=sr_answer($dev,0,20,'AT+GMM');
				if (strpos($answer,'SIMCOM_SIM800C')!==false)
				{
					$data['modem']="SIM800";
					sr_command($dev,'AT+CLIP=1');		
					$answer=sr_answer($dev,0,20,'AT+CLIP');
					sr_command($dev,'AT+CMGF=0');		
					$answer=sr_answer($dev,0,20,'AT+CMGF');
					sr_command($dev,'AT&W0');		
					$answer=sr_answer($dev,0,20,'AT&W0');
				}
				elseif (strpos($answer,'SIMCOM_SIM5320E')!==false)
				{
					$data['modem']="SIM5320";
				}
				elseif (strpos($answer,'SIMCOM_SIM5360E')!==false)
				{
					$data['modem']="SIM5360";
				}
				elseif (strpos($answer,'SIMCOM_SIM7100E')!==false)
				{
					$data['modem']="SIM7100";
				}
				sr_command($dev,'dev:mode=navigator&&save&&sound:beep');		
				$qry="UPDATE `devices` SET `title`=`model`,`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
setlog($qry,'link');
				mysqli_query($db,$qry);
				return(1);
			}
		}
	}
	return(0);
}

?>