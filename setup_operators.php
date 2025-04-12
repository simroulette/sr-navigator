<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
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
	if ($_POST['save'] && $_POST['title'] && $_POST['name'][0])
	{
		$_POST['color']=trim($_POST['color'],'#');
		if (!$_POST['color']){$_POST['color']='000000';}

		$qry='';
		if ($_GET['edit']!='new')
		{
			if ($result = mysqli_query($db, 'SELECT * FROM `operators` WHERE `id`='.(int)$_GET['edit'])) 
			{
				if ($row = mysqli_fetch_assoc($result))
				{
					$qry="UPDATE `operators` SET
					`title`='".mysqli_real_escape_string($db,$_POST['title'])."',
					`prefix`='".(int)$_POST['prefix']."',
					`name`='".mysqli_real_escape_string($db,';'.implode(';',$_POST['name']).';')."',
					`get_number`='".mysqli_real_escape_string($db,trim($_POST['get_number']))."',
					`get_number_type`='".mysqli_real_escape_string($db,$_POST['get_number_type'])."',
					`get_balance`='".mysqli_real_escape_string($db,trim($_POST['get_balance']))."',
					`get_balance_type`='".mysqli_real_escape_string($db,$_POST['get_balance_type'])."',
					`color`='".mysqli_real_escape_string($db,$_POST['color'])."',
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
			`prefix`='".$_POST['prefix']."',
			`name`='".mysqli_real_escape_string($db,';'.implode(';',$_POST['name']).';')."',
			`get_number`='".mysqli_real_escape_string($db,trim($_POST['get_number']))."',
			`get_number_type`='".mysqli_real_escape_string($db,$_POST['get_number_type'])."',
			`get_balance`='".mysqli_real_escape_string($db,trim($_POST['get_balance']))."',
			`get_balance_type`='".mysqli_real_escape_string($db,$_POST['get_balance_type'])."',
			`color`='".mysqli_real_escape_string($db,$_POST['color'])."',
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
				$prefix=$row['prefix'];
				$name=explode(';',trim($row['name'],';'));
				$get_number=$row['get_number'];
				$get_number_type=$row['get_number_type'];
				$get_balance=$row['get_balance'];
				$get_balance_type=$row['get_balance_type'];
				$color=$row['color'];
			}
		}
	}
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
<br><br><br>
Имя оператора на карте или в сети (обязательное поле)
<br>
<select name="name[]" size="5" multiple>
<?
$my=array();
$s='';
$s2='';
if ($result = mysqli_query($db, 'SELECT * FROM `operators_uniq` GROUP BY `name` ORDER BY `name`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$my[]=$row['name'];
		$s.='<option value="'.$row['name'].'"';
		if (in_array($row['name'],$name)){$s.=' selected';}
		$s.='>'.$row['name'].'</option>';
	}                                                                                                                    
}
if ($s){$s='<optgroup label="На ваших СИМ-картах">'.$s;}
if ($result = mysqli_query($db, 'SELECT * FROM `operators_uniq` GROUP BY `name` ORDER BY `name`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!in_array($row['name'],$my))
		{
			$s2.='<option value="'.$row['name'].'"';
			if (in_array($row['name'],$name)){$s2.=' selected';}
			$s2.='>'.$row['name'].'</option>';
		}
	}
if ($s2){$s2='<optgroup label="Системные">'.$s2;}
echo $s.$s2;
}
?>
</select>
<div class="hint" style="margin-top: -4px;">Выберите все подходящие имена. Если в списке нет нужного значения, просканируйте карту оператора, которого хотите добавить. После этого оператор появится в списке.</div>
Префикс телефонного номера, например 7 (без +)
<br>
<input type="number" name="prefix" value="<?=$prefix?>" maxlength="6">
<br><br>
Команда для запроса номера  
<br>
<input type="text" name="get_number" value="<?=$get_number?>" maxlength="32">
<div class="hint" style="margin-top: -4px;">Пример: *110*10# (для выбора из меню используйте "|", <em>например: *111*6#|1</em>)</div>
Тип получения номера
<br>
<select name="get_number_type">
<option value="none">- выберите из списка -</option>
<option value="ussd"<? if ($get_number_type=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_number_type=='sms'){echo ' selected=1';}?>>SMS</option>
</select>
<br><br>
Команда для запроса баланса
<br>
<input type="text" name="get_balance" value="<?=$get_balance?>" maxlength="32">
<div class="hint" style="margin-top: -4px;">Пример: #102# (для выбора из меню используйте "|")</div>
Тип получения баланса
<br>
<select name="get_balance_type">
<option value="none">- выберите из списка -</option>
<option value="ussd"<? if ($get_balance_type=='ussd'){echo ' selected=1';}?>>USSD</option>
<option value="sms"<? if ($get_balance_type=='sms'){echo ' selected=1';}?>>SMS</option>
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
	if ($result = mysqli_query($db, 'SELECT * FROM `operators` ORDER BY `name`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'name'=>$row['name'],
				'prefix'=>$row['prefix'],
				'bg'=>$row['color'],
				'color'=>$color,
			);
		}
	}
	if (count($table))
	{
?>
<div class="table_box">
<table class="table table_adaptive">
	<thead>
		<tr><th>Оператор</th><th>Имя в сети</th><th>Префикс</th><th></th></tr>  
	</thead>
<?
	$def=0;
	foreach ($table as $data)
{
?>
	<tr>
	<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['title']?></td><td><? $a=explode(';',trim($data['name'],';')); echo implode(', ',$a);?></td><td><? if ($data['prefix']){echo '+'.$data['prefix'];} ?></td>
	<td><a href="setup_operators.php?edit=<?=$data['id']?>" title="Редактировать настройки оператора"><i class="icon-pencil"></i></a> <a href="javascript:void();" onclick="if (confirm('Вы готовы удалить индивидуальные настройки?')){document.location='setup_operators.php?delete=<?=$data['id']?>';}" title="Удалить индивидуальные настройки"><i class="icon-trash"></i></a> </td>
	</tr>
<?
}
?>
</table>
</div>
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
