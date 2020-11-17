<?
// ===================================================================
// Sim Roulette -> SR-Train functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

// Container function: Selecting a row, connecting contacts, powering modems, checking connections, and performing the following functions                        
// Функция-контейнер: Выбор ряда, подключение контактов, включение модемов, проверка связи и выполнение перечисленных функций
function sim_link($dev, $data, $curRow, $modems, $actId, $func, $adata)
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
	$max_row=$data['rows'];
	$time_limit=time()+$data['time_limit'];
	sr_answer_clear();
	$connect=time();
	$reconnect=0; // The count of reconnections | Счетчик переподключений
	$modem_shift=0; // The shift of the modems on the 3rd row forwards/backwards | Сдвиг модемов на 3 ряда вперед/назад
	$mod=explode(',',trim($modems,','));
	sort($mod);
	$mod=array_flip($mod);
	foreach ($mod AS $key=>$data)
	{
		$mod[$key]=array();
	}
	for ($i=1;$i<17;$i++)
	{
		if (!isset($mod[$i]))
		{
			$mod[$i]['status']=2;
		}
	}
	// The choice of the row, connecting contacts and enabling the modem | Выбор ряда, подключение контактов и включение модемов
	if ($curRow==0){$otherRow=$curRow+1;} else {$otherRow=$curRow-1;}
	sr_command($dev,'row:'.$otherRow.'&&row:'.$curRow.'&&modem>connect&&modem>on');

	while ($time_limit>time())
	{
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.($time_limit-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if ($reconnect>5 && $reconnect<10)
		{
			sr_command($dev,'row:'.$otherRow.'&&row:'.$curRow.'&&modem>connect&&modem>on');
			$reconnect=10;
			$connect=time();
			setlog('[sim_link:'.$dev.'] Repositioning of the modem');
		}
		elseif ($reconnect>20 && $reconnect<100)
		{
			// Changing the modem line | Меняем линию модемов
			if ($curRow<=$max_row-3)
			{
				$modem_shift=1;
			}
			elseif ($curRow>=3)
			{
				$modem_shift=-1;
			}
			if ($modem_shift!=0)
			{
				sr_command($dev,'row:'.($curRow+$modem_shift*3).'&&modem>connect&&modem>on');
			}
			$reconnect=100;
			$connect=time();
			setlog('[sim_link:'.$dev.'] Connecting another modem line');
		}

		$error='';
		setlog('[sim_link:'.$dev.'] Getting information about operators');
		$step=sr_command($dev,'modem>pack:AT+COPS?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
		$answer=explode('#1#',sr_answer($dev,$step,30));
		for ($i=0;$i<count($answer);$i++)
		{
			if (strpos($answer[$i],'##'))
			{
				$a=explode('##',$answer[$i]);
		                preg_match('!"(.*)"!Us', $a[1], $test);
				if ($a[0] && $test[1])
				{
					$mod[$a[0]]['operator']=$test[1];
					if ($mod[$a[0]]['status']!=2){$mod[$a[0]]['status']=1;}
				}
				elseif ($a[0])
				{
					$mod[$a[0]]['operator']=$test[1];
					if ($mod[$a[0]]['status']!=2)
					{
						$mod[$a[0]]['status']=0;
						if ((int)$a[0])
						{
							$error.=$a[0].';';
						}
					}
				}
			}
		}
		$restart=1;
		$test=count($mod);
		foreach ($mod AS $key=>$data)
		{
			br($dev,'act_'.$actId.'_stop'); // Checking the early exit flag | Проверка флага досрочного выхода
			br($dev);
			setlog('[sim_link:'.$dev.'] Modem:'.$key.', Operator:'.$data['operator'].', Status:'.$data['status']);
			if ($data['status']==1) // The modem is ready to work | Модем готов к работе
			{
				if ($restart && $error)
				{
					setlog('[sim_link:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
					$reconnect++;
					$restart=0;
				}
				$a=explode(';',$func);
				if ($data['status']==1)
				{
					for ($k=0;$k<count($a);$k++)
					{
						$f=$a[$k]; 
						setlog('[sim_link:'.$dev.'] The start of the function: '.$f); // Запуск функции
						$m=$key;
						$r=$curRow;
						if ($modem_shift==-1) // Changing the modem line 9-15 -> 1-8 | Меняем линию модемов 9-15 -> 1-8
						{
							$m=$m+8;
						}
						elseif ($modem_shift==1) // Changing the modem line 1-8 -> 9-15 | Меняем линию модемов 1-8 -> 9-15
						{
							$m=$m-8;
							$r=$r+3;	
						}
						sr_command($dev,'modem>select:'.$key);
						$answer=$f($dev,$r,$m,$adata,$data['operator']);
						if ($answer)
						{
							$test--;
							$mod[$key]['status']=2;
							mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
						}
						setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
					}			
				}
			}
			if ($data['status']==2) // Модем обработан
			{
				setlog('[sim_link:'.$dev.'] Modem processed, left: '.$test);
				$test--;
				if (!$test)
				{
					setlog('[sim_link:'.$dev.'] Done!'); // Готово
					return;
				}				
			}	

		}
		if ($restart && $error && $connect+40<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem: '.$error);
			sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
			$reconnect++;
			$restart=0;
			$connect=time();
		}
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $curRow, $modems)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db;
	setlog('[online_mode:'.$dev.'] Start');
	$max_row=19;
	$modems=trim($modems,',');
	$modems=str_replace(',','--',$modems);
	$smsTime=array();
	$getCops=4;
	$mod=explode('--',$modems);
	sr_answer_clear();
	if ($curRow==0){$r=$curRow+1;} else {$r=$curRow-1;}

	sr_command($dev,'row:'.$r.'&&row:'.$curRow.'&&modem>connect'.'&&modem>on');
	$mm=array();

	while (1)
	{
		foreach ($mod AS $data)
		{
			if ($mm[$data][1]!=-2)
			{
				$mm[$data]=array($curRow,1);
			}
		}
		$step=sr_command($dev,'modem>sms:1');
		$answer=sr_answer($dev,$step,30);
		$smsBuf=$answer=explode('#3#',$answer);
		$error="";
		if ($answer!="1")
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+COPS?##ALL##1'); // Мониторинг подключения к сотовой сети
				$answer=explode('#1#',sr_answer($dev,$step,30));
				foreach ($mod AS $data)
				{
					$mm[$data][1]=-2;
				}
				for ($i=0;$i<count($answer);$i++)
				{
					$a=explode('##',$answer[$i]);
			                preg_match('!"(.*)"!Us', $answer[$i], $test);
					if ($a[0] && $test[1])
					{
						$mm[$a[0]][1]=1;
					}
					elseif ($a[1])
					{
						if ((int)$a[0])
						{
							$error.=$a[0].';';
						}
					}
				}
				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 

				}
				$getCops=4;
			}
			$getCops--;
			foreach ($smsBuf AS $data)
			{
				$data=explode('##',$data);
				$m=$data[0];
				if ($data[1]=='E') // Ошибка
				{
					if ($mm[$m][1]!=-2){$mm[$m][1]=0;}
					setlog('[online_mode:'.$dev.'] Error receiving SMS!');
				}
				for ($i=1;$i<count($data);$i++)
				{
					if (in_array($m,$mod))
					{
						$sms='';
						setlog('[online_mode:'.$dev.'] Modem: '.$m);
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
							if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$r."-".$p."'")) 
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
						setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$mm[$m][1]);
						if ($smsNum>7 && $smsTime[$m] && $smsTime[$m]<time()-60 && $mm[$m][1]==1)
						{
							setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card');
							sr_command($dev,'modem>select:'.$m.'&&modem>send:AT+CMGDA="DEL ALL"'); // Удаление всех SMS с SIM-карты
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
				if ($row['time']<time()-30)
				{
					mysqli_query($db, "REPLACE INTO `modems` SET `device`=".$dev.", `modems`='".serialize($mm)."'");
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

// Getting the path length
// Получение длины пути
function dev_rows($dev)
{
//	$dev		Device ID
	global $db;
	$rows=sr_command($dev,'row>calc',300);
	if ($rows!=(int)$rows)
	{
		return(0);
	}
	$qry="UPDATE `devices` SET `data`='".serialize(array('data'=>$rows))."' WHERE `id`=".(int)$dev;
	mysqli_query($qry,$db);
	return(1);
}
?>