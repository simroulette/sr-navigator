<?
// ===================================================================
// Sim Roulette -> Settings
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_POST['delete']) // Deletes the selected actions | Удаление отмеченных задач
{
	foreach ($_POST['check'] as $data)
	{
		action_stop($data);
	}
}

sr_header("Очередь задач"); // Output page title and title | Вывод титул и заголовок страницы

$table=array();
$where=array();
$actions=array();
if ($_POST['device'])
{
	$where[]="a.device=".(int)$_POST['device'];
}
if (count($where)){$where='WHERE '.implode(' AND ',$where);} else {$where='';}

if ($result = mysqli_query($db, 'SELECT a.*,d.title AS device,d.model FROM `actions` a 
LEFT JOIN `devices` d ON a.`device`=d.`id` 
'.$where.'
ORDER BY a.`time`')) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
		$s=round($row['progress']/($row['count']/100+0.000001),2);
		if ($s>100){$s=100;}
		if ($row['status']=='inprogress' && $s==100)
		{
			$s='Выполнено';
		} 
		elseif ($row['status']=='inprogress')
		{
			$s='Прогресс '.$s.'%';
		} 
		elseif ($row['status']='waiting')
		{
			$s='В очереди';
		}
		else
		{
			$s='...';
		}
		$place='';
		if ($result2 = mysqli_query($db, 'SELECT * FROM `card2action` WHERE `action`='.$row['id'])) 
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
		$actions[]=$row['id'];
		$table[]=array(
			'num'=>$n,
			'number'=>$row['number'],
			'time'=>date('d.m.Y H:i:s',$row['time']),
			'id'=>$row['id'],
			'action'=>$row['action'],
			'device'=>$row['device'],
			'status_txt'=>$s,
			'place'=>trim(trim($place,' '),','),
			'bg'=>$row['color'],
			'color'=>$color,
		);
		$n++;
	}
}

if ($_GET['device']){$where='WHERE id='.(int)$where;} else {$where='';}
	
if ($result = mysqli_query($db, 'SELECT * FROM `devices` '.$where.' ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices[$row['id']]=$row['title'];
	}
}
?>
<br>
<? 
if (count($devices)>1)
{
?>
<form method="get">
Устройство
<select name="device">
<option value="-1">— Выберите устройство —</option>
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($_GET['device']==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<div class="sidebar" style="margin-bottom: 10px;"></div>
<input type="submit" name="save" value="Отфильтровать" style="padding: 10px; margin: 5px 0">
</form>
<?
}

if (count($table))
{
?>
<form method="post" id="sms" name="sms">
	<table class="table table_sort">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'sms','check')"></th>
				<th>№</th>
				<th>Задача</th>
				<th>Устройство</th>
				<th>Места</th>
				<th>Время</th>
				<th>Статус</th>
			</tr>  
		</thead>
<?
	$n=0;
	foreach ($table as $data)
	{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['id']?>"></td>
			<td><?=$data['num']?></td>
			<td><?=$data['action']?></td>
			<td><?=$data['device']?></td>
			<td><?=$data['place']?></td>
			<td><?=$data['time']?></td>
			<td id="act_<?=$data['id']?>"><?=$data['status_txt']?></td>
		</tr>
<?
	}
?>
	</table>
<br>

<script>
setInterval(function()
{
	getProgressAll(<?="'".implode(';',$actions)."'";?>);
}, 1000);
</script>

<input type="submit" name="delete" value="Отменить задачу" style="background: #FF0000; float:left; margin-right: 10px">
</form>
<?
}
else
{
?>
<em>— Очередь пуста!</em>
<?
}

sr_footer();
?>