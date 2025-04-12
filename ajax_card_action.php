<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$actions=array('set_title|Добавить имя карты','get_number|Получить номер','get_balance|Получить баланс','get_iccid|Получить ICCID','get_number;get_balance|Получить номер и баланс','get_sms|Получить SMS','send_sms|Отправить SMS','do_call|Позвонить');
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	$f=$a[0];
	if (!$_GET['f1'])
	{
		$f=$f();
		if ($f['options'])
		{
			echo $f['options'];
			$field='';
			$save='';
			for ($i=0;$i<$f['count'];$i++)
			{
				$field.="+'&f".($i+1)."='+encodeURIComponent(document.getElementById('f".($i+1)."').value)";
			}
			$field.="+'&count=".$f['count']."'";
			if ($f['save']){$field.="+'&save=".$f['save']."'";}
			echo '<input type="button" onclick="getActions(\'ajax_card_action.php?id='.$_GET['id'].'&action='.$_GET['action']."'".$field.');" value="Выполнить" style="padding: 10px; margin: 5px 0">';
			exit();
		}
	}
	elseif ($_GET['save'])
	{
		$qry="UPDATE `cards` SET
		`".$_GET['save']."`='".trim($_GET['f1'],'+')."'
		WHERE `number`='".(int)$_GET['id']."'";
		mysqli_query($db, $qry); 
?>
<div id="scanned">
<div id="action" style="margin-bottom: 10px;">Имя СИМ-карты сохранено. Обновите список...</div>
</div>
<?
		exit();
	}
	else
	{
		for ($i=0;$i<$_GET['count'];$i++)
		{
			$data[]=$_GET['f'.($i+1)];		
		}
	}
	$answer=action_card_create($_GET['id'],$a[0],$data);
	if ($answer['status'])
	{
?>
<div id="scanned">
<div id="action" style="margin-bottom: 10px;">Задача ожидает очереди...</div>
<div id="progress_percent" align="center">
0%
</div>
<progress id="progress" value="0" max="100"></progress>
<br><br>
<input type="button" onclick="clearInterval(timerId); stopAction('<?=$answer['action']?>','scanned');" value="Остановить" style="background:#FF0000; padding: 10px; margin: 5px 0">
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
$qry='SELECT device FROM `modems` WHERE `device`='.(int)$_GET['device'];
if ($result = mysqli_query($db, $qry)) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		echo '<div class="tooltip danger">— Перед сканирование следует отключить Онлайн-режим!</div><br><br><span class="link" onclick="location.href=\'online.php?device='.$_GET['device'].'\'">Выключить Онлайн</span>';
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
<input type="button" onclick="getActions('ajax_card_action.php?id=<?=$_GET['id']?>&action=a'+document.getElementById('action').options.selectedIndex);" value="Продолжить" style="padding: 10px; margin: 5px 0">
