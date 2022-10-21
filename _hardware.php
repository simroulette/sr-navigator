<?                    
// ===================================================================
// Sim Roulette -> Hardware functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

// Deleting device SIM cards from the database table
// Удаление СИМ-карт агрегатора из таблицы БД
function dev_truncate($dev)
{
//	$dev		Device ID

	global $db;
	$qry="DELETE FROM `cards` WHERE `device`=".(int)$dev;
	mysqli_query($qry,$db);
	return(1);
}

// Clearing device responses
// Очистка ответов агрегаторов
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
		setlog('Clear incoming','link_'.$dev);
		mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `device`='.(int)$dev); 
	}
	else
	{
		setlog('Clear incoming SR','link_'.$dev);
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
// Отправка команды агрегатору и получение ответа (опционально)
function sr_command($dev,$command,$wait=0,$transaction_code='',$transaction_time=0)
{
//	$dev			Device ID
//	$command		Command
//	$wait			How many seconds to wait for a response / 0-don't wait
//	$transaction_code	A code (random number) for subsequent access within the transaction
//	$transaction_time	The automatic completion of the transaction
	global $db;
	setlog('[sr_command:'.$dev.'] Command:'.$command,'link_'.$dev);
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано

	// Transaction processing for priority access to the device | Обработка транзакций для приоритетного доступа к устройcтву

	if (file_exists($GLOBALS['root'].'flags/command_'.$dev))
	{
		$data=explode(';',file_get_contents($GLOBALS['root'].'flags/command_'.$dev));
	}
	if ($transaction_code && $transaction_code==$data[1] && !$transaction_time && !$command)
	{
		unlink($GLOBALS['root'].'flags/command_'.$dev);
		setlog('[sr_command:'.$dev.'] Transaction exit','link_'.$dev);
		return;
	}
	// Checking whether the transaction is open | Проверяем открыта ли транзакция
	if (!$GLOBALS['terminal_mode']){
		if ((!$transaction_code || $transaction_code!=$data[1]) && $data[0]>time())
		{
			while ($data[0]>time())
			{
				setlog('[sr_command:'.$dev.'] Waiting for the transaction to end','link_'.$dev); // Ожидание окончания транзакции
				if (!file_exists($GLOBALS['root'].'flags/command_'.$dev)){break;}
				sleep(1);
			}
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
			
			$ss=$s;
			if ($row['type']=='server') // Accessing the server device | Обращение к агрегатору-серверу
			{
				$link='http://'.$row['ip'].'/port?data='.$row['token_local'].urlencode('||'.$s.'||'.$command);
			       	$ch = curl_init();
			       	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		        	curl_setopt($ch, CURLOPT_HEADER, 0);
			       	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $link);
		        	$answer = curl_exec($ch);
			       	curl_close($ch);
				$answer=explode('#!#',$answer);
				if ($answer[1]) // Saving the response received from the device | Сохранение полученного от агрегатора ответа
				{
					flagSet($dev,'answer');
					if (!$answer[0]) // If there is an out-of-order response from the device, we generate a random number to save as a unique response in the table | Если внеочередной ответ агрегатора - генерируем случайное число, чтобы сохранить в таблице как уникальный ответ
					{
						$uniq=",`uniq`='".rand(1111,9999).rand(1111,9999)."'";
					} 
					else 
					{
						$uniq="";
					} 
					$qry="INSERT `link_incoming` SET
					`device`='".(int)$dev."',
					`step`=".(int)$answer[0].",
					`answer`='".mysqli_real_escape_string($db,$answer[1]."'".$uniq);
					mysqli_query($db,$qry);
				}
				$qry="UPDATE `devices` SET
				`step`=".($s+1)."
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
				setlog('[sr_command:'.$dev.'] Out:'.$out,'link_'.$dev);
				return($out);
			}
		}
		else {return('error:device not found');} // Ошибка - Агрегатор отсутсвует
	}

	// Accessing the client device | Обращение к агрегатору-клиенту
	// Save the command to the "link_outgoing" table | Сохраняем команду в таблицу "link_outgoing"

	if ($c=trim($command))
	{
		// Ждем устройство 30 минут
		$time=time();
		for ($w=0;$w<180;$w++)
		{
			$access=flagGet($dev,'answer',1);
			if ($access+10<time())
			{
				sleep(10);
			}
			else
			{
				break;
			}
		}
		$GLOBALS['time_correct']=$GLOBALS['time_correct']+(time()-$time);
		$qry="INSERT `link_outgoing` SET
		`device`='".(int)$dev."',
		`command`='".$c."',
		`step`=".(int)$s++;

		mysqli_query($db,$qry);

		if ($result = mysqli_query($db, "SELECT * FROM `link_outgoing` WHERE `device`=".(int)$dev." ORDER BY `id` DESC LIMIT 10")) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				setlog('[sr_command:'.$dev.'] '.$row['command'].':'.$row['step'],'link_'.$dev);
			}
		}
	}
	else
	{
		return('error:no command specified');
	}

	// Saving the step for the selected device in the "devices" table | Сохраняем шаг (step) для выбранного агрегатора в таблицу "devices"
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

function sr_command_smart($dev,$command,$sign="",$wait=0,$transaction_code='',$transaction_time=0)
{
//	$dev			Device ID
//	$command		Command
//	$wait			How many seconds to wait for a response / 0-don't wait
//	$transaction_code	A code (random number) for subsequent access within the transaction
//	$transaction_time	The automatic completion of the transaction
	global $db;
	setlog('[sr_command_smart:'.$dev.'] Command:'.$command,'link_'.$dev);
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано

	// Transaction processing for priority access to the device | Обработка транзакций для приоритетного доступа к устройcтву

	if (file_exists($GLOBALS['root'].'flags/command_'.$dev))
	{
		$data=explode(';',file_get_contents($GLOBALS['root'].'flags/command_'.$dev));
	}
	if ($transaction_code && $transaction_code==$data[1] && !$transaction_time && !$command)
	{
		unlink($GLOBALS['root'].'flags/command_'.$dev);
		setlog('[sr_command_smart:'.$dev.'] Transaction exit','link_'.$dev);
		return;
	}
	// Checking whether the transaction is open | Проверяем открыта ли транзакция
	if (!$GLOBALS['terminal_mode'])
	{
		if ((!$transaction_code || $transaction_code!=$data[1]) && $data[0]>time())
		{
			while ($data[0]>time())
			{
				setlog('[sr_command_smart:'.$dev.'] Waiting for the transaction to end','link_'.$dev); // Ожидание окончания транзакции
				if (!file_exists($GLOBALS['root'].'flags/command_'.$dev)){break;}
				sleep(1);
			}
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
	if ($result = mysqli_query($db, "SELECT `type`,`ip`,`token_local` FROM `devices` WHERE `id`=".(int)$dev)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			if ($row['type']=='server') // Accessing the server device | Обращение к агрегатору-серверу
			{
				$link='http://'.$row['ip'].'/port?data='.$row['token_local'].urlencode('||'.$s.'||'.$command);
			       	$ch = curl_init();
			       	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		        	curl_setopt($ch, CURLOPT_HEADER, 0);
			       	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $link);
		        	$answer = curl_exec($ch);
			       	curl_close($ch);
				$answer=explode('#!#',$answer);
				if ($answer[1]) // Saving the response received from the device | Сохранение полученного от агрегатора ответа
				{
					flagSet($dev,'answer');
					if (!$answer[0]) // If there is an out-of-order response from the device, we generate a random number to save as a unique response in the table | Если внеочередной ответ агрегатора - генерируем случайное число, чтобы сохранить в таблице как уникальный ответ
					{
						$uniq=",`uniq`='".rand(1111,9999).rand(1111,9999)."'";
					} 
					else 
					{
						$uniq="";
					} 
					$qry="INSERT `link_incoming` SET
					`device`='".(int)$dev."',
					`answer`='".mysqli_real_escape_string($db,$answer[1]."'".$uniq);
					mysqli_query($db,$qry);
				}
				$qry="UPDATE `devices` SET
				`step`=".($s+1)."
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
				setlog('[sr_command_smart:'.$dev.'] Out:'.$out,'link_'.$dev);
				return($out);
			}
		}
		else {return('error:device not found');} // Ошибка - Агрегатор отсутсвует
	}

	// Accessing the client device | Обращение к агрегатору-клиенту
	// Save the command to the "link_outgoing" table | Сохраняем команду в таблицу "link_outgoing"

	setlog('[sr_command_smart:'.$dev.'] Next','link_'.$dev);


	if ($c=trim($command))
	{
		// Ждем устройство 30 минут
		$time=time();
		for ($w=0;$w<180;$w++)
		{
			$access=flagGet($dev,'answer',1);
			if ($access+10<time())
			{
				sleep(10);
			}
			else
			{
				break;
			}
		}
		$GLOBALS['time_correct']=$GLOBALS['time_correct']+(time()-$time);
		$qry="INSERT `link_outgoing` SET
		`device`='".(int)$dev."',
		`command`='".mysqli_real_escape_string($db,$c)."'";
		mysqli_query($db,$qry);

		if ($result = mysqli_query($db, "SELECT * FROM `link_outgoing` WHERE `device`=".(int)$dev." ORDER BY `id` DESC LIMIT 10")) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				setlog('[sr_command_smart:'.$dev.'] '.$row['command'],'link_'.$dev);
			}
		}
	}
	else
	{
		setlog('[sr_command_smart:'.$dev.'] error:no command specified','link_'.$dev);
		return('error:no command specified');
	}

	if (!$wait) // Exit without waiting for a response | Выход без ожидания ответа
	{
		$out=1;
	} 
	else
	{
		$out=sr_answer_smart($dev,$sign,$wait);
	}
	if (!$transaction_time)
	{
		unlink($GLOBALS['root'].'flags/command_'.$dev);
	}
	return($out);
}

// Getting the device response
// Получение ответа от агрегатора
function sr_answer($dev,$step=0,$wait=20,$search="") 
{
//	$dev		Device ID
//	$step		Device Step OR 0
//	$wait		How many seconds to wait for a response
//	$search		Поиск в ответе агрегатора

	global $db;
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано
	$time=time()+$wait;

	$search2=explode('||',$search);
	$search2=$search2[1];
	
	// Accessing the server device | Обращение к агрегатору-серверу
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
					mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.(int)$subRow['id']); 
		
					if ($search && (mb_strpos($subRow['answer'],$search)!==false || ($search2 && mb_strpos($subRow['answer'],$search2))!==false))
					{
						return($subRow['answer']);
					}
					elseif (!$search)
					{
						return($subRow['answer']);
					}
				}
			}

			// Getting from the device | Получение ответа
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

				if ($answer[1])
				{
					flagSet($dev,'answer');
				}

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

	// Accessing the client device | Обращение к агрегатору-клиенту

	while ($time>time())
	{
		br($dev);
		if ($result = mysqli_query($db, 'SELECT * FROM `link_incoming` WHERE `device`='.(int)$dev.' AND `step`='.(int)$step.' ORDER BY id')) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.$row['id']); 
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
				if ($search && (mb_strpos($row['answer'],$search)!==false || ($search2 && mb_strpos($row['answer'],$search2))!==false))
				{
					return($row['answer']);
				}
				elseif (!$search)
				{
					return($row['answer']);
				}
			}
		}
		if ($time>time()){sleep(1);}
	}
	return('error:no answer');
}

// Getting the device response
// Получение ответа от агрегатора
function sr_answer_smart($dev,$sign="",$wait=20,$search="") 
{
//	$dev		Device ID
//	$step		Device Step OR 0
//	$wait		How many seconds to wait for a response
//	$search		Поиск в ответе агрегатора

	global $db;
	if (!$dev){return('error:device is not selected');} // Ошибка - Устройство не указано
	$time=time()+$wait;

	$search2=explode('||',$search);
	$search2=$search2[1];
	
	// Accessing the server device | Обращение к агрегатору-серверу
	$qry="SELECT `ip`,`token_local` FROM `devices` WHERE `type`='server' AND `ip`<>'' AND `id`=".(int)$dev;
	if ($result = mysqli_query($db, $qry)) 
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
					mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.(int)$subRow['id']); 
		
					if ($search && (mb_strpos($subRow['answer'],$search)!==false || ($search2 && mb_strpos($subRow['answer'],$search2))!==false))
					{
						return($subRow['answer']);
					}
					elseif (!$search)
					{
						return($subRow['answer']);
					}
				}
			}

			// Getting from the device | Получение ответа
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

				if ($answer[1])
				{
					flagSet($dev,'answer');
				}

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

	// Accessing the client device | Обращение к агрегатору-клиенту

	while ($time>time())
	{
		br($dev);
		if ($sign)
		{
			$signCom=' AND `sign`="'.$sign.'"';
		}
		$qry='SELECT * FROM `link_incoming` WHERE `device`='.(int)$dev.$signCom.' ORDER BY id LIMIT 1';
		if ($result = mysqli_query($db, $qry)) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.(int)$row['id']); 
				if ($sign)
				{
					return($row['answer']);
				}			
				if ($search && (mb_strpos($row['answer'],$search)!==false || ($search2 && mb_strpos($row['answer'],$search2))!==false))
				{
					return($row['answer']);
				}
				elseif (!$search)
				{
					return($row['answer']);
				}
			}
		}
		if ($time>time()){sleep(1);}
	}
	return('error:no answer');
}

// Stopping an action
// Остановка задачи
function action_stop($id)
{
//	$id		Action ID

	global $db;
	if ($result = mysqli_query($db, 'SELECT a.* FROM `actions` a 
	INNER JOIN `devices` d ON d.id=a.device 
	WHERE a.`id`='.(int)$id)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db, "DELETE FROM `actions` WHERE `id`=".(int)$id);
			mysqli_query($db, "DELETE FROM `card2action` WHERE `action`=".(int)$id);
			if ($row['pool_id'])
			{
				$qry="UPDATE `pools` SET `status`='free' WHERE `id`=".(int)$row['pool_id'];
				mysqli_query($db,$qry);
			}
			elseif ($row['card_number'])
			{
				$qry="UPDATE `cards` SET `status`='free' WHERE `number`=".(int)$row['card_number'];
				mysqli_query($db,$qry);
			}
			flagSet($row['device'],'act_'.(int)$id); // ???
			flagSet($row['device'],'stop');
		}		
	}
}

function action_close($id)
{
	global $db;

	if ($result = mysqli_query($db, 'SELECT a.*,d.`model` FROM `actions` a 
	INNER JOIN `devices` d ON d.id=a.device 
	WHERE a.`id`='.(int)$id)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			if ($result2 = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `action`='.$row['id'].' ORDER BY `place`')) 
			{
				while ($row2 = mysqli_fetch_assoc($result2))
				{
					if ($row['model']=='SR-Train')
					{
						$a=explode(',',$row2['place']);
						for ($i=0;$i<count($a);$i++)
						{
							if ($a[$i]<=8)
							{
								$place.=$row2['row'].'-'.$a[$i].',';
							}
							else
							{
								$place.=($row2['row']+3).'-'.($a[$i]-8).',';
							}
						}
					}
					elseif (strpos($row['model'],'SR-Nano')!==false)
					{
						$place.=remove_zero($row2['place']).', ';
					}
				}
			}

			$qry="INSERT INTO `reports` 
			SET 
			`report`='".$row['report']."',
			`time_begin`=".(int)$row['time'].",
			`time_end`=".time().",
			`card_number`='".$row['card_number']."',
			`pool_id`='".$row['pool_id']."',
			`device`='".$row['device']."',
			`action`='".$row['action']."',
			`count`='".$row['count']."',
			`errors`='".$row['errors']."',
			`success`='".$row['success']."',
			`place`='".trim(trim($place,' '),',')."'";
			mysqli_query($db,$qry);
		}
	}
}

// Creating an action for a SIM card
// Создание задачи для СИМ-карты
function action_card_create($number,$type,$data='')
{
//	$number		Phone Number
//	$type		Action Type

	global $db;
	if ($result = mysqli_query($db, 'SELECT c.*,d.`modems`,d.`model` FROM `cards` c INNER JOIN `devices` d ON d.id=c.`device` WHERE c.`number`='.(int)$number)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db,"UPDATE `cards` SET `status`='waiting' WHERE `number`=".(int)$number);
			mysqli_query($db,"INSERT INTO `actions` SET `report`='',`card_number`=".(int)$number.",`device`=".$row['device'].",`action`='".$type."',`data`='".serialize($data)."',`count`=1,`time`=".time());
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
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".$act_id.",`row`=".$place[0].",`place`='".$place[1]."'");
			}
			else if ($row['model']=='SR-Box-8' || $row['model']=='SR-Box-2' || $row['model']=='SR-Box-Bank' || $row['model']=='SR-Box-2-Bank' || $row['model']=='SR-Board')
			{
				// Getting a place | Получение места
				$placeColumn=ord($row['place'][0])-65;
				if (strlen($row['place'])>1)
				{
					$placeRow=substr($row['place'],1,10);
				}
				else
				{
					$placeRow=0;
				}
				// Checking whether this modem is enabled | Проверка задействован ли модем
				$modems=explode(',',$row['modems']);
				$status=0;
				for ($i=0;$i<count($modems);$i++)
				{
					if (ord($row['place'])-64==$modems[$i])
					{
						$status=1;
						break;
					}
				}
				if (!$status){return(array('status'=>0));}
				$qry="INSERT INTO `card2action` SET `device`=".$row['device'].",`action`=".$act_id.",`row`=".$placeRow.",`place`='".(ord($row['place'])-64)."'";
				mysqli_query($db,$qry);
			}
			else if ($row['model']=='SR-Organizer')
			{
				// Getting a row and a place | Получение ряда и места
				$place=explode('-',$row['place']);
				if (!$place[0] || !$place[1]){return(array('status'=>0));}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".(int)$act_id.",`row`=0,`place`='".$row['place']."'");
			}
			else if ($row['model']=='SR-Organizer')
			{
				// Getting a row and a place | Получение ряда и места
				$place=explode('-',$row['place']);
				if (!$place[0] || !$place[1]){return(array('status'=>0));}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".(int)$act_id.",`row`=0,`place`='".$row['place']."'");
			}
			else if ($row['model']=='SR-Organizer-Smart')
			{
				// Getting a row and a place | Получение ряда и места
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".(int)$act_id.",`row`=0,`place`='".$row['place']."'");
			}
			elseif ($row['model']=='SR-Nano-500')
			{
				$l=$row['place'][0];
				$p=substr($row['place'],1,3);
				if (strlen($p)<2){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".(int)$act_id.",`place`='".$l.$p."'");
			}		
			elseif ($row['model']=='SR-Nano-1000')
			{
				$l=$row['place'][0];
				$p=substr($row['place'],1,3);
				if (strlen($p)<2){$p='00'.$p;}
				elseif (strlen($p)<3){$p='0'.$p;}
				mysqli_query($db,"INSERT INTO `card2action` SET `device`=".(int)$row['device'].",`action`=".(int)$act_id.",`place`='".$l.$p."'");
			}		
			return(array('status'=>1,'action'=>$act_id));
		}
	}
}

// Creating an action for scanning SIM cards
// Создание задачи сканирования СИМ-карт
function action_card_scanner($id,$span,$new,$fool=0)
{
//	$id		Device ID
//	$span		Span of SIM cards, examples ("0,1", "0-3", "A0,A1", "A10-B20" etc)
//	$new		Only new

	global $db;
	if (!$id){return(array('status'=>0,'message'=>'Ошибка — Вы не выбрали агрегатор!'));} // Error - you didn't select a device!
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
							$qry=1;
							$l=$i[0];
							$p=substr($i,1,3);
							if ($new)
							{
								if ($testResult = mysqli_query($db, "SELECT `number`,`place` FROM `cards` WHERE `device`=".(int)$id." AND `place`='".remove_zero($l.$p)."'"))
								{
									if ($testRow = mysqli_fetch_assoc($testResult))
									{
										if ($testRow['number'])
										{
											$qry='';
										}
									}								
								}
							}
							if ($qry)
							{
								if (!$count)
								{
									mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='".serialize(array('fool'=>(int)$fool))."',`action`='get_number',`time`=".time());
									$act_id=mysqli_insert_id($db);
								}		
								$count++;
								if ($row['model']=='SR-Nano-500')
								{
									if (strlen($p)<2){$p='0'.$p;}
									$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$p."'";
								}		
								else
								{
									if (strlen($p)<2){$p='00'.$p;}
									elseif (strlen($p)<3){$p='0'.$p;}
									$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$p."'";
								}		
								mysqli_query($db,$qry);
							}
						}
					}
					if ($count)
					{
						$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
						mysqli_query($db,$qry);

						$qry="UPDATE `actions` SET `report`,`count`=".$count." WHERE `id`=".$act_id;
						mysqli_query($db,$qry);
			
						return(array('status'=>0,'message'=>'ok','action'=>$act_id));
					}
					else
					{
						if ($row['model']=='SR-Nano-500')
						{
							return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны места СИМ-карт за пределами диапазонов:<br><br>A0-100, B0-90, C0-80, D0-68, E0-58, F0-46, G0-34, H0-24</div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
						}
						elseif ($row['model']=='SR-Nano-1000')
						{
							return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны места СИМ-карт за пределами диапазонов:<br><br>A0-139, B0-129, C0-119, D0-109, E0-100, F0-90, G0-80, H0-68, I0-58, J0-46, K0-34, L0-24</div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
						}
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
								$i=$let*1000;
							}
							else
							{
								break;
							}
						}
						$qry=1;
						$l=chr($let);
						if ($new)
						{
							if ($testResult = mysqli_query($db, "SELECT `number`,`place` FROM `cards` WHERE `device`=".(int)$id." AND `place`='".remove_zero($l.$num)."'"))
							{
								if ($testRow = mysqli_fetch_assoc($testResult))
								{
									if ($testRow['number'])
									{
										$qry='';
									}
								}								
							}
						}
						if ($qry)
						{
							if (!$count)
							{
								mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='".serialize(array('fool'=>(int)$fool))."',`action`='get_number',`time`=".time());
								$act_id=mysqli_insert_id($db);
							}		
							$count++;
							if ($row['model']=='SR-Nano-500')
							{
								if ($num<10){$num='0'.$num;}
								$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$num."'";
							}		
							else
							{
								if ($num<10){$num='00'.$num;}
								elseif ($num<100){$num='0'.$num;}
								$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$l.$num."'";
							}		
							if ($qry){mysqli_query($db,$qry);}
						}
					}
					if ($count)
					{

						$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
						mysqli_query($db,$qry);

						$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
						mysqli_query($db,$qry);
			
						return(array('status'=>0,'message'=>'ok','action'=>$act_id));
					}
					else
					{
						if ($row['model']=='SR-Nano-500')
						{
							return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны места СИМ-карт за пределами диапазонов:<br><br>A0-100, B0-90, C0-80, D0-68, E0-58, F0-46, G0-34, H0-24</div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
						}
						elseif ($row['model']=='SR-Nano-1000')
						{
							return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны места СИМ-карт за пределами диапазонов:<br><br>A0-139, B0-129, C0-119, D0-109, E0-100, F0-90, G0-80, H0-68, I0-58, J0-46, K0-34, L0-24</div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
						}
					}
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
							return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны ряды СИМ-карт за пределами диапазона: <b>'.$data['row_begin'].' — '.$data['rows'].'</b><br><br>— Диапазон задается в настройках агрегатора либо вычисляется в процессе инициализации</div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
  						}
					}
					
					$modems=explode(',',$row['modems']);

					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());
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
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='".trim($m,',')."'";
							mysqli_query($db,$qry);
						}
					}
					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
				else
				{
					if ($data['rows']<$to || $data['row_begin']>$from)
					{
						return(array('status'=>0,'message'=>'<div class="tooltip danger">— Выбраны ряды СИМ-карт за пределами диапазона: <b>'.$data['row_begin'].' — '.$data['rows'].'</b></div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
					}

					$modems=explode(',',$row['modems']);

					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());
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
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='".trim($m,',')."'";
							mysqli_query($db,$qry);
						}
					}

					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
			}
			elseif ($row['model']=='SR-Box-8' || $row['model']=='SR-Box-2')
			{
				$count=0;
				$done=array();
				$m='';
				$modems=explode(',',$row['modems']);
				for ($n=1;$n<9;$n++)
				{
					if (in_array($n,$modems))
					{
						$m.=$n.',';
						$count++;
					}
				}
				mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`count`=".$count.",`time`=".time());
				$act_id=mysqli_insert_id($db);

				$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=0,`place`='".trim($m,',')."'";
				mysqli_query($db,$qry);
				$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
				mysqli_query($db,$qry);
				return(array('status'=>0,'message'=>'ok','action'=>$act_id));
			}
			elseif ($row['model']=='SR-Box-Bank' || $row['model']=='SR-Board')
			{
				$data['row_begin']=1;
				$data['rows']=8;
				$d=unserialize($row['data']);
				if ($d['map']>1)
				{
					$data['row_begin']=1;
					$data['rows']=64;
				}
				if ($from==-1)
				{
					$from=0;
					$to=$data['rows'];
				}
				if (is_array($select))
				{
					$done=array();
					foreach ($select AS $i)
					{
						if (!in_array($i,$done))
						{
							$done[]=$i;
							if ($data['rows']<$i || $data['row_begin']>$i)
							{
								return(array('status'=>0,'message'=>'<div class="tooltip danger">— Диапазон вашего агрегатора с SIM-банком: <b>'.$data['row_begin'].' — '.$data['rows'].'</b></div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
							}
						}
					}
					
					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());
					$act_id=mysqli_insert_id($db);
					$count=0;
					$done=array();
					foreach ($select AS $i)
					{
						if (!in_array($i,$done))
						{
							$done[]=$i;
							$count=$count+8;
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='1,2,3,4,5,6,7,8'";
							mysqli_query($db,$qry);
						}
					}
					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));

				}
				else
				{
					if ($data['rows']<$to || $data['row_begin']>$from)
					{
						return(array('status'=>0,'message'=>'<div class="tooltip danger">— Диапазон вашего агрегатора с SIM-банком: <b>'.$data['row_begin'].' — '.$data['rows'].'</b></div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
					}

					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());
					$act_id=mysqli_insert_id($db);
					$count=0;
					for ($i=$from;$i<=$to;$i++)
					{
						$count=$count+8;
						$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='1,2,3,4,5,6,7,8'";
						mysqli_query($db,$qry);
					}

					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
			}
			elseif ($row['model']=='SR-Box-2-Bank')
			{
				$data['row_begin']=1;
				$data['rows']=8;
				$d=unserialize($row['data']);
				if ($d['map']>1)
				{
					$data['row_begin']=1;
					$data['rows']=64;
				}
				if ($from==-1)
				{
					$from=0;
					$to=$data['rows'];
				}

				if (is_array($select))
				{
					$done=array();
					foreach ($select AS $i)
					{
						if (!in_array($i,$done))
						{
							$done[]=$i;
							if ($data['rows']<$i || $data['row_begin']>$i)
							{
								return(array('status'=>0,'message'=>'<div class="tooltip danger">— Диапазон вашего агрегатора с SIM-банком: <b>'.$data['row_begin'].' — '.$data['rows'].'</b></div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
							}
						}
					}
					
					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());
					$act_id=mysqli_insert_id($db);
					$count=0;
					$done=array();
					foreach ($select AS $i)
					{
						if (!in_array($i,$done))
						{
							$done[]=$i;
							$count=$count+8;
							$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='1,2,3,4,5,6,7,8'";
							mysqli_query($db,$qry);
						}
					}
					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));

				}
				else
				{
					if ($data['rows']<$to || $data['row_begin']>$from)
					{
						return(array('status'=>0,'message'=>'<div class="tooltip danger">— Диапазон вашего агрегатора с SIM-банком: <b>'.$data['row_begin'].' — '.$data['rows'].'</b></div><br><br><span class="link but_win" onclick="getActions(\'ajax_card_scanner.php?device='.(int)$id.'\');">Исправить</span>'));
					}

					mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`action`='get_number',`time`=".time());

					$act_id=mysqli_insert_id($db);
					$count=0;
					for ($i=$from;$i<=$to;$i++)
					{
						$count=$count+8;
						$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`row`=".$i.",`place`='1,2,3,4,5,6,7,8'";
						mysqli_query($db,$qry);
					}

					$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
					mysqli_query($db,$qry);

					$qry="UPDATE `actions` SET `count`=".$count." WHERE `id`=".(int)$act_id;
					mysqli_query($db,$qry);
			
					return(array('status'=>0,'message'=>'ok','action'=>$act_id));
				}
			}
			elseif ($row['model']=='SR-Organizer')
			{
				$count=16;
				$place='1-1,1-2,1-3,1-4,1-5,1-6,1-7,1-8,2-1,2-2,2-3,2-4,2-5,2-6,2-7,2-8';
				$count=count($count);			
	
				mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`count`=".$count.",`action`='get_number',`time`=".time());
				$act_id=mysqli_insert_id($db);

				$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".(int)$act_id.",`place`='".$place."'";
				mysqli_query($db,$qry);

				$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
				mysqli_query($db,$qry);
			
				return(array('status'=>0,'message'=>'ok','action'=>$act_id));
			}
			elseif ($row['model']=='SR-Organizer-Smart')
			{
				if ($data['lines']==3)
				{
					$place=array('A1','A2','A3','A4','A5','A6','A7','A8','B1','B2','B3','B4','B5','B6','B7','B8','C1','C2','C3','C4','C5','C6','C7','C8');
				}
				else
				{
					$place=array('A1','A2','A3','A4','A5','A6','A7','A8','B1','B2','B3','B4','B5','B6','B7','B8');
				}
				$count=count($place);				
	
				mysqli_query($db,"INSERT INTO `actions` SET `report`='',`device`=".(int)$id.",`data`='',`count`=".$count.",`action`='get_number',`time`=".time());
				$act_id=mysqli_insert_id($db);

				for ($i=0;$i<$count;$i++)
				{
					$qry="INSERT INTO `card2action` SET `device`=".(int)$id.",`action`=".$act_id.",`place`='".$place[$i]."'";
					mysqli_query($db,$qry);
				}
				$qry="UPDATE `devices` SET `status`='waiting' WHERE `id`=".(int)$id;
				mysqli_query($db,$qry);
			
				return(array('status'=>0,'message'=>'ok','action'=>$act_id));
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
function action_pool_create($id,$type,$data='') 
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
	$qry='SELECT c.*,d.`modems`,d.`data`,d.`model` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card` 
	INNER JOIN `devices` d ON d.id=c.`device` 
	WHERE p.`pool`='.(int)$id.' ORDER BY c.`device`,CHAR_LENGTH(c.`place`),c.`place`';
	if ($result = mysqli_query($db, $qry)) 
	{
		$ii=0;
		while ($row[$ii]=mysqli_fetch_assoc($result))
		{
			$ii++;
		}
		$actions_list=array();
		for ($k=0;$k<$ii;$k++) 
		{
			// Getting a row and place | Получение ряда и места
			$place=explode('-',$row[$k]['place']);

			if ($row[$k]['model']=='SR-Box-Bank' || $row[$k]['model']=='SR-Box-2-Bank' || $row[$k]['model']=='SR-Board'){$row[$k]['modems']='1,2,3,4,5,6,7,8';}
			$modems=explode(',',$row[$k]['modems']);
			$dt=unserialize($row[$k]['data']);
			if ($device!=$row[$k]['device'])
			{
				$qry="INSERT INTO `actions` SET `report`='',`device`=".$row[$k]['device'].",`status`='preparing',`action`='".$type."',`data`='".serialize($data)."',`pool_id`=".(int)$id.",`time`=".time();
				mysqli_query($db,$qry);
				$act_id_old=$act_id;
				$act_id=mysqli_insert_id($db);
				$actions_list[]=$act_id;
				$counter=0;
				$task++;
			}
			$device=$row[$k]['device'];
			if ($row[$k]['model']=='SR-Train')
			{
                                $next=1;
				for ($i=0;$i<count($modems);$i++)
				{
					if ($place[1]+8==$modems[$i] && $place[0]>2)
					{
						$counter++;
						$out=1;
						$r=$place[0]-3;

						if ($subResult = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' AND `row`='.$r)) 
						{
							if ($subRow = mysqli_fetch_assoc($subResult))
							{
								$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$modems[$i],',')."' WHERE `id`=".$subRow['id'];
								mysqli_query($db,$qry);
							}
							else
							{
								if ($r+3>$dt['rows'])
								{
									$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".$r.",`place`='".$modems[$i]."'";
									mysqli_query($db,$qry);
								}
								if ($subResult = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' AND `row`='.($r+3))) 
								{
									if ($subRow = mysqli_fetch_assoc($subResult))
									{
										$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.($modems[$i]-8),',')."' WHERE `id`=".$subRow['id'];
										mysqli_query($db,$qry);
									}
									else
									{
										$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".($r+3).",`place`='".($modems[$i]-8)."'";
										mysqli_query($db,$qry);
									}
								}
							}
						}
						$next=0;
						break;
					}
				}
				if ($next)
				{
					for ($i=0;$i<count($modems);$i++)
					{
						if ($place[1]==$modems[$i])
						{
							$counter++;
							$out=1;
							$r=$place[0];

							if ($subResult = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' AND `row`='.$r)) 
							{
								if ($subRow = mysqli_fetch_assoc($subResult))
								{
									$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$modems[$i],',')."' WHERE `id`=".$subRow['id'];
									mysqli_query($db,$qry);
								}
								else
								{
									$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".$r.",`place`='".$modems[$i]."'";
									mysqli_query($db,$qry);
								}
							}
							break;
						}
					}
				}
			}
			elseif ($row[$k]['model']=='SR-Box-Bank' || $row[$k]['model']=='SR-Board')
			{
				$out=1;
				$insert=1;
				$counter++;
				$rw=0;
				$pl=$row[$k]['place'][0];
				$qry='SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' ORDER BY `row`';
				if ($subResult = mysqli_query($db, $qry)) 
				{
					while ($subRow = mysqli_fetch_assoc($subResult))
					{
						if (strpos($subRow['place'],$pl)===false)
						{	
							$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$row[$k]['place'],',')."' WHERE `id`=".$subRow['id'];
							mysqli_query($db,$qry);
							$insert=0;
							break;
						}
						$rw++;
					}
				}
				if ($insert)
				{
					$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".$rw.",`place`='".$row[$k]['place']."'";
					mysqli_query($db,$qry);
				}
			}
			elseif ($row[$k]['model']=='SR-Box-2-Bank')
			{
				$out=1;
				$insert=1;
				$counter++;
				$pl=ord($row[$k]['place'][0])-64;
				$rw=substr($row[$k]['place'],1,100);
				$qry='SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id.' ORDER BY `row`';
				if ($subResult = mysqli_query($db, $qry)) 
				{
					while ($subRow = mysqli_fetch_assoc($subResult))
					{
						if (strpos($subRow['place'],$pl)===false)
						{	
							$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$pl,',')."' WHERE `id`=".$subRow['id'];
							mysqli_query($db,$qry);
							$insert=0;
							break;
						}
						$rw++;
					}
				}
				if ($insert)
				{
					$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=".$rw.",`place`='".$pl."'";
					mysqli_query($db,$qry);
				}
			}
			elseif ($row[$k]['model']=='SR-Box-8' || ($row[$k-1]['model']=='SR-Box-8' && $device!=$d))
			{
				$out=1;
				$insert=1;
				$counter++;
				$place=ord($row[$k]['place'])-65;
				$qry='SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id;
				if ($subResult = mysqli_query($db, $qry)) 
				{
					if ($subRow = mysqli_fetch_assoc($subResult))
					{
						$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$placeColumn,',')."' WHERE `id`=".$subRow['id'];
						mysqli_query($db,$qry);
						$insert=0;
					}
				}
				if ($insert)
				{
					$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=0,`place`='".trim($placeColumn,',')."'";
					mysqli_query($db,$qry);
				}
			}
			elseif ($row[$k]['model']=='SR-Box-2' || ($row[$k-1]['model']=='SR-Box-2' && $device!=$d))
			{
				$out=1;
				$insert=1;
				$counter++;
				$place=ord($row[$k]['place'])-65;
				$qry='SELECT * FROM `card2action` WHERE `device`='.$device.' AND `action`='.$act_id;
				if ($subResult = mysqli_query($db, $qry)) 
				{
					if ($subRow = mysqli_fetch_assoc($subResult))
					{
						$qry="UPDATE `card2action` SET `place`='".trim($subRow['place'].','.$placeColumn,',')."' WHERE `id`=".$subRow['id'];
						mysqli_query($db,$qry);
						$insert=0;
					}
				}
				if ($insert)
				{
					$qry="INSERT INTO `card2action` SET `device`=".$device.",`action`=".$act_id.",`row`=0,`place`='".trim($placeColumn,',')."'";
					mysqli_query($db,$qry);
				}
			}
			elseif ($row[$k]['model']=='SR-Nano-500')
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
			elseif ($row[$k]['model']=='SR-Organizer')
			{
				$out=1;
				$counter++;
				$l=$row[$k]['place'][0];
				$p=substr($row[$k]['place'],1,3);
				$qry="INSERT INTO `card2action` SET `device`=".$row[$k]['device'].",`action`=".$act_id.",`place`='".$l.$p."'";
				mysqli_query($db,$qry);
			}		
			elseif ($row[$k]['model']=='SR-Organizer-Smart')
			{
				$out=1;
				$counter++;
				$l=$row[$k]['place'][0];
				$p=substr($row[$k]['place'],1,3);
				$qry="INSERT INTO `card2action` SET `device`=".$row[$k]['device'].",`action`=".$act_id.",`place`='".$l.$p."'";
				mysqli_query($db,$qry);
			}		

			$qry="UPDATE `actions` SET `count`=".$counter." WHERE `id`=".$act_id;
			mysqli_query($db,$qry);
		}
		foreach ($actions_list AS $data)
		{
			$qry="UPDATE `actions` SET `status`='waiting' WHERE `id`=".$data;
			mysqli_query($db,$qry);
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
// Создание задачи для агрегатора
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
		if ($row['title']=='[create]')
		{
			$qry="UPDATE `devices` SET `title`='[init]' WHERE `id`=".$id;
			mysqli_query($db,$qry);
		}
		$out=1;
		$place=explode('-',$row['place']);
		$modems=explode(',',$row['modems']);
		$data=unserialize($row['data']);
		$count=1;
		$qry="INSERT INTO `actions` SET `report`='',`device`=".$row['id'].",`data`='',`action`='".$type."',`count`='".$count."',`time`=".time();
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

// Getting ICCID
// Получение ICCID
function get_iccid($dev=0,$model,$row='',$place='',$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db;

	if ($model=='SR-Train') // Модель
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
	else
	{
		$place=remove_zero($place);
	}

	for ($n=0;$n<3;$n++)
	{
		setlog('[get_iccid:'.$dev.'] Request ICCID','link_'.$dev);
		sr_command($dev,'modem>send:AT+CCID'); // Запрос ICCID
		$answer=sr_answer($dev,0,20,'AT+CCID');
		$iccid=str_replace('AT+CCID','',$answer);
		$iccid=str_replace('+CCID:','',$iccid);
		$iccid=str_replace('"','',$iccid);
		$iccid=str_replace('OK','',$iccid);
		$iccid=trim($iccid);
		setlog('[get_iccid:'.$dev.'] ICCID: '.$iccid,'link_'.$dev);
		if ($iccid)
		{
			// Saving the iccid | Сохраняем iccid
			$qry='SELECT `id` FROM `cards` WHERE `device`='.(int)$dev.' AND `place`="'.$place.'"';
			if ($result = mysqli_query($db, $qry))
			{	
				if ($resRow = mysqli_fetch_assoc($result))
				{
					$qry="UPDATE `cards` SET
					`iccid`='".$iccid."',
					`time`='".time()."'
					WHERE `id`=".$resRow['id'];
				}
				else
				{
					$qry="REPLACE INTO `cards` SET
					`iccid`='".$iccid."',
					`place`='".$place."',
					`time`='".time()."'";
				}
				mysqli_query($db,$qry);
			}
			return(1);
		}
	}
	return(0);
}

// Getting ICCID
// Получение ICCID
function get_iccid_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db;

	// Saving the iccid | Сохраняем iccid
	$qry='SELECT `id` FROM `cards` WHERE `device`='.(int)$dev.' AND `place`="'.$place.'"';
	if ($result = mysqli_query($db, $qry))
	{	
		if ($resRow = mysqli_fetch_assoc($result))
		{
			$qry="UPDATE `cards` SET
			`iccid`='".$iccid."',
			`time`='".time()."'
			WHERE `id`=".$resRow['id'];
		}
		else
		{
			$qry="REPLACE INTO `cards` SET
			`iccid`='".$iccid."',
			`place`='".$place."',
			`time`='".time()."'";
		}
		mysqli_query($db,$qry);
	}
	return(1);
}

// Getting a phone number
// Получение номера телефона
function get_number($dev=0,$model,$row='',$place='',$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device
//	$adata		Array with additional data from action	

	setlog($model.','.$row.','.$place.','.$adata.','.$operator.','.$roaming,'link_'.$dev);

	if (!$dev)
	{
		return;
	}

	global $db,$pdu;

	$status=0;

	sr_command($dev,'modem>send:AT+ICCID');
	setlog('[get_number:'.$dev.'] Getting the ICCID','link_'.$dev);
	$answer=sr_answer($dev,0,50,'+ICCID');
	if ($answer!='error:no answer')
	{
		$answer=str_replace('1:','',str_replace('2:','',$answer));
		$iccid=explode(':',str_replace('OK','',$answer));
		$iccid=trim($iccid[1]);
		setlog('[get_number:'.$dev.'] ICCID: '.$iccid,'link_'.$dev);
	}
	if (strpos($answer,'ERROR')!=false)
	{
		sr_command($dev,'modem>send:AT+CICCID');
		setlog('[get_number:'.$dev.'] Getting the CICCID','link_'.$dev);
		setlog('[get_number:'.$dev.'] !!! CSPN?','link_'.$dev);
		$answer=sr_answer($dev,0,50,'+CICCID');
		if ($answer!='error:no answer')
		{
			$answer=str_replace('1:','',str_replace('2:','',$answer));
			$iccid=explode(':',str_replace('OK','',$answer));
			$iccid=trim($iccid[1]);
			setlog('[get_number:'.$dev.'] ICCID: '.$iccid,'link_'.$dev);
		}
	}
	$iccid=explode("\n",$iccid);
	$iccid=$iccid[0];

	sr_command($dev,'modem>send:AT+CSPN?'); // Repeated request for the operator name | Повторный запрос названия оператора
	setlog('[get_number:'.$dev.'] Getting the name of the operator','link_'.$dev);
	$answer=sr_answer($dev,0,50,'+CSPN');
	if ($answer=='error:no answer')
	{
		sr_command($dev,'modem>send:AT+CSPN?'); // Repeated request for the operator name | Повторный запрос названия оператора
		$answer=sr_answer($dev,0,50,'+CSPN');
	}
	if ($answer && strpos($answer,'error:')===false)
	{
		preg_match('!"(.*)"!Uis', $answer, $test);
		$operator=$test[1];
	}
	if (!$operator)
	{
		sr_command($dev,'modem>send:AT+COPS?'); // Repeated request for the operator name | Повторный запрос названия оператора
		$answer=sr_answer($dev,0,50,'+COPS');
		if ($answer && strpos($answer,'error:')===false)
		{
			preg_match('!"(.*)"!Uis', $answer, $test);
			$operator=$test[1];
		}
		if (!$operator)
		{
			setlog('[get_number:'.$dev.'] The modem did not return a response to the operator\'s request!','link_'.$dev); // Модем не ответил на запрос оператора
			return($status);
		}
	}		
	if ($operator)	
	{
		// Getting rules for getting a number | Получение правил запроса номера
		if ($result = mysqli_query($db, "SELECT * FROM `operators` WHERE `name` LIKE '%;".$operator.";%' ORDER BY `name` DESC, id DESC LIMIT 1")) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$prefix=$resRow['prefix'];
				if ($roaming)
				{
					$prefix=$resRow['prefix_r'];
					$resRow['get_number']=$resRow['get_number_r'];
					$resRow['get_number_type']=$resRow['get_number_type_r'];
				}
				if (!$getNumber=$resRow['get_number'])
				{
					setlog('[get_number:'.$dev.'] There is no method for getting a number','link_'.$dev); // Нет методики получения номера
					return($status);
				}
				$getNumberType=$resRow['get_number_type'];
				setlog('[get_number:'.$dev.'] The modem is connected to the '.$operator.' network (ID:'.$resRow['id'].')','link_'.$dev); 
			}
			else
			{
				setlog('[get_number:'.$dev.'] '.$operator.' operator not found!','link_'.$dev); // Оператор не найден
				return($status);
			}
		}
	}
// Сохраняем оператора в пользовательской таблице
	if ($operator)
	{
		$qry="INSERT INTO `operators_uniq` SET
		`name`='".strtoupper($operator)."'";
		mysqli_query($db,$qry);
	}

	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема

	if ($getNumberType=='sms' && !$k)
	{
		sr_command($dev,'modem>send:AT+CMGDA=5'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
		$answer=sr_answer($dev,0,50,'AT+CMGD');
		if (strpos($answer,'OK')===false)
		{
			sr_command($dev,'modem>send:AT+CMGD=0,4'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
			sr_answer($dev,0,50,'AT+CMGD');
		}
		$k=1;
	}
	setlog('[get_number:'.$dev.'] Request a phone number','link_'.$dev);
	if (strpos($getNumber,'|'))
	{
		$gn=explode('|',$getNumber);
		sr_command($dev,'modem>send:AT+CUSD=1,"'.$gn[0].'",15'); // Запрос 1 часть
		sleep(10);
		sr_command($dev,'modem>send:AT+CUSD=1,"'.$gn[1].'",15'); // Запрос 2 часть
	}
	else
	{
		sr_command($dev,'modem>send:AT+CUSD=1,"'.$getNumber.'",15'); // Запрос номера телефона
	}

        $number='';
	for ($n=0;$n<3;$n++)
	{
		if ($getNumberType=='sms') // The phone number is returned in an SMS | Номер телефона возвращается в SMS
		{
			if ($model=='SR-Box-2' || $model=='SR-Box-2-Bank') // Модель
			{
				sr_command($dev,'modem>send:AT+CMGL=0');
				$answer=sr_answer($dev,0,40,'CMGL');
				$ans=explode("CMGL:",$answer);

				if (strpos(strtoupper($answer),'ERROR')===false)
				{
					foreach ($ans AS $d)
					{
						$d=explode('
',$d);
						$raw=$d[1];
						$answer=$pdu->pduToText($raw);
						$answer=$answer['message'];
						$answer=trim_number($answer);
        			        	preg_match('!([0-9]{10,13})!', $answer, $test);
						if ($number=trim($test[1]))
						{
							break(2);
						}
					}	
				}
			}
			elseif ($model=='SR-Train' || $model=='SR-Box-8' || $model=='SR-Box-Bank' || $model=='SR-Board') // Модель
			{
 				$answer=sr_answer($dev,0,40,'+CMTI: "');
                		preg_match('!CMTI: ".*",(.*)!is', $answer, $test);

				if (strpos($answer,'CMTI: "')!==false)
				{
			                preg_match('!CMTI: ".*",(.*)!is', $answer, $test);
					if ($sms=$test[1])
					{
						sr_command($dev,'modem>send:AT+CMGR='.$sms);
						$answer=str_replace('.','',sr_answer($dev,0,40,'CMGR:'));

						if (strpos(strtoupper($answer),'ERROR')===false)
						{
							preg_match('!CMGR:(.*)OK!Uis', $answer, $raw);
							$raw=explode("\n",$raw[1]);
							if (!$raw[1])
							{
								preg_match('!CMGR:(.*)!is', $answer, $raw);
								$raw=explode("\n",$raw[1]);
							}	
							$answer=$pdu->pduToText($raw[1]);
							$answer=$answer['message'];

							$answer=trim_number($answer);
		        			        preg_match('!([0-9]{10,13})!', $answer, $test);
							$number=trim($test[1]);
				
							break;
						}
					}
				}
			}
			else
			{
				sleep(10);
				$smsBuf=sr_command($dev,'modem>sms:4',30);
				if ($smsBuf!="NO RESPONSE" && $smsBuf!="1")
				{
					$data=explode('##',$smsBuf);
					for ($i=1;$i<count($data);$i++)
					{
						if ($data[$i])
						{
							$raw=explode("\n",$data[$i]);
							$sms=$pdu->pduToText($raw[1]);
							if (!$sms['userDataHeader'])
							{
								$answer=trim_number($sms['message']);
				        		        preg_match('!([0-9]{10,13})!', $answer, $test);
								$number=trim($test[1]);
								break 2;
							}
						}
					}
				}
			}	
			sr_command($dev,'modem>send:AT+CUSD=1,"'.$getNumber.'",15'); // Повторный запрос номера телефона
		}
		else
		{		
			$answer=sr_answer($dev,0,15,'CUSD:');
			$answer=$pdu->decode_ussd($answer);
			setlog('[get_number:'.$dev.'] Request a number: '.$answer,'link_'.$dev); // Запрос номера телефона
			$answer=trim_number($answer);
	                preg_match('!([0-9]{10,13})!', $answer, $test);
			if ($number=trim($test[1]))
			{
				break;
			}
		}
	}
	if ($number)
	{
		setlog('[get_number:'.$dev.'] Number: '.$number,'link_'.$dev); // Номер телефона не получен
		$status=1;
		sr_command($dev,'modem>send:AT+CMGDA=5'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
		sr_command($dev,'modem>send:AT+CMGD=0,4'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
	}
	else
	{
		setlog('[get_number:'.$dev.'] The phone number is not received!','link_'.$dev); // Номер телефона не получен
	}
	if ($status)
	{
		if ($prefix && strpos('!'.$number,'!'.$prefix)===false)
		{
			$number=$prefix.$number;
		}

		setlog('[get_number:'.$dev.'] Received phone number: '.$number.' Place: '.$place,'link_'.$dev);
	}

	if ($model=='SR-Train') // Модель
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
	elseif ($model=='SR-Organizer') // Модель
	{
		$place=$row.'-'.$place;
	}
	elseif ($model!='SR-Box-8' && $model!='SR-Box-2' ) // Модель
	{
		$place=remove_zero($place);
	}

	$qry="SELECT `id` FROM `cards` WHERE `place`='".$place."' AND `device`=".$dev;
	setlog('[get_number:'.$dev.'] Save number: '.$qry,'link_'.$dev);
	if ($result = mysqli_query($db, $qry)) 
	{
		$ic='';
		$nu='';
		if ($iccid){$ic="`iccid`='".$iccid."',";}
		if ($number){$nu="`number`='".$number."',";}
		if ($iccid || $number)
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$qry="UPDATE `cards` SET
				".$ic.$nu."				
				`roaming`='".$roaming."',
				`operator`='".strtoupper($operator)."',
				`time_number`='".time()."',
				`time`='".time()."'
				WHERE `id`=".$resRow['id'];
				mysqli_query($db,$qry);
			}
			else
			{
				// Saving the number | Сохраняем номер
				$qry="INSERT INTO `cards` SET
				".$ic.$nu."				
				`place`='".$place."',
				`roaming`='".$roaming."',
				`device`=".(int)$dev.",
				`operator`='".strtoupper($operator)."',
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);
			}
			setlog('[get_number:'.$dev.'] Save number: '.$qry,'link_'.$dev);
		}
	}
	else
	{
		setlog('[get_number:'.$dev.'] No Result','link_'.$dev);
	}
	return($status);
}

function get_number_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db;

	$status=0;

	if ($operator)	
	{
		$operator_short=explode(' ',$operator);
		// Getting rules for getting a number | Получение правил запроса номера
		if ($result = mysqli_query($db, "SELECT * FROM `operators` WHERE (`name` LIKE '%".$operator."%' OR `name` LIKE '%".$operator_short[0]."%') ORDER BY `name` DESC, id DESC LIMIT 1")) 
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$prefix=$resRow['prefix'];
				if ($roaming)
				{
					$prefix=$resRow['prefix_r'];
					$resRow['get_number']=$resRow['get_number_r'];
					$resRow['get_number_type']=$resRow['get_number_type_r'];
				}
				if (!$getNumber=$resRow['get_number'])
				{
					setlog('[get_number:'.$dev.'] There is no method for getting a number','link_'.$dev); // Нет методики получения номера
					return($status);
				}
				$getNumberType=$resRow['get_number_type'];
				$operatorName=$resRow['name'];
				setlog('[get_number:'.$dev.'] The modem is connected to the '.$operator.' network (ID:'.$resRow['id'].')','link_'.$dev); 
			}
			else
			{
				setlog('[get_number:'.$dev.'] '.$operator.' operator not found!','link_'.$dev); // Оператор не найден
				return($status);
			}
		}
	}
// Сохраняем оператора в пользовательской таблице
	if ($operator)
	{
		$qry="INSERT INTO `operators_uniq` SET
		`name`='".strtoupper($operator)."'";
		mysqli_query($db,$qry);
	}
		
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема
	setlog('[get_number:'.$dev.'] Request a phone number','link_'.$dev);
	if (strpos($getNumber,'|'))
	{
		$gn=explode('|',$getNumber);
		sr_command_smart($dev,'ussd:'.$gn[0].','.$modem); // Запрос 1 часть
		sleep(10);
		sr_command_smart($dev,'ussd:'.$gn[1].','.$modem); // Запрос 2 часть
	}
	else
	{
		sr_command_smart($dev,'ussd:'.$getNumber.','.$modem); // Запрос номера телефона
	}

        $number='';

	if ($getNumberType=='ussd')
	{
		for ($i=0;$i<60;$i++)
		{
			$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="ussd" AND `time`>'.(time()-10);
			if ($result_smart = mysqli_query($db, $qry)) 
			{
				while ($row_smart = mysqli_fetch_assoc($result_smart))
				{
					$answer=trim_number($row_smart['result']);
       				        preg_match('!([0-9]{10,13})!', $answer, $test);
					$number=trim($test[1]);
					break(2);
				}
			}
			br($dev);
			sleep(2);
		}
	}
	else
	{	
		for ($i=0;$i<30;$i++)
		{
			$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="sms" AND `time`>'.(time()-10);
			if ($result_smart = mysqli_query($db, $qry)) 
			{
				while ($row_smart = mysqli_fetch_assoc($result_smart))
				{
					$answer=trim_number($row_smart['result']);
       				        preg_match('!([0-9]{10,13})!', $answer, $test);
					$number=trim($test[1]);
					break(2);
				}
			}
			br($dev);
			sleep(2);
		}
	}
	if ($number)
	{
		setlog('[get_number:'.$dev.'] Number: '.$number,'link_'.$dev); // Номер телефона не получен
		$status=1;
	}
	else
	{
		setlog('[get_number:'.$dev.'] The phone number is not received!','link_'.$dev); // Номер телефона не получен
	}
	if ($status)
	{
		if ($prefix && strpos('!'.$number,'!'.$prefix)===false)
		{
			$number=$prefix.$number;
		}

		setlog('[get_number:'.$dev.'] Received phone number: '.$number.' Place: '.$place,'link_'.$dev);
	}

	$qry="SELECT `id` FROM `cards` WHERE `place`='".$place."' AND `device`=".$dev;
	setlog('[get_number:'.$dev.'] Save number: '.$qry,'link_'.$dev);
	if ($result = mysqli_query($db, $qry)) 
	{
		$ic='';
		$nu='';
		if ($iccid){$ic="`iccid`='".$iccid."',";}
		if ($number){$nu="`number`='".$number."',";}
		if ($iccid || $number)
		{
			if ($resRow = mysqli_fetch_assoc($result))
			{
				$qry="UPDATE `cards` SET
				".$ic.$nu."				
				`roaming`='".$roaming."',
				`operator`='".$operatorName."',
				`time_number`='".time()."',
				`time`='".time()."'
				WHERE `id`=".$resRow['id'];
				mysqli_query($db,$qry);
			}
			else
			{
				// Saving the number | Сохраняем номер
				$qry="INSERT INTO `cards` SET
				".$ic.$nu."				
				`place`='".$place."',
				`roaming`='".$roaming."',
				`device`=".(int)$dev.",
				`operator`='".$operatorName."',
				`time_number`='".time()."',
				`time`='".time()."'";
				mysqli_query($db,$qry);
			}
			setlog('[get_number:'.$dev.'] Save number: '.$qry,'link_'.$dev);
		}
	}
	else
	{
		setlog('[get_number:'.$dev.'] No Result','link_'.$dev);
	}
	return($status);
}


// Getting a balance
// Получение баланса
function get_balance($dev=0,$model,$row='',$place='',$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the panel
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db,$pdu;

	$status=0;

	if ($model=='SR-Train') // Модель
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

	elseif ($model=='SR-Nano')
	{
		$place=remove_zero($place);
	}

	$qry='SELECT c.*,o.`get_balance`,o.`get_balance_type` FROM `cards` c 
	INNER JOIN `operators` o ON o.`name` LIKE CONCAT("%;",c.`operator`,";%")
	WHERE c.`device`='.$dev.' AND c.`place`="'.$place.'" DESC LIMIT 1';
	setlog($qry,'link_'.$dev);

	// Getting balance request rules | Получение правил запроса баланса
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($resRow = mysqli_fetch_assoc($result))
		{
			$cardId=$resRow['id'];
			$cardPlace=$resRow['place'];
			$cardNumber=$resRow['number'];
			if ($roaming)
			{
				$resRow['get_balance']=$resRow['get_balance_r'];
				$resRow['get_balance_type']=$resRow['get_balance_type_r'];
			}
			if (!$getBalance=$resRow['get_balance'])
			{
				setlog('[get_balance:'.$dev.'] There is no method for getting a balance!','link_'.$dev); // Нет методики получения баланса
				return($status);
			}
			$getBalanceType=$resRow['get_balance_type'];
		}
		else
		{
			setlog('[get_balance:'.$dev.'] SIM card or Operator not found!','link_'.$dev); // Нет методики получения баланса
			return('SIM card or Operator not found!');
		}
	}
	sr_answer_clear($dev,1); // Очищаем буфер ответов модема
	for ($k=0;$k<2;$k++)
	{
		if ($getBalanceType=='sms' && !$k)
		{
			setlog('[get_balance:'.$dev.'] Deleting all SMS from the SIM card!','link_'.$dev);
			sr_command($dev,'modem>send:AT+CMGDA=5'); // Удаление всех СМС с СИМ-карты
			sr_command($dev,'modem>send:AT+CMGD=0,4'); // Deleting all SMS messages from SIM card | Удаление всех SMS с SIM-карты
		}
		setlog('[get_balance:'.$dev.'] Request a balance','link_'.$dev);

		if (strpos($getBalance,'|'))
		{
			$gb=explode('|',$getBalance);
			sr_command($dev,'modem>send:AT+CUSD=1,"'.$gb[0].'",15'); // Запрос 1 часть
			sleep(10);
			sr_command($dev,'modem>send:AT+CUSD=1,"'.$gb[1].'",15'); // Запрос 2 часть
		}
		else
		{
			sr_command($dev,'modem>send:AT+CUSD=1,"'.$getBalance.'",15'); // Запрос номера телефона
		}


		for ($n=0;$n<2;$n++)
		{
			if ($getBalanceType=='sms')
			{
				$answer=sr_answer($dev,0,20,'+CMTI: "SM"');
		                preg_match('!CMTI: ".*",(.*)!is', $answer, $test);
				if (!$test[1]){$test[1]=1;}
				if ($sms=$test[1])
				{
					setlog('[get_balance:'.$dev.'] Getting an SMS #'.$sms,'link_'.$dev); // Получение SMS с балансом
					sr_command($dev,'modem>send:AT+CMGR='.$sms);
					$answer=sr_answer($dev,0,40,'CMGR:');

					preg_match('!CMGR:(.*)OK!Uis', $answer, $raw);
					$raw=explode("\n",$raw[1]);
					if ($raw[1])
					{
						$answer=$pdu->pduToText($raw[1]);
						setlog('[get_balance:'.$dev.'] The balance: '.print_r($answer,1),'link_'.$dev); // Баланс не получен
						$h=explode(' ',trim($answer['userDataHeader']));

						while ((int)$h[count($h)-1]!=1)
						{
							$test[1]++;
							sr_command($dev,'modem>send:AT+CMGR='.($test[1]));
							$answer=sr_answer($dev,0,40,'CMGR:');
							preg_match('!CMGR:(.*)OK!Uis', $answer, $raw);
							$raw=explode("\n",$raw[1]);
							if ($raw[1])
							{
								$answer=$pdu->pduToText($raw[1]);
								$h=explode(' ',trim($answer['userDataHeader']));
							}
							else
							{
								break;
							}
						}									
						$answer=$answer['message'];

	                                        $balance=trim_balance($answer);
					}
					if ($balance || $balance=="0")
					{
						$status=1;
						sr_command($dev,'modem>send:AT+CMGD='.$sms); // Deleting text SMS with a blanace || Удаление СМС с балансом
						break(2);
					}
					else
					{
						setlog('[get_balance:'.$dev.'] The balance is not received!','link_'.$dev); // Баланс не получен
					}
				}
				else
				{
					setlog('[get_balance:'.$dev.'] SMS not received!','link_'.$dev); // SMS не получена
				}
			}
			else
			{		
				$answer=sr_answer($dev,0,15,'+CUSD: 0');
				$test=$pdu->decode_ussd($answer);
				$balance=trim_balance($test);
				if ($balance || $balance=="0")
				{
					$status=1;
					break(2);
				}
			}
		}
	}
	if ($status)
	{
		setlog('[get_balance:'.$dev.'] Received balance: '.$balance,'link_'.$dev);
		$qry="UPDATE `cards` SET
		`last_balance`=`balance`,
		`time_last_balance`=`time_balance`,
		`balance`='".$balance."',
		`time_balance`='".time()."',
		`time`='".time()."'
		WHERE `id`='".$cardId."'";

		mysqli_query($db,$qry);
	}
	return($status);
}

function get_balance_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{
//	$dev		Device ID
//	$row	        Panel row for positioning 1 modem line
//	$place	        Modem position relative to the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db;

	$status=0;

	$qry="SELECT c.*,o.`get_balance`,o.`get_balance_type` FROM `cards` c INNER JOIN `operators` o ON o.`name`=c.`operator` WHERE c.`device`='".$dev."' AND c.`place`='".$place."' DESC LIMIT 1";

	// Getting balance request rules | Получение правил запроса баланса
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($resRow = mysqli_fetch_assoc($result))
		{
			$cardId=$resRow['id'];
			$cardPlace=$resRow['place'];
			$cardNumber=$resRow['number'];
			if ($roaming)
			{
				$resRow['get_balance']=$resRow['get_balance_r'];
				$resRow['get_balance_type']=$resRow['get_balance_type_r'];
			}
			if (!$getBalance=$resRow['get_balance'])
			{
				setlog('[get_balance:'.$dev.'] There is no method for getting a balance!','link_'.$dev); // Нет методики получения баланса
				return($status);
			}
			$getBalanceType=$resRow['get_balance_type'];
		}
		else
		{
			setlog('[get_balance:'.$dev.'] SIM card or Operator not found!','link_'.$dev); // Нет методики получения баланса
			return('SIM card or Operator not found!');
		}
	}

	setlog('[get_balance:'.$dev.'] Request a balance','link_'.$dev);
	if (strpos($getBalance,'|'))
	{
		$gb=explode('|',$getBalance);
		sr_command_smart($dev,'ussd:'.$gb[0].','.$modem); // Запрос 1 часть
		sleep(10);
		sr_command_smart($dev,'ussd:'.$gb[1].','.$modem); // Запрос 1 часть
	}
	else
	{
		sr_command_smart($dev,'ussd:'.$getBalance.','.$modem); // Запрос баланса
	}

        $number='';

	if ($getBalanceType=='ussd')
	{
		for ($i=0;$i<30;$i++)
		{
			$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="ussd" AND `time`>'.(time()-10);
			if ($result_smart = mysqli_query($db, $qry)) 
			{
				while ($row_smart = mysqli_fetch_assoc($result_smart))
				{
					$balance=trim_balance($row_smart['result']);
					if ($balance || $balance=="0")
					{
						$status=1;
						break(2);
					}
				}
			}
			br($dev);
			sleep(2);
		}
	}
	else
	{	
		for ($i=0;$i<30;$i++)
		{
			$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="sms" AND `time`>'.(time()-10);
			if ($result_smart = mysqli_query($db, $qry)) 
			{
				while ($row_smart = mysqli_fetch_assoc($result_smart))
				{
					$balance=trim_balance($row_smart['result']);
					if ($balance || $balance=="0")
					{
						$status=1;
						break(2);
					}
				}
			}
			br($dev);
			sleep(2);
		}
	}
	if ($balance || $balance=="0")
	{
		setlog('[get_balance:'.$dev.'] Balance: '.$balance,'link_'.$dev); // Баланс получен
		$status=1;
	}
	else
	{
		setlog('[get_balance:'.$dev.'] The balance is not received!','link_'.$dev); // Баланс не получен
	}
	if ($status)
	{
		setlog('[get_balance:'.$dev.'] Received balance: '.$balance,'link_'.$dev);
		$qry="UPDATE `cards` SET
		`last_balance`=`balance`,
		`time_last_balance`=`time_balance`,
		`balance`='".$balance."',
		`time_balance`='".time()."',
		`time`='".time()."'
		WHERE `id`='".$cardId."'";

		mysqli_query($db,$qry);
	}
	return($status);
}

function get_sms($dev=0,$model,$curRow='',$place='',$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	global $db, $pdu;

	$sms=array();
	$out=0;
	$com='';

	if ($place>=1 && $place<=16)
	{
		setlog('[get_sms:'.$dev.'] Select modem: '.$place,'link_'.$dev);
		$com='modem>select:'.$place.'&&';
	}
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема
	for ($n=1;$n<50;$n++) // Processing 20 SMS memory cells | Обрабатываем 20 ячеек памяти SMS
	{
		for ($k=0;$k<2;$k++)
		{
			sr_command($dev,$com.'modem>send:AT+CMGR='.$n);
			$com="";
			$answer=sr_answer($dev,0,30,'CMGR');
	                preg_match('!AT\+CMGR.{0,200}(OK|ERROR)!s', $answer, $test);
			setlog('[get_sms:'.$dev.'] SMS #'.$n.' received: '.$test[1],'link_'.$dev);
			if (strpos($answer,'error:')!==false && $k>0)
			{
				return(0);
			}
			elseif (strpos($answer,'error:')!==false)
			{
				setlog('[get_sms:'.$dev.'] Repeated request #1: '.sr_answer($dev,0),'link_'.$dev); // Повторный запрос #1
				setlog('[get_sms:'.$dev.'] Repeated request #2: '.sr_answer($dev,0),'link_'.$dev); // Повторный запрос #2
			}
			elseif (strpos($answer,'error:')===false) 
			{
				break;
			}
		}
		if ($test[1] && strlen($answer)<50)
		{
			if ($test[1]=='ERROR' && $n>1){$test[1]='OK';}
			setlog('[get_sms:'.$dev.'] Completion with the status: '.$test[1],'link_'.$dev); // Завершение
			$out=1;
			break;
		}
		elseif (strlen($answer)>30)
		{
			preg_match('!CMGR:(.*)OK!Uis', $answer, $raw);
			$raw=explode("\n",$raw[1]);
			setlog('[get_sms:'.$dev.'] SMS: '.trim($raw[1]),'link_'.$dev); // Подготовка SMS
			$sms[]=$pdu->pduToText($raw[1]);
			sr_command($dev,'modem>send:AT+CMGD='.$n,10);
		}
	}
	if (count($sms))
	{
		if ($model=='SR-Train') // Модель
		{
			if ($place>8)
			{
				$place=($curRow+3).'-'.($place-8);
			} 
			else 
			{
				$place=$curRow.'-'.$place;
			}
		}
		else
		{
			$place=remove_zero($place);
		}

		// Getting a SIM card number | Получение номера СИМ-карты
		if ($result = mysqli_query($db, "SELECT * FROM `cards` WHERE `place`='".$place."'")) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				for ($i=0;$i<count($sms);$i++)
				{
					$update=0;
					// Saving to the database | Сохранение в БД
					sms_save($sms[$i]['userDataHeader'],$row['number'],$row['email'],'',$sms[$i]['number'],$sms[$i]['unixTimeStamp'],$sms[$i]['message']);
					setlog('[get_sms:'.$dev.'] SMS saved','link_'.$dev);
				}
			}
			else
			{
				setlog('[get_sms:'.$dev.'] The SIM card phone number is not received, the SMS is not saved!','link_'.$dev); // Номер SIM-карты не получен, SMS не сохранена
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

function get_sms_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		return;
	}

	sleep(60); // Ждем 60 секунд
	return(1);
}


// Outgoing call from the specified modem
// Осуществление вызова с указанного модема
function do_call($dev=0,$model,$curRow='',$place='',$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		$out=array();
		$out['options']=auto_field('Номер телефона (+7...)','f1',30).auto_field('Ожидание (сек.)','f2','number','',5);
		$out['count']=2;
		return($out);
	}

	global $db;
	$sms=array();
	$com='';

	if ($place>=1 && $place<=16)
	{
		setlog('[get_sms:'.$dev.'] Select modem: '.$place,'link_'.$dev);
		$com='modem>select:'.$place.'&&';
	}
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема
	setlog('[put_call:'.$dev.'] Calling '.$adata[0].'...','link_'.$dev);
	sr_command($dev,$com.'modem>send:ATD'.$adata[0].';');
	$answer=sr_answer($dev,0,30,'ATD');
	if (strpos($answer,'ERROR')!==false)
	{
		setlog('[put_call:'.$dev.'] Error!','link_'.$dev);
		return(0);
	}
	elseif (strpos($answer,'OK')!==false)
	{
		setlog('[put_call:'.$dev.'] Ok!','link_'.$dev);
		$timer=time()+$adata[1];
		setlog('[put_call:'.$dev.'] Time:'.($timer-time()),'link_'.$dev);
		while ($timer>time())
		{
			if (!$GLOBALS['set_data']['no_carrier_ignore'])
			{
				$answer=sr_answer($dev,0,3,'NO CARRIER');
				setlog('[put_call:'.$dev.'] Answer:'.$answer.' Time:'.($timer-time()),'link_'.$dev);
				if ($answer=='NO CARRIER')
				{
					setlog('[put_call:'.$dev.'] NO CARRIER','link_'.$dev);
					break;
				}
			}
			else
			{
				sleep(3);
			}
		}
		if ($timer<=time())
		{
			sr_command($dev,'ATH0');
			$answer=sr_answer($dev,0,20,'ATH0');
		}
		return(1);
	}
	return(0);
}

// Outgoing call from the specified modem
// Осуществление вызова с указанного модема
function do_call_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		$out=array();
		$out['options']=auto_field('Номер телефона (+7...)','f1',30).auto_field('Ожидание (сек.)','f2','number','',5);
		$out['count']=2;
		return($out);
	}

	global $db;
	sr_answer_clear($dev,1); // Clearing the response buffer of the modem | Очистка буфера ответов модема
	setlog('[put_call:'.$dev.'] Call '.$adata[0],'link_'.$dev);

	mysqli_query($db, 'DELETE FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="calling"');
	sr_command_smart($dev,'call:'.$adata[0].','.$modem);

// Ждем событие
	$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="calling"';
	$status=0;
	for ($i=0;$i<20;$i++)
	{
		if ($result_smart = mysqli_query($db, $qry)) 
		{
			if ($row_smart = mysqli_fetch_assoc($result_smart))
			{
				setlog('[put_call:'.$dev.'] Calling...','link_'.$dev);
				$status=1;
				break;
			}
		}
		sleep(2);
	}

	mysqli_query($db, 'DELETE FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="no_carrier"');
	$qry='SELECT `result` FROM `devices_events` WHERE `device_id`='.$dev.' AND `dev`="modem'.$modem.'" AND `event`="no_carrier"';
	while ($timer>time())
	{
		if ($result_smart = mysqli_query($db, $qry)) 
		{
			if ($row_smart = mysqli_fetch_assoc($result_smart))
			{
				setlog('[put_call:'.$dev.'] NO CARRIER','link_'.$dev);
				break;
			}
		}
		sleep(2);
	}
	if ($timer<=time())
	{
		sr_command_smart($dev,'command:hangup,'.$modem);
	}

	return($status);
}

function send_sms($dev=0,$model,$curRow='',$place='',$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		$out=array();
		$out['options']=auto_field('Номера телефонов в столбик (+7...)','f1','minitxt').auto_field('Через сколько номеров менять СИМ-карту','f2','number','',1).auto_field('Текст SMS','f3',400);
		$out['count']=3;
		return($out);
	}

	global $db;
	$sms=array();
	$com='';

	$ph=explode('
',$adata[0]);
	$phone=$ph[(int)$adata['line']];		
	if (!$phone)
	{
		$phone=$ph[0];		
		$adata['line']=0;
	}
	else
	{
		$adata['line']++;
	}
	while(1)
	{
		$counter++;
		if ($adata[1]<$counter){break;}	
		sr_command($dev,$com.'sms>send:'.$phone.';'.$adata[2]);
		sleep(10);
	}
	$qry="UPDATE `actions` SET `data`='".serialize($adata)."' WHERE `id`=".$GLOBALS['taskRow']['id'];
	mysqli_query($db,$qry);	

	return(1);
}

function send_sms_smart($dev=0,$model,$modem,$place,$iccid,$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		$out=array();
		$out['options']=auto_field('Номера телефонов в столбик (+7...)','f1','minitxt').auto_field('Через сколько номеров менять СИМ-карту','f2','number','',1).auto_field('Текст SMS','f3',400);
		$out['count']=2;
		return($out);
	}

	global $db;
	$sms=array();
	$com='';

	if ($place>=1 && $place<=16)
	{
		setlog('[get_sms:'.$dev.'] Select modem: '.$place,'link_'.$dev);
		$com='modem>select:'.$place.'&&';
	}
	$p=$adata[0];
	$GLOBALS['adata']='xxx';
	return(0);
}

function actionReport($id,$report) // Добавляем отчет об ошибках в action
{
	global $db;
	$qry='UPDATE `actions` SET `report`=`report`+"\n"+"'.$report.'" WHERE `id`='.$id;
	mysqli_query($db,$qry);
}

function autoStop() // Если аппарат простаивает больше минуты - выключаем включенные акции и онлайн
{
	global $db;
	if ($result = mysqli_query($db, 'SELECT * FROM `flags` WHERE `name`="request" OR `name`="answer" OR `name`="stop" OR `name`="cron" ORDER BY `device`,`name`'))
	{
		$dev=0;
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($dev && $dev!=$row['device'])
			{
				if ($answer+30>time() && $request && $request+60<time())
				{
					if ($stop!=1 && $cron && $cron+60<time())
					{
						if ($result2 = mysqli_query($db, 'SELECT `id` FROM `actions` WHERE `device`='.$dev.' AND `status`="inprocess"'))
						while ($row2 = mysqli_fetch_assoc($result2))
						{
							action_close($row2['id']);
							mysqli_query($db, "DELETE FROM `link_incoming` WHERE `device`=".$dev.";DELETE FROM `actions` WHERE `device`=".$dev.";DELETE FROM `card2action` WHERE `device`=".$dev.";DELETE FROM `modems` WHERE `device`=".$dev.";DELETE FROM `flags` WHERE `name`<>'request' AND `device`=".$dev);
						}
					}
					else
					{
						flagDelete($dev,'stop');
					}
				}
				$answer=0;
				$request=0;
				$cron=0;
				$stop=0;
			
			} 
			$dev=$row['device'];
			if ($row['name']=='answer'){$answer=$row['time'];}
			if ($row['name']=='request'){$request=$row['time'];}
			if ($row['name']=='cron'){$cron=$row['time'];}
			if ($row['name']=='stop'){$stop=$row['value'];}
		}
	}
}

function sms_monitor()
{
	global $db;
	$qry='SELECT s.*,c.`email` FROM `sms_incoming` s 
	LEFT JOIN `cards` c ON c.`number`=s.`number`
	WHERE s.`header`<>"" ORDER BY s.`header`';
	if ($result = mysqli_query($db, $qry))
	{
		$next=0;
		$header=array();
		$h='';
		while ($row = mysqli_fetch_assoc($result))
		{
			$head=explode(' ',$row['header']);
			$code=$head[0].$head[1].$head[2].$head[3];
			if ($h && $h!=$code)
			{
				sms_merge($header);
				$header=array();
			}
			$h=$code;
			$total=$head;
			$total=hexdec($total[4]);
			$part=$head;
			$part=hexdec($part[5]);
			$header['id'][$part]=$row['id'];
			$header['header'][$part]=$row['header'];
			$header['number'][$part]=$row['number'];
			$header['email'][$part]=$row['email'];
			$header['sender'][$part]=$row['sender'];
			$header['time'][$part]=$row['time'];
			$header['total'][$part]=$total;
			$header['txt'][$part]=$row['txt'];
			$header['modified'][$part]=$row['modified'];
		}
		if (count($header))
		{
			sms_merge($header);
		}
	}
}

function sms_merge($header)
{
	global $db;
	$c=count($header['id']);
	$t=$header['total'][1];
	foreach ($header['modified'] AS $data)
	{
		$modified=$data;
		break;
	}
	if (!$t)
	{
		foreach ($header['total'] AS $data)
		{
			if ($data){$t=$data; break;}
		}
	}	
	if ($c==$t || $modified+60<time()) // Если 30 секунд прошло, а SMS полностью не получена - склеиваем что есть
	{
		for ($i=0;$i<$t;$i++)
		{
			if (!$a=$header['txt'][$i+1])
			{
				$a=' ... ';
			}
			if (!$id)
			{
				$id=$header['id'][$i+1];
				$number=$header['number'][$i+1];
				$email=$header['email'][$i+1];
				$sender=$header['sender'][$i+1];
				$time=$header['time'][$i+1];
			}
			$sms.=$a;
			if ($header['id'][$i+1] && $id!=$header['id'][$i+1])
			{
				$qry='DELETE FROM `sms_incoming` WHERE id='.$header['id'][$i+1];
				mysqli_query($db,$qry);
			}
		}
		$qry='UPDATE `sms_incoming` SET `txt`="'.mysqli_real_escape_string($db,trim($sms)).'",`header`="",`done`=1 WHERE id='.$id;
		mysqli_query($db,$qry);
		sms_notification($number,$email,$sender,$time,trim($sms));
	}
// Удаляем старые SMS
	$qry="DELETE FROM `sms_incoming` WHERE `header`<>'' AND `done`=0 AND `time`<".(time()-86400*2);
	mysqli_query($db,$qry);

	pool_clear(); // Удаляем старые Пулы
	flag_clear(); // Удаляем старые Пулы
}

function sms_save($header,$number,$email,$place,$sender,$time,$txt,$card_id=0)
{
	global $db;

	if (trim($header))
	{
		$qry="`header`='".trim($header)."',";
	}
	else
	{
		$qry="`done`=1,";
	}
	if ($card_id)
	{
		$qry.="`card_id`='".$card_id."',";
	}
	if ($place)
	{
		$qry.="`place`='".$place."',";
	}
	$qry="INSERT INTO `sms_incoming` SET
	".$qry."
	`number`='".$number."',
	`sender`='".$sender."',
	`time`=".$time.",
	`modified`=".time().",
	`txt`='".mysqli_real_escape_string($db,$txt)."'";
	mysqli_query($db,$qry);
	if (!trim($header))
	{
		sms_notification($number,$email,$sender,$time,$txt);
	}
}

function log_clear()
{
	global $db;
	mysqli_query($db, 'DELETE FROM `link_incoming` WHERE unix_timestamp(time)+300<'.time());
}

// Adding the name of the SIM card
// Добавление названия СИМ-карты
function set_title($dev=0,$curRow='',$place='',$adata='',$operator='',$roaming=0)
{

//	$dev		Device ID
//	$curRow	        Panel row for positioning 1 modem line
//	$place		Modem position in the device
//	$adata		Array with additional data from action	

	if (!$dev)
	{
		$out=array();
		$out['options']=auto_field('Имя карты','f1',32);
		$out['count']=1;
		$out['save']='title';
		return($out);
	}

	global $db;
}

function sms_out($txt)
{
	$txt=str_replace("\n","<br>",$txt);
	$txt1=preg_replace('!([0-9-]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',str_replace("\n","<br>",$txt));
	$txt2=str_replace('http://https://','https://',str_replace('http://http://','http://',preg_replace("/(([a-z]+:\/\/)?(?:[a-zа-я0-9@:_-]+\.)+[a-zа-я0-9]{2,4}(?(2)|\/).*?)([-.,:]?(?:\\s|\$))/is",'<a href="http://$1" target="_blank">$1</a>$3', $txt)));
	if ($txt2!=$txt){return($txt2);}
	return($txt1);
}

function press($dev,$errors=0)
{
	global $db;
	$qry="UPDATE `devices` SET
	`press`=`press`+1,
	`errors`=`errors`+".(int)$errors."
	WHERE `id`=".(int)$dev;
	mysqli_query($db,$qry);
}

function pressPlace($dev,$place,$error=0)
{
	global $db;
	$place=remove_zero($place);
	$qry="INSERT INTO `devices_press` SET
	`time`=".time().",
	`place`='".$place."',
	`device`='".$dev."',
	`error`=".(int)$error;
	mysqli_query($db,$qry);
}

function getSerial($dev,$time)
{
	global $db;
	if ($time+3600<time())
	{
		$answer=sr_command($dev,'dev:serial',30); 
		if (strpos($answer,'error')===false)
		{
			$qry="UPDATE `devices` SET
			`serial`='".$answer."',
			`serial_time`=".time()."
			WHERE `id`=".(int)$dev;
			mysqli_query($db,$qry);
		}
	}
}

function getOnline($numbers=array()) // Получение номеров которые Online
{
	global $db;
	$places=array();
	if ($result = mysqli_query($db, 'SELECT d.*,m.modems FROM `devices` d 
	LEFT JOIN `modems` m ON m.device=d.id'));            
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$m=unserialize($row['modems']);
			if (strpos($row['model'],'Nano')!==false)
			{
				if ($m[0])
				{
					$places[]='(`device`='.$row['id'].' AND `place`="'.$m[0].'")';
				}
			}
			elseif (strpos($row['model'],'Train')!==false)
			{
				foreach ($m AS $mk => $md)
				{
					$places[]='(`device`='.$row['id'].' AND `place`="'.$md[0].'-'.$mk.'")';
				}
			}
			elseif (strpos($row['model'],'Box')!==false || strpos($row['model'],'Organizer')!==false)
			{
				foreach ($m AS $mk => $md)
				{
					$places[]='(`device`='.$row['id'].' AND `place`="'.chr($mk+64).$md[0].'")';
				}
			}
		}
	}
	$out=array();
	if (count($places))
	{
		if (count($numbers))
		{
			$numbers='`number` IN ("'.implode('","',$numbers).'") AND ';
		}
		else
		{
			$numbers='';
		}
		$qry='SELECT * FROM `cards` c 
		WHERE '.$numbers.' ('.implode(' OR ',$places).')';
		if ($result = mysqli_query($db, $qry));            
		{       	
			while ($row = mysqli_fetch_assoc($result))
			{
				$out[]=$row['number'];						
			}
		}
	}
	return($out);
}

?>