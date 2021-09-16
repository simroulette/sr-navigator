<?
// ===================================================================
// Sim Roulette -> SR-Box-Bank functions
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

	$time_limit=time()+$data['time_limit'];
	sr_answer_clear($dev);

	$progress=0;
	$mod=explode(',',trim($modems,','));
	sort($mod);
	$mod=array_flip($mod);

	foreach ($mod AS $key=>$data)
	{
		$mod[$key]=array();
	}

	for ($i=1;$i<9;$i++)
	{
		if (!isset($mod[$i]))
		{
			$mod[$i]['status']=2;
		}
	}

	// Выбираем ряд
	sr_command($dev,'modem>off&&modem>select:1&&modem>card:'.$curRow.'&&modem>select:2&&modem>card:'.$curRow.'&&modem>select:3&&modem>card:'.$curRow.'&&modem>select:4&&modem>card:'.$curRow.'&&modem>select:5&&modem>card:'.$curRow.'&&modem>select:6&&modem>card:'.$curRow.'&&modem>select:7&&modem>card:'.$curRow.'&&modem>select:8&&modem>card:'.$curRow.'&&modem>on');
	sleep(10);

	while ($time_limit>time())
	{
		setlog('[sim_link:'.$dev.'] Remaining time:'.($time_limit-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');

		$error='';
		setlog('[sim_link:'.$dev.'] Getting information about operators');
		$step=sr_command($dev,'modem>pack:AT+CREG?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
		$answer=explode('#1#',sr_answer($dev,$step,30));
		for ($i=0;$i<count($answer);$i++)
		{
			if (strpos($answer[$i],'##'))
			{
				$a=explode('##',$answer[$i]);
				preg_match('!:(.*)OK!Uis', $a[1], $test);
				$test=trim($test[1]);
				if ($mod[$a[0]]['status']!=2 && $test[2]!=2){$mod[$a[0]]['status']=$test[2];}
				if (($test[2]==0 || $test[2]==4) && $mod[$a[0]]['status']!=2)
				{
					$error.=$a[0].';';
				}
			}
		}

		if ($error)
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem: '.$error);
			sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
		}

		$restart=1;
		$test=count($mod);
		foreach ($mod AS $key=>$data)
		{
			br($dev,'act_'.$actId.'_stop'); // Checking the early exit flag | Проверка флага досрочного выхода
			br($dev);
			setlog('[sim_link:'.$dev.'] Modem:'.$key.', Status:'.$data['status']);

			$a=explode(';',$func);
			if ($data['status']==1 || $data['status']==5)
			{
				for ($k=0;$k<count($a);$k++)
				{
					$f=$a[$k]; 
					setlog('[sim_link:'.$dev.'] The start of the function: '.$f); // Запуск функции
					sr_command($dev,'modem>select:'.$key);
					$answer=$f($dev,$curRow,$key,$adata);
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
					if ($answer)
					{
						$mod[$key]['status']=2;
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
						$progress++;
					}
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
				}			
			}
			else if ($data['status']==3 && $GLOBALS['set_data']['code_block'])
			{
				setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".$curRow.'-'.$key."'";
				mysqli_query($db,$qry);

				// Saving the number | Сохраняем номер
				$qry="REPLACE INTO `cards` SET
				`number`='".$curRow.'-'.$key."',
				`place`='".$curRow.'-'.$key."',
				`device`=".(int)$dev.",
				`operator`=0,
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);

				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`+`success`+1 WHERE `id`='.(int)$actId); 
				$mod[$key]['status']=2;
			} 

			if ($mod[$key]['status']==2) // Модем обработан
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
	}
	$total=count($mod);
	if ($progres<$total)
	{
		mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+'.($total-$progress).',`errors`=`errors`+'.($total-$progress).' WHERE `id`='.(int)$actId); 
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems, $modemTime, $devData)
//function online_mode($dev, $modems)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start');
	$smsTime=array();
	sr_answer_clear($dev);
	$com=array();

	$new=0;
	for ($i=1;$i<9;$i++)
	{
		if ($modems[$i][1]==-3)
		{
			$modems[$i][1]=-1;
			$new++;
			$m=$i;
		}
	}
	if ($new!=1)
	{
		for ($i=1;$i<9;$i++)
		{
			$modems[$i][1]=-1;
		}
		$com[]='modem>off';
		for ($i=1;$i<9;$i++)
		{
			if ($modems[1][1]<0)
			{
				$com[]='modem>select:'.$i;
				$com[]='modem>card:'.$modems[$i][0]; 
			}
		}
		$com[]='modem>on';
		$com[]='answer>clear';
	}
	else
	{
		$com[]='modem>select:'.$m;
		$com[]='modem>card:'.$modems[$m][0]; 
	}
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."' WHERE `time`=".$modemTime." AND `device`=".$dev);

	sr_command($dev,implode('&&',$com)); 

	$connect=time();
	$getCops=1;
	while (1)
	{
		$step=sr_command($dev,'modem>sms:0');
		$answer=sr_answer($dev,$step,30);
		$smsBuf=$answer=explode('#3#',$answer);
		$error="";
		if ($answer!="1" || $getCops<=0)
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+CREG?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
				$answer=explode('#1#',sr_answer($dev,$step,30));

				$modems[1][1]=-2;
				$modems[2][1]=-2;
				$modems[3][1]=-2;
				$modems[4][1]=-2;
				$modems[5][1]=-2;
				$modems[6][1]=-2;
				$modems[7][1]=-2;
				$modems[8][1]=-2;

				$getCops=5;
				for ($i=0;$i<count($answer);$i++)
				{
					if (strpos($answer[$i],'##'))
					{
						$a=explode('##',$answer[$i]);
						preg_match('!:(.*)OK!Uis', $a[1], $test);
						$test=trim($test[1]);
						if ($test[2]==0 || $test[2]==2  || $test[2]==3  || $test[2]==4)
						{
							$getCops=2;
						}
						if ($test[2]==0 || $test[2]==4  || ($test[2]==2 && $connect+60<time()))
						{
							$error.=$a[0].';';
						}
						elseif ($a[0] && $test[2]!=2 && $test[2]!=3)
						{
							$modems[$a[0]][1]=1;
						}
					}
				}
				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
					$connect=time();
				}
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
			
							if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$modems[$m][0]."-".$m."' AND `device`=".$dev)) 
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
					mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."' WHERE `time`=".$row['time']." AND `device`=".$dev);
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
	$map=sr_command($dev,'modem>map',30);
	if (strpos($map,'error:')===false)
	{
		if ($result = mysqli_query($db, "SELECT `data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$data=unserialize($row['data']);
				if ($map=='NULL'){$map=0;$model='SR-Box-8';} else {$model='SR-Box-Bank';} 
				$data['map']=$map;

				$qry="UPDATE `devices` SET `title`=`model`,`model`='".$model."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				mysqli_query($db,$qry);
				sr_command($dev,'dev:mode=navigator&&save&&sound:beep');		
				return(1);
			}
		}			
	}
	return(0);
}

?>