<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

if (!isset($db))
{
	include("_func.php");
	$view=1;
}
$s='';
if ($result = mysqli_query($db, 'SELECT d.`modems`,d.`model`,d.`data`,a.`status` FROM `devices` d
LEFT JOIN `actions` a ON a.`device`=d.`id` AND a.`status`<>"suspended"
WHERE d.`id`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$data=unserialize($row['data']);
		$action=$row['status'];
		if ($row['model']=='SR-Train') // SR Train
		{
			if ($_GET['row']<0 || $_GET['row']>$data['rows']){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
			$mod=explode(',',$row['modems']);
		}
		else if ($row['model']=='SR-Box-8') // SR Box
		{
			if ($_GET['row']>0){if ($view){echo 'Ошибка: Для выбранной конфигурации доступен только 0 ряд модемов!';} exit();}
			$mod=explode(',',$row['modems']);
		}
	}
}
if ($action){if ($view){echo 'Ошибка: Есть активные задачи! Задачи необходимо приостановить или отменить...';} exit();}

$model=$row['model'];
if ($model=='SR-Train') // SR Train
{
	$modems=array();
	for ($i=0;$i<count($mod);$i++)
	{
		$modems[$mod[$i]]=array($_GET['row'],-3);
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `row`=".(int)$_GET['row'].",`modems`='".serialize($modems)."', `time`=".time()); 
}
elseif ($model=='SR-Organizer') // SR Organizer
{
	$modems=array();
	if (strpos($_GET['row'],':')!==false)
	{
		$a=explode(':',$_GET['row']);
		$a=explode('-',$a[1]);
		if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
			}
		}
		$modems[$a[0]]=array($a[1],-3);
	}
	else
	{	
		$r=explode('-',$_GET['row']);
		if ($r[0]<1 || $r[0]>9 || $r[1]<1 || $r[1]>9){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}

		if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
			}
		}

		if ($modems[1][0]!=$r[0]){$modems[1]=array($r[0],-3);}
		if ($modems[2][0]!=$r[1]){$modems[2]=array($r[1],-3);}
	}

	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
elseif ($model=='SR-Organizer-Smart') // SR Organizer
{
	$modems=array();
	if (strpos($_GET['row'],':')!==false)
	{

		$a=explode(':',$_GET['row']);
		$l=ord($a[1][0])-64;
		$d=ord($a[1][1])-48;
		if ($l<1 || $l>3 || $d<1 || $d>8){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		if ($result_smart = mysqli_query($db, 'SELECT * FROM `devices_state` WHERE `dev`="modem'.$l.'" AND `device_id`='.(int)$_GET['device'])) 
		{
			if ($row_smart = mysqli_fetch_assoc($result_smart))
			{
				$dt=unserialize($row_smart['data']);
				$dt->card=$d;
				$qry='REPLACE INTO `devices_state` SET `device_id`='.(int)$_GET['device'].', `dev`="modem'.$l.'", `result`="-1",`data`="'.mysqli_real_escape_string($db,serialize($dt)).'"';
				mysqli_query($db,$qry);
				sr_command_smart((int)$_GET['device'],'modem'.$l.'.card:'.$d); 
			}
		}
		exit();
	}

	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
elseif ($model=='SR-Box-Bank' || $model=='SR-Board') // SR Box-Bank
{
	$_GET['row']=str_replace('place:','',$_GET['row']);
	if ($_GET['row']>100)
	{
		$qry='SELECT `place` FROM `cards` WHERE (`number` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `comment`="%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `title` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%") LIMIT 1';
		if ($result = mysqli_query($db, $qry)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$_GET['row']=$row['place'];
			}
			else
			{
				if ($view){echo 'Ошибка: Поиск по номеру, имени и комментарию не дал результатов!';} exit();
			}
		}
	}
	if (strpos($_GET['row'],'A')!==false || strpos($_GET['row'],'B')!==false || strpos($_GET['row'],'C')!==false || strpos($_GET['row'],'D')!==false || strpos($_GET['row'],'E')!==false || strpos($_GET['row'],'F')!==false || strpos($_GET['row'],'G')!==false || strpos($_GET['row'],'H')!==false)  // любое_слово:2-1
	{
		$places=explode(',',$_GET['row']);
		$channels=array();
		$newPlace=array();
		$banks=array();
		$max=0;
		$min=100;
		foreach ($places AS $p)
		{
			$p=trim($p);
			if (strlen($p)>1)
			{
//				$newPlace[ord($p)-64]=$a=substr($p,1,10); // !!!
				$newPlace[ord($p[0])-64]=$a=substr($p,1,10);
				if (ceil($a/8)>8){$stop=1;}
				if (ord($p[0])>73){$stop=1;}
				$channels[ord($p[0])-64]++;
				$banks[ceil($a/8)]=1; // !!! new
//				if ($a<$max){$max=$a;}
				if ($a>$max){$max=$a;} // !!! Тут поменял и не проверял
			}
			else
			{
				$min=0;
			}
		}
		if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
			}
		}

		if (!$data['map'] || $data['map']==1)
		{
			for ($j=0;$j<8;$j++)
			{
				if ($channels[$j+1]>1)
				{
					if ($view)
					{
						echo 'Ошибка: Выбрано несколько карт для одного модема!';
					} 
					exit();
				}
			}
			if ($min<1 || $max>8){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}
		else
		{
			for ($j=0;$j<8;$j++)
			{
				if ($channels[$j+1]>1)
				{
					if ($view)
					{
						echo 'Ошибка: Выбрано несколько карт для одного модема!';
					} 
					exit();
				}
				if ($banks[$j+1] && !$data['map'][$j])
				{
					$error=1; 
					break;
				}
			}

			if ($error){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}

		for ($i=1;$i<9;$i++)
		{
			if ($newPlace[$i] && $modems[$i][0]!=$newPlace[$i]){$modems[$i]=array($newPlace[$i],-3);} elseif (!$modems[$i]){$modems[$i]=array(1,-3);}
		}
	}
	else
	{
		$r=explode(',',$_GET['row']);
		if (count($r)==1)
		{
			for ($i=1;$i<8;$i++)
			{
				$r[$i]=$r[0];
			}
		}	
		$i=count($r);
		for ($i;$i<8;$i++)
		{
			$r[$i]=1;
		}
		foreach ($r AS $data)
		{
			$d=unserialize($row['data']);
			if (!$d['map'] || (strlen($d['map'])==1 && $d['map']==1))
			{
				if ($data<1 || $data>8){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
			}
			else
			{
				$error=1;
				for ($j=0;$j<8;$j++)
				{
					if ($d['map'][$j])
					{
						if ($data>$j*8 && $data<=$j*8+8){$error=''; break;}
					}
				}
				if ($error){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
			}
		}
		$modems=array();
		if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
			}
		}
		for ($i=1;$i<9;$i++)
		{
			if ($modems[$i][0]!=$r[$i-1]){$modems[$i]=array($r[$i-1],-3);}
		}
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
elseif ($model=='SR-Box-2-Bank') // SR Box-2-Bank
{
	$_GET['row']=str_replace('place:','',$_GET['row']);
	if ($_GET['row']>100)
	{
		$qry='SELECT `place` FROM `cards` WHERE (`number` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `comment`="%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `title` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%") LIMIT 1';
		if ($result = mysqli_query($db, $qry)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$_GET['row']=$row['place'];
			}
			else
			{
				if ($view){echo 'Ошибка: Поиск по номеру, имени и комментарию не дал результатов!';} exit();
			}
		}
	}
	if (strpos($_GET['row'],'A')!==false || strpos($_GET['row'],'B')!==false || strpos($_GET['row'],'C')!==false || strpos($_GET['row'],'D')!==false || strpos($_GET['row'],'E')!==false || strpos($_GET['row'],'F')!==false || strpos($_GET['row'],'G')!==false || strpos($_GET['row'],'H')!==false)  // любое_слово:2-1
	{
		$d=unserialize($row['data']);
		$places=explode(',',$_GET['row']);
		$channels=array();
		$newPlace=array();
		$banks=array();
		$max=0;
		$min=100;
		foreach ($places AS $p)
		{
			$p=trim($p);
			if (strlen($p)>1)
			{
//				$newPlace[ord($p)-64]=$a=substr($p,1,10); // !!!
				$newPlace[ord($p[0])-64]=$a=substr($p,1,10);
				if (ord($p[0])-64<5){$mod_place1=ord($p[0])-64;}
				if (ord($p[0])-64>4){$mod_place2=ord($p[0])-64;}
				if (ceil($a/8)>8){$stop=1;}
				if (ord($p[0])>73){$stop=1;}
				$channels[ord($p[0])-64]++;
				$banks[ceil($a/8)]=1; // !!! new
//				if ($a<$max){$max=$a;}
				if ($a>$max){$max=$a;} // !!! Тут поменял и не проверял
			}
			else
			{
				$min=0;
			}
		}
//echo $mod_place1.'-'.$mod_place2;
		if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);
			}
		}
//	echo '>'.$data['map'].' '.$min.'-'.$max;

		if (!$d['map'] || (strlen($d['map'])==1 && $d['map']==1))
		{
			for ($j=0;$j<8;$j++)
			{
				if ($channels[$j+1]>1)
				{
					if ($view)
					{
						echo 'Ошибка: Выбрано несколько карт для одного модема!';
					} 
					exit();
				}
			}
			if ($min<1 || $max>8){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}
		else
		{
/* !!!
			$error=1;
			for ($j=0;$j<8;$j++)
			{
				if ($data['map'][$j])
				{
					if ($a[0]>$j*8 && $a[0]<=$j*8+8){$error=''; break;}
				}
			}
*/
			for ($j=0;$j<8;$j++)
			{
				if ($channels[$j+1]>1)
				{
					if ($view)
					{
						echo 'Ошибка: Выбрано несколько карт для одного модема!';
					} 
					exit();
				}
				if ($banks[$j+1] && !$data['map'][$j])
				{
					$error=1; 
					break;
				}
			}

			if ($error){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}

		$mod1=0;
		$mod2=0;
		
		for ($i=1;$i<9;$i++)
		{
			if (!count($modems[$i]))
			{
				$modems[$i]=array(0,-4);
			}
		}
		ksort($modems);
		$i=0;
		foreach ($modems AS $data)
		{
			$i++;
			$m[$i]=$data;
		}
		$modems=$m;

		if (count($places)>1)
		{
//			$mod_place1=1;			
//			$mod_place2=1;
			$modems=array();			
		}
		if ($mod_place1)
		{
			for ($i=1;$i<5;$i++)
			{
//				if (!$newPlace[$i])
				if (!$mod_place1!=$i)
				{
					$modems[$i]=array(0,-4);
				}
				else
				{
					$modems[$i]=array((int)$modems[$i][0],(int)$modems[$i][1]);
				}
			}
		}
		if ($mod_place2)
		{
			for ($i=5;$i<9;$i++)
			{
//				if (!$newPlace[$i])
				if (!$mod_place2!=$i)
				{
					$modems[$i]=array(0,-4);
				}
				else
				{
					$modems[$i]=array((int)$modems[$i][0],(int)$modems[$i][1]);
				}
			}
		}
		if ($mod_place1)
		{
			$modems[$mod_place1]=array($newPlace[$mod_place1],-3);
		}
		if ($mod_place2)
		{
			$modems[$mod_place2]=array($newPlace[$mod_place2],-3);
		}
//print_r($modems);
/*
		for ($i=1;$i<9;$i++)
		{
			if ($newPlace[$i] && (($newPlace[$i]<5 && !$mod1) || ($newPlace[$i]>4 && !$mod2)) && $modems[$i][0]!=$newPlace[$i])
			{
				$modems[$i]=array($newPlace[$i],-3);
				if ($newPlace[$i]<5){$mod1=1;}			
				elseif ($newPlace[$i]>4){$mod2=1;}			
			} 
			elseif ($modems[$i][1]!=-4 && (($i<5 && !$mod1) || ($i>4 && !$mod2)))
			{
				$modems[$i]=array(1,-3);
				if ($newPlace[$i]<5){$mod1=1;}			
				elseif ($newPlace[$i]>4){$mod2=1;}			
			}
		}
*/
//		print_r($modems);
	}
/*
	elseif (strpos($_GET['row'],'-')!==false)
	{
		if ($view){echo 'Ошибка: Неверный формат "'.$_GET['row'].'"!';} 
		exit();
	}
*/
	else
	{
		$data=(int)$_GET['row'];
		$d=unserialize($row['data']);
		if (!$d['map'] || (strlen($d['map'])==1 && $d['map']==1))
		{
			if ($data<1 || $data>8){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}
		else
		{
			$error=1;
			for ($j=0;$j<8;$j++)
			{
				if ($d['map'][$j])
				{
					if ($data>$j*8 && $data<=$j*8+8){$error=''; break;}
				}
			}
			if ($error){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
		}
		$modems=array();
		for ($i=1;$i<9;$i++)
		{
			$modems[$i]=array(0,-4);
		}
		$modems[1]=array($data,-3);
		$modems[5]=array($data,-3);
	}
	
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
elseif ($model=='SR-Box-8') // SR Box
{
	$modems=array();
	for ($i=0;$i<count($mod);$i++)
	{
		$modems[$mod[$i]]=array($_GET['row'],-3);
	}

//echo $model;
//print_r($mod);
//print_r($modems);
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
elseif (strpos($model,'SR-Nano')!==false) // SR Nano
{
	$qry='SELECT `place` FROM `cards` WHERE (`place`="'.mysqli_real_escape_string($db,$_GET['row']).'" OR `number` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `comment`="%'.mysqli_real_escape_string($db,$_GET['row']).'%" OR `title` LIKE "%'.mysqli_real_escape_string($db,$_GET['row']).'%") LIMIT 1';
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$_GET['row']=$row['place'];
		}
		else
		{
			if (($_GET['row'][0]<'A' || $_GET['row'][0]>'L') && ($_GET['row'][0]<'a' || $_GET['row'][0]>'l'))
			{
				if ($view){echo 'Поиск по месту, номеру, имени и комментарию результата не дал!';}
				exit();
			}
			else // Проверяем подходит ли диапазон
			{
				if ($model=='SR-Nano-1000')
				{
					$cards=array(140,130,120,110,100,90,80,68,58,46,34,24);				
				}		
				else
				{
					$cards=array(100,90,80,68,58,46,34,24);				
				}	
				$c=substr($_GET['row'],1,255);
				if ($cards[ord($_GET['row'][0])-65]<=$c)
				{
				  	if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';}
					exit();
				}
			}
		}
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize(array(strtoupper($_GET['row']),-3))."', `time`=".time()); 
}
if (flagGet($_GET['device'],'cron'))
{
	flagSet($_GET['device'],'stop');
	flagDelete($_GET['device'],'cron');
}
?>