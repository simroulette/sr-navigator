<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");

$qry='SELECT a.* FROM `reports` a 
INNER JOIN `devices` d ON a.`device`=d.`id` 
WHERE a.`id`='.(int)$_GET['id'];
if ($result = mysqli_query($db, $qry))
{
	if ($row = mysqli_fetch_assoc($result))
	{
		echo '<h1>Отчет об ошибках</h1>';
		echo '<em><br>Всего: <b>'.$row['count'].'</b>';
		echo '<br>Успешно: <b>'.$row['success'].'</b>';
		echo '<br>Ошибки: <b>'.$row['errors'].'</b></em><br><br>';

		echo ' <table class="table table_sort" style="width: 100%;">
		<thead>
			<tr>
				<th style="text-align: right;">Место</th>
				<th style="text-align: right;">Код&nbsp;ошибки</th>
				<th>Рекомендация</th>
			</tr>  
		</thead>';

		$a=explode(' ',trim($row['report']));
		foreach ($a AS $data)
		{
			$b=explode(':',$data);
			if ($b[1]=='NULL'){$status='Неизвестная ошибка';}
			else if ($b[1]==0 || $b[1]==4){$status='Проверьте контакты';}
			elseif ($b[1]==3){$status='Проверьте SIM-карту';}
			echo '<tr><td align="right">'.$b[0].'</td><td align="right">'.$b[1].'</td><td>'.$status.'</td></tr>';			
		}
		echo '</table>';
	}
}
echo $out;
?>