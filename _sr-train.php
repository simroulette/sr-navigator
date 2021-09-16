<?
// ===================================================================
// Sim Roulette -> SR-Train functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
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
	sr_answer_clear($dev);
	$connect=time();
	$reconnect=0; // The count of reconnections | Счетчик переподключений
	$modem_shift=0; // The shift of the modems on the 3rd row forwards/backwards | Сдвиг модемов на 3 ряда вперед/назад
	$mod=explode(',',trim($modems,','));
	$progress=0;
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
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.($time_limit-time()).' sek.','com_'.$dev);
		br($dev,'act_'.$actId.'_stop');
		if ($reconnect>5 && $reconnect<10)
		{
			sr_command($dev,'row:'.$otherRow.'&&row:'.$curRow.'&&modem>connect&&modem>on');
			$reconnect=10;
			$connect=time();
			setlog('[sim_link:'.$dev.'] Repositioning of the modem','com_'.$dev);
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
			setlog('[sim_link:'.$dev.'] Connecting another modem line','com_'.$dev);
		}

		$error='';
		setlog('[sim_link:'.$dev.'] Getting information about operators','com_'.$dev);
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
					if ($mod[$a[0]]['status']!=2)
					{
						$mod[$a[0]]['status']=1;
						$mod[$a[0]]['link']=1;
					}
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
			setlog('[sim_link:'.$dev.'] Modem:'.$key.', Operator:'.$data['operator'].', Status:'.$data['status'],'com_'.$dev);
			if ($data['status']==1) // The modem is ready to work | Модем готов к работе
			{
				if ($restart && $error)
				{
					setlog('[sim_link:'.$dev.'] Restarting the modem: '.$error,'com_'.$dev);
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
						setlog('[sim_link:'.$dev.'] The start of the function: '.$f,'com_'.$dev); // Запуск функции
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
						$answer=$f($dev,$r,$m,$adata);//,$data['operator']);
						setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'com_'.$dev);
						if ($answer && strlen($answer)>1)
						{
							actionReport($actId,$a[$k].': '.$curRow.'-'.$key.' -> '.$answer);
							$time_limit=0;
						}
						elseif ($answer)
						{
							$test--;
							$mod[$key]['status']=2;
							mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
							$progress++;
						}
						setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'com_'.$dev);
					}			
				}
			}
			if ($data['status']==2) // Модем обработан
			{
				setlog('[sim_link:'.$dev.'] Modem processed, left: '.$test,'com_'.$dev);
				$test--;
				if (!$test)
				{
					setlog('[sim_link:'.$dev.'] Done!','com_'.$dev); // Готово
					return;
				}				
			}	

		}
		if ($restart && $error && $connect+40<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem: '.$error,'com_'.$dev);
			sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
			$reconnect++;
			$restart=0;
			$connect=time();
		}
	}
	$total=count($mod);
	if ($progres<$total)
	{
		$errors=0;
		foreach ($mod AS $key=>$data)
		{
			if (!$data['link'] && $mod[$i]['status']!=2){$errors++;}
		}
		mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+'.($total-$progress).',`errors`=`errors`+'.$errors.' WHERE `id`='.(int)$actId); 
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!','com_'.$dev); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $curRow, $modems, $devData)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start','com_'.$dev);
	$max_row=$devData['rows'];
	$modems=trim($modems,',');
	$modems=str_replace(',','--',$modems);
	$smsTime=array();
	$mod=explode('--',$modems);
	sr_answer_clear($dev);
	if ($curRow==0){$r=$curRow+1;} else {$r=$curRow-1;}

	$restart=0;
	$connect=time();
	$getCops=1;

	sr_command($dev,'row:'.$r.'&&row:'.$curRow.'&&modem>connect'.'&&modem>on');
	$mm=array();

	foreach ($mod AS $data)
	{
		if ($mm[$data][1]!=-2)
		{
			$mm[$data]=array($curRow,-2);
		}
	}

	while (1)
	{
		$step=sr_command($dev,'modem>sms:0');
		$answer=sr_answer($dev,$step,30);
		$smsBuf=$answer=explode('#3#',$answer);
		$error='';
		if ($answer!="1" || $getCops<=0)
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network','com_'.$dev);
				$step=sr_command($dev,'modem>pack:AT+CREG?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
				$answer=sr_answer($dev,$step,30);
				if (strpos($answer,'error:')===false)
				{
					$answer=explode('#1#',$answer);
					$getCops=5;
					foreach ($mod AS $data)
					{
						$mm[$data][1]=-2;
					}
					for ($i=0;$i<count($answer);$i++)
					{
						$a=explode('##',$answer[$i]);
						preg_match('!:(.*)OK!Uis', $a[1], $test);
						$test=trim($test[1]);

						if ($test[2]=='0' || $test[2]=='2' || $test[2]=='3' || $test[2]=='4')
						{
							$restart++;
							if ($restart<12)
							{
								$getCops=2;
							}
							else
							{
								$getCops=4;
							}
						}
						if ($test[2]=='0' || $test[2]=='4' || ($test[2]=='2' && $connect+60<time()))
						{
							$error.=$a[0].';';
						}
						elseif ($a[0] && $test[2]!=2 && $test[2]!=3)
						{
							$mm[$a[0]][1]=1;
						}
					}
				}
				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error,'com_'.$dev);
					sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
					$connect=time();
				}

			}
			$getCops--;
			foreach ($smsBuf AS $data)
			{
				$data=explode('##',$data);
				$m=$data[0];
				if ($data[1]=='E') // Ошибка
				{
					if ($mm[$m][1]!=-2){$mm[$m][1]=0;}
					setlog('[online_mode:'.$dev.'] Error receiving SMS!','com_'.$dev);
				}
				else
				{
					for ($i=1;$i<count($data);$i++)
					{
						if (in_array($m,$mod))
						{
							$sms='';
							setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i],'com_'.$dev); // Получена SMS
							if ($data[$i])
							{
								$smsNum=explode(',',$data[$i]);
								$smsNum=$smsNum[0];
								setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum,'com_'.$dev); // Подготовка SMS
						
								$raw=explode("\n",$data[$i]);
								$sms=$pdu->pduToText($raw[1]);
								setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1),'com_'.$dev); // Подготовка SMS
				
								if ($m>8){$p=$m-8;$r=$curRow+3;} else {$p=$m;$r=$curRow;}
								if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$r."-".$p."' AND `device`=".$dev)) 
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
										setlog('[online_mode:'.$dev.'] '.$qry,'com_'.$dev);
										setlog('[online_mode:'.$dev.'] SMS saved','com_'.$dev); // SMS сохранена
							    			if ($GLOBALS['set_data']['email'])
										{
											setlog('[online_mode:'.$dev.'] SMS sent to E-mail','com_'.$dev); // SMS отправлена на E-mail
										}
									}
								}
								else
								{
									setlog('[online_mode:'.$dev.'] SIM card not found in the database!','com_'.$dev); // СИМ-карта не найдена в БД
									return($out);
								}
							}
							setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$m]).', Status: '.$place[1],'com_'.$dev);
							if ($smsNum>7)
							{
								setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card','com_'.$dev);
								sr_command($dev,'modem>select:'.$m.'&&modem>send:AT+CMGDA=5'); // Удаление всех SMS с SIM-карты
							$smsNum=0;
							}
						}
					}
				}
			}
			if ($error)
			{
				setlog('[online_mode:'.$dev.'] Errors: '.$error,'com_'.$dev);
			}
		}
		if ($result = mysqli_query($db, "SELECT time FROM `modems` WHERE `device`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				if ($row['time']<time()-30)
				{
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($mm)."' WHERE `device`=".$dev);
				}
			}
			else
			{
				if (flagGet($dev,'stop'))
				{
					flagDelete($dev,'stop');
				}
				setlog('[online_mode:'.$dev.'] Early exit!','com_'.$dev); // Досрочный выход
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

function dev_init($dev)
{
//	$dev		Device ID
	global $db;
	$answer=sr_command($dev,'dev:mode=navigator',20);
	if ($answer=='Navigator mode ON')
	{
		sr_command($dev,'save&&sound:beep');		
		if ($result = mysqli_query($db, "SELECT `data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$data=unserialize($resRow['data']);
				$data['rows']=sr_command($dev,'row:max',20);
				$qry="UPDATE `devices` SET `title`=`model`,`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				mysqli_query($db,$qry);
				return(1);
			}
		}
	}
	return(0);
}

?>