<?
include("_func.php");
$status=1;

if ($_POST['submit']) // Save the settings | Сохранение настроек
{
	unset($_POST['submit']);
	$qry="TRUNCATE TABLE `values`";
	mysqli_query($db,$qry);
	foreach ($_POST as $key => $value)
	{
		$qry="INSERT `values` SET `name`='".$key."',`value`='".$value."'";
		mysqli_query($db,$qry);
	}
	header('location: setup.php');
	exit();
}
sr_header("Настройки"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<form id="form" method="post" target="_parent" enctype="multipart/form-data">
<? 
$a=explode("\n",$GLOBALS['sets']);

for ($i=0; $i<count($a);$i++)
{
	if ($b=trim($a[$i]))
	{
		$b=explode(';',$b);
		$c=explode(':',$b[0]);
		if ($c[1]<>'')
		{
			$name=$c[0].'['.$c[1].']';
			$data=$GLOBALS['set_data'][$c[0]][$c[1]];
		}
		else
		{
			$name=$b[0];
			$data=$GLOBALS['set_data'][$b[0]];
		}
		if ($b[0]=='---')
		{
			if ($b[1])
			{
?>
			<h2><?=$b[1]?></h2>
<?
			} 
			else
			{
				echo '<hr>';
			}
		}
		else
		{
			echo '<div style="margin-bottom: 7px;"><span title="'.$name.'">'.$b[1].'</span>';

			if ((int)$b[2])
			{
?>
				<input type="text" id="<?=$name?>" name="<?=$name?>" maxlength="<?=$b[2]?>" value='<?=$data?>' class="form-control input-xlarge"/>
<?
			}
			elseif ($b[2]=='txt')
			{
?>
				<textarea name="<?=$name?>" id="<?=$name?>" class="form-control input-xlarge" maxlength="100000" rows="10"><?=$data?></textarea>
<?
			}
			elseif ($b[2]=='url')
			{
?>
				<input type="text" id="<?=$name?>" name="<?=$name?>" maxlength="100" value='<?=$data?>' class="form-control input-xlarge"/>
<?
			}
			elseif ($b[2]=='email')
			{
?>
				<input type="email" id="<?=$name?>" name="<?=$name?>" maxlength="100" value='<?=$data?>' class="form-control input-xlarge"/>
<?
			}
			elseif ($b[2]=='digit')
			{
?>
				<input type="number" id="<?=$name?>" name="<?=$name?>" maxlength="100" value='<?=$data?>' class="form-control input-xlarge"/>
<?
			}
			elseif ($b[2]=='number')
			{
?>
				<input type="number" id="<?=$name?>" name="<?=$name?>" maxlength="100" value='<?=$data?>' class="form-control input-xlarge"/>
<?
			}
			elseif ($b[2]=='radio')
			{
				if ($data){$c=' checked';$d='';} else {$d=' checked';$c='';}
				echo 'On <input type="radio" name="'.$name.'" id="'.$name.'" value=\'1\''.$c.'>&nbsp;&nbsp;&nbsp;';
				echo 'Off <input type="radio" name="'.$name.'" id="'.$name.'" value=\'0\''.$d.'>';
			}
			elseif ($b[2]=='check')
			{
?>
				<input type="checkbox" id="<?=$name?>" name="<?=$name?>" class="make-switch" value="1" data-on-color="success" data-off-color="danger" <? if ($data){echo 'checked';} ?>>
<?
			}
?>
			</div>
<?
			if ($b[3])
			{
				echo '<div class="help_block">'.$b[3].'</div>';
			}
		}
	}
}


?>
			<br>
			<input type="submit" name="submit" value="Сохранить" style="padding: 10px;">
			</form>
<?

sr_footer();
?>