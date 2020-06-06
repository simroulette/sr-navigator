<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$actions=array('get_number|Получить номер','get_balance|Получить баланс','get_number;get_balance|Получить номер и баланс','get_sms|Получить SMS');
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	$answer=action_pool_create($_GET['id'],$a[0]);
	if ($answer['task'])
	{
		echo 'Создано задач: <a href="actions.php">'.$answer['task'].'</a>';
		exit();
	}
	else if ($answer['status'])
	{
?>
<div id="scanned">
<div id="action" style="margin-bottom: 10px;">Задача ожидает очереди...</div>
<div id="progress_percent" align="center">
0%
</div>
<progress id="progress" value="0" max="100"></progress>
<br><br>
<input type="button" onclick="stopAction('<?=$answer['action']?>','scanned');" value="Остановить" style="background:#FF0000; padding: 10px; margin: 5px 0">
</div>
<script>
var timerId = setInterval(function()
{
	getProgress(<?=$answer['action']?>);
}, 1000);
</script>
<?
		exit();
	}
	else
	{
		echo 'Ошибка!';
		exit();
	}
}
?>
Выберите задачу
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
<input type="button" onclick="getActions('ajax_pool_action.php?id=<?=$_GET['id']?>&action=a'+document.getElementById('action').options.selectedIndex);" value="Выполнить" style="padding: 10px; margin: 5px 0">