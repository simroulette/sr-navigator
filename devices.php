<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

$db2_connect=1;
include("_func.php");
$status=1;
if ($_GET['delete']) // Deleting the device | Удаление агрегатора
{
	$qry="DELETE FROM `cards` WHERE `device`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
	$qry="DELETE FROM `devices` WHERE `id`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['unlink']) // Deleting the device | Разрыв связи с агрегатором
{
	$qry="SELECT * FROM `devices` WHERE `id`=".(int)$_GET['unlink'];
	if ($result = mysqli_query($db, $qry))
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			sr_command((int)$_GET['unlink'],'server_url=&&set.server:NONE,0&&save');
		}
	}	
}
if ($_GET['stop'])
{
	if ($result = mysqli_query($db, 'SELECT id AS press_errors FROM `devices` 
	WHERE `id`='.(int)$_GET['stop']))
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
			if (flagGet($_GET['stop'],'stop',1)<time()-30)
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
	 	if ($_GET['edit']=='new')
		{
			$dev_model=$model=$_POST['model'];
			if ($_POST['model']=='SR-Train'){$modems='1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16';}
			elseif ($_POST['model']=='SR-Box-8'){$modems='1,2,3,4,5,6,7,8';}
			elseif ($_POST['model']=='SR-Box-8-Voice'){$modems='1,2,3,4,5,6,7,8';}
			elseif ($_POST['model']=='SR-Box-2'){$modems='1,2,3,4,5,6,7,8';}
			elseif ($_POST['model']=='SR-Box-1'){$modems='1,2,3,4,5,6,7,8';}
			elseif ($_POST['model']=='SR-Organizer'){$modems='1-1,1-2,1-3,1-4,1-5,1-6,1-7,1-8,2-1,2-2,2-3,2-4,2-5,2-6,2-7,2-8';}
			elseif ($_POST['model']=='SR-Board'){$modems='1,2,3,4,5,6,7,8';}

			$d=array(
			'row_begin'=>0,
			'rows'=>19,
			'time_limit'=>300,
			'carrier_limit'=>120,
			'activation'=>1,
			'review'=>60,
			'review_start'=>0,
			'storage'=>0
			);
			$dev_data=serialize($d);

			if (!$nolicense)
			{
				$qry="INSERT `devices` SET
				`title`='".mysqli_real_escape_string($db,$_POST['title'])."',
				`model`='".$model."',
				`step`='1000',
				`data`='".$dev_data."',
				`modems`='".$_POST['modems']."',
				`token_remote`='".rand(11111,99999).rand(11111,99999)."',
				`serial`='".$_POST['serial']."',
				`time`='".time()."'";

				if ($status=mysqli_query($db,$qry))
				{			
					$id=mysqli_insert_id($db);
					$status=2;
				}
			}
		}
	 	if ($_POST['title'])
		{
			if ($result = mysqli_query($db, 'SELECT `model`,`data` FROM `devices` WHERE `id`='.(int)$_GET['edit'])) 
			{
				if ($row = mysqli_fetch_assoc($result))
				{
					$model=$row['model'];
					$data=unserialize($row['data']);
				}
				else
				{
					header('location:devices.php');
					exit();
				}
			}
			if ($model=='SR-Box-8' || $model=='SR-Board'){$_POST['modems']=$_POST['modems_box'];}
			else if ($model=='SR-Organizer')
			{
				$_POST['review']=$_POST['review_organizer'];
				$_POST['review_start']=$_POST['review_start_organizer'];
			}
			else if ($model=='SR-Organizer-Smart')
			{
				$_POST['review']=$_POST['review_organizer_smart'];
				$_POST['review_start']=$_POST['review_start_organizer_smart'];
			}
			$data['row_begin']=$_POST['row_begin'];
			$data['rows']=$_POST['rows'];
			$data['time_limit']=$_POST['time_limit'];
			$data['carrier_limit']=$_POST['carrier_limit'];
			$data['modem']=$_POST['modem'];
			$data['review']=$_POST['review'];
			$data['review_start']=$_POST['review_start'];
			$data['activation']=$_POST['activation'];
			$data['storage']=$_POST['storage'];

			$qry="UPDATE `devices` SET
			`title`='".mysqli_real_escape_string($db,$_POST['title'])."',
			`type`='".mysqli_real_escape_string($db,$_POST['type'])."',
			`step`='".(int)$_POST['step']."',
			`token_local`='".mysqli_real_escape_string($db,$_POST['token'])."',
			`modems`='".mysqli_real_escape_string($db,$_POST['modems'])."',
			`data`='".serialize($data)."',
			`ip`='".mysqli_real_escape_string($db,$_POST['ip'])."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
			if ($status=mysqli_query($db,$qry))
			{			
				header('location:devices.php');
				exit();
			}
		}
	}
	elseif ($_POST['save'])
	{
		$status='При сохранении данных произошла ошибка, проверьте правильность заполнения полей!';
	}

	if ($_GET['edit']=='new')
	{
		sr_header("Добавление нового агрегатора"); // Output page title and title | Вывод титул и заголовок страницы
	}
	else
	{
		sr_header("Настройки агрегатора"); // Output page title and title | Вывод титул и заголовок страницы
	}
	$data['time_limit']=300;
	$data['carrier_limit']=60;
	$data['rows']=19;
	$modems='1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16';
	$modems_box='1,2,3,4,5,6,7,8';
	$modems_organizer='1-1,1-2,1-3,1-4,1-5,1-6,1-7,1-8,2-1,2-2,2-3,2-4,2-5,2-6,2-7,2-8';
	$review_organizer='180';
	$review_organizer_smart='180';
	$review_start_organizer_smart='0';

	$title=$_POST['title'];
	$model=$_POST['model'];
	$token=$_POST['token_local'];
	$ip=$_POST['ip'];

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
					elseif ($model=='SR-Organizer')
					{
						$modems_organizer=$modems;
						$review_organizer=$data['review'];
						$review_start_organizer=$data['review_start'];
					}
				}
				if ($model=='SR-Organizer-Smart')
				{
					$review_organizer_smart=$data['review'];
					$review_start_organizer_smart=$data['review_start'];
				}
			}
		}
	}

if ($step==''){$step=100;}
if ($status!=1 && $status!=2)
{
?>
<div class="status_error"><?=$status?></div>
<?
}
?>
<form method="post">
Тип соединения SR с Навигатором
<br>
<select name="type" onchange="if (this.value=='server'){document.getElementById('server').style.display='block';} else {document.getElementById('server').style.display='none';}">
<option value="client"<? if ($type=='client'){echo ' selected=1';}?>>SR в качестве клиента (рекомендовано)</option>
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

Имя агрегатора (обязательное поле)
<br>
<input type="text" name="title" value="<?=$title?>" maxlength="60">
<br><br>
Модель (обязательное поле)
<br>
<select name="model" onchange="selectDevice(this,'SR-Train;SR-Nano-500;SR-Nano-1000;SR-Box-2;SR-Box-8;SR-Box-Bank;SR-Box-2-Bank;SR-Organizer;SR-Organizer-Smart;SR-Board');">
<option value="0">— Выберите модель агрегатора —</option>
<option value="SR-Train"<? if ($model=='SR-Train'){echo ' selected=1';}?>>SR-Train</option>
<option value="SR-Nano-500"<? if ($model=='SR-Nano-500'){echo ' selected=1';}?>>SR-Nano-500</option>
<option value="SR-Nano-1000"<? if ($model=='SR-Nano-1000'){echo ' selected=1';}?>>SR-Nano-1000</option>
<option value="SR-Box-2"<? if ($model=='SR-Box-2'){echo ' selected=1';}?>>SR-Box-2</option>
<option value="SR-Box-8"<? if ($model=='SR-Box-8'){echo ' selected=1';}?>>SR-Box-8</option>
<option value="SR-Box-Bank"<? if ($model=='SR-Box-Bank'){echo ' selected=1';}?>>SR-Box-Bank</option>
<option value="SR-Box-2-Bank"<? if ($model=='SR-Box-2-Bank'){echo ' selected=1';}?>>SR-Box-Bank</option>
<option value="SR-Organizer"<? if ($model=='SR-Organizer'){echo ' selected=1';}?>>SR-Organizer</option>
<option value="SR-Organizer-Smart"<? if ($model=='SR-Organizer-Smart'){echo ' selected=1';}?>>SR-Organizer-Smart</option>
<option value="SR-Board"<? if ($model=='SR-Board'){echo ' selected=1';}?>>SR-Board</option>
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
<div id="SR-Organizer-Smart" <? if ($model!='SR-Organizer-Smart'){echo 'style="display: none;"';}?>>
<br>
Время до переключения карты в режиме обзора (в секундах)
<br>
<input type="number" name="review_organizer_smart" value="<?=$review_organizer_smart?>" maxlength="5">
<br>
<br>
Автостарт обзора при отсутствии активности (в секундах, 0 - автостарт выключен)
<br>
<input type="number" name="review_start_organizer_smart" value="<?=$review_start_organizer_smart?>" maxlength="5">
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
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
}
else // Device list | Список агрегаторов
{
	sr_header("Агрегаторы SIM Roulette");

	$table=array();
	$qry='SELECT d.*, a.status AS status2, a.count, a.progress, a.action FROM `devices` d 
	LEFT JOIN `actions` a ON a.device=d.id
	GROUP BY d.`id` ORDER BY d.`id`';
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$explane='';
			if ($row['model']=='SR-Box-Bank' || $row['model']=='SR-Box-2-Bank' || $row['model']=='SR-Board')
			{
				$d=unserialize($row['data']);
				if ($d['map']=='1')
				{
					$explane='64';
				}
				else
				{
					for ($i=0;$i<8;$i++)
					{
						if ($d['map'][$i]=='1')
						{
							$explane++;
						}
					}
					if ($explane){$explane=$explane*64;} else {$explane='';}
				}
			}
			if ($explane){$explane='<br><span class="legend">'.$explane.' SIM</span>';}
			if ($row['status2']=='inprogress' && $row['count']){$s='Прогресс:&nbsp;'.round($row['progress']/($row['count']/100+0.0000001),2).'% <span class="legend">['.$row['action'].']</span>';} elseif ($row['status2']=='waiting'){$s='Очередь';} else {$s='<br>• • •';}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'model'=>$row['model'],
				'data'=>$row['data'],
				'press_stat'=>$row['press_stat'],
				'press_errors'=>$row['press_errors'],
				'explane'=>$explane,
				'token_local'=>$row['token_local'],
				'token_remote'=>$row['token_remote'],
				'type'=>$row['type'],
				'folder'=>unserialize($row['folder']),
				'press'=>$row['press'],
				'serial'=>$row['serial'],
				'version'=>$row['ver'],
				'rev'=>$row['rev'],
				'ip'=>$row['ip_internal'],
				'status'=>$s,
			);
		}
	}
	if (count($table))
	{
		echo '<br>';
		foreach ($table as $data)
		{
			$link=explode('/',$_SERVER['REQUEST_URI']);
			unset($link[count($link)-1]);
			$link=str_replace('//','/',$_SERVER['SERVER_NAME'].'/'.implode("/",$link).'/link.php?token='.$data['token_remote']);
			$disk='';
			if (strpos($data['model'],'Nano')){$disk=$data['folder']['title'];}
			$stat=$data['press'];
			if (!$p=$data['press_stat']){$p=0.0000001;}
			$percent=round($data['press_errors']/($p/100),2);
			$resume=stat_resume($data['press_stat'],$percent);

			echo '<div class="device" id="device_'.$data['id'].'"';
			if ($data['title']=='[create]')
			{
				echo ' style="opacity: 0.5"';
// Проверяем не нужно ли запускать инициализацию
				$access=flagGet($data['id'],'answer',1);
				$r='';
				if ($access+30<time())
				{
					$stop=1;			
				}
				else
				{
					if ($result = mysqli_query($db, 'SELECT * FROM `actions` WHERE `device`='.$data['id'].' AND `action`="dev_init"')) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$stop=1;
						}
					}
				}
				if (!$stop)
				{
					echo "><script>getActions('ajax_device_action.php?id=".$data['id']."&action=a0');document.getElementById('device_".$data['id']."').style.opacity='1';</script";
				}
			}
			echo '><div class="dev_left" id="dev_logo_'.$data['id'].'">';
			if ($data['title']!='[create]')
			{
				echo '<a href="devices.php?edit='.$data['id'].'" title="Настройка агрегатора">';
			}
			echo icon_out($data['model'],$data['data'],0);
			if ($data['title']!='[create]')
			{
		        	echo '</a>';
			}

			echo '<div class="dev_status" id="status_'.$data['id'].'">'.$data['status'].'</div></div>';
			echo '<table class="tab_device"><tr><tr><td colspan="2" style="border: none;">';

			if ($data['title']!='[init]' && $data['title']!='[create]'){$aa=' "'.$data['title'].'"';} else {$aa='';}
			if ($data['title']=='[create]')
			{
				echo '<h2 id="title_'.$data['id'].'">Новый агрегатор</h2>';
			} 
			elseif ($data['title']=='[init]')
			{
				echo '<h2 id="title_'.$data['id'].'">инициализация...</h2>';
			} 
			else 
			{
				echo '<h2 class="but_win" data-id="win_action" data-title=\'Управление агрегатором'.$aa.'\' data-type="ajax_device_action.php?id='.$data['id'].'" data-height="400" data-width="600">'.$data['title'].'</h2>';
			}
			echo '</td></tr><tr><td><div>Модель</td><td class="bg" id="model_'.$data['id'].'">'.$data['model'].$data['explane'].'</td></tr>';
			echo '<tr><td>ID</td><td class="bg">#'.$data['id'].'</td></tr>';
			if ($data['serial']){echo '<tr><td>Серийный номер</td><td class="bg">'.$data['serial'].'</td></tr>';}
			if ($data['version']){echo '<tr><td>Версия микропрограммы</td><td class="bg">'.$data['version'].' / rev.'.$data['rev'].'</td></tr>';}
			if ($disk)
			{
				echo '<tr><td>Диск</td><td class="bg"><a href="folders.php">'.$disk.'</a></td></tr>';
				$modem=unserialize($data['data']);
				if ($modem=$modem['modem']){$modem='">'.$modem;} else {$modem=' danger_tab">Не найден';}
				echo '<tr><td>Модем</td><td class="bg'.$modem.'</td></tr>';
			}				
			echo '<tr><td>Тип подключения</td><td class="bg">'.$data['type'].'</td></tr>';
			if ($data['ip']){echo '<tr><td>Локальный IP-адрес</td><td class="bg">'.$data['ip'].'</td></tr>';}
			if ($data['token_local']){echo '<tr><td>Диск:</td><td class="bg">'.$data['token_local'].'</td></tr>';}
			echo '<tr><td>URL удаленного подключения</td><td class="note device_note" onclick="copy(\''.$link.'\');soundClick();">'.$link.'</td></tr>';
			if ($stat){echo '<tr><td><a href="stat.php">Статистика</a></td><td class="bg dev_stat">Обработано карт: '.$stat.' / '.$data['press_stat'].'<br>Ошибок: '.$data['press_errors'].' ['.$percent.'%]<br>Резюме: <div style="border-bottom: 3px solid #'.$resume[1].';">'.$resume[0].'</div></td></tr>';}
			if (strpos($data['model'],'Box')!==false){echo '<tr><td>Примечание</td><td class="bg">После изменения конфигурации СИМ-банков требуется повторная инициализация</td></tr>';}
			echo '<tr><td colspan="2" style="border: none;"><div class="tools">';
//			if ($data['title']!='[create]')
//			{
				echo '<span class="but_win" data-id="win_action" data-title=\'Управление агрегатором'.$aa.'\' data-type="ajax_device_action.php?id='.$data['id'].'" data-height="400" data-width="600" title="Управление агрегатором"><i class="icon-cog"></i></span> <a href="devices.php?edit='.$data['id'].'" title="Настройка агрегатора"><i class="icon-wrench"></i></a> <a href="actions.php" title="Задачи"><i class="icon-tasks"></i></a> <a href="devices.php?stop='.$data['id'].'" title="Остановить задачи агрегатора"><i class="icon-stop" style="color: #F00;"></i></a> ';
//			}
			echo '<a href="javascript:void();" onclick="if (confirm(\'Вы готовы отключить агрегатор? Включить агрегатор можно будет только через WEB-Интерфейс...\')){document.location=\'devices.php?unlink='.$data['id'].'\';}" title="Отключить связь с агрегатором"><i class="icon-lock"></i></a>';
			echo '<a href="javascript:void();" onclick="if (confirm(\'Вы готовы удалить агрегатор?\')){document.location=\'devices.php?delete='.$data['id'].'\';}" title="Удалить агрегатор"><i class="icon-trash"></i></a>';
			echo '</div></td></tr>';
			echo '</table>';
/*
		<td></td>
		<td class="sidebar" align="right"><?=$stat?></td>
		<td id="status_<?=$data['id']?>"><?=$data['status']?></td>
*/
			echo '<div class="clr"><span></span></div></div>';
		}
	}
	else
	{
?>
<div class="tooltip">— Добавьте свой агрегатор!</div>
<br>
<?
	}
?>
<br>
<a href="devices.php?edit=new" class="link">Добавить агрегатор</a>

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