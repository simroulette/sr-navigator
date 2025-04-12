<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");

$actions=array('get_number|Получить номер','get_balance|Получить баланс','get_iccid|Получить ICCID','get_number;get_balance|Получить номер и баланс','get_sms|Получить SMS','send_sms|Отправить SMS','do_call|Осуществить Вызов');
$data=array();
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	$f=$a[0];
	$f=explode(';',$f);
	$f=$f[0];
	if (!$_GET['f1'])
	{
		$f=$f();
		if ($f['options'])
		{
			echo $f['options'];
			$field='';
			for ($i=0;$i<$f['count'];$i++)
			{
				$field.="+'&f".($i+1)."='+encodeURIComponent(document.getElementById('f".($i+1)."').value)";
			}
			$field.="+'&count=".$f['count']."'";
			if ($f['save']){$field.="+'&save=".$f['save']."'";}
			echo '<input type="button" onclick="document.getElementById(\'loading\').style.display=\'block\';getActions(\'ajax_pool_action.php?id='.$_GET['id'].'&action='.$_GET['action']."'".$field.');" value="Выполнить" style="padding: 10px; margin: 5px 0">';
			echo '<div id="loading"><img src="sr/loading.gif"></div>';
			exit();
		}
	}
	else
	{
		for ($i=0;$i<$_GET['count'];$i++)
		{
			$data[]=$_GET['f'.($i+1)];		
		}
	}
/*
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	$f=$a[0];
	if (!$_GET['f1'])
	{
		$f=$f();
		if ($f[0])
		{
			echo $f[0];
			$field='';
			for ($i=0;$i<$f[1];$i++)
			{
				$field.="+'&f".($i+1)."='+encodeURIComponent(document.getElementById('f".($i+1)."').value)";
			}
			$field.="+'&count=".$f[1]."'";
			echo '<input type="button" onclick="getActions(\'ajax_pool_action.php?id='.$_GET['id'].'&action='.$_GET['action']."'".$field.');" value="Выполнить" style="padding: 10px; margin: 5px 0">';
			exit();
		}
	}
	else
	{
		for ($i=0;$i<$_GET['count'];$i++)
		{
			$data[]=$_GET['f'.($i+1)];		
		}
	}
*/
//echo $_GET['id'].','.$a[0].','.$data;
//exit();
	$answer=action_pool_create($_GET['id'],$a[0],$data);
	if ($answer['task']==1)
	{
		echo '<h1>Задача создана!</h1><br><a href="actions.php">Перейти к задаче</a>';
		exit();
	}
	else if ($answer['task'])
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
<?
//m.device
$qry='SELECT c.device FROM `pools` p
INNER JOIN `card2pool` cp ON `cp`.pool=`p`.id
INNER JOIN `cards` c ON c.`number`=cp.`card`
INNER JOIN `modems` m ON m.`device`=c.`device`
WHERE p.`id`='.(int)$_GET['id'];
if ($result = mysqli_query($db, $qry)) 
{
	$stop=0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$stop=1;
		break;
	}
	if ($stop)
	{
		echo '<div class="tooltip danger">— Перед началом работы с Пулом следует отключить Онлайн-режим!</div><br><br><span class="link" onclick="location.href=\'online.php?device='.$_GET['device'].'\'">Выключить Онлайн</span>';
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
<div id="loading"><img src="sr/loading.gif"></div>
<input type="button" onclick="this.style.display='none';document.getElementById('loading').style.display='block';getActions('ajax_pool_action.php?id=<?=$_GET['id']?>&action=a'+document.getElementById('action').options.selectedIndex);" value="Продолжить" style="padding: 10px; margin: 5px 0">
