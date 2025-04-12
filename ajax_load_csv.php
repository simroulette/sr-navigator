<? 
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
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
					$qry="DELETE FROM `cards2folder` WHERE (`number`='".(int)$number[1]."' OR `place`='".mysqli_real_escape_string($db,$track.($i-1))."')";
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

		$message.='<h2>Импорт успешно завершен!</h2><br>Обработано карт: '.$count.'<br>';
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

				if (strpos($a[$i],"\t")!==false)
				{
					$b=explode("\t",$a[$i]);
				}
				elseif (strpos($a[$i],';')!==false)
				{
					$b=explode(";",$a[$i]);
				}
				elseif (strpos($a[$i],',')!==false)
				{
					$b=explode(",",$a[$i]);
				}
				else
				{
					jsOnResponse("{'message':'<h2>Ошибка импорта!</h2><br>Неверный формат файла.<br>В качестве разделителей допустимы только символы: <b>[TAB] ; ,</b>'}");  
					unlink($file);  
					exit();
				}	
				$dev=(int)trim($b[1]);
				$model=$b[0];
				$cards[$i-1]['iccid']=trim($b[2]);	
				$cards[$i-1]['number']=str_replace('+','',trim($b[3]));	
				$cards[$i-1]['place']=str_replace('P:','',trim($b[4]));	
				$cards[$i-1]['balance']=trim($b[5]);	
				$cards[$i-1]['operator']=trim($b[6]);	
				$t=explode(' ',trim($b[7]));	
				$t1=explode('.',$t[0]);	
				$t2=explode(':',$t[1]);	
				$cards[$i-1]['time']=mktime($t2[0],$t2[1],$t2[2],$t1[1],$t1[0],$t1[2]);	
				$cards[$i-1]['title']=trim($b[8]);	
				$cards[$i-1]['comment']=trim($b[9]);	

				// Ищем устройство по ID
				if ($dev)
				{
					if ($result = mysqli_query($db, 'SELECT id FROM `devices` WHERE `id`='.$dev)) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$cards[$i-1]['id']=$row['id'];
							$msg='СИМ-карты привязаны к указанному в списке агрегатору.';
						}
					}
				}
				if (!$cards[$i-1]['id'])
				{

					$qry='SELECT id,`title` FROM `devices` WHERE `model`="'.mysqli_real_escape_string($db,$model).'" ORDER BY `title` DESC';
					if ($result = mysqli_query($db, $qry)) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$cards[$i-1]['id']=$row['id'];
							$msg='СИМ-карты привязаны к агрегатору <b>'.$row['title'].'</b> (ID #'.$id.').';
						}
					}
				}
			}
		}
/*
    [0] => SR-Nano-500
    [1] => 4436
    [2] => 8970199160502281818f
    [3] => +79656149321
    [4] => P:A0
    [5] => 96.46
    [6] => BEELINE
    [7] => 
    [8] => 22.05.2022 23:01:51
    [9] => 
    [10] => 
*/
		if (!$message)
		{
			$imp=0;
			for ($i=0;$i<count($cards);$i++)
			{
				if ($cards[$i]['id'])
				{
					$imp++;
					$qry="REPLACE INTO `cards` SET
					`iccid`='".$cards[$i]['iccid']."',
					`number`='".$cards[$i]['number']."',
					`place`='".$cards[$i]['place']."',
					`balance`='".str_replace(',','.',$cards[$i]['balance'])."',
					`time`='".$cards[$i]['time']."',
					`operator`='".$cards[$i]['operator']."',
					`device`=".$cards[$i]['id'].",
					`title`='".$cards[$i]['title']."',
					`comment`='".$cards[$i]['comment']."'";
					mysqli_query($db,$qry);
				}
			}
			if ($imp==count($cards))
			{
				$message='<h2>Импорт успешно завершен!</h2><br>Обработано карт: <b>'.count($cards).'</b><br>'.$msg;
			}
			elseif ($imp)
			{
				$message='<h2>Импортированы не все карты!</h2><br>Импортировано <b>'.count($cards).'</b> из <b>'.$imp.'</b><br>'.$msg;
			}
			else
			{
				$message='<h2>Ошибка импорта!</h2><br>Не найдено подходящего агрегатора...';
			}
		}
	}
//echo $message;
	jsOnResponse("{'message':'".$message."'}");  
	unlink($file);  
}
?> 
