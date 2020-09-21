<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$actions=array('dev_truncate|Удалить полученные номера','get_number|Получить номера SIM-карт','get_balance|Получить балансы SIM-карт','get_number;get_balance|Получить номера и балансы');
if ($result = mysqli_query($db, 'SELECT `model` FROM `devices` WHERE `id`='.(int)$_GET['id'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($row['model']=='SR-Train') // SR Train
		{
			$actions=array('dev_rows|Получить длину пути','dev_truncate|Удалить полученные номера','get_number|Получить номера SIM-карт','get_balance|Получить балансы SIM-карт','get_number;get_balance|Получить номера и балансы');
		}
	}
}
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	if (action_device_create($_GET['id'],$a[0]))
	{
		echo 'Действие запущено...';
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