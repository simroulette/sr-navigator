<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$s='';
$id=0;
if ($result = mysqli_query($db, 'SELECT d.*,a.status AS status2,a.count,a.progress,a.action FROM `devices` d LEFT JOIN `actions` a ON a.device=d.id ORDER BY d.`id`,a.`status`,a.`id`')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if ($row['id']!=$id)
		{
			if ($id)
			{
				$s.='#';
			}
			$s.=$row['id'].';';
			$id=$row['id'];
			$status=0;
		}
		$progress=$progress_txt=round($row['progress']/($row['count']/100+0.0000001),2);
		if ($progress && $progress<5){$progress=5;}
		$access=flagGet($row['id'],'answer',1);
		if ($access+30<time()){$o='Offline';if (!$access){$t=': ∞';} else {$t=': '.time_calc(time()-$access);}} else {$o='Online';$t='';}

		if ($row['status2']=='inprogress' && $row['count'])
		{
			$s.='Прогресс: '.$progress_txt.'% <span class="legend">['.$row['action'].']</span> <progress value="'.$progress.'" max="100" style="margin: 3px 0"></progress><span class="legend '.$o.'">'.$o.$t.'</span><br><br>';
			$status=1;
		} 
		elseif ($row['status2']=='waiting')
		{
			$s.='дальше → <span class="legend">['.$row['action'].']</span><br><br>';
			$status=1;
		} 
		else 
		{
			if (!$status)
			{
				$s.=' <span class="'.$o.'">'.$o.$t.'</span>';
			}
			$status=1;
		}
	}
	echo trim($s,'#');
}