<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
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
	header('location:actions.php');
	exit();
}

if ($_POST['suspend']) // Приостановка отмеченных задач
{
	foreach ($_POST['check'] as $data)
	{
		if ($result = mysqli_query($db, 'SELECT a.* FROM `actions` a 
		INNER JOIN `devices` d ON d.id=a.device 
		WHERE a.`id`='.(int)$data)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$qry="UPDATE `actions` SET `status`='suspension' WHERE `id`=".(int)$data;
				mysqli_query($db,$qry);
			}
		}
	}
	header('location:actions.php');
	exit();
}

if ($_POST['unsuspend']) // Приостановка отмеченных задач
{
	foreach ($_POST['check'] as $data)
	{
		if ($result = mysqli_query($db, 'SELECT a.* FROM `actions` a 
		INNER JOIN `devices` d ON d.id=a.device 
		WHERE a.`id`='.(int)$data)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$qry="UPDATE `actions` SET `status`='waiting' WHERE `status`='suspended' AND `id`=".(int)$data;
				mysqli_query($db,$qry);
			}
		}
	}
	header('location:actions.php');
	exit();
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
INNER JOIN `devices` d ON a.`device`=d.`id` 
'.$where.'
ORDER BY a.`id`')) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
		$s=round($row['progress']/($row['count']/100+0.000001),2);
		if ($s>100){$s=100;}
		if ($row['status']=='inprogress' && $s==100)
		{
			$s='Выполнена';
		} 
		elseif ($row['status']=='inprogress')
		{
			$s='Прогресс&nbsp;'.$s.'%';
		} 
		elseif ($row['status']=='waiting')
		{
			$s='В очереди';
		}
		elseif ($row['status']=='suspension')
		{
			$s='Приостанавливается...';
		} 
		elseif ($row['status']=='suspended')
		{
			$s='Приостановлена';
		}
		else
		{
			$s='...';
		}
		$place='';
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
				elseif (strpos($row['model'],'SR-Box-8')!==false)
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
		if (strlen($place)<100){$place2=$place;} else {$p=explode(',',$place);$place2=$p[0].' ...'.$p[count($p)-2];}
		if (strlen($place)>500){$p=explode(',',$place);$place=$p[0].' ...'.$p[count($p)-2];}
		$actions[]=$row['id'];
		if ($row['card_number']){$row['card_number']=' <em>+'.$row['card_number'].'</em>';}
		$table[]=array(
			'num'=>$n,
			'number'=>$row['number'],
			'time'=>srdate('d.m.Y H:i:s',$row['time']),
			'id'=>$row['id'],
			'action'=>$row['action'],
			'device'=>$row['device'],
			'count'=>$row['count'],
			'status_txt'=>$s,
			'place'=>trim(trim($place,' '),',').$row['card_number'],
			'place2'=>trim(trim($place2,' '),',').$row['card_number'],
			'bg'=>$row['color'],
			'color'=>$color,
		);
		$n++;
	}
}

if ($_GET['device']){$where=' AND id='.(int)$where;} else {$where='';}
	
if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
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
Агрегатор
<select name="device">
<option value="-1">— Выберите агрегатор —</option>
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
<form method="post" name="actions" id="actions">
	<table class="table table_sort">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'actions','check')"></th>
				<th class="sidebar">№</th>
				<th>Задача</th>
<? if (count($devices)>1){?>
				<th>Агрегатор</th>
<? } ?>
				<th class="sidebar">Места</th>
				<th class="sidebar">Время</th>
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
			<td class="sidebar"><?=$data['num']?></td>
			<td><?=$data['action']?><span class="extinfo"><div style="margin-top: 7px;"><?=$data['place2']?><div class="legend">Всего:<?=$data['count']?></div></div></span></td>
<? if (count($devices)>1){?>
			<td><?=$data['device']?></td>
<? } ?>
			<td class="sidebar"><?=$data['place']?><div class="legend">Всего:<?=$data['count']?></div></td>
			<td class="sidebar"><?=$data['time']?></td>
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

<input type="submit" name="delete" value="Отменить задачу" style="background: #FF0000; float:left; margin: 0 10px 10px 0;">
<input type="submit" name="suspend" value="Приостановить задачу" style="float:left; margin: 0 10px 10px 0;">
<input type="submit" name="unsuspend" value="Продолжить выполнение" style="float:left; margin: 0 10px 10px 0;">
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