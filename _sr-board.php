<?
// ===================================================================
// Sim Roulette -> SR-Board functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
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
	setlog('[sim_link:'.$dev.'] Board Start','link_'.$dev);

	$c=$curRow;
	if (!is_array($c))
	{
		$curRow=array();
		$curRow[0]=$c;
		$curRow[1]=$c;
		$curRow[2]=$c;
		$curRow[3]=$c;
		$curRow[4]=$c;
		$curRow[5]=$c;
		$curRow[6]=$c;
		$curRow[7]=$c;
	}
	$time_limit=time()+$data['time_limit'];
	sr_answer_clear($dev);

	$progress=0;
	$mod=explode(',',trim($modems,','));
	sort($mod);
	$mod=array_flip($mod);

	foreach ($mod AS $key=>$data)
	{
		$mod[$key]=array();
		$mod[$key]['status']=0;
	}

	for ($i=1;$i<9;$i++)
	{
		if (!isset($mod[$i]))
		{
			$mod[$i]['status']=2;
		}
	}

	$errorReport=array();

	// Выбираем ряд
	sr_command($dev,'modem>fix:0&&modem>on&&modem>select:1&&modem>card:'.$curRow[0].'&&modem>select:2&&modem>card:'.$curRow[1].'&&modem>select:3&&modem>card:'.$curRow[2].'&&modem>select:4&&modem>card:'.$curRow[3].'&&modem>select:5&&modem>card:'.$curRow[4].'&&modem>select:6&&modem>card:'.$curRow[5].'&&modem>select:7&&modem>card:'.$curRow[6].'&&modem>select:8&&modem>card:'.$curRow[7].'&&answer>clear');
	sleep(30);

	while ($time_limit>time())
	{
		setlog('[sim_link:'.$dev.'] Remaining time:'.($time_limit-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');

		$error='';
		setlog('[sim_link:'.$dev.'] Getting information about operators');
		$step=sr_command($dev,'modem>pack:AT+CREG?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
		$answer=explode('#1#',sr_answer($dev,$step,50));
		for ($i=0;$i<count($answer);$i++)
		{
			if (strpos($answer[$i],'##'))
			{
				$a=explode('##',$answer[$i]);
				if (!$a[1]) // Если модем не отвечает
				{
					$moo=1;
					if ($a[0]>4){$moo=2;}
					sr_command($dev,'modem>off['.$moo.']&&modem>on'); // Перезапуск модемов 	
				}

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
					$answer=$f($dev,'SR-Box-Bank',0,chr($key+64).$curRow[$key-1],$adata);
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'link_'.$dev);
					if ($answer)
					{
						$mod[$key]['status']=2;
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
						$progress++;
						unset($errorReport[$key-1]);
					}
					else
					{
						$errorReport[$key-1]=chr($key+64).$curRow[$key-1].':11';
					}
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
				}			
			}
			else if ($data['status']==3 && $GLOBALS['set_data']['code_block']==2)
			{
				setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".chr($key+64).$curRow[$key-1]."'";
				mysqli_query($db,$qry);

				if ($result = mysqli_query($db, "SELECT `id` FROM `cards` WHERE `place`='".chr($key+64).$curRow[$key-1]."' AND `device`=".$dev)) 
				{
					// Saving the information | Сохраняем информацию о блокировке
					if ($resRow = mysqli_fetch_assoc($result))
					{
						$qry="UPDATE `cards` SET
						`number`='".chr($key+64).$curRow[$key-1]."',
						`place`='".chr($key+64).$curRow[$key-1]."',
						`device`=".(int)$dev.",
						`operator`=0,
						`time_number`='".time()."',
						`time`='".time()."'
						WHERE `id`=".$resRow['id'];
						mysqli_query($db,$qry);
					}
					else
					{
						$qry="INSERT INTO `cards` SET
						`number`='".chr($key+64).$curRow[$key-1]."',
						`place`='".chr($key+64).$curRow[$key-1]."',
						`device`=".(int)$dev.",
						`operator`=0,
						`time_number`='".time()."',
						`time`='".time()."'";
						mysqli_query($db,$qry);
					}
				}

				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
				$progress++;
				$mod[$key]['status']=2;
			} 
			elseif ($data['status']==0 || $data['status']==4)
			{
				$errorReport[$key-1]=chr($key+64).$curRow[$key-1].':'.(int)$data['status'];
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
	setlog('Progress:'.$progress.' Total:'.$total.' Errors:'.implode(',',$errorReport),'link_'.$dev); 
	if ($progress<$total)
	{
		$qry='UPDATE `actions` SET `progress`=`progress`+'.($total-$progress).',`errors`=`errors`+'.($total-$progress).',`report`=CONCAT(`report`,"'.implode(',',$errorReport).',") WHERE `id`='.(int)$actId;
		mysqli_query($db, $qry); 
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems, $modemTime, $devData)
{
//	$dev		Device ID
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
	if ($new>7)
	{
		for ($i=1;$i<9;$i++)
		{
			$modems[$i][1]=-1;
		}
		$com[]='modem>fix:0&&modem>off';
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
	$mmBuf=serialize($modems);
	$try=0;

	while (1)
	{
		$step=sr_command($dev,'modem>sms:0');
		$answer=sr_answer($dev,$step,60);
		$smsBuf=$answer=explode('#3#',$answer);
		$error="";
		if ($answer!="1" || $getCops<=0)
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+CREG?##ALL##1'); // Getting information about operators | Запрос информации об операторах 
				$answer=explode('#1#',sr_answer($dev,$step,60));

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
						if (!$a[1]) // Если модем не отвечает
						{
							$moo=1;
							if ($a[0]>4){$moo=2;}
							if (!$rm[$moo-1])
							{
								$rm[$moo-1]=1;
								$try++;
							}
						}
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
						if ($a[0])
						{
							$modems[$a[0]][1]=(int)$test[2];
						}
					}
				}
				if ($rm[0]==1 && $rm[1]==1 && $try>5)
				{
					sr_command($dev,'modem>off'); // Перезапуск модемов 	
					sr_command($dev,'modem>on'); // Перезапуск модемов 	
					setlog('[online_mode:'.$dev.'] Restarting the modems: 1-2','link_'.$dev);
					$try=0;
				}
				elseif ($rm[0]==1 && $try>5)
				{
					sr_command($dev,'modem>off[1]'); // Перезапуск модемов 	
					sr_command($dev,'modem>on'); // Перезапуск модемов 	
					setlog('[online_mode:'.$dev.'] Restarting the modems: 1','link_'.$dev);
					$try=0;
				}
				elseif ($rm[1]==1 && $try>5)
				{
					sr_command($dev,'modem>off[2]'); // Перезапуск модемов 	
					sr_command($dev,'modem>on'); // Перезапуск модемов 	
					setlog('[online_mode:'.$dev.'] Restarting the modems: 2','link_'.$dev);
					$try=0;
				}

				if ($error)
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error);
					sr_command($dev,'modem>on&&modem>pack:AT+CFUN=1,1##'.$error.'##1'); // Перезапуск модемов 
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
							setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i],'link_'.$dev); // Получена SMS
							if ($data[$i])
							{
								$smsNum=explode(',',$data[$i]);
								$smsNum=$smsNum[0];
								setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum,'link_'.$dev); // Подготовка SMS
						
								$raw=explode("\n",$data[$i]);
								$sms=$pdu->pduToText($raw[1]);

   								if (trim($sms['userDataHeader']))
								{
									$smsNum=0;
								}

								if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".chr($m+64).$modems[$m][0]."' AND `device`=".$dev)) 
								{
									if ($row = mysqli_fetch_assoc($result))
									{
										// Saving to the database | Сохранение в БД
										sms_save($sms['userDataHeader'],$row['number'],$row['email'],'',$sms['number'],$sms['unixTimeStamp'],$sms['message'],$row['id']);
									}
									else
									{
										// Добавляем новую карту
										$qry2="INSERT INTO `cards` SET
										`place`='".chr($m+64).$modems[$m][0]."',
										`device`=".$dev.",
										`operator`=0,
										`time_number`='".time()."',
										`time`='".time()."'";
										mysqli_query($db,$qry2);
										$cardId=mysqli_insert_id($db);

										// Saving to the database | Сохранение в БД
										sms_save($sms['userDataHeader'],$row['number'],'','',$sms['number'],$sms['unixTimeStamp'],$sms['message'],$cardId);
									}
									setlog('[online_mode:'.$dev.'] SMS saved','link_'.$dev); // SMS сохранена
						    			if ($GLOBALS['set_data']['email'])
									{
										setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
									}
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
		if ($mmBuf!=serialize($modems))
		{
			$mmBuf=serialize($modems);
			mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."' WHERE `time`=".$modemTime." AND `device`=".$dev);
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

function dev_init($dev, $actId)
{
//	$dev		Device ID
	global $db;
	sr_command($dev,'modem>off&&modem>fix:0&&display=16::C::64::20::Инициализация агрегатора');
	sr_command($dev,'modem>on',30);
	if (strpos($map,'error:')===false)
	{
		if ($result = mysqli_query($db, "SELECT `title`,`data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$data=unserialize($row['data']);
				$data['map']='11111111';
				$modems='1,2,3,4,5,6,7,8';

				sr_command($dev,'display=24::C::64::25::10%&&dev:mode=navigator');		
				mysqli_query($db, 'UPDATE `actions` SET `count`=100,`progress`=10 WHERE `id`='.(int)$actId);
				sr_command($dev,'save',30);		
				$p=10;
 				for ($i=1;$i<9;$i++)
				{
	                                $p=10+$i*10;
					sr_command($dev,'modem>select:'.$i.'&&display=24::C::64::25::'.$p.'%');		
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+10 WHERE `id`='.(int)$actId);
					sleep(12);
					sr_command($dev,'AT+CLIP=1');		
					sleep(12);
					sr_command($dev,'AT+CMGF=0');		
					sleep(12);
					sr_command($dev,'AT&W0');		
				}
				mysqli_query($db, 'UPDATE `actions` SET `progress`=99 WHERE `id`='.(int)$actId);
				sr_command($dev,'display=16::C::64::25::Готов к работе!',30);		

				mysqli_query($db, 'UPDATE `actions` SET `count`=1,`progress`=1,`success`=1 WHERE `id`='.(int)$actId);
				if ($row['title']=='[init]')
				{
					$qry="UPDATE `devices` SET `title`='SR-Board',`modems`='".$modems."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				else
				{
					$qry="UPDATE `devices` SET `modems`='".$modems."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				mysqli_query($db,$qry);

				return(1);
			}
		}			
	}
	return(0);
}

?>
