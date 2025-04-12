<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
$id=(int)str_replace('ce_','',$_POST['id']);
$comment=trim(urldecode($_POST['comment']));
if ($id && $comment)
{
	if ($sv_staff_id)
	{
		$qry='SELECT `ext`,`name` FROM `staff` WHERE `id`='.$sv_staff_id; 
		if ($result = mysqli_query($db, $qry)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$ext=$row['ext'];
				$staff=$row['name'];
			}
		}
	}
	else
	{
		$ext=3;
	}
	if ($ext==2)
	{
		$qry='SELECT `comment`,`id` FROM `cards` WHERE `number`="'.$id.'"'; 
		if ($result = mysqli_query($db, $qry)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$comment=trim($row['comment'].'
user:'.$staff.' time:'.srdate('d.m.Y H:i:s',$row['time']).'
'.$comment);
				$qry='UPDATE `cards` SET `comment`="'.mysqli_real_escape_string($db,$comment).'" WHERE `number`="'.$id.'"';
				mysqli_query($db,$qry);
				$comment=preg_replace('/\n(user:(.*)time:(.*))\n/Us', "\n".'<user>${2} • ${3}</user>'."\n",$comment);
			}
		}
	}
	else
	{
		$qry='UPDATE `cards` SET `comment`="'.mysqli_real_escape_string($db,$comment).'" WHERE `number`="'.$id.'"';
		mysqli_query($db,$qry);
		$comment=preg_replace('/\n(user:(.*)time:(.*))\n/Us', "\n".'<user>${2} • ${3}</user>'."\n",$comment);
	}
	echo str_replace('
','<br>',$comment);
}
?>
