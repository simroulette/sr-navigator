<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;
if ($_POST['del']) // Deleting a SIM card Pool | Удаление Пула СИМ-карт
{
	foreach ($_POST['check'] as $data)
	{
		$a=explode(';',$data);
		$qry="DELETE FROM `cards` WHERE `number`='".$a[0]."'";
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
			$qry="INSERT `pools` SET
			`title`='".trim($_POST['title'])."',
			`key`='".md5(rand(0,10000000))."',
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
		if ($result = mysqli_query($db, 'SELECT p.*,c.`place`,d.`title` FROM `card2pool` p 
		INNER JOIN `cards` c ON c.`number`=p.`card` 
		INNER JOIN `devices` d ON d.`id`=c.`device` 
		WHERE p.`card`<>"" AND p.`pool`='.(int)$_GET['edit'].' ORDER BY p.`card`')) 
		{
			while ($row = mysqli_fetch_assoc($result))
			{
				$_POST['check'][]=$row['card'].';'.$row['place'].';'.$row['title'];
			}
		}
	}
	sr_header("Редактирование пула СИМ-карт"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<?
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

	<table class="table table_sort table_adaptive">
		<thead>
			<tr>
				<th>№</th>
				<th>Номер</th>
				<th>Агрегатор</th>
				<th>Место</th>
				<th>Действие</th>
			</tr>  
		</thead>
<?
	$n=0;
	foreach ($_POST['check'] as $data)
	{
		$data=explode(";",$data);
?>
		<tr>
			<td><?=($n+1)?><input type="hidden" name="check[<?=$n++?>]" value="<?=$data[0]?>"></td>
			<td>+<?=$data[0]?></td>
			<td><?=$data[2]?></td>
			<td><?=$data[1]?></td>
			<td align="center"><span onclick="deleteItem(this);"><i class="icon-trash" title="Удалить номер из пула"></i></span></td>
		</tr>

	<div>
<?
	}	
?>
	</table>
<br>
<input type="submit" name="save" value="Сохранить" style="padding: 10px;">
</form>
<?
	}
}
else // List of Pools | Список Пулов
{
	sr_header("Пулы СИМ-карт"); // Выводим титул и заголовок страницы

	$table=array();

	if ($result = mysqli_query($db, 'SELECT 
	p.*,
	(SELECT count(pool) FROM `card2pool` WHERE `pool`=p.id) AS `count`,
	(SELECT count(c.id) FROM `card2pool` cp INNER JOIN `cards` c ON c.`number`=cp.`card` WHERE cp.`pool`=p.id) AS `realcount`,
	(SELECT sum(c.balance) 
	FROM `card2pool` cp 
	INNER JOIN `cards` c ON c.`number`=cp.`card` WHERE cp.`pool`=p.id) AS `balance` FROM `pools` p  
	ORDER BY `title`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if (hexdec($row['color'])>8388607){$color='000';} else {$color='FFF';}
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

	if (count($table))
	{
?>
<br>
<form method="post" id="cards" name="cards">
	<table class="table">
		<thead>
		<tr>
			<th><input type="checkbox" onclick="SelectGroup(checked,'cards','check')"></th>
			<th>Пул</th>
			<th style="text-align:right;">Номера</th>
			<th class="sidebar">Баланс</th>
			<th class="sidebar">Модификация</th>
			<th class="sidebar">Key</th>
			<th>Действие</th>
			<th>Статус</th>
		</tr>  
		</thead>
<?
	$n=0;
	foreach ($table as $data)
{
?>
		<tr>
			<td><input type="checkbox" name="check[<?=$n++?>]" id="check" value="<?=$data['id']?>"></td>
			<td><span class="but_win" data-id="win_action" data-title='Управление пулом "<?=$data['title']?>"' data-type="ajax_pool_action.php?id=<?=$data['id']?>" data-height="400" data-width="600"><?=$data['title']?></span></td>
			<td align="right"><?=num_out($data['realcount']).'/'.num_out($data['count'])?></td>
			<td class="sidebar" align="right"><?=balance_out($data['balance'])?></td>
			<td class="sidebar"><?=$data['time']?></td>
			<td class="sidebar"><span class="legend note" onclick="copy('<?=$data['key']?>');soundClick();"><?=$data['key']?></span></td>
			<td><a href="pools.php?edit=<?=$data['id']?>"><i class="icon-pencil" title="Редактировать пул"></i></a> <a href="pools.php?delete=<?=$data['id']?>"><i class="icon-trash" title="Удалить пул, но оставить телефонные номера"></i></a></td>
			<td><em><?=$data['status']?></em></td>
		</tr>
<?
}
?>
	</table>

<br>
<input type="submit" name="merge" value="Объединить пулы" style="float:left; margin-right: 10px">
</form>
<?
	}
	else
	{
?>
<br>
<em>— Пулов СИМ-карт нет!</em>
<?
	}
}

sr_footer();
?>