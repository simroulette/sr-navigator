<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
if ($_GET['modem']){$_GET['modem']='modem>select:'.$_GET['modem'].'&&';} else {$_GET['modem']='';}
if ($_GET['action']=='sms' && $_GET['txt'] && $_GET['number'] && $_GET['txt']!='reload')
{
	$number=trim($_GET['number']);
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[0]!='+'){$number='+'.$number;}
	sr_command($_GET['device'],$_GET['modem'].'sms>send:'.$number.';'.$_GET['txt'],0,'SMS',60);
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
	if ($number[strlen($number)-1]=='#') // USSD
	{
		sr_command($_GET['device'],$_GET['modem'].'modem>send:AT+CUSD=1,"'.$number.'",15',0,'USSD',60);
		echo '<div class="return_active">USSD-запрос выполняется...</div>###reload';
	}
	else
	{
		if ($number[0]!='+' && $number[0]!='8'){$number='+'.$number;}
		sr_command($_GET['device'],$_GET['modem'].'modem>send:ATD'.$number.';',0,'CALL',60);
		echo '<div class="return_active">Набор номера '.$number.'...</div>###reload';
	}
}
elseif ($_GET['action']=='call' && $_GET['number'] && $_GET['txt']=='reload')
{
	$number=str_replace('[r]','#',trim($_GET['number']));
	if ($number[strlen($number)-1]=='#') // USSD
	{
		$answer=sr_answer($_GET['device'],0,1,'AT+CUSD=1,"'.$number.'",15');
		$answer=sr_answer($_GET['device'],0,30,'AT+CUSD=1,"'.$number.'",15');
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
	else
	{
		if ($number[0]!='+'){$number='+'.$number;}
		$answer=sr_answer($_GET['device'],0,1,'ATD'.$number.';');
		$answer=sr_answer($_GET['device'],0,20,'ATD'.$number.';');
		if (strpos($answer,'error:')===false)
		{
			if (strpos($answer,'ERROR'))
			{
				echo '<div class="return_error">Вызов отклонен!</div>';
				echo '<br>'.$answer;
			}
			else
			{
				echo '<div class="return_ok">Осуществляется вызов...</div>';
				echo '<br>'.$answer;
			}
			echo '<br><br>';
		}
		sr_command($_GET['device'],'',0,'CALL',0);
	}
}
elseif ($_GET['action']=='call')
{
	echo '<div class="return_error">Вызов не осуществлен! Проверьте правильность заполнения полей...</div>';
}
?>