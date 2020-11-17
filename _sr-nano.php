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

	sr_answer_clear();
	$connect=time();
	$reconnect=0; // The count of reconnections | Счетчик переподключений
	$done=array();

	while ($time_limit>time())
	{
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.($time_limit-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if (!$reconnect || $reconnect==7)
		{
			sr_command($dev,'card:'.$place,20);
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
						$answer=$f($dev,0,$place,$adata,'');
						if ($answer)
						{
							$done[$k]=1;
						}
					}
				}
				if ($answer)
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!'); // Готово
					return;
				}
				setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
			}
			elseif ($test=='0,3')
			{
				setlog('[sim_link:'.$dev.'] SIM card blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".$place."'";
				mysqli_query($db,$qry);

				// Saving the number | Сохраняем номер
				$qry="REPLACE INTO `cards` SET
				`number`='".($place=remove_zero($place))."',
				`place`='".$place."',
				`device`=".(int)$dev.",
				`operator`=0,
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);

				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
				return;
			} 
			elseif (($test=='0,0' || $test=='0,4') && $restart_time<time()+20)
			{
				$restart_time=0;			
			} 
		}

		elseif ($restart_time<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem');
			sr_command($dev,'modem>send:AT+CFUN=1,1'); // Перезапуск модемов 
		}
		setlog('[sim_link:'.$dev.'] Sleep 30'); // Лимит времени исчерпан
		sleep($sleep);
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $place)
{
//	$dev		Device ID
//	$place 		Place on SR-Nano

	global $db;
	setlog('[online_mode:'.$dev.'] Start');
	$max_row=19;
	$smsTime=array();
	$getCops=4;
	sr_answer_clear();

	sr_command($dev,'card:'.$place[0].'&&modem>connect'.'&&modem>on');
	$mm=array();

	while (1)
	{
		if ($place[1]=-2)
		{
			$place[1]=1;
		}
		$step=sr_command($dev,'modem>sms:1');
		$smsBuf=$answer=sr_answer($dev,$step,30);
		setlog('[online_mode:'.$dev.'] Answer: '.$answer);
		$error="";
		if ($answer!="1")
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>send:AT+COPS?'); // Мониторинг подключения к сотовой сети
				$answer=sr_answer($dev,$step,30);
				$place[1]=-2;
				for ($i=0;$i<count($answer);$i++)
				{
					$a=explode('##',$answer[$i]);
			                preg_match('!"(.*)"!Us', $answer[$i], $test);
					if ($a[0] && $test[1])
					{
						$place[1]=1;
					}
					elseif ($a[1])
					{
						if ((int)$a[0])
						{
							$error=1;
						}
					}
				}
				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>send:AT+CFUN=1,1'); // Перезапуск модемов 

				}
				$getCops=4;
			}
			$getCops--;

			if ($smsBuf=='Error') // Ошибка
			{
				if ($place[1]=-2){$place[1]=0;}
				setlog('[online_mode:'.$dev.'] Error receiving SMS!');
			}
			$data=explode('##',$smsBuf);
			for ($i=1;$i<count($data);$i++)
			{
				$sms='';
		                preg_match('!(.*),"(.*)","(.*)","(.*)"(.*)#END#!Us', $data[$i].'#END#', $test);
				$a=explode(',',$test[4]);
				$b=explode('/',$a[0]);
				$a=explode('+',$a[1]);
				$c=explode(':',$a[0]);
			
				$tm=mktime($c[0],$c[1],$c[2],$b[1],$b[2],$b[0]);
				if (trim($test[5]))
				{
					$smsNum=trim($test[1]);
					$txt=$test[5];
					$sms=array('sender'=>$test[2],'time'=>$tm,'txt'=>$txt);
					setlog('[online_mode:'.$dev.'] SMS received: '.$sms['sender'].', '.$sms['time'].', '.$sms['txt']); // Получена SMS
					// Getting a SIM card number | Получение номера SIM-карты
					if ($m>8){$p=$m-8;$r=$curRow+3;} else {$p=$m;$r=$curRow;}
					if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$place[0]."'")) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$update=0;
							// Checking a new SMS or part of a previous one | Проверка новая SMS или часть предыдущей
							if ($result = mysqli_query($db, "SELECT * FROM `sms_incoming` WHERE `number`='".$row['number']."' AND `sender`='".$sms['sender']."' AND `time`>".($sms['time']-10))) 
							{
								if ($row2 = mysqli_fetch_assoc($result))
								{
									$qry="DELETE `sms_incoming` WHERE `id`=".$row2['id'];
									// Saving to the database | Сохранение в БД
									$qry="INSERT INTO `sms_incoming` SET
									`number`='".$row['number']."',
									`sender`='".$sms['sender']."',
									`time`=".$sms['time'].",
									`modified`=".$row2['time'].",
									`txt`='".sms_prep($row2['txt'].$sms['txt'])."'"; 
									mysqli_query($db,$qry);
									setlog('[online_mode:'.$dev.'] Added part of the SMS'); // Дописана часть SMS
									$update=1;
							    		if ($GLOBALS['set_data']['email'])
									{
										mail($GLOBALS['set_data']['email'], "SR Roulette", "Sender: ".$row['number']."\nTime: ".$sms['time']."\nText: ".sms_prep($sms['txt']));
										setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
									}
									$smsTime[$m]=time();
								}
							}
							if (!$update)
							{
								// Saving to the database | Сохранение в БД
								$qry="INSERT INTO `sms_incoming` SET
								`number`='".$row['number']."',
								`sender`='".$sms['sender']."',
								`time`=".$sms['time'].",
								`modified`=".time().",
								`txt`='".sms_prep($sms['txt'])."'";
								mysqli_query($db,$qry);
								setlog('[online_mode:'.$dev.'] SMS saved'); // SMS сохранена
						    		if ($GLOBALS['set_data']['email'])
								{
									mail($GLOBALS['set_data']['email'], "SR Roulette", "Sender: ".$row['number']."\nTime: ".$sms['time']."\nText: ".sms_prep($sms['txt']));
									setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
								}
								$smsTime[$m]=time();
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
				if ($smsNum>7 && $smsTime[$m] && $smsTime[$m]<time()-60 && $place[1]==1)
				{
					setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card');
					sr_command($dev,'modem>send:AT+CMGDA="DEL ALL"'); // Удаление всех SMS с SIM-карты
					$smsNum=0;
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
				if ($row['time']<time()-30)
				{
					mysqli_query($db, "REPLACE INTO `modems` SET `device`=".$dev.", `modems`='".serialize($place)."'");
				}
			}
			else
			{
				setlog('[online_mode:'.$dev.'] Emergency exit!'); // Экстренный выход
				exit();
			}			
		}
		br($dev);
	}
}

?>