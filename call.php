<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;

$devices_=array();
$devices=array();

if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices_[]=$row['id'];
		$devices[$row['id']]=$row['title'];
	}
}


if ($_GET['delete']=='all') // Delete all Call | Удаление всех входящих вызовов
{
	$qry='DELETE FROM `call_incoming` 
	WHERE `device` in ('.implode(',',$devices_).')';
	mysqli_query($db,$qry);
	header('location:call.php');
	exit();
}
if ($_POST['delete']) // Deleting Call | Удаление входящего вызова
{
	if (count($_POST['check']))
	{
		foreach ($_POST['check'] as $data)
		{
			$qry='DELETE FROM `call_incoming` WHERE `device` in ('.implode(',',$devices_).') AND `id`='.(int)$data;
			mysqli_query($db,$qry);
		}
	}
}

sr_header("Список входящих вызовов"); // Output page title and title | Вывод титул и заголовок страницы

$table=array();
$where=array();
if (!$_GET['page']){$_GET['page']=1;}
$limit=' LIMIT '.((int)$GLOBALS['set_data']['page_limit']*($_GET['page']-1)).','.(int)$GLOBALS['set_data']['page_limit'];

if ($_GET['number'])
{
	$where[]="c.number LIKE '%".(int)$_GET['number']."%'";
}
if ($_GET['incoming'])
{
	$where[]="s.incoming LIKE '%".(int)$_GET['incoming']."%'";
}
if ($_GET['operator'])
{
	$where[]="(o1.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o1.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%')";
}
if ($_GET['device'])
{
	$where[]="c.device=".(int)$_GET['device'];
}
if ($_GET['place'])
{
	$where[]="c.place LIKE '".mysqli_real_escape_string($db,$_GET['place'])."%'";
}
if ($_GET['balance'])
{
	$a=str_replace(',','.',$_GET['balance']);
	if ($_GET['balance'][0]!='>' && $_GET['balance'][0]!='<')
	{
		$where[]="c.balance=".mysqli_real_escape_string($db,$a);
	}
	else
	{
		$where[]="c.balance".mysqli_real_escape_string($db,$a);
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
elseif ($_GET['sort']==3)
{
	$order=' ORDER BY c.`balance`,s.`time` DESC';
}
elseif ($_GET['sort']==4)
{
	$order=' ORDER BY c.`operator`';
}
elseif ($_GET['sort']==5)
{
	$order=' ORDER BY c.`number`,s.`time` DESC';
}
elseif ($_GET['sort']==6)
{
	$order=' ORDER BY s.`incoming`,s.`time` DESC';
}

	$operators=array();

$qry='SELECT count(s.id) AS counter FROM `call_incoming` s 
LEFT JOIN `cards` c ON s.`number`=c.`number` AND s.`done`=1
LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
LEFT JOIN `devices` d ON s.`device`=d.`id` 
WHERE s.`device` in ('.implode(',',$devices_).')'.$where;

if ($result = mysqli_query($db, $qry))
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$total=$row['counter'];	
	}
}

$qry='SELECT c.*, o1.`title` AS `operator_name`, o1.`color` AS `color`,s.incoming,s.time,s.number,s.id AS `call_id`,d.title AS device FROM `call_incoming` s
LEFT JOIN `cards` c ON (s.`number`=c.`number` OR s.`number`=c.`place`) AND s.`device`=c.`device`
LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
LEFT JOIN `devices` d ON s.`device`=d.`id` 
WHERE s.`number`<>"" AND s.`device` in ('.implode(',',$devices_).')'.$where.$order.$limit;
//echo $qry;
if ($result = mysqli_query($db, $qry)) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
		$table[]=array(
			'num'=>$n+$GLOBALS['set_data']['page_limit']*($_GET['page']-1),
			'number'=>$row['number'],
			'title'=>$row['title'],
			'time'=>srdate('d.m.Y H:i:s',$row['time']),
			'incoming'=>$row['incoming'],
			'call_id'=>$row['call_id'],
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
		if ($row['title']){$title_td=1;}
		$n++;
	}
}

$a=$_GET; unset($a['page']); 
if (empty($a) && count($table)){echo '<div id="filter_hint" onclick="fltr();">Отфильтровать</div>';} 
?>
<div id="filter"<? if (empty($a)){echo ' class="hide"';}?>>
<form method="get">
<div class="sidebar">
Номер телефона на который пришел вызов
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
<div class="sidebar">
<br>
Номер телефона с которого пришел вызов
</div>
<input type="text" name="incoming" value="<?=$_GET['incoming']?>" maxlength="15" placeholder="Входящий номер. Пример: 903">
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
<option value="6"<? if ($_GET['sort']==6){echo ' selected=1';}?>>По входящим номерам</option>
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
<form method="post" id="call" name="call">
<div class="table_box">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'call','check')"></th>
				<th class="sidebar">№</th>
				<? if ($title_td){?><th class="sidebar">Имя</th><? } ?>
				<? if (count($devices)>1){ ?>
				<th class="sidebar">Агрегатор</th>
				<? } ?>
				<th>Место</th>
				<th class="sidebar">Оператор</th>
				<th>На&nbsp;номер</th>
				<th>С&nbsp;номера</th>
				<th>Время</th>
			</tr>  
		</thead>
<?
$n=0;
foreach ($table as $data)
{
			if ($data['time_balance']){$balance=balance_out($data['balance'],'').'<div class="legend">'.srdate('d.m.Y H:i',$data['time_balance']).'</div>';} else {$balance='—';}
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['call_id']?>"></td>
			<td class="sidebar"><?=$data['num']?></td>
			<? if ($title_td){?><td class="sidebar"><?=$data['title']?></td><? } ?>
			<? if (count($devices)>1){ ?>
			<td class="sidebar" nowrap><?=$data['device']?></td>
			<? } ?>
			<td class="sidebar" align="right"><? if ($data['place']){echo $data['place'];} else {echo '—';} ?></td>
			<td class="exttab" align="right"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['place']?></td>
			<td class="sidebar"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center"><?=$data['operator_name']?></td>
			<td><? if ($data['number']){echo '+'.$data['number'];} else {echo '—';} if ($data['title']){?><span class="extinfo"><br><s><?=$data['title']?></s></span><? } ?>
			</td>
			<td><? if ($data['incoming']){echo '+'.$data['incoming'];} else {echo '—';}?></td>
			<td><?=$data['time']?></td>
		</tr>
<?
}
?>
	</table>
</div>
<?=$scroller=scrollbar($total,$_GET['page'],$GLOBALS['set_data']['page_limit'],'page');?>
<br>
<input type="submit" name="delete" value="Удалить отмеченные вызовы" class="width">
<a href="call.php?delete=all" class="link width" style="background:#FF0000;">Удалить все вызовы</a>
</form>
<?
}
else
{
?>
<div class="tooltip">— Список входящих вызовов пуст!</div>
<?
}

sr_footer();
?>