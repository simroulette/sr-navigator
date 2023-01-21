<?
// ===================================================================
// Sim Roulette -> Functions
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2022 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

Error_Reporting(~E_ALL & ~E_NOTICE & ~E_DEPRECATED);

include($root.'_config.php');
include($root.'_hardware.php');
include($root.'_ext.php');
require($root.'pdu/Pdu/Pdu.php'); 
require($root.'pdu/Utf8/Utf8.php'); 
require($root.'pdu/Exception/InvalidArgumentException.php');
$pdu = Application\Pdu\Pdu::getInstance();
$set_data['flags']=array();

function statusAtr($dev,$status,$callOnly=0)
{
	// Проверка на Online
	$access=flagGet($dev,'answer',1);
	if ($access+90<time())
	{
		$st='Offline';
		$bg='FF0000';
		$color='FFFFFF';
		if (!$access)
		{
			$st.=': ∞';
		} 
		else 
		{
			$t=time_calc(time()-$access);
			if (strlen($t)>4){$t='∞';}
			$st.=': '.$t;
		}
	}
	else
	{
		$color='000';
		if ($status==-3)
		{
			$st='ожидание';
			$bg='CCCCCC';
		}
		elseif ($status==-2)
		{
			$st='инициализация';
			$bg='b0b5fd';
		}
		elseif ($status==-1)
		{
			$st='включение';
			$bg='99CCFF';
		}
		elseif ($status==0)
		{
			$st='неактивна <span>0</span>';
			$bg='ff8600';
		}
		elseif ($status==1 && !$callOnly)
		{
			$st='активна';
			$bg='82b013';
		}
		elseif ($status==1)
		{
			$st='SMS недоступны';
			$bg='ff5db4';
		}
		elseif ($status==2)
		{
			$st='регистрация <span>2</span>';
			$bg='eab50e';
		}
		elseif ($status==3)
		{
			$st='заблокирована <span>3</span>';
			$bg='FF0000';
			$color='fff';
		}
		elseif ($status==4)
		{
			$st='неактивна <span>4</span>';
			$bg='FF9900';
		}
		elseif ($status==5)
		{
			$st='активна <span>R</span>';
			$bg='82b013';
		}
		elseif ($status==6)
		{
			$st='[пусто]';
			$bg='000000';
			$color='fff';
		}
		elseif ($status==9)
		{
			$st='SMS недоступны';
			$bg='ff5db4';
		}
	}
	return(array($color,$bg,$st));
}

function statusAtrApi($dev,$status)
{
	// Проверка на Online
	$access=flagGet($dev,'answer',1);
	if ($access+60<time())
	{
		$st='OFFLINE';
	}
	else
	{
		if ($status==-3 || $status==-1 || $status==2 || $status==-2)
		{
			$st='CONNECTING';
		}
		elseif ($status==3 || $status==4 || !$status)
		{
			$st='ERROR';
		}
		elseif ($status==1 || $status==5)
		{
			$st='WAIT_SMS';
		}
	}
	return($st);
}

// Output a table of modem statuses
// Вывод таблицы статусов модемов
function onlineTable($dev,$hide=0)
{
//	$dev		Device ID

	global $db;

	if ($dev)
	{
		if ($result = mysqli_query($db, 'SELECT m.*,d.model,d.data FROM `modems` m INNER JOIN `devices` d ON d.id='.$dev.' WHERE `device`='.$dev)) 
		{
			if ($row = mysqli_fetch_assoc($result))
			{
				$modems=unserialize($row['modems']);

				if ($row['model']=='SR-Train')
				{
					foreach ($modems AS $key => $status)
					{
						$place=$status[0].'-'.$key;
						if ($key>8){$place=($status[0]+3).'-'.($key-8);}
						$places[]="'".$place."'";
					}
					if (count($places))
					{
						if ($result2 = mysqli_query($db, 'SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
						LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
						WHERE c.`device`='.$row['device'].' AND c.`place` IN ('.implode(',',$places).') ORDER BY c.`place`')) 
						if ($result2 = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row2 = mysqli_fetch_assoc($result2))
							{
								$no=0;
								$ids[$row2['place']]=$row2['id'];
								$numbers[$row2['place']]=$row2['number'];
								$operators[$row2['place']]=$row2['operator_name'];
								$names[$row2['place']]=$row2['title'];
								if ($row2['number'])
								{
									$numb[]='"'.$row2['number'].'"';
								}
								else
								{
									$numb[]='"'.$row2['place'].'"';
								}
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
					$places=array();		
					foreach ($modems AS $key => $status)
					{
						$curRow=$status[0];
						
						$realPlace=$place=$status[0].'-'.$key;
						if ($key>8){$place=($status[0]+3).'-'.($key-8);}
						$places[]="'".$place."'";

						if ($row['incoming'] && $numbers[$place]==$row['incoming']){$incoming=1;$callOnly=0;} 
						elseif (!$row['incoming']){$incoming='';$callOnly=0;}
						else {$incoming='';$callOnly=1;}
                                                $ar=statusAtr($dev,$status[1],$callOnly);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];

						$table[$key]=array(
						'num'=>$key,
						'incoming'=>$incoming,
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
					}
				}

				else if ($row['model']=='SR-Organizer')
				{
					$n=1;
					for ($i=1;$i<9;$i++)
					{
						$table[$i]=array(
						'num'=>$i,
						'place'=>'1-'.$i,
						'status'=>'B',
						'bg'=>'DDD',
						'color'=>'000000',
						);
					}
					for ($i=1;$i<9;$i++)
					{
						$table[8+$i]=array(
						'num'=>(8+$i),
						'place'=>'2-'.$i,
						'status'=>'B',
						'bg'=>'DDD',
						'color'=>'000000',
						);
					}
					foreach ($modems AS $key => $status)
					{
                                                $ar=statusAtr($dev,$status[1]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$place=$n.'-'.$status[0];
						$places[]="'".$place."'";
						$table[($n-1)*8+$status[0]]=array(
						'num'=>((($n-1)*8)+$status[0]),
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if (count($places))
					{
						$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
						LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
						WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';
						if ($result = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$ids[$row['place']]=$row['id'];
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$row['operator_name'];
								$names[$row['place']]=$row['title'];
								if ($row['number'])
								{
									$numb[]='"'.$row['number'].'"';
								}
								else
								{
									$numb[]='"'.$row['place'].'"';
								}
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}

				else if ($row['model']=='SR-Organizer-Smart')
				{
					$dt=unserialize($row['data']);
					$n=1;

					for ($i=1;$i<9;$i++)
					{
						$table[$i]=array(
						'num'=>$i,
						'place'=>'A'.$i,
						'status'=>'B',
						'bg'=>'DDD',
						'color'=>'000000',
						);
					}
					for ($i=1;$i<9;$i++)
					{
						$table[8+$i]=array(
						'num'=>(8+$i),
						'place'=>'B'.$i,
						'status'=>'B',
						'bg'=>'DDD',
						'color'=>'000000',
						);
					}
					if ($dt['lines']==3)
					{
						for ($i=1;$i<9;$i++)
						{
							$table[16+$i]=array(
							'num'=>(16+$i),
							'place'=>'C'.$i,
							'status'=>'B',
							'bg'=>'DDD',
							'color'=>'000000',
							);
						}
					}	
					$m=array(-10,-10,-10);
					$c=array();
					if ($result_smart = mysqli_query($db, 'SELECT * FROM `devices_state` WHERE `device_id`='.$dev)) 
					{
						while ($row_smart = mysqli_fetch_assoc($result_smart))
						{
							$d=unserialize($row_smart['data']);
							if ($row_smart['dev']=='modem1'){$m[0]=$row_smart['result'];$c[0]=$d->card;}
							if ($row_smart['dev']=='modem2'){$m[1]=$row_smart['result'];$c[1]=$d->card;}
							if ($row_smart['dev']=='modem3'){$m[2]=$row_smart['result'];$c[2]=$d->card;}
						}
					}
					if ($m[0]!=-10)
					{
                                                $ar=statusAtr($dev,$m[0]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$place='A'.$c[0];
						$places[]="'".$place."'";
						$table[($n-1)*8+$c[0]]=array(
						'num'=>((($n-1)*8)+$c[0]),
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if ($dt['modems']==3)
					{
						if ($m[1]!=-10)
						{
                	                                $ar=statusAtr($dev,$m[1]);
						        $color=$ar[0];
							$bg=$ar[1];
							$st=$ar[2];
							$place='B'.$c[1];
							$places[]="'".$place."'";
							$table[($n-1)*8+$c[1]]=array(
							'num'=>((($n-1)*8)+$c[1]),
							'place'=>$place,
							'status'=>$st,
							'bg'=>$bg,
							'color'=>$color,
							);
							$n++;
						}
					}
					if ($dt['modems']==3)
					{
						if ($m[2]!=-10)
						{
                	                                $ar=statusAtr($dev,$m[2]);
						        $color=$ar[0];
							$bg=$ar[1];
							$st=$ar[2];
							$place='C'.$c[2];
							$places[]="'".$place."'";
							$table[($n-1)*8+$c[2]]=array(
							'num'=>((($n-1)*8)+$c[2]),
							'place'=>$place,
							'status'=>$st,
							'bg'=>$bg,
							'color'=>$color,
							);
							$n++;
						}
					}
					if (count($places))
					{
						$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
						LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
						WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';

						if ($result = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$ids[$row['place']]=$row['id'];
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$row['operator_name'];
								$names[$row['place']]=$row['title'];
								if ($row['number'])
								{
									$numb[]='"'.$row['number'].'"';
								}
								else
								{
									$numb[]='"'.$row['place'].'"';
								}
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}
				else if ($row['model']=='SR-Box-Bank')
				{
					$n=1;
					$d=unserialize($row['data']);
					if (!$d['map'] || (strlen($d['map'])==1 && $d['map']==1))
					{
						for ($k=0;$k<8;$k++)
						{
							for ($i=1;$i<9;$i++)
							{
								if ($i==8){$subsection=1;} else {$subsection='';}
								$table[($k*8)+$i]=array(
								'num'=>($k*8)+$i,
								'place'=>chr(64+$i).($k+1),
								'subsection'=>$subsection,
								'status'=>'B',
								'bg'=>'DDD',
								'color'=>'000000',
								);
							}
						}
					}
					else 
					{
						for ($j=0;$j<8;$j++)
						{
							if ($d['map'][$j])
							{
								if ($row['model']!='SR-Board')
								{
									$section=($j+1).' банк';
								}
								for ($k=$j*8;$k<$j*8+8;$k++)
								{
									for ($i=1;$i<9;$i++)
									{
										if ($i==8){$subsection=1;} else {$subsection='';}
										if ($k-$j*8!=$k)
										{
											$explane='<span class="explane">'.($k-$j*8+1).'</span>';
										}
										else
										{
											$explane='';
										}
										$table[($k*8)+$i]=array(
										'num'=>($k*8)+$i,
										'place'=>chr(64+$i).($k+1),
										'explane'=>$explane,
										'status'=>'B',
										'section'=>$section,
										'subsection'=>$subsection,
										'bg'=>'DDD',
										'color'=>'000000',
										);
										$section='';
									}
								}
							}
						}
					}

					$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
					LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
					WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';

					if ($result2 = mysqli_query($db, $qry)) 
					{
						$no=1;
						while ($row2 = mysqli_fetch_assoc($result2))
						{
							$no=0;
							if ($row2['number']!=$row2['place'])
							{
								$ids[$row2['place']]=$row2['id'];
								$numbers[$row2['place']]=$row2['number'];
							}
							$operators[$row2['place']]=$row2['operator_name'];
							$names[$row2['place']]=$row2['title'];
							if ($row2['number'])
							{
								$numb[]='"'.$row2['number'].'"';
							}
							else
							{
								$numb[]='"'.$row2['place'].'"';
							}
						}
						if ($no)
						{
							$numb=1;
						}
					}
					foreach ($modems AS $key => $status)
					{
						$place=chr(64+$n).($status[0]);
						$places[]="'".$place."'";
						if ($n==8){$subsection=1;} else {$subsection='';}
						if ($row['incoming'] && $numbers[$place]==$row['incoming']){$incoming=1;$callOnly=0;} 
						elseif (!$row['incoming']){$incoming='';$callOnly=0;}
						else {$incoming='';$callOnly=1;}
                                                $ar=statusAtr($dev,$status[1],$callOnly);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$table[($status[0]-1)*8+$n]=array(
						'num'=>(($status[0]-1)*8+$n),
						'section'=>$table[($status[0]-1)*8+$n]['section'],
						'subsection'=>$subsection,
						'incoming'=>$incoming,
						'explane'=>$table[($status[0]-1)*8+$n]['explane'],
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if (!count($places))
					{
						$numb='';
					}
				}
				else if ($row['model']=='SR-Board')
				{
					$n=1;
					$d=unserialize($row['data']);

					for ($j=0;$j<8;$j++)
					{
						if (!$hide){$section='<a name="modem'.chr(65+$j).'"></a>Модем '.chr(65+$j).' &nbsp; ('.(($j*4)+1).'-'.(($j*4)+4).') &nbsp;&nbsp; <a href="#modems">↑</a>';}
						for ($k=1;$k<65;$k++)
						{
							$nn=($k%16);
							if (!$nn){$nn=16;}
							if ($nn<10){$nn="0".$nn;}
							$nn=ceil($k/16).'-'.$nn;
							if ($k==16 || $k==32 || $k==48){$subsection=1;} else {$subsection='';}
							$table[$j*64+$k]=array(
							'num'=>$nn,
							'place'=>chr(65+$j).$k,
							'status'=>'B',
							'section'=>$section,
							'subsection'=>$subsection,
							'bg'=>'DDD',
							'color'=>'000000',
							);
							$section='';
						}
					}

					$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
					LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
					WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';

					if ($result2 = mysqli_query($db, $qry)) 
					{
						$no=1;
						while ($row2 = mysqli_fetch_assoc($result2))
						{
							$no=0;
							if ($row2['number']!=$row2['place'])
							{
								$ids[$row2['place']]=$row2['id'];
								$numbers[$row2['place']]=$row2['number'];
							}
							$operators[$row2['place']]=$row2['operator_name'];
							$names[$row2['place']]=$row2['title'];
							if ($row2['number'])
							{
								$numb[]='"'.$row2['number'].'"';
							}
							else
							{
								$numb[]='"'.$row2['place'].'"';
							}
						}
						if ($no)
						{
							$numb=1;
						}
					}
					foreach ($modems AS $key => $status)
					{
						$place=chr(64+$n).($status[0]);
						$places[]="'".$place."'";
						if ($n==64){$subsection=1;} else {$subsection='';}
						if ($row['incoming'] && $numbers[$place]==$row['incoming']){$incoming=1;$callOnly=0;} 
						elseif (!$row['incoming']){$incoming='';$callOnly=0;}
						else {$incoming='';$callOnly=1;}
                                                $ar=statusAtr($dev,$status[1],$callOnly);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						if (!$hide)
						{
        						$nn=($n%16);
							if (!$nn){$nn=16;}
							if ($nn<10){$nn="0".$nn;}
							$nn=ceil($n/16).'-'.$nn;
						}
						else
						{
							$nn=$n;
						}
							
						$table[($n-1)*64+$status[0]]=array(
						'num'=>($nn),
						'section'=>$table[($n-1)*64+$status[0]]['section'],
						'subsection'=>$subsection,
						'incoming'=>$incoming,
						'explane'=>$table[($status[0]-1)*8+$n]['explane'],
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if (!count($places))
					{
						$numb='';
					}
				}
				else if ($row['model']=='SR-Box-8-Smart')
				{
					$dt=unserialize($row['data']);
					$n=1;

					for ($k=0;$k<8;$k++)
					{
						for ($i=1;$i<9;$i++)
						{
							$table[$i+(8*$k)]=array(
							'num'=>$i+(8*$k),
							'place'=>chr(65+$k).$i,
							'status'=>'B',
							'bg'=>'DDD',
							'color'=>'000000',
							);
						}
					}
					$m=array(-10,-10,-10,-10,-10,-10,-10,-10);
					$c=array();
					if ($result_smart = mysqli_query($db, 'SELECT * FROM `devices_state` WHERE `device_id`='.$dev)) 
					{
						while ($row_smart = mysqli_fetch_assoc($result_smart))
						{
							$d=unserialize($row_smart['data']);
							if ($row_smart['dev']=='modem1'){$m[0]=$row_smart['result'];$c[0]=$d->card;}
							if ($row_smart['dev']=='modem2'){$m[1]=$row_smart['result'];$c[1]=$d->card;}
							if ($row_smart['dev']=='modem3'){$m[2]=$row_smart['result'];$c[2]=$d->card;}
							if ($row_smart['dev']=='modem4'){$m[3]=$row_smart['result'];$c[3]=$d->card;}
							if ($row_smart['dev']=='modem5'){$m[4]=$row_smart['result'];$c[4]=$d->card;}
							if ($row_smart['dev']=='modem6'){$m[5]=$row_smart['result'];$c[5]=$d->card;}
							if ($row_smart['dev']=='modem7'){$m[6]=$row_smart['result'];$c[6]=$d->card;}
							if ($row_smart['dev']=='modem8'){$m[7]=$row_smart['result'];$c[7]=$d->card;}
						}
					}
					for ($k=0;$k<8;$k++)
					{
						if ($m[0]!=-10)
						{
                	                                $ar=statusAtr($dev,$m[$k]);
						        $color=$ar[0];
							$bg=$ar[1];
							$st=$ar[2];
							$place=chr(65+$k).$c[$k];
							$places[]="'".$place."'";
							$table[($n-1)*8+$c[$k]]=array(
							'num'=>((($n-1)*8)+$c[$k]),
							'place'=>$place,
							'status'=>$st,
							'bg'=>$bg,
							'color'=>$color,
							);
							$n++;
						}
					}
/*
					if ($m[1]!=-10)
					{
               	                                $ar=statusAtr($dev,$m[1]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$place='B'.$c[1];
						$places[]="'".$place."'";
						$table[($n-1)*8+$c[1]]=array(
						'num'=>((($n-1)*8)+$c[1]),
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
					if ($m[2]!=-10)
					{
               	                                $ar=statusAtr($dev,$m[2]);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];
						$place='C'.$c[2];
						$places[]="'".$place."'";
						$table[($n-1)*8+$c[2]]=array(
						'num'=>((($n-1)*8)+$c[2]),
						'place'=>$place,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
						$n++;
					}
*/
					if (count($places))
					{
						$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
						LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
						WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';
						if ($result = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row = mysqli_fetch_assoc($result))
							{
								$no=0;
								$ids[$row['place']]=$row['id'];
								$numbers[$row['place']]=$row['number'];
								$operators[$row['place']]=$row['operator_name'];
								$names[$row['place']]=$row['title'];
								if ($row['number'])
								{
									$numb[]='"'.$row['number'].'"';
								}
								else
								{
									$numb[]='"'.$row['place'].'"';
								}
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
				}
				else if ($row['model']=='SR-Box-2-Bank')
				{
					$n=1;
					$d=unserialize($row['data']);
					if (!$d['map'] || (strlen($d['map'])==1 && $d['map']==1))
					{
						for ($k=0;$k<8;$k++)
						{
							for ($i=1;$i<9;$i++)
							{
								if ($i==8){$subsection=1;} else {$subsection='';}
								$table[($k*8)+$i]=array(
								'num'=>($k*8)+$i,
								'place'=>chr(64+$i).($k+1),
								'subsection'=>$subsection,
								'status'=>'B',
								'bg'=>'DDD',
								'color'=>'000000',
								);
							}
						}
					}
					else 
					{
						for ($j=0;$j<8;$j++)
						{
							if ($d['map'][$j])
							{
								$section=($j+1).' банк';
								for ($k=$j*8;$k<$j*8+8;$k++)
								{
									for ($i=1;$i<9;$i++)
									{
										if ($i==8){$subsection=1;} else {$subsection='';}
										if ($k-$j*8!=$k)
										{
											$explane='<span class="explane">'.($k-$j*8+1).'</span>';
										}
										else
										{
											$explane='';
										}
										$table[($k*8)+$i]=array(
										'num'=>($k*8)+$i,
										'place'=>chr(64+$i).($k+1),
										'explane'=>$explane,
										'status'=>'B',
										'section'=>$section,
										'subsection'=>$subsection,
										'bg'=>'DDD',
										'color'=>'000000',
										);
										$section='';
									}
								}
							}
						}
					}

					$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
					LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
					WHERE c.`device`='.$row['device'].' ORDER BY c.`place`';

					if ($result2 = mysqli_query($db, $qry)) 
					{
						$no=1;
						while ($row2 = mysqli_fetch_assoc($result2))
						{
							$no=0;
							if ($row2['number']!=$row2['place'])
							{
								$ids[$row2['place']]=$row2['id'];
								$numbers[$row2['place']]=$row2['number'];
							}
							$operators[$row2['place']]=$row2['operator_name'];
							$names[$row2['place']]=$row2['title'];
							if ($row2['number'])
							{
								$numb[]='"'.$row2['number'].'"';
							}
							else
							{
								$numb[]='"'.$row2['place'].'"';
							}
						}
						if ($no)
						{
							$numb=1;
						}
					}
					foreach ($modems AS $key => $status)
					{
						$place=chr(64+$n).($status[0]);
						$places[]="'".$place."'";
						if ($n==8){$subsection=1;} else {$subsection='';}
						if ($row['incoming'] && $numbers[$place]==$row['incoming']){$incoming=1;$callOnly=0;} 
						elseif (!$row['incoming']){$incoming='';$callOnly=0;}
						else {$incoming='';$callOnly=1;}
						if ($status[1]>-4)
						{
	                                                $ar=statusAtr($dev,$status[1],$callOnly);
						        $color=$ar[0];
							$bg=$ar[1];
							$st=$ar[2];
							$table[($status[0]-1)*8+$n]=array(
							'num'=>(($status[0]-1)*8+$n),
							'section'=>$table[($status[0]-1)*8+$n]['section'],
							'subsection'=>$subsection,
							'incoming'=>$incoming,
							'explane'=>$table[($status[0]-1)*8+$n]['explane'],
							'place'=>$place,
							'status'=>$st,
							'bg'=>$bg,
							'color'=>$color,
							);
						}
						$n++;
					}
					if (!count($places))
					{
						$numb='';
					}
				}
				else if ($row['model']=='SR-Box-8')
				{
					foreach ($modems AS $key => $status)
					{
						$places[]="'".chr($key+64)."'";
					}
					if (count($places))
					{
						$qry='SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
						LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
						WHERE c.`device`='.$row['device'].' AND c.`place` IN ('.implode(',',$places).') ORDER BY c.`place`';
						if ($result2 = mysqli_query($db, $qry)) 
						{
							$no=1;
							while ($row2 = mysqli_fetch_assoc($result2))
							{
								$no=0;
								if ($row2['number']!=$row2['place'])
								{
									$ids[$row2['place']]=$row2['id'];
									$numbers[$row2['place']]=$row2['number'];
								}
								$operators[$row2['place']]=$row2['operator_name'];
								$names[$row2['place']]=$row2['title'];
								if ($row2['number'])
								{
									$numb[]='"'.$row2['number'].'"';
								}
								else
								{
									$numb[]='"'.$row2['place'].'"';
								}
							}
							if ($no)
							{
								$numb=1;
							}
						}
					}
					$places=array();
					foreach ($modems AS $key => $status)
					{
						$curRow=$status[0];
						$realPlace=$place=chr($key+64);
						$places[]="'".$place."'";
						if ($row['incoming'] && $numbers[$place]==$row['incoming']){$incoming=1;$callOnly=0;} 
						elseif (!$row['incoming']){$incoming='';$callOnly=0;}
						else {$incoming='';$callOnly=1;}
                                                $ar=statusAtr($dev,$status[1],$callOnly);
					        $color=$ar[0];
						$bg=$ar[1];
						$st=$ar[2];

						$table[$key]=array(
						'num'=>$key,
						'place'=>$place,
						'incoming'=>$incoming,
						'status'=>$st,
						'bg'=>$bg,
						'color'=>$color,
						);
					}
				}
				else
				{
                                        $ar=statusAtr($dev,$modems[1]);
				        $color=$ar[0];
					$bg=$ar[1];
					$st=$ar[2];
					$table[0]=array(

					'num'=>1,
					'place'=>$modems[0],
					'status'=>$st,
					'bg'=>$bg,
					'color'=>$color,
					);

					if ($result = mysqli_query($db, 'SELECT c.*, o1.`title` AS `operator_name` FROM `cards` c 
					LEFT JOIN `operators` o1 ON o1.`name` LIKE CONCAT("%;",c.`operator`,";%") 
					WHERE c.`device`='.$row['device']." AND c.`place`='".$modems[0]."'")) 
					{
						if ($row = mysqli_fetch_assoc($result))
						{
							$ids[$row['place']]=$row['id'];
							$numbers[$row['place']]=$row['number'];
							$operators[$row['place']]=$row['operator_name'];
							$names[$row['place']]=$row['title'];
							if ($row['number'])
							{
								$numb[]='"'.$row['number'].'"';
							}
							else
							{
								$numb[]='"'.$row['place'].'"';
							}
						}
						else
						{
							$numb[]='';
						}
					}
					$curRow=$modems[0];
				}
			}
		}
		if (count($table))
		{
			$ss='';
			if ($row['model']=='SR-Board' && !$hide)
			{
				$ss.='<a name="modems"></a>&nbsp;&nbsp;Модемы: <a href="#modemA">A</a> <a href="#modemB">B</a> <a href="#modemC">C</a> <a href="#modemD">D</a> <a href="#modemE">E</a> <a href="#modemF">F</a> <a href="#modemG">G</a> <a href="#modemH">H</a>';
			}
			$ss.='
<table class="table table_small">
<tr>
	<th class="sidebar">№</th>
	<th class="sidebar">Имя</th>
	<th style="text-align:center; width: ';
	if ($row['model']=='SR-Board'){$ss.='80';} else {$ss.='60';} 
	$ss.='px;">Место</th>
	<th>Номер</th>
	<th class="sidebar">Оператор</th>
	<th style="text-align:center;width: 135px;">Статус</th>
</tr>';         
			$n=0;
			foreach ($table as $key=>$data)
			{
				$s='';				
				if (!$numbers[$data['place']]){$nt=$numbers[$data['place']]='—';} else 
				{
					$nt='';
					$prefix='+'.substr($numbers[$data['place']],0,1);
					$num=substr($numbers[$data['place']],1,255);
					$numbers[$data['place']]='<span class="note2 light" onclick="copy(\''.$numbers[$data['place']].'\');soundClick();">'.$prefix.'</span><span class="note2" onclick="copy(\''.$num.'\');soundClick();">'.$num.'</span>';
				}

				if ($data['section']){$s.='<tr><td colspan="6" class="section">'.$data['section'].'</td></tr>';}

				if ($data['subsection'] && $data['incoming'] && !$hide){$class=' incoming subsection';} 
				elseif ($data['incoming']){$class=' incoming';} 
				elseif ($data['subsection'] && !$hide){$class=' subsection';} else {$class='';}

				if (($data['status']!='—' && $data['status']!='B') || !$hide)
				{
					$s.='<tr class="rowhide'.$class.'">';
					$s.='<td class="sidebar" align="right">'.$data['num'].'</td>
	<td class="sidebar">'.($names[$data['place']]?$names[$data['place']]:'—').'</td>
	<td align="center">';

					$a=$data['place'];
					$ad=$marker=(ord($a[0])-64)*4-3;
					$a=substr($a,1,255);
					$as=$a%16;
					$ab=ceil($a/16);
					if (!$as){$as=16;}
					$marker+=$ab-1;
					$marker.=','.$as;


					if ($data['status']=='—')
					{
						$s.=$data['place'].'</td>';
					}
					elseif ($data['status']=='B')
					{
						$data['status']='—';
						$s.='<span onclick="onlineCreateCom('.$dev.',\'place:'.$data['place'].'\');soundClick();" class="but_nowin">'.$data['place'].'</span>';
						if ($row['model']=='SR-Board' && !$GLOBALS['sv_staff_id']){$s.=' <a href="javascript:void();" onclick="eject('.$dev.',\'marker:'.$marker.'\');"><i class="icon-eject"></i></a>';}
					}
					else
					{
						$nn=$nc=strip_tags($numbers[$data['place']]);
						if ($nn=='—')
						{		
							$nn=$data['place'];
							$nc='картой '.$data['place'];
						}
						else
						{
							$nc='номером '.$nc;
						}
						if ($key>8 && ord($data['place'][0])>57)
						{
							$key=$key%8;
							if ($key==0){$key=8;}
						}
						if ($row['model']=='SR-Board'){$key=ord($data['place'][0])-64;}
						$s.='<span onclick="winOpen(this)" class="but_win sel" data-id="win_action" data-title="Управление '.$nc.'" data-type="ajax_online_card_action.php?cardId='.strip_tags($ids[$data['place']]).'&number='.urlencode($nn).'&inc='.(int)$data['incoming'].'&dev='.$dev.'&modem='.$key.'" data-height="400" data-width="600">'.$data['place'].'</span>';
						if ($row['model']=='SR-Board' && !$GLOBALS['sv_staff_id']){$s.=' <a href="javascript:void();" onclick="eject('.$dev.',\'marker:'.$marker.'\');"><i class="icon-eject"></i></a>';}
					}

					$s.=$data['explane'].'</td><td>'.$numbers[$data['place']].'<br><div class="legend extinfo">'.$names[$data['place']].'</div></td>
	<td class="sidebar" align="center">'.($operators[$data['place']]?$operators[$data['place']]:'—').'</td>
	<td class="onlineStatus" id="status_'.$data['num'].'"';
	if ($data['color']){$s.=' style="color: #'.$data['color'].';background:#'.$data['bg'].'"';} 
	$s.=' align="center">'.$data['status'].'</td>
</tr>';

					if (!$nt || !$sv_staff_id){$ss.=$s;}
				}
			}
$ss.='</table>';
			return(array($ss,$numb,$curRow,count($table)));
		}
	}
}

// Output of received SMS
// Вывод полученных SMS
function onlineView($numb)
{
//	$numb		Array with phone numbers to receive SMS for

	global $db;

	$qry='SELECT s.*,c.place,c.title AS `ctitle` FROM `sms_incoming` s 
	INNER JOIN `cards` c ON (c.`place` IN ('.implode(',',$numb).') OR c.`number` IN ('.implode(',',$numb).')) AND (c.id=s.card_id OR c.number=s.number)
	WHERE s.`done`=1 
	ORDER BY s.`id` DESC LIMIT 10';
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($row['number'])
			{
				$number='+'.$row['number'].'<div class="answer_head">'.trim($row['ctitle'].' '.$row['place']).'</div>';
			}
			else
			{
				$number=trim($row['ctitle'].' '.$row['place'].'');
			}
			$txt=sms_out($row['txt']);
			$time=$row['time'];
			$sender=$row['sender'];
			if (!$id){$id=$row['id']+1;}
			$s.='<div class="term_answer_item"><div class="answer_left answer_head" style="width: 120px;">'.srdate('H:i:s d.m',$time).'</div><div class="answer_head">'.$sender.'</div><div class="answer_left answer_fix">'.$number.'</div><div class="list_txt">'.$txt.'</div></div>';
		}
	}
	return(array($s,$id));
}

// Processing SMS messages before saving them to the database
// Обработка SMS перед сохранением в БД
function sms_prep($txt)
{
	$txt=preg_replace('!\+CMTI: ".*",{d}!Us','',$txt);
	$txt=str_replace("\n",'',$txt);
	$txt=str_replace("\r",'',$txt);
	$txt=str_replace('{space}',' ',$txt);
	$txt=str_replace('FEFF',' ',$txt);
	$txt=str_replace('00AB','«',$txt);
	$txt=str_replace('00BB','»',$txt);
	$txt=str_replace(' 2013 ',' – ',$txt);
	return($txt);
}

// Deleting leading zeros in the SR-Nano disk space designation
// Удаление лидирующих нулей в обозначение места на диске SR-Nano
function remove_zero($place)
{
//	$place 		Place on SR-Nano
	$l=$place[0];
	if (ord($l)<58)
	{
		return($place);
	}
	return($l.(int)substr($place,1,3));
}

// Deleting old flags
// Удаление старых флагов
function clear_flags($time=86400)
{
//	$time		Delete files created earlier X seconds ago

	global $root;
	$dir=$root.'flags';
	if($OpenDir=opendir($dir))
	{
		while(($file=readdir($OpenDir))!==false)
		{
			if ($file != "." && $file != "..")
			{
				$defTime=intval(time()-filectime("{$dir}/{$file}"));
				if ($defTime>$time)
				{
					 unlink("{$dir}/{$file}");
			        }
			}
		}
	}
	closedir($OpenDir); 
}                                                   

// Autoformazione form fields
// Автоформирование полей формы
function auto_field($title,$name,$type,$desc='',$data='',$comment='')
{
//	$name 		Field name
//	$type		Field type
//	$data		Field data
	$str= '<div style="margin-bottom: 7px;"><span title="'.$desc.'">'.$title.'</span>';
	if ((int)$type)
	{
		$str.= '<input type="text" id="'.$name.'" name="'.$name.'" maxlength="'.$type.'" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='minitxt')
	{
		$str.= '<textarea name="'.$name.'" id="'.$name.'" class="form-control" maxlength="1000" style="height:70px;">'.$data.'</textarea>';
	}
	elseif ($type=='txt')
	{
		$str.= '<textarea name="'.$name.'" id="'.$name.'" class="form-control" maxlength="100000" style="height:200px;">'.$data.'</textarea>';
	}
	elseif ($type=='url')
	{
		$str.= '<input type="text" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='email')
	{
		$str.= '<input type="email" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='digit')
	{
		$str.= '<input type="number" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='number')
	{
		$str.= '<input type="number" id="'.$name.'" name="'.$name.'" maxlength="100" value="'.$data.'" class="form-control input-xlarge"/>';
	}
	elseif ($type=='radio')
	{
		if ($data){$c=' checked';$d='';} else {$d=' checked';$c='';}
		$str.= 'On <input type="radio" name="'.$name.'" id="'.$name.'" value="1"'.$c.'>&nbsp;&nbsp;&nbsp;';
		$str.= 'Off <input type="radio" name="'.$name.'" id="'.$name.'" value="0"'.$d.'>';
	}
	elseif ($type=='check')
	{
		$str.= '<input type="hidden" value="1" name="'.$name.'"><input type="checkbox" id="'.$name.'" name="'.$name.'_check" class="make-switch" value="2" data-on-color="success" data-off-color="danger"';
		if ($data==2){$str.= 'checked';}
		$str.= '>';
	}
	$str.= '</div>';
	if ($comment)
	{
		$str.= '<div class="help_block">'.$comment.'</div>';
	}
	return($str);
}

// Checking the emergency exit flag
// Проверка флага аварийного выхода
function br($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	if (flagGet($dev,'stop'))
	{
		setlog('[DEVICE:'.$dev.'] Emergency exit!');
		flagSet($dev,'stop',2);
		flagDelete($dev,'cron');
		exit();
	}

	if (flagGet($dev,$file))
	{
		setlog('[DEVICE:'.$dev.'] ['.$file.'] exit!');
		flagDelete($dev,$file);
		flagDelete($dev,'stop');
		flagDelete($dev,'cron');
		exit();
	}
}

// Check for the flag
// Проверка наличия флага
function ts($dev,$file='stop')
{
//	$dev		Device ID
//	$file		Filename

	setlog('Вызов отмененной функции ts','error');
}

// Preparing the balance
// Форматирование баланса
function balance_out($balance,$sign='')
{
//	$balance	Balance
//	$sign		Sign before the number
	if ($balance<0){$b='-';} else {$b=$sign;}
	$balance=str_replace('.',',',$balance);
	$balance=str_replace('-','',$balance);
	$cent=explode(',',$balance);
	$cent[1]=substr($cent[1].'00',0,2);
	return($b.str_replace(',',"'",number_format($balance)).'.'.$cent[1]);
}


// Preparing the number
// Форматирование числа
function num_out($num) // Вывод числа
{
//	$num		Number

	$a=explode(',',round($num,2));
	$b=str_replace(',',"'",number_format($a[0])).'.'.$a[1];
	return(rtrim($b,'.'));
}

// Writing to a log file
// Запись в лог файл
function setlog($data,$file='sr')
{
//	$data		Text string
//	$file		Filename

	global $root;
	if (!$GLOBALS['set_data']['log_size']){return;}
	if ($GLOBALS['set_data']['log_size']>-1 && filesize($root.'logs/'.$file.'.log')>$GLOBALS['set_data']['log_size']*2*1024)
	{
		$txt=explode("\n",file_get_contents($root.'logs/'.$file.'.log'));
		for ($i=count($txt);$i>0;$i--)
		{
			if (trim($txt[$i]))
			{
				$t=$txt[$i]."\n".$t;
			}
			if (strlen($t)>$GLOBALS['set_data']['log_size']*1024){break;}
		}
		file_put_contents($root.'logs/'.$file.'.log',$t);
	}
	$f=fopen($root.'logs/'.$file.'.log', "a"); 
	fwrite($f,date('H:i:s d.m.Y').' '.$data."\n");
	fclose($f);
}

function setlog_full($data,$file='sr')
{
//	$data		Text string
//	$file		Filename

	global $root;
	$f=fopen($root.'logs/'.$file.'.log', "a"); 
	fwrite($f,date('H:i:s d.m.Y').' '.$data."\n");
	fclose($f);
}

function time_calc($time)
{
	$m=round($time/60);
	if ($m==0)
	{
		return('∞');
	}
	$h=0;
	while ($m>59)
	{
		$h++;
		$m-=60;
	}
	while ($h>23)
	{
		$d++;
		$h-=24;
	}
	if (!$m){$m='';} else {$m.='м';}
	if ($d)
	{
		return($d.'д '.$h.'ч '.$m);
	}
	else if ($h)
	{
		return($h.'ч '.$m);
	}
	return($m);
}

function flagSet($dev,$name,$value=1)
{
//	$dev		Device ID

	global $db;

	mysqli_query($db, 'REPLACE INTO `flags` SET `name`="'.$name.'", `device`='.(int)$dev.', `value`="'.$value.'", `time`='.time());
}

function flagGet($dev,$name,$time=0)
{
//	$dev		Device ID

	global $db;

	if ($result = mysqli_query($db, 'SELECT `time`,`value` FROM `flags` WHERE `device`='.(int)$dev.' AND `name`="'.$name.'"')) 
	{
		if ($row = mysqli_fetch_assoc($result))
		{
			if ($time)
			{
				return($row['time']);
			}
			else
			{
				return($row['value']);
			}
		}
	}
}

function flagDelete($dev,$name)
{
//	$dev		Device ID

	global $db;

	mysqli_query($db, 'DELETE FROM `flags` WHERE `name`="'.$name.'" AND `device`='.(int)$dev);
}

function srtime($format='',$time=0)
{
	if ($format[0]=='d'){$format='d.m.Y';}
	elseif ($format[0]=='t'){$format='H:i';}
	elseif ($format[0]=='s'){$format='H:i:s';}
	elseif ($format[0]=='a'){$format='d.m.Y H:i:s';}
	else {$format='d.m.Y H:i';}
	return(srdate($format,$time));
}

function srdate($format,$time=0)
{
        if (!$time){$time=time();}
	if ($GLOBALS['sv_timezone']!=255)
	{
		$time=$time+3600*($GLOBALS['sv_timezone']-3);
	}
	return(date($format,$time));
}

function operator($operator)
{
	$operator=explode(' ',strtoupper($operator));
	return($operator[0]);	
}

function operator_select()
{
	global $db;
	$operators=array();

	$qry='SELECT * FROM `operators` ORDER BY CHAR_LENGTH(`name`) DESC';
	if ($result = mysqli_query($db, $qry)) 
	{
		$name='';
		while ($row = mysqli_fetch_assoc($result))
		{
			if ($name!=$row['name'])
			{
				$operators[]=$row['name'];
			}
			$name=$row['name'];
		}
	}
	if ($result = mysqli_query($db, 'SELECT `id`,`operator` FROM `cards`')) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			foreach ($operators AS $name)
			{
				if (strpos($row['operator'],$name)!==false)
				{ 			
					$qry="UPDATE `cards` SET `operator`='".$row['operator']."' WHERE `id`=".$row['id'];
					mysqli_query($db,$qry);
				}
			}			
		}
	}
}

function trim_number($number)
{
	$number=str_replace('-','',$number);
	$number=str_replace('(','',$number);
	$number=str_replace(')','',$number);
	$number=str_replace(' ','',$number);
	return($number);
}

function trim_balance($balance)
{
	setlog($balance,'balance');
        preg_match('!(инус|inus)!i', $balance, $minus);
	$minus=$minus[0];
        preg_match('!([0-9]{1,5})([\.|\,])*([0-9]{1,2})*!', $balance, $test);
	$a=strpos($balance,$test[1]);
	if (strpos($balance,'-')!==false)
	{
		if (strpos($balance,'-')<$a)
		{
			$minus=1;
		} 
	}
	$balance=str_replace(',','.',trim(trim($test[1].$test[2].$test[3],'.')));
	if ($minus){$balance=$balance*-1;}
	return($balance);
}

function get_values($user_id=0)
{
	global $db;
	$qry="SELECT * FROM `values`";
	$result = mysqli_query($db,$qry);
	while ($row = mysqli_fetch_array($result))
	{
		$GLOBALS['set_data'][$row['name']]=$row['value'];
	}
}

function nullto0($txt)
{
	if ($txt=='NULL'){$txt=0;}
	return($txt);
}

// Отправка писем пользователям
function email2user($email,$title,$text,$name='',$action='',$greeting='',$photos='',$file='', $email_from='') 
{
	$txt=$text;
	$mtemp=mail_template_out($title,$text);

	if (!$greeting && $name){$greeting='Здравствуйте, '.$name.'.';}
	elseif (!$greeting){$greeting='Здравствуйте';}

	if (!$action){$action=$title;}

	$headers  = "Content-type: text/html; charset=UTF-8 \r\n"; 
	$headers .= "From: ".$GLOBALS['set_data']['sitemail']."\r\n"; 
	$mtemp['body']=str_replace('{title}',$action,$mtemp['body']);
	$mtemp['body']=str_replace('{greeting}',$greeting,$mtemp['body']);
	$mtemp['body']=str_replace('{content}',$text,$mtemp['body']);

	$photos_preview='';

	if ($photos)
	{
		$str='
				<tr>
					<td style="padding: 0 30px 30px 30px;">
			                	<table border="0" cellspacing="0" cellpadding="0">
							<tbody>
								<tr>
';
		$a=explode(';',$photos);
		for ($i=0;$i<count($a);$i++)
		{
			$photos_preview.=tri_image_output($a[$i],0,150,300,300,'','',1).';';
			$str.='
									<td align="left">
					                  			<table border="0" cellspacing="0" cellpadding="0">
											<tbody>
												<tr>
													<td>
														<img style="color: rgb(255, 255, 255); font-size: 36px; font-weight: bold;" src="cid:pic'.($i+1).'" border="0" />
													</td>
							                                        </tr>
											</tbody>
										</table>
									</td>
									<td width="16"></td>

';

		}
		$str.='
									<td width="96" height="96" align="center" style=\'color: rgb(159,177,187); line-height: 96px; font-family: "Trebuchet MS","Helvetica CY","DejaVu Sans",serif; font-size: 36px;\' bgcolor="#e9f0f4">
				                        	        	...
                                					</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
';
	}

	$mtemp['body']=str_replace('{photos}',$str,$mtemp['body']);

	$text=$mtemp['body'];
	$text=str_replace('{begin}',$mtemp["begin"],$text);
	$text=str_replace('{end}',$mtemp["end"],$text);

	$site_mail=$GLOBALS['set_data']['sysmail'];
	$site_title='SIM Roulette';
	$site_adv_image='';

	if (!$email_from)
	{
		mailer($title,$site_mail,$site_title,$text,$email,$name,'mail/logo.png;'.$photos_preview,$file,$site_adv_image);
	} 
	else 
	{
		mailer($title,$email_from,$site_title,$text,$email,$name,'mail/logo.png;'.$photos_preview,$file,$site_adv_image);
	}
}

function mail_template_out($title,$text) 
{
	$mtemp=array();
	$mtemp["body"]='<table width="100%" bgcolor="#f1f5f8" border="0" cellspacing="0" cellpadding="0" style="border: 50px solid #f1f5f8; border-bottom: 10px solid #f1f5f8;">
<tbody>
<tr>
	<td style="padding: 15px; font-size: 15px;" bgcolor="#003663">
		<a href="'.$GLOBALS['set_data']['siteurl'].'"><img src="cid:pic0" border="0" alt="'.$GLOBALS['set_data']['sitename'].'" style="margin: 8px 0 2px 18px;"></a>
	</td>
</tr>
<tr>
	<td bgcolor="#dae1e5" border="0" cellspacing="0" cellpadding="0" style="padding: 20px 30px; color: rgb(52,52,52); text-transform: uppercase; font-family: Tahoma,Geneva CY,sans-serif; font-size: 16px;">
		{title}
	</td>
</tr>
<tr>
	<td style="border-bottom-color: rgb(223,230,235); border-bottom-width: 1px; border-bottom-style: solid;">
		<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#FFFFFF">
			<tbody>
				<tr>
					<td style="padding: 20px 30px; color: rgb(52,52,52); text-transform: uppercase; font-family: Tahoma,Geneva CY,sans-serif; font-size: 19px; font-weight: bold;">
                                            {greeting}                                    
					</td>
			      	</tr>
				<tr>
					<td style="padding: 0 30px 25px 30px; color: rgb(52,52,52); font-family: Tahoma,Geneva CY,sans-serif; font-size: 15px;">
                                            {content}<br />
					</td>
			      	</tr>
				{photos}
			</tbody>
		</table>
	</td>
</tr>
<tr>
	<td bgcolor="#efe8e1" border="0" cellspacing="0" cellpadding="0" style="padding: 10px 30px; color: rgb(52,52,52); font-family: Tahoma,Geneva CY,sans-serif; font-size: 16px;">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tbody>
				<tr>
					<td width="50">
<!--                                            <div style="margin-right: 20px; padding-top: 3px;"><a href="'.$GLOBALS['set_data']['letter_advert_url'].'"><img src="cid:adv" border="0"></a></div> -->
					</td>
					<td>
                                            <em>'.$GLOBALS['set_data']['letter_advert_txt'].'</em>
					</td>
			      	</tr>
			</tbody>
		</table>
	</td>
</tr>
</tbody>
</table>
';

	$mtemp["begin"]='
<br /><br /><table align="center" border="0" width="100%" cellspacing="0" cellpadding="0" style="border: 10px solid #dae1e5;">
	<tbody>
		<tr>
			<td style="padding: 20px;">
';
	$mtemp["end"]='
			</td>
		</tr>
	</tbody>
</table><br />
';  
	return($mtemp);
}

function mailer($title,$revmail,$revname,$letter,$mail,$name,$image,$file,$adv='')
{
	global $root;
	require_once($root.'mail/class.phpmailer.php');

	$letter=str_replace(' ','
 ',$letter);

	if (strpos($mail,'generate_')!==false)
	{
		$mail=substr($mail,18,255);
	}
	$mailer = new PHPMailer();      
	$mailer->priority = 3;
	$mailer->IsHTML(true);
	$mailer->CharSet = "UTF-8";
	$mailer->IsSendmail();
	$mailer->From = $revmail;
	$mailer->FromName = $revname;
	$mailer->Sender = $mailer->From;
	$mailer->Subject = $title;
	$mailer->Body = $letter;
	$mailer->AddAddress($mail, $name);

	if ($file)
	{
		$fn=explode('/',$file);
		$mailer->AddAttachment($root.$file, $fn[count($fn)-1]);
	}

	if ($adv)
	{
		$mailer->AddEmbeddedImage($root.$adv, 'adv', '', 'base64', 'image/jpeg');
	}

	$a=explode(';',$image);
	for ($i=0;$i<count($a);$i++)
	{
		if ($a[$i])
		{
			$mailer->AddEmbeddedImage($root.$a[$i], 'pic'.$i, '', 'base64', 'image/jpeg');
		}
	}

	if(!$mailer->Send()){
		$out=false;
	} else {
		$out=true;
	}

	$mailer->ClearAddresses();
	$mailer->ClearAttachments();
	$mailer->IsHTML(true);

	unset($mailer);
		
	return($out);
}

function icon_out($model,$data,$color=1)
{
	$data=unserialize($data);
	$icon=strtolower($model);
	if (strpos($icon,'-500')!==false){$icon=str_replace('-500','',$icon);}
	if (strpos($icon,'-1000')!==false){$icon=str_replace('-1000','',$icon);}
	if (strpos($icon,'-smart')!==false){$sm=1;$icon=str_replace('-smart','',$icon);}
	if (strpos($icon,'-8')!==false){$icon=str_replace('-8','',$icon);}
	if (strpos($icon,'-2')!==false){$icon=str_replace('-2','',$icon);}
	if (strpos($icon,'organizer')!==false && $data['modems']==3){$icon.='-24-3';}
	elseif (strpos($icon,'organizer')!==false && $data['lines']==3){$icon.='-24-1';}
	elseif (strpos($icon,'organizer')!==false && ($data['lines']==1 || !$sm)){$icon.='-16-1';}
	elseif (strpos($icon,'organizer')!==false){$icon.='s';}
	if (strpos($icon,'box-bank')!==false && $data['map']!=1){$icon.='s';}
	if (!$color){$c=' class="grayscale"';}
	return($rev.'<img src="icon/'.$icon.'.svg"'.$c.'>');
}

function stat_resume($count,$percent)
{
	if ($count<100){$color='CCCCCC';$resume="Недостаточно информации...";}
	else if ($percent<5){$color='9ad840';$resume="Аппарат исправен!";}
	elseif ($percent<10){$color='bab022';$resume="Есть замечания!";}
	elseif ($percent<25){$color='e18c44';$resume="Есть небольшие проблемы!";}
	else {$color='d25743';$resume="Требуется калибровка!";}
	return(array($resume,$color));
}

function scrollbar($vsego,$pn,$lim,$value,$url="",$buffer=1,$method="get")
{
	if ($vsego<=$lim){return;}
	global $REQUEST_URI;
	$buff='
	<div class="pagination">
';
	$method=strtolower($method);
	$summa=0;
	$vse=$vsego;
	while($vsego>0)
	{
		$summa++;
		$vsego=$vsego-$lim;
	}
	if ($summa>1)
	{
		if (!$url)
		{
			$url=$_SERVER['REQUEST_URI'];
			$url=str_replace("$value=","",$url);
		}
		$a=explode('&',str_replace('?','&',$url));
		$a[]=$value;
		for ($i=0;$i<count($a);$i++)
		{
			if ((int)$a[$i]){unset($a[$i]);}
		}
		$url=$a[0].'?';
		unset($a[0]);
		$url.=implode('&',$a);
		
		if ($method=="get")
		{
			if ($pn>1)
			{
				$a=$pn-$lim; 
				$buff.='
				<a href="'.$url.'='.($pn-1).'"><</a>';
			}
			$klm=1;
			while ($klm<=$summa)
			{
				$pn2=($klm-1)*$lim;
				if ($pn==$klm)
				{
					$buff.='
				<span class="page_active">'.$klm.'</span>';
				}
				else
				{
					$buff.=' <a href="'.$url.'='.$klm.'">'.$klm.'</a>';
				}
				$klm++;
			}
			if ($pn<$klm-1)
			{
				$buff.=' <a href="'.$url.'='.($pn+1).'">></a>';
			}
		}
	}
	$buff.='
	</div>
';
	if (!$buffer){echo $buff;} else {return($buff);}
}

function pool_clear()
{
	global $db;
	$qry='SELECT cp.id FROM `card2pool` cp
	LEFT JOIN `pools` p ON p.`id`=cp.`pool`
	WHERE p.`id` IS NULL';
	if ($result = mysqli_query($db, $qry)) 
	{
		while ($row = mysqli_fetch_assoc($result))
		{
			$qry="DELETE FROM `card2pool` WHERE `id`=".$row['id'];
			mysqli_query($db,$qry);
		}
	}
}

function flag_clear()
{
	global $db;
	$qry="DELETE FROM `flags` WHERE `time`<".(time()-86400*7)." AND `name` LIKE '%act_'";
	mysqli_query($db,$qry);
}

?>