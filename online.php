<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$status=1;

// ---------------------------------------------
// Список активных сим-карт агрегатора
// ---------------------------------------------
sr_header('Онлайн','win_action'); // Output page title and title | Вывод титул и заголовок страницы
?>
<script>
var id=0;
</script>
<?
$devices=array();
$dev=$_GET['device'];

$qry="SELECT d.*,a.`status` FROM `devices` d
LEFT JOIN `actions` a ON a.`device`=d.`id` AND a.`status`<>'suspended'
ORDER BY d.`title`";

if ($result = mysqli_query($db, $qry)) 
{
	$m='';
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$dev){$dev=$row['id'];}
		$devices[$row['id']]=$row['title'];
		$actions[$row['id']]=$row['status'];
		if (!$m){$m=$row['model'];}
		if ($row['id']==$_GET['device']){$m=$row['model'];}
	}
	if (strpos($m,'SR-Nano')!==false)
	{
		$hint='Место <em>(пример: A0)</em> • Имя • Часть телефонного номера';
		$cardholder='СИМ-карта';
		$placeholder='Укажите место СИМ-карты, имя или номер';
		$autostart=0;
	}
	if ($m=='SR-Train')
	{
		$hint='Начальный ряд <em>(пример: 1 — будут подключены ряды 1 и 4)</em> • Имя • Часть телефонного номера СИМ-карты';
		$cardholder='Ряд';
		$placeholder='Укажите ряд, имя или номер';
		$autostart=0;
	}
	if ($m=='SR-Organizer')
	{
		$hint='Карта_1_ряда-Карта_2_ряда <em>(пример: 2-5)</em> • Имя • Часть телефонного номера СИМ-карты';
		$cardholder='СИМ-карты';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$rowhide=1;
		$review=1;
		$autostart=0;
	}
	if ($m=='SR-Organizer-Smart')
	{
		$hint='Карта_1_ряда-Карта_2_ряда <em>(пример: 2-5)</em> • Имя • Часть телефонного номера СИМ-карты';
		$cardholder='СИМ-карты';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$autostart=1;
		$rowhide=1;
		$review=1;
	}
	if ($m=='SR-Box-Bank')
	{
		$hint='Ряд карт <em>(пример: 5)</em> • Места <em>(пример: A1,B4,H8)</em> • Часть телефонного номера СИМ-карты • Поиск по имени и комментарию ';
		$cardholder='СИМ-карты';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$autostart=0;
		$rowhide=1;
	}
	if ($m=='SR-Board')
	{
		$hint='Ряд карт <em>(пример: 5)</em> • Места <em>(пример: A1,B4,H8)</em> • Часть телефонного номера СИМ-карты • Поиск по имени и комментарию ';
		$cardholder='СИМ-карты';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$autostart=0;
		$rowhide=1;
	}
	if ($m=='SR-Box-2-Bank')
	{
		$hint='Ряд карт <em>(пример: 5)</em> • Места <em>(пример: A1,B4)</em> • Часть телефонного номера СИМ-карты • Поиск по имени и комментарию ';
		$cardholder='СИМ-карты';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$autostart=0;
		$rowhide=1;
	}
	if ($m=='SR-Box-8')
	{
		$cardholder='Ряд';
		$placeholder='Укажите СИМ-карты, имя или номер';
		$autostart=1;
	}
}
if (count($devices)<1)
{
?>
<div class="tooltip">— Сначала нужно <a href="devices.php?edit=new">добавить в список</a> свой агрегатор!</div>
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
	if (!$_GET['device']){$rowhide=0;}
	}
?>
</select>
<div class="sidebar">
<br>
</div>
<?
}
else
{
	$_GET['device']=$dev;
}
$res=onlineTable((int)$_GET['device'],$_COOKIE['srn_hide']);

?>
<script>
var owner=1; 
</script>
<?
	$busy=0;
?>
<input type="hidden" id="one" value="<?=$dev?>">
<?
$stop=0;
if (count($devices)<2 || $_GET['device'])
{
	if ($autostart)
	{
?>
		<form id="form" onsubmit="onlineCreate(); return false;">
		<input type="hidden" id="row" value="0">
		</form>
<?
	}
	else
	{
		if ($actions[$_GET['device']])
		{
?>
			<div class="tooltip danger">— Онлайн-режим не может быть включен пока есть активные задачи!<br>— Снимите или приостановите <a href="actions.php"><b>задачи в списке</b></a>.</div>
<?
		 	$stop=1;
		}
		else
		{
?>
<div class="sidebar">
<?=$cardholder?>
</div>
<form id="form" onsubmit="onlineCreate(); return false;">
<input type="text" id="row" value="<?=$res[2]?>" maxlength="20" placeholder="<?=$placeholder?>">
</form>
<? if ($hint){ ?><div class="hint" style="margin-bottom: 10px;"><?=$hint?></div><? } 
 
		}
	}
}
if (!$stop)
{
?>
<div class="sidebar" style="margin-bottom: 10px;"></div>
<div class="tablo" id="session" style="display: none;"></div>
<input type="submit" id="restart" onclick="onlineRestart();return false;" value="Продлить сеанс" class="width" style="background:#0066BB; display: none;">
<input type="submit" id="on" onclick="onlineCreate();return false;" value="Включить" class="green width" style="display: <?=(!$busy ? 'inline-block' : 'none'); ?>">
<input type="submit" id="stop" onclick="onlineStop();return false;" value="Выключить" class="width" style="background:#FF0000;<? if (!$res[3]){echo ' display: none;';}?>">
<?
}
?>
<input type="submit" id="clear_sms" class="width" onclick="sendCommand('clear_sms');return false;" value="Очистить SMS-память на SIM" style="<? if (!$res[3]){echo ' display: none;';}?>">
<? if ($rowhide || $review){?><div class="tools" style="margin-top: 0; padding: 9px 10px;">
<? if ($review){ 
if ($a=flagGet($_GET['device'],'review')){?>
<script>rview=1;</script>
<? } ?>
<i class="icon-arrows<? if ($a){?> icon-active<? } ?>" onclick="review('<?=$_GET['device']?>');" title="Автоматический перебор карт"></i>
<? } 

if ($rowhide)
{
	if ($_COOKIE['srn_hide'])
	{
?>
<script>rhide=1;</script>
<i class="icon-eye-off" onclick="rowhide();" title="Показывать неактивные карты"></i>
<? 	} 
	else 
	{ 
?>
<i class="icon-eye" onclick="rowhide();" title="Скрывать неактивные карты"></i>
<? 
	} 
}
?>
</div><? } ?>
<div class="clr"><span></span></div>

<div id="table" class="table_box" style="display: none; margin: 20px 0;">
<?
	echo $res[0];
?>
</div>

<div id="msg" style="display: none;"></div>

<div id="answer"<? if (!$res[3]){echo ' style="display: none;"';}?>>
Принятые SMS:
<div class="icon_cont"><i class="icon-trash" title="Очистить список" onclick="document.getElementById('result_receive').innerHTML='<div style=\'display:none\'></div>';"></i></div>
<div class="table_box">
<div id="result_receive" class="term_answer" style="height: 200px;"><?
$answer=onlineView($res[1]);
echo $answer[0].'<script>
id='.(int)$answer[1].';
</script>';
?>
</div>
</div>
</div>

<?
	if ($GLOBALS['sv_owner_id'])
	{
?>
<hr>
<?
	$a=$_GET; 
	unset($a['device']); 
	unset($a['filter']); 
	if (empty($a)){echo '<h2>Список номеров</h2><div id="filter_hint" onclick="fltr();">Отфильтровать</div>';} else {echo '<h2>Параметры поиска карт</h2><div id="filter_hint" onclick="fltr();" style="display:none;">Отфильтровать</div>';}
?>
<br>
<div id="filter"<? if (empty($a)){echo ' class="hide"';}?>>
<form method="get">
<div class="sidebar">
Номер телефона
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
<div class="sidebar">
<br>
Имя
</div>
<input type="text" name="title" value="<?=$_GET['title']?>" maxlength="32" placeholder="Имя СИМ-карты. Пример: Исходящий номер">
<div class="sidebar">
<br>
Оператор
</div>
<input type="text" name="operator" value="<?=$_GET['operator']?>" maxlength="15" placeholder="Название оператора. Пример: МТС">
<div class="sidebar">
<br>
Место
</div>
<input type="text" name="place" value="<?=$_GET['place']?>" maxlength="7" placeholder="Место. Примеры: A0 или A или 2-8 или 2">
<div class="sidebar">
<br>
Комментарий
</div>
<input type="text" name="comment" value="<?=$_GET['comment']?>" maxlength="32" placeholder="Комментарий. user:login (^ — для отрицания)">
<div class="sidebar">
<br>
Отсортировать
</div>
<select name="sort">
<option value="0"<? if (!$_GET['sort']){echo ' selected=1';}?>>По номерам телефонов</option>
<option value="2"<? if ($_GET['sort']==2){echo ' selected=1';}?>>По местам</option>
<option value="4"<? if ($_GET['sort']==4){echo ' selected=1';}?>>По операторам</option>
</select>
<?
if ($total>(int)$GLOBALS['set_data']['page_limit'])
{
?>
<div class="sidebar">
<br>
Страница
</div>
<select name="page">
<?
	$v=ceil($total/(int)$GLOBALS['set_data']['page_limit']);
	for ($i=1;$i<=$v;$i++)
	{
?>
<option value="<?=$i?>"<? if ($_GET['page']==$i){echo ' selected=1';}?>><?='Страница '.$i.' ('.((int)$GLOBALS['set_data']['page_limit']*($i-1)).'—'.((int)$GLOBALS['set_data']['page_limit']*$i).')'?></option>
<?
	}
?>
<option value="all"<? if ($_GET['page']=='all'){echo ' selected=1';}?>>Все</option>
</select>
<?
}
?>
<div class="sidebar">
<br>
</div>
<div style="margin: 5px 0 20px 0;">
<input type="submit" name="filter" value="Отфильтровать" class="green width">
<a href="javascript:void(0);" onclick="fltr_off();" class="link width">Спрятать фильтр</a>
</div>
<div class="clr"><span></span></div>
</form>
</div>


<?
	$where=array();
	$limit='';
	$order='';
	if ($_GET['number'])
	{
		$where[]="c.number LIKE '%".(int)$_GET['number']."%'";
	}
	if ($_GET['title'])
	{
		$where[]="c.title LIKE '%".mysqli_real_escape_string($db,$_GET['title'])."%'";
	}
	if ($_GET['operator'])
	{
		$where[]="(o1.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o1.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%')";
	}
	if ($_GET['place'])
	{
		$where[]="c.place LIKE '".mysqli_real_escape_string($db,$_GET['place'])."%'";
	}
	if ($_GET['comment'])
	{
		if (strpos($_GET['comment'],'^')!==false)
		{
			$where[]="c.comment NOT LIKE '%".mysqli_real_escape_string($db,str_replace('^','',$_GET['comment']))."%'";
		}
		else
		{
			$where[]="c.comment LIKE '%".mysqli_real_escape_string($db,$_GET['comment'])."%'";
		}
	}
	if (!$_GET['sort'])
	{
		$order=' ORDER BY c.`number`';
	}
	elseif ($_GET['sort']==2)
	{
		$order=' ORDER BY left(c.`place`,1),CHAR_LENGTH(c.`place`),c.`place`';
	}
	elseif ($_GET['sort']==4)
	{
		$order=' ORDER BY c.`operator`';
	}

	if (count($where)){$where=' AND '.implode(' AND ',$where);} else {$where='';}

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
	$qry='SELECT c.*,o1.`title` AS `operator_name`, o1.`color` AS `color`, d.`title` AS `device_title` FROM `card2pool` p 
	INNER JOIN `cards` c ON c.`number`=p.`card`
	INNER JOIN `devices` d ON d.`id`=c.`device` 
	LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
	WHERE `pool`='.(int)$sv_pool.$where.$order;
	if ($result = mysqli_query($db, $qry)) 
	{
		$pnum=0;
		$title_td=0;
		$comment_present=0;
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
			$pnum++;
			if ($row['comment'])
			{
				$comment_present++;
			}
			$table[]=array(
				'num'=>$pnum,
				'number'=>$row['number'],
				'title'=>$row['title'],
				'device'=>$row['device_title'],
				'device_id'=>$row['device'],
				'place'=>$row['place'],
				'operator'=>$row['operator_name'],
				'operator_id'=>$row['operator_id'],
				'bg'=>$row['color'],
				'comment'=>$row['comment'],
				'color'=>$color,
				);
				if ($row['title']){$title_td=1;}
		}
	}
	if (count($table))
	{
?>
		<div style="margin: 15px 0 6px 0;">⏵ Доступно карт: <?=count($table);?></div>
<div class="table_box">
		<table class="table table_sort table_adaptive">
			<thead>
				<tr>
					<th class="sidebar">№</th>
					<? if ($title_td){?><th class="sidebar">Имя</th><? } ?>
					<th>Номер</th>
<?
		if (count($devices)>1)
		{
?>
					<th>Агрегатор</th>
<?
		}
?>
					<th class="sidebar" style="text-align:right;">Место</th>
					<th class="sidebar">Оператор</th>
					<? if ($comment_present){?><th>Комментарий</th><? } ?>
				</tr>  
			</thead>
<?
		$n=0;
		foreach ($table as $data)
		{
?>
				<tr>
					<td class="sidebar" align="right"><?=$data['num']?></td>
					<? if ($title_td){?><td class="sidebar"><?=$data['title']?></td><? } ?>
					<td><? if ($data['title']){?><span class="extinfo"><s><?=$data['title']?></s><br></span><? } ?><a href="javascript:void(0);" onclick="getNumber(<?=$data['device_id']?>,'<?=$data['number']?>')">+<?=$data['number']?></a></td>
<?
			if (count($devices)>1)
			{
?>
					<td><?=$data['device']?></td>
<?
			}
?>
					<td class="sidebar" align="right"><?=$data['place']?></td>
					<td class="sidebar"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center"><?=$data['operator']?></td>
<?
 			if ($comment_present)
			{
				echo '<td><span id="sce_'.$data['number'].'">';
				echo str_replace("\n",'<br>',
					str_replace($_GET['comment'],'<span class="note" style="padding: 0px;">'.$_GET['comment'].'</span>',
						trim(preg_replace('/\n(user:(.*)time:(.*))\n/Us', "\n".'<user>${2} • ${3}</user>'."\n",$data['comment']))));
			}
?></span>

</textarea></td>
				</tr>
<?
		}
?>
		</table>
</div>

<script>
function com_edit(t)
{
	if ($(t).hasClass('icon-pencil'))
	{
		$(t).addClass('icon-download-1');
		$(t).removeClass('icon-pencil');
		$(t).attr('title','Сохранить');
		$(t).parent().find('textarea').slideDown(500);
	}
	else
	{
		$(t).removeClass('icon-download-1');
		$(t).addClass('icon-pencil');
		$(t).attr('title','Редактировать');
		$(t).parent().find('textarea').slideUp(500);
		commentSave($(t).parent().find('textarea').attr('id'),$(t).parent().find('textarea').val());
	}
}
</script>
<?
	}
	elseif ($where)
	{
?>
<div class="tooltip danger">— По результам фильтрации номеров нет!</div>
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
getModemStatus();
</script>

<?
}
sr_footer();
?>
