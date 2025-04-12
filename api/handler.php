<?
$cron=1;
$_SERVER['DOCUMENT_ROOT']='';
$root="[path]";
include($root.'_func.php');

if (!$_POST){$_POST=$_GET;}

$_POST['number']=str_replace('+','',trim($_POST['number']));

if (!$_POST['pool_key'])
{
	echo 'BAD_KEY';
	exit();
}
else
{
	if ($result = mysqli_query($db, "SELECT `id` FROM `pools` WHERE `key`='".$_POST['pool_key']."'")) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$pool_id=$row['id'];
		}
		else
		{
			echo 'BAD_KEY';
			exit();
		}
	}
}

if ($_POST['phone_exception'] && !(int)$_POST['phone_exception'])
{
	echo 'WRONG_EXCEPTION_PHONE';
	exit();
}

$where='';

if ($_POST['operator'])
{
	$o=explode(',',$_POST['operator']);
	$operators="";
	foreach ($o AS $data)
	{
		$operators.="'".trim($data)."',";
	}
	$where=" AND c.`operator` IN(".trim($operators,',').")";
}
if ($_POST['deviceId'])
{
	$d=explode(',',$_POST['deviceId']);
	$devices="";
	foreach ($d AS $data)
	{
		$devices.=(int)trim($data).",";
	}
	$where=" AND c.`device` IN(".trim($devices,',').")";
}

$channels=array(
'SR-Nano-500'=>1,
'SR-Nano-1000'=>1,
'SR-Train'=>16,
'SR-Organizer'=>2,
'SR-Box-8'=>8,
'SR-Box-Bank'=>8,
);

if ($_POST['action']=='getAgregators')
{
	$devices=array();
	$qry="SELECT c.`number`, c.`device`,c.`operator`,d.`model` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	WHERE p.`pool`='".$pool_id."' ORDER BY c.`device`,c.`operator`";

	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$devices[$row['device']]['id']=$row['device'];
			$devices[$row['device']]['model']=$row['model'];
			$devices[$row['device']]['numbers']++;
			$devices[$row['device']]['channels']=$channels[$row['model']];
		}
	}
	sort($devices);
	echo json_encode($devices);
	exit();
}

else if ($_POST['action']=='getOperators')
{
	$operators=array();
	$qry="SELECT c.`number`, c.`device`,c.`operator` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	WHERE p.`pool`='".$pool_id."' ORDER BY c.`operator`";

	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$operators[$row['operator']]['name']=$row['operator'];
			$operators[$row['operator']]['numbers']++;
		}
	}
	sort($operators);
	echo json_encode($operators);
	exit();
}

else if ($_POST['action']=='getPoolStatus')
{
	$status=getPoolStatus();
	echo json_encode($status['total']);
	exit();
}

elseif ($_POST['action']=='poolRestart')
{
// Выключаем устройства пула
	$qry="SELECT c.`device` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`
	WHERE p.`pool`='".$pool_id."'";
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".$row['device']);
			if (flagGet($row['device'],'cron'))
			{
				if (!flagGet($row['device'],'stop'))
				{
					flagSet($row['device'],'stop');
				}
				flagDelete($row['device'],'cron');
			}
			elseif (flagGet($row['device'],'stop',1)<time()-60)
			{
				flagDelete($row['device'],'stop');
			}
		}
	}
	$qry="UPDATE `card2pool` SET `done`=0 WHERE `pool`='".$pool_id."'";
	mysqli_query($db, $qry);
	echo 'DONE';
	exit();
}

else if ($_POST['action']=='getNumbers')
{
	$numbers=array();
	$qry="SELECT c.`number`,c.`place`,c.`device` AS `device_id`,d.`model`,c.`operator`,m.`row`,m.`modems` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."' ORDER BY c.`device`,CHAR_LENGTH(c.`place`),c.`place`";
	if ($result = mysqli_query($db, $qry)) 
	{
		$dev=0;
		$device=array();
		$dev_numbers=array();
		$max=0;
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['model']=='SR-Train')
			{
				$place=explode('-',$row['place']);
				if ($row['row']==$place[0] || $row['row']+3==$place[0])
				{			
					$row['position']='ONLINE';
				}
				else
				{
					$row['position']='HOLD';
				}
			}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000')
			{
				$m=unserialize($row['modems']);
				if ($m[0]==$row['place'])
				{			
					$row['position']='ONLINE';
				}
				else
				{
					$row['position']='HOLD';
				}
			}
			elseif ($row['model']=='SR-Box-Bank' || $row['model']=='SR-Box-Bank')
			{
				$m=unserialize($row['modems']);
				$a=explode('-',$row['place']);
				if ($m[$a[1]][0]==$a[0])
				{			
					$row['position']='ONLINE';
				}
				else
				{
					$row['position']='HOLD';
				}
			}
			unset($row['row']);
			unset($row['modems']);

			if ($_POST['position']!='ONLINE' || $row['position']=='ONLINE')
			{
				$row['number']=str_replace($_POST['phone_exception'],'',$row['number']);
				$numbers[]=$row;
			}
		}
	}
	if (!count($numbers))
	{
		echo 'NO NUMBERS';
	}
	else
	{	
		echo json_encode($numbers);
	}
	exit();
}

else if ($_POST['action']=='openNumber')
{
	if ($_POST['number'])
	{
		$where.=" AND c.`number`='".(int)trim($_POST['number'])."'";
	}

	$qry="SELECT c.`number`, c.`place`,c.`device`,c.`user_id`,d.`model`,m.`device` AS `busy`,m.`modems` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."'";
	if (!$_POST['mode']=='repeat'){$qry.='';}
	$qry.=' LIMIT 1';
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
// Подключаем номер
			if ($row['busy'] && ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000'))
			{
				echo 'BUSY';
			}
			else
			{			
				$dev_row=0;
				if ($row['model']=='SR-Train')
				{
					$p=explode('-',$row['place']);
					$dev_row=$_GET['row']=$p[0];
				}
				else if ($row['model']=='SR-Organizer')
				{
					$p=explode('-',$row['place']);
					if ($p[0]=="1")
					{
						$_GET['row']=$p[1].'-1';
					}
					elseif ($p[0]=="2")
					{
						$_GET['row']='1-'.$p[1];
					}
				}
				else if ($row['model']=='SR-Box-Bank')
				{
					$nm=array(1,1,1,1,1,1,1,1);
					$nn=explode('-',$row['place']);
					if ($row['modems'])
					{
						$modems=unserialize($row['modems']);
						for ($i=1;$i<9;$i++)
						{
							$nm[$i-1]=$modems[$i][0];	
						}
					}
					$nm[$nn[1]-1]=$nn[0];
					$_GET['row']=implode(',',$nm);
		
//					$p=explode('-',$row['place']);
//					$_GET['row']=$p[0];
				}
				else if ($status['model']=='SR-Box-8' || $status['model']=='SR-Box-2')
				{
					$_GET['row']=0;
				}
				else
				{
					$_GET['row']=$row['place'];
				}

				$_GET['device']=$row['device'];
				$card_num=$row['number'];
				$out=str_replace($_POST['phone_exception'],'',$row['number']);

				include($root.'ajax_online_create.php');
// Записываем задействованные номера
				$qry="UPDATE `modems` SET `numbers`='".$out."',`pool_id`=".$pool_id.",`row`=".$dev_row." WHERE `device`=".$_GET['device'];
				mysqli_query($db, $qry);
// Помечаем как отработанный
				$qry="UPDATE `card2pool` SET `done`=1 WHERE `pool`='".$pool_id."' AND `card`='".$card_num."'";
				mysqli_query($db, $qry);
			
				echo str_replace($_POST['phone_exception'],'',$out);
			}
		}       	
		else
		{
			echo 'NO_NUMBERS';
		}
	}
	exit();
}

else if ($_POST['action']=='openNumbers')
{
	if ($_POST['number'])
	{
		$o=explode(',',$_POST['number']);
		$numbers="";
		foreach ($o AS $data)
		{
			$numbers.="'".trim($data)."',";
		}
		$where.=" AND c.`number` IN(".trim($numbers,',').")";
	}

	$status=getPoolStatus();
	if (count($status['numbers']))
	{
		$dev_row=0;
		if ($_POST['channels']!='max' && count($status['numbers'])<$_GET['channels'])
		{
			echo 'NO CHANNELS';
			exit();
		}
		$dev_row=0;
		if ($status['model']=='SR-Train')
		{
			$dev_row=$_GET['row']=$status['places'][0][0];
		}
		else if ($status['model']=='SR-Box-Bank')
		{
			$nm=array(1,1,1,1,1,1,1,1);
			foreach($status['places'] AS $data)
			{
				$nn=explode('-',$data);
				if ($status['modems'])
				{
					$modems=unserialize($status['modems']);
					for ($i=1;$i<9;$i++)
					{
						$nm[$i-1]=$modems[$i][0];	
					}
				}
				$nm[$nn[1]-1]=$nn[0];
			}
			$_GET['row']=implode(',',$nm);
		}
		else if ($status['model']=='SR-Box-8' || $status['model']=='SR-Box-2')
		{
			$_GET['row']=0;
		}
		else if ($status['model']=='SR-Organizer')
		{
			$_GET['row']=$status['places'][0];
		}
		else
		{
			$_GET['row']=$status['places'][0];
		}

		$numbers=array();
		foreach ($status['numbers'] AS $data)
		{
			$numbers[]=str_replace($_POST['phone_exception'],'',$data);
			$cards[]=$data;
		}

		sort($numbers);
		$count=count($numbers);
		$numbers=implode(',',$numbers);
		$_GET['device']=$status['device'];

		include($root.'ajax_online_create.php');

// Записываем задействованные номера
		$qry="UPDATE `modems` SET `numbers`='".$numbers."',`pool_id`=".$pool_id.",`row`=".$dev_row." WHERE `device`=".$_GET['device'];
		mysqli_query($db, $qry);

// Помечаем как отработанный
		$qry="UPDATE `card2pool` SET `done`=1 WHERE `pool`='".$pool_id."' AND `card` IN ('".implode("','",$cards)."')";
		mysqli_query($db, $qry);

		$out=array('total'=>$count,'numbers'=>$numbers);
		echo json_encode($out);
		exit();
	}
	echo 'NO_NUMBERS';
	exit();
}

else if ($_POST['action']=='closeNumber')
{
	if (!$_POST['number'])
	{
		echo 'EMPTY_NUMBER';
		exit();
	}
	$numbers=array();
	$_POST['number']=explode(',',$_POST['number']);
	foreach ($_POST['number'] AS $data)
	{
		$numbers[]=' `numbers` LIKE "%'.$data.'%" ';
	}
	$qry="SELECT * FROM `modems`
	WHERE `pool_id`=".$pool_id." AND (".implode('OR',$numbers).")";
	$done=0;
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$numbers=explode(',',$row['numbers']);
			foreach ($numbers AS $key=>$data)
			{
				if (in_array($data,$_POST['number']))
				{
					unset($numbers[$key]);
					$done=1;
		                }
			}
			if (count($numbers))
			{
				$qry="UPDATE `modems` SET `numbers`='".implode(',',$numbers)."' WHERE `id`=".$row['id'];
				mysqli_query($db,$qry);
			}
			else // Отключаем Online-режим
			{
				mysqli_query($db, "DELETE FROM `modems` WHERE `id`=".(int)$row['id']);
				if (flagGet($row['device'],'cron'))
				{
					if (!flagGet($row['device'],'stop'))
					{
						flagSet($row['device'],'stop');
					}
					flagDelete($row['device'],'cron');
				}
				elseif (flagGet($row['device'],'stop',1)<time()-60)
				{
					flagDelete($row['device'],'stop');
				}
			}
		}
		if ($done)
		{
			echo 'DONE';
			exit();
		}
	}
	echo 'BAD_NUMBER';
	exit();
}

else if ($_POST['action']=='getSimStatus')
{
	if (!$_POST['number'])
	{
		echo 'EMPTY_NUMBER';
		exit();
	}
	$numbers=array();
	$list=array();
	$out=array();
	$_POST['number']=explode(',',$_POST['number']);
	foreach ($_POST['number'] AS $data)
	{
		$list[$data]=array('number'=>$data,'status'=>'NOT_FOUND');
		$numbers[]='c.`number` LIKE "%'.$data.'%"';
	}
	$qry="SELECT c.`number`, c.`place`,c.`device`,d.`model`,d.`msg`,m.`modems`,m.`row` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."' AND (".implode(' OR ',$numbers).")";
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['model']=='SR-Train')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
				$r=$place2[0];
				$modem=$place2[1];
				if ($r==$row['row'])
				{
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}
				elseif ($r-3==$row['row'] && $modem<9)
				{
					$modem+=8;
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}					
				else
				{
	 				$status='INACTIVE';
				}
			}
			elseif ($row['model']=='SR-Organizer')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[0]][1]));
			}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000')
			{
				$modems=unserialize($row['modems']);
				if ($row['place']==$modems[0])
				{
					$m=unserialize($row['msg']);
					if ($m['type']=='RING' && $m['time']>time()-10)
					{
						
		 				$status=str_replace('WAIT_SMS','RING_['.$m['data'].']',statusAtrApi($row['device'],$modems[1]));
					}
					else
					{
		 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[1]));
					}
				}
				else
				{
	 				$status='INACTIVE';
				}

			}
			elseif ($row['model']=='SR-Box-8' || $row['model']=='SR-Box-2' || $row['model']=='SR-Box-Bank')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[1]][1]));
			}
			foreach ($list AS $key=>$data)
			{
				if (strpos($row['number'],"$key")!==false)
				{
					$list[$key]['status']=$status;
				}
			}
		}
	}
	$list=array_values($list);
	echo json_encode($list);
	exit();
}

else if ($_POST['action']=='getLastSms')
{
	if (!$_POST['number'])
	{
		echo 'NO_NUMBER';
		exit();
	}
	$where="";	
	if ($_POST['period'])
	{
		$where=' AND s.`time_receive`>'.(time()-$_POST['period']);	
	}
	if ($_POST['sender'])
	{
		$where.=' AND s.`sender` LIKE "%'.$_POST['sender'].'%"';	
	}
	if ($_POST['new'])
	{
		$where.=' AND s.`readed`=0';	
	}
	$qry="SELECT s.`id`,s.`txt`,s.`readed` FROM `sms_incoming` s 
	INNER JOIN `card2pool` c ON c.`card`=s.`number` AND c.`pool`=".$pool_id."
	WHERE s.`done`=1 AND s.`number` LIKE '%".(int)$_POST['number']."%'".$where.' ORDER BY s.`time_receive` DESC LIMIT 1';

	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($_POST['code'])
			{
				preg_match('!([0-9]{'.(int)$_POST['code'].',100})!U',$row['txt'],$row['txt']);
				$row['txt']=$row['txt'][1];
			}
			if ($row['txt'])
			{
				echo $row['txt'];
				if (!$row['readed'])
				{
					$qry="UPDATE `sms_incoming` SET `readed`=1 WHERE `id`=".$row['id'];
					mysqli_query($db,$qry);
				}
				exit();
			}
		}
	}
	echo 'NOT FOUND';
	exit();
}

else if ($_POST['action']=='getSms')
{
	if (!$_POST['number'])
	{
		echo 'NO_NUMBER';
		exit();
	}
	$numbers=array();
	$list=array();
	$sms_numbers=array();
	$out=array();
	$_POST['number']=explode(',',$_POST['number']);
	foreach ($_POST['number'] AS $data)
	{
		$list[$data]=array('number'=>$data,'status'=>'NOT_FOUND_NUMBER','sms_counter'=>'0');
		$numbers[]='c.`number` LIKE "%'.$data.'%"';
	}
	$qry="SELECT c.`number`, c.`place`,c.`device`,d.`model`,d.`msg`,m.`modems`,m.`row` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."' AND (".implode(' OR ',$numbers).")";
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['model']=='SR-Train')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
				$r=$place2[0];
				$modem=$place2[1];
				if ($r==$row['row'])
				{
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}
				elseif ($r-3==$row['row'] && $modem<9)
				{
					$modem+=8;
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}					
				else
				{
	 				$status='INACTIVE';
				}
				$sms_numbers[]=$row['number'];

			}
			elseif ($row['model']=='SR-Organizer')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
				$sms_numbers[]=$row['number'];
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[0]][1]));
			}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000')
			{
				$modems=unserialize($row['modems']);
				if ($row['place']==$modems[0])
				{
					$m=unserialize($row['msg']);
					if ($m['type']=='RING' && $m['time']>time()-10)
					{
						
		 				$status=str_replace('WAIT_SMS','RING_['.$m['data'].']',statusAtrApi($row['device'],$modems[1]));
					}
					else
					{
		 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[1]));
					}
				}
				else
				{
	 				$status='INACTIVE';
				}
				$sms_numbers[]=$row['number'];
			}
			elseif ($row['model']=='SR-Box-8' || $row['model']=='SR-Box-2' || $row['model']=='SR-Box-Bank')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[1]][1]));
				$sms_numbers[]=$row['number'];
			}
			foreach ($list AS $key=>$data)
			{
				if (strpos($row['number'],"$key")!==false)
				{
					$list[$key]['status']=$status;
				}
			}
		}
		if (count($sms_numbers))
		{
// Проверяем SMS
			$where="";	
			if (!$_POST['period']){$_POST['period']=60;}
			$where=' AND unix_timestamp(c.`time_receive`)>'.(time()-$_POST['period']);	
			if ($_POST['sender'])
			{
				$where.=' AND c.`sender` LIKE "%'.$_POST['sender'].'%"';	
			}
			if ($_POST['new'])
			{
				$where.=' AND c.`readed`=0';	
			}
			$out=array();
			$qry="SELECT c.`id`,c.`number`,c.`txt`,c.`time`,c.`readed`,c.`sender` FROM `sms_incoming` c 
			INNER JOIN `card2pool` p ON p.`card`=c.`number` AND p.`pool`=".$pool_id."
			WHERE c.`done`=1 AND (".implode(' OR ',$numbers).")".$where.' ORDER BY c.`time_receive` DESC';
			if ($result = mysqli_query($db, $qry)) 
			{
				while ($row = mysqli_fetch_assoc($result))
				{
					if ($_POST['code'])
					{
						preg_match('!([0-9]{'.(int)$_POST['code'].',100})!U',$row['txt'],$row['txt']);
						$row['txt']=$row['txt'][1];
					}
					if ($row['txt'])
					{
						$n=array();
						foreach ($list AS $key=>$data)
						{
							if (strpos($row['number'],"$key")!==false)
							{
								if ($row['readed'])
								{
									$status='READED';
								} 
								else 
								{
									$status='NEW';
									$list[$key]['status']='NEW_SMS';
									$qry="UPDATE `sms_incoming` SET `readed`=1 WHERE `id`=".$row['id'];
									mysqli_query($db,$qry);
								}
								$list[$key]['sms'][]=array('id'=>$row['id'],'sender'=>$row['sender'],'text'=>$row['txt'],'time'=>date('H:i:s d.m.Y',$row['time']),'timestamp'=>$row['time'],'status'=>$status);
								$list[$key]['sms_counter']++;
							}
						}
						$out[]=$n;
					}
				}
			}
		}
	}
	$list=array_values($list);
	echo json_encode($list);
	exit();
}

else if ($_POST['action']=='deleteSms')
{
	if (!$_POST['sms_id'])
	{
		echo 'BAD_ID';
		exit();
	}
	$qry="SELECT s.`id` FROM `sms_incoming` s
	INNER JOIN `card2pool` p ON p.`card`=s.`number` AND p.pool=".$pool_id." 
	WHERE s.`id`=".(int)$_POST['sms_id'];
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$qry="DELETE FROM `sms_incoming` WHERE `id`=".$row['id'];
			mysqli_query($db,$qry);
			echo 'DONE';
			exit();
		}
	}
	echo 'NOT FOUND';
	exit();
}

else if ($_POST['action']=='getCall')
{
	if (!$_POST['number'])
	{
		echo 'NO_NUMBER';
		exit();
	}
	$numbers=array();
	$list=array();
	$call_numbers=array();
	$out=array();
	$_POST['number']=explode(',',$_POST['number']);
	foreach ($_POST['number'] AS $data)
	{
		$list[$data]=array('number'=>$data,'status'=>'NOT_FOUND_NUMBER','call_counter'=>'0');
		$numbers[]='c.`number` LIKE "%'.$data.'%"';
	}
	$qry="SELECT c.`number`, c.`place`,c.`device`,d.`model`,d.`msg`,m.`modems` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."' AND (".implode(' OR ',$numbers).")";
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['model']=='SR-Train')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
				$r=$place2[0];
				$modem=$place2[1];
				if ($r==$row['row'])
				{
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}
				elseif ($r-3==$row['row'] && $modem<9)
				{
					$modem+=8;
	 				$status=statusAtrApi($row['device'],$modems[$modem][1]);
				}					
				else
				{
	 				$status='INACTIVE';
				}
			}
			elseif ($row['model']=='SR-Organizer')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
				$call_numbers[]=$row['number'];
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[0]][1]));
			}
			elseif ($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000')
			{
				$modems=unserialize($row['modems']);
				if ($row['place']==$modems[0])
				{
					$m=unserialize($row['msg']);
					if ($m['type']=='RING' && $m['time']>time()-10)
					{
						
		 				$status=str_replace('WAIT_SMS','RING_['.$m['data'].']',statusAtrApi($row['device'],$modems[1]));
					}
					else
					{
		 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[1]));
					}
				}
				else
				{
	 				$status='INACTIVE';
				}
				$call_numbers[]=$row['number'];
			}
			elseif ($row['model']=='SR-Box-8' || $row['model']=='SR-Box-2' || $row['model']=='SR-Box-Bank')
			{
				$modems=unserialize($row['modems']);
				$place2=explode('-',$row['place']);
 				$status=str_replace('WAIT_SMS','WAIT_SMS_CALL',statusAtrApi($row['device'],$modems[$place2[1]][1]));
				$sms_numbers[]=$row['number'];
			}
			foreach ($list AS $key=>$data)
			{
				if (strpos($row['number'],"$key")!==false)
				{
					$list[$key]['status']=$status;
				}
			}
		}
		if (count($call_numbers))
		{
// Проверяем Вызовы
			$where="";	
			if (!$_POST['period']){$_POST['period']=60;}
			$where=' AND c.`time`>'.(time()-$_POST['period']);	
			$out=array();
			$qry="SELECT c.`id`,c.`number`,c.`incoming`,c.`time` FROM `call_incoming` c 
			INNER JOIN `devices` d ON c.`device`=d.`id`
			INNER JOIN `card2pool` p ON p.`card`=c.`number` AND p.`pool`=".$pool_id."
			WHERE (".implode(' OR ',$numbers).")".$where.' ORDER BY c.`time` DESC';
			if ($result = mysqli_query($db, $qry)) 
			{
				while ($row = mysqli_fetch_assoc($result))
				{
					if ($row['incoming'])
					{
						$n=array();
						foreach ($list AS $key=>$data)
						{
							if (strpos($row['number'],"$key")!==false)
							{
								$list[$key]['call'][]=array('id'=>$row['id'],'incoming_number'=>$row['incoming'],'time'=>date('H:i:s d.m.Y',$row['time']),'timestamp'=>$row['time'],'status'=>$status);
								$list[$key]['call_counter']++;
							}
						}
						$out[]=$n;
					}
				}
			}
		}
	}
	$list=array_values($list);
	echo json_encode($list);
	exit();
}

else if ($_POST['action']=='deleteCall')
{
	if (!$_POST['call_id'])
	{
		echo 'BAD_ID';
		exit();
	}
	$qry="SELECT s.`id` FROM `call_incoming` s
	INNER JOIN `card2pool` p ON p.`card`=s.`number` AND p.pool=".$pool_id." 
	WHERE s.`id`=".(int)$_POST['call_id'];
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$qry="DELETE FROM `call_incoming` WHERE `id`=".$row['id'];
			mysqli_query($db,$qry);
			echo 'DONE';
			exit();
		}
	}
	echo 'NOT FOUND';
	exit();
}

function getPoolStatus()
{
	global $db,$pool_id,$where,$channels;

	$places=array();
	$total=0;

	$numbers=array('total'=>0,'used'=>0,'free'=>0,'channels'=>0);
	$qry="SELECT c.`number`, c.`place`, c.`device`,c.`operator`,d.`model`,m.`device` AS `dev`,m.`modems` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`".$where."
	INNER JOIN `devices` d ON d.`id`=c.`device`
	LEFT JOIN `modems` m ON m.`device`=c.`device`
	WHERE p.`pool`='".$pool_id."' ORDER BY c.`device`,CHAR_LENGTH(c.`place`),c.`place`";
	if ($result = mysqli_query($db, $qry)) 
	{
		$dev=0;
		$device=array();
		$dev_numbers=array();
		$max=0;
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($dev>0 && $dev!=$row['device'])
			{
				$a=test_place($model,$device);
				$total+=count($a);
				if ($max<count($a))
				{
					$max=count($a);
					$d=$dev;
					$m=$model;
					$places=$a;
					foreach ($a AS $data)
					{
						$dev_numbers_used[$d][$data]=$dev_numbers[$d][$data];
					}
				}
				$device=array();
			}
			$dev=$row['device'];
			$numbers['total']++;
			if ($row['done'] || (($row['model']=='SR-Nano-500' || $row['model']=='SR-Nano-1000') && $row['dev']))
			{
				$numbers['used']++;
			}
			else
			{
				$device[]=$row['place'];
				$dev_numbers[$row['device']][$row['place']]=$row['number'];
				$modems=$row['modems'];
				$model=$row['model'];
			}
		}
		if (count($device))
		{
			$a=test_place($model,$device);
			$total+=count($a);
			if ($max<count($a))
			{
				$max=count($a);
				$d=$dev;
				$m=$model;
				$places=$a;
				foreach ($a AS $data)
				{
					$dev_numbers_used[$d][$data]=$dev_numbers[$d][$data];
				}
			}
		}
	}
	$numbers['free']=$numbers['total']-$numbers['used'];
	if (!$numbers['free']){$max=0;}
	$numbers['channels']=$total;
	$numbers['channels_device']=$max;
	return(array('total'=>$numbers,'numbers'=>$dev_numbers_used[$d],'device'=>$d,'model'=>$m,'places'=>$places,'modems'=>$modems));
}

function test_place($model,$device)
{
	if ($model=='SR-Box-8' || $model=='SR-Box-2')
	{
		return($device);
	}
	if ($model=='SR-Box-Bank')
	{
		$modems=array();
		sort($device);
		$busy=array();
		foreach ($device AS $key => $data)
		{
			$a=explode('-',$data);
			if ($busy[$a[1]]==1){unset($device[$key]);}
			$busy[$a[1]]=1;
		}
		sort($device);
		return($device);
	}
	if ($model=='SR-Organizer')
	{
		$modems=array();
		sort($device);
		foreach ($device AS $data)
		{
			$a=explode('-',$data);
			if (!$modems[$a[0]]){$modems[$a[0]]=$a[1];}
			if ($modems[1] && $modems[2])
			{
				break;
			}
		}
		$m=array();
		foreach($modems AS $key => $data)
		{
			$m[]=$key.'-'.$data;
		}
		return($m);
	}
	if ($model=='SR-Train')
	{
		$max=0;
		$row=0;
		$modems=array();
		sort($device);
		foreach ($device AS $data)
		{
			$a=explode('-',$data);
			$modems[$a[0]]++;
		}
		
		foreach ($modems AS $key => $data)
		{
			if ($max<$data+$modems[$key+3]){$max=$data+$modems[$key+3];$row=$key;}
		}
		$d=array();
		for ($i=1;$i<9;$i++)
		{
			if (in_array($row.'-'.$i,$device))
			{
				$d[]=$row.'-'.$i;
			}
			if (in_array(($row+3).'-'.$i,$device))
			{
				$d[]=($row+3).'-'.$i;
			}
		}
		sort($d);
		return($d);
	}
	return($device);
}

echo 'BAD_ACTION';

?>
