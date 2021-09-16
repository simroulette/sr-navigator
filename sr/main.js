// ===================================================================
// Sim Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

var noclose=0;
var rhide=0;
var timer=0;

$(window).on('load', function () {
    $('.preloader').addClass("preloader-remove");     
});

function menuToggle(x) 
{
	x.classList.toggle("change");
	if (document.getElementById('menu_cont').style.display=='block')
	{
		document.getElementById('menu_cont').style.display='none';	
	}
	else
	{
		document.getElementById('menu_cont').innerHTML="<a href=\"index.php\"><img src=\"sr/logo.gif\" border=\"0\" style=\"float: right; margin: -70px 20px 0 0; width: 150px;\"></a>"+document.getElementById('menu').innerHTML;	
		document.getElementById('menu_cont').classList.add("panel");	
		document.getElementById('menu_cont').style.display='block';	
	}
}

window.onresize = function(event) 
{
	if (document.getElementById('menu_cont').style.display=='block' && screen.width>760)
	{
		document.getElementById('m').classList.toggle("change");
		document.getElementById('menu_cont').style.display='none';	
	}
}

function SelectGroup(mark,fn,name) 
{ 
	for (i = 0; i < document[fn].elements.length; i++) 
	{ 
		var item = document[fn].elements[i]; 
		if (item.id == name) 
		{ 
			if (mark) 
			{
				$(item).closest('span').addClass('checked');
				$(item).attr('checked','checked');
				$(item).prop('checked','checked');
			} 
			else 
			{
				$(item).closest('span').removeClass('checked');
				$(item).removeAttr('checked');
				$(item).removeProp('checked');
			}
		}
	} 
} 

function deleteItem(v)
{
	$(v).parent().parent().html('');	
}

document.addEventListener('DOMContentLoaded', () => {

    const getSort = ({ target }) => {
        const order = (target.dataset.order = -(target.dataset.order || -1));
        const index = [...target.parentNode.cells].indexOf(target);
        const collator = new Intl.Collator(['en', 'ru'], { numeric: true });
        const comparator = (index, order) => (a, b) => order * collator.compare(
            a.children[index].innerHTML,
            b.children[index].innerHTML
        );
        
        for(const tBody of target.closest('table').tBodies)
            tBody.append(...[...tBody.rows].sort(comparator(index, order)));

        for(const cell of target.parentNode.cells)
            cell.classList.toggle('sorted', cell === target);
    };
    
    document.querySelectorAll('.table_sort thead').forEach(tableTH => tableTH.addEventListener('click', () => getSort(event)));
    
});

$(document).ready(function() 
{
	var headerHeight = $('.header').outerHeight();
	anchor = window.location.hash.substr(1); 
	if ($( window ).width()<1000 || $( window ).height()<600)
	{
		$(".dm-modal").width("100%");
		$(".dm-modal").height("100%");

	}
	$(".but_win").on('click', function(event){
		$("body").css("overflow-y","hidden");
		id='#'+$(this).attr('data-id');
		height=$(this).attr('data-height');
		width=$(this).attr('data-width');
		title=$(this).attr('data-title');
		$(".dm-overlay").find("h3").html(title);
		if (height)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-height",height);
		}
		if (width)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-width",width);
		}
		getActions($(this).attr('data-type'));
		$(".dm-overlay").show();
		$(id).show();
		win_check(1);
		$(document).mouseup(function (e){
			var div = $(".dm-modal");
			if (!noclose && ((!div.is(e.target) && div.has(e.target).length === 0)) || $(".win-close").is(e.target)) {
			win_close();			
			}
		});
		if ($(this).attr('data-comment'))
		{
			link_load(this);
		}
	});
}); 

$( window ).resize(function() {
	win_check(0);
});

function winOpen(v)
{
		$("body").css("overflow-y","hidden");
		wid='#'+$(v).attr('data-id');
		height=$(v).attr('data-height');
		width=$(v).attr('data-width');
		title=$(v).attr('data-title');
		$(".dm-overlay").find("h3").html(title);
		if (height)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-height",height);
		}
		if (width)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-width",width);
		}
		getActions($(v).attr('data-type'));
		$(".dm-overlay").show();
		$(wid).show();
		win_check(1);
		$(document).mouseup(function (e){
			var div = $(".dm-modal");
			if (!noclose && ((!div.is(e.target) && div.has(e.target).length === 0)) || $(".win-close").is(e.target)) {
			win_close();			
			}
		});
		if ($(v).attr('data-comment'))
		{
			link_load(v);
		}
}

function win_check(start) 
{
	if ($( window ).width()<1000 || $( window ).height()<600)
	{
		$(".dm-modal").width("100%");
		$(".dm-modal").height("100%");
	}
	else
	{
		width=$(".dm-overlay").find(".dm-modal").attr("data-width");
		height=$(".dm-overlay").find(".dm-modal").attr("data-height");
		if (width)
		{
			$(".dm-modal").width(width);
		}
		else
		{
			$(".dm-modal").width("50%");
		}
		if (height)
		{
			$(".dm-modal").height(height);
		}
		else
		{
			$(".dm-modal").height("80%");
		}
	}
}

function win_close() {
	$(".dm-overlay").hide();
	$(".dm-modal").removeAttr("data-active");
	$(".dm-modal").removeAttr("data-height");
	$(".dm-modal").removeAttr("data-width");
	$(".dm-modal").width("50%");
	$(".dm-modal").height("80%");
	$("body").css("overflow-y","auto");
}

function getActions(file)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				$(".dm-body").html(Request.responseText);
			}
		}
	}
	Request.open("GET", file, true);
	Request.send(null);
}

function stopAction(action,div)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				$("#"+div).html(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_action_stop.php?action='+action, true);
	Request.send(null);
}

function getDeviceStatus()
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				var ans=Request.responseText.split("#");
				for (i=0;i<ans.length;i++)
				{
					var str=ans[i].split(";");
					if (str[0]){$("#status_"+str[0]).html(str[1]);}
					if (str[2])
					{
						if ($("#resume").html())
						{
							if (str[3])
							{
								$("#resume").hide(300);
								$("#done").show(300);
							}
							else if (timer!=1000)
							{
								timer=1000;
								getActions('ajax_device_action.php?id='+str[2]+'&action=a0');
								$("#resume").show(300);
								$("#info").hide(300);
							}
						}
						else if (str[3])
						{
							$("#title_"+str[2]).html(str[3]);
							$("#model_"+str[2]).html(str[4]);
							$("#row_"+str[2]).removeClass('rowsel');
						}
					}
				}
			}
		}
	}
	Request.open("GET", 'ajax_device_status.php', true);
	Request.send(null);
}

function getModemStatus()
{
	var Request = new XMLHttpRequest();
	var txt=0;
	if (document.getElementById('result_receive').innerHTML){txt=1;}
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				var txt=Request.responseText.split("#-#");
				if (txt[0]=='hide')
				{
					document.getElementById("table").innerHTML='';
					document.getElementById('stop').style.display='none';
					document.getElementById('restart').style.display='none';
					document.getElementById('clear_sms').style.display='none';
					document.getElementById('session').style.display='none';
					if (document.getElementById('waiting').style.display=='inline-block' && !txt[3])
					{
						document.getElementById('waiting').style.display='none';
						document.getElementById('on').style.display='inline-block';
					}
				}
				else if (txt[0])
				{
					document.getElementById('table').innerHTML=txt[0];
					document.getElementById('answer').style.display='block';
					document.getElementById('stop').style.display='inline-block';
					document.getElementById('clear_sms').style.display='inline-block';
				}
				if (txt[5])
				{
					if (timer+5<time())
					{
						soundRing();
						timer=time();
					}
					document.getElementById('msg').innerHTML=txt[5];
					$("#msg").show(300);
				}
				else
				{
					document.getElementById('msg').style.display='none';
					$("#msg").hide(300);
				}
				id=txt[1];
				if (txt[2])
				{
					document.getElementById('result_receive').innerHTML=txt[2]+document.getElementById('result_receive').innerHTML;
					if (txt[3])
					{
						soundAlert();
					}
				}
				else if (txt[3])
				{
					if (txt[4]!=1 && txt[4]!=-1)
					{
						if (txt[3]<=0)
						{
							document.getElementById('on').style.display='inline-block';
							document.getElementById('waiting').style.display='none';
						}
						else
						{
							document.getElementById('on').style.display='none';
							document.getElementById('answer').style.display='none';
							document.getElementById('table').style.display='none';
							document.getElementById('msg').style.display='none';
							document.getElementById('stop').style.display='none';
							document.getElementById('restart').style.display='none';
							document.getElementById('clear_sms').style.display='none';
							document.getElementById('waiting').style.display='inline-block';
							document.getElementById('waiting').innerHTML='Сеанс можно начать через '+txt[3]+' сек.';
						}
					}
					else
					{
						if (txt[3]<=0 && txt[4]!=-1)
						{
							document.getElementById('table').style.display='none';
							document.getElementById('msg').style.display='none';
							document.getElementById('stop').style.display='none';
//							document.getElementById('reconnect').style.display='none';
							document.getElementById('restart').style.display='none';
							document.getElementById('clear_sms').style.display='none';
						}
						else if (txt[4]==1)
						{
							document.getElementById('stop').style.display='inline-block';
							document.getElementById('restart').style.display='inline-block';
							document.getElementById('clear_sms').style.display='inline-block';
							document.getElementById('stop').value='Выключить ('+txt[3]+' сек.)';
							document.getElementById('restart').style.display='inline-block';
						}
						else
						{
							document.getElementById('stop').style.display='inline-block';
							document.getElementById('restart').style.display='inline-block';
							document.getElementById('clear_sms').style.display='inline-block';
							document.getElementById('session').style.display='inline-block';
							document.getElementById('session').innerHTML=txt[3];
							document.getElementById('restart').style.display='inline-block';
						}
					}
				}
			}
		}
	}
	Request.open("GET", 'ajax_modem_status.php?device='+device+'&txt='+txt+'&id='+id+'&hide='+rhide, true);
	Request.send(null);
}

function soundAlert() 
{
	var audio = new Audio();
	audio.src = 'sound/sound.mp3';
	audio.autoplay = true;
}

function soundRing() 
{
	var audio = new Audio();
	audio.src = 'sound/ring.mp3';
	audio.autoplay = true;
}

function soundClick() 
{
	var audio = new Audio();
	audio.src = 'sound/click.mp3';
	audio.autoplay = true;
}

function onlineCreate()
{
	document.getElementById('answer').style.display='none';
	document.getElementById('stop').style.display='none';
	document.getElementById('clear_sms').style.display='none';

	device=document.getElementById('one').value;
	if (device==0)
	{
		device=document.getElementById('device').options[document.getElementById('device').options.selectedIndex].value;
	}
	if (device==0)
	{
		alert('Выберите агрегатор!');
		document.getElementById('device').focus();
		return;
	}
	var row=document.getElementById('row').value;
	if (!row){alert('Укажите ряд'); document.getElementById('row').focus(); return false;}

	document.getElementById('table').innerHTML='';
	document.getElementById('table').style.display='block';
	document.getElementById('answer').style.display='block';
	document.getElementById('result_receive').innerHTML='';
	soundClick();

	onlineCreateCom(device,row);
}

function onlineCreateOut(device,row)
{
	soundClick();
	onlineCreateCom(device,row);
        document.location.href = "online.php?device="+device;
}

function onlineCreateCom(device,row)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_online_create.php?device='+device+'&row='+row, true);
	Request.send(null);
}

function onlineStop()
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
			if (!Request.responseText)
			{
				document.getElementById('table').style.display='none';
				document.getElementById('msg').style.display='none';
				document.getElementById('answer').style.display='none';
				document.getElementById('stop').style.display='none';
//				document.getElementById('reconnect').style.display='none';
				document.getElementById('clear_sms').style.display='none';
			}
			else
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_online_stop.php?device='+device, true);
	Request.send(null);
}

function onlineReconnect()
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_online_reconnect.php?device='+device, true);
	Request.send(null);
}

function onlineRestart()
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_online_restart.php?device='+device, true);
	Request.send(null);
}

function sendCommand(command)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_send_command.php?device='+device+'&command='+command, true);
	Request.send(null);
}

function onlineCommand(modem,action,number,txt)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			var ans=Request.responseText.split("###");
			if (ans[0])
			{
				if (!ans[1])
				{
					if (action=='sms')
					{
						$('#winResult').html(ans[0]+'<input type="submit" onclick="document.getElementById(\'winResult\').style.display=\'none\';document.getElementById(\'contSms\').style.display=\'block\';return false;" value="Повторить" style="background: #AAA; padding: 10px; margin-top: 15px;">');
					}
					else
					{
						$('#winResult').html(ans[0]+'<input type="submit" onclick="document.getElementById(\'winResult\').style.display=\'none\';document.getElementById(\'contCall\').style.display=\'block\';return false;" value="Повторить" style="background: #AAA; padding: 10px; margin-top: 15px;">');
					}
				}
				else
				{
					$('#winResult').html(ans[0]);
				}
				$('#contSms').hide(300);
				$('#contCall').hide(300);
				$('#winResult').show(300);
				soundClick();
			}
			if (ans[1]=='reload')
			{
				onlineCommand(modem,action,number,'reload');
			}
		}
	}
	Request.open("GET", 'ajax_online_command.php?device='+device+'&modem='+modem+'&action='+action+'&number='+encodeURI(number.split("#").join("[r]"))+'&txt='+encodeURI(txt), true);
	Request.send(null);
}

function copy(str)
{
	let tmp = document.createElement('INPUT'),
	focus = document.activeElement;
	tmp.value = str;
	document.body.appendChild(tmp);
	tmp.select();
	document.execCommand('copy');
	document.body.removeChild(tmp);
	focus.focus();
}

function onlineCard()
{
	var test=document.getElementById('actionSelect').options[document.getElementById('actionSelect').options.selectedIndex].value;
	$('#winResult').hide(300);
	$('#contSms').hide(300);
	$('#contCall').hide(300);
	if (test!=-1)
	{
		$('#'+test).show(300);
	}
}

function getProgress(action)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			var ans=Request.responseText.split(";");
			if (ans[0]==100)
			{
				document.getElementById('scanned').innerHTML='Задача выполнена!';
				clearInterval(timerId);
			}
			else if (ans[1])
			{
				document.getElementById('action').innerHTML=ans[1];
				document.getElementById('progress_percent').innerHTML=ans[0]+'% (обработано:'+ans[2]+'/успешно:'+ans[4]+'/ошибки:'+ans[3]+' <span class="'+ans[5]+'">'+ans[5]+ans[6]+'</span>)';
				document.getElementById('progress').value=ans[0];
			}
		}
	}
	Request.open("GET", 'ajax_progress.php?action='+action, true);
	Request.send(null);
}

function getProgressAll(actions)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				var ans=Request.responseText.split("###");
				for (i=0;i<ans.length;i++)
				{
					var a=ans[i].split(";");
					if (a[9]==1 && a[1]<100)
					{
						$('#act_'+a[0]).html('Прогресс&nbsp;'+a[1]+'%<progress id="progress" value="'+a[1]+'" max="100"></progress><div class="legend">Обработано: '+(a[2])+'<br>Успешно: '+a[4]+'<br>Ошибки: '+a[3]+'<br>Прошло: '+a[7]+'<br>Ещё: '+a[8]+'<br><span class="'+a[5]+'">'+a[5]+a[6]+'</span></div>');
					}
					else if (a[9]==1)
					{
						$('#act_'+a[0]).html('Выполнена');
					}
					else if (a[9]==2)
					{
						$('#act_'+a[0]).html('<span class="warning">Приостановлена</span><br><div class="legend">Прогресс: '+a[1]+'%<br>Обработано: '+(a[2])+'<br>Успешно: '+a[4]+'<br>Ошибки: '+a[3]+'<br></div>');
					}
					else if (a[9]==3)
					{
						$('#act_'+a[0]).html('Приостанавливается...<progress id="progress" value="'+a[1]+'" max="100"></progress><div class="legend">Обработано: '+(a[2])+'<br>Успешно: '+a[4]+'<br>Ошибки: '+a[3]+'<br>Прошло: '+a[7]+'<br>Ещё: '+a[8]+'<br><span class="'+a[5]+'">'+a[5]+a[6]+'</span></div>');
					}
					else if (a[9]==4)
					{
						$('#act_'+a[0]).html('Подготавливается...');
					}
					else
					{
						$('#act_'+a[0]).html('В очереди');
					}
				}
			}
		}
	}
	Request.open("GET", 'ajax_progress.php?actions='+actions, true);
	Request.send(null);
}

function selectDevice(v,sections)
{
	sections=sections.split(';');
	for (i=0;i<sections.length;i++)
	{
		id=sections[i]
		if (id=='SR-Nano-500' || id=='SR-Nano-1000'){id='SR-Nano';}
		$('#'+id).hide(300);
	}
	id=$(v).find('option:selected').val();
	if (id=='SR-Nano-500' || id=='SR-Nano-1000'){id='SR-Nano';}
	$('#'+id).show(300);
}

function FindFile() { document.getElementById('my_hidden_file').click(); }  
function LoadFile() { document.getElementById('my_hidden_load').click(); }  

function onResponse(d) 
{  
	eval('var obj = ' + d + ';');  
	if(obj.message)
	{
        	$("body").css("overflow-y","hidden");
		height=400;
		width=600;
		title='Импорт CSV';
		$(".dm-overlay").find("h3").html(title);
		if (height)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-height",height);
		}
		if (width)
		{
			$(".dm-overlay").find(".dm-modal").attr("data-width",width);
		}
		$(".dm-overlay").show();
		win_check(1);
		$(document).mouseup(function (e){
			var div = $(".dm-modal");
			if (!noclose && ((!div.is(e.target) && div.has(e.target).length === 0)) || $(".win-close").is(e.target)) 
			{
				document.location.reload();			
				win_close();
			}
		});
		$(".dm-content").html(obj.message);
		return; 
	}; 
}  

function help() 
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
		        	$("body").css("overflow-y","hidden");
				height=400;
				width=600;
				title='Контекстная помощь';
				$(".dm-overlay").find("h3").html(title);
				if (height)
				{
					$(".dm-overlay").find(".dm-modal").attr("data-height",height);
				}
				if (width)
				{
					$(".dm-overlay").find(".dm-modal").attr("data-width",width);
				}
				$(".dm-overlay").show();
				win_check(1);
				$(document).mouseup(function (e){
					var div = $(".dm-modal");
					if (!noclose && ((!div.is(e.target) && div.has(e.target).length === 0)) || $(".win-close").is(e.target)) 
					{
						win_close();
					}
				});
				$(".dm-body").html(Request.responseText);
				return; 
			}
		}
	}
	Request.open("GET", 'ajax_help.php', true);
	Request.send(null);
}

function rowhide() 
{
	if (rhide==1){rhide=0;} else {rhide=1;}
	soundClick();
}

function time()
{
	return parseInt(new Date().getTime()/1000);
}