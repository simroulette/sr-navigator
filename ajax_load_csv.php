<? 
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
function jsOnResponse($obj)  
{  
	echo '<script type="text/javascript"> window.parent.onResponse("'.$obj.'"); </script> ';  
}  
$file = 'tmp_csv';  
if (move_uploaded_file($_FILES['loadfile']['tmp_name'], $file))
{
	$cards=array();
	$dev=array();
	$operators=array();
	$a=trim(file_get_contents($file));

	if (mb_detect_encoding($a, 'UTF-8, Windows-1251')=='Windows-1251')
	{
		$a=iconv('windows-1251//IGNORE', 'UTF-8//IGNORE', $a);
	}
	
	$a=explode("\n",$a);

// Импорт с SR-Nano
	if (strpos($a[0],'Track:')!==false)
	{
		$count=0;
		if ($result = mysqli_query($db, 'SELECT id FROM `folders` WHERE `title`="Импорт с агрегатора"')) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$folder=$row['id'];
			}
		}
		if (!$folder)
		{
			$qry="INSERT `folders` SET
			`title`='Импорт с агрегатора',
			`comment`='Импорт с SR-Nano',
			`time`='".time()."'";
			mysqli_query($db,$qry);
			$folder=mysqli_insert_id($db);
		}

		$track=trim(str_replace('Track:','',$a[0]));
		for ($i=1;$i<count($a);$i++)
		{
			if ($a[$i]=trim($a[$i]))
			{
				$b=explode(';',$a[$i]);
				$number=explode('+',$b[0]);
				if ($number[1])
				{
					$count++;
					$b=explode(';',$a);

					$qry="DELETE FROM `cards2folder` WHERE (`number`='".$number[1]."' OR `place`='".$track.($i-1)."')";
					mysqli_query($db,$qry);

					$qry="INSERT INTO `cards2folder` SET
					`number`='".$number[1]."',
					`place`='".$track.($i-1)."',
					`operator`='".$b[1]."',
					`balance`='".$b[2]."',
					`folder_id`=".$folder.",
					`comment`='".$b[3]."'";
					mysqli_query($db,$qry);
				}
			}
		}

		$message='<h2>Импорт успешно завершен!</h2><br>Обработано карт: '.$count.'<br>';
		if ($count)
		{
			$message.='<br>Импортированные карты помещены на диск <b><a href=\"folders.php\">Импорт из агрегатора</a></b>.<br>';
		}
	}
// Импорт CSV
	else
	{
		for ($i=1;$i<count($a);$i++)
		{
			if (trim($a[$i]))
			{
				$b=explode("\t",$a[$i]);
				if (!$b[1]){$b=explode(";",$a[$i]);}	
				else if (!$b[1]){$b=explode(",",$a[$i]);}	
				$cards[$i-1]['number']=str_replace('+','',trim($b[0]));	
				$cards[$i-1]['place']=str_replace('P:','',trim($b[3]));	
				$model='SR-Train';
				$c=trim($cards[$i-1]['place']);	
				if (ord($c[0])>=65){$model='';}
				$cards[$i-1]['balance']=trim($b[4]);	
				$t=explode(' ',trim($b[7]));	
				$t1=explode('.',$t[0]);	
				$t2=explode(':',$t[1]);	
				$cards[$i-1]['time']=mktime($t2[0],$t2[1],$t2[2],$t1[1],$t1[0],$t1[2]);	
				$cards[$i-1]['title']=trim($b[8]);	
				$cards[$i-1]['comment']=trim($b[9]);	

				// Ищем устройство
				if (!$dev[trim($b[2])])
				{
					if ($result = mysqli_query($db, 'SELECT id FROM `devices` WHERE `id`='.(int)trim($b[2]))) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$dev[trim($b[2])]=$row['id'];
						}
					}
				}
				if (!$dev[trim($b[2])])
				{
					if ($result = mysqli_query($db, 'SELECT id FROM `devices` WHERE `title`="'.trim($b[1]).'" ORDER BY `title` DESC')) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$dev[trim($b[2])]=$row['id'];
						}
					}
				}
				if (!$dev[trim($b[2])] && $model) // Добавляем новое устройство
				{
					$qry="INSERT `devices` SET
					`title`='".trim($b[1])."',
					`model`='".$model."',
					`type`='client',
					`token_local`='',
					`token_remote`='".rand(11111,99999).rand(11111,99999)."',
					`modems`='1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16',
					`data`='a:4:{s:9:\"row_begin\";s:1:\"0\";s:4:\"rows\";s:2:\"20\";s:10:\"time_limit\";s:3:\"180\";s:13:\"carrier_limit\";s:2:\"60\";}',
					`time`='".time()."'";
					mysqli_query($db,$qry);
					$dev[trim($b[2])]=mysqli_insert_id($db);
					$newdev=trim($b[1]);
				}
				else if (!$dev[trim($b[2])] && !$model) // Добавляем новое устройство
				{
					$qry="INSERT `folders` SET
					`title`='".trim($b[1])."',
					`comment`='Импорт из CSV',
					`time`='".time()."'";
					mysqli_query($db,$qry);
					$dev[trim($b[2])]='F'.mysqli_insert_id($db);
				}
				if ($dev[trim($b[2])])
				{
					$cards[$i-1]['dev']=$dev[trim($b[2])];
				}
				// Ищем оператора
				if (!$operators[trim($b[6])])
				{
					if ($result = mysqli_query($db, 'SELECT name FROM `operators` WHERE `name` LIKE "%'.trim($b[6]).'%" ORDER BY `name`="'.trim($b[6]).'" LIMIT 1')) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$operators[trim($b[6])]=$row['name'];
						}
					}
				}
				if (!$operators[trim($b[6])])
				{
					if ($result = mysqli_query($db, 'SELECT id FROM `operators` WHERE `title` LIKE "'.trim($b[5]).'" OR `name` LIKE "'.trim($b[5]).'"')) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$operators[trim($b[6])]=$row['name'];
						}
					}
				}
				if ($operators[trim($b[6])])
				{
					$cards[$i-1]['operator']=$operators[trim($b[6])];
				}
			}

		}
		$noop=0;
		$message='<h2>Импорт успешно завершен!</h2><br>Обработано карт: '.count($cards).'<br>';
		for ($i=0;$i<count($cards);$i++)
		{
			if ((int)$cards[$i]['operator']){$noop=1;}
			if ($cards[$i]['dev'][0]=='F')
			{
				$qry="INSERT INTO `cards2folder` SET
				`number`='".$cards[$i]['number']."',
				`place`='".$cards[$i]['place']."',
				`balance`='".str_replace(',','.',$cards[$i]['balance'])."',
				`time`=".$cards[$i]['time'].",
				`operator`='".$cards[$i]['operator']."',
				`folder_id`=".(int)substr($cards[$i]['dev'],1,255).",
				`title`='".$cards[$i]['title']."',
				`comment`='".$cards[$i]['comment']."';";
				$status1=1;
			}
			else
			{
				$qry="REPLACE INTO `cards` SET
				`number`='".$cards[$i]['number']."',
				`place`='".$cards[$i]['place']."',
				`balance`='".str_replace(',','.',$cards[$i]['balance'])."',
				`time`=".$cards[$i]['time'].",
				`operator`='".$cards[$i]['operator']."',
				`device`=".(int)$cards[$i]['dev'].",
				`title`='".$cards[$i]['title']."',
				`comment`='".$cards[$i]['comment']."'";
				if ($newdev)
				{
					$status2=1;
				}
			}
			mysqli_query($db,$qry);
		}
		if ($status1)
		{
			$message.='<br>Импортированные карты помещены на новый диск <b><a href=\"folders.php\">Импорт из CSV</a></b>.<br>';
		}
		if ($status2)
		{
			$message.='<br>Импортированные карты привязаны к новому агрегатору <b><a href=\"setup_devices.php\">'.$newdev.'</a></b>.';
		}
	}
	jsOnResponse("{'message':'".$message."'}");  
	unlink($file);  
}
?> 