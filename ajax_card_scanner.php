<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['do'])
{
	if (!$d=$_GET['dev1']){$d=$_GET['dev2'];}	
	if ($_GET['new']=='true'){$new=1;} else {$new=0;}
	if ($_GET['full']=='true'){$full=1;} else {$full=0;}
	$answer=action_card_scanner($d,$_GET['span'],$new,$full);
	if ($answer['message']=='ok')
	{
?>
<div id="scanned">
<div id="action" class="tooltip" style="margin-bottom: 10px;">— Сканирование ожидает очереди...</div>
<div id="progress_percent" align="center">
0%
</div>
<progress id="progress" value="0" max="100"></progress>
<br><br>
<div id="loading"><img src="sr/loading.gif"></div>
<input type="button" onclick="kill=1;this.style.display='none';document.getElementById('loading').style.display='block';stopAction('<?=$answer['action']?>','scanned');" value="Остановить" style="background:#FF0000; padding: 10px; margin: 5px 0">
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
		echo $answer['message'];
		exit();
	}
}
$devices=array();
if ($result = mysqli_query($db, 'SELECT d.*,m.device FROM `devices` d 
LEFT JOIN `modems` m ON m.`device`=d.`id`
ORDER BY d.`title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$dev){$dev=$row['id'];}
		$devices[$row['id']]=$row['title'];
		$models[$row['id']]=$row['model'];
		$data[$row['id']]=unserialize($row['data']);
		$busy[$row['id']]=$row['device'];
	}
	if (count($devices)>1)
	{
		$dev=0;
?>
<u>Агрегатор</u>
<select id="device" style="margin-bottom: 10px;" onchange="getActions('ajax_card_scanner.php?device='+device.options[device.selectedIndex].value);">
	<option value="0">— Выберите агрегатор —</option>
<?
		foreach ($devices as $id=>$title)
		{
?>
	<option value="<? echo $id; echo '"'; if ($_GET['device']==$id){echo ' selected=1';} echo '>'.$title;?></option>
<?
		}
?>
</select>
<?
	}
	else
	{
		if (!$_GET['device']){$_GET['device']=$dev;}
	}
}
if (count($devices)<2 || $_GET['device'])
{
        $m=$models[$_GET['device']];
	
	if (strpos($m,'SR-Nano')!==false)
	{
		$hint='Место <em>(пример: A0)</em> • Диапазон <em>(A0-A99)</em> • Список <em>(A0, A99, B10)</em>';
		$cardholder='Место (X), диапазон (X-X) или список (X,X)</u><br><u>Оставьте пустым для полного сканирования диска';
		$placeholder='место, диапазон, список';
		$autostart=0;
		$new=1;
	}
	if ($m=='SR-Train')
	{
		$hint='Ряд <em>(пример: 0)</em> • Диапазон <em>(0-5)</em> • Список <em>(0, 2, 5)</em>';
		$cardholder='Ряд (X), диапазон ('.$data[$_GET['device']]['row_begin'].'-'.$data[$_GET['device']]['rows'].') или список (X,X)';
		$placeholder='ряд, диапазон, список';
		$autostart=0;
	}
	if ($m=='SR-Organizer')
	{
		$autostart=1;
	}
	if ($m=='SR-Organizer-Smart')
	{
		$autostart=1;
	}
	if ($m=='SR-Box-Bank' || $m=='SR-Board')
	{
		$hint='Горизонтальный ряд (8 карт) <em>(пример: 1)</em> • Диапазон <em>(пример: 1-64)</em> • Список <em>(пример: 1,2,8)</em>';
		$cardholder='Ряд (X), диапазон (X-X) или список (X,X)';
		$placeholder='ряд, диапазон, список';
		$autostart=0;
		$rowhide=1;
	}
	if ($m=='SR-Box-2-Bank')
	{
		$hint='Горизонтальный ряд (8 карт) <em>(пример: 1)</em> • Диапазон <em>(пример: 1-64)</em> • Список <em>(пример: 1,2,8)</em>';
		$cardholder='Ряд (X), диапазон (X-X) или список (X,X)';
		$placeholder='ряд, диапазон, список';
		$autostart=0;
		$rowhide=1;
	}
	if ($m=='SR-Box-8')
	{
		$autostart=1;
	}

if ($busy[$_GET['device']])
{
	echo '<div class="tooltip danger">— Перед сканирование следует отключить Онлайн-режим!</div><br><br><span class="link" onclick="location.href=\'online.php?device='.$_GET['device'].'\'">Выключить Онлайн</span> <span class="link" onclick="getActions(\'ajax_card_scanner.php\');">Продолжить</span></div>';
}
else
{
	if (!$autostart)
	{
		echo '<u>'.$cardholder.'</u>';
?>
<input type="text" id="span" maxlength="32" placeholder="<?=$placeholder?>">
<? if ($hint){ ?><div class="hint" style="margin:-5px 0 10px 0;"><?=$hint?></div><? } 
?><div<? if (!$new){echo ' style="display:none;"';} ?>>
<div><input type="checkbox" id="new" value="1"> <label for="new">Пропускать просканированные карты</label></div>
<div><input type="checkbox" id="full" value="1"> <label for="full">Пропускать пустые ячейки</label></div>
</div>
<div>
<br>
<div id="loading"><img src="sr/loading.gif"></div>
<input type="button" style="margin-top: -5px;" onclick="this.style.display='none';document.getElementById('loading').style.display='block';getActions('ajax_card_scanner.php?dev1=<?=$dev; if ($dev==0){?>&dev2='+document.getElementById('device').options[document.getElementById('device').options.selectedIndex].value+'<? } ?>&do=1&span='+document.getElementById('span').value+'&new='+document.getElementById('new').checked+'&full='+document.getElementById('full').checked);" value="Выполнить" style="padding: 10px; margin: 5px 0">
<?
	}
	else
	{
?>
<div class="tooltip">— Будут просканированы все СИМ-карты агрегатора.</div><br><br>
<div id="loading"><img src="sr/loading.gif"></div>
<input type="button" onclick="this.style.display='none';document.getElementById('loading').style.display='block';getActions('ajax_card_scanner.php?dev1=<?=$dev; if ($dev==0){?>&dev2='+document.getElementById('device').options[document.getElementById('device').options.selectedIndex].value+'<? } ?>&do=1');" value="Выполнить" style="padding: 10px; margin: 5px 0">
<?
	}
}
} ?>