<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
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
		if (strpos($row['report'],'dev_')!==false)
		{
			echo $row['report'];
		}
		else
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
					<th>Комментарий</th>
				</tr>  
			</thead>';
			
			$a=explode(',',trim($row['report'],','));
			foreach ($a AS $data)
			{
				$b=explode(':',$data);
				if ($b[1]=='NULL'){$status='Неизвестная ошибка';}
				else if ($b[1]==0 || $b[1]==4 || $b[1]==12){$status='Карта неактивна';}
				elseif ($b[1]==2){$status='Нет регистрации в сети';}
				elseif ($b[1]==3){$status='Карта заблокирована';}
				elseif ($b[1]==11){$status='Некорректная задача либо сбой в работе оператора связи';}
				elseif ($b[1]==101){$status='Не удалось выполнить предварительную калибровку!<br>Требуется вмешательство специалиста.';}
				echo '<tr><td align="right">'.$b[0].'</td><td align="right">'.$b[1].'</td><td>'.$status.'</td></tr>';			
			}
			echo '</table>';
		}
	}
}
echo $out;
?>