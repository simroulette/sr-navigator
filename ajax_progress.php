<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['action'])
{
	if ($result = mysqli_query($db, "SELECT * FROM `actions` WHERE `id`=".(int)$_GET['action'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$s=round($row['progress']/($row['count']/100+0.000001),2);
			if ($s>100){$s=100;}
			if ($row['status']=='inprogress')
			{
				$p='Задача выполняется...';
			}
		}
		else
		{
			$s=100;
		}
	}
	echo $s.';'.$p;
}
else
{
	$txt='';
	$actions=';'.$_GET['actions'].';';
	if ($result = mysqli_query($db, "SELECT * FROM `actions`")) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$actions=str_replace(';'.$row['id'].';',';',$actions);
			$s=round($row['progress']/($row['count']/100+0.000001),2);
			if ($s>100){$s=100;}
			$txt.=$row['id'].';'.$s.';';
			if ($row['status']=='inprogress')
			{
				$txt.='1###';
			}
			else
			{
				$txt.='0###';
			}
		}
	}
	$actions=trim($actions,';');
	if ($actions)
	{
		$actions=explode(';',$actions);
		foreach ($actions AS $key=>$data)
		{
			$txt.=$data.';100;1###';
		}
	}
	echo $txt;
}
?>