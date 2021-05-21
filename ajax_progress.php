<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['action'])
{
	if ($result = mysqli_query($db, "SELECT * FROM `actions` WHERE `id`=".(int)$_GET['action'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$p=round($row['progress']/($row['count']/100+0.000001),2);
			$e=$row['errors'];
			$s=$row['success'];
			$pd=$row['progress'];
			if ($p>100){$p=100;}
			$access=flagGet($row['device'],'answer',1);
			if ($access+30<time()){$o='Offline';$t=': '.time_calc(time()-$access);} else {$o='Online';$t='';}
			if ($row['status']=='inprogress')
			{
				$m='<em>Задача выполняется...</em><br><br>';
			}
		}
		else
		{
			$p=100;
		}
	}
	echo $p.';'.$m.';'.$pd.';'.$e.';'.$s.';'.$o.';'.$t;
}
else
{
	$txt='';
	$actions=';'.$_GET['actions'].';';
	$a=explode(';',$_GET['actions']);
	if ($result = mysqli_query($db, "SELECT * FROM `actions` WHERE `id` IN (".implode(',',$a).")")) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$actions=str_replace(';'.$row['id'].';',';',$actions);
			$p=round($row['progress']/($row['count']/100+0.000001),2);
			if ($p>100){$p=100;}
			$e=$row['errors'];
			$s=$row['success'];
			$pd=$row['progress'];
			$access=flagGet($row['device'],'answer',1);
			if ($access+30<time()){$o='Offline';$t=': '.time_calc(time()-$access);} else {$o='Online';$t='';}
			$el=time_calc(time()-$row['time']);
			$lt=time_calc(($row['count']-$row['progress'])*((time()-$row['time'])/$row['progress']));
			$txt.=$row['id'].';'.$p.';'.$pd.';'.$e.';'.$s.';'.$o.';'.$t.';'.$el.';'.$lt.';';
			if ($row['status']=='inprogress')
			{
				$txt.='1###';
			}
			elseif ($row['status']=='suspended')
			{
				$txt.='2###';
			}
			elseif ($row['status']=='suspension')
			{
				$txt.='3###';
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
			$txt.=$data.';100;;;;;;;;1###';
		}
	}
	echo $txt;
}
?>