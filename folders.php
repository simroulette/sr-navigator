<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the Folder | Удаление Диска
{
	$qry="DELETE FROM `folders` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
$_GET['folder_id']=1;
if ($_GET['change']) // Editing the Folder | Редактирование Диска
{
	if ($_POST['save'])// && $_POST['folder'])
	{
		if ($result = mysqli_query($db, "SELECT c.* FROM `devices` d
		LEFT JOIN `cards` c ON c.`device`=d.`id`
		WHERE d.`id`=".(int)$_GET['change']))  
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				// Удаляем из текущей таблицы
				$qry="DELETE FROM `cards` WHERE `id`=".$row['id'];
				mysqli_query($qry);

				// Копируем в цикле карты на новый диск
				$qry="INSERT `cards2folder` SET
				`number`='".$row['number']."',
				`title`='".$row['title']."',
				`operator`='".$row['operator']."',
				`balance`='".$row['balance']."',
				`place`='".$row['place']."',
				`time`='".$row['time']."',
				`time_number`='".$row['time_number']."',
				`time_balance`='".$row['time_balance']."',
				`time_sms`='".$row['time_sms']."',
				`comment`='".$row['comment']."',
				`folder_id`='".(int)$_GET['folder_id']."'";
				$status=mysqli_query($db,$qry);
			}
		}
		if ($result = mysqli_query($db, "SELECT * FROM `cards2folder`
		WHERE `folder_id`=".(int)$_GET['folder_id']))  
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				// Удаляем из текущей таблицы
				$qry="DELETE FROM `cards2folder` WHERE `id`=".$row['id'];
				mysqli_query($qry);

				// Копируем в цикле карты на новый диск
				$qry="INSERT `cards` SET
				`number`='".$row['number']."',
				`title`='".$row['title']."',
				`operator`='".$row['operator']."',
				`device`='".(int)$_GET['change']."',
				`balance`='".$row['balance']."',
				`place`='".$row['place']."',
				`time`='".$row['time']."',
				`time_number`='".$row['time_number']."',
				`time_balance`='".$row['time_balance']."',
				`time_sms`='".$row['time_sms']."',
				`comment`='".$row['comment']."',
				`folder_id`='".(int)$_GET['folder_id']."'";
				$status=mysqli_query($db,$qry);
			}
		}

		if ($status)
		{			
			header('location:folders.php');
			exit();
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	if ($result = mysqli_query($db, 'SELECT d.`id`,d.`title`,d.`folder_id`,f.`title` AS `folder` FROM `devices` d 
	LEFT JOIN `folders` f ON f.`id`=d.`folder_id`
	WHERE d.`id`='.(int)$_GET['change'])) 
	{
		$device = mysqli_fetch_assoc($result);
	}
	if ($device['id'])
	{
		if (!$folder=$device['folder']){$folder='default';}
		$folder=$device['title'].' → '.$folder;
	}
	sr_header('Смена диска '.$folder); // Output page title and title | Вывод титула и заголовка страницы

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
Новый диск
<br>
<select name="folder">
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
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>

<?
}
else
{
	$table=array();
	$folders=array();
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
	if ($_GET['title'])
	{
		$where[]="c.title LIKE '%".$_GET['title']."%'";
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

	if ($result = mysqli_query($db, "SELECT d.*,count(c.id) AS `cards` FROM `devices` d
	LEFT JOIN `cards` c ON c.`device`=d.`id`
	WHERE d.`model` LIKE '%nano%' GROUP BY d.`id`
	"))  
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'folder_id'=>$row['folder_id'],
				'cards'=>$row['cards'],
			);
		}
	}

	if ($result = mysqli_query($db, 'SELECT * FROM `folders`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$folders[$row['id']]=$row['title'];
		}
	}

	sr_header("Список Дисков"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
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
				<th>Агрегатор</th>
				<th>Диск</th>
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
			if (!$folder=$folders[$data['folder_id']])
			{
				$folder='default';
			}
			else
			{
				unset($folders[$data['folder_id']]);
			}
								
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['number'].';'.$data['place'].';'.$data['device']?>"></td>
			<td class="sidebar"><?=$n?></td>
			<td><?=$data['title']?></td>
			<td><a href="folders.php?change=<?=$data['id']?>&folder=<?=$data['folder_id']?>" title="Сменить диск"><?=$folder?></a></td>
			<td><?=$data['cards']?></td>
			<td class="sidebar"><?=$data['status']?></td>
		</tr>
<?
		}
		foreach ($folders as $data)
		{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['number'].';'.$data['place'].';'.$data['device']?>"></td>
			<td class="sidebar"><?=$n?></td>
			<td>—</td>
			<td><?=$data?></td>
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