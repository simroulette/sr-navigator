<?
include("_func.php");
$status=1;
if ($_GET['delete']=='all') // Delete all SMS | Удаление всех SMS
{
	$qry="TRUNCATE TABLE `sms_incoming`";
	mysqli_query($db,$qry);
}
if ($_POST['delete']) // Deleting SMS | Удаление SMS
{
	if (count($_POST['check']))
	{
		foreach ($_POST['check'] as $data)
		{
			$qry="DELETE FROM `sms_incoming` WHERE `id`=".(int)$data;
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
	$where[]="(o.title LIKE '%".$_GET['operator']."%' OR o.name LIKE '%".$_GET['operator']."%')";
}
if ($_GET['device'])
{
	$where[]="c.device=".(int)$_GET['device'];
}
if ($_GET['place'])
{
	$where[]="c.place LIKE '".$_GET['place']."%'";
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
if (count($where)){$where='WHERE '.implode(' AND ',$where);} else {$where='';}

if (!$_GET['sort'])
{
	$order=' ORDER BY c.`number`';
}
elseif ($_GET['sort']==1)
{
	$order=' ORDER BY c.`device`';
}
elseif ($_GET['sort']==2)
{
	$order=' ORDER BY c.`place`';
}
elseif ($_GET['sort']==3)
{
	$order=' ORDER BY c.`balance`';
}
elseif ($_GET['sort']==4)
{
	$order=' ORDER BY c.`operator`';
}
elseif ($_GET['sort']==5)
{
	$order=' ORDER BY s.`time` DESC';
}

if ($result = mysqli_query($db, 'SELECT count(s.id) AS counter FROM `cards` c 
INNER JOIN `sms_incoming` s ON s.`number`=c.`number` 
LEFT JOIN `operators` o ON c.`operator`=o.`id` 
LEFT JOIN `devices` d ON c.`device`=d.`id` 
'.$where))
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$total=$row['counter'];	
	}
}

if ($result = mysqli_query($db, 'SELECT c.*,o.title,s.sender,s.time,s.txt,s.id AS sms_id,o.color,d.title AS device FROM `cards` c 
INNER JOIN `sms_incoming` s ON s.`number`=c.`number` 
LEFT JOIN `operators` o ON c.`operator`=o.`id` 
LEFT JOIN `devices` d ON c.`device`=d.`id` 
'.$where.$order.$limit)) 
{
	$n=1;
	while ($row = mysqli_fetch_assoc($result))
	{
		if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
		$txt=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$row['txt']);
		$table[]=array(
			'num'=>$n+$GLOBALS['set_data']['page_limit']*($_GET['page']-1),
			'number'=>$row['number'],
			'time'=>date('d.m.Y H:i:s',$row['time']),
			'sender'=>$row['sender'],
			'sms'=>$txt,
			'sms_id'=>$row['sms_id'],
			'model'=>$row['model'],
			'device'=>$row['device'],
			'place'=>$row['place'],
			'operator'=>$row['title'],
			'balance'=>$row['balance'],
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
?>
<br>
<form method="get">
<div class="sidebar">
Номер телефона
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
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
Устройство
</div>
<select name="device">
	<option value="0">Все устройства</option>
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
Баланс (&lt;&gt;)
</div>
<input type="text" name="balance" value="<?=$_GET['balance']?>" maxlength="8" placeholder="Баланс. Пример: >100">
<br>
<div class="sidebar">
<br>
Отсортировать
</div>
<select name="sort">
<option value="0"<? if (!$_GET['sort']){echo ' selected=1';}?>>По номерам телефонов</option>
<option value="1"<? if ($_GET['sort']==1){echo ' selected=1';}?>>По устройствам</option>
<option value="2"<? if ($_GET['sort']==2){echo ' selected=1';}?>>По местам</option>
<option value="3"<? if ($_GET['sort']==3){echo ' selected=1';}?>>По балансам</option>
<option value="4"<? if ($_GET['sort']==4){echo ' selected=1';}?>>По операторам</option>
<option value="5"<? if ($_GET['sort']==5){echo ' selected=1';}?>>По времени</option>
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
<?
if (count($table))
{
?>
<form method="post" id="sms" name="sms">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'sms','check')"></th>
				<th class="sidebar">№</th>
				<th>Номер</th>
				<th class="sidebar">Устройство</th>
				<th class="sidebar">Место</th>
				<th class="sidebar">Оператор</th>
				<th class="sidebar">Баланс</th>
				<th>От</th>
				<th>SMS</th>
				<th>Время</th>
			</tr>  
		</thead>
<?
$n=0;
foreach ($table as $data)
{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['sms_id']?>"></td>
			<td class="sidebar"><?=$data['num']?></td>
			<td>+<?=$data['number']?></td>
			<td class="sidebar"><?=$data['device']?></td>
			<td class="sidebar"><?=$data['place']?></td>
			<td class="sidebar"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center"><?=$data['operator']?></td>
			<td align="right" class="sidebar"><?=$data['balance']?></td>
			<td><?=$data['sender']?></td>
			<td><?=$data['sms']?></td>
			<td><?=$data['time']?></td>
		</tr>
<?
}
?>
	</table>
<br>
<input type="submit" name="delete" value="Удалить отмеченные SMS" style="float:left; margin-right: 10px">
<a href="sms.php?delete=all" class="link" style="background:#FF0000; margin: 0 5px 10px 0">Удалить все SMS</a>
</form>
<?
}
else
{
?>
<em>— Список SMS пуст!</em>
<?
}

sr_footer();
?>