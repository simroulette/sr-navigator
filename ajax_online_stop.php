<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$s='';
mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".(int)$_GET['device']);
if (flagGet($_GET['device'],'cron'))
{
	if (!flagGet($_GET['device'],'stop'))
	{
		flagSet($_GET['device'],'stop');
	}
	flagDelete($_GET['device'],'cron');
	flagDelete($_GET['device'],'busy');
	flagDelete($_GET['device'],'staff');
}
elseif (flagGet($_GET['device'],'stop',1)<time()-60)
{
	flagDelete($_GET['device'],'stop');
}
echo 'Процесс завершается...';
?>
