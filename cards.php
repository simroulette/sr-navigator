<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");

$status=1;

$devices=array();
if ($result = mysqli_query($db, 'SELECT * FROM `devices` ORDER BY `title`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$devices[$row['id']]=$row['title'];
		$devices_model[$row['id']]=$row['model'];
		$devices_data[$row['id']]=unserialize($row['data']);
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
			`iccid`='".mysqli_real_escape_string($db,trim($_POST['iccid'],'+'))."',
			`number`='".mysqli_real_escape_string($db,trim($_POST['number'],'+'))."',
			`title`='".mysqli_real_escape_string($db,trim($_POST['title']))."',
			`place`='".mysqli_real_escape_string($db,$_POST['place'])."',
			`device`='".mysqli_real_escape_string($db,$_POST['device'])."',
			`operator`='".mysqli_real_escape_string($db,$_POST['operator'])."',
			`balance`='".mysqli_real_escape_string($db,$_POST['balance'])."',
			`comment`='".mysqli_real_escape_string($db,$_POST['comment'])."',
			`email`='".mysqli_real_escape_string($db,trim($_POST['email']))."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `cards` SET
			`iccid`='".mysqli_real_escape_string($db,trim($_POST['iccid'],'+'))."',
			`number`='".mysqli_real_escape_string($db,trim($_POST['number'],'+'))."',
			`title`='".mysqli_real_escape_string($db,trim($_POST['title']))."',
			`place`='".mysqli_real_escape_string($db,$_POST['place'])."',
			`device`='".mysqli_real_escape_string($db,$_POST['device'])."',
			`operator`='".mysqli_real_escape_string($db,$_POST['operator'])."',
			`balance`='".mysqli_real_escape_string($db,$_POST['balance'])."',
			`comment`='".mysqli_real_escape_string($db,$_POST['comment'])."',
			`email`='".mysqli_real_escape_string($db,trim($_POST['email']))."',
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

	$iccid=$_POST['iccid'];
	$title=$_POST['title'];
	$place=$_POST['place'];
	$operator=$_POST['operator'];
	$balance=$_POST['balance'];
	$comment=$_POST['comment'];
	$email=$_POST['email'];
	$device=$_POST['device'];

	if ($_GET['edit']!='new')
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `cards` WHERE `id`='.(int)$_GET['edit'])) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				if ($row['number']!=$row['place'] && $row['number'])
				{
					$number=$row['number'];
				}
				if ($row['iccid']){$iccid=$row['iccid'];}
				if ($row['title']){$title=$row['title'];}
				if ($row['place']){$place=$row['place'];}
				if ($row['operator']){$operator=$row['operator'];}
				if ($row['device']){$device=$row['device'];}
				if ($row['balance']){$balance=$row['balance'];}
				if ($row['comment']){$comment=$row['comment'];}
				if ($row['email']){$email=$row['email'];}
				$id=$row['id'];
			}
		}
	}

	$operators=array();

	$qry='SELECT * FROM `operators` ORDER BY `name` DESC';
	if ($result = mysqli_query($db, $qry)) 
	{
		$name='';
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($name!=$row['name'])
			{
				$operators[$row['name']]=$row['title'];
			}
			$name=$row['name'];
		}
	}
if (!$status)
{
?>
<div class="tooltip danger">— Не все обязательные поля заполнены!</div>
<br><br>
<?
}
?>
<form method="post">
Номер телефона (обязательное поле)      
<br>
<input type="text" name="number" value="<? if (strlen($number)>7){echo '+';} echo $number ?>" maxlength="15">
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
Оператор
<br>
<select name="operator">
<option value="">— выберите из списка —</option>
<?
	foreach ($operators as $name=>$title)
	{
?>
	<option value="<? if (strpos($name,';'.$operator.';')!==false){echo $operator.'" selected=1';} else {$name=explode(';',$name); echo $name[1].'"';}?>><?=$title?></option>
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
E-mail
<br>
<input type="text" name="email" value="<?=$email?>" maxlength="32">
<div class="help_block">Адрес электронной почты для перенаправления SMS и прочих уведомлений</div>
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
		$where[]="(o1.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o1.`name` LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%' OR o2.title LIKE '%".mysqli_real_escape_string($db,$_GET['operator'])."%')";
	}
	if ($_GET['device'])
	{
		$where[]="c.device=".(int)$_GET['device'];
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
		$order=' ORDER BY left(c.`place`,1),CHAR_LENGTH(c.`place`),c.`place`';
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
	elseif ($_GET['sort']==9)
	{
		$order=' ORDER BY `pools`';
	}

	if (count($where)){$where=' AND '.implode(' AND ',$where);} else {$where='';}

// Получаем список актуальных операторов

	$qry='SELECT count(DISTINCT c.`id`) AS counter FROM `cards` c 
	LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
	WHERE 1'.$where.'';
	if ($result = mysqli_query($db, $qry)) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$total=$row['counter'];	
		}
	}
	$title_td=0;
	$balance_td=0;
	$qry='SELECT c.*,o1.`title` AS `operator_name`,o1.`color` AS `color`,d.title AS device_name,m.modems,d.model,IF (pp.`id` IS NOT NULL,count(DISTINCT p.id),0) AS `pools` FROM `cards` c 
	LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%",c.`operator`,"%") 
	LEFT JOIN `card2pool` p ON p.`card`=c.`number` 
	LEFT JOIN `pools` pp ON pp.`id`=p.`pool`
	LEFT JOIN `devices` d ON c.`device`=d.`id` 
	LEFT JOIN `modems` m ON m.`device`=d.`id` 
	WHERE 1'.$where.' GROUP BY c.`id`'.$order.$limit;
	if ($result = mysqli_query($db, $qry)) 
	{
		$n=1;
		$nn=array();
		$copy=array();
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
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
//			$a=explode('-',$row['place']);
//			$b=ord($row['place'][0])-64;
			$online=0;
			if (strpos($row['model'],'SR-Nano')!==false)
			{
				$c='';
				$eject=1;
				if ($modems[0]==$row['place'])
				{
					$online=1;
				}
			}
			else
			{			
				if ($modems[$b][0]==substr($row['place'],1,255))
				{
					$online=1;
				}
			}
/*
			if (
			$modems[0]==ord($row['place'])-64 || 
			(strlen($row['place'])==1 && isset($modems[ord($row['place'])-64])) || 
			$modems[$a[1]][0]==$a[0] || 
			($c && $modems[$b][0]==$c)
			)
			{$online=1;}
*/
			if (strlen($row['number'])>7){$row['vnumber']='+'.$row['number'];} else {$row['vnumber']=$row['number'];}
			if (!$row['pools']){$row['pools']='—';}
			$table[]=array(
				'num'=>$pnum,
				'id'=>$row['id'],
				'iccid'=>$row['iccid'],
				'number'=>$row['number'],
				'vnumber'=>$row['vnumber'],
				'title'=>$row['title'],
				'comment'=>$row['comment'],
				'time'=>srdate('d.m.Y H:i:s',$row['time']),
				'time_balance'=>$row['time_balance'],
				'time_last_balance'=>$row['time_last_balance'],
				'model'=>$row['model'],
				'eject'=>$eject,
				'dev'=>$row['device'],
				'device'=>$row['device_name'],
				'place'=>$row['place'],
				'pools'=>$row['pools'],
				'operator'=>trim($row['operator'],';'),
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
			if ($row['balance']){$balance_td=1;}
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
		$str="Агрегатор;ID;ICCID;Номер;Место;Баланс;Оператор;Время;Имя;Комментарий
";
		foreach ($table as $data)
		{
			if ($data['number']==$data['place'])
			{
				$str.=$data['model'].";".$data['dev'].";".$data['iccid'].";Блокировка".";P:".$data['place'].";".$data['balance'].";—;—;".$data['time'].'
';
			}
			else
			{
				$str.=$data['model'].";".$data['dev'].";".$data['iccid'].";".$data['vnumber'].";P:".$data['place'].";".$data['balance'].";".strip_tags($data['operator']).";".$data['time'].";".$data['title'].";".$data['comment'].'
';
			}
		}
		if ($GLOBALS['set_data']['cp-1251']==2)
		{
			echo iconv('UTF-8//IGNORE', 'windows-1251//IGNORE', $str);
		}
		else
		{
			echo $str;
		}
		exit();
	}
	if ($_GET['type']=='number_txt')
	{
		header('Content-Type:text/plain');
		$str="";
		foreach ($table as $data)
		{
			if ($a=trim($data['number']))
			{
				$str.=$a.'
';
			}
		}
		echo $str;
		exit();
	}
	if ($_GET['type']=='iccid_txt')
	{
		header('Content-Type:text/plain');
		$str="";
		foreach ($table as $data)
		{
			if (trim($data['number']) && $i=trim($data['iccid']))
			{
				$str.=$i.' '.trim($data['number']).'
';
			}
		}
		echo $str;
		exit();
	}
	sr_header("Список СИМ-карт"); // Output page title and title | Вывод титул и заголовок страницы
	$a=$_GET; unset($a['page']); 
	if (empty($a)){echo '<div id="filter_hint" onclick="fltr();">Отфильтровать</div>';} 
?>
<div id="filter"<? if (empty($a)){echo ' class="hide"';}?>>
<form method="get">
<div class="sidebar">
Номер телефона
</div>
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" placeholder="Часть телефонного номера. Пример: 903">
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
Место
</div>
<input type="text" name="place" value="<?=$_GET['place']?>" maxlength="7" placeholder="Место. Примеры: A0 или A или 2-8 или 2">
<div class="sidebar">
<br>
Баланс
</div>
<input type="text" name="balance" value="<?=$_GET['balance']?>" maxlength="8" placeholder="Баланс. Пример: >100">
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
<option value="0"<? if (!$_GET['sort']){echo ' selected=1';}?>>По местам</option>
<option value="1"<? if ($_GET['sort']==1){echo ' selected=1';}?>>По агрегаторам</option>
<option value="8"<? if ($_GET['sort']==8){echo ' selected=1';}?>>По ICCID</option>
<option value="2"<? if ($_GET['sort']==2){echo ' selected=1';}?>>По номерам телефонов</option>
<option value="3"<? if ($_GET['sort']==3){echo ' selected=1';}?>>По балансам</option>
<option value="6"<? if ($_GET['sort']==6){echo ' selected=1';}?>>По времени получения баланса ↑</option>
<option value="7"<? if ($_GET['sort']==7){echo ' selected=1';}?>>По времени получения баланса ↓</option>
<option value="4"<? if ($_GET['sort']==4){echo ' selected=1';}?>>По операторам</option>
<option value="5"<? if ($_GET['sort']==5){echo ' selected=1';}?>>По времени</option>
<option value="9"<? if ($_GET['sort']==9){echo ' selected=1';}?>>По Пулам</option>
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
</div>

<?
	if (count($table))
	{
?>
<em style="float: right;margin: 10px 10px 0 10px;font-style: italic;">Карт: <? if (count($table)!=$total){echo count($table).'/'.$total;} else {echo $total;}?></em>
<form method="post" action="pools.php?edit=new" id="cards" name="cards">
<div class="table_box">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="SelectGroup(checked,'cards','check')"></th>
				<th class="sidebar">№</th>
				<? if ($title_td){?><th class="sidebar">Имя</th><? } ?>
				<? if ($GLOBALS['set_data']['iccid_show']==2){?><th class="sidebar">ICCID</th><? } ?>
				<th>Номер</th>
				<th class="sidebar">Пулы</th>
				<? if (count($devices)>1){ ?>
				<th class="sidebar">Агрегатор</th>
				<? } ?>
				<th style="width: 100px;text-align: right;">Место</th>
				<? if ($balance_td){?><th style="text-align:right;">Баланс</th><? } ?>
				<th class="sidebar">Оператор</th>
				<th class="sidebar">Время</th>
<?
				if ($_GET['comment']){
?>
				<th>Комментарий</th>
<?
}
?>
				<th class="ic"></th>
			</tr>  
		</thead>
<?
		$n=0;
		foreach ($table as $data)
		{
//			if (!$data['status']){$data['status']='•';}
?>
		<tr<? if ($data['online']){echo ' class="rowsel"';}?>>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['number'].';'.$data['place'].';'.$data['device'].';'.$data['id']?>"></td>
			<td class="sidebar" align="right"><?=$data['num']?></td>
			<? if ($title_td){?><td class="sidebar"><?=$data['title']?></td><? } ?>
			<? if ($GLOBALS['set_data']['iccid_show']==2){?><td class="sidebar"><?=$data['iccid']?></span></td><? } ?>
			<td><? if ($data['title']){?><span class="extinfo"><s><?=$data['title']?></s><br></span><? } ?>
			<?
			if ($data['place']!=$data['number'] && $data['number']){
			?>
			<span class="but_win <?=$copy[$data['number']]?>" data-id="win_action" data-title="Управление номером +<?=$data['number']?>" data-type="ajax_card_action.php?device=<?=$data['dev']?>&id=<?=$data['number']?>" data-height="400" data-width="600"><?=$data['vnumber']?></span>
			<? } elseif ($data['number']) { ?>
			<em>Карта заблокирована</em>
			<? } if (count($devices)>1){echo '<div class="legend exttab">'.$data['device'].'</div>';}?>
			</td>
			<td class="sidebar" align="right"><?=$data['pools']?></td>
			<? if (count($devices)>1){ ?>
			<td class="sidebar" nowrap><?=$data['device']?></td>
			<? } ?>
			<td class="sidebar" align="right"><?=$data['place']?></td>
			<td class="exttab" align="right"<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?>><?=$data['place']?></td>
			<?
			if ($data['place']!=$data['number'])
			{
				if ($balance_td)
				{
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
					elseif ($data['balance']) 
					{
						$balance=balance_out($data['balance'],'');
					}
					else 
					{
						$balance='—';
					}
			?>
					<td align="right"><?=$balance?></td>
					<? 
				}
			} 
			elseif ($balance_td) 
			{ ?>
				<td align="right"><em>—</em></td>
			<?
			}
			?>
			<td<? if ($data['color']){?> style="color: #<?=$data['color']?>; background:#<?=$data['bg']?>"<? } ?> align="center" class="sidebar"><?=$data['operator_name']?></td>
			<td class="sidebar"><?=$data['time']?></td>
<?
			if ($_GET['comment']){
?>
			<td><?=str_replace("\n",'<br>',str_replace($_GET['comment'],'<span class="note" style="padding: 0px;">'.$_GET['comment'].'</span>',trim(preg_replace('/\n(user:(.*)time:(.*))\n/Us', "\n".'<user>${2} • ${3}</user>'."\n",$data['comment']))))?></td> 
<?
}
?>
			<td class="tr"><a href="cards.php?edit=<?=$data['id']?>"><i class="icon-pencil"></i></a> 
			<? if ($data['online']){?>
			<i class="icon-online" style="color: #ff8c00 !important;"></i>
			<? } else { ?>
			<a href="javascript:void();" onclick="onlineCreateOut(<?=$data['dev']?>,<?=$data['number']?>);"><i class="icon-online"></i></a>
			<? } if ($data['eject']){?>
			<a href="javascript:void();" onclick="eject(<?=$data['dev']?>,'card>out:<?=$data['place']?>');"><i class="icon-eject"></i></a>
			<? } ?>
			</td>
		</tr>
<?
		}
?>
	</table>
</div>
<?=$scroller=scrollbar($total,$_GET['page'],$GLOBALS['set_data']['page_limit'],'page');?>
<br>
<input type="hidden" id="sub" name="sub">
<?                                    

	if (strpos($_SERVER['REQUEST_URI'],'?'))
	{
		$csv=$_SERVER['REQUEST_URI'].'&type=csv';
		$txt=$_SERVER['REQUEST_URI'].'&type=number_txt';
		$iccid=$_SERVER['REQUEST_URI'].'&type=iccid_txt';
	}
	else
	{
		$csv=$_SERVER['REQUEST_URI'].'?type=csv';
		$txt=$_SERVER['REQUEST_URI'].'?type=number_txt';
		$iccid=$_SERVER['REQUEST_URI'].'?type=iccid_txt';
	}
       
	$bottom_menu[]='<span onclick="menuOpen();">Операции с СИМ-картами</span>';
	$bottom_menu[]='<a href="cards.php?edit=new">Добавить СИМ-карту</a>';
	$bottom_menu[]='<a href="#" onclick="document.getElementById(\'sub\').value=\'del\';document.getElementById(\'cards\').submit(); return false;">Удалить отмеченные СИМ-карты</a>';
	$bottom_menu[]='<a href="#" onclick="document.getElementById(\'cards\').submit(); return false;">Создать Пул из отмеченных карт</a>';
	$bottom_menu[]='<a href="javascript:void(0);" class="but_win" data-id="win_action" data-title="Сканирование СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканировать СИМ-карты</a>';
	$bottom_menu[]='<a href="javascript:void(0);" onclick="FindFile();">Импортировать из Excel (CSV) или с SR-Nano</a>';
	$bottom_menu[]='<a href="'.$csv.'" download="srn_cards.csv">Экспортировать в Excel (CSV)</a>';
	$bottom_menu[]='<a href="'.$txt.'" download="srn_numbers.txt">Экспортировать список номеров (TXT)</a>';
	$bottom_menu[]='<a href="'.$iccid.'" download="srn_iccid2number.txt">Экспортировать списки ICCID и номеров (TXT)</a>';
?>
</form>
<form action="ajax_load_csv.php" target="rFrame" method="POST" enctype="multipart/form-data">  
<div class="hiddenInput">
 <input type="file" id="my_hidden_file" name="loadfile" onchange="LoadFile();">  
 <input type="submit" id="my_hidden_load" style="display: none;" value="Загрузить">  
</div></form>

<?
	}
	else
	{
?>
<br><div class="tooltip">— Список СИМ-карт пуст.<br>— Карты можно добавить вручную, получить номера автоматически или импортировать из Excel-файла!</div>
<?
	$bottom_menu[]='<span onclick="menuOpen();">Операции с СИМ-картами</span>';
	$bottom_menu[]='<a href="cards.php?edit=new">Добавить СИМ-карту</a>';
	$bottom_menu[]='<a href="javascript:void(0);" class="but_win" data-id="win_action" data-title="Сканирование СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканировать СИМ-карты</a>';
	$bottom_menu[]='<a href="javascript:void(0);" onclick="FindFile();">Импорт из Excel (CSV) или с агрегатора</a>';
/*
<br><br>
<a href="cards.php?edit=new" class="link" style="margin: margin: 0 10px 10px 0">Добавить СИМ-карту</a>
<span class="link but_win" data-id="win_action" data-title="Сканирование СИМ-карт" data-type="ajax_card_scanner.php" data-height="400" data-width="600">Сканирование СИМ-карт</span>

<div class="link" onclick="FindFile();">Импорт из CSV или с агрегатора</div>
*/
?>
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
