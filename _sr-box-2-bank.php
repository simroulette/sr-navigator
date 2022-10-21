<?
// ===================================================================
// Sim Roulette -> SR-Box-Bank functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
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
	setlog('[sim_link:'.$dev.'] Box-2-Bank Start','link_'.$dev);

	sr_answer_clear($dev);

	setlog('[sim_link:'.$dev.'] Rows:'.print_r($curRow,1),'link_'.$dev);
	setlog('[sim_link:'.$dev.'] Modems:'.print_r($modems,1),'link_'.$dev);

	$modems=explode(',',$modems);

	$errorReport=array();

	// Выбираем ряд
	for ($md=1;$md<9;$md++)
	{
		if (in_array($md,$modems))
		{
			$initTime=time()+$data['time_limit'];
	
			if ($md<5){$modem=1;} else {$modem=2;}
			sr_command($dev,'modem>select:'.$md.'&&modem'.$modem.'>card:'.$curRow);
			sleep(10);
	
			while ($initTime>time())
			{
				br($dev,'act_'.$actId.'_stop'); // Checking the early exit flag | Проверка флага досрочного выхода
				br($dev);

				$error='';
				setlog('[sim_link:'.$dev.'] Getting information about operators','link_'.$dev);
				$step=sr_command($dev,'modem'.$modem.'>send:AT+CREG?'); // Getting information about operators | Запрос информации об операторах 
				$answer=sr_answer($dev,0,30,'AT+CREG');
				if (strpos($answer,'CREG')!==false)
				{
					preg_match('!CREG:(.*)OK!Uis', $answer, $test);
					$test=trim($test[1]);
					$test=$test[2];
					if ($test==1 || $test==5 || ($test=='3' && $GLOBALS['set_data']['code_reg']==2)){break;}
				}
			}
			setlog('[sim_link:'.$dev.'] Status:'.print_r($test,1),'link_'.$dev);
			if ($test==1 || $test==5 || ($test=='3' && $GLOBALS['set_data']['code_reg']==2))	
			{
				$a=explode(';',$func);
				for ($k=0;$k<count($a);$k++)
				{
					sr_command($dev,'modem>send:AT+CPMS="SM","SM","SM"');
					sr_command($dev,'modem2>send:AT+CPMS="SM","SM","SM"');
					br($dev,'act_'.$actId.'_stop'); // Checking the early exit flag | Проверка флага досрочного выхода
					br($dev);
					$f=$a[$k]; 
					setlog('[sim_link:'.$dev.'] The start of the function: '.$f,'link_'.$dev); // Запуск функции
					if ($f=='get_sms')
					{
						setlog('[sim_link:'.$dev.'] Waiting...','link_'.$dev); // Запуск функции
						sleep(20);
					}
					$answer=$f($dev,'SR-Box-2-Bank',0,chr($md+64).$curRow,$adata);//,$data['operator']);
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer,'link_'.$dev);
					if ($answer)
					{
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
						$progress++;
					}
					else
					{
						$errorReport=chr($md+64).$curRow.':11';
						mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`,"'.$errorReport.',") WHERE `id`='.(int)$actId); 
					}
					setlog('[sim_link:'.$dev.'] The function is executed with the result: '.$answer);
				}			
			}       	
			else if ($test==3 && $GLOBALS['set_data']['code_block']==2)
			{
				setlog('[sim_link:'.$dev.'] SIM card is blocked!'); // СИМ-карта заблокирована
				// Clearing a place in the database | Очищаем место в БД
				$qry="DELETE FROM `cards` WHERE
				`place`='".chr($md+64).$curRow."'";
				mysqli_query($db,$qry);
				setlog('[sim_link:'.$dev.'] '.$qry,'link_'.$dev);

				if ($result = mysqli_query($db, "SELECT `id` FROM `cards` WHERE `place`='".chr($md+64).$curRow."' AND `device`=".$dev)) 
				{
					// Saving the information | Сохраняем информацию о блокировке
					if ($resRow = mysqli_fetch_assoc($result))
					{
						$qry="UPDATE `cards` SET
						`number`='".chr($md+64).$curRow."',
						`place`='".chr($md+64).$curRow."',
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
						`number`='".chr($md+64).$curRow."',
						`place`='".chr($md+64).$curRow."',
						`device`=".(int)$dev.",
						`operator`=0,
						`time_number`='".time()."',
						`time`='".time()."'";
						mysqli_query($db,$qry);
					}
				}
				
				setlog('[sim_link:'.$dev.'] '.$qry,'link_'.$dev);
		
				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
				$progress++;
			} 
			elseif ($test==0 || $test==4 || ($test==3 && $GLOBALS['set_data']['code_block']<2))
			{
				$errorReport=chr($md+64).$curRow.':'.$test;
				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`,"'.$errorReport.',") WHERE `id`='.(int)$actId); 
				$progress++;
			}
			setlog('MD:'.$md,'link_'.$dev); // Лимит времени исчерпан
		}
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!','link_'.$dev); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems, $modemTime, $devData)
//function online_mode($dev, $modems)
{
//	$dev		Device ID
//	$modems	        List of modems to process
	global $db,$pdu;

	setlog('[online_mode:'.$dev.'] Start SR-Box-2-Bank');
//	setlog('[online_mode:'.$dev.'] Time: '.$modemTime,'link_'.$dev);
	$smsTime=array();
	sr_answer_clear($dev);
	$com=array();

//setlog(print_r($modems,1),'link_'.$dev);

	$new=0;
	$modem1=0;
	$modem2=0;
	$on1=0;
	$on2=0;
	$card1=0;
	$card2=0;
	for ($i=1;$i<9;$i++)
	{
//		if ($modems[$i][1]==-3)
		if ($modems[$i][1]!=-4)
		{
//			if ($modems[$i][1]==-3 && $i<5){$modem1=$i;$card1=$modems[$i][0];}
//			if ($modems[$i][1]==-3 && $i>4){$modem2=$i;$card2=$modems[$i][0];}
			if ($i<5){$modem1=$i;$card1=$modems[$i][0];if ($modems[$i][1]==-3){$on1=1;}}
			if ($i>4){$modem2=$i;$card2=$modems[$i][0];if ($modems[$i][1]==-3){$on2=1;}}
			if ($modems[$i][1]==-3){$modems[$i][1]=-1;}
			$new++;
			$m=$i;
		}
	}
setlog(print_r($modems,1),'link_'.$dev);
//	if ($new!=1)
	{
/*
		for ($i=1;$i<9;$i++)
		{
			if ($modems[$i][1]>-4){$modems[$i][1]=-1;}
		}
/*
		for ($i=1;$i<9;$i++)
		{
			if ($modems[1][1]<0)
			{
				$com[]='modem>select:'.$i;
				$com[]='modem>card:'.$modems[$i][0]; 
			}
		}
*/
		if ($on1)
		{
			$com[]='modem>select:'.$modem1;
			$com[]='modem1>card:'.$card1; 
		}
		if ($on2)
		{
			$com[]='modem>select:'.$modem2;
			$com[]='modem2>card:'.$card2; 
		}
	}
/*
	else
	{
		$com[]='modem>select:'.$m;
		$com[]='modem>card:'.$modems[$m][0]; 
	}
*/
	mysqli_query($db, "UPDATE `modems` SET `modems`='".serialize($modems)."' WHERE `time`=".$modemTime." AND `device`=".$dev);

	sr_command($dev,implode('&&',$com)); 
	sleep(30);
	setlog('[1] -> '.print_r($modems,1).'link_'.$dev);
	$connect=time();
	$getCops=1;
	$mmBuf=serialize($modems);

	while (1)
	{
		sleep(5);
		$step=sr_command($dev,'modem>sms:0');
		$answer=sr_answer($dev,$step,30);
		$smsBuf=$answer=explode('#3#',$answer);
		$error=array();
		if ($answer!="1" || $getCops<=0)
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+CREG?'); // Getting information about operators | Запрос информации об операторах 
				$answer=explode('#1#',sr_answer($dev,$step,50));
//setlog(print_r($answer,1),'link_'.$dev);
				for ($i=1;$i<9;$i++)
				{
					if ($modems[$i][1]>-4){$modems[$i][1]=-2;}
				}

				$getCops=5;
				for ($i=0;$i<count($answer);$i++)
				{
					if (strpos($answer[$i],'##'))
					{
						$a=explode('##',$answer[$i]);
						preg_match('!CREG:(.*)OK!Uis', $a[1], $test);
						$test=trim($test[1]);
						if ($test[2]==0 || $test[2]==2  || $test[2]==3  || $test[2]==4)
						{
							$getCops=2;
						}
						if ($test[2]==0 || $test[2]==4  || ($test[2]==2 && $connect+60<time()))
						{
							$error[$a[0]]=1;
						}
//						elseif ($a[0] && $test[2]!=2 && $test[2]!=3)
						if ($a[0])
						{
							if ($a[0]==1)
							{
								for ($mi=1;$mi<5;$mi++)
								{
									if ($modems[$mi][1]>-4)
									{
										$modems[$mi][1]=(int)$test[2];
										break;
									}
								}
							}
							if ($a[0]==2)
							{
								for ($mi=5;$mi<9;$mi++)
								{
									if ($modems[$mi][1]>-4)
									{
										$modems[$mi][1]=(int)$test[2];
										break;
									}
								}
							}
						}
					}
				}
				if ($error[1] || $error[2])
				{
					setlog('[online_mode:'.$dev.'] Restarting the modem: '.$error,'link_'.$dev);
//					sr_command($dev,'modem'.$error.'>send:AT+CFUN=1,1'); // Перезапуск модемов 
					if ($error[1])
					{
						sr_command($dev,'modem>select:'.$modem1.'&&modem1>card:'.$card1); 
					}
					else
					{
						sr_command($dev,'modem>select:'.$modem2.'&&modem2>card:'.$card2); 
					}
					$error=array();
					$connect=time();
					$init=0;
				}
				else if (!$init)
				{
					$init=1;
					sr_command($dev,'modem1>send:AT+CLIP=1');		
					sr_command($dev,'modem2>send:AT+CLIP=1');		
					sr_command($dev,'modem1>send:AT+CPMS="SM","SM","SM"');
					sr_command($dev,'modem2>send:AT+CPMS="SM","SM","SM"');

//					$answer=sr_answer($dev,0,20,'AT+CLIP');
				}
			}
			$getCops--;
			foreach ($smsBuf AS $data)
			{
				$data=explode('##',$data);
				$mn=$data[0][0];
//setlog("M:".$mn." C1:".print_r($modem1,1)." C2:".print_r($modem2,1),'link_'.$dev);
				if ($mn==1){$mn=$modem1;}
				elseif ($mn==2){$mn=$modem2;}
				if ($data[1]=='E') // Ошибка
				{
//					$modems[$a[0]][1]=9;
					setlog('[online_mode:'.$dev.'] Error receiving SMS!');
				}
				else
				{
					for ($i=1;$i<count($data);$i++)
					{
//						if (in_array($m,$mod))
//						{
							$sms='';
							setlog('[online_mode:'.$dev.'] RAW SMS received: '.$data[$i],'link_'.$dev); // Получена SMS
							if ($data[$i])
							{
//setlog("111",'link_'.$dev);
								$smsNum=explode(',',$data[$i]);
								$smsNum=$smsNum[0];
								setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum,'link_'.$dev); // Подготовка SMS
						
								$raw=explode("\n",$data[$i]);
								$sms=$pdu->pduToText($raw[1]);
								setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1),'link_'.$dev); // Подготовка SMS

   								if (trim($sms['userDataHeader']))
								{
									$smsNum=0;
								}

								$qry="SELECT * FROM `cards` WHERE `place`='".chr($mn+64).$modems[$mn][0]."' AND `device`=".$dev;
								if ($result = mysqli_query($db, $qry)) 
								{
									if ($row = mysqli_fetch_assoc($result))
									{
										// Saving to the database | Сохранение в БД
										sms_save($sms['userDataHeader'],$row['number'],$row['email'],'',$sms['number'],$sms['unixTimeStamp'],$sms['message'],$row['id']);
/*
*/
									}
									else
									{
										// Добавляем новую карту
										$qry2="INSERT INTO `cards` SET
										`place`='".chr($mn+64).$modems[$mn][0]."',
										`device`=".$dev.",
										`operator`=0,
										`time_number`='".time()."',
										`time`='".time()."'";
										setlog('[online_mode:'.$dev.'] '.$qry,'link_'.$dev);
										mysqli_query($db,$qry2);
										$cardId=mysqli_insert_id($db);

									setlog('[online_mode:'.$dev.'] '.$qry2,'link_'.$dev);

										// Saving to the database | Сохранение в БД
										sms_save($sms['userDataHeader'],$row['number'],'','',$sms['number'],$sms['unixTimeStamp'],$sms['message'],$cardId);
									}
									setlog('[online_mode:'.$dev.'] '.$qry,'link_'.$dev);
									setlog('[online_mode:'.$dev.'] SMS saved','link_'.$dev); // SMS сохранена
						    			if ($GLOBALS['set_data']['email'])
									{
										setlog('[online_mode:'.$dev.'] SMS sent to E-mail'); // SMS отправлена на E-mail
									}
								}
							}
							setlog('[online_mode:'.$dev.'] SMS counter: '.$smsNum.', Time: '.(time()-$smsTime[$mn]).', Status: '.$place[1]);
							if ($smsNum>7)
							{
								setlog('[online_mode:'.$dev.'] Deleting all SMS messages from the SIM card');
								sr_command($dev,'modem>select:'.$mn.'&&modem>send:AT+CMGD=0,4');//AT+CMGDA=5'); // Удаление всех SMS с SIM-карты
								$smsNum=0;
							}
//						}
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
	sr_command($dev,'display=16::C::64::25::Инициализация агрегатора');
	$map=sr_command($dev,'modem>map',30);
	if (strpos($map,'error:')===false)
	{
		if ($result = mysqli_query($db, "SELECT `title`,`data` FROM `devices` WHERE `id`=".$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$data=unserialize($row['data']);
				if ($map=='NULL'){$map=0;$model='SR-Box-2';} else {$model='SR-Box-2-Bank';} 
				$data['map']=$map;
				$data['time_limit']=60;
				$modems='1,2,3,4,5,6,7,8';

				if ($row['title']=='[init]')
				{
					$qry="UPDATE `devices` SET `title`='".$model."',`model`='".$model."',`modems`='".$modems."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				else
				{
					$qry="UPDATE `devices` SET `model`='".$model."',`modems`='".$modems."',`init`=".time().",`data`='".serialize($data)."' WHERE `id`=".$dev;
				}
				mysqli_query($db,$qry);

				sr_command($dev,'save',30);		
				sr_command($dev,'display=16::C::64::25::Готов к работе!&&sound:beep');		
				return(1);
			}
		}			
	}
	return(0);
}

?>