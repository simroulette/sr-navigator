<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if (flagGet($_GET['device'],'busy',0)==$GLOBALS['sv_staff_id'])
{
	if ($GLOBALS['sv_owner_id']){flagSet($_GET['device'],'busy',$GLOBALS['sv_staff_id']);}
	echo 'Сеанс продлен!';
}
else
{
	echo 'Вы не можете продлить этот сеанс!';
}
?>