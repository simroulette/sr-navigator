// ===================================================================
// Sim Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

var noclose=0;

function menuToggle(x) 
{
	x.classList.toggle("change");
	if (document.getElementById('menu_cont').style.display=='block')
	{
		document.getElementById('menu_cont').style.display='none';	
	}
	else
	{
		document.getElementById('menu_cont').innerHTML="<a href=\"index.php\"><img src=\"sr/logo.gif\" border=\"0\" style=\"float: right; margin: -75px 10px 0 0; width: 150px;\"></a>"+document.getElementById('menu').innerHTML;	
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
	$(v).parent().html('');	
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
		id='#'+$(v).attr('data-id');
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
		$(id).show();
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
					if (str[0]){document.getElementById("status_"+str[0]).innerHTML=str[1];}
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
				if (txt[0])
				{
					document.getElementById("table").innerHTML=txt[0];
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
			}
		}
	}
	Request.open("GET", 'ajax_modem_status.php?device='+device+'&txt='+txt+'&id='+id, true);
	Request.send(null);
}

function soundAlert() 
{
	var audio = new Audio();
	audio.src = 'sound/sound.mp3';
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
	device=document.getElementById('one').value;
	if (device==0)
	{
		device=document.getElementById('device').options[document.getElementById('device').options.selectedIndex].value;
	}
	if (device==0)
	{
		alert('Выберите устройство!');
		document.getElementById('device').focus();
		return;
	}
	var row=document.getElementById('row').value;
	if (!row){alert('Укажите ряд'); document.getElementById('row').focus(); return false;}

	soundClick();
	document.getElementById('table').innerHTML='';
	document.getElementById('table').style.display='block';
	document.getElementById('answer').style.display='block';
	document.getElementById('result_receive').innerHTML='';
	document.getElementById('stop').style.display='inline-block';
	document.getElementById('reconnect').style.display='inline-block';
	document.getElementById('clear_sms').style.display='inline-block';

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
			if (!Request.responseText)
			{
				document.getElementById('table').style.display='none';
				document.getElementById('answer').style.display='none';
				document.getElementById('stop').style.display='none';
				document.getElementById('reconnect').style.display='none';
				document.getElementById('clear_sms').style.display='none';
				soundClick();
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
			if (!Request.responseText)
			{
				soundClick();
			}
			else
			{
				alert(Request.responseText);
			}
		}
	}
	Request.open("GET", 'ajax_online_reconnect.php?device='+device, true);
	Request.send(null);
}

function sendCommand(command)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (!Request.responseText)
			{
				soundClick();
			}
			else
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
				document.getElementById('progress_percent').innerHTML=ans[0]+'%';
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
					if (a[2]==1 && a[1]<100)
					{
						$('#act_'+a[0]).html('Прогресс '+a[1]+'%<progress id="progress" value="'+a[1]+'" max="100"></progress>');
					}
					else if (a[2]==1)
					{
						$('#act_'+a[0]).html('Выполнено');
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
		$('#'+sections[i]).hide(300);
	}
	$('#'+$(v).find('option:selected').val()).show(300);
}
