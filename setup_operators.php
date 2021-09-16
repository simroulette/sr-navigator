<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the operator | Удаление оператора
{
	$qry="DELETE FROM `operators` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['edit']) // Editing the operator | Редактирование оператора
{
	if ($_POST['save'] && $_POST['title'] && $_POST['name'])
	{
		$_POST['color']=trim($_POST['color'],'#');
		if (!$_POST['color']){$_POST['color']='000000';}
		$_POST['color_r']=trim($_POST['color_r'],'#');
		if (!$_POST['color_r']){$_POST['color_r']='000000';}

		$qry='';
		if ($_GET['edit']!='new')
		{
			if ($result = mysqli_query($db, 'SELECT * FROM `operators` WHERE `id`='.(int)$_GET['edit'])) 
			{
				if ($row = mysqli_fetch_assoc($result))
				{
					$qry="UPDATE `operators` SET
					`title`='".$_POST['title']."',
					`title_r`='".$_POST['title_r']."',
					`prefix`='".$_POST['prefix']."',
					`prefix_r`='".$_POST['prefix_r']."',
					`name`='".strtoupper($_POST['name'])."',
					`get_number`='".trim($_POST['get_number'])."',
					`get_number_type`='".$_POST['get_number_type']."',
					`get_balance`='".trim($_POST['get_balance'])."',
					`get_balance_type`='".$_POST['get_balance_type']."',
					`get_number_r`='".$_POST['get_number_r']."',
					`get_number_type_r`='".$_POST['get_number_type_r']."',
					`get_balance_r`='".$_POST['get_balance_r']."',
					`get_balance_type_r`='".$_POST['get_balance_type_r']."',
					`color`='".$_POST['color']."',
					`color_r`='".$_POST['color_r']."',
					`time`='".time()."'
					WHERE `id`=".(int)$_GET['edit'];
				}
			}
			if (!$qry){$_GET['edit']='new';}
		}

		if ($_GET['edit']=='new')
		{
			$qry="INSERT `operators` SET
			`title`='".$_POST['title']."',
			`title_r`='".$_POST['title_r']."',
			`prefix`='".$_POST['prefix']."',
			`prefix_r`='".$_POST['prefix_r']."',
			`name`='".strtoupper($_POST['name'])."',
			`get_number`='".trim($_POST['get_number'])."',
			`get_number_type`='".$_POST['get_number_type']."',
			`get_balance`='".trim($_POST['get_balance'])."',
			`get_balance_type`='".$_POST['get_balance_type']."',
			`get_number_r`='".$_POST['get_number_r']."',
			`get_number_type_r`='".$_POST['get_number_type_r']."',
			`get_balance_r`='".$_POST['get_balance_r']."',
			`get_balance_type_r`='".$_POST['get_balance_type_r']."',
			`color`='".$_POST['color']."',
			`color_r`='".$_POST['color_r']."',
			`time`='".time()."'";
		}
		if (mysqli_query($db,$qry))
		{			
			operator_select();
			header('location:setup_operators.php');
			exit();
		}

	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	sr_header("Редактирование оператора"); // Output page title and title | Вывод титул и заголовок страницы

	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `operators` WHERE `id`='.(int)$_GET['edit'])) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				$title=$row['title'];
				$title_r=$row['title_r'];
				$prefix=$row['prefix'];
				$prefix_r=$row['prefix_r'];
				$name=$row['name'];
				$get_number=$row['get_number'];
				$get_number_type=$row['get_number_type'];
				$get_balance=$row['get_balance'];
				$get_balance_type=$row['get_balance_type'];
				$get_number_r=$row['get_number_r'];
				$get_number_type_r=$row['get_number_type_r'];
				$get_balance_r=$row['get_balance_r'];
				$get_balance_type_r=$row['get_balance_type_r'];
				$color=$row['color'];
				$color_r=$row['color_r'];
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
?>
<form method="post">
Название оператора
<br>
<input type="text" name="title" value="<?=$title?>" maxlength="60">
<br><br>
Цвет оператора <input type="color" name="color" value="#<?=$color?>">
<br><br>
Имя оператора в сети (обязательное поле)
<br>
<input type="text" name="name" value="<?=$name?>" maxlength="32">
<br><br>
Префикс телефонного номера, например 7 (без +)
<br>
<input type="number" name="prefix" value="<?=$prefix?>" maxlength="6">
<br><br>
Команда для запроса номера, например: *110*10#
<br>
<input type="text" name="get_number" value="<?=$get_number?>" maxlength="32">
<br><br>
Тип получения номера
<br>
<select name="get_number_type">
<option value="none">- выберите из списка -</option>
<option value="ussd"<? if ($get_number_type=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_number_type=='sms'){echo ' selected=1';}?>>SMS</option>
</select>
<br><br>
Команда для запроса баланса, например: #102#
<br>
<input type="text" name="get_balance" value="<?=$get_balance?>" maxlength="32">
<br><br>
Тип получения баланса
<br>
<select name="get_balance_type">
<option value="none">- выберите из списка -</option>
<option value="ussd"<? if ($get_balance_type=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_balance_type=='sms'){echo ' selected=1';}?>>SMS</option>
</select>
<br><br>
<h3>Для СИМ-карт в роуминге</h3>
Название оператора
<br>
<input type="text" name="title_r" value="<?=$title_r?>" maxlength="60">
<br><br>
Цвет оператора
<input type="color" name="color_r" value="#<?=$color_r?>">
<br><br>
Префикс телефонного номера, например 7 (без +)
<br>
<input type="number" name="prefix_r" value="<?=$prefix_r?>" maxlength="6">
<br><br>
Команда для запроса номера, например: *000#
<br>
<input type="text" name="get_number_r" value="<?=$get_number_r?>" maxlength="32">
<br><br>
Тип получения номера
<br>
<select name="get_number_type_r">
<option value="none">- выберите из списка -</option>
<option value="ussd"<? if ($get_number_type_r=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_number_type_r=='sms'){echo ' selected=1';}?>>SMS</option>
</select>
<br><br>
Команда для запроса баланса, например: *001#
<br>
<input type="text" name="get_balance_r" value="<?=$get_balance_r?>" maxlength="32">
<br><br>
Тип получения баланса
<br>
<select name="get_balance_type_r">
<option value="none">— выберите из списка —</option>
<option value="ussd"<? if ($get_balance_type_r=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_balance_type_r=='sms'){echo ' selected=1';}?>>SMS</option>
</select>
<br><br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else // List of operators | Список операторов
{
	sr_header("Операторы сотовой связи");
	$table=array();
	if ($result = mysqli_query($db, 'SELECT * FROM `operators` ORDER BY `title`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
			if ($row['title_r']){$row['title'].=' ('.$row['title_r'].')';}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'name'=>$row['name'],
				'bg'=>$row['color'],
				'color'=>$color,
			);
		}
	}
	if (count($table))
	{
?>
<br>
<table class="table table_adaptive">
	<thead>
		<tr><th>Оператор</th><th>Имя в сети</th><th></th></tr>  
	</thead>
<?
	$def=0;
	foreach ($table as $data)
{
?>
	<tr>
	<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['title']?></td><td><?=$data['name']?></td>
	<td><a href="setup_operators.php?edit=<?=$data['id']?>" title="Редактировать настройки оператора"><i class="icon-pencil"></i></a></td>
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
<em>— Список операторов пуст!</em>
<br>
<?
	}
?>
<br>
<a href="setup_operators.php?edit=new" class="link">Добавить оператора</a>
<?
}

sr_footer();
?>