<?
// ===================================================================
// Sim Roulette -> Settings
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
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
		if ($_GET['edit']=='new')
		{
			$qry="INSERT `operators` SET
			`title`='".$_POST['title']."',
			`name`='".$_POST['name']."',
			`get_number`='".$_POST['get_number']."',
			`get_number_type`='".$_POST['get_number_type']."',
			`get_balance`='".$_POST['get_balance']."',
			`get_balance_type`='".$_POST['get_balance_type']."',
			`color`='".trim($_POST['color'],'#')."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `operators` SET
			`title`='".$_POST['title']."',
			`name`='".$_POST['name']."',
			`get_number`='".$_POST['get_number']."',
			`get_number_type`='".$_POST['get_number_type']."',
			`get_balance`='".$_POST['get_balance']."',
			`get_balance_type`='".$_POST['get_balance_type']."',
			`color`='".trim($_POST['color'],'#')."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
		}
		if ($status=mysqli_query($db,$qry))
		{			
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
				$name=$row['name'];
				$get_number=$row['get_number'];
				$get_number_type=$row['get_number_type'];
				$get_balance=$row['get_balance'];
				$get_balance_type=$row['get_balance_type'];
				$color=$row['color'];
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
Имя оператора в сети (обязательное поле)
<br>
<input type="text" name="name" value="<?=$name?>" maxlength="32">
<br><br>
Команда для запроса номера, например: AT+CUSD=1,"*110*10#"
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
Команда для запроса баланса, например: AT+CUSD=1,"#102#"
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
Цвет оператора, например: #FFFF00
<br>
<input type="text" name="color" value="<?=$color?>" maxlength="7">
<br><br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else // List of operators | Список операторов
{
	sr_header("Операторы сотовой связи");
	$table=array();
	if ($result = mysqli_query($db, 'SELECT * FROM `operators`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
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
<table class="table table_adaptive"><tr><th>Оператор</th><th>Имя в сети</th><th>Действие</th></tr>  
<?
	foreach ($table as $data)
{
?>
	<tr><td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['title']?></td><td><?=$data['name']?></td><td><a href="setup_operators.php?edit=<?=$data['id']?>" title="Редактировать настройки оператора"><i class="icon-pencil"></i></a> <a href="setup_operators.php?delete=<?=$data['id']?>" title="Удалить оператора"><i class="icon-trash"></i></a> </td></tr>
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