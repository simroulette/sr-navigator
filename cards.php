<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;

$devices=array();
if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices[$row['id']]=$row['title'];
	}
}

if ($_GET['delete']) // Deleting the SIM card | Удаление СИМ-карты
{
	$qry="DELETE FROM `cards` WHERE `number`=".(int)$_GET['delete'];
	mysqli_query($db,$qry);
}
if ($_GET['edit']) // Editing a SIM card | Редактирование СИМ-карты
{
	if ($_POST['save'] && $_POST['number'] && $_POST['place'] && $_POST['device'])
	{
		if ($_GET['edit']=='new')
		{
			$qry="INSERT `cards` SET
			`iccid`='".trim($_POST['iccid'],'+')."',
			`number`='".trim($_POST['number'],'+')."',
			`title`='".trim($_POST['title'])."',
			`place`='".$_POST['place']."',
			`device`='".$_POST['device']."',
			`operator`='".$_POST['operator']."',
			`balance`='".$_POST['balance']."',
			`comment`='".$_POST['comment']."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `cards` SET
			`iccid`='".trim($_POST['iccid'],'+')."',
			`number`='".trim($_POST['number'],'+')."',
			`title`='".trim($_POST['title'])."',
			`place`='".$_POST['place']."',
			`device`='".$_POST['device']."',
			`operator`='".$_POST['operator']."',
			`balance`='".$_POST['balance']."',
			`comment`='".$_POST['comment']."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
		}
		if ($status=mysqli_query($db,$qry))
		{			
			header('location:cards.php');
			exit();
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}

	sr_header('Редактирование СИМ-карты','win_action'); // Output page title and title | Вывод титул и заголовок страницы
	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `cards` WHERE `id`='.(int)$_GET['edit'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				if ($row['number']!=$row['place'])
				{
					$number='+'.$row['number'];
				}
				$iccid=$row['iccid'];
				$title=$row['title'];
				$place=$row['place'];
				$operator=$row['operator'];
				$device=$row['device'];
				$balance=$row['balance'];
				$comment=$row['comment'];
				$id=$row['id'];
			}
		}
	}

	$operators=array();

	$qry='SELECT * FROM `operators` ORDER BY `name`';
	if ($result = mysqli_query($db, $qry)) 
	{
		$name='';
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($name!=$row['name'])
			{
				$operators[operator($row['name'])]=$row['title'];
			}
			$name=$row['name'];
		}
	}
?>
<br>
<?
if (!$status)
{
?>
<div class="status_error">При сохранении данных произошла ошибка, проверьте правильность заполнения полей!</div>
<?
}
?>
<form method="post">
Номер телефона (обязательное поле)
<br>
<input type="text" name="number" value="<?=$number?>" maxlength="15">
<br><br>
Место, например: A0 для SR-Nano или 2-8 для SR-Train (обязательное поле)
<br>
<input type="text" name="place" value="<?=$place?>" maxlength="7">
<br><br>
Имя
<br>
<input type="text" name="title" value="<?=$title?>" maxlength="32">
<br><br>
ICCID
<br>
<input type="text" name="iccid" value="<?=$iccid?>" maxlength="20">
<br><br>
Оператор
<br>
<select name="operator">
<option value="0">— выберите из списка —</option>
<?
	foreach ($operators as $name=>$title)
	{
?>
	<option value="<?=$name?>"<? if ($operator==$name){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<br><br>
Агрегатор (обязательное поле)
<br>
<select name="device">
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($device==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<br><br>
Баланс
<br>
<input type="text" name="balance" value="<?=$balance?>" maxlength="10">
<br><br>
Комментарий
<br>
<textarea name="comment" style="height: 100px;"><?=$comment?></textarea>
<br><br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>

<?
}
else
{
	$table=array();
	$where=array();
	$limit='';
	$order='';
	if ($_GET['page']!='all')
	{
		if (!$_GET['page']){$_GET['page']=1;}
		$limit=' LIMIT '.((int)$GLOBALS['set_data']['page_limit']*($_GET['page']-1)).','.(int)$GLOBALS['set_data']['page_limit'];
	}
	if ($_GET['iccid'])
	{
		$where[]="c.iccid LIKE '%".(int)$_GET['iccid']."%'";
	}
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
		$where[]="(o.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%')";
	}
	if ($_GET['device'])
	{
		$where[]="c.device=".(int)$_GET['device'];
	}
	if ($_GET['place'])
	{
		$where[]="c.place LIKE '".mysqli_real_escape_string($db,$_GET['place'])."%'";
	}
	if ($_GET['balance'])
	{
		$a=str_replace(',','.',$_GET['balance']);
		if ($_GET['balance'][0]!='>' && $_GET['balance'][0]!='<')
		{
			$where[]="c.balance=".mysqli_real_escape_string($db,$a);
		}
		else
		{
			$where[]="c.balance".mysqli_real_escape_string($db,$a);
		}
	}
	if (!$_GET['sort'])
	{
		$order=' ORDER BY CHAR_LENGTH(c.`place`),c.`place`';
	}
	elseif ($_GET['sort']==1)
	{
		$order=' ORDER BY c.`device`,CHAR_LENGTH(c.`place`),c.`place`';
	}
	elseif ($_GET['sort']==2)
	{
		$order=' ORDER BY c.`number`';
	}
	elseif ($_GET['sort']==8)
	{
		$order=' ORDER BY c.`iccid`';
	}
	elseif ($_GET['sort']==3)
	{
		$order=' ORDER BY c.`balance`';
	}
	elseif ($_GET['sort']==6)
	{
		$order=' ORDER BY c.`time_balance`';
	}
	elseif ($_GET['sort']==7)
	{
		$order=' ORDER BY c.`time_balance` DESC';
	}
	elseif ($_GET['sort']==4)
	{
		$order=' ORDER BY c.`operator`';
	}
	elseif ($_GET['sort']==5)
	{
		$order=' ORDER BY c.`time` DESC';
	}

	if (count($where)){$where=' AND '.implode(' AND ',$where);} else {$where='';}

// Получаем список актуальных операторов
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
	if ($result = mysqli_query($db, 'SELECT count(c.`number`) AS counter FROM `cards` c')) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$total=$row['counter'];	
		}
	}
	$title_td=0;
	if ($result = mysqli_query($db, 'SELECT c.*,d.title AS device_name,m.modems FROM `cards` c 
	LEFT JOIN `devices` d ON c.`device`=d.`id` 
	LEFT JOIN `modems` m ON m.`device`=d.`id` 
	WHERE 1=1'.$where.$order.$limit)) 
	{
		$n=1;
		$nn=array();
		$copy=array();
		while ($row = mysqli_fetch_assoc($result))
		{
			$o=operator($row['operator']);
			$row['operator']=$operators[$o]['title'];
			$row['operator_name']=$o;
			$row['color']=$operators[$o]['color'];
			if ($operators[$o]['title'] && $row['roaming']){$row['operator']=$operators[$o]['title_r'].' <span class="roaming">R</span> <div class="legend">'.$row['operator'].'</div>';$row['color']=$operators[$o]['color_r'];}
			if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
			if ($row['status']=='inprogress')
			{
				$row['status']='Процесс';
			}
			else if ($row['status']=='waiting')
			{
				$row['status']='В очереди';
			}
			else
			{
				$row['status']='';
			}
			if ($_GET['page']=='all'){$pnum++;} else {$pnum=$n+$GLOBALS['set_data']['page_limit']*($_GET['page']-1);}
			$modems=unserialize($row['modems']);
			$a=explode('-',$row['place']);
			if ($modems[0]==$row['place'] || $modems[$a[1]][0]==$a[0]){$online=1;} else {$online=0;}
			$table[]=array(
				'num'=>$pnum,
				'id'=>$row['id'],
				'iccid'=>$row['iccid'],
				'number'=>$row['number'],
				'title'=>$row['title'],
				'comment'=>$row['comment'],
				'time'=>srdate('d.m.Y H:i:s',$row['time']),
				'time_balance'=>$row['time_balance'],
				'time_last_balance'=>$row['time_last_balance'],
				'model'=>$row['model'],
				'dev'=>$row['device'],
				'device'=>$row['device_name'],
				'place'=>$row['place'],
				'operator'=>$row['operator'],
				'operator_name'=>$row['operator_name'],
				'status'=>$row['status'],
				'balance'=>$row['balance'],
				'last_balance'=>$row['last_balance'],
				'online'=>$online,
				'bg'=>$row['color'],
				'color'=>$color,
			);
			if (in_array($row['number'],$nn)){$copy[$row['number']]='warning';}
			$nn[]=$row['number'];
			if ($row['title']){$title_td=1;}
			$n++;
		}
	}
	if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$devices[$row['id']]=$row['title'];
		}
	}
	if ($_GET['type']=='csv')
	{
		header('Content-Type:csv/plain');
		$str="ICCID\tНомер\tАгрегатор\tID\tМесто\tБаланс\tОператор\tСеть\tВремя\tИмя\tКомментарий
";
		foreach ($table as $data)
		{
			$str.=$data['iccid']."\t";
			if ($data['number']==$data['place'])
			{
				$str.="Блокировка\t".$data['device']."\t".$data['dev']."\tP:".$data['place']."\t".$data['balance']."\t—\t—\t".$data['time'].'
';
			}
			else
			{
				$str.='+'.$data['number']."\t".$data['device']."\t".$data['dev']."\tP:".$data['place']."\t".$data['balance']."\t".strip_tags($data['operator'])."\t".$data['operator_name']."\t".$data['time']."\t".$data['title']."\t".$data['comment'].'
';
			}
		}
		if ($GLOBALS['set_data']['cp-1251'])
		{
			echo iconv('UTF-8//IGNORE', 'windows-1251//IGNORE', $str);
		}
		else
		{
			echo $str;
		}
		exit();
	}
	sr_header("Список СИМ-карт"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<form method="get">
<div class="sidebar">
Номер телефона
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
<div class="sidebar">
<br>
Место
</div>
<input type="text" name="place" value="<?=$_GET['place']?>" maxlength="7" placeholder="Место. Примеры: A0 или A или 2-8 или 2">
<div class="sidebar">
<br>
ICCID
</div>
<input type="text" name="iccid" value="<?=$_GET['iccid']?>" maxlength="20" placeholder="Часть ICCID. Пример: 897">
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
<? 
if (count($devices)>1)
{
?>
<div class="sidebar">
<br>
Агрегатор
</div>
<select name="device">
	<option value="0">Все агрегаторы</option>
<?
	foreach ($devices as $id=>$title)
	{
?>
	<option value="<?=$id?>"<? if ($_GET['device']==$id){echo ' selected=1';}?>><?=$title?></option>
<?
	}
?>
</select>
<?
}
?>
<div class="sidebar">
<br>
Баланс
</div>
<input type="text" name="balance" value="<?=$_GET['balance']?>" maxlength="8" placeholder="Баланс. Пример: >100">
<div class="sidebar">
<br>
Отсортировать
</div>
<select name="sort">
<option value="0"<? if (!$_GET['sort']){echo ' selected=1';}?>>По местам</option>
<option value="1"<? if ($_GET['sort']==1){echo ' selected=1';}?>>По агрегаторам</option>
<option value="8"<? if ($_GET['sort']==8){echo ' selected=1';}?>>По ICCID</option>
<option value="2"<? if ($_GET['sort']==2){echo ' selected=1';}?>>По номерам телефонов</option>
<option value="3"<? if ($_GET['sort']==3){echo ' selected=1';}?>>По балансам</option>
<option value="6"<? if ($_GET['sort']==6){echo ' selected=1';}?>>По времени получения баланса ↑</option>
<option value="7"<? if ($_GET['sort']==7){echo ' selected=1';}?>>По времени получения баланса ↓</option>
<option value="4"<? if ($_GET['sort']==4){echo ' selected=1';}?>>По операторам</option>
<option value="5"<? if ($_GET['sort']==5){echo ' selected=1';}?>>По времени</option>
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
<div style="margin-bottom: 10px;"></div>
<input type="submit" name="save" value="Отфильтровать" style="padding: 10px; margin: 5px 0 20px 0">
</form>

<?
	if (count($table))
	{
?>
		<em style="float: right; margin-top: -60px;font-style: italic;">Карт: <? if (count($table)!=$total){echo count($table).'/'.$total;} else {echo $total;}?></em>
<form method="post" action="pools.php?edit=new" id="cards" name="cards">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'cards','check')"></th>
				<th class="sidebar">№</th>
				<? if ($title_td){?><th>Имя</th><? } ?>
				<? if ($GLOBALS['set_data']['iccid_show']){?><th class="sidebar">ICCID</th><? } ?>
				<th>Номер</th>
				<? if (count($devices)>1){ ?>
				<th class="sidebar">Агрегатор</th>
				<? } ?>
				<th style="text-align:right;">Место</th>
				<th style="text-align:right;">Баланс</th>
				<th class="sidebar">Оператор</th>
				<th class="sidebar">Время</th>
				<th></th>
			</tr>  
		</thead>
<?
		$n=0;
		foreach ($table as $data)
		{
?>
		<tr<? if ($data['online']){echo ' class="rowsel"';}?>>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['number'].';'.$data['place'].';'.$data['device'].';'.$data['id']?>"></td>
			<td class="sidebar"><?=$data['num']?></td>
			<? if ($title_td){?><td><?=$data['title']?></td><? } ?>
			<? if ($GLOBALS['set_data']['iccid_show']){?><td class="sidebar"><?=$data['iccid']?></span><td><? } ?>
			<?
			if ($data['place']!=$data['number'] && $data['number']){
			?>
			<span class="but_win <?=$copy[$data['number']]?>" data-id="win_action" data-title="Управление номером +<?=$data['number']?>" data-type="ajax_card_action.php?id=<?=$data['number']?>" data-height="400" data-width="600">+<?=$data['number']?></span>
			<? } elseif ($data['number']) { ?>
			<em>Карта заблокирована</em>
			<? } ?>
			</td>
			<? if (count($devices)>1){ ?>
			<td class="sidebar"><?=$data['device']?></td>
			<? } ?>
			<td align="right"><?=$data['place']?></td>
			<?
			if ($data['place']!=$data['number']){
			if ($data['time_balance'])
			{
				$last_balance='';
				if ($data['last_balance'] && $data['last_balance']!=$data['balance'])
				{
					$last_balance=' '.balance_out($data['balance']-$data['last_balance'],'+');
					if ($data['balance']-$data['last_balance']>0){$last_balance='<span class="plus" title="'.srdate('d.m.Y H:i',$data['time_last_balance']).'">'.$last_balance.'</span>';} else {$last_balance='<span class="minus" title="'.srdate('d.m.Y H:i',$data['time_last_balance']).'">'.$last_balance.'</span>';} 
				}
				$balance=balance_out($data['balance'],'').$last_balance.'<div class="legend">'.srdate('d.m.Y H:i',$data['time_balance']).'</div>';
			} 
			else 
			{
				$balance='—';
			}
			?>
			<td align="right"><?=$balance?></td>
			<? } else { ?>
			<td align="right"><em>—</em></td>
			<?
			}
			?>
			<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center" class="sidebar"><?=$data['operator']?></td>
			<td class="sidebar"><?=$data['time']?></td>
			<td><a href="cards.php?edit=<?=$data['id']?>"><i class="icon-pencil"></i></a> 
			<? if ($data['online']){?>
			Online
			<? } else { ?>
			<a href="javascript:void();" onclick="onlineCreateOut(<?=$data['dev']?>,<?=$data['number']?>);"><b>O</b></a>
			<? } ?>
			</td>
		</tr>
<?
		}
?>
	</table>

<br>
<input type="submit" name="del" value="Удалить карты" style="background: #F00; float:left; margin: 15px 10px 0 0">
<input type="submit" name="add" value="Создать пул" class="green" style="margin: 15px 5px 0 0">
<a href="cards.php?edit=new" class="link" style="margin: 15px 5px 0 0">Добавить СИМ-карту</a>
<span class="link but_win" data-id="win_action" data-title="Сканирование диапазона СИМ-карт" style="margin-top: 15px;" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканирование диапазона</span>
</form>

<a class="link violet" style="margin: 0px 5 15px 0;" href="<?
if (strpos($_SERVER['REQUEST_URI'],'?'))
{
	echo $_SERVER['REQUEST_URI'].'&type=csv';
}
else
{
	echo $_SERVER['REQUEST_URI'].'?type=csv';
}
?>">Экспорт в CSV</a>

<div class="link violet" onclick="FindFile();">Импорт из CSV или с агрегатора</div>
<form action="ajax_load_csv.php" target="rFrame" method="POST" enctype="multipart/form-data">  
<div class="hiddenInput">
 <input type="file" id="my_hidden_file" name="loadfile" onchange="LoadFile();">  
 <input type="submit" id="my_hidden_load" style="display: none" value='Загрузить'>  
</div></form>

<?
	}
	else
	{
?>
<br><br>
<em>— Список СИМ-карт пуст!</em>
<br><br>
<a href="cards.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить СИМ-карту</a>
<span class="link but_win" data-id="win_action" data-title="Сканирование диапазона СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканирование диапазона</span>

<div class="link" onclick="FindFile();">Импорт из CSV или с агрегатора</div>
<form action="ajax_load_csv.php" target="rFrame" method="POST" enctype="multipart/form-data">  
<div class="hiddenInput">
 <input type="file" id="my_hidden_file" name="loadfile" onchange="LoadFile();">  
 <input type="submit" id="my_hidden_load" style="display: none" value='Загрузить'>  
</div></form>

<?
	}
}
?>
<iframe id="rFrame" name="rFrame" style="display: none;"> </iframe> 
<?
sr_footer();
?>