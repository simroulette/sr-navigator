<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the device | Удаление агрегатора
{
	$qry="DELETE FROM `cards` WHERE `device`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
	$qry="DELETE FROM `devices` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['stop'])
{
	if ($result = mysqli_query($db, 'SELECT id FROM `devices` WHERE `id`='.(int)$_GET['stop']))
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			mysqli_query($db, "DELETE FROM `link_incoming` WHERE `device`=".(int)$_GET['stop']);
			mysqli_query($db, "DELETE FROM `link_outgoing` WHERE `device`=".(int)$_GET['stop']);
			mysqli_query($db, "DELETE FROM `actions` WHERE `device`=".(int)$_GET['stop']);
			mysqli_query($db, "DELETE FROM `card2action` WHERE `device`=".(int)$_GET['stop']);
			mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".(int)$_GET['stop']);
			sr_command_clear((int)$_GET['stop']);
			if (!flagGet($_GET['stop'],'stop'))
			{
				flagSet($_GET['stop'],'stop');
			}
			flagDelete($_GET['stop'],'cron');
			if (flagGet($_GET['stop'],'stop',1)<time()-60)
			{
				flagDelete($_GET['stop'],'stop');
			}
		}
	}
}
if ($_GET['edit']) // Editing the device | Редактирование агрегатора
{
	if ($_POST['save'] && $_POST['title'] && $_POST['model'])
	{
		if ($_POST['model']=='SR-Box-8'){$_POST['modems']=$_POST['modems_box'];}
		else if ($_POST['model']=='SR-Organizer'){$_POST['modems']=$_POST['modems_organizer'];}
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
			`data`='".serialize(array('row_begin'=>$_POST['row_begin'],'rows'=>$_POST['rows'],'time_limit'=>$_POST['time_limit'],'carrier_limit'=>$_POST['carrier_limit'],'modem'=>$_POST['modem'],'activation'=>$_POST['activation'],'storage'=>$_POST['storage']))."',
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
			`data`='".serialize(array('row_begin'=>$_POST['row_begin'],'rows'=>$_POST['rows'],'time_limit'=>$_POST['time_limit'],'carrier_limit'=>$_POST['carrier_limit'],'modem'=>$_POST['modem'],'activation'=>$_POST['activation'],'storage'=>$_POST['storage']))."',
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

	$data['time_limit']=300;
	$data['carrier_limit']=60;
	$data['rows']=20;
	$modems='1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16';
	$modems_box='1,2,3,4,5,6,7,8';
	$modems_organizer='1-1,1-2,1-3,1-4,1-5,1-6,1-7,1-8,2-1,2-2,2-3,2-4,2-5,2-6,2-7,2-8';

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
				$modem=$data['modem'];
				if ($row['modems'])
				{
					$modems=$row['modems'];
					if ($model=='SR-Box-8'){$modems_box=$modems;}
					elseif ($model=='SR-Organizer'){$modems_organizer=$modems;}
				}
			}
		}
	}
	if ($step==''){$step=100;}
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
<select name="model" onchange="selectDevice(this,'SR-Train;SR-Nano-500;SR-Nano-1000;SR-Box-8;SR-Box-Bank;SR-Organizer');">
<option value="0">— Выберите модель агрегатора —</option>
<option value="SR-Train"<? if ($model=='SR-Train'){echo ' selected=1';}?>>SR-Train</option>
<option value="SR-Nano-500"<? if ($model=='SR-Nano-500'){echo ' selected=1';}?>>SR-Nano-500</option>
<option value="SR-Nano-1000"<? if ($model=='SR-Nano-1000'){echo ' selected=1';}?>>SR-Nano-1000</option>
<option value="SR-Box-8"<? if ($model=='SR-Box-8'){echo ' selected=1';}?>>SR-Box-8</option>
<option value="SR-Box-Bank"<? if ($model=='SR-Box-Bank'){echo ' selected=1';}?>>SR-Box-Bank</option>
<option value="SR-Organizer"<? if ($model=='SR-Organizer'){echo ' selected=1';}?>>SR-Organizer</option>
</select>
<br>
<div id="SR-Nano" <? if (strpos($model,'SR-Nano')===false){echo 'style="display: none;"';}?>>
<br>
Модем
<br>
<select name="modem">
<option value="0">— Выберите модель модема —</option>
<option value="M590"<? if ($modem=='M590'){echo ' selected=1';}?>>M590</option>
<option value="M35"<? if ($modem=='M35'){echo ' selected=1';}?>>M35</option>
<option value="SIM800"<? if ($modem=='SIM800'){echo ' selected=1';}?>>SIM800</option>
<option value="SIM5320"<? if ($modem=='SIM5320'){echo ' selected=1';}?>>SIM5320</option>
<option value="SIM5360"<? if ($modem=='SIM5360'){echo ' selected=1';}?>>SIM5360</option>
<option value="SIM7100"<? if ($modem=='SIM7100'){echo ' selected=1';}?>>SIM7100</option>
</select>
<br>
<div style="margin: 10px 0 7px 0;" class="check_panel">
<label for="activation">Быстрая активация модема</span>
<input type="checkbox" id="activation" name="activation" value="1"<? if ($data['activation']){?> checked<? } ?>>
</div>
<div style="margin: 10px 0 7px 0;" class="check_panel">
<label for="storage">Хранение SMS в памяти модема (не рекомендуется)</span>
<input type="checkbox" id="storage" name="storage" value="1"<? if ($data['storage']){?> checked<? } ?>>
</div>
</div>
<div id="SR-Organizer" <? if ($model!='SR-Organizer'){echo 'style="display: none;"';}?>>
<br>
Модемы, котрые должны использоваться (через запятую)
<br>
<input type="text" name="modems_organizer" value="<?=$modems_organizer?>" maxlength="63">
<br>
</div>
<div id="SR-Box-8" <? if ($model!='SR-Box-8'){echo 'style="display: none;"';}?>>
<br>
Модемы, котрые должны использоваться (через запятую)
<br>
<input type="text" name="modems_box" value="<?=$modems_box?>" maxlength="15">
<br>
</div>
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
Лимит времени (в секундах) для обработки 1 юнита (1 СИМ-карты SR-Nano, 2х рядов для SR-Train)
<br>
<input type="text" name="time_limit" value="<?=(int)$data['time_limit']?>" maxlength="4">
<br><br>
Лимит времени для ожидания ответа агрегатора (команда Терминала carrier_time)
<br>
<input type="text" name="carrier_limit" value="<?=(int)$data['carrier_limit']?>" maxlength="4">
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
	Токен (должен быть указан в настройках локального подключения на SR)
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
			if ($row['status2']=='inprogress' && $row['count']){$s='Прогресс&nbsp;'.round($row['progress']/($row['count']/100+0.0000001),2).'% <span class="legend">['.$row['action'].']</span>';} elseif ($row['status2']=='waiting'){$s='Очередь';} else {$s='•';}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'model'=>$row['model'],
				'token_local'=>$row['token_local'],
				'token_remote'=>$row['token_remote'],
				'type'=>$row['type'],
				'folder'=>unserialize($row['folder']),
				'press'=>$row['press'],
				'serial'=>$row['serial'],
				'status'=>$s,
			);
		}
	}
	if (count($table))
	{
?>
<br>
<table class="table table_sort table_adaptive"><tr>
<thead>
<th>Агрегатор</th><th class="sidebar">Модель</th><th class="sidebar">Диск</th><th class="sidebar">Подключение</th><th>Токен</th><th>Удаленное подключение</th><th>Действие&nbsp;&nbsp;&nbsp;</th><th class="sidebar">Статистика</th><th>Статус</th></tr>
</thead>
<?
		foreach ($table as $data)
		{
			$link=explode('/',$_SERVER['REQUEST_URI']);
			unset($link[count($link)-1]);
			$link=str_replace('//','/',$_SERVER['SERVER_NAME'].'/'.implode("/",$link).'/link.php?token='.$data['token_remote']);
			$disk='';
			if (strpos($data['model'],'Nano')){$disk=$data['folder']['title'];} else {$disk='—';}
			$stat=$data['press'];
			if ($data['serial']){$data['serial']='<div class="sidebar legend">S/N:'.$data['serial'].'</div>';}
?>
	<tr>
		<td><span class="but_win" data-id="win_action" data-title='Управление агрегатором "<?=$data['title']?>"' data-type="ajax_device_action.php?id=<?=$data['id']?>" data-height="400" data-width="600"><?=$data['title']?></span><?=$data['serial']?></td>
		<td class="sidebar"><?=$data['model']?></td>
		<td class="sidebar"><?=$disk?></td>
		<td class="sidebar"><?=$data['type']?></td>
		<td><?=$data['token_local']?></td>
		<td><span class="legend note" onclick="copy('<?=$link?>');soundClick();"><?=$link?></span></td>
		<td><a href="setup_devices.php?edit=<?=$data['id']?>" title="Редактировать настройки агрегатора"><i class="icon-pencil"></i></a> <a href="setup_devices.php?stop=<?=$data['id']?>" title="Остановить задачи агрегатора"><i class="icon-stop" style="color: #F00;"></i></a> <a href="javascript:void();" onclick="if (confirm('Вы готовы удалить агрегатор?')){document.location='setup_devices.php?delete=<?=$data['id']?>';}" title="Удалить агрегатор"><i class="icon-trash"></i></a></td>
		<td class="sidebar" align="right"><?=$stat?></td>
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
<em>— Нет добавленных агрегаторов!</em>
<br>
<?
	}
?>
<br>
<a href="setup_devices.php?edit=new" class="link">Добавить агрегатор</a>

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