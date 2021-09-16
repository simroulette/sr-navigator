<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");

if ($_POST['delete']) // Deletes the selected actions | Удаление отмеченных задач
{
	foreach ($_POST['check'] as $data)
	{
		$qry="DELETE FROM `folders` WHERE `id`=".(int)$data;
		mysqli_query($db,$qry);
		$qry="DELETE FROM `cards2folder` WHERE `folder_id`=".(int)$data;
		mysqli_query($db,$qry);
	}
}

$status=1;
if ($_GET['edit'] && $_POST['save']) // Editing the Folder | Редактирование Диска
{
	if ($_POST['title'])
	{
		mysqli_query($db, "UPDATE `folders` SET `time`=".time().",`title`='".$_POST['title']."',`comment`='".$_POST['comment']."' WHERE `id`=".(int)$_GET['edit']);  
	}
	else
	{
		mysqli_query($db, "UPDATE `folders` SET `time`=".time().",`comment`='".$_POST['comment']."' WHERE `id`=".(int)$_GET['edit']);  
	}
	header('location:folders.php');
	exit();
}
if ($_GET['change']) // Changing the disk | Смена диска
{
	if ($_POST['save'])
	{
		if (!$_POST['folder_id'])
		{
			if (!$_POST['title']){$_POST['title']='Новый диск';}
			// Создаем новый диск
			$qry="INSERT INTO `folders` SET `time`=".time().",`title`='".$_POST['title']."',`comment`='".$_POST['comment']."'";
			mysqli_query($db, $qry);  
			$_POST['folder_id']=mysqli_insert_id($db);
		}

		// Копируем c активного диска карты на сменный диск (временно)
		if ($result = mysqli_query($db, "SELECT c.* FROM `devices` d
		INNER JOIN `cards` c ON c.`device`=d.`id`
		WHERE d.`id`=".(int)$_GET['change']))  
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				$qry="INSERT `cards2folder` SET
				`iccid`='".$row['iccid']."',
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
				`folder_id`='".((int)$_POST['folder_id']*-1)."'";
				$status=mysqli_query($db,$qry);
			}
		}
		// Удаляем данные карт с активного диска
		mysqli_query($db, "DELETE FROM `cards` WHERE `device`=".(int)$_GET['change']);  

		// Копируем cо сменного диска карты на активный диск
		if ($result = mysqli_query($db, "SELECT * FROM `cards2folder` 
		WHERE `folder_id`=".(int)$_POST['folder_id']))  
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				// Копируем в цикле карты на новый диск
				$qry="INSERT `cards` SET
				`iccid`='".$row['iccid']."',
				`number`='".$row['number']."',
				`title`='".$row['title']."',
				`operator`='".$row['operator']."',
				`device`=".(int)$_GET['change'].",
				`balance`='".$row['balance']."',
				`place`='".$row['place']."',
				`time`='".$row['time']."',
				`time_number`='".$row['time_number']."',
				`time_balance`='".$row['time_balance']."',
				`time_sms`='".$row['time_sms']."',
				`comment`='".$row['comment']."'";
				$status=mysqli_query($db,$qry);
			}
		}
		// Удаляем скопированные данные карт с активного диска
		mysqli_query($db, "DELETE FROM `cards2folder` WHERE `folder_id`>0 AND `folder_id`=".(int)$_POST['folder_id']);  

		// Превращаем временные данные в постоянные
		mysqli_query($db, "UPDATE `cards2folder` SET `folder_id`=`folder_id`*-1 WHERE `folder_id`<0");  

		$disk=array();
		if ($result = mysqli_query($db, "SELECT * FROM `folders` WHERE `id`=".(int)$_POST['folder_id']))  
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$disk['folder']=$row['id'];
				$disk['title']=$row['title'];
				$disk['time']=$row['time'];
				$disk['comment']=$row['comment'];
			}
		}

		$folder=array();
		if ($result = mysqli_query($db, "SELECT `folder`,`time` FROM `devices` WHERE `id`=".(int)$_GET['change']))  
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$folder=unserialize($row['folder']);
				if (!$folder['time']){$folder['time']=$row['time'];}
				if (!$folder['title']){$folder['title']='default';}
			}
		}
		// Обновляем данные о диске в устройстве
		mysqli_query($db, "UPDATE `devices` SET `folder`='".serialize($disk)."' WHERE `id`=".(int)$_GET['change']);  

		// Обновляем данные о извлеченном диске
		mysqli_query($db, "UPDATE `folders` SET `time`=".(int)$folder['time'].",`title`='".$folder['title']."',`comment`='".$folder['comment']."' WHERE `id`=".(int)$_POST['folder_id']);  

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

	$folders=array();
	if ($result = mysqli_query($db, 'SELECT * FROM `folders`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$folders[$row['id']]=$row['title'].' ('.srdate('d.m.Y H:i',$row['time']).')';
		}
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
Установить диск
<br>
<select name="folder_id" onchange="if (this.value==0){document.getElementById('new').style.display='block';}">
<option value="none">— выберите из списка —</option>
	<option value="0">[Новый диск]</option>
<?
	foreach ($folders as $id=>$title)
	{
?>
	<option value="<?=$id?>"><?=$title?></option>
<?
	}
?>
</select>
<br><br>
<div id="new" style="display:none;">
Название диска
<br>
<input type="text" name="title" maxlength="20">
<br><br>
Комментарий
<br>
<input type="text" name="comment" maxlength="200">
<br><br>
</div>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>

<?
}
elseif ($_GET['edit'])
{
	$title='';
	if ($result = mysqli_query($db, 'SELECT * FROM `folders`  
	WHERE `id`='.(int)$_GET['edit'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$title=$row['title'];
			$comment=$row['comment'];
		}
	}
	if (!$title)
	{
		header('location:folders.php');
		exit();
	}
	sr_header("Редактирование диска ".$title); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<form method="post">
Название диска
<br>
<input type="text" name="title" value="<?=$title?>" maxlength="20">
<br><br>
Комментарий
<br>
<input type="text" name="comment" value="<?=$comment?>" maxlength="200">
<br><br>
</div>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else
{
	$table=array();
	$folders=array();

	if ($result = mysqli_query($db, "SELECT d.*,count(c.id) AS `cards` FROM `devices` d 
	LEFT JOIN `cards` c ON c.`device`=d.`id`
	WHERE d.`model` LIKE '%nano%' GROUP BY d.`id` ORDER BY d.`id`"))  
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$disk=unserialize($row['folder']);
			if (!$disk['time']){$disk['time']=$row['time'];}
			$table[]=array(
				'id'=>$row['id'],
				'device'=>$row['title'],
				'title'=>$disk['title'],
				'time'=>$disk['time'],
				'comment'=>$disk['comment'],
				'cards'=>$row['cards'],
			);
		}
	}

	if ($result = mysqli_query($db, "SELECT f.*,count(c.id) AS `cards` FROM `folders` f
	LEFT JOIN `cards2folder` c ON c.`folder_id`=f.`id` 
	GROUP BY f.`id` ORDER BY f.`id`
	"))  
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'folder_id'=>$row['id'],
				'time'=>$row['time'],
				'comment'=>$row['comment'],
				'cards'=>$row['cards'],
			);
		}
	}
	sr_header("Список дисков"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<?
	if (count($table))
	{
?>
<form method="post" name="folders" id="folders">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'folders','check')"></th>
				<th class="sidebar">№</th>
				<th>Агрегатор</th>
				<th>Диск</th>
				<th style="text-align:right;">Карты</th>
				<th class="sidebar">Время</th>
				<th class="sidebar">Комментарий</th>
				<th></th>
			</tr>  
		</thead>
<?
		$n=1;
		$folder=0;
		foreach ($table as $data)
		{
/*
			if (!$folder=$folders[$data['folder_id']])
			{
				$folder='default';
			}
			else
			{
				unset($folders[$data['folder_id']]);
			}
*/								
			if (!$data['device']){$data['device']='—';} else {$data['device']='<a href="folders.php?change='.$data['id'].'" title="Сменить диск">'.$data['device'].'</a>';}

			if ($data['device']=='—' && !$folder)
			{
				$folder=1;
				
?>
		<tr>
			<td colspan="4">ДОПОЛНИТЕЛЬНЫЕ ДИСКИ:</td>
			<td class="sidebar" colspan="3"></td>
			<td></td>
		</tr>
<?
			}
?>
		<tr>
			<td><? if ($data['device']=='—'){?><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['id']?>"><? } ?></td>
			<td class="sidebar"><?=$n?></td>
			<td><?=$data['device']?></td>
			<td><?=$data['title']?></td>
			<td align="right"><?=$data['cards']?></td>
			<td class="sidebar"><?=srdate('d.m.Y H:i',$data['time'])?></td>
			<td class="sidebar"><em><?=$data['comment']?></em></td>
			<td><? if ($data['device']=='—'){echo '<a href="folders.php?edit='.$data['id'].'" title="Редактировать диск"><i class="icon-pencil"></i></a>';} ?></td>
		</tr>
<?
		}
?>
	</table>
<?
	if ($folder)
		{
?>
<br>
<input type="submit" name="delete" value="Удалить отмеченные диски" style="background: #FF0000;">
<?
		}
?>
</form>
<?
	}
	else
	{
?>
<em>— Сначала нужно добавить в список свой агрегатор SR-Nano!</em>
<br><br>
<a href="devices.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить Агрегатор</a>
<?
	}
}

sr_footer();
?>