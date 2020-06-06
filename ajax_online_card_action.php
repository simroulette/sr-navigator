<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$actions=array('contSms|Отправить SMS','contCall|Набрать номер');
if ($_GET['action'])
{
	$a=explode('|',$actions[trim($_GET['action'],'a')]);
	if (action_card_create($_GET['id'],$a[0]))
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
Действие
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
Номер получателя
</div>
<input type="text" id="phone" maxlength="15" placeholder="Номер получателя">
<div class="sidebar">
<br>
Текст SMS
</div>
<input type="text" id="txt" maxlength="500" placeholder="Текст SMS">
<br>
<br>
<input type="button" onclick="onlineCommand(<?=$_GET['modem']?>,'sms',document.getElementById('phone').value,document.getElementById('txt').value);" value="Отправить SMS" style="padding: 10px; margin-top: 5px">
</div>
<div id="contCall" style="display:none;">
<div class="sidebar">
<br>
Номер или команда
</div>
<input type="text" id="phone2" maxlength="15" placeholder="Номер">
<br>
<br>
<input type="button" onclick="onlineCommand(<?=$_GET['modem']?>,'call',document.getElementById('phone2').value,'');" value="Набрать номер" style="padding: 10px; margin-top: 5px">
</div>