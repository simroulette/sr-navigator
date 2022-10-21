<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$actions=array('contSms|Отправить SMS','contCall|Набрать номер или ввести USSD-команду');
if ((int)$_GET['number'] && $result = mysqli_query($db, 'SELECT `model` FROM `devices` WHERE `id`='.(int)$_GET['dev'].' AND (`model`="SR-Box-8" OR `model`="SR-Box-Bank" OR `model`="SR-Board" OR `model`="SR-Train")')) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if ($_GET['inc'])
		{
			$actions=array('contIncomingCall|Закончить прием вызовов','contSms|Отправить SMS','contCall|Набрать номер или ввести USSD-команду');
			$_GET['modem']=0;
			$_GET['number']='000';
		}
		else
		{
			$actions=array('contIncomingCall|Принять входящий вызов','contSms|Отправить SMS','contCall|Набрать номер или ввести USSD-команду');
		}
	}
}
?>
<u>Действие</u>
<script>
var cModem=<?=urldecode($_GET['modem'])?>;
var cNumber='<?=$_GET['number']?>';
</script>
<select id="actionSelect" onchange="onlineCard()">
<option value="-1">— Выберите действие —</option>
<?
	$n=1;
	foreach ($actions AS $txt)
	{
		$txt=explode('|',$txt);
		echo '<option value="'.$txt[0].'">'.$txt[1].'</option>';
	}
?>
</select>
<div id="winResult"></div>
<div id="contSms" style="display:none;">
<div class="sidebar">
<br>
<u>Номер получателя</u>
</div>
<input type="text" id="phone" maxlength="15" placeholder="Номер получателя">
<div class="sidebar">
<br>
<u>Текст SMS</u>
</div>
<input type="text" id="txt" maxlength="500" placeholder="Текст SMS">
<br>
<br>
<input type="button" onclick="onlineCommand(<?=$_GET['modem']?>,'sms',document.getElementById('phone').value,document.getElementById('txt').value);" value="Отправить SMS" style="padding: 10px; margin-top: 5px">
</div>
<div id="contCall" style="display:none;">
<div class="sidebar">
<br>
<u>Номер или команда USSD</u><br>
<u>для выбора пункта из USSD-меню используйте "|", например: *111*6#|1</u><br>
<u>для отправки USSD-команды посредством вызова добавьте в конце ";"</u>
</div>
<input type="text" id="phone2" maxlength="30" placeholder="Номер или Команда">
<br>
<br>
<input type="button" onclick="onlineCommand(<?=$_GET['modem']?>,'call',document.getElementById('phone2').value,'');" value="Набрать номер" style="padding: 10px; margin-top: 5px">
</div>