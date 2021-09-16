<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['mode']=='clear') // Clearing the List | Очистка списка
{
	mysqli_query($db, 'TRUNCATE `reports`'); 
	header('location:reports.php');
	exit();
}

sr_header("Отчеты о выполненных задачах"); // Output page title and title | Вывод титул и заголовок страницы

$table=array();
$where=array();
$actions=array();
if ($_POST['device'])
{
	$where[]="a.device=".(int)$_POST['device'];
}
if (count($where)){$where='WHERE '.implode(' AND ',$where);} else {$where='';}

if ($result = mysqli_query($db, 'SELECT a.*,d.title AS device,d.model FROM `reports` a 
INNER JOIN `devices` d ON a.`device`=d.`id` 
'.$where.'
ORDER BY a.`id` DESC')) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		$place=$row['place'];
		if (strlen($place)<100){$place2=$place;} else {$p=explode(',',$place);$place2=$p[0].' ...'.$p[count($p)-2];}
		if (strlen($place)>500){$p=explode(',',$place);$place=$p[0].' ...'.$p[count($p)-2];}
		$actions[]=$row['id'];
		if ($row['card_number']){$row['card_number']=' <em>+'.$row['card_number'].'</em>';}
		$table[]=array(
			'num'=>$n,
			'number'=>$row['number'],
			'time_begin'=>srdate('d.m.Y H:i:s',$row['time_begin']),
			'time_end'=>srdate('d.m.Y H:i:s',$row['time_end']),
			'duration'=>time_calc($row['time_end']-$row['time_begin']),
			'id'=>$row['id'],
			'action'=>$row['action'],
			'device'=>$row['device'],
			'count'=>$row['count'],
			'status_txt'=>$s,
			'place'=>trim(trim($place,' '),',').$row['card_number'],
			'place2'=>trim(trim($place2,' '),',').$row['card_number'],
			'errors'=>$row['errors'],
			'success'=>$row['success'],
		);
		$n++;
	}
}

if ($_GET['device']){$where=' AND id='.(int)$where;} else {$where='';}
	
if ($result = mysqli_query($db, 'SELECT * FROM `devices` WHERE 1=1'.$where.' ORDER BY `title`')) 
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
	<table class="table table_sort">
		<thead>
			<tr>
				<th>Задача</th>
<? if (count($devices)>1){?>
				<th>Агрегатор</th>
<? } ?>
				<th class="sidebar">Места</th>
				<th class="sidebar">Начало</th>
				<th class="sidebar">Окончание</th>
				<th><span class="sidebar">Выполнение</span><span class="extinfo">Время</span></th>
				<th>Детали</th>
			</tr>  
		</thead>
<?
	$n=0;
	foreach ($table as $data)
	{
?>
		<tr>
			<td><?=$data['action']?><span class="extinfo"><div style="margin-top: 7px;"><?=$data['place2']?></div><div class="legend">Всего:<?=$data['count']?></div></span></td>
<? if (count($devices)>1){?>
			<td><?=$data['device']?></td>
<? } ?>
			<td class="sidebar"><?=$data['place']?></span><div class="legend">Всего:<?=$data['count']?></div></td>
			<td class="sidebar"><?=$data['time_begin']?></td>
			<td class="sidebar"><?=$data['time_end']?></td>
			<td><?=$data['duration']?></td>
			<td>Успешно:<?=$data['success']?><br>Ошибки:<?=$data['errors'] ? '<span class="but_win" data-id="win_action" data-title="Задача '.$data['action'].'" data-type="ajax_action_errors.php?id='.$data['id'].'" data-height="400" data-width="600">'.$data['errors'].'</span>' : '0' ?></td>
		</tr>
<?
	}
?>
	</table>
<br>

<a href="reports.php?mode=clear" class="link" style="background: #FF0000;">Очистить список</a>
<br>
<?
}
else
{
?>
<em>— Список пуст!</em>
<?
}

sr_footer();
?>