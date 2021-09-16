<?
// ===================================================================
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$status=1;

if ($_GET['noadmin']){$_COOKIE['triforce_direct']=0;$noadmin='?noadmin=1';}

if ($_POST['submit']) // Save the settings | Сохранение настроек
{
	unset($_POST['submit']);
	$qry="DELETE FROM `values`";
	mysqli_query($db,$qry);
	foreach ($_POST as $key => $value)
	{
		$qry="INSERT `values` SET `name`='".$key."',`value`='".$value."'";
		mysqli_query($db,$qry);
	}
	header('location: setup.php'.$noadmin);
	exit();
}
sr_header("Настройки"); // Output page title and title | Вывод титул и заголовок страницы
?>
<br>
<form method="post">
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
			echo auto_field($b[1],$name,$b[2],$b[0],$data,$b[3]);
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