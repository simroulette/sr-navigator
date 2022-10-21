<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$s='';
$com='';
if ($result = mysqli_query($db, 'SELECT m.*,d.`model` FROM `modems` m INNER JOIN `devices` d ON d.`id`=m.`device` WHERE m.`device`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$modems=unserialize($row['modems']);
		if ($row['model']=='SR-Train')
		{
			for ($i=1;$i<17;$i++)
			{
				$modems[$i][1]=-1;
			}
		}
		else
		{
			$modems[1]=-1;
		}
		mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].",`modems`='".serialize($modems)."', `time`=".time()); 
		if (strpos($row['model'],'SR-Nano')!==false)
		{
			$com='card>reposition&&';
		}
	}
}
sr_command((int)$_GET['device'],$com.'modem>disconnect&&modem>connect&&modem>on');
?>Команда на переподключение отправлена