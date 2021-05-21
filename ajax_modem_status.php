<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");

$s=onlineTable((int)$_GET['device']);
$numb=$s[1];
$staff=$time=flagGet($_GET['device'],'busy',1);
$time=$time+300-time();

if (!$numb || ($GLOBALS['sv_staff_id'] && $GLOBALS['sv_staff_id']!=flagGet((int)$_GET['device'],'busy')))
{
	if ($numb)
	{
		echo 'hide#-#'.$id.'#-#'.'#-#'.$time;
	}
	else
	{
		echo 'hide#-#'.$id.'#-#';
	}
	exit();
}

$s=$s[0];

$answer=$number='';
$id=$_GET['id'];
if ($result = mysqli_query($db, 'SELECT * FROM `sms_incoming` WHERE `number` IN ('.implode(',',$numb).') AND `done`=1 AND `id`>='.(int)$id.' ORDER BY `id` LIMIT 1')) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$number='+'.$row['number'];
		$txt=$row['txt'];
		$txt=preg_replace('!([0-9]{4,20})!','<span class="note">$1</span>',$txt);
		$txt=preg_replace("/(([a-z]+:\/\/)?(?:[a-zа-я0-9@:_-]+\.)+[a-zа-я0-9]{2,4}(?(2)|\/).*?)([-.,:]?(?:\\s|\$))/is",'<a href="$1" target="_blank"><b>$1</b></a>$3', $txt);
		$time=$row['time'];
		$sender=$row['sender'];
		mysqli_query($db, 'UPDATE `sms_incoming` SET `readed`=1 WHERE `id`='.$row['id']); 
		$id=$row['id']+1;
	}
}
if (!$_GET['txt'])
{
	$answer=onlineView($numb);
	$id=$answer[1];
	$answer=$answer[0];
	setlog($_GET['device'].'-'.$id,'test');
}
elseif ($number)
{
	$txt=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$txt);
	$answer='<div class="term_answer_item"><div class="answer_left answer_head" style="width: 120px;">'.srdate('H:i:s d.m',$time).'</div><div class="answer_head">'.$sender.'</div><div class="answer_left" style="width: 120px; margin-bottom: 10px;">'.$number.'</div><div>'.$txt.'</div></div>';
	$sound='#-#1';
}

if ($GLOBALS['sv_staff_id'])
{
	$msg='#-#'.$time;
	
	if (flagGet($_GET['device'],'busy',0)==$GLOBALS['sv_staff_id'])
	{
		$msg.='#-#1';
	}

	if ($time<=0 && flagGet($_GET['device'],'busy'))
	{
		mysqli_query($db, "DELETE FROM `modems` WHERE `device`=".(int)$_GET['device']);
		if (flagGet($_GET['device'],'cron'))
		{
			if (!flagGet($_GET['device'],'stop'))
			{
				flagSet($_GET['device'],'stop');
			}
			flagDelete($_GET['device'],'cron');
		}
		elseif (flagGet($_GET['device'],'stop',1)<time()-60)
		{
			flagDelete($_GET['device'],'stop');
		}
		flagDelete($_GET['device'],'busy');
	}
}
elseif ($staff && $time>0)
{
	$msg='#-#До конца сеанса <b>'.flagGet($_GET['device'],'staff').': '.$time.' сек.</b>#-#-1';
}

echo $s.'#-#'.$id.'#-#'.$answer.$sound.$msg;
?>
