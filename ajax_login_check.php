<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$_GET['login']=trim($_GET['login']);
if (strlen(trim($_GET['login']))<8){echo '1'; exit();}
if (strlen(trim($_GET['login']))>32){echo '1'; exit();}
if (!preg_match('/^[A-Za-z0-9_-]{6,32}$/i',$_GET['login'])){echo '1'; exit();}
$qry="SELECT * FROM `a_users` WHERE `login` LIKE '".mysqli_real_escape_string($db,$_GET['login'])."'";
if ($result = mysqli_query($db, $qry)) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		echo '1';
		exit();
	}
}
$qry="SELECT * FROM `staff` WHERE `login` LIKE '".mysqli_real_escape_string($db,$_GET['login'])."'";
if ($result = mysqli_query($db, $qry)) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		echo '1';
	}
}
?>