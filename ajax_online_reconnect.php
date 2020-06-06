<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$s='';
if ($result = mysqli_query($db, 'SELECT * FROM `modems` WHERE `device`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$modems=unserialize($row['modems']);
		for ($i=1;$i<17;$i++)
		{
			$modems[$i][1]=-1;
		}
		mysqli_query($db, "REPLACE INTO `modems` SET `device`=".(int)$_GET['device'].",`modems`='".serialize($modems)."', `time`=".time()); 
	}
}
sr_command((int)$_GET['device'],'modem>disconnect&&modem>connect&&modem>on');
?>