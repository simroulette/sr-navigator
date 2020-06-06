<?
// ===================================================================
// Sim Roulette -> SR-Nano functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

// Container function: Selecting a row, connecting contacts, powering modems, checking connections, and performing the following functions                        
// Функция-контейнер: Выбор ряда, подключение контактов, включение модемов, проверка связи и выполнение перечисленных функций
function sim_link($dev, $data, $curRow, $place, $actId, $func)
{
//	$dev		Device ID
//	$data		Array with additional data	
//	$curRow	        Panel row for positioning 1 modem line
//	$modems	        List of modems to process
//	$actId          Action ID
//	$func     	List of functions to perform

	global $root,$db;
	setlog('[sim_link:'.$dev.'] Start');
	$data=unserialize($data);
	$time_limit=time()+$data['time_limit'];
	sr_answer_clear();
	$connect=time();
	$reconnect=0; // The count of reconnections | Счетчик переподключений
	sr_command($dev,'card:A0');
	$done=array();

	while ($time_limit>time())
	{
		setlog('[sim_link:'.$dev.'] Cicle -> Reconnect:'.$reconnect.', Remaining time:'.($time_limit-time()).' sek.');
		br($dev,'act_'.$actId.'_stop');
		br($dev);
		if (!$reconnect || $reconnect==20)
		{
			sr_command($dev,'card:'.$place.'&&modem>connect&&modem>on');
			$restart_time=time()+40;
		}
		$reconnect++;
		setlog('[sim_link:'.$dev.'] Getting information about operators');
		sr_command($dev,'modem>send:AT+COPS?'); // Getting information about the operator | Запрос информации об операторе 
		$answer=sr_answer($dev,0,10,'+COPS');
		if ($answer=='error:no answer')
		{
			sr_command($dev,'modem>send:AT+COPS?'); // Repeated request for information about the operator | Повторный запрос информации об операторе
			$answer=sr_answer($dev,0,10,'+COPS');
		}
		if ($answer && strpos($answer,'error:')===false)
		{
			preg_match('!"(.*)"!Uis', $answer, $test);
			if ($test[1])
			{
				setlog('[sim_link:'.$dev.'] Operator:'.$test[1]);
				$a=explode(';',$func);
				$status=array();
				for ($k=0;$k<count($a);$k++)
				{
					if (!$done[$k])
					{
						$f=$a[$k]; 
						$answer=$f($dev,0,$place,$test[1]);
						if ($answer['status'])
						{
							$done[$k]=1;
						}
					}
				}
				if ($answer['status'])
				{
					mysqli_query($db, 'UPDATE `actions` SET `progress`=`progress`+1 WHERE `id`='.(int)$actId); 
					setlog('[sim_link:'.$dev.'] Done!'); // Готово
					return;
				}
			}
		}
		elseif ($restart_time<time())
		{
			setlog('[sim_link:'.$dev.'] Restarting the modem');
			sr_command($dev,'modem>send:AT+CFUN=1,1'); // Перезапуск модемов 
		}
	}
	setlog('[sim_link:'.$dev.'] The time limit is reached!'); // Лимит времени исчерпан
}
?>