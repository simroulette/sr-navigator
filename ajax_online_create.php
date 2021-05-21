<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

if (!isset($db))
{
	include("_func.php");
	$view=1;
}
$s='';
// Checks whether the selected row falls within the range | Проверка попадает ли выбранный ряд в диапазон
if ($result = mysqli_query($db, 'SELECT `modems`,`model`,`data` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($row['model']=='SR-Train') // SR Train
		{
			$data=unserialize($row['data']);
			if ($_GET['row']<0 || $_GET['row']>$data['rows']){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}
			$mod=explode(',',$row['modems']);
		}
		else if ($row['model']=='SR-Box-8') // SR Box
		{
			$data=unserialize($row['data']);
			if ($_GET['row']>0){if ($view){echo 'Ошибка: Для выбранной модели доступен только 0 ряда модемов!';} exit();}
			$mod=explode(',',$row['modems']);
		}
	}
}
$model=$row['model'];
if ($model=='SR-Train') // SR Train
{
	$modems=array();
	for ($i=0;$i<count($mod);$i++)
	{
		$modems[$mod[$i]]=array($_GET['row'],-3);
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
	if ($GLOBALS['sv_owner_id'])
	{
		flagSet($_GET['device'],'busy',$GLOBALS['sv_staff_id']);
		flagSet($_GET['device'],'staff',$_COOKIE['login']);
	} 
	else 
	{
		flagDelete($_GET['device'],'busy');
		flagDelete($_GET['device'],'staff');
	}
}
elseif ($model=='SR-Organizer') // SR Organizer
{
	$r=explode('-',$_GET['row']);
	if ($r[0]<1 || $r[0]>9 || $r[1]<1 || $r[1]>9){if ($view){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';} exit();}

	$modems=array();
	if ($result = mysqli_query($db, "SELECT * FROM `modems` WHERE `device`=".(int)$_GET['device'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$modems=unserialize($row['modems']);
		}
	}

	if ($modems[1][0]!=$r[0]){$modems[1]=array($r[0],-3);}
	if ($modems[2][0]!=$r[1]){$modems[2]=array($r[1],-3);}

	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
	if ($GLOBALS['sv_owner_id'])
	{
		flagSet($_GET['device'],'busy',$GLOBALS['sv_staff_id']);
		flagSet($_GET['device'],'staff',$_COOKIE['srlogin']);
	} 
	else 
	{
		flagDelete($_GET['device'],'busy');
		flagDelete($_GET['device'],'staff');
	}
}
elseif ($model=='SR-Box-8') // SR Box
{
	$modems=array();
	for ($i=0;$i<count($mod);$i++)
	{
		$modems[$mod[$i]]=array($_GET['row'],-3);
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
	if ($GLOBALS['sv_owner_id'])
	{
		flagSet($_GET['device'],'busy',$GLOBALS['sv_staff_id']);
		flagSet($_GET['device'],'staff',$_COOKIE['srlogin']);
	} 
	else 
	{
		flagDelete($_GET['device'],'busy');
		flagDelete($_GET['device'],'staff');
	}
}
elseif (strpos($model,'SR-Nano')!==false) // SR Nano
{
	if ($sv_staff_id)
	{
		$qry='SELECT c.`place` FROM `cards` c
		INNER JOIN `card2pool` p ON p.`card`=c.`number` AND p.`pool`='.$sv_pool.' 
		WHERE (c.`place`="'.$_GET['row'].'" OR c.`number` LIKE "%'.$_GET['row'].'%" OR c.`comment`="%'.$_GET['row'].'%" OR c.`title` LIKE "%'.$_GET['row'].'%") LIMIT 1';
	}
	else
	{
		$qry='SELECT `place` FROM `cards` WHERE (`place`="'.$_GET['row'].'" OR `number` LIKE "%'.$_GET['row'].'%" OR `comment`="%'.$_GET['row'].'%" OR `title` LIKE "%'.$_GET['row'].'%") LIMIT 1';
	}
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$_GET['row']=$row['place'];
		}
		elseif ($sv_staff_id)
		{
			echo 'Доступ запрещен! Выберите номер из списка внизу экрана...';
			exit();
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
	if ($GLOBALS['sv_owner_id'])
	{
		flagSet($_GET['device'],'busy',$GLOBALS['sv_staff_id']);
		flagSet($_GET['device'],'staff',$_COOKIE['srlogin']);
	} 
	else 
	{
		flagDelete($_GET['device'],'busy');
		flagDelete($_GET['device'],'staff');
	}
}
if (flagGet($_GET['device'],'cron'))
{
	flagSet($_GET['device'],'stop');
	flagDelete($_GET['device'],'cron');
}
?>