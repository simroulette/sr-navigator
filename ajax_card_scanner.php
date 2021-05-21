<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================
include("_func.php");
if ($_GET['do'])
{
	if (!$d=$_GET['dev1']){$d=$_GET['dev2'];}	
	if ($_GET['new']=='true'){$new=1;} else {$new=0;}
	$answer=action_card_scanner($d,$_GET['span'],$new);
	if ($answer['message']=='ok')
	{
?>
<div id="scanned">
<div id="action" style="margin-bottom: 10px;">Сканирование ожидает очереди...</div>
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
		echo $answer['message'];
		exit();
	}
}
$devices=array();
if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$dev){$dev=$row['id'];}
		$devices[$row['id']]=$row['title'];
	}
	if (count($devices)>1)
	{
		$dev=0;
?>
Агрегатор
<select id="device">
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
<br><br>
<?
	}
}
?>
Диапазон (*-*) или список (*,*) рядов (SR-Train) либо мест (SR-Nano) 
<input type="text" id="span" maxlength="32" placeholder="Ряд(ы) или Место(а)">
<br><br>
<input type="checkbox" id="new" value="1">
<label for="new">Только новые (опция для SR-Nano)</label> 
<div>
<br>
<input type="button" onclick="getActions('ajax_card_scanner.php?dev1=<?=$dev; if ($dev==0){?>&dev2='+document.getElementById('device').options[document.getElementById('device').options.selectedIndex].value+'<? } ?>&do=1&span='+document.getElementById('span').value+'&new='+document.getElementById('new').checked);" value="Выполнить" style="padding: 10px; margin: 5px 0">