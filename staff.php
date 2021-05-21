<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the staffer | Удаление сотрудника
{
	$qry="DELETE FROM `staff` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['edit']) // Editing the staffer | Редактирование сотрудника
{
	if ($_POST['save'] && $_POST['login'] && $_POST['pass'] && $_POST['name'])
	{
		$_POST['color']=trim($_POST['color'],'#');
		if (!$_POST['color']){$_POST['color']='000000';}

		$qry='';
		if ($_GET['edit']!='new')
		{
			$qry="UPDATE `staff` SET
			`name`='".$_POST['name']."',
			`pass`='".$_POST['pass']."',
			`pool`=".(int)$_POST['pool'].",
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
			mysqli_query($db,$qry);
		}

		if ($_GET['edit']=='new')
		{
			$qry="INSERT `staff` SET
			`name`='".$_POST['name']."',
			`login`='".$_POST['login']."',
			`pass`='".$_POST['pass']."',
			`pool`=".(int)$_POST['pool'].",
			`time`='".time()."'";
			mysqli_query($db,$qry);
			if (mysqli_insert_id($db)) 
			{			
				header('location:staff.php');
				exit();
			}
			else    	
			{
				$status='pass';
			}
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	sr_header("Редактирование аккаунта сотрудника"); // Output page title and title | Вывод титул и заголовок страницы

	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `staff` WHERE `id`='.(int)$_GET['edit'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$_POST['name']=$row['name'];
				$_POST['login']=$row['login'];
				$_POST['pass']=$row['pass'];
				$_POST['pool']=$row['pool'];
			}
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
else if ($status=='pass')
{
?>
<div class="status_error">Логин уже используется!</div>
<?
}
?>
<form method="post">
Имя сотрудника
<br>
<input type="text" name="name" value="<?=$_POST['name']?>" maxlength="60">
<br><br>
Логин
<br>
<input type="text" name="login" value="<?=$_POST['login']?>" maxlength="32">
<br><br>
Пароль
<br>
<input type="text" name="pass" value="<?=$_POST['pass']?>" maxlength="32">
<br><br>
Пул
<br>
<select name="pool">
<option value="0">— выберите из списка —</option>
<?
	$pools=array();
	if ($result = mysqli_query($db, 'SELECT * FROM `pools` ORDER BY `title`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
?>
	<option value="<?=$row['id']?>"<? if ($row['id']==$_POST['pool']){echo ' selected=1';}?>><?=$row['title']?></option>
<?
		}
	}
?>
</select>
<br><br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else // List of staffers | Список сотрудников
{
	sr_header("Сотрудники");
	$table=array();
	if ($result = mysqli_query($db, 'SELECT s.*,p.title FROM `staff` s LEFT JOIN `pools` p ON p.`id`=s.`pool` ORDER BY s.`name`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (!$row['title']){$row['title']='—';}
			$table[]=array(
				'id'=>$row['id'],
				'name'=>$row['name'],
				'login'=>$row['login'],
				'pool'=>$row['title'],
				'time'=>$row['time'],
			);
		}
	}
	if (count($table))
	{
?>
<br>
<table class="table table_adaptive">
	<thead>
		<tr><th>Сотрудник</th><th>Логин</th><th>Пул</th><th></th></tr>  
	</thead>
<?
	foreach ($table as $data)
{
?>
	<tr>
	<td><?=$data['name']?></td>
	<td><?=$data['login']?></td>
	<td><?=$data['pool']?></td>
	<td><a href="staff.php?edit=<?=$data['id']?>" title="Редактировать профайл сотрудника"><i class="icon-pencil"></i></a> <a href="javascript:void();" onclick="if (confirm('Вы готовы удалить сотрудника?')){document.location='staff.php?delete=<?=$data['id']?>';}" title="Удалить сотрудника"><i class="icon-trash"></i></a></td>
	</tr>
<?
}
?>
</table>
<?
	}
	else
	{
?>
<br>
<em>— Список сотрудников пуст!</em>
<br>
<?
	}
?>
<br>
<a href="staff.php?edit=new" class="link">Добавить сотрудника</a>
<?
}

sr_footer();
?>