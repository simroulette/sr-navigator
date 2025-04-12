<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2025 Sim Roulette, https://sim-roulette.com
// ===================================================================

include("_func.php");
//$com=htmlspecialchars_decode(str_replace('&num;','#',str_replace('&plus;','+',str_replace('!','&',urldecode($_GET['command'])))));
if ($result = mysqli_query($db, 'SELECT `model`,`data` FROM `devices` WHERE `id`='.(int)$_GET['device'])) 
{
	if ($row = mysqli_fetch_assoc($result))
	{
		if (strpos($row['model'],'SR-Nano')!==false)
		{
			$data=unserialize($row['data']);
			if ($data['modem']=='SIM5320' || $data['modem']=='SIM5360' || $data['modem']=='SIM7100')
			{
				$com=str_replace('clear_sms','modem>send:AT+CMGD=0,4',$_GET['command']);
			}
			else
			{
				$com=str_replace('clear_sms','modem>send:AT+CMGDA=5',$_GET['command']);
			}
		}
		elseif (strpos($row['model'],'SR-Box-2')!==false)
		{
			$com=str_replace('clear_sms','modem>pack:AT+CMGD=0,4',$_GET['command']);
		}
		else
		{
//			$com=str_replace('clear_sms','modem>pack:AT+CMGDA="DEL ALL"##ALL##1',$_GET['command']);
			$com=str_replace('clear_sms','modem>pack:AT+CMGDA=5##ALL##1',$_GET['command']);
		}
	}
}
sr_command((int)$_GET['device'],$com);
?>Команда отправлена
