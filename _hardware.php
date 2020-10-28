<?
// ===================================================================
// Sim Roulette -> Hardware functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

// Deleting device SIM cards from the database table
// Удаление СИМ-карт устройства из таблицы БД
function dev_truncate($dev)
{
//	$dev		Device ID

	global $db;
	$qry="DELETE FROM `cards` WHERE `device`=".(int)$dev;
	mysqli_query($qry,$db);
	return(1);
}

// Clearing device responses
// Очистка ответов устройств
function sr_answer_clear($dev=0,$sr=0)
{
//	$dev		Device ID
//	$sr		Deleting responses other than "SR start"

	global $db;
	if (!$dev)
	{
		mysqli_query($db,'TRUNCATE TABLE `link_incoming`');
	}
	elseif (!$sr)
	{
		mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `device`='.(int)$dev); 
	}
	else
	{
		mysqli_query($db, "DELETE FROM `link_incoming` WHERE `device`=".(int)$dev." AND `answer`!='SR start'"); 
	}
}

// Clearing the command buffer
// Очистка буфера команд
function sr_command_clear($dev=0)
{
//	$dev		Device ID

	global $db;
	if (!$dev)
	{
		mysqli_query($db,'TRUNCATE TABLE `link_outgoing`');
	}
	else
	{
		mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".(int)$dev); 
	}
}

// Sending a command to the device and receiving a response (optional)
// Отправка команды устройству и получение ответа (опционально)
function sr_command($dev,$command,$wait=0,$transaction_code='',$transaction_time=0)
{
//	$dev			Device ID
//	$command		Command
//	$wait			How many seconds to wait for a response / 0-don't wait
//	$transaction_code	A code (random number) for subsequent access within the transaction
//	$transaction_time	The automatic completion of the transaction

	global $db;
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано

	// Transaction processing for priority access to the device | Обработка транзакций для приоритетного доступа к устройcтву

	if (file_exists($GLOBALS['root'].'flags/command_'.$dev))
	{
		$data=explode(';',file_get_contents($GLOBALS['root'].'flags/command_'.$dev));
	}
	if ($transaction_code && $transaction_code==$data[1] && !$transaction_time && !$command)
	{
		unlink($GLOBALS['root'].'flags/command_'.$dev);
		return;
	}
	// Checking whether the transaction is open | Проверяем открыта ли транзакция
	if ((!$transaction_code || $transaction_code!=$data[1]) && $data[0]>time())
	{
		while ($data[0]>time())
		{
			setlog('[sr_command:'.$dev.'] Waiting for the transaction to end'); // Ожидание окончания транзакции
			if (!file_exists($GLOBALS['root'].'flags/command_'.$dev)){break;}
			sleep(1);
		}
	}
	if ($transaction_code && $transaction_time)
	{
		file_put_contents('flags/command_'.$dev,(time()+$transaction_time).';'.$transaction_code);
	}
	else
	{
		file_put_contents('flags/command_'.$dev,time()+30);
	}

	if ($result = mysqli_query($db, "SELECT `step`,`type`,`ip`,`token_local` FROM `devices` WHERE `id`=".(int)$dev)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$step=$s=$row['step'];
			if ($row['type']=='server') // Accessing the server device | Обращение к устройству-серверу
			{
				$link='http://'.$row['ip'].'/port?data='.$row['token_local'].'||'.$s.'||'.$command;
			       	$ch = curl_init();
			       	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		        	curl_setopt($ch, CURLOPT_HEADER, 0);
			       	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $link);
		        	$answer = curl_exec($ch);
			       	curl_close($ch);
				$answer=explode('#!#',$answer);
				if ($answer[1]) // Saving the response received from the device | Сохранение полученного от устройства ответа
				{
					if (!$answer[0]) // If there is an out-of-order response from the device, we generate a random number to save as a unique response in the table | Если внеочередной ответ устройства - генерируем случайное число, чтобы сохранить в таблице как уникальный ответ
					{
						$uniq=",`uniq`='".rand(1111,9999).rand(1111,9999)."'";
					} 
					else 
					{
						$uniq="";
					} 
					$qry="INSERT `link_incoming` SET
					`device`='".(int)$dev."',
					`step`=".$answer[0].",
					`answer`='".$answer[1]."'".$uniq;
					mysqli_query($db,$qry);
				}
				$qry="UPDATE `devices` SET
				`step`=".($s+1)."
				WHERE `id`=".(int)$dev;
				mysqli_query($db,$qry);

				setlog('device:'.$dev.' IN > '.$answer[0].' | '.stripslashes($answer[1]).' OUT > '.$step.' | '.$command,'link');

				if (!$wait) // Exit without waiting for a response | Выход без ожидания ответа
				{
					$out=$step;
				} 
				else
				{
					$out=sr_answer($dev,$step,$wait);
				}
				return($out);
			}
		}
		else {return('error:device not found');} // Ошибка - Устройство отсутсвует
	}

	// Accessing the client device | Обращение к устройству-клиенту

	// Save the command to the "link_outgoing" table | Сохраняем команду в таблицу "link_outgoing"
	if ($c=trim($command))
	{
		$qry="INSERT `link_outgoing` SET
		`device`='".(int)$dev."',
		`command`='".$c."',
		`step`=".(int)$s++;
		mysqli_query($db,$qry);
	}
	else
	{
		return('error:no command specified');
	}

	// Saving the step for the selected device in the "devices" table | Сохраняем шаг (step) для выбранного устройства в таблицу "devices"
	$qry="UPDATE `devices` SET
	`step`=".$s."
	WHERE `id`=".(int)$dev;
	mysqli_query($db,$qry);

	if (!$wait) // Exit without waiting for a response | Выход без ожидания ответа
	{
		$out=$step;
	} 
	else
	{
		$out=sr_answer($dev,$step,$wait);
	}
	if (!$transaction_time)
	{
		unlink($GLOBALS['root'].'flags/command_'.$dev);
	}
	return($out);
}

// Getting the device response
// Получение ответа устройства
function sr_answer($dev,$step=0,$wait=20,$search="") 
{
//	$dev		Device ID
//	$step		Device Step OR 0
//	$wait		How many seconds to wait for a response
//	$search		Поиск в ответе устройства

	global $db;
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано
	$time=time()+$wait;
	
	// Accessing the server device | Обращение к устройству-серверу
	if ($result = mysqli_query($db, "SELECT `ip`,`token_local` FROM `devices` WHERE `type`='server' AND `ip`<>'' AND `id`=".(int)$dev)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			// Reading the response from the table | Читаем ответ из таблицы
			if ($subResult = mysqli_query($db, 'SELECT * FROM `link_incoming` WHERE `device`='.(int)$dev.' AND `step`='.(int)$step.' ORDER BY id')) 
			{
				while ($subRow = mysqli_fetch_assoc($subResult))
				{
					if ($step)
					{
						return($subRow['answer']);
					}			
					elseif (trim($subRow['answer'])=='SR start')
					{
						$GLOBALS['set_data']['flags']['restart']=1;
					}
					elseif (trim($subRow['answer'])=='NOT READY')
					{
						$GLOBALS['set_data']['flags']['not_ready']=1;
					}
					mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.$subRow['id']); 
		
					if ($search && mb_strpos($subRow['answer'],$search)!==false)
					{
						return($subRow['answer']);
					}
					elseif (!$search)
					{
						return($subRow['answer']);
					}
				}
			}

			// Getting from the device | Получаем с устройства
			$n=0;
			while ($time>time() || !$n)
			{
	                        $n=1;
				$link='http://'.$row['ip'].'/port?data='.$row['token_local'].'||0||REQUEST';
			       	$ch = curl_init();
			       	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		        	curl_setopt($ch, CURLOPT_HEADER, 0);
			       	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $link);
		        	$answer = curl_exec($ch);
			       	curl_close($ch);
				$answer=explode('#!#',$answer);

				setlog('device:'.$dev.' IN > '.$answer[0].' | '.stripslashes($answer[1]).' OUT > '.$step.' | REQUEST','link');

				if ($step && $step==$answer[0])
				{
					return($answer[1]);
				}			
				elseif (trim($answer[1])=='SR start')
				{
					$GLOBALS['set_data']['flags']['restart']=1;
				}
				elseif (trim($answer[1])=='NOT READY')
				{
					$GLOBALS['set_data']['flags']['not_ready']=1;
				}
				if ($search && mb_strpos($answer[1],$search)!==false)
				{
					return($answer[1]);
				}
				elseif (!$search && $answer[0] && $answer[1])
				{
					return($answer[1]);
				}
				if ($time>time()){sleep(1);}
			}
			return('error:no answer');
		}
	}

	// Accessing the client device | Обращение к устройству-клиенту

	while ($time>time())
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `link_incoming` WHERE `device`='.(int)$dev.' AND `step`='.(int)$step.' ORDER BY id LIMIT 1')) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				if ($step)
				{
					return($row['answer']);
				}			
				elseif (trim($row['answer'])=='SR start')
				{
					$GLOBALS['set_data']['flags']['restart']=1;
				}
				elseif (trim($row['answer'])=='NOT READY')
				{
					$GLOBALS['set_data']['flags']['not_ready']=1;
				}
				mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.$row['id']); 

				if ($search && mb_strpos($row['answer'],$search)!==false)
				{
					return($row['answer']);
				}
				elseif (!$search)
				{
					return($row['answer']);
				}
			}
		}
	}
	return('error:no answer');
}

// Stopping an action
// Остановка задачи
function action_stop($id)
{
//	$id		Action ID

	global $db;
	if ($result = mysqli_query($db, 'SELECT * FROM `actions` WHERE `id`='.(int)$id)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db, "DELETE FROM `actions` WHERE `id`=".(int)$id);
			mysqli_query($db, "DELETE FROM `card2action` WHERE `action`=".(int)$id);
			if ($row['pool_id'])
			{
				$qry="UPDATE `pools` SET `status`='free' WHERE `id`=".$row['pool_id'];
				mysqli_query($db,$qry);
			}
			elseif ($row['card_number'])
			{
				$qry="UPDATE `cards` SET `status`='free' WHERE `number`=".$row['card_number'];
				mysqli_query($db,$qry);
			}
			file_put_contents('flags/act_'.(int)$id.'_stop_'.$row['device'],'1');
			unlink('flags/cron_'.$row['device']);
		}		
	}
}

// Creating an action for a SIM card
// Создание задачи для СИМ-карты
function action_card_create($number,$type)
{
//	$number		Phone Number
//	$type		Action Type

	global $db;
	if ($result = mysqli_query($db, 'SELECT c.*,d.`modems`,d.`model` FROM `cards` c INNER JOIN `devices` d ON d.id=c.`device` WHERE c.`number`='.(int)$number)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db,"UPDATE `cards` SET `status`='waiting' WHERE `number`=".(int)$number);
			mysqli_query($db,"INSERT INTO `actions` SET `card_number`=".(int)$number.",`device`=".$row['device'].",`action`='".$type."',`count`=1,`time`=".time());
			$act_id=mysqli_insert_id($db);
			if ($row['model']=='SR-Train')
			{
				// Getting a row and a place | Получение ряда и места
				$place=explode('-',$row['place']);
				// Checking whether this modem is enabled | Проверка задействован ли модем
				$modems=explode(',',$row['modems']);
				$status=0;
				for ($i=0;$i<count($modems);$i++)
				{
					if ($place[1]==$modems[$i])
					{
						$status=1;
						break;
					}
					if ($place[1]+8==$modems[$i] && $place[0]-3>=0)
					{
						$place[1]=$place[1]+8;
						$place[0]=$place[0]-3;
						$status=1;
						break;
					}						
				}
				if (!$status){return(array('status'=>0));}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".$row['device'].",`action`=".$act_id.",`row`=".$place[0].",`place`='".$place[1]."'");
			}
			elseif ($row['model']=='SR-Nano-500')
			{
				$l=$row['place'][0];
				$p=substr($row['place'],1,3);
				if (strlen($p)<2){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".$row['device'].",`action`=".$act_id.",`place`='".$l.$p."'");
			}		
			elseif ($row['model']=='SR-Nano-1000')
			{
				$l=$row['place'][0];
				$p=substr($row['place'],1,3);
				if (strlen($p)<2){$p='00'.$p;}
				elseif (strlen($p)<3){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".$row['device'].",`action`=".$act_id.",`place`='".$l.$p."'");
			}		
			return(array('status'=>1,'action'=>$act_id));
		}
	}
}

// Creating an action for scanning SIM cards
// Создание задачи сканирования СИМ-карт
function action_card_scanner($id,$span)
{
//	$id		Device ID
//	$span		Span of SIM cards, examples ("0,1", "0-3", "A0,A1", "A10-B20" etc)

	global $db;
	if (!$id){return(array('status'=>0,'message'=>'Ошибка — Вы не выбрали устройство!'));} // Error - you didn't select a device!
	$span=trim(strtoupper($span),'-');
	$span=trim($span,',');
	$span=trim($span);
	$select=explode('-',$span);
	if ($select[1])
	{
		$from=$select[0];
		$to=$select[1];
		$select='';
	} 
	else 
	{
		$select=explode(',',$span);
		if (!strlen($select[0]))
		{
			$select='';
			$from=-1;
		}
	}
	if ($result = mysqli_query($db, 'SELECT * FROM `devices` WHERE `id`='.(int)$id)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$data=unserialize($row['data']);
			if (strpos($row['model'],'SR-Nano')!==false)
			{
				if ($from==-1)
				{
					$from='A0';
					$to='X0';
				}
				$count=0;
				// SR-Nano track capacity | Емкость дорожек SR-Nano
				if ($row['model']=='SR-Nano-500'){$cards=array(100,90,80,68,58,46,34,24);}
				elseif ($row['model']=='SR-Nano-1000'){$cards=array(140,130,120,110,100,90,80,68,58,46,34,24);}
				if (is_array($select))
				{
					foreach ($select AS $i)
					{
						if (substr($i,1,255)<$cards[ord($i[0])-65]) // Go to the next track | Переход на следующую дорожку
						{
							if (!$count)
							{
								mysqli_query($db,"INSERT INTO `actions` SET `device`=".(int)$id.",`action`='get_number',`time`=".time());
								$act_id=mysqli_insert_id($db);
							}		
							$count++;
							if ($row['model']=='SR-Nano-500')
							{
								$l=$i[0];
								$p=substr($i,1,3);
								if (strlen($p)<2){$p='0'.$p;}
								$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$p."'";
							}		
							else
							{
								$l=$i[0];
								$p=substr($i,1,3);
								if (strlen($p)<2){$p='00'.$p;}
								elseif (strlen($p)<3){$p='0'.$p;}
								$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$p."'";
							}		
							mysqli_query($db,$qry);
						}
					}
					if ($count)
					{
						$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
						mysqli_query($db,$qry);

						$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".$act_id;
						mysqli_query($db,$qry);
			
						return(array('status'=>0,'message'=>'ok','action'=>$act_id));
					}
					else
					{
						return(array('status'=>0,'message'=>'Ошибка — Места СИМ-карт за пределами диапазона дорожек: A0-100, B0-90, C0-80, D0-68, E0-58, F0-46, G0-34, H0-24')); // Error - Place SIM cards outside of the track range
					}
				}
				else
				{
					$let_end=count($cards)+64;
					$from=ord($from[0])*1000+substr($from,1,255);
					$to=ord($to[0])*1000+substr($to,1,255);
					$let_max=substr($to,0,2);
					$num_max=(int)substr($to,2,255);

					for ($i=$from;$i<=$to;$i++)
					{
						$let=substr($i,0,2);
						$num=(int)substr($i,2,255);
						while (1)
						{
							if ($let>$let_end){break(2);}
							if ($let==$let_max && $num>$num_max){break(2);}
							if ($num>$cards[$let-65]-1) // Go to the next track | Переход на следующую дорожку
							{
								$num=$num-($cards[$let-65]);
								$let++;
							}
							else
							{
								break;
							}
						}
						if (!$count)
						{
							mysqli_query($db,"INSERT INTO `actions` SET `device`=".(int)$id.",`action`='get_number',`time`=".time());
							$act_id=mysqli_insert_id($db);
						}		
						$count++;
						if ($row['model']=='SR-Nano-500')
						{
							$l=chr($let);
							if ($num<10){$num='0'.$num;}
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$num."'";
						}		
						else
						{
							$l=chr($let);
							if ($num<10){$num='00'.$num;}
							elseif ($num<100){$num='0'.$num;}
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$num."'";
						}		
						mysqli_query($db,$qry);
					}
					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
			}
			elseif ($row['model']=='SR-Train')
			{
				if ($from==-1)
				{
					$from=0;
					$to=$data['rows'];
				}
				if (is_array($select))
				{
					foreach ($select AS $i)
					{
						if ($data['rows']<$i || $data['row_begin']>$i)
						{
							return(array('status'=>0,'message'=>'Ошибка — Диапазон устройства: '.$data['row_begin'].'...'.$data['rows']));
						}
					}
					
					$modems=explode(',',$row['modems']);

					mysqli_query($db,"INSERT INTO `actions` SET `device`=".(int)$id.",`action`='get_number',`time`=".time());
					$act_id=mysqli_insert_id($db);

					$count=0;
					$done=array();
					foreach ($select AS $i)
					{
						if (!in_array($i,$done))
						{
							$m='';
							for ($n=1;$n<9;$n++)
							{
								if (in_array($n,$modems))
								{
									$m.=$n.',';
									$count++;
								}
							}
							if (in_array($i+3,$select))
							{	
								$done[]=$i+3;			
								for ($n=9;$n<17;$n++)
								{
									if (in_array($n,$modems))
									{
										$m.=$n.',';
										$count++;
	                        		                	}
								}
							}
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`row`=".$i.",`place`='".trim($m,',')."'";
							mysqli_query($db,$qry);
						}
					}
					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
				else
				{
					if ($data['rows']<$to || $data['row_begin']>$from)
					{
						return(array('status'=>0,'message'=>'Ошибка — Диапазон устройства: '.$data['row_begin'].'...'.$data['rows']));
					}

					$modems=explode(',',$row['modems']);

					mysqli_query($db,"INSERT INTO `actions` SET `device`=".(int)$id.",`action`='get_number',`time`=".time());
					$act_id=mysqli_insert_id($db);
					$count=0;
					for ($i=$from;$i<=$to;$i++)
					{
						$m='';
						if (!in_array($i,$done))
						{
							for ($n=1;$n<9;$n++)
							{
								if (in_array($n,$modems))
								{
									$m.=$n.',';
									$count++;
								}
							}
							if ($i+3<=$to)
							{				
								for ($n=9;$n<17;$n++)
								{
									$done[]=$i+3;			
									if (in_array($n,$modems))
									{
										$m.=$n.',';
										$count++;
		        	                                	}
								}
							}
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`row`=".$i.",`place`='".trim($m,',')."'";
							mysqli_query($db,$qry);
						}
					}

					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
			}
		}
		else
		{
			return(array('status'=>0,'message'=>'Ошибка!')); // Error
		}
	}
}

// Creating an action for a SIM card pool
// Создание действия для пула СИМ-карт
function action_pool_create($id,$type) 
{
//	$id		Pool ID
//	$type		Action Type

	global $db;
	$out=0;
	$n=0;
	$d=-1; 
	$r=-1;
	$rowMin=1000000;
	$counter=0;
	$device=0;
	$row=array();
	$act_id_old=0;
	$task=0;
	$qry='SELECT c.*,d.`modems`,d.`model` FROM `card2pool` p INNER JOIN `cards` c ON c.`number`=p.`card` INNER JOIN `devices` d ON d.id=c.`device` WHERE p.`pool`='.(int)$id.' ORDER BY c.`device`,c.`place`';
	if ($result = mysqli_query($db, $qry)) 
	{
		$i=0;
		while ($row[$i]=mysqli_fetch_assoc($result)){$i++;}
		for ($k=0;$k<$i;$k++) 
		{
			// Getting a row and place | Получение ряда и места
			$place=explode('-',$row[$k]['place']);
			$modems=explode(',',$row[$k]['modems']);
			if ($device!=$row[$k]['device'])
			{
				$qry="INSERT INTO `actions` SET `device`=".$row[$k]['device'].",`action`='".$type."',`pool_id`=".(int)$id.",`time`=".time();
				mysqli_query($db,$qry);
				$act_id_old=$act_id;
				$act_id=mysqli_insert_id($db);
				$counter=0;
				$task++;
			}
			$device=$row[$k]['device'];
			if ($row[$k]['model']=='SR-Train' || ($row[$k-1]['model']=='SR-Train' && $device!=$d))
			{
				if (($device!=$d || $place[0]!=$r) && $m)
				{
					if ($d && $device!=$d){$d2=$d;$act=$act_id_old;} else {$d2=$device;$act=$act_id;} 
					$out=1;
					$insert=1;
					if ($r>2) // Search for an already created row to add a task for second-row modems | Поиск уже составленного ряда, чтобы добавить задание для модемов второго ряда
					{
						if ($subResult = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `device`='.$d2.' AND `action`='.$act.' AND `row`='.($r-3))) 
						{
							if ($subRow = mysqli_fetch_assoc($subResult))
							{
								$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$m2,',')."' WHERE `id`=".$subRow['id'];
								mysqli_query($db,$qry);
								$insert=0;
							}
						}
					}
					if ($insert)
					{
						$qry="INSERT INTO `card2action` SET `device`=".$d2.",`action`=".$act.",`row`=".$r.",`place`='".trim($m,',')."'";
						mysqli_query($db,$qry);
					}
					$m=$m2='';
					$rowMin=1000000;
				}
				$d=$device;
				$r=$place[0];
				// Checking whether the current modem is disabled in the device settings | Проверка не отключен ли текущий модем в настройках устройства
				$status=0;
				for ($i=0;$i<count($modems);$i++)
				{
					if ($place[1]==$modems[$i])
					{
						$counter++;
						$m.=$modems[$i].','; // If the modem is in the allowed list, add it to the array for processing | Если модем есть в списке разрешенных - добавляем в массив для обработки
					}
					if ($place[1]+8==$modems[$i])
					{
						$m2.=$modems[$i].','; // If the modem is in the allowed list, add it to the array for processing | Если модем есть в списке разрешенных - добавляем в массив для обработки
					}
				}
			}
			if ($row[$k]['model']=='SR-Nano-500')
			{
				$out=1;
				$counter++;
				$l=$row[$k]['place'][0];
				$p=substr($row[$k]['place'],1,3);
				if (strlen($p)<2){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".$row[$k]['device'].",`action`=".$act_id.",`place`='".$l.$p."'");
			}		
			elseif ($row[$k]['model']=='SR-Nano-1000')
			{
				$out=1;
				$counter++;
				$l=$row[$k]['place'][0];
				$p=substr($row[$k]['place'],1,3);
				if (strlen($p)<2){$p='00'.$p;}
				elseif (strlen($p)<3){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".$row[$k]['device'].",`action`=".$act_id.",`place`='".$l.$p."'");
			}		

			$qry="UPDATE `actions` SET `count`=".$counter." WHERE `id`=".$act_id;
			mysqli_query($db,$qry);
		}
		if ($m)
		{
			$out=1;
			$insert=1;
			if ($r>2) // // Search for an already created row to add a task for second-row modems | Поиск уже составленного ряда, чтобы добавить задание для модемов второго ряда	
			{
				if ($result2 = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' AND `row`='.($r-3))) 
				{
					if ($row2 = mysqli_fetch_assoc($result2))
					{
						$qry="UPDATE `card2action` SET `place`='".trim($row2['place'].','.$m2,',')."' WHERE `id`=".$row2['id'];
						mysqli_query($db,$qry);
						$insert=0;
					}
				}
			}
			if ($insert)
			{
				$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".$r.",`place`='".trim($m,',')."'";
				mysqli_query($db,$qry);
			}
			$m='';
			$m2='';
			$rowMin=1000000;
		}
		if (!$out)
		{
			$qry="DELETE FROM `actions` WHERE `id`=".$act_id;
			mysqli_query($db,$qry);
		}
		else
		{
			$qry="UPDATE `pools` SET `status`='waiting' WHERE `id`=".(int)$id;
			mysqli_query($db,$qry);
		}
	}
	return(array('status'=>$out,'action'=>$act_id,'task'=>$task));
}

// Creating an action for a device
// Создание задачи для устройства
function action_device_create($id,$type)
{
//	$id		Device ID
//	$type		Action Type

	global $db;
	$out=0;
	$n=0;
	$qry='SELECT * FROM `devices` WHERE `id`='.(int)$id;
	$result = mysqli_query($db, $qry);
	if ($row = mysqli_fetch_assoc($result))
	{
		$out=1;
		$place=explode('-',$row['place']);
		$modems=explode(',',$row['modems']);
		$data=unserialize($row['data']);
		$count=count($modems)*$data['rows'];
		$qry="INSERT INTO `actions` SET `device`=".$row['id'].",`action`='".$type."',`count`='".$count."',`time`=".time();
		mysqli_query($db,$qry);
		$act_id=mysqli_insert_id($db);
		$n=0;
		if (strpos($type,'dev_')===false && strpos($type,'online')===false)
		{
			for ($i=$data['row_begin'];$i<=$data['rows'];$i++)
			{
				$qry="INSERT INTO `card2action` SET `device`=".$row['id'].",`action`=".$act_id.",`row`=".$i.",`place`='".$row['place']."'";
				$n++;
				if ($n>2)
				{
					$i=$i+3;
					$n=0;
				}
				mysqli_query($db,$qry);
			}
		}
	}
	return($out);
}

// Getting a phone number
// Получение номера телефона
function get_number($dev,$row,$place,$operator='')
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device

	global $db;
	$status=0;
	if (!$operator)
	{
		sr_command('[get_number:'.$dev.'] Getting the name of the operator');
		$answer=sr_answer($dev,0,10,'+COPS');
		if ($answer=='error:no answer')
		{
			sr_command($dev,'modem>send:AT+COPS?'); // Repeated request for the operator name | Повторный запрос названия оператора
			$answer=sr_answer($dev,0,10,'+COPS');
		}
		if ($answer && strpos($answer,'error:')===false)
		{
			preg_match('!"(.*)"!Uis', $answer, $test);
			$operator=$test[1];
		}
		else
		{
			setlog('[get_number:'.$dev.'] The modem did not return a response to the operator\'s request!'); // Модем не ответил на запрос оператора
			return($status);
		}
	}		
	if ($operator)	
	{
		// Getting rules for getting a number | Получение правил запроса номера
		if ($result = mysqli_query($db, "SELECT * FROM `operators` WHERE `name`='".$operator."' ORDER BY id DESC LIMIT 1")) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				if (!$getNumber=$resRow['get_number'])
				{
					setlog('[get_number:'.$dev.'] There is no method for getting a number'); // Нет методики получения номера
					return($status);
				}
				$getNumberType=$resRow['get_number_type'];
				$operatorId=$resRow['id'];
				setlog('[get_number:'.$dev.'] The modem is connected to the '.$operator.' network (ID:'.$resRow['id'].')'); 
			}
			else
			{
				setlog('[get_number:'.$dev.'] '.$operator.' operator not found!'); // Оператор не найден
				return($status);
			}
		}
	}
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема

	if ($getNumberType=='sms' && !$k)
	{
		sr_command($dev,'modem>send:AT+CMGDA="DEL ALL"'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
	}
	setlog('[get_number:'.$dev.'] Request a phone number');
	sr_command($dev,'modem>send:AT+CUSD=1,"'.$getNumber.'"'); // Запрос номера телефона

	for ($n=0;$n<2;$n++)
	{
		if ($getNumberType=='sms') // The phone number is returned in an SMS | Номер телефона возвращается в SMS
		{
			$answer=sr_answer($dev,0,20,'+CMTI: "');
	                preg_match('!CMTI: ".*",(.*)!is', $answer, $test);
			if ($sms=$test[1])
			{
				setlog('[get_number:'.$dev.'] Getting an SMS #'.$sms); // Получение SMS с номером телефона
				sr_command($dev,'modem>send:AT+CMGR='.$sms);
				$answer=str_replace('.','',sr_answer($dev,0,40,'CMGR:'));
		                preg_match('!([0-9]{10,11})\s{1,10}OK!', $answer, $test);
				if ($number=trim($test[1]))
				{
					$status=1;
					sr_command($dev,'modem>send:AT+CMGD='.$sms); // Deleting text SMS with a phone number || Удаление СМС с номером телефона
					break;
				}
				else
				{
					setlog('[get_number:'.$dev.'] The phone number is not received!'); // Номер телефона не получен
				}
			}
			else
			{
				setlog('[get_number:'.$dev.'] SMS not received!'); // SMS не получена
			}
		}
		else
		{		
			$answer=sr_answer($dev,0,15,'CUSD:');
			setlog('[get_number:'.$dev.'] Request a number: '.$answer); // Запрос номера телефона
	                preg_match('!([0-9]{10,11})!', $answer, $test);
			if ($number=trim($test[1]))
			{
				$status=1;
				break;
			}
		}
	}
	if ($status)
	{
		if (strpos('!'.$number,'!'.$GLOBALS['set_data']['phone_prefix'])===false)
		{
			$number=$GLOBALS['set_data']['phone_prefix'].$number;
		}
		setlog('[get_number:'.$dev.'] Received phone number: '.$number);
		if (ord($place[0])<58) // SR Train
		{
			if ($place>8)
			{
				$place=($row+3).'-'.($place-8);
			} 
			else 
			{
				$place=$row.'-'.$place;
			}
		}
		// Clearing a place in the database | Очищаем место в БД

		$qry="DELETE FROM `cards` WHERE
		`place`='".($place=remove_zero($place))."'";
		mysqli_query($db,$qry);

		// Saving the number | Сохраняем номер
		$qry="REPLACE INTO `cards` SET
		`number`='".$number."',
		`place`='".$place."',
		`device`=".(int)$dev.",
		`operator`=".(int)$operatorId.",
		`time_number`='".time()."',
		`time`='".time()."'";
		mysqli_query($db,$qry);
	}
	return($status);
}

// Getting a balance
// Получение баланса
function get_balance($dev,$row,$place,$operator='')
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the panel

	global $db;
	$status=0;

	if (ord($place[0])<58) // SR-Train
	{
		if ($place>8)
		{
			$place=($row+3).'-'.($place-8);
		} 
		else 
		{
			$place=$row.'-'.$place;
		}
	}

	// Getting balance request rules | Получение правил запроса баланса
	if ($result = mysqli_query($db, "SELECT c.*,o.`get_balance`,o.`get_balance_type` FROM `cards` c INNER JOIN `operators` o ON o.`id`=c.`operator` WHERE c.`place`='".remove_zero($place)."'")) 
	{
		if ($resRow = mysqli_fetch_assoc($result))
		{
			$cardId=$resRow['id'];
			if (!$getBalance=$resRow['get_balance'])
			{
				setlog('[get_balance:'.$dev.'] There is no method for getting a balance!'); // Нет методики получения баланса
				return($status);
			}
			$getBalanceType=$resRow['get_balance_type'];
		}
		else
		{
			setlog('[get_balance:'.$dev.'] SIM card or Operator not found!'); // Нет методики получения баланса
			return($status);
		}
	}
	sr_answer_clear($dev,1); // Очищаем буфер ответов модема
	for ($k=0;$k<2;$k++)
	{
		if ($getBalanceType=='sms' && !$k)
		{
			setlog('[get_balance:'.$dev.'] Deleting all SMS from the SIM card!');
			sr_command($dev,'modem>send:AT+CMGDA="DEL ALL"'); // Удаление всех СМС с СИМ-карты
		}
		setlog('[get_balance:'.$dev.'] Request a balance');
		sr_command($dev,'modem>send:AT+CUSD=1,"'.$getBalance.'"'); // Получение SMS с балансом
		for ($n=0;$n<2;$n++)
		{
			if ($getBalanceType=='sms')
			{
				$answer=sr_answer($dev,0,20,'+CMTI: "SM"');
		                preg_match('!CMTI: ".*",(.*)!is', $answer, $test);
				if ($sms=$test[1])
				{
					setlog('[get_balance:'.$dev.'] Getting an SMS #'.$sms); // Получение SMS с балансом
					sr_command($dev,'modem>send:AT+CMGR='.$sms);
					$answer=sr_answer($dev,0,40,'CMGR:');
			                preg_match('!"(.*)"!s', $answer, $test);
			                preg_match('!(minus|-)!i', $test[1], $minus);
			                preg_match('!([0-9]{1,5})([\.|\,])*([0-9]{1,2})*!', $test[1], $test);
					if ($balance=trim(trim($test[1]+$test[2]+'.'.$test[3],'.')))
					{
						$status=1;
						sr_command($dev,'modem>send:AT+CMGD='.$sms); // Deleting text SMS with a blanace || Удаление СМС с балансом
					}
					else
					{
						setlog('[get_balance:'.$dev.'] The balance is not received!'); // Баланс не получен
					}
				}
				else
				{
					setlog('[get_balance:'.$dev.'] SMS not received!'); // SMS не получена
				}
			}
			else
			{		
				$answer=sr_answer($dev,0,15,'+CUSD: 0');
		                preg_match('!"(.*)"!', $answer, $test);
		                preg_match('!(minus|-)!i', $test[1], $minus);
		                preg_match('!([0-9]{1,5})([\.|\,])*([0-9]{1,2})*!', $test[1], $test);
				if ($balance=trim(trim($test[1].'.'.$test[3],'.')))
				{
					$status=1;
					break;
				}
			}
		}
	}
	if ($status)
	{
		if ($minus[1]){$balance=$balance*-1;}
		setlog('[get_balance:'.$dev.'] Received balance: '.$balance);

		// Saving the balance | Сохраняем баланс
		$qry="UPDATE `cards` SET
		`balance`='".$balance."',
		`time_balance`='".time()."',
		`time`='".time()."'
		WHERE `id`='".$cardId."'";
		mysqli_query($db,$qry);
	}
	return($status);
}

// Receiving SMS from the specified modem
// Получение SMS с указанного модема
function get_sms($dev,$curRow,$place,$operator='')
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device

	global $db;
	$sms=array();
	while ($begin>time()){}
	$out=0;
	$com='';

	if ($place>=1 && $place<=16)
	{
		setlog('[get_sms:'.$dev.'] Select modem: '.$place);
		$com='modem>select:'.$place.'&&';
	}
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема
	for ($n=1;$n<20;$n++) // Processing 20 SMS memory cells | Обрабатываем 20 ячеек памяти SMS
	{
		for ($k=0;$k<2;$k++)
		{
			sr_command($dev,$com.'modem>send:AT+CMGR='.$n);
			$com="";
			$answer=sr_answer($dev,0,30,'CMGR');
	                preg_match('!AT\+CMGR.{0,20}(OK|ERROR)!s', $answer, $test);
			setlog('[get_sms:'.$dev.'] SMS #'.$n.' received: '.$test[1]);
			if (strpos($answer,'error:')!==false && $k>0)
			{
				return(0);
			}
			elseif (strpos($answer,'error:')!==false)
			{
				setlog('[get_sms:'.$dev.'] Repeated request #1: '.sr_answer($dev,0)); // Повторный запрос #1
				setlog('[get_sms:'.$dev.'] Repeated request #2: '.sr_answer($dev,0)); // Повторный запрос #2
			}
			elseif (strpos($answer,'error:')===false) 
			{
				break;
			}
		}
		if ($test[1] && strlen($answer)<50)
		{
			if ($test[1]=='ERROR' && $n>1){$test[1]='OK';}
			setlog('[get_sms:'.$dev.'] Completion with the status: '.$test[1]); // Завершение
			$out=1;
			break;
		}
		elseif (strlen($answer)>30)
		{
			setlog('[get_sms:'.$dev.'] Preparing an SMS'); // Подготовка SMS
	                preg_match('!"(.*)","(.*)","(.*)","(.*)"(.*)OK!Us', $answer, $test);
			$a=explode(',',$test[4]);
			$b=explode('/',$a[0]);
			$a=explode('+',$a[1]);
			$c=explode(':',$a[0]);
			$tm=mktime($c[0],$c[1],$c[2],$b[1],$b[2],$b[0]);
			$sms[]=array('sender'=>$test[2],'time'=>$tm,'txt'=>trim(substr($test[5],0,-2)));
		}
	}
	if (count($sms))
	{
		if (ord($place[0])<58) // SR Train
		{
			if ($place>8)
			{
				$place=($surRow+3).'-'.($place-8);
			} 
			else 
			{
				$place=$surRow.'-'.$place;
			}
		}

		// Getting a SIM card number | Получение номера СИМ-карты
		if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".remove_zero($place)."'")) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				for ($i=0;$i<count($sms);$i++)
				{
					$update=0;
					// Checking whether to add a long SMS | Проверка надо ли дописать длинную SMS
					if ($result = mysqli_query($db, "SELECT * FROM `sms_incoming` WHERE `number`='".$row['number']."' AND `sender`='".$sms[$i]['sender']."' AND `time`>".($sms[$i]['time']-10))) 
					{
						if ($row2 = mysqli_fetch_assoc($result) && mb_strpos($row2['txt'],sms_prep($sms[$i]['txt']))===false)
						{
							$update=1;
							// Saving to the database | Сохранение в БД
							$qry="UPDATE `sms_incoming` SET
							`txt`='".sms_prep($row2['txt'].$sms[$i]['txt'])."'
							WHERE `id`=".$row2['id'];
							mysqli_query($db,$qry);
							setlog('[get_sms:'.$dev.'] Added part of the SMS');
						}
					}
					if (!$update)
					{
						// Saving to the database | Сохранение в БД
						$qry="INSERT INTO `sms_incoming` SET
						`number`='".$row['number']."',
						`sender`='".$sms[$i]['sender']."',
						`time`=".$sms[$i]['time'].",
						`txt`='".sms_prep($sms[$i]['txt'])."'";
						mysqli_query($db,$qry);
						setlog('[get_sms:'.$dev.'] SMS saved');
					}
				}
			}
			else
			{
				setlog('[get_sms:'.$dev.'] The SIM card phone number is not received, the SMS is not saved!'); // Номер SIM-карты не получен, SMS не сохранена
				return(0);
			}
		}
		if (count($sms))
		{
			return(1);
		}
	}
	return($out);
}

?>