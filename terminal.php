<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");

if ($_POST['save'] && $_POST['commands'] && $_POST['device'])
{
	if ($result = mysqli_query($db, 'SELECT * FROM `devices` WHERE `id`='.(int)$_POST['device'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$step=$row['step'];
		}
	}

	$com=explode('
',trim($_POST['commands']));
	for ($i=0;$i<count($com);$i++)
	{
		if ($c=trim($com[$i]))
		{
			$qry="INSERT `link_outgoing` SET
			`device`='".(int)$_POST['device']."',
			`command`='".$c."',
			`step`=".(int)$step++;
			mysqli_query($db,$qry);
		}
	}

	$qry="UPDATE `devices` SET
	`step`=".$step."
	WHERE `id`=".(int)$_POST['device'];
	mysqli_query($db,$qry);
}
elseif ($_POST['save'])
{
	$status=0;
}

sr_header("Терминал"); // Output page title and title | Вывод титул и заголовок страницы

$devices=array();
if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices[$row['id']]=$row['title'];
	}
}
if (count($devices)<1)
{
?>
<br>
<em>— Сначала нужно добавить в список свой агрегатор!</em>
<br><br>
<a href="devices.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить Агрегатор</a>
<?
}
else
{
?>
<br>
Ответ Sim Roulette:
<div class="icon_cont"><i class="icon-trash" title="Очистить буфер" onclick="document.getElementById('result_receive').innerHTML='';"></i></div>
<div id="result_receive" class="term_answer"></div>
<script>
var term_int=<?=$GLOBALS['set_data']['term_int']?>;
</script>
<script src="sr/terminal.js" type="text/javascript"></script>
<form onsubmit="getRequest();return false;">
<div <? if (count($devices)<2){echo 'style="display:none;"';}?>?>
<br>
Агрегатор
<br>
<select id="device" name="device">
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($_POST['device']==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<br>
</div>
<br>
Команда:
<input type="text" id="command" name="command" style="margin: 6px 0 0 0;">
<input type="hidden" id="step" name="step" value="0">
<br><br>
<input type="button" value="Отправить" style="padding: 10px; float: left;" onclick="getRequest();return false;">
<? 
$a=explode(';',$GLOBALS['set_data']['terminal_hot']);
foreach ($a AS $data)
{
	echo '<div class="example" onclick="document.getElementById(\'command\').value=\''.$data.'\'">'.$data.'</div>';
}
?>
<div style="clear: both;"><span></span></div>
</form>
Лог команд:<div class="icon_cont"><i class="icon-trash" title="Очистить буфер" onclick="document.getElementById('result_send').innerHTML='';"></i></div>
<div id="result_send" class="term"></div>
<?
}
sr_footer();
?>