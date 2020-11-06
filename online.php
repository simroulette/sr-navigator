<?
// ===================================================================
// Sim Roulette -> Settings
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;

// ---------------------------------------------
// Список активных сим-карт устройства
// ---------------------------------------------

sr_header('Онлайн','win_action'); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<script>
var id=0;
</script>
<?
$devices=array();
$dev=$_GET['device'];
if ($result = mysqli_query($db, "SELECT * FROM `devices` ORDER BY `title`")) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$dev){$dev=$row['id'];}
		$devices[$row['id']]=$row['title'];
	}
}
if (count($devices)>1)
{
	$dev=0;
?>
<div class="sidebar">
<br>
Устройство
</div>
<select id="device" onchange="document.location.href='online.php?device='+this.options[this.options.selectedIndex].value;">
	<option value="0">— Выберите устройство —</option>
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($_GET['device']==$id){echo ' selected=1';} echo '>'.$title;?></option>
<?
	}
?>
</select>
<?
}
else
{
	$_GET['device']=$dev;
}
$res=onlineTable((int)$_GET['device']);
?>
<input type="hidden" id="one" value="<?=$dev?>">
<div class="sidebar">
<br>
Ряд
</div>
<form id="form" onsubmit="onlineCreate(); return false;">
<input type="text" id="row" value="<?=$res[2]?>" maxlength="5" placeholder="Начальный ряд, например: 1 (будут задействованы ряды 1 и 4) или место, например: A0">
</form>
<div class="sidebar" style="margin-bottom: 10px;"></div>
<input type="submit" onclick="onlineCreate();return false;" value="Включить" style="padding: 10px; margin: 0 10px 12px 0">
<input type="submit" id="stop" onclick="onlineStop();return false;" value="Выключить" style="background:#FF0000; padding: 10px; margin: 0 10px 7px 0;<? if (!$res[3]){echo ' display: none;';}?>">
<input type="submit" id="reconnect" onclick="onlineReconnect();return false;" value="Переподключить" style="background:#0066BB; padding: 10px; margin: 0 10px 7px 0;<? if (!$res[3]){echo ' display: none;';}?>">
<input type="submit" id="clear_sms" onclick="sendCommand('modem>pack:AT!plus;CMGDA=!quot;DEL ALL!quot;!num;!num;ALL!num;!num;1');return false;" value="Очистить память SMS" style="background:#0066BB; padding: 10px; margin: 0;<? if (!$res[3]){echo ' display: none;';}?>">

<div id="table" style="margin: 20px 0;">
<?
	echo $res[0];
?>
</div>

<div id="answer"<? if (!$res[3]){echo ' style="display: none;"';}?>>
Ответ Sim Roulette:
<div class="icon_cont"><i class="icon-trash" title="Очистить буфер" onclick="document.getElementById('result_receive').innerHTML='<div style=\'display:none\'></div>';"></i></div>
<div id="result_receive" class="term_answer" style="height: 200px;"><?
$answer=onlineView($res[1]);
echo $answer[0].'<script>
id='.(int)$answer[1].';
</script>';
?>
</div>
</div>

<script>
var device=<?=(int)$_GET['device']?>;
setInterval(function()
{
	getModemStatus();
}, 1000);
</script>

<?

sr_footer();
?>