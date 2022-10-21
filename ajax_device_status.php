<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
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
//     		$access=file_get_contents($GLOBALS['root'].'flags/answer_'.$row['id']);
		$access=flagGet($row['id'],'answer',1);
		$r='';
		if ($access+30<time())
		{
			$o='Offline';
			if (!$access){$t=': ∞';} else {$t=': '.time_calc(time()-$access);}
		} 
		else
		{
			$o='Online';
			if ($row['title']=='[create]' || $row['title']=='[init]' || $row['init']+10>time()) 
			{
				$r=';'.$row['id'];
				if ($row['init']+10>time())
				{
//					$r.=';<span class="but_win" data-id="win_action" data-title=\'Управление агрегатором '.$row['title'].'\' data-type="ajax_device_action.php?id='.$id.'" data-height="400" data-width="600">'.$row['title'].'</span>';
					$r.=';'.$row['title'];
//'<div class="sidebar legend">'.$row['serial'].'</div>';
					$explane='';
					if ($row['model']=='SR-Box-Bank' || $row['model']=='SR-Board')
					{
						$d=unserialize($row['data']);
						if ($d['map']=='1')
						{
							$explane='64';
						}
						else
						{
							for ($i=0;$i<8;$i++)
							{
								if ($d['map'][$i]=='1')
								{
									$explane++;
								}
							}
							if ($explane){$explane=$explane*64;} else {$explane='';}
						}
					}
					if ($explane){$explane='<br><span class="legend">'.$explane.' SIM</span>';}
					$r.=';'.str_replace('<img src="icons/','',str_replace('">','',icon_out($row['model'],$row['data'])));
					$r.=';'.$row['model'].$explane;
				}
			}
			else
			{
//				$n=';'.$row['id'].';'.$row['title'].';'.str_replace('<img src="icons/','',str_replace('">','',icon_out($row['model'],$row['data'])));
//				$n=';'.$row['id'].';<span class="but_win" data-id="win_action" data-title=\'Управление агрегатором '.$row['title'].'\' data-type="ajax_device_action.php?id='.$id.'" data-height="400" data-width="600">'.$row['title'].'</span>;'.str_replace('<img src="icons/','',str_replace('">','',icon_out($row['model'],$row['data'])));
//				$n=';'.$row['id'].';'.$row['title'].';'.str_replace('<img src="icons/','',str_replace('">','',icon_out($row['model'],$row['data'])));
			}
			$t='';
		}

		if ($row['status2']=='inprogress' && $row['count'])
		{
			$s.='Прогресс: '.$progress_txt.'% <span class="legend">['.$row['action'].']</span> <progress value="'.$progress.'" max="100" style="margin: 3px 0"></progress><span class="legend '.$o.'">'.$o.$t.'</span><br><br>';
			$status=1;
		} 
		elseif ($row['status2']=='waiting')
		{
			$s.='дальше ▶ <span class="legend">['.$row['action'].']</span><br><br>';
			$status=1;
		} 
		else 
		{
			if (!$status)
			{
				$s.=' <span class="'.$o.'">'.$o.$t.'</span>'.$r;
			}
			$status=1;
		}
	}
	echo trim($s,'#');
}