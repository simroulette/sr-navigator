<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$s='';
if ($result = mysqli_query($db, 'SELECT d.*,a.status AS status2,a.count,a.progress,a.action FROM `devices` d LEFT JOIN `actions` a ON a.device=d.id')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		$s.=$row['id'].';';
		$progress=$progress_txt=round($row['progress']/($row['count']/100+0.0000001),2);
		if ($progress && $progress<5){$progress=5;}
		if ($row['status2']=='inprogress' && $row['count']){$s.='Прогресс: '.$progress_txt.'% ('.$row['action'].') <progress value="'.$progress.'" max="100"></progress>';} elseif ($row['status2']=='waiting'){$s.='В очереди';} else {$s.='';}
		$s.='#';
	}
	echo trim($s,'#');
}