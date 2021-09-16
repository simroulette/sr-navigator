<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;

// ---------------------------------------------
// Список активных сим-карт агрегатора
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
		if ($row['id']==$_GET['device'] || count($devices)<2)
		{
			if (strpos($row['model'],'SR-Nano')!==false)
			{
				$hint='Место <em>(пример: A0)</em> • Имя • Часть телефонного номера';
				$cardholder='СИМ-карта';
				$placeholder='Укажите место СИМ-карты, имя или номер';
				$autostart=0;
			}
			if ($row['model']=='SR-Train')
			{
				$hint='Начальный ряд <em>(пример: 1 — будут подключены ряды 1 и 4)</em> • Имя • Часть телефонного номера СИМ-карты';
				$cardholder='Ряд';
				$placeholder='Укажите ряд, имя или номер';
				$autostart=0;
			}
			if ($row['model']=='SR-Organizer')
			{
				$hint='Карта_1_ряда-Карта_2_ряда <em>(пример: 2-5)</em> • Имя • Часть телефонного номера СИМ-карты';
				$cardholder='СИМ-карты';
				$placeholder='Укажите СИМ-карты, имя или номер';
				$autostart=0;
			}
			if ($row['model']=='SR-Box-Bank')
			{
				$hint='Карта_1_ряда-Карта_2_ряда <em>(пример: 2-5)</em> • Имя • Часть телефонного номера СИМ-карты';
				$cardholder='СИМ-карты';
				$placeholder='Укажите СИМ-карты, имя или номер';
				$autostart=0;
			}
			if ($row['model']=='SR-Box-8')
			{
				$autostart=0;
				$cardholder='Ряд';
				$placeholder='Укажите СИМ-карты, имя или номер';
			}
		}
	}
}
if (count($devices)<1)
{
?>
<em>— Сначала нужно добавить в список свой агрегатор!</em>
<br><br>
<a href="devices.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить Агрегатор</a>
<?
}
else
{
if (count($devices)>1)
{
	$dev=0;
?>
<div class="sidebar">
<br>
Агрегатор
</div>
<select id="device" onchange="document.location.href='online.php?device='+this.options[this.options.selectedIndex].value;">
	<option value="0">— Выберите агрегатор —</option>
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

if ($sv_staff_id)
{
?>
<script>
var owner=0; 
</script>
<?
	$busy=1;
	if (flagGet($_GET['device'],'busy')==$sv_staff_id)
	{
		$busy=0;
	}
	else
	{
		$time=flagGet($_GET['device'],'busy',1);
		if ($time+300<time())
		{
			$busy=0;
		}
	}
}
else
{
?>
<script>
var owner=1; 
</script>
<?
	$busy=0;
}
?>
<input type="hidden" id="one" value="<?=$dev?>">
<?
if ((count($devices)<2 || $_GET['device']) && !$autostart)
{
?>
<div class="sidebar">
<br>
<?=$cardholder?>
</div>
<form id="form" onsubmit="<? if (!$sv_staff_id){?>onlineCreate(); <? } ?>return false;">
<input type="text" id="row" value="<?=$res[2]?>" maxlength="20" placeholder="<?=$placeholder?>">
</form>
<? if ($hint){ ?><div class="hint"><?=$hint?></div><? } ?>
<? 
}
?>
<div class="sidebar" style="margin-bottom: 10px;"></div>
<div class="tablo" id="waiting" style="display: <?=($busy ? 'inline-block' : 'none'); ?>;">Ожидайте начала сеанса...</div>
<div class="tablo" id="session" style="margin: 0 30px 20px 0; display: none;"></div>
<input type="submit" id="on" onclick="onlineCreate();return false;" value="Включить" class="green" style="padding: 10px; margin: 0 10px 12px 0; display: <?=(!$busy ? 'inline-block' : 'none'); ?>">
<input type="submit" id="stop" onclick="onlineStop();return false;" value="Выключить" style="background:#FF0000; padding: 10px; margin: 0 10px 7px 0;<? if (!$res[3] || $sv_staff_id){echo ' display: none;';}?>">
<input type="submit" id="restart" onclick="onlineRestart();return false;" value="Продлить сеанс" style="background:#0066BB; padding: 10px; margin: 0 10px 7px 0; display: none;">
<input type="submit" id="clear_sms" onclick="sendCommand('clear_sms');return false;" value="Очистить память SMS на SR" style="background:#0066BB; padding: 10px; margin: 0;<? if (!$res[3] || $sv_staff_id){echo ' display: none;';}?>">

<span onclick="rowhide();" class="rhide" title="Скрывать неактивные карты">H</span>

<div id="table" style="margin: 20px 0;">
<?

	if (!$sv_staff_id)
	{
		echo $res[0];
	}
?>
</div>

<div id="msg" style="display: none;"></div>

<div id="answer"<? if (!$res[3] || $sv_staff_id){echo ' style="display: none;"';}?>>
Принятые SMS:
<div class="icon_cont"><i class="icon-trash" title="Очистить список" onclick="document.getElementById('result_receive').innerHTML='<div style=\'display:none\'></div>';"></i></div>
<div id="result_receive" class="term_answer" style="height: 200px;"><?
$answer=onlineView($res[1]);
echo $answer[0].'<script>
id='.(int)$answer[1].';
</script>';
?>
</div>
</div>

<?
if ($autostart) 
{
?>
<form id="form">
<input type="hidden" id="row" value="0">
</form>
<script>
onlineCreate();
</script>
<?
}

	if ($GLOBALS['sv_owner_id'])
	{
		$operators=array();
		if ($result = mysqli_query($db, 'SELECT * FROM `operators`')) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				$row['name']=operator($row['name']);
				$operators[$row['name']]['title']=$row['title'];
				$operators[$row['name']]['title_r']=$row['title_r'];
				$operators[$row['name']]['color']=$row['color'];
				$operators[$row['name']]['color_r']=$row['color_r'];
			}
		}

		$table=array();
		$qry="SELECT c.* FROM `card2pool` p 
		INNER JOIN `cards` c ON c.`number`=p.`card`
		WHERE `pool`=".(int)$sv_pool." ORDER BY c.`number`";
		if ($result = mysqli_query($db, $qry)) 
		{
			$pnum=0;
			$title_td=0;
			while ($row = mysqli_fetch_assoc($result))
			{
				$o=operator($row['operator']);
				$row['operator']=$operators[$o]['title'];
				$row['operator_name']=$o;
				$row['color']=$operators[$o]['color'];
				if ($operators[$o]['title'] && $row['roaming']){$row['operator']=$operators[$o]['title_r'].' <span class="roaming">R</span> <div class="legend">'.$row['operator'].'</div>';$row['color']=$operators[$o]['color_r'];}
				if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
				$pnum++;
				$table[]=array(
					'num'=>$pnum,
					'number'=>$row['number'],
					'title'=>$row['title'],
					'place'=>$row['place'],
					'operator'=>$row['operator'],
					'operator_id'=>$row['operator_id'],
					'bg'=>$row['color'],
					'color'=>$color,
				);
				if ($row['title']){$title_td=1;}
			}
		}
		if (count($table))
		{
?>
		<div style="margin: 15px 0 6px 0;">Доступных карт: <?=count($table);?></div>
		<table class="table table_sort table_adaptive">
			<thead>
				<tr>
					<th class="sidebar">№</th>
					<? if ($title_td){?><th>Имя</th><? } ?>
					<th>Номер</th>
					<th style="text-align:right;">Место</th>
					<th>Оператор</th>
				</tr>  
			</thead>
<?
			$n=0;
			foreach ($table as $data)
			{
?>
				<tr>
					<td class="sidebar" align="right"><?=$data['num']?></td>
					<? if ($title_td){?><td><?=$data['title']?></td><? } ?>
					<td><a href="javascript:void(0);" onclick="document.getElementById('row').value='<?=$data['number']?>';">+<?=$data['number']?></a></td>
					<td align="right"><?=$data['place']?></td>
					<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center"><?=$data['operator']?></td>
				</tr>
<?
			}
?>
		</table>
<?
		}
	}
?>

<script>
var device=<?=(int)$_GET['device']?>;
setInterval(function()
{
	getModemStatus();
}, 2000);
</script>

<?
}
sr_footer();
?>