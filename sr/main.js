// ===================================================================
// Sim Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

var noclose=0;
var rhide=0;
var timer=0;
var kill=0;
var bottom_menu=0;
var rview=0;
var sound_sms="";
var sound_ring="";
var hi=0;

$(window).on('load', function () {
    $('.preloader').addClass("preloader-remove");     
});

$(document).mouseup(function (e){
	menuClose();			
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
		document.getElementById('menu_cont').innerHTML="<img src=\"license/logo.svg\" border=\"0\" style=\"float: right; margin: -73px 10px 0 0; width: 180px;\" onclick=\"document.location='index.php'\";>"+document.getElementById('menu').innerHTML;	
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
	helpInfo();
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
	helpInfo();
	$('.icon-pencil').attr('title','Редактировать');
	$('.icon-online').attr('title','Подключить карту в режиме Online');
	$('.icon-eject').attr('title','Извлечь карту');
	var headerHeight = $('.header').outerHeight();
	anchor = window.location.hash.substr(1); 
	if ($( window ).width()<1000 || $( window ).height()<600)
	{
		$(".dm-modal").width("100%");
		$(".dm-modal").height("100%");

	}
	var w=$('body').width();
	if (w<760){$('.table_box').width(w-20);}

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
	var w=$('body').width();
	if (w<760){$('.table_box').width(w-20);}
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

function loginCheck(login)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText==1)
			{
				$("#login_report").css("background-color","#9e0b0f");
				$("#login_report").css("color","#FFF");
			}
			else
			{
				$("#login_report").css("background-color","#59b427");
				$("#login_report").css("color","#FFF");
			}
		}
	}
	Request.open("GET", 'ajax_login_check.php?login='+login, true);
	Request.send(null);
}

function passCheck(pass)
{
	var res=1;
	if (pass.length>11 && (/[0-9]/.test(pass)) && (/[A-Z]/.test(pass)) && (/[a-z]/.test(pass))){res=0;}
	if (res==1)
	{
		$("#pass_report").css("background-color","#9e0b0f");
		$("#pass_report").css("color","#FFF");
	}
	else
	{
		$("#pass_report").css("background-color","#59b427");
		$("#pass_report").css("color","#FFF");
	}
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

					if (str[0])
					{
						$("#status_"+str[0]).html(str[1]);
						if (str[1].indexOf('Offline')>-1)
						{
 						    	$('#dev_logo_'+str[0]).find('img').addClass("grayscale");
						}
						else
						{
 						    	$('#dev_logo_'+str[0]).find('img').removeClass("grayscale");
						}
					}
					if (str[2])
					{
						if ($("#resume_"+str[0]).html())
						{
							if (str[3])
							{
								$("#resume_"+str[0]).hide(300);
								$("#done_"+str[0]).show(300);
							}
							else if (timer!=1000)
							{
								timer=1000;
								getActions('ajax_device_action.php?id='+str[2]+'&action=a0');
								$("#resume_"+str[0]).show(300);
								$("#info_"+str[0]).hide(300);
							}

							html=$("#device_"+str[0]).html();
							if (html && str[4])
							{
								var html2=html;	
								html=html.split('sr-organizers.svg').join(str[4]);
								html=html.split('sr-box.svg').join(str[4]);
								html=html.split('sr-box-bank.svg').join(str[4]);
								html=html.split('sr-box-banks.svg').join(str[4]);
								if (html2!=html)
								{
									$("#device_"+str[0]).html(html);
								}
							}
						}
						else if (str[3])
						{
							var html=$("#title_"+str[0]).html();
							if ((html && html.indexOf('инициализация...')>-1) || (str[5] && $("#model_"+str[0]).html()!=str[5]))
							{
								html=$("#dev_logo_"+str[2]).html();
								html=html.split('sr-organizers.svg').join(str[4]);
								html=html.split('sr-box.svg').join(str[4]);
								html=html.split('sr-box-bank.svg').join(str[4]);
								html=html.split('sr-box-banks.svg').join(str[4]);
								$("#model_"+str[0]).html(str[5]);
								$("#dev_logo_"+str[0]).html(html);
								$("#title_"+str[0]).html(str[3]);
							}
							$("#device_"+str[2]).css('opacity','1');
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
				var flags=txt[0];
				flags=flags.split(";");
				rview=flags[0];
				review_set();
				if (txt[1]=='hide')
				{
					$("#table").slideUp(500);
					document.getElementById("table").innerHTML='';
					document.getElementById('stop').style.display='none';
					document.getElementById('restart').style.display='none';
					document.getElementById('clear_sms').style.display='none';
					document.getElementById('session').style.display='none';
					if (document.getElementById('waiting').style.display=='table' && !txt[4])
					{
						document.getElementById('waiting').style.display='none';
						document.getElementById('on').style.display='inline-block';
					}
				}
				else if (txt[1])
				{
//					if (!document.getElementById('table').innerHTML)
					$("#table").html(txt[1]);
					$("#table").slideDown(500);
					$("#answer").slideDown(500);
					if (txt[5]<2)
					document.getElementById('stop').style.display='inline-block';
					document.getElementById('clear_sms').style.display='inline-block';
				}
				id=txt[2];
				if (txt[3])
				{
					document.getElementById('result_receive').innerHTML=txt[3]+document.getElementById('result_receive').innerHTML;
					if (txt[4])
					{
						soundAlert();
					}
				}
				else if (txt[6])
				{
					if (timer+5<time())
					{
						soundRing();
						timer=time();
					}
					document.getElementById('msg').innerHTML=txt[6];
					$("#msg").show(300);
				}
				else 
				{
					document.getElementById('msg').style.display='none';
					$("#msg").hide(300);
					if (txt[4])
					{
						if (txt[5]!=1 && txt[5]!=-1)
						{
							if (txt[4]<=0)
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
								document.getElementById('waiting').style.display='table';
								document.getElementById('waiting').innerHTML='Сеанс можно начать через '+txt[4]+' сек.';
							}
						}
						else
						{
							if (txt[4]<=0 && txt[5]!=-1)
							{
								if (txt[5]==2)
								document.getElementById('on').style.display='inline-block';
								document.getElementById('table').style.display='none';
								document.getElementById('msg').style.display='none';
								document.getElementById('stop').style.display='none';
//								document.getElementById('reconnect').style.display='none';
								document.getElementById('restart').style.display='none';
								document.getElementById('clear_sms').style.display='none';
							}
							else if (txt[5]>0)
							{
								if (txt[5]==2)
								document.getElementById('on').style.display='inline-block';
								document.getElementById('stop').style.display='inline-block';
								document.getElementById('restart').style.display='inline-block';
								document.getElementById('clear_sms').style.display='inline-block';
								if (txt[5]<2)
								document.getElementById('stop').value='Выключить ('+txt[4]+' сек.)';
								document.getElementById('restart').style.display='inline-block';
							}
							else
							{
								document.getElementById('stop').style.display='inline-block';
								document.getElementById('restart').style.display='inline-block';
								document.getElementById('clear_sms').style.display='inline-block';
								document.getElementById('session').style.display='table';
								document.getElementById('session').innerHTML=txt[4];
								document.getElementById('restart').style.display='inline-block';
							}
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

function soundAttention() 
{
	var audio = new Audio();
	audio.src = 'sound/attention.mp3';
	audio.autoplay = true;
}

function onlineCreate()
{
	document.getElementById('answer').style.display='none';
//	document.getElementById('table').style.display='none';
	document.getElementById('stop').style.display='none';
//	document.getElementById('restart').style.display='none';
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
	if (!row){alert('Выберите СИМ-карту'); document.getElementById('row').focus(); return false;}

	document.getElementById('table').innerHTML='';
	document.getElementById('table').style.display='block';
	document.getElementById('answer').style.display='block';
	document.getElementById('result_receive').innerHTML='';
//	document.getElementById('stop').style.display='inline-block';
//	document.getElementById('reconnect').style.display='inline-block';
//	document.getElementById('clear_sms').style.display='inline-block';
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
			if (Request.responseText)
			{
				soundAttention();
				alertDeskTop(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_send_command.php?device='+device+'&command='+command, true);
	Request.send(null);
}

function eject(dev,command)
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
	Request.open("GET", 'ajax_send_command.php?device='+dev+'&command='+command, true);
	Request.send(null);
}

function commentSave(id,comment)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
			$('#s'+id).html(Request.responseText);
		}
	}
	Request.open("POST", 'ajax_comment_save.php', true);
	Request.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=utf-8");
	Request.send('id='+id+'&comment='+comment);
}

function hangup(modem)
{
	onlineCommand(modem,'hangup','','');
	$('#win_action').hide(300);
}
function answer(modem)
{
	onlineCommand(modem,'answer','','');
	$('#win_action').hide(300);
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
					else if (action=='call')
					{
						$('#winResult').html(ans[0]+'<input type="submit" onclick="document.getElementById(\'winResult\').style.display=\'none\';document.getElementById(\'contCall\').style.display=\'block\';return false;" value="Повторить" style="background: #AAA; padding: 10px; margin-top: 15px;">');
					}
				}
				else
				{
					$('#winResult').html(ans[0]);
				}
				if (action!='incoming' && action!='hangup' && action!='answer')
				{
					$('#contSms').hide(300);
					$('#contCall').hide(300);
					$('#winResult').show(300);
				}
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
		if (test=='contIncomingCall')
		{
			onlineCommand(cModem,'incoming','',cNumber);
			$('#win_action').hide(300);
		}
		else
		{
			$('#'+test).show(300);
		}
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
				if (!kill)
				{
					document.getElementById('scanned').innerHTML='Задача выполнена!';
				}
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
//		$('#'+sections[i]).hide(300);
	}
	id=$(v).find('option:selected').val();
	if (id=='SR-Nano-500' || id=='SR-Nano-1000'){id='SR-Nano';}
	$('#'+id).show(300);
//	$('#'+$(v).find('option:selected').val()).show(300);
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

function helpInfo()
{
	if ($('#helpInf').length>0 && hi==0)
	{
		var offset=0;
		if ($("#help").css("display")=='none'){offset=10;}
		$("#help").show();
		$(".dm-table").hide();
		$(".dm-overlay").show();
		var position = $('#help').position();
		$("#help").hide();
		$('#helpInf').html('<em class="help" title="Помощь" onclick="location.reload(); return false;"></em>');	
		$(".help").css('opacity','1');
		$(".help").css('border','5px solid #FFF');
		$(".help").css('background','#1766dc');
//		$('#helpInf').offset({top: (position.top-5), left: position.left+70+offset});	
		$('#helpInf').offset({top: (position.top-5), left: position.left+60+offset});	
		$('#helpInfDesc').offset({top: (position.top-10)});
		$('body').css("position","fixed");
	}
}

function helpdesc()
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			$(".dm-overlay").hide();
			$(".dm-table").show();
			$(".help").css('border','none');
			$("#help").show();
			$("#helpInf").hide();
			$("#helpInfDesc").hide();
			$('body').css("position","relative");
			hi=1;
		}
	}
	Request.open("GET", 'ajax_helpdesc.php', true);
	Request.send(null);
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
	if (rhide==1)
	{
		rhide=0;
	} 
	else 
	{
		rhide=1;
	}
	rowhideset();
	var expires = new Date();
	expires.setTime(expires.getTime()+86400*365);  
	document.cookie = "srn_hide="+rhide+";expires="+expires.toGMTString();
	soundClick();
}

function review(device) 
{
	if (rview==1)
	{
		rview=0;
	}
	else
	{
		rview=1;
	}
	review_set();
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			soundClick();
		}
	}
	Request.open("GET", 'ajax_online_command.php?device='+device+'&action=review&switch='+rview, true);
	Request.send(null);
}

function review_set()
{
	if (rview==0)
	{
 		$('.icon-arrows').removeClass("icon-active");     
	}
	else
	{
 		$('.icon-arrows').addClass("icon-active");     
	}
}

function rowhideset() 
{
	if (rhide==0)
	{
		$('.icon-eye-off').attr('title','Скрывать неактивные карты');
  	    	$('.icon-eye-off').addClass("icon-eye");     
  	    	$('.icon-eye-off').removeClass("icon-eye-off");     
	} 
	else 
	{
		$('.icon-eye').attr('title','Показывать неактивные карты');
  	    	$('.icon-eye').addClass("icon-eye-off");     
  	    	$('.icon-eye').removeClass("icon-eye");     
	}
}

function time()
{
	return parseInt(new Date().getTime()/1000);
}

function fltr()
{
	$("#filter_hint").fadeOut(50);
	$("#filter").slideDown(300);
}

function fltr_off()
{
	$("#filter_hint").fadeIn(50);
	$("#filter").slideUp(300);
}

function menuOpen() {
	if (bottom_menu==0)
	{
		$('#botNavbar').addClass("responsive");     
	}
	bottom_menu=0;
}

function menuClose() {
	bottom_menu=0;
	if ($('#botNavbar').hasClass("responsive")==true)
	{
		$('#botNavbar').removeClass("responsive");     
		bottom_menu=1;
	}
}

function getNumber(dev,number) {
	if (dev!=device)
	{
		onlineCreateOut(dev,number);
	}
	else
	{
		$('#row').val(number);
		$('#row').focus();
		onlineCreateCom(device,number);
	}
}

function menuSave(menu)
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
	Request.open("GET", 'ajax_tree_save.php?'+menu, true);
	Request.send(null);
}

function alertDeskTop(txt)
{
	$("#desktop").html(txt);
	$("#desktop").slideDown(200);
	setTimeout(clearDeskTop,2000);
}

function clearDeskTop()
{
	$("#desktop").fadeOut(1000);
}