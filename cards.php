<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the SIM card | Удаление СИМ-карты
{
	$qry="DELETE FROM `cards` WHERE `number`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['edit']) // Editing a SIM card | Редактирование СИМ-карты
{
	if ($_POST['save'] && $_POST['number'] && $_POST['place'] && $_POST['device'])
	{
		if ($_GET['edit']=='new')
		{
			$qry="INSERT `cards` SET
			`number`='".trim($_POST['number'],'+')."',
			`place`='".$_POST['place']."',
			`device`='".$_POST['device']."',
			`operator`='".$_POST['operator']."',
			`balance`='".$_POST['balance']."',
			`comment`='".$_POST['comment']."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `cards` SET
			`number`='".trim($_POST['number'],'+')."',
			`place`='".$_POST['place']."',
			`device`='".$_POST['device']."',
			`operator`='".$_POST['operator']."',
			`balance`='".$_POST['balance']."',
			`comment`='".$_POST['comment']."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
		}
		if ($status=mysqli_query($db,$qry))
		{			
			header('location:cards.php');
			exit();
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	sr_header('Редактирование СИМ-карты','win_action'); // Output page title and title | Вывод титул и заголовок страницы
	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `cards` WHERE `id`='.(int)$_GET['edit'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$number='+'.$row['number'];
				$place=$row['place'];
				$operator=$row['operator'];
				$device=$row['device'];
				$balance=$row['balance'];
				$comment=$row['comment'];
				$id=$row['id'];
			}
		}
	}

	$operators=array();
	if ($result = mysqli_query($db, 'SELECT * FROM `operators` ORDER BY `title`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$operators[$row['id']]=$row['title'];
		}
	}

	$devices=array();
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
if (!$status)
{
?>
<div class="status_error">При сохранении данных произошла ошибка, проверьте правильность заполнения полей!</div>
<?
}
?>
<form method="post">
Номер телефона (обязательное поле)
<br>
<input type="text" name="number" value="<?=$number?>" maxlength="15">
<br><br>
Место, например: A0 для SR-Nano или 2-8 для SR-Train (обязательное поле)
<br>
<input type="text" name="place" value="<?=$place?>" maxlength="7">
<br><br>
Оператор
<br>
<select name="operator">
<option value="none">- выберите из списка -</option>
<?
	foreach ($operators as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($operator==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<br><br>
Устройство (обязательное поле)
<br>
<select name="device">
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($device==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<br><br>
Баланс (&lt;&gt;)
<br>
<input type="text" name="balance" value="<?=$balance?>" maxlength="10">
<br><br>
Комментарий
<br>
<textarea name="comment" style="height: 100px;"><?=$comment?></textarea>
<br><br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>

<?
}
else
{
	$table=array();
	$where=array();
	$limit='';
	$order='';
	if ($_GET['page']!='all')
	{
		if (!$_GET['page']){$_GET['page']=1;}
		$limit=' LIMIT '.((int)$GLOBALS['set_data']['page_limit']*($_GET['page']-1)).','.(int)$GLOBALS['set_data']['page_limit'];
	}
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
		$order=' ORDER BY c.`time` DESC';
	}

	if (count($where)){$where='WHERE '.implode(' AND ',$where);} else {$where='';}

	if ($result = mysqli_query($db, 'SELECT count(c.`number`) AS counter FROM `cards` c 
	LEFT JOIN `operators` o ON c.`operator`=o.`id` 
	LEFT JOIN `devices` d ON c.`device`=d.`id` 
	'.$where)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$total=$row['counter'];	
		}
	}
	if ($result = mysqli_query($db, 'SELECT c.*,o.title AS operator,o.id AS operator_id,o.color,d.title AS device_name FROM `cards` c 
	LEFT JOIN `operators` o ON c.`operator`=o.`id` 
	LEFT JOIN `devices` d ON c.`device`=d.`id` 
	'.$where.$order.$limit)) 
	{
		$n=1;
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
			if ($row['status']=='inprogress')
			{
				$row['status']='Процесс';
			}
			else if ($row['status']=='waiting')
			{
				$row['status']='В очереди';
			}
			else
			{
				$row['status']='';
			}
			if ($_GET['page']=='all'){$pnum++;} else {$pnum=$n+$GLOBALS['set_data']['page_limit']*($_GET['page']-1);}
			$table[]=array(
				'num'=>$pnum,
				'id'=>$row['id'],
				'number'=>$row['number'],
				'time'=>date('d.m.Y H:i:s',$row['time']),
				'model'=>$row['model'],
				'dev'=>$row['device'],
				'device'=>$row['device_name'],
				'place'=>$row['place'],
				'operator'=>$row['operator'],
				'operator_id'=>$row['operator_id'],
				'status'=>$row['status'],
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
	if ($_GET['type']=='csv')
	{
		header('Content-Type:csv/plain');
		echo "Номер\tАгрегатор\tID\tМесто\tБаланс\tОператор\tID\tВремя
";
		foreach ($table as $data)
		{
			if ($data['number']==$data['place'])
			{
				echo "Блокировка\t".$data['device']."\t".$data['dev']."\tP:".$data['place']."\t".$data['balance']."\t—\t—\t".$data['time'].'
';
			}
			else
			{
				echo '+'.$data['number']."\t".$data['device']."\t".$data['dev']."\tP:".$data['place']."\t".$data['balance']."\t".$data['operator']."\t".$data['operator_id']."\t".$data['time'].'
';
			}
		}
		exit();
	}
	sr_header("Список СИМ-карт"); // Output page title and title | Вывод титул и заголовок страницы
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
Баланс
</div>
<input type="text" name="balance" value="<?=$_GET['balance']?>" maxlength="8" placeholder="Баланс. Пример: >100">
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
<option value="all"<? if ($_GET['page']=='all'){echo ' selected=1';}?>>Все</option>
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
<form method="post" action="pools.php?edit=new" id="cards" name="cards">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'cards','check')"></th>
				<th class="sidebar">№</th>
				<th>Номер</th>
				<th class="sidebar">Устройство</th>
				<th style="text-align:right;">Место</th>
				<th style="text-align:right;" class="sidebar">Баланс</th>
				<th class="sidebar">Оператор</th>
				<th class="sidebar">Время</th>
				<th>Действие</th>
				<th style="text-align: center;" class="sidebar">Статус</th>
			</tr>  
		</thead>
<?
		$n=0;
		foreach ($table as $data)
		{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['number']?>"></td>
			<td class="sidebar"><?=$data['num']?></td>
			<?
			if ($data['place']!=$data['number']){
			?>
			<td><span class="but_win" data-id="win_action" data-title="Управление номером +<?=$data['number']?>" data-type="ajax_card_action.php?id=<?=$data['number']?>" data-height="400" data-width="600">+<?=$data['number']?></span></td>
			<? } else { ?>
			<td><em>Карта заблокирована</em></td>
			<? } ?>
			<td class="sidebar"><?=$data['device']?></td>
			<td align="right"><?=$data['place']?></td>
			<td align="right" class="sidebar"><?=$data['balance']?></td>
			<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center" class="sidebar"><?=$data['operator']?></td>
			<td class="sidebar"><?=$data['time']?></td>
			<td><a href="cards.php?edit=<?=$data['id']?>"><i class="icon-pencil"></i></a> <a href="cards.php?delete=<?=$data['number']?>"><i class="icon-trash"></i></a></td>
			<td class="sidebar"><?=$data['status']?></td>
		</tr>
<?
		}
?>
	</table>

<br>
<input type="submit" name="add" value="Создать пул" style="float:left; margin: 0 10px 10px 0">
<a href="cards.php?edit=new" class="link" style=" margin: 0 5px 10px 0">Добавить СИМ-карту</a>
<span class="link but_win" data-id="win_action" data-title="Сканирование диапазона СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600" style=" margin: 0 5px 10px 0">Сканирование диапазона</span>
<a class="link" href="<?
if (strpos($_SERVER['REQUEST_URI'],'?'))
{
	echo $_SERVER['REQUEST_URI'].'&type=csv';
}
else
{
	echo $_SERVER['REQUEST_URI'].'?type=csv';
}
?>">Экспорт в CSV</a>
</form>

<?
	}
	else
	{
?>
<em>— Список СИМ-карт пуст!</em>
<br><br>
<a href="cards.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить СИМ-карту</a>
<span class="link but_win" data-id="win_action" data-title="Сканирование диапазона СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканирование диапазона</span>
<?
	}
}

sr_footer();
?>