<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$status=1;

if ($_POST['restart'])
{
	foreach ($_POST['check'] as $data)
	{
// Выключаем устройства пула
		$qry="SELECT c.`device` FROM `card2pool` p 
		INNER JOIN `cards` c ON c.`number`=p.`card`
		WHERE p.`pool`=".(int)$data; 
		if ($result = mysqli_query($db, $qry)) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".$row['device']);
				if (flagGet($row['device'],'cron'))
				{
					if (!flagGet($row['device'],'stop'))
					{
						flagSet($row['device'],'stop');
					}
					flagDelete($row['device'],'cron');
				}
				elseif (flagGet($row['device'],'stop',1)<time()-60)
				{
					flagDelete($row['device'],'stop');
				}
			}
			$qry="UPDATE `card2pool` SET `done`=0 WHERE `pool`='".(int)$data."'";
			mysqli_query($db, $qry);
		}
	}
	header('location:pools.php');
	exit();
}
if ($_POST['sub']=='del') // Deleting a SIM card Pool | Удаление Пула СИМ-карт
{
	foreach ($_POST['check'] as $data)
	{
		$a=explode(';',$data);
		for ($i=0;$i<count($a);$i++){$a[$i]=(int)$a[$i];}
		$qry="DELETE FROM `cards` WHERE `id`='".$a[3]."'";
		mysqli_query($db,$qry);
	}
	header('location:cards.php');
	exit();
}
if ($_GET['delete']) // Deleting a SIM card Pool | Удаление Пула СИМ-карт
{
	if ($result = mysqli_query($db, 'SELECT * FROM `pools` WHERE `id`='.(int)$_GET['delete'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$qry="DELETE FROM `card2pool` WHERE `pool`=".(int)$_GET['delete'];
			mysqli_query($db,$qry);

			$qry="DELETE FROM `pools` WHERE `id`=".(int)$_GET['delete'];
			mysqli_query($db,$qry);
		}
	}
}
if ($_POST['merge']) // Combining SIM card Pools | Объединение Пулов СИМ-карт
{
	$f=0;
	foreach ($_POST['check'] as $data)
	{
		if ($result = mysqli_query($db, 'SELECT * FROM `pools` WHERE `id`='.(int)$data)) 
		{
			if (!mysqli_fetch_assoc($result))
			{
				header('location:pools.php');
				exit();
			}
		}
		if ($f)
		{
			if ($result = mysqli_query($db, 'SELECT * FROM `card2pool` WHERE `pool`='.(int)$data.' ORDER BY `card`')) 
			{
				while ($row = mysqli_fetch_assoc($result))
				{
					// Transferring numbers to the first pool | Перенос номеров в первый пул
					$qry="REPLACE INTO `card2pool` SET `pool`=".(int)$f.", `card`='".$row['card']."'";
		                        mysqli_query($db,$qry);
				}
			}
			// Deleting SIM-cards from the pool | Удаление SIM-карт из пула
			$qry="DELETE FROM `card2pool` WHERE `pool`=".(int)$data;
                        mysqli_query($db,$qry);

			// Deleting a processed pool | Удаление обработанного пула
			$qry="DELETE FROM `pools` WHERE `id`=".(int)$data;
                        mysqli_query($db,$qry);
		}
		else
		{
			$f=$data;
		}
	}
	header('location:pools.php');
	exit();
}

if ($_GET['edit']) // Editing the Pool | Редактирование Пула
{
	if ($_POST['save'] && $_POST['title'])
	{
		if ($_GET['edit']=='new')
		{
			$poolKey=md5(rand(1111,9999).rand(1111,9999).rand(1111,9999).rand(1111,9999));

			$qry="INSERT `pools` SET
			`title`='".trim($_POST['title'])."',
			`key`='".$poolKey."',
			`time`='".time()."'";
		}
		else
		{
			$qry="UPDATE `pools` SET
			`title`='".trim($_POST['title'])."',
			`time`='".time()."'
			WHERE `id`=".(int)$_GET['edit'];
		}
		if ($status=mysqli_query($db,$qry))
		{			
			if (!(int)$_GET['edit'])
			{
				$_GET['edit']=mysqli_insert_id($db); 
			} 
			else
			{			
				$qry="DELETE FROM `card2pool` 
				WHERE `pool`=".(int)$_GET['edit'];
                	        mysqli_query($db,$qry);
			}
			foreach ($_POST['check'] as $data)
			{
				$qry="INSERT `card2pool` SET
				`pool`=".(int)$_GET['edit'].",
				`card`=".(int)$data;
                	        mysqli_query($db,$qry);
                	}
			header('location:pools.php');
			exit();
		}
	}
	elseif ($_POST['save'])
	{
		$status=0;
	}
	if ((int)$_GET['edit'])
	{
		if ($_GET['edit']!='new')
		{
			if ($result = mysqli_query($db, 'SELECT * FROM `pools` WHERE `id`='.(int)$_GET['edit'])) 
			{
				if ($row = mysqli_fetch_assoc($result))
				{
					$_POST['title']=$row['title'];
				}
			}
		}
		$_POST['check']=array();
		$balance_td=0;
		$qry='SELECT p.*,c.`place`,c.`balance`,d.`title`, o1.`title` AS `operator`, o1.`color` AS `color` FROM `card2pool` p 
		INNER JOIN `cards` c ON c.`number`=p.`card`
		INNER JOIN `devices` d ON d.`id`=c.`device` 
		LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
		WHERE p.`card`<>"" AND p.`pool`='.(int)$_GET['edit'].' ORDER BY CHAR_LENGTH(c.`place`),c.`place`';
		if ($result = mysqli_query($db, $qry)) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
				$_POST['check'][]=$row['card'].';'.$row['place'].';'.$row['title'].';'.$row['done'].';'.$row['operator'].';'.$row['color'].';'.$color.';'.$row['balance'];
				if ($row['balance']){$balance_td=1;}
			}
		}
	}
	sr_header("Редактирование пула СИМ-карт"); // Output page title and title | Вывод титул и заголовок страницы
	if (!count($_POST['check']))
	{
?>
<div class="status_error">Телефонные номера не выбраны!</div>
<?
	}
	else 
	{
		if (!$status)
		{
?>
<div class="status_error">Вы не ввели название пула либо пул с таким названием уже существует!</div>
<?
		}
?>
<form method="post">

Название пула
<br>
<input type="text" name="title" value="<?=$_POST['title']?>" maxlength="32">
<br><br>
<div class="table_box">
	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th>№</th>
				<th>Номер</th>
<?
if (count($devices)>1 && $_GET['edit']!='new')
{
?>
				<th>Агрегатор</th>
<?
}
if ($_GET['edit']!='new'){
?>
				<th class="sidebar" style="text-align: right;">Место</th>
				<th class="exttab"></th>
				<th class="sidebar">Оператор</th>
				<? if ($balance_td){?><th style="text-align:right;" class="sidebar">Баланс</th><? } ?>
<?
}
?>
				<th></th>
			</tr>  
		</thead>
<?
	$n=0;
	foreach ($_POST['check'] as $data)
	{
		$data=explode(";",$data);
?>
		<tr<? if ($data[3]){echo ' class="rowsel"';}?>>
			<td><?=($n+1)?><input type="hidden" name="check[<?=$n++?>]" value="<?=$data[0]?>"></td>
			<td>+<?=$data[0]?></td>
<?
if (count($devices)>1 && $_GET['edit']!='new')
{
?>
			<td><?=$data[2]?></td>
<?
}
if ($_GET['edit']!='new'){
?>
			<td class="sidebar" align="right"><?=$data[1]?></td>
			<td class="exttab" align="right"<? if ($data[5]){?> style="color: #<?=$data[6]?>; background:#<?=$data[5]?>"<? } ?>><?=$data[1]?></td>

			<td<? if ($data[5]){?> style="color: #<?=$data[6]?>; background:#<?=$data[5]?>"<? } ?> align="center" class="sidebar"><?=$data[4]?></td>
<?
			if ($balance_td)
			{
			?>
			<td align="right" class="sidebar"><?=balance_out($data[7],'')?></td>
			<? 
			}
}
?>
			<td align="center"><span onclick="deleteItem(this);"><i class="icon-trash" title="Удалить номер из пула"></i></span></td>
		</tr>

	<div>
<?
	}	
?>
	</table>
</div>
<br>
<input type="submit" name="save" value="Сохранить" class="green width">
</form>
<?
	}
}
else // List of Pools | Список Пулов
{
	sr_header("Пулы СИМ-карт"); // Выводим титул и заголовок страницы

	$table=array();
	
	if ($_GET['number']){$w='INNER JOIN `card2pool` cp2 ON cp2.`pool`=p.id AND cp2.`card` LIKE "%'.(int)$_GET['number'].'%"';} else {$w='';}

	$qry='SELECT 
	p.*,
	(SELECT count(pool) FROM `card2pool` WHERE `pool`=p.id) AS `count`,
	(SELECT count(c.id) FROM `card2pool` cp INNER JOIN `cards` c ON c.`number`=cp.`card` WHERE cp.`pool`=p.id) AS `realcount`,
	(SELECT sum(c.balance) 
	FROM `card2pool` cp 
	INNER JOIN `cards` c ON c.`number`=cp.`card` WHERE cp.`pool`=p.id) AS `balance` FROM `pools` p  
	'.$w.'
	GROUP BY `id`
	ORDER BY `title`';
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607 || !$row['color']){$color='000';} else {$color='FFF';}
			if ($row['balance']==''){$row['balance']=0;}
			$table[]=array(
				'id'=>$row['id'],
				'title'=>$row['title'],
				'key'=>$row['key'],
				'count'=>$row['count'],
				'realcount'=>$row['realcount'],
				'balance'=>$row['balance'],
				'status'=>$row['status'],
				'time'=>srdate('H:i d.m.Y',$row['time']),
			);
		}
	}
?>
<form method="get">
<input type="text" name="number" value="<?=$_GET['number']?>" maxlength="15" style="width: 200px;" placeholder="Номер телефона">
<input type="submit" name="save" value="Искать" style="padding: 10px; margin: 5px 0 10px 5px">
</form>

<?
	if (count($table))
	{
		if ($_GET['number'] && count($table)>1)
		{
			?><div class="tooltip">— Номер найден в нескольких Пулах:</div><br><br><?
		}
		else if ($_GET['number'])
		{
			?><div class="tooltip">— Номер найден в одном Пуле</div><br><br><?
		}
?>
<form method="post" id="cards" name="cards">
<div class="table_box">
	<table class="table">
		<thead>
		<tr>
			<th><input type="checkbox" onclick="SelectGroup(checked,'cards','check')"></th>
			<th>Пул</th>
			<th style="text-align:right;">Номера</th>
			<th class="sidebar" style="text-align:right">Баланс</th>
			<th class="sidebar">Модификация</th>
			<th class="sidebar">pool_key</th>
			<th style="text-align: center;">Действие</th>
		</tr>  
		</thead>
<?
	$n=0;
	foreach ($table as $data)
{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['id']?>"></td>
			<td nowrap><span class="but_win" data-id="win_action" data-title='Управление пулом "<?=$data['title']?>"' data-type="ajax_pool_action.php?id=<?=$data['id']?>" data-height="400" data-width="600"><?=$data['title']?></span></td>
			<td align="right"><?=num_out($data['realcount']).'/'.num_out($data['count'])?></td>
			<td class="sidebar" align="right"><?=balance_out($data['balance'])?></td>
			<td class="sidebar"><?=$data['time']?></td>
			<td class="sidebar"><span class="legend note" onclick="copy('<?=$data['key']?>');soundClick();"><?=$data['key']?></span></td>
			<td align="center"><a href="pools.php?edit=<?=$data['id']?>"><i class="icon-pencil"></i></a> <a href="pools.php?delete=<?=$data['id']?>"><i class="icon-trash" title="Удалить пул, но оставить телефонные номера"></i></a></td>
		</tr>
<?
}
?>
	</table>
</div>
<br>
<input type="submit" name="merge" value="Объединить Пулы" class="width">
<input type="submit" name="restart" value="Перезапустить Пулы для API" class="width">
</form>
<?
	}
	elseif ($_GET['number'])
	{
?>
<div class="tooltip danger">— Номер <b><?=$_GET['number']?></b> в Пулах не найден!</div>
<?
	}
	else
	{
?>
<div class="tooltip">— Для создания Пула нужно отметить <a href="cards.php">СИМ-карты в списке</a>!</div>
<?
	}
}

sr_footer();
?>
