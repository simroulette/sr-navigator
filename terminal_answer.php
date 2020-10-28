<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['step'])
{
	$dev="-";
	if ($result = mysqli_query($db, 'SELECT `title`,`step` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			$dev=$row['title'];
			$step=$row['step'];
		}
	}
	$com=explode('||',trim($_GET['command']));
	$str='';
	for ($i=0;$i<count($com);$i++)
	{
		if ($c=trim($com[$i]))
		{
			sr_command((int)$_GET['device'],$c);
			$str='<div class="term_item" onclick="document.getElementById(\'command\').value=\''.$c.'\';"><div class="answer_left answer_head">'.date('H:i:s').'</div><div class="answer_head">'.$dev.'</div><div class="answer_left" style="text-align: right;">'.$step++.'</div><div>'.$c.'</div></div>'.$str;
		}
	}
	echo $str;
	$qry="UPDATE `devices` SET
	`step`=".$step."
	WHERE `id`=".(int)$_GET['device'];
	mysqli_query($db,$qry);
}

$n=0;

// Getting responses from client devices | Получение ответов агрегаторов-клиентов
if ($result = mysqli_query($db, 'SELECT l.*,unix_timestamp(l.time) AS time, d.title FROM `link_incoming` l LEFT JOIN `devices` d ON d.id=l.device ORDER BY l.`id` LIMIT 5')) 
{
	while ($row = mysqli_fetch_assoc($result))
	{
		if (!$n){echo '#!#';} $n++;
		mysqli_query($db, 'DELETE FROM `link_incoming` WHERE `id`='.$row['id']); 
		if (!$answer=$row['answer']){$answer='—';}
		if ($answer=='1'){$answer.=' <span class="comment">TRUE</span>';}
		if ($answer=='NULL'){$answer.=' <span class="comment">FALSE</span>';}
		echo '<div class="term_answer_item"><div class="answer_left answer_head">'.date('H:i:s',$row['time']).'</div><div class="answer_head">'.$row['title'].'</div><div class="answer_left" style="text-align: right;">'.$row['step'].'</div><div>'.$answer.'</div></div>';
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
			echo '<div class="term_answer_item"><div class="answer_left answer_head">'.date('H:i:s',time()).'</div><div class="answer_head">'.$row['title'].'</div><div class="answer_left" style="text-align: right;">'.$row['step'].'</div><div>'.$answer.'</div></div>';
		}
	}
}

?>