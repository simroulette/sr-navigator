<?
// ===================================================================
// Sim Roulette -> SR-Organizer functions
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
	$progress=0;
//$place='1-1,2-1';
	$p=explode(',',$place);
	$total=count($p);	
setlog(print_r($place,1),'link_'.$dev);
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
setlog(print_r($place,1),'link_'.$dev);
setlog(print_r($cards,1),'link_'.$dev);

	sr_answer_clear($dev);
	$done=array();
        $GLOBALS['time_correct']=0;

	sr_command($dev,'modem>off',30);

	if ($cards[0] && $cards[1])
	{
		sr_command($dev,'modem[1]>card:'.$cards[0].'&&modem[2]>card:'.$cards[1].'&&modem>on');
	}
	elseif ($cards[0])
	{
		sr_command($dev,'modem[1]>card:'.$cards[0].'&&modem>on');
	}
	elseif ($cards[1])
	{
		sr_command($dev,'modem[2]>card:'.$cards[1].'&&modem>on');
	}
	else
	{
		return;
	}
	sr_command($dev,'modem>select:1',30);
//	sleep(5);
	$errorReport=array();

	for ($counter=0;$counter<$cnt;$counter++)
	{

//	setlog('[sim_link:'.$dev.'] '.print_r($place,1),'link_36');
	        if ($time_limit<time()){setlog('TimeOut!','link_'.$dev);break;}
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
//				setlog('[sim_link:'.$dev.'] Get activation:'.$activation,'link_36');
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
//					$activation[0]=$activation[2];
//					$activation[1]=$activation[3];
setlog('ACTIVATION:'.print_r($activation,1),'link_'.$dev);
				}
//				setlog($counter.' >>>','link_36'); // СИМ-карта не подключается
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
//				setlog('[sim_link:'.$dev.'] Get activation:'.$activation,'link_36');
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
//				setlog($counter.' >>>','link_36'); // СИМ-карта не подключается
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
//					setlog('[sim_link:'.$dev.'] --->>>: '.$modem."-".$place.' === '.$pl.' Data:'.$adata.' ACT:'.$activation[1].' R:'.$roaming,'link_'.$dev);
					$answer=$f($dev,'SR-Organizer',$modem,$pl,$adata,$activation[1],$roaming);
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
			setlog('[sim_link:'.$dev.'] MODEM:'.$modem.'-'.$pl.' The function is executed with the result: '.$answer,'link_'.$dev);
			if ($answer)
			{
				mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`success`=`success`+1 WHERE `id`='.(int)$actId); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
				$progress++;
				unset($errorReport[$modem.'-'.$pl]);
			}
			else
			{
				$errorReport[$modem.'-'.$pl]=$modem.'-'.$pl.':11';
//				$qry='UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1,`report`=CONCAT(`report`," '.$place.':11,") WHERE `id`='.(int)$actId;
//				mysqli_query($db, $qry); 
				setlog('[sim_link:'.$dev.'] Done!'); // Готово
			}
			if ($counter+1==$cnt)
			{
				return;
			}
		}
		elseif ($activation[0]=='3' && $GLOBALS['set_data']['code_block']==2)
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

//			setlog('[sim_link:'.$dev.'] QRY:'.$qry,'link_36');

			mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
			setlog('[sim_link:'.$dev.'] Done!'); // Готово
			$progress++;
			unset($errorReport[$pl]);
		} 
		elseif ($activation[0]==0 || $activation[0]==4)
		{
			$errorReport[$modem.'-'.$pl]=$modem.'-'.$pl.':'.(int)$activation[0];
		}
		elseif ($activation[0]=='')
		{
			$errorReport[$modem.'-'.$pl]=$modem.'-'.$pl.':-1';
		}
	}
setlog($counter.'<'.$cnt.' TOTAL:'.$total.', ER~~~~~~~~~~~~~~~~:'.print_r($errorReport,1),'link_'.$dev);
	if ($progress<$total)
	{
		setlog(print_r($errorReport,1),'link_'.$dev);
		$qry='UPDATE `actions` SET `progress`=`progress`+'.($total-$progress).',`errors`=`errors`+'.($total-$progress).',`report`=CONCAT(`report`,"'.implode(',',$errorReport).',") WHERE `id`='.(int)$actId;
		setlog($qry,'link_'.$dev);
		mysqli_query($db, $qry); 
	}

//	mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1,`errors`=`errors`+1 WHERE `id`='.(int)$actId); 
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}

// Online mode: Connect to the selected modems for receiving SMS in a loop
// Онлайн-режим: Подключение выбранных модемов, прием SMS в цикле
function online_mode($dev, $modems, $modemTime)
{
//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
	global $db,$pdu,$db;

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

	setlog('[1] -> '.print_r($modems,1).'link_'.$dev);

	$mmBuf=serialize($modems);

	$modems[1][1]=-2;
	$modems[2][1]=-2;
	
	flagDelete($dev,'review_timer');

	while (1)
	{
		$b=flagGet($dev,'review');
		$t=flagGet($dev,'review_timer',1);
		if (!$t)
		{
			flagSet($dev,'review_timer');
			flagSet($dev,'review_step',1);
			$t=time();
		}
setlog('Flag:'.$b.' Period:'.$dat['review'].' ElTime:'.(($t+$dat['review'])-time()),'link_'.$dev);
		if ($dat['review_start'] && !$b && flagGet($dev,'command',1)+$dat['review_start']<time())
		{
			flagSet($dev,'review');
			$b=1;
		}			
		if ($b && $t+$dat['review']<time())
		{
			flagSet($dev,'review_timer');
			$step=flagGet($dev,'review_step');
			sr_command($dev,'modem[1]>card:'.$step.'&&modem[2]>card:'.$step);
/*
a:2:{
	i:1;a:2:{i:0;s:1:"1";i:1;i:-1;}
     	i:2;a:2:{i:0;s:1:"1";i:1;i:-1;}
}
*/
			$modems[1][0]=$step;
			$modems[1][1]=-2;
			$modems[2][0]=$step;
			$modems[2][1]=-2;
			$step++;
			if ($step==9){$step=1;}
			flagSet($dev,'review_step',$step);
		}

		$step=sr_command($dev,'modem>sms:4'); // 0 - непрочитанные, 4 - все
		$answer=sr_answer($dev,$step,40);
		$smsBuf=$answer=explode('#3#',$answer);
		$error="";
		if ($answer!="1")
		{
			if ($getCops<=0)
			{
				setlog('[online_mode:'.$dev.'] Monitoring the connection to the cellular network');
				$step=sr_command($dev,'modem>pack:AT+CREG?##all##1'); // Мониторинг подключения к сотовой сети
				$answer=explode('#1#',sr_answer($dev,$step,30));

//				$modems[1][1]=-2;
//				$modems[2][1]=-2;

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
/*
						elseif ($a[0] && $test[2]!=2 && $test[2]!=3)
						{
							$modems[$a[0]][1]=1;
						}
*/
						if ($a[0])
						{
							$modems[$a[0]][1]=(int)$test[2];
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
//					if ($modems[$m][1]!=-2){$modems[$m][1]=9;}
//					if ($modems[$m][1]==1 || $modems[$m][1]==5){$modems[$m][1]=9;}
					setlog('[online_mode:'.$dev.'] Error receiving SMS!','link_'.$dev);
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
								$smsNum=explode(',',$data[$i]);
								$smsNum=trim($smsNum[0]);
								setlog('[online_mode:'.$dev.'] SMSnum: '.$smsNum,'link_'.$dev); // Подготовка SMS
								sr_command($dev,'modem['.$m.']>send:AT+CMGD='.$smsNum); // Удаление полученной SMS
						
								$raw=explode("\n",$data[$i]);
								$sms=$pdu->pduToText($raw[1]);
								setlog('[online_mode:'.$dev.'] SMS: '.print_r($sms,1)); // Подготовка SMS
				
								if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$m."-".$modems[$m][0]."' AND `device`=".$dev)) 
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