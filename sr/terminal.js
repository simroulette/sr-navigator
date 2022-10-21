// ===================================================================
// Sim Roulette
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

var send="";
var receive="";
var connect=0;
var com_id=0;
setInterval(function()
{
	SendRequest('terminal_answer.php?com_id='+com_id);
}, term_int);
function SendRequest(url)
{
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				var str=Request.responseText.split("#!#");
				if (str[2]){com_id=str[2];}
				if (send!=str[0])
				{
					if (str[0]){document.getElementById("result_send").innerHTML=str[0]+document.getElementById("result_send").innerHTML;}
					send=str[0];
				}
				if (receive!=str[1])
				{
					var com=str[1].match(/sr>com:(.*);/);
					var enter=str[1].match(/sr>enter:(.*);/);
					var ln=str[1].match(/\[link\](.*)\[\/link\]/);
					var re=str[1].match(/sr>reload(.*);/);
					var cl=str[1].match(/sr>clear;/);
					var fe=str[1].match(/file>edit(.*);/);
					var fv=str[1].match(/file>view(.*);/);
					if (fe && fe[1])
					{
						window.location.href = "/edit?id="+fe[1];
					}
					else if (fv && fv[1])
					{
						window.location.href = "/view?id="+fv[1];
					}
					else if (re && re[1])
					{
						window.location.href = re[1];
					}
					else if (re && re[1])
					{
						window.location.href = re[1];
					}
					else if (com && com[1])
					{
						document.getElementById('result_receive').innerHTML='';
						var step=parseInt(document.getElementById('step').value)+1;
						document.getElementById('step').value=step;
						SendRequest('GET','/answer?step='+step+'&command='+com[1]);
					}
					else if (enter && enter[1])
					{
						resize_normal();
						document.getElementById('command').value=enter[1];
						document.getElementById('command').focus();
					}

					if (ln || cl)
					{
						document.getElementById('result_receive').innerHTML='';
					}

					if (enter)
					{
						str[1]="";
	        			}
					str[1]=str[1].split("[link]").join("<span class=\"lnk\" onclick=\"document.getElementById('step').value=parseInt(document.getElementById('step').value)+1;SendRequest('GET','/answer?step='+document.getElementById('step').value+'&command=");
					str[1]=str[1].split("[name]").join("');\">");
					str[1]=str[1].split("[/name]").join("");
					str[1]=str[1].split("[/link]").join("</span>");
					str[1]=str[1].split("sr>clear;").join("");

					if (str[1]){document.getElementById("result_receive").innerHTML=str[1]+document.getElementById("result_receive").innerHTML;}
					receive=str[1];
				}
			}
		}
	}
	Request.open("GET", url, true);
	Request.send(null);
}
function getRequest(){
	var com=encodeURIComponent(document.getElementById('command').value);
	document.getElementById('step').value=parseInt(document.getElementById('step').value)+1;
	SendRequest('terminal_answer.php?com_id='+com_id+'&&step='+document.getElementById('step').value+'&command='+com+'&device='+document.getElementById('device').value);
}