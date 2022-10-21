<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
$modem=$_GET['modem'];
if ($_GET['modem'])
{
	if ($result = mysqli_query($db, 'SELECT `modems`,`model`,`data` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			if ($row['model']=='SR-Train')
			{
				$_GET['modem']='modem>select:'.$_GET['modem'].'&&';
			}
			elseif ($row['model']=='SR-Box-Bank' || $row['model']=='SR-Board')
			{
				$_GET['modem']=$_GET['modem']%8;
				if (!$_GET['modem']){$_GET['modem']=8;}
				$_GET['modem']='modem>select:'.$_GET['modem'].'&&';
			}
			elseif ($row['model']=='SR-Box-2-Bank')
			{
				if ($_GET['modem']<5){$_GET['modem']='modem1>';}
				elseif ($_GET['modem']>4){$_GET['modem']='modem2>';}
			}
			elseif ($row['model']=='SR-Box-8')
			{
				$_GET['modem']='modem>select:'.$_GET['modem'].'&&';
			}
			elseif ($row['model']=='SR-Organizer')
			{
				if ($_GET['modem']<9){$_GET['modem']=1;} else {$_GET['modem']=2;}
				$_GET['modem']='modem>select:'.$_GET['modem'].'&&';
			}
			elseif ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
			{
				if ($data['modems']==1)
				{
					$_GET['modem']=1;
				}
			}
			else
			{
				$_GET['modem']='';
			}
		}
	}
}
else
{
		$_GET['modem']='';
}
if ($_GET['action']=='sms' && $_GET['txt'] && $_GET['number'] && $_GET['txt']!='reload')
{
	$number=trim($_GET['number']);
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[0]!='+'){$number='+'.$number;}
	if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
	{
		sr_command_smart($_GET['device'],'sms.send:{"number":"'.$number.'","sms":"'.$_GET['txt'].'","modem":"'.(int)$_GET['modem'].'"}');
	}
	else
	{
//		if (strpos($_GET['modem'],'modem')!==false)
		if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
		{
			sr_command($_GET['device'],str_replace('modem','sms',$_GET['modem']).'send:'.$number.';'.$_GET['txt'],0,'SMS',60);
		}
		else
		{
			sr_command($_GET['device'],$_GET['modem'].'sms>send:'.$number.';'.$_GET['txt'],0,'SMS',60);
		}
	}
	echo '<div class="return_active">Отправление SMS...</div>###reload';
}
elseif ($_GET['action']=='sms' && $_GET['txt'] && $_GET['number'] && $_GET['txt']=='reload')
{
	$number=trim($_GET['number']);
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[0]!='+'){$number='+'.$number;}
	$answer=sr_answer($_GET['device'],0,1,'+CMGS:');
	$answer=sr_answer($_GET['device'],0,30,'+CMGS:');
	if (strpos($answer,'error:')===false)
	{
		if (strpos($answer,'ERROR')!==false)
		{
			echo '<div class="return_error">SMS не отправлена!</div>';
			echo '<br>'.$answer;
		}
		else
		{
			echo '<div class="return_ok">SMS успешно отправлена!</div>';
			echo '<br>'.$answer;
		}
		echo '<br><br>';
	}
	else
	{
		echo '<div class="return_active">Статус отправки SMS неизвестен!</div>';
	}
	sr_command($_GET['device'],'',0,'SMS',0);
}
elseif ($_GET['action']=='sms')
{
	echo '<div class="return_error">SMS не отправлена! Проверьте правильность заполнения полей...</div>';
}
elseif ($_GET['action']=='call' && $_GET['number'] && $_GET['txt']!='reload')
{
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[0]!='+' && $number[0]!='8' && strpos($number,'#')===false && strlen($number)>5){$number='+'.$number;}
	if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
	{
		if ($number[strlen($number)-1]!='#' && !strpos($number,'|')) // CALL
		{
			mysqli_query($db,'DELETE FROM `devices_events` WHERE `dev`="modem'.(int)$_GET['modem'].'" AND (`event`="calling" OR `event`="no_carrier")');
			sr_command_smart($_GET['device'],'call:'.rtrim($number,';').','.$_GET['modem']);
			echo '<div class="return_active">Набор номера '.rtrim($number,';').'...</div>###reload';
		}
		else // USSD
		{
			mysqli_query($db,'DELETE FROM `devices_events` WHERE `dev`="modem'.(int)$_GET['modem'].'" AND `event`="ussd"');
			if (strpos($number,'|'))
			{
				$gn=explode('|',$number);
				sr_command_smart($_GET['device'],'ussd:'.$gn[0].','.$_GET['modem']); // Запрос 1 часть
				sleep(15);	
				sr_command_smart($_GET['device'],'ussd:'.$gn[1].','.$_GET['modem']); // Запрос 2 часть
			}
			else
			{
				sr_command_smart($_GET['device'],'ussd:'.$number.','.$_GET['modem']); // Запрос
			}
			echo '<div class="return_active">USSD-запрос выполняется...</div>###reload';
		}
	}
	else
	{
		if ($number[strlen($number)-1]!='#' && !strpos($number,'|')) 
		{
			if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
			{
				sr_command($_GET['device'],$_GET['modem'].'send:ATD'.rtrim($number,';').';',0,'CALL',60);
			}
			else
			{
				sr_command($_GET['device'],$_GET['modem'].'modem>send:ATD'.rtrim($number,';').';',0,'CALL',60);
			}
			echo '<div class="return_active">Набор номера '.rtrim($number,';').'...</div>###reload';
		}
		else // USSD
		{
			if (strpos($number,'|'))
			{
				$gn=explode('|',$number);
				if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
				{
					sr_command($_GET['device'],$_GET['modem'].'send:AT+CUSD=1,"'.$gn[0].'",15',0,'USSD',60); // Запрос 1 часть
					sleep(15);	
					sr_command($_GET['device'],$_GET['modem'].'send:AT+CUSD=1,"'.$gn[1].'",15',0,'USSD',60); // Запрос 2 часть
				}
				else
				{
					sr_command($_GET['device'],$_GET['modem'].'modem>send:AT+CUSD=1,"'.$gn[0].'",15',0,'USSD',60); // Запрос 1 часть
					sleep(15);	
					sr_command($_GET['device'],$_GET['modem'].'modem>send:AT+CUSD=1,"'.$gn[1].'",15',0,'USSD',60); // Запрос 2 часть
				}
			}
			else
			{
				if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
				{
					sr_command($_GET['device'],$_GET['modem'].'send:AT+CUSD=1,"'.$number.'",15',0,'USSD',60);
				}
				else
				{
					sr_command($_GET['device'],$_GET['modem'].'modem>send:AT+CUSD=1,"'.$number.'",15',0,'USSD',60);
				}
			}
			echo '<div class="return_active">USSD-запрос выполняется...</div>###reload';
		}
	}
}
elseif ($_GET['action']=='call' && $_GET['number'] && $_GET['txt']=='reload')
{
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[strlen($number)-1]!='#' && !strpos($number,'|')) 
	{
		if ($number[0]!='+' && $number[0]!='8' && strpos($number,'#')===false && strlen($number)>5){$number='+'.$number;}
		if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
		{
			if ($number[strlen($number)-1]!='#' && !strpos($number,'|')) 
			{
				$qry="SELECT `id`,`event`,`result` FROM `devices_events` WHERE `dev`='modem".$_GET['modem']."' AND (`event`='calling' OR `event`='no_carrier') ORDER BY `time` DESC LIMIT 1";
				for ($i=0;$i<30;$i++)
				{
					sleep(1);
					$result_call=mysqli_query($db,$qry);
					if ($row_smart = mysqli_fetch_assoc($result_call))
					{
						mysqli_query($db,"DELETE FROM `devices_events` WHERE `id`=".$row_smart['id']);
						if ($row_smart['event']=='calling' && $row_smart['result']==1)
						{
							echo '<div class="return_ok">Осуществляется вызов на номер '.rtrim($number,';').'...<input type="button" onclick="hangup('.$modem.');" value="Завершить" class="hangup"></div><br>###reload';
//							echo '<br>'.$answer;
						}
						else
						{
							echo '<div class="return_error">Вызов отклонен!</div><br>';
//							echo '<br>'.$answer;
						}
						break;
					}
				}
			}
		}
		else
		{
/*
			if (strpos($_GET['modem'],'modem')!==false)
			{
				$answer=sr_answer($_GET['device'],0,1,str_replace('modem','',str_replace('>','',$_GET['modem'])).':ATD'.rtrim($number,';').';');
				$answer=sr_answer($_GET['device'],0,20,str_replace('modem','',str_replace('>','',$_GET['modem'])).':ATD'.rtrim($number,';').';');
			}
			else
*/
			{
				$answer=sr_answer($_GET['device'],0,1,'ATD'.rtrim($number,';').';');
				$answer=sr_answer($_GET['device'],0,20,'ATD'.rtrim($number,';').';');
			}
			if (strpos($answer,'error:')===false)
			{
				if (strpos($answer,'ERROR'))
				{
					echo '<div class="return_error">Вызов отклонен!</div>';
					echo '<br>'.$answer;
				}
				else
				{
					echo '<div class="return_ok">Осуществляется вызов...<input type="button" onclick="hangup('.$modem.');" value="Завершить" class="hangup"></div>';
					echo '<br>'.$answer;
				}
				echo '<br><br>';
			}
			sr_command($_GET['device'],'',0,'CALL',0);
		}
	}
	else // USSD
	{
		if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
		{
			$qry="SELECT `result` FROM `devices_events` WHERE `dev`='modem".$_GET['modem']."' AND `event`='ussd'";
			for ($i=0;$i<30;$i++)
			{
				sleep(1);
				$result_call=mysqli_query($db,$qry);
				if ($row_smart = mysqli_fetch_assoc($result_call))
				{
					echo '<div class="return_ok">USSD-запрос выполнен:</div>';
//					echo '<div class="qans">'.$row_smart['result'].'</div>';
       					$answer=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$row_smart['result']);
					echo '<div class="qans">'.$answer,'</div>';
					echo '<br><br>';
					break;
				}
			}
		}
		else
		{
			if (strpos($number,'|'))
			{
				$gn=explode('|',$number);
/*
				if (strpos($_GET['modem'],'modem')!==false)
				{
					$answer=sr_answer($_GET['device'],0,1,$_GET['modem'].'.send:AT+CUSD=1,"'.$gn[0].'",15');
					sleep(15);	
					$answer=sr_answer($_GET['device'],0,30,$_GET['modem'].'.send:AT+CUSD=1,"'.$gn[1].'",15');
				}
				else
*/
				{
					$answer=sr_answer($_GET['device'],0,1,'AT+CUSD=1,"'.$gn[0].'",15');
					sleep(15);	
					$answer=sr_answer($_GET['device'],0,30,'AT+CUSD=1,"'.$gn[1].'",15');
				}
			}
			else
			{
/*
				if (strpos($_GET['modem'],'modem')!==false)
				{
					$answer=sr_answer($_GET['device'],0,1,$_GET['modem'].'.send:AT+CUSD=1,"'.$number.'",15');
					$answer=sr_answer($_GET['device'],0,30,$_GET['modem'].'.send:AT+CUSD=1,"'.$number.'",15');
				}
*/
//				else
				{
					$answer=sr_answer($_GET['device'],0,1,'AT+CUSD=1,"'.$number.'",15');
					$answer=sr_answer($_GET['device'],0,30,'AT+CUSD=1,"'.$number.'",15');
				}
			}
			if (strpos($answer,'error:')===false)
			{
				if (strpos($answer,'ERROR'))
				{
					echo '<div class="return_error">USSD-запрос не выполнен!</div>';
				}
				else
				{
					echo '<div class="return_ok">USSD-запрос выполнен:</div>';
					echo '<div class="qans">'.$answer.'</div>';
					$answer=sr_answer($_GET['device'],0,20,'+CUSD:');

					if ($answer && strpos($answer,'error:')===false)
					{
						$answer=$pdu->decode_ussd($answer);
						$answer=preg_replace('!([0-9]{4,20})!','<span class="note" onclick="copy(\'$1\');soundClick();">$1</span>',$answer);
						echo '<div class="qans">'.$answer,'</div>';
					}
					echo '<br><br>';
				}
			}
			else
			{
				echo '<div class="return_active">USSD-запрос не вернул ответ!</div>';
			}
			sr_command($_GET['device'],'',0,'USSD',0);
		}
	}
}
elseif ($_GET['action']=='call')
{
	echo '<div class="return_error">Вызов не осуществлен! Проверьте правильность заполнения полей...</div>';
}
elseif ($_GET['action']=='incoming')
{
	$_GET['txt']=(int)$_GET['txt'];
	if (!$_GET['txt']){$_GET['txt']='';}
	setlog('UPDATE `modems` SET `incoming`="'.$_GET['txt'].'" WHERE `device`='.(int)$_GET['device'],'test');
	mysqli_query($db, 'UPDATE `modems` SET `incoming`="'.mysqli_real_escape_string($db,$_GET['txt']).'" WHERE `device`='.(int)$_GET['device']); 
	sr_command($_GET['device'],'modem>fix:'.$modem);
}
elseif ($_GET['action']=='hangup')
{
	if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
	{
		sr_command_smart($_GET['device'],'command:hangup,'.(int)$_GET['modem']);
	}
	else
	{
		if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
		{
			sr_command($_GET['device'],$_GET['modem'].'send:ATH0');
			mysqli_query($db, 'UPDATE `devices` SET `answer`='.time().',msg="" WHERE `id`='.(int)$_GET['device']); 
		}
		else
		{
			sr_command($_GET['device'],$_GET['modem'].'modem>send:ATH0',0,'ANSWER',20);
		}
	}
	echo '1';
}
elseif ($_GET['action']=='answer')
{
	if ($row['model']=='SR-Organizer-Smart' || $row['model']=='SR-Box-8-Smart')
	{
		sr_command_smart($_GET['device'],'command:answer,'.(int)$_GET['modem']);
	}
	else
	{
		if (strpos($_GET['modem'],'modem1>')!==false || strpos($_GET['modem'],'modem2>')!==false)
		{
			sr_command($_GET['device'],$_GET['modem'].'send:ATA');
			mysqli_query($db, 'UPDATE `devices` SET `answer`='.time().',msg="" WHERE `id`='.(int)$_GET['device']); 
		}
		else
		{
			sr_command($_GET['device'],$_GET['modem'].'modem>send:ATA',0,'ANSWER',20);
		}
	}
	echo '1';
}
elseif ($_GET['action']=='review')
{
	if ($_GET['switch'])
	{
		flagSet((int)$_GET['device'],'review');
	}
	else
	{
		flagDelete((int)$_GET['device'],'review');
		flagDelete((int)$_GET['device'],'review_timer');
		flagDelete((int)$_GET['device'],'review_step');
	}
	echo '1';
}
?>