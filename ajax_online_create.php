<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$s='';

// Checks whether the selected row falls within the range | Проверка попадает ли выбранный ряд в диапазон
if ($result = mysqli_query($db, 'SELECT `modems`,`model`,`data` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($row['model']=='SR-Train') // SR Train
		{
			$data=unserialize($row['data']);
			if ($_GET['row']<0 || $_GET['row']>$data['rows']){echo 'Ошибка: Выбранный ряд выходит за рамки диапазона!';exit();}
			$mod=explode(',',$row['modems']);
		}
	}
}
if ($row['model']=='SR-Train') // SR Train
{
	$modems=array();
	for ($i=0;$i<count($mod);$i++)
	{
		$modems[$mod[$i]]=array($_GET['row'],-3);
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize($modems)."', `time`=".time()); 
}
else // SR Nano
{
	if (ord($_GET['row'][0])<58)
	{
		if ($result = mysqli_query($db, 'SELECT `place` FROM `cards` WHERE `number` LIKE "%'.(int)$_GET['row'].'%"')) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$_GET['row']=$row['place'];
			}
			else
			{
				echo 'Ошибка: Номер не найден!';exit();
			}
		}
	}
	mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].", `modems`='".serialize(array($_GET['row'],-3))."', `time`=".time()); 
}
file_put_contents('flags/stop_'.(int)$_GET['device'],'1');
sleep(10);
file_put_contents('flags/stop_'.(int)$_GET['device'],'1');
unlink('flags/cron_'.(int)$_GET['device']);
?>