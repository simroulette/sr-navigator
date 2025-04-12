<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']=='all') // Delete all SMS | Удаление всех SMS
{
	$qry="DELETE FROM `sms_incoming`";
	mysqli_query($db,$qry);
}
if ($_POST['delete']) // Deleting SMS | Удаление SMS
{
	if (count($_POST['check']))
	{
		foreach ($_POST['check'] as $data)
		{
			if ($sv_user_level=='god')
			{
				$qry="DELETE FROM `sms_incoming` WHERE `id`=".(int)$data;
			}
			else
			{
				$qry="DELETE FROM `sms_incoming` WHERE `id`=".(int)$data;
			}
			mysqli_query($db,$qry);
		}
	}
}

sr_header("Список SMS"); // Output page title and title | Вывод титул и заголовок страницы

$table=array();
$where=array();
if (!$_GET['page']){$_GET['page']=1;}
$limit=' LIMIT '.((int)$GLOBALS['set_data']['page_limit']*($_GET['page']-1)).','.(int)$GLOBALS['set_data']['page_limit'];

if ($_GET['number'])
{
	$where[]="c.number LIKE '%".(int)$_GET['number']."%'";
}
if ($_GET['operator'])
{
	$where[]="(o1.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o1.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%')";
}
if ($_GET['device'])
{
	$where[]="c.device=".(int)$_GET['device'];
}
if ($_GET['sender'])
{
	$where[]="s.sender LIKE '%".mysqli_real_escape_string($db,$_GET['sender'])."%'";
}
if ($_GET['place'])
{
	$where[]="c.place LIKE '%".mysqli_real_escape_string($db,$_GET['place'])."%'";
}
if ($_GET['balance'])
{
	$a=str_replace(',','.',$_GET['balance']);
	if ($_GET['balance'][0]!='>' && $_GET['balance'][0]!='<')
	{
		$where[]="c.balance=".$a;
	}
	else
	{
		$where[]="c.balance".$a;
	}
}
if (count($where)){$where=' AND '.implode(' AND ',$where);} else {$where='';}

if (!$_GET['sort'])
{
	$order=' ORDER BY s.`time` DESC';
}
elseif ($_GET['sort']==1)
{
	$order=' ORDER BY c.`device`,s.`time` DESC';
}
elseif ($_GET['sort']==2)
{
	$order=' ORDER BY c.`place`,c.`device`,s.`time` DESC';
}
elseif ($_GET['sort']==4)
{
	$order=' ORDER BY c.`operator`,s.`time` DESC';
}
elseif ($_GET['sort']==5)
{
	$order=' ORDER BY c.`number`,s.`time` DESC';
}
elseif ($_GET['sort']==6)
{
	$order=' ORDER BY s.`sender`,s.`time` DESC';
}

	$operators=array();

$qry='SELECT count(s.id) AS counter FROM `cards` c 
LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
INNER JOIN `sms_incoming` s ON s.`number`=c.`number` AND s.`done`=1
LEFT JOIN `devices` d ON c.`device`=d.`id` 
WHERE 1'.$where;

if ($result = mysqli_query($db, $qry))
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$total=$row['counter'];	
	}
}

$qry='SELECT c.*, o1.`title` AS `operator_name`, o1.`color` AS `color`,s.sender,s.time,s.txt,s.readed,s.id AS sms_id,d.title AS device FROM `cards` c 
LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
INNER JOIN `sms_incoming` s ON s.`number`=c.`number` AND s.`done`=1
LEFT JOIN `devices` d ON c.`device`=d.`id` 
WHERE 1'.$where.$order.$limit;
if ($result = mysqli_query($db, $qry)) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
		if ($row['number']){$row['number']='+'.$row['number'];} else {$row['number']='—';}
		$table[]=array(
			'num'=>$n+$GLOBALS['set_data']['page_limit']*($_GET['page']-1),
			'number'=>$row['number'],
			'time'=>srdate('d.m.Y H:i:s',$row['time']),
			'sender'=>$row['sender'],
			'sms'=>sms_out($row['txt']),
			'sms_id'=>$row['sms_id'],
			'readed'=>$row['readed'],
			'model'=>$row['model'],
			'device'=>$row['device'],
			'place'=>$row['place'],
			'operator'=>$row['operator'],
			'operator_name'=>$row['operator_name'],
			'balance'=>$row['balance'],
			'time_balance'=>$row['time_balance'],
			'bg'=>$row['color'],
			'color'=>$color,
		);
		$n++;
	}
}

if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices[$row['id']]=$row['title'];
	}
}
$a=$_GET; unset($a['page']); 
if (empty($a) && count($table)){echo '<div id="filter_hint" onclick="fltr();">Отфильтровать</div>';} 
?>
<div id="filter"<? if (empty($a)){echo ' class="hide"';}?>>
<form method="get">
<div class="sidebar">
Номер телефона
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
<div class="sidebar">
<br>
Отправитель
</div>
<input type="text" name="sender" value="<?=$_GET['sender']?>" maxlength="16" placeholder="Отправитель. Пример: Beeline">
<div class="sidebar">
<br>
Оператор
</div>
<input type="text" name="operator" value="<?=$_GET['operator']?>" maxlength="15" placeholder="Название оператора. Пример: МТС">
<? 
if (count($devices)>1)
{
?>
<div class="sidebar">
<br>
Агрегатор
</div>
<select name="device">
	<option value="0">Все агрегаторы</option>
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($_GET['device']==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<?
}
?>
<div class="sidebar">
<br>
Место
</div>
<input type="text" name="place" value="<?=$_GET['place']?>" maxlength="7" placeholder="Место. Примеры: A0 или A или 2-8 или 2">
<div class="sidebar">
<br>
Отсортировать
</div>
<select name="sort">
<option value="0"<? if (!$_GET['sort']){echo ' selected=1';}?>>По времени</option>
<option value="1"<? if ($_GET['sort']==1){echo ' selected=1';}?>>По агрегаторам</option>
<option value="2"<? if ($_GET['sort']==2){echo ' selected=1';}?>>По местам</option>
<option value="4"<? if ($_GET['sort']==4){echo ' selected=1';}?>>По операторам</option>
<option value="5"<? if ($_GET['sort']==5){echo ' selected=1';}?>>По номерам телефонов</option>
<option value="6"<? if ($_GET['sort']==6){echo ' selected=1';}?>>По отправителю</option>
</select>
<br>
<?
if ($total>(int)$GLOBALS['set_data']['page_limit'])
{
?>
<div class="sidebar">
<br>
Страница
</div>
<select name="page">
<?
	$v=ceil($total/(int)$GLOBALS['set_data']['page_limit']);
	for ($i=1;$i<=$v;$i++)
	{
?>
<option value="<?=$i?>"<? if ($_GET['page']==$i){echo ' selected=1';}?>><?='Страница '.$i.' ('.((int)$GLOBALS['set_data']['page_limit']*($i-1)).'—'.((int)$GLOBALS['set_data']['page_limit']*$i).')'?></option>
<?
	}
?>
</select>
<?
}
?>
<div class="sidebar" style="margin-bottom: 10px;"></div>
<input type="submit" name="save" value="Отфильтровать" style="padding: 10px; margin: 5px 0">
</form>
</div>
<?
if (count($table))
{
?>
<form method="post" id="sms" name="sms">
<div class="table_box">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'sms','check')"></th>
				<th class="sidebar">№</th>
				<th>Номер</th>
				<th class="sidebar">Агрегатор</th>
				<th class="sidebar">Место</th>
				<th class="exttab" style="text-align: right;">М</th>
				<th class="sidebar">Оператор</th>
				<th>От</th>
				<th>SMS</th>
				<th>Время</th>
			</tr>  
		</thead>
<?
$n=0;
foreach ($table as $data)
{
			if ($data['time_balance']){$balance=balance_out($data['balance'],'').'<div class="legend">'.srdate('d.m.Y H:i',$data['time_balance']).'</div>';} else {$balance='—';}
?>
		<tr<? if (!$data['readed']){echo ' class="rowsel"';}?>>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['sms_id']?>"></td>
			<td class="sidebar"><?=$data['num']?></td>
			<td><?=$data['number']?></td>
			<td class="sidebar"><?=$data['device']?></td>
			<td class="sidebar" align="right"><?=$data['place']?></td>
			<td class="exttab" align="right"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['place']?></td>
			<td class="sidebar"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center"><?=$data['operator_name']?></td>
			<td><?=$data['sender']?></td>
			<td><?=$data['sms']?></td>
			<td><?=$data['time']?></td>
		</tr>
<?
}
?>
	</table>
</div>
<?=$scroller=scrollbar($total,$_GET['page'],$GLOBALS['set_data']['page_limit'],'page');?>
<br>
<input type="submit" name="delete" value="Удалить отмеченные SMS" class="width">
<a href="sms.php?delete=all" class="link red width">Удалить все SMS</a>
</form>
<?
}
else
{
?>
<div class="tooltip">— Список SMS пуст!</div>
<?
}

sr_footer();
?>
