<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['step'])
{
//	echo $c.'-'.rand(111,999);
	$dev="-";
	if ($result = mysqli_query($db, 'SELECT `title`,`step` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$dev=$row['title'];
			$step=$row['step'];
		}
	}
	if ($c=trim($_GET['command']))
	{
		$GLOBALS['terminal_mode']=1;
		sr_command((int)$_GET['device'],$c);
//	echo $c.'+'.rand(111,999); exit();
//		echo '<div class="term_item" onclick="document.getElementById(\'command\').value=\''.str_replace('"','&quot;',$c).'\';"><div class="answer_left answer_head">'.srdate('H:i:s').'</div><div class="answer_head">'.$dev.'</div><div class="answer_left" style="text-align: right;">'.$step++.'</div><div>'.$c.'</div></div>'.$str;
		echo '<div class="term_item" onclick="document.getElementById(\'command\').value=\''.str_replace('"','&quot;',$c).'\';"><div class="answer_left answer_head">'.srdate('H:i:s').'</div><div class="answer_head">'.$dev.'</div><div class="answer_left" style="text-align: right;">'.$step++.'</div><div>'.$c.'</div></div>'.$str;//.'#!#'.$_GET['com_id'];
		exit();
	}
	$qry="UPDATE `devices` SET
	`step`=".$step."
	WHERE `id`=".(int)$_GET['device'];
	mysqli_query($db,$qry);
}

$n=0;

// Getting responses from client devices | Получение ответов агрегаторов-клиентов
if ($result = mysqli_query($db, 'SELECT l.*,unix_timestamp(l.time) AS time, d.title FROM `link_incoming` l INNER JOIN `devices` d ON l.id>'.(int)$_GET['com_id'].' AND d.id=l.device ORDER BY l.`id` LIMIT 5')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$n){echo '#!#';} $n++;
//		mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.$row['id']); 
		if (!$answer=$row['answer']){$answer='—';}

		$answer=bbcode($answer);

		if ($answer=='1'){$answer.=' <span class="comment">TRUE</span>';}
		if ($answer=='NULL'){$answer.=' <span class="comment">FALSE</span>';}
		echo '<div class="term_answer_item"><div class="answer_left answer_head">'.srdate('H:i:s',$row['time']).'</div><div class="answer_head">'.$row['title'].'</div><div class="answer_left" style="text-align: right;">'.$row['step'].'</div><div>'.$answer.'</div></div>#!#'.$row['id'];
		exit();
	}
}

// Getting responses from server devices | Получение ответов агрегаторов-серверов
if ($result = mysqli_query($db, "SELECT `id` FROM `devices` WHERE `type`='server' AND `ip`<>''")) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		$answer=sr_answer($row['id'],0,0);
		if ($answer!='error:no answer')
		{
			if (!$n){echo '#!#';} $n++;
			if ($answer=='1'){$answer.=' <span class="comment">TRUE</span>';}
			if ($answer=='NULL'){$answer.=' <span class="comment">FALSE</span>';}
			echo '<div class="term_answer_item"><div class="answer_left answer_head">'.srdate('H:i:s',time()).'</div><div class="answer_head">'.$row['title'].'</div><div class="answer_left" style="text-align: right;">'.$row['step'].'</div><div>'.$answer.'</div></div>#!#'.$_GET['com_id'];
		}
	}
}

function bbcode($answer)
{
	$answer=str_replace('[table]','<table class="tabout">',$answer);
	$answer=str_replace('[/table]','</table>',$answer);
	$answer=str_replace('[tr]','<tr>',$answer);
	$answer=str_replace('[/tr]','</tr>',$answer);
	$answer=str_replace('[th]','<th>',$answer);
	$answer=str_replace('[thc]','<th style="text-align:center">',$answer);
	$answer=str_replace('[thr]','<th style="text-align:right">',$answer);
	$answer=str_replace('[/th]','</th>',$answer);
	$answer=str_replace('[td]','<td>',$answer);
	$answer=str_replace('[tdc]','<td align="center">',$answer);
	$answer=str_replace('[tdr]','<td align="right">',$answer);
	$answer=str_replace('[/td]','</td>',$answer);
	$answer=str_replace('[b]','<b>',$answer);
	$answer=str_replace('[/b]','</b>',$answer);
	$answer=str_replace('[i]','<i>',$answer);
	$answer=str_replace('[/i]','</i>',$answer);
	$answer=str_replace('[br]','<br>',$answer);
	return($answer);
}

?>