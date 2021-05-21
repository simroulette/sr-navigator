<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
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
		else
		{
			$com=str_replace('clear_sms','modem>pack:AT+CMGDA=5##ALL##1',$_GET['command']);
		}
	}
}
sr_command((int)$_GET['device'],$com);
?>Команда отправлена