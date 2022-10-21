<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$actions=array('dev_init|Инициализировать агрегатор','dev_truncate|Удалить все СИМ-карты');//,'get_number|Получить номера SIM-карт','get_balance|Получить балансы SIM-карт','get_number;get_balance|Получить номера и балансы');
if ($result = mysqli_query($db, 'SELECT `model` FROM `devices` WHERE `id`='.(int)$_GET['id'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($row['model']=='SR-Train') // SR Train
		{
			$actions=array('dev_init|Инициализировать агрегатор','dev_rows|Получить длину пути','dev_truncate|Удалить все СИМ-карты');//,'get_number|Получить номера SIM-карт','get_balance|Получить балансы SIM-карт','get_number;get_balance|Получить номера и балансы');
		}
		elseif (strpos($row['model'],'SR-Nano')!==false) // SR Nano
		{
			$actions=array('dev_init|Инициализировать агрегатор','dev_calibration1|Калибровка #1 (позиционирование у 0 ряда)','dev_calibration2|Калибровка #2 (карты 0 ряда)','dev_calibration3|Калибровка #3 (остальные карты)','dev_truncate|Удалить все СИМ-карты');//,'get_number|Получить номера SIM-карт','get_balance|Получить балансы SIM-карт','get_number;get_balance|Получить номера и балансы');
		}
	}
}
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	if (action_device_create($_GET['id'],$a[0]))
	{
		if ($a[0]=='dev_calibration1')
		{
			echo '<h1>Калибровка #1 запущена</h1>Для успешной калибровки необходима версия ПО не ниже <b>6.41</b><br><br>Восстановить настройки после неудачной калибровки можно командой Терминала <b>fs>copy:/config/settings_c1 /config/settings</b><br><br>Следить за ходом выполнения можно в разделе <b><a href="actions.php">Задачи</a></b>.';
		}
		elseif ($a[0]=='dev_calibration2')
		{
			echo '<h1>Калибровка #2 запущена</h1><b>Важно!</b> Установите СИМ-карту в слот <b>A0</b>, удалите соседние 2 карты на дорожке <b>A</b>!<br><br>Следить за ходом выполнения можно в разделе <b><a href="actions.php">Задачи</a></b>.';
		}
		elseif ($a[0]=='dev_calibration3')
		{
			echo '<h1>Калибровка #3 запущена</h1><b>Важно!</b> Установите СИМ-карту в слот <b>A1</b>, удалите соседние 2 карты на дорожке <b>A</b>!<br><br>Следить за ходом выполнения можно в разделе <b><a href="actions.php">Задачи</a></b>.';
		}
		else
		{
			echo 'Действие запущено...';
		}
		exit();
	}
	else
	{
		echo 'Ошибка!';
		exit();
	}
}
?>
Выберите действие
<select id="action">
<?
	$n=1;
	foreach ($actions AS $txt)
	{
		$txt=explode('|',$txt);
		echo '<option value="'.$n++.'">'.$txt[1].'</option>';
	}
?>
</select>
<br><br>
<input type="button" onclick="getActions('ajax_device_action.php?id=<?=$_GET['id']?>&action=a'+document.getElementById('action').options.selectedIndex);" value="Выполнить" style="padding: 10px; margin: 5px 0">