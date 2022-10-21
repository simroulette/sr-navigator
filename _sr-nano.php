<?

// ===================================================================
// Sim Roulette -> SR-Nano functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
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

//setlog("FOOL:".print_r($adata,1),'link_'.$dev);

if ($data['activation'])
{
	while ($time_limit+$GLOBALS['time_correct']>time())
	{
		setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','link_'.$dev);
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','link_'.$dev);
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		$report=12;
		if ($reconnect<3)
		{
			if (!$reconnect)
			{
				$answer=sr_command($dev,'card:'.$place,60);
				press($dev);
				if ($answer!=1)
				{
					setlog('[sim_link:'.$dev.'] Positioning error! Repeat!','link_'.$dev); // Лимит времени исчерпан
					sr_command($dev,'card:'.$place,60);
					flagDelete($dev,'pin');
				}
			}
			else
			{
				$answer=sr_command($dev,'answer>clear&&card>reposition',180);
				flagDelete($dev,'pin');
				press($dev,1);
				pressPlace($dev,$place,1);
			}
			if (!$answer)
			{
				setlog('[sim_link:'.$dev.'] Positioning error!','link_'.$dev); // Лимит времени исчерпан
				$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':11,") WHERE `id`='.(int)$actId;
				mysqli_query($db, $qry); 
			}
			if ($adata['fool']) // Проверяем есть ли карта
			{
				$answer=sr_command($dev,'card>discover',30);
				setlog('Card Discover: '.$answer,'link_'.$dev);
				if ($answer=='NULL')
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
					return;
				}		
			}
			if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
			{
				$activation=sr_command($dev,'modem>activation',120);
			}
			else
			{
				$activation=sr_command($dev,'modem>activation',70);
			}
			setlog('[sim_link:'.$dev.'] Activation:'.$activation,'link_'.$dev);
			$activation=explode(';',$activation);
//			sleep(5);
		}
		else
		{
			$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':'.(int)$activation[0].',") WHERE `id`='.(int)$actId;
			mysqli_query($db, $qry); 

			if ($result = mysqli_query($db, 'SELECT id FROM `cards` WHERE `device`='.$dev.' AND `place`="'.remove_zero($place).'"'))
			{	
				if (!$resRow = mysqli_fetch_assoc($result))
				{
					// Добавляем новую карту
					$qry="INSERT INTO `cards` SET
					`place`='".remove_zero($place)."',
					`device`=".$dev.",
					`operator`=0,
					`time_number`='".time()."',
					`time`='".time()."'";
					mysqli_query($db,$qry);
				}
			}
			setlog('[sim_link:'.$dev.'] '.$qry,'link_'.$dev); // СИМ-карта не подключается
			setlog('[sim_link:'.$dev.'] The SIM card does not connect!','link_'.$dev); // СИМ-карта не подключается
			return;
		}
		if (!(int)$activation[0]){$report=(int)$activation[0];}
		$reconnect++;
		if ($activation[0]=='1' || $activation[0]=='5' || ($activation[0]=='3' && $GLOBALS['set_data']['code_reg']==2))
		{
			pressPlace($dev,$place);
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
						$answer=$f($dev,'SR-Nano',0,$place,$adata,$activation[2],$roaming);
						if ($GLOBALS['adata'])
						{
							mysqli_query($db, 'UPDATE `actions` SET `data`="'.serialize($GLOBALS['adata']).'" WHERE `id`='.(int)$actId); 
							setlog('[sim_link:'.$dev.'] Action DATA update!','link_'.$dev); // Обновлены данные задачи
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
						if (flagGet($dev,'pin'))
						{
							flagDelete($dev,'pin');
							setlog('[sim_link:'.$dev.'] !!!!!!!!!!!!!!!! PIN !!!!!!!!!!!!!!!!: '.$answer,'link_'.$dev);
							sr_command($dev,'modem>send:AT+CFUN=1,1');
							sleep(15);
						}
					}
				}
				setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'link_'.$dev);
				if ($answer)
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1, `success`=`success`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
					return;
				}
				sleep(1);
				setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','link_'.$dev);
				setlog('[sim_link:'.$dev.'] Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','link_'.$dev);
			}
		} 
		elseif ($activation[0]=='3' && $GLOBALS['set_data']['code_block']==2 && !$block_test)
		{
			pressPlace($dev,$place);
			setlog('[sim_link:'.$dev.'] SIM card is blocked!','link_'.$dev); // СИМ-карта заблокирована
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
			setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
			return;
		}

		if ($sleep)
		{
			setlog('[sim_link:'.$dev.'] Sleep '.$sleep,'link_'.$dev); // Ждем
			sleep($sleep);
		}
	}
}
else
{
	while ($time_limit+$GLOBALS['time_correct']>time())
	{
		$report=12;
		setlog('[sim_link:'.$dev.'] Extra time: '.$GLOBALS['time_correct'].' sek.','link_'.$dev);
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.(($time_limit+$GLOBALS['time_correct'])-time()).' sek.','link_'.$dev);
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if (!$reconnect || $reconnect==7)
		{
			if ($reconnect==7)
			{
				$answer=sr_command($dev,'answer>clear&&card>reposition',180);
				press($dev,1);
				pressPlace($dev,$place,1);
			}
			elseif ($reconnect==14)
			{
				setlog('[sim_link:'.$dev.'] The SIM card does not connect!','link_'.$dev); // СИМ-карта не подключается
				return;
			}
			else
			{
				$answer=sr_command($dev,'card:'.$place,60);
				press($dev);
			}
			if (!$answer)
			{
				setlog('[sim_link:'.$dev.'] Positioning error!','link_'.$dev); // Лимит времени исчерпан
				$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':11,") WHERE `id`='.(int)$actId;
				mysqli_query($db, $qry); 
				return;
			}	
			if ($adata['fool']) // Проверяем есть ли карта
			{
				$answer=sr_command($dev,'card>discover',30);
				setlog('Card Discover: '.$answer,'link_'.$dev);
				if ($answer=='NULL')
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
					return;
				}		
			}
			sr_command($dev,'modem>connect',10);
			sr_command($dev,'modem>on',10);
			$restart_time=time()+40;
			sleep(5);
		}
		$reconnect++;
		setlog('[sim_link:'.$dev.'] Getting information about Status','link_'.$dev);

		sr_command($dev,'modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
		$answer=sr_answer($dev,0,10,'+CREG');
		if ($answer=='error:no answer')
		{
			sr_command($dev,'modem>send:AT+CREG?'); // Repeated request for information about the operator | Повторный запрос информации об операторе
			$answer=sr_answer($dev,0,10,'+CREG');
		}
//		setlog('[sim_link:'.$dev.'] Answer:'.$answer);

		if ($answer && strpos($answer,'error:')===false)
		{
			preg_match('!:(.*)OK!Uis', $answer, $test);
			$test=trim($test[1]);
			if ($test=='0,1' || $test=='0,5')
			{
				pressPlace($dev,$place);
				setlog('[sim_link:'.$dev.'] Status:'.$test,'link_'.$dev);
				$a=explode(';',$func);
				$status=array();
				for ($k=0;$k<count($a);$k++)
				{
					if (!$done[$k])
					{
						$f=$a[$k]; 
						$GLOBALS['adata']='';
//						$answer=$f($dev,0,$place,$adata);
						if ($test=='0,5'){$roaming=1;} else {$roaming=0;}
//						$answer=$f($dev,0,$place,$adata,'',$roaming);
						$answer=$f($dev,'SR-Nano',0,$place,$adata,$activation[2],$roaming);
						if ($GLOBALS['adata'])
						{
							mysqli_query($db, 'UPDATE `actions` SET `data`="'.serialize($GLOBALS['adata']).'" WHERE `id`='.(int)$actId); 
							setlog('[sim_link:'.$dev.'] Action DATA update!','link_'.$dev); // Обновлены данные задачи
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
				setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'link_'.$dev);
				if ($answer)
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
					return;
				}
				$restart_time=time()+30;
			}
			elseif ($test=='0,3' && $GLOBALS['set_data']['code_block']==2 && !$block_test)
			{
				pressPlace($dev,$place);
				setlog('[sim_link:'.$dev.'] SIM card is blocked!','link_'.$dev); // СИМ-карта заблокирована
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
				setlog('[sim_link:'.$dev.'] Done!','link_'.$dev); // Готово
				return;
			} 
			elseif ($test=='0,2' || ($test=='0,3' && ($block_test || $GLOBALS['set_data']['code_block']!=2)))
			{
				if ($block_test){$block_test--;}
				$restart_time=time()+30;			
			} 
			elseif (($test=='0,0' || $test=='0,4') && $restart_time<time()+20)
			{
				$restart_time=0;			
				$report=(int)$activation[0];
			} 
		}

		if ($restart_time<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem','link_'.$dev);
			sr_command($dev,'modem>on&&modem>send:AT+CFUN=1,1'); // Перезапуск модема 
			$restart_time=time()+20;
		}
		if ($sleep)
		{
			setlog('[sim_link:'.$dev.'] Sleep '.$sleep,'link_'.$dev); // Ждем
			sleep($sleep);
		}
	}
}
//	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.remove_zero($place).':'.$report.',") WHERE `id`='.(int)$actId); 
	if ($time_limit==0)
	{
		setlog('[sim_link:'.$dev.'] Break!','link_'.$dev); // Досрочный выход с ошибкой
	}
	else
	{
		setlog('[sim_link:'.$dev.'] The time limit is reached!','link_'.$dev); // Лимит времени исчерпан
	}
}

// Инициализация модема в режиме Online
function connect_online($dev,$place,$data,$modemTime,$begin=0)
{
	global $db;

	if ($begin)
	{
		$place[1]=-2;
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
		setlog('[sim_link:'.$dev.'] Activation:'.$activation,'link_'.$dev);
		br($dev);
		$activation=explode(';',$activation);
		press($dev);
		if ($activation[0]=='1' || $activation[0]=='3' || $activation[0]=='5')
		{
			pressPlace($dev,$place[0]);
		}
		for ($i=0;$i<3;$i++)
		{
			if ($activation[0]=='1' || $activation[0]=='3' || $activation[0]=='5')
			{
				$place[1]=$activation[0];
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

				sr_command($dev,'modem>send:AT+CCID'); // Запрос ICCID
				$answer=sr_answer($dev,0,20,'AT+CCID');
				$iccid=str_replace('AT+CCID','',$answer);
				$iccid=str_replace('+CCID:','',$iccid);
				$iccid=str_replace('"','',$iccid);
				$iccid=str_replace('OK','',$iccid);
				$iccid=trim($iccid);
				setlog('[get_iccid:'.$dev.'] ICCID: '.$iccid,'link_'.$dev);

				return($out); // Выходим, статус модемов уже сохранили
			}
			else
			{
				if ($i)
				{
//					if ($activation[0]=='2')
//					{
//						$place[1]=2;
//					}
//					else
//					{
						$place[1]=(int)$activation[0];
//					}
					$out=0;
					break;
				}
				if ($begin)
				{
					sr_command($dev,'card>reposition',300);
					press($dev,1);
					pressPlace($dev,$place[0],1);
				}
				br($dev);
				$activation=sr_command($dev,'modem>activation',60);
				setlog('[sim_link:'.$dev.'] Activation:'.$activation,'link_'.$dev);
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
//			if ($begin)
//			{
				sr_command($dev,'modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
//			}
//			else
//			{
//				sr_command($dev,'modem>on&&modem>send:AT+CREG?'); // Getting status | Запрос статуса подключения 
//			}
			$answer=sr_answer($dev,0,30,'+CREG');
			if ($answer && strpos($answer,'error:')===false)
			{
				preg_match('!:(.*)OK!Uis', $answer, $test);
				$test=trim($test[1]);
				setlog('[online_mode:'.$dev.'] Status: '.$test,'link_'.$dev);
				if ($test=='0,1' || $test=='0,5')
				{
					$place[1]=$test[2];
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
					if ($begin){pressPlace($dev,$place[0]);}
					return($out); // Выходм, статус модемов уже сохранили
				}
/*
				elseif ($test=='0,3')
				{
					pressPlace($dev,$place[0]);
				}
*/
				elseif ($i==4 && ($test=='0,0' || $test=='0,4'))
				{
					if (!$n)
					{
						$place[1]=(int)$test[2];
						$out=0;
						break;
					}
					$n--;
					$i=0;
					sr_command($dev,'card>reposition',300);
					press($dev,1);
					pressPlace($dev,$place[0],1);
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
			else
			{
				sr_command($dev,'modem>on');
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

	setlog('[online_mode:'.$dev.'] Start','link_'.$dev);
	$smsTime=array();
	sr_answer_clear($dev);

	$place[1]=-1;
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($place)."' WHERE `time`=".$modemTime." AND `device`=".$dev);

	$answer=sr_command($dev,'card:'.$place[0],300);
	setlog('Status: '.$answer,'link_'.$dev);
	if ($answer!=1)
	{
		$answer=sr_command($dev,'card:'.$place[0],300);
		setlog('Status2: '.$answer,'link_'.$dev);
	}
	
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
		setlog('[online_mode:'.$dev.'] Status:'.$status,'link_'.$dev);
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
			setlog('[online_mode:'.$dev.'] Get SMS & Waiting...','link_'.$dev);

			$smsBuf=$answer=sr_answer($dev,$step,30);
			br($dev);

			setlog('[online_mode:'.$dev.'] Step: '.$step.' Answer: '.$answer,'link_'.$dev);
			$error="";
			if ($answer!="1" || $getCops<=0)
			{
				if ($getCops<=0 || $answer=="NO RESPONSE")
				{
//					sr_command($dev,'modem>on'); // Getting status | Запрос статуса подключения 
					$status=connect_online($dev,$place,$devData,$modemTime); // Подключение
					$getCops=4;
				}
				if ($smsBuf=='Error') // Ошибка
				{
					$place[1]=9;
//					if ($place[1]=-2){$place[1]=0;}
					setlog('[online_mode:'.$dev.'] Error receiving SMS!','link_'.$dev);
				}

				$data=explode('##',$smsBuf);
				$sms='';
				for ($i=1;$i<count($data);$i++)
				{
					$sms='';
					setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i],'link_'.$dev); // Получена SMS
					if ($data[$i])
					{
						$smsNum=explode(',',$data[$i]);
						$smsNum=$smsNum[0];
						setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum,'com_'.$dev); // Подготовка SMS

						$raw=explode("\n",$data[$i]);
						setlog('[online_mode:'.$dev.'] SMS: '.trim($raw[1]),'link_'.$dev); // Подготовка SMS
						$sms=$pdu->pduToText($raw[1]);

						setlog($raw[1],'sms');
						setlog(print_r($sms,1),'sms');
						setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1),'link_'.$dev); // Подготовка SMS
				
						// Если номер есть
						if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$place[0]."' AND `device`=".$dev)) 
						{
							if ($row = mysqli_fetch_assoc($result))
							{
                       						if (trim($sms['userDataHeader']))
								{
									$smsNum=0;
								}

								// Saving to the database | Сохранение в БД
								
								sms_save($sms['userDataHeader'],$row['number'],$row['email'],'',$sms['number'],$sms['unixTimeStamp'],$sms['message']);
								setlog('[online_mode:'.$dev.'] SMS saved','link_'.$dev); // SMS сохранена
					    			if ($GLOBALS['set_data']['email'])
								{
									setlog('[online_mode:'.$dev.'] SMS sent to E-mail','link_'.$dev); // SMS отправлена на E-mail
								}
								$smsTime[$m]=time();
							}
						}
						else  // Если номера нет
						{
							// Saving to the database | Сохранение в БД

							sms_save($sms['userDataHeader'],$place[0],'',$place[0],$sms['number'],$sms['unixTimeStamp'],$sms['message']);
							setlog('[online_mode:'.$dev.'] SMS saved','link_'.$dev); // SMS сохранена
				    			if ($GLOBALS['set_data']['email'])
							{
								setlog('[online_mode:'.$dev.'] SMS sent to E-mail','link_'.$dev); // SMS отправлена на E-mail
							}
							$smsTime[$m]=time();
						}
					}
					setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$place[1],'link_'.$dev);
					if ($smsNum>7 && $smsTime[$m] && $smsTime[$m]<time()-60 && $place[1]==1)
					{
						setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card','link_'.$dev);
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
//			sr_command($dev,'modem>on'); // Getting status | Запрос статуса подключения 
			$status=connect_online($dev,$place,$devData,$modemTime); // Подключение
		}
		if (flagGet($dev,'stop'))
		{
			flagDelete($dev,'stop');
			setlog('[online_mode:'.$dev.'] Early exit!','link_'.$dev); // Досрочный выход
			exit();
		}			
		br($dev);
	}
}

function dev_init($dev, $actId)
{
//	$dev		Device ID
	global $db;
	$answer=sr_command($dev,'display=16::C::64::25::Инициализация агрегатора&&modem>on',30);
//	$answer=sr_command($dev,'modem>on',30);
	if ($answer==1)
	{
		if ($result = mysqli_query($db, "SELECT `data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$data=unserialize($resRow['data']);
				sr_command($dev,'AT+GMM');		
				$answer=sr_answer($dev,0,20,'AT+GMM');
				mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=5 WHERE `id`='.(int)$actId);
				if (strpos($answer,'SIMCOM_SIM800C')!==false)
				{
					$data['modem']="SIM800";
					sr_command($dev,'AT+CLIP=1');		
					$answer=sr_answer($dev,0,20,'AT+CLIP');
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+30 WHERE `id`='.(int)$actId);
					sr_command($dev,'AT+CMGF=0');		
					$answer=sr_answer($dev,0,20,'AT+CMGF');
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+30 WHERE `id`='.(int)$actId);
					sr_command($dev,'AT&W0');		
					$answer=sr_answer($dev,0,20,'AT&W0');
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+30 WHERE `id`='.(int)$actId);
				}
				elseif (strpos($answer,'SIMCOM_SIM5320E')!==false)
				{
					$data['modem']="SIM5320";
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+90 WHERE `id`='.(int)$actId);
				}
				elseif (strpos($answer,'SIMCOM_SIM5360E')!==false)
				{
					$data['modem']="SIM5360";
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+90 WHERE `id`='.(int)$actId);
				}
				elseif (strpos($answer,'SIMCOM_SIM7100E')!==false)
				{
					$data['modem']="SIM7100";
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+90 WHERE `id`='.(int)$actId);
				}
//				sr_command($dev,'&&sound:beep');		
				sr_command($dev,'dev:mode=navigator&&save&&sound:beep&&sound:beep',30);		
				mysqli_query($db, 'UPDATE `actions` SET `progress`=99 WHERE `id`='.(int)$actId);
				sr_command($dev,'display=16::C::64::25::Готов к работе!',30);		
				mysqli_query($db, 'UPDATE `actions` SET `count`=1,`progress`=1,`success`=1 WHERE `id`='.(int)$actId);
				$qry="UPDATE `devices` SET `title`=`model`,`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				mysqli_query($db,$qry);
				return(1);
			}
		}
	}
	return(0);
}

function dev_calibration1($dev, $actId) // Настройка нулевого ряда
{
//	$dev		Device ID
	global $db;
	
	sr_command($dev,'fs>remove:/config/settings_c1&&fs>copy:/config/settings /config/settings_c1',10);
	sr_command($dev,'drv>set:x;ss=0&&drv>com:x;i&&drv>set:r;dh=400&&drv>set:r;dl=400&&drv>set:x;dh=400&&drv>set:x;dl=400&&answer>clear');
	$ss=0;
	mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=5 WHERE `id`='.(int)$actId);

	while ($ss<120)
	{
		$ss+=10;
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		$answer=sr_command($dev,'drv>test:r;5200',120);
		if (strpos($answer,'error')===false)
		{
			$a=$answer;
			$a=explode(';',$answer);
			if ($a[0]<10000 && $a[1]>28000)
			{
				mysqli_query($db, 'UPDATE `actions` SET `progress`=30 WHERE `id`='.(int)$actId);
				$l=$a[0]*2;
				$answer=sr_command($dev,'drv>search:r;5200,'.$l.',low',120);
				if (strpos($answer,'error')===false)
				{
					$a=explode(';',$answer);
					if ($a[0]==1)
					{
						mysqli_query($db, 'UPDATE `actions` SET `progress`=50 WHERE `id`='.(int)$actId);
						sr_command($dev,'drv>set:r;p=0&&drv>move:r;1000',10);
						mysqli_query($db, 'UPDATE `actions` SET `progress`=60 WHERE `id`='.(int)$actId);
						// Повторяем
						$answer=sr_command($dev,'drv>search:r;5200,'.$l.',low',120);
						if (strpos($answer,'error')===false)
						{
							$a=explode(';',$answer);
							if ($a[0]==1 && $a[1]>2000)
							{
								$ok=1;								
								break;
							}
						}
					}
				}
			}
		}
		sr_command($dev,'drv>set:x;ss='.$ss.'&&drv>com:x;i');
	}
	if ($ok)
	{
		mysqli_query($db, 'UPDATE `actions` SET `progress`=70 WHERE `id`='.(int)$actId);
		$s=0;
		$l=0;
		$m=0;
		for ($i=0;$i<10;$i++)
		{
			$answer=sr_command($dev,"drv>search:x;200",20);
			if (strpos($answer,'error')===false)
			{
				$a=explode(';',$answer);
				$b=explode(',',$a[0]);
				$c=explode(',',$a[1]);
				$s+=($b[0]+$c[0])/2;
				$l+=($b[1]+$c[1])/2;
				$m++;
			}
			mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+2 WHERE `id`='.(int)$actId);
		}
		setlog("M:".$m,'link_'.$dev);
		$s=round($s/$m);
		$l=round($l/$m);
		if ($l<30){$l=200;} 
		elseif ($l<100){$l=$l*7;} 
		elseif ($l<1000){$l=$l*3;} 
		else {$l=$l*2;} 

		sr_command($dev,'restart',20);
		mysqli_query($db, 'UPDATE `actions` SET `progress`=99 WHERE `id`='.(int)$actId);
		sr_command($dev,'drv>set:x;ss='.(int)$s.'&&sensor>set:0;l='.$l);
		sr_command($dev,'save');
		sr_command($dev,'sound:beep&&sound:beep',20);
		mysqli_query($db, 'UPDATE `actions` SET `count`=1,`progress`=100,`success`=1 WHERE `id`='.(int)$actId);
		return(1);
	}
	sr_command($dev,'fs>rename:/config/settings_c1 /config/settings',30);
	mysqli_query($db, 'UPDATE `actions` SET `count`=1,`progress`=100,`errors`=1,`report`="0 position:101" WHERE `id`='.(int)$actId);
	return(0);
}

function dev_calibration2($dev, $actId) // Настройка A0
{
//	$dev		Device ID
	global $db;
	
	sr_command($dev,'fs>remove:/config/settings_c2&&fs>copy:/config/settings /config/settings_c2',10);
	sr_command($dev,'card:A0',30);
	mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=1 WHERE `id`='.(int)$actId);
	$s0=sr_command($dev,'sensor>set:0;o',30);
	$errors=array();
	$sensor0=array();
	$p=3;
	// Грубо
	for ($n=-27;$n<28;$n=$n+3) 
	{
		$sensor0[]=$n;
		sr_command($dev,'sensor>set:0;o='.$n,30);
		$answer=sr_command($dev,'card>null',120);
		if ($answer==1)
		{
			$answer=sr_command($dev,'modem>activation',60);
			if ($answer[0]==1 || $answer[0]==3 || $answer[0]==5)
			{
				$errors[]=0;
			}
			else
			{
				$errors[]=1;
			}
			br($dev,'act_'.$actId.'_stop');
			br($dev);
		}
		else
		{
			$errors[]=1;
		}
		$qry='UPDATE `actions` SET `progress`=`progress`+'.$p.' WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		// Ищем лучший вариант
	}
	setlog('Errors:'.print_r($errors,1),'link_'.$dev);
	setlog('E:'.json_encode($errors),'link_'.$dev);
	setlog('Sensor0:'.print_r($sensor0,1),'link_'.$dev);
	setlog('S:'.json_encode($sensor0),'link_'.$dev);
	$res=analize($errors,$sensor0);
	setlog(print_r($res,1),'link_'.$dev);
	// Точно
	if (!$res['state'])
	{
		sr_command($dev,'sensor>set:0;o='.$s0,30);
		$qry='UPDATE `actions` SET `count`=1,`progress`=1,`errors`=1,`report`="<h1>Ошибка</h1>Не удалось выполнить предварительную калибровку!<br>Требуется вмешательство специалиста." WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		return(0);
	}
	$errors=array();
	$sensor0=array();
	for ($n=$res['sensor0']-5;$n<$res['sensor0']+6;$n++) 
	{
		$sensor0[]=$n;
		sr_command($dev,'sensor>set:0;o='.$n,30);
		$answer=sr_command($dev,'card>null',120);
		if ($answer==1)
		{
			$answer=sr_command($dev,'modem>activation',60);
			if ($answer[0]==1 || $answer[0]==3 || $answer[0]==5)
			{
				$errors[]=0;
			}
			else
			{
				$errors[]=1;
			}
			br($dev,'act_'.$actId.'_stop');
			br($dev);
		}
		else
		{
			$errors[]=1;
		}
		$qry='UPDATE `actions` SET `progress`=`progress`+'.$p.' WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		// Ищем лучший вариант
	}
	$res=analize($errors,$sensor0);
	setlog(print_r($res,1),'link_'.$dev);
	if ($res['state'])
	{
		$qry='UPDATE `actions` SET `progress`=99 WHERE `id`='.(int)$actId;
		sr_command($dev,'sensor>set:0;o='.$res['sensor0'].'&&save');
		sr_command($dev,'modem>disconect');
		$log['best']=$best;
		$log['s0']=$s0;
		$log['s1']=$s1;
		$log['errors']=$errors;
		$log['sensor0']=$sensor0;
		$log['sensor1']=$sensor1;
		setlog(print_r($log,1),'link_'.$dev);
		$qry='UPDATE `actions` SET `progress`=1,`success`=1,`count`=1 WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		return(1);
	}
	// Настроить не удалось - восстанавливаем
	sr_command($dev,'sensor>set:0;o='.$s0);
	sr_command($dev,'modem>disconect');
	sr_command($dev,'sound:beep&&sound:beep',20);
	$qry='UPDATE `actions` SET `progress`=1,`count`=1,`errors`=1,`report`="<h1>Ошибка</h1>Не удалось выполнить калибровку!<br>Требуется вмешательство специалиста." WHERE `id`='.(int)$actId;
	mysqli_query($db, $qry); 
	return(0);
}

function dev_calibration3($dev, $actId) // Настройка A1
{
//	$dev		Device ID
	global $db;
	
	sr_command($dev,'fs>remove:/config/settings_c3&&fs>copy:/config/settings /config/settings_c3',10);
	sr_command($dev,'card:A0',30);
	mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=1 WHERE `id`='.(int)$actId);
	$s0=sr_command($dev,'sensor>set:1;o',30);
	$errors=array();
	$sensor0=array();
	$p=3;
	// Грубо
	for ($n=-27;$n<28;$n=$n+3) 
	{
		$sensor0[]=$n;
		sr_command($dev,'sensor>set:1;o='.$n,30);
		sr_command($dev,'card>null',30);
		$answer=sr_command($dev,'card:A1',30);
		if ($answer==1)
		{
			$answer=sr_command($dev,'modem>activation',60);
			if ($answer[0]==1 || $answer[0]==3 || $answer[0]==5)
			{
				$errors[]=0;
			}
			else
			{
				$errors[]=1;
			}
			br($dev,'act_'.$actId.'_stop');
			br($dev);
		}
		else
		{
			$errors[]=1;
		}
		$qry='UPDATE `actions` SET `progress`=`progress`+'.$p.',`success`=`success`+'.$p.' WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		// Ищем лучший вариант
	}
	setlog('Errors:'.print_r($errors,1),'link_'.$dev);
	$res=analize($errors,$sensor0);
	setlog(print_r($res,1),'link_'.$dev);
	// Точно
	if (!$res['state'])
	{
		sr_command($dev,'sensor>set:1;o='.$s0,30);
		$qry='UPDATE `actions` SET `progress`=1,`errors`=1,`report`="<h1>Ошибка</h1>Не удалось выполнить предварительную калибровку!<br>Требуется вмешательство специалиста." WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		return(0);
	}
	$errors=array();
	$sensor0=array();
	for ($n=$res['sensor0']-5;$n<$res['sensor0']+6;$n++) 
	{
		$sensor0[]=$n;
		sr_command($dev,'sensor>set:1;o='.$n,30);
		sr_command($dev,'card>null',30);
		$answer=sr_command($dev,'card:A1',30);
		if ($answer==1)
		{
			$answer=sr_command($dev,'modem>activation',60);
			if ($answer[0]==1 || $answer[0]==3 || $answer[0]==5)
			{
				$errors[]=0;
			}
			else
			{
				$errors[]=1;
			}
			br($dev,'act_'.$actId.'_stop');
			br($dev);
		}
		else
		{
			$errors[]=1;
		}
		$qry='UPDATE `actions` SET `progress`=`progress`+'.$p.',`success`=`success`+'.$p.' WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		// Ищем лучший вариант
	}
	$res=analize($errors,$sensor0);
	setlog(print_r($res,1),'link_'.$dev);
	if ($res['state'])
	{
		sr_command($dev,'sensor>set:1;o='.$res['sensor0'].'&&save');
		sr_command($dev,'modem>disconect');
		$log['best']=$best;
		$log['s0']=$s0;
		$log['s1']=$s1;
		$log['errors']=$errors;
		$log['sensor0']=$sensor0;
		$log['sensor1']=$sensor1;
		setlog(print_r($log,1),'link_'.$dev);
		$qry='UPDATE `actions` SET `progress`=1,`success`=1,`count`=1 WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
		return(1);
	}
	// Настроить не удалось - восстанавливаем
	sr_command($dev,'sensor>set:1;o='.$s0);
	sr_command($dev,'modem>disconect');
	sr_command($dev,'sound:beep&&sound:beep',20);
	$qry='UPDATE `actions` SET `progress`=1,`errors`=1,`report`="<h1>Ошибка</h1>Не удалось выполнить калибровку!<br>Требуется вмешательство специалиста." WHERE `id`='.(int)$actId;
	mysqli_query($db, $qry); 
	return(0);
}

function analize($errors,$sensor0="",$sensor1="",$multiplier=1,$serial=1) // Поиск лучшего значения
{
	$s=array();
	$k=array();
	$sn=0;
	$ok=0;
	foreach ($errors AS $key=>$data)
	{
		if (!$data)
		{
			$ok=1;
			if (!$s[$sn])
			{
				$s[$sn]=1;
				$k[$sn]=$key;
			}
			else
			{
				$s[$sn]++;
			}
		}	
		else
		{
			$sn++;
		}
	}
// Если есть попытки без ошибок
	if ($ok)
	{
		$max=0;
		foreach ($s AS $key=>$data)
		{
			if ($data>$max)
			{
				$max=$data;
				$pos=$key;
			}
		}
		if ($max>$serial && $max/1.7<count($errors))
		{
			$out=array('state'=>1,'best'=>round(($k[$pos]+$max/2)*$multiplier)+$multiplier,'sensor0'=>$sensor0[round($pos+$max/2)],'sensor1'=>$sensor1[round($pos+$max/2)],'max'=>$max);
			return($out);
		}
		elseif ($max>$serial)
		{
			$out=array('state'=>0,'best'=>round(($k[$pos]+$max/2)*$multiplier)+$multiplier,'sensor0'=>$sensor0[round($pos+$max/2)],'sensor1'=>$sensor1[round($pos+$max/2)],'action'=>'minus','max'=>$max);
			return($out);
		}
	}	
// Если нет попыток без ошибок, то ищем лучший вариант
	$min=5;
	foreach ($errors AS $key=>$data)
	{
		if ($data<$min)
		{
			$min=$data;
			$pos=$key;
			$s0=$sensor0[$key];
			$s1=$sensor1[$key];
		}
	}
	$out=array('state'=>0,'best'=>$pos*$multiplier+$multiplier,'sensor0'=>$s0,'sensor1'=>$s1,'action'=>'plus','max'=>$max);
	return($out);
}

?>