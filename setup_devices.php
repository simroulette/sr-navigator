<?
include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the device | Удаление агрегатора
{
	$qry="DELETE FROM `devices` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['stop'])
{
	mysqli_query($db, "DELETE FROM `actions` WHERE `device`=".(int)$_GET['stop']);
	mysqli_query($db, "DELETE FROM `card2action` WHERE `device`=".(int)$_GET['stop']);
	sr_command_clear((int)$_GET['stop']);
	file_put_contents('flags/stop_'.$_GET['stop'],'1');
}
if ($_GET['edit']) // Editing the device | Редактирование агрегатора
{
	if ($_POST['save'] && $_POST['title'] && $_POST['model'])
	{
		if ($_GET['edit']=='new')
		{
			$qry="INSERT `devices` SET
			`title`='".$_POST['title']."',
			`model`='".$_POST['model']."',
			`type`='".$_POST['type']."',
			`step`='".(int)$_POST['step']."',
			`token_local`='".$_POST['token']."',
			`token_remote`='".rand(11111,99999).rand(11111,99999)."',
			`modems`='".$_POST['modems']."',
			`data`='".serialize(array('row_begin'=>$_POST['row_begin'],'rows'=>$_POST['rows'],'time_limit'=>$_POST['time_limit']))."',
			`ip`='".$_POST['ip']."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `devices` SET
			`title`='".$_POST['title']."',
			`model`='".$_POST['model']."',
			`type`='".$_POST['type']."',
			`step`='".(int)$_POST['step']."',
			`token_local`='".$_POST['token']."',
			`modems`='".$_POST['modems']."',
			`data`='".serialize(array('row_begin'=>$_POST['row_begin'],'rows'=>$_POST['rows'],'time_limit'=>$_POST['time_limit'],'sleep'=>$_POST['sleep']))."',
			`ip`='".$_POST['ip']."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
		}
		if ($status=mysqli_query($db,$qry))
		{			
			header('location:setup_devices.php');
			exit();
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	sr_header("Редактирование агрегатора"); // Output page title and title | Вывод титул и заголовок страницы

	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `devices` WHERE `id`='.(int)$_GET['edit'])) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				$title=$row['title'];
				$model=$row['model'];
				$token=$row['token_local'];
				$ip=$row['ip'];
				$type=$row['type'];
				$step=$row['step'];
				$data=unserialize($row['data']);
				$modems=$row['modems'];
			}
		}
	}
	else
	{
		$data['time_limit']=600;
		$data['rows']=20;
		$modems='1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16';
	}
	if ($step==''){$step=1000;}
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
Имя агрегатора (обязательное поле)
<br>
<input type="text" name="title" value="<?=$title?>" maxlength="60">
<br><br>
Модель (обязательное поле)
<br>
<select name="model" onchange="selectDevice(this,'SR-Train;SR-Nano-500;SR-Nano-1000');">
<option value="0">— Выберите устройство —</option>
<option value="SR-Train"<? if ($model=='SR-Train'){echo ' selected=1';}?>>SR-Train</option>
<option value="SR-Nano-500"<? if ($model=='SR-Nano-500'){echo ' selected=1';}?>>SR-Nano-500</option>
<option value="SR-Nano-1000"<? if ($model=='SR-Nano-1000'){echo ' selected=1';}?>>SR-Nano-1000</option>
<option value="SR-Box-One"<? if ($model=='SR-Box-One'){echo ' selected=1';}?>>SR-Box-One</option>
<option value="SR-Box-Eight"<? if ($model=='SR-Box-Eight'){echo ' selected=1';}?>>SR-Box-Eight</option>
<option value="SR-Box-Bank"<? if ($model=='SR-Box-Bank'){echo ' selected=1';}?>>SR-Box-Bank</option>
</select>
<br>
<div id="SR-Train" <? if ($model!='SR-Train'){echo 'style="display: none;"';}?>>
<br>
Модемы, котрые должны использоваться (через запятую)
<br>
<input type="text" name="modems" value="<?=$modems?>" maxlength="38">
<br><br>
Начальный ряд
<br>
<input type="text" name="row_begin" value="<?=(int)$data['row_begin']?>" maxlength="7">
<br><br>
Длина пути
<br>
<input type="text" name="rows" value="<?=(int)$data['rows']?>" maxlength="7">
<br>
</div>
<br>
Лимит времени для обработки SIM-карты(карт) под контактной группой (в секундах)
<br>
<input type="text" name="time_limit" value="<?=(int)$data['time_limit']?>" maxlength="4">
<br><br>
Пауза между итерациями CRON для слабых хостингов (в секундах)
<br>
<input type="text" name="sleep" value="<?=(int)$data['sleep']?>" maxlength="4">
<br><br>
Текущий шаг команд (step)
<br>
<input type="text" name="step" value="<?=$step?>" maxlength="7">
<br><br>
Тип подключения (обязательное поле)
<br>
<select name="type" onchange="if (this.value=='server'){document.getElementById('server').style.display='block';} else {document.getElementById('server').style.display='none';}">
<option value="client"<? if ($type=='client'){echo ' selected=1';}?>>SR в качестве клиента</option>
<option value="server"<? if ($type=='server'){echo ' selected=1';}?>>SR в качестве сервера</option>
</select>
<br><br>
<div id="server"<? if ($type!='server'){?> style="display:none;"<? } ?>>
	Токен (должен быть указан в настройках удаленного подключения на SR)
	<br>
	<input type="text" name="token" value="<?=$token?>" maxlength="5">
	<br><br>
	IP SIM Roulette (можно указать порт, например: 192.168.1.111:1234)
	<br>
	<input type="text" name="ip" value="<?=$ip?>" maxlength="32">
	<br><br>
</div>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else // Device list | Список агрегаторов
{
	sr_header("Агрегаторы SIM Roulette");

	$table=array();
	if ($result = mysqli_query($db, 'SELECT d.*,a.status AS status2,a.count,a.progress,a.action FROM `devices` d LEFT JOIN `actions` a ON a.device=d.id GROUP BY d.`id` ORDER BY d.`id`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['status2']=='inprogress' && $row['count']){$s='Inprogress '.round($row['progress']/($row['count']/100+0.0000001),2).'% ('.$row['action'].')';} elseif ($row['status2']=='waiting'){$s='В очереди';} else {$s='';}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'model'=>$row['model'],
				'token_local'=>$row['token_local'],
				'token_remote'=>$row['token_remote'],
				'type'=>$row['type'],
				'status'=>$s,
			);
		}
	}
	if (count($table))
	{
?>
<br>
<table class="table table_adaptive"><tr><th>Устройство</th><th class="sidebar">Модель</th><th class="sidebar">Подключение</th><th class="sidebar">Токен</th><th class="sidebar">Удаленное подключение</th><th>Действие</th><th>Статус</th></tr>  
<?
		foreach ($table as $data)
		{
			$link=explode('/',$_SERVER['REQUEST_URI']);
			unset($link[count($link)-1]);
			$link=str_replace('//','/',$_SERVER['SERVER_NAME'].'/'.implode("/",$link).'/link.php?token='.$data['token_remote']);
?>
	<tr>
		<td><span class="but_win" data-id="win_action" data-title='Управление устройством "<?=$data['title']?>"' data-type="ajax_device_action.php?id=<?=$data['id']?>" data-height="400" data-width="600"><?=$data['title']?></span></td>
		<td class="sidebar"><?=$data['model']?></td>
		<td class="sidebar"><?=$data['type']?></td>
		<td class="sidebar"><?=$data['token_local']?></td>
		<td class="sidebar"><?=$link?></td>
		<td><a href="setup_devices.php?edit=<?=$data['id']?>" title="Редактировать настройки устройства"><i class="icon-pencil"></i></a> <a href="setup_devices.php?stop=<?=$data['id']?>" title="Остановить задачи устройства"><i class="icon-stop" style="color: #F00;"></i></a> <a href="setup_devices.php?delete=<?=$data['id']?>" title="Удалить устройство"><i class="icon-trash"></i></a></td>
		<td id="status_<?=$data['id']?>"><?=$data['status']?></td>
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
<em>— Нет добавленных устройств!</em>
<br>
<?
	}
?>
<br>
<a href="setup_devices.php?edit=new" class="link">Добавить устройство</a>

<script>
setInterval(function()
{
	getDeviceStatus();
}, 1000);
</script>

<?
}

sr_footer();
?>