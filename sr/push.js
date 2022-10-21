firebase.initializeApp({
    messagingSenderId: '867194476754'
});

// браузер поддерживает уведомления
// вообще, эту проверку должна делать библиотека Firebase, но она этого не делает
if ('Notification' in window) {
    var messaging = firebase.messaging();

    // пользователь уже разрешил получение уведомлений
    // подписываем на уведомления если ещё не подписали
    if (Notification.permission === 'granted') {
        subscribe(0);
    }

    // по клику, запрашиваем у пользователя разрешение на уведомления
    // и подписываем его
/*
    $('#subscribe').on('click', function () {
       subscribe(1);
    });
*/
}

function subscribe(mode) {
    // запрашиваем разрешение на получение уведомлений
//                    console.log("Start");
    messaging.requestPermission()
        .then(function () {
            // получаем ID устройства
            messaging.getToken()
                .then(function (currentToken) {
                    console.log(currentToken);

                    if (currentToken) {
			if (mode==0) {
			   setButton(currentToken,isTokenSentToServer(currentToken));
			   return;		
			}
                        sendTokenToServer(currentToken);
//			alert('Ok.');
                    } else {
//			alert('Не удалось получить токен.');
                        console.warn('Не удалось получить токен.');
                        setTokenSentToServer(false);
                    }
                })
                .catch(function (err) {
//		alert('При получении токена произошла ошибка.'+err);
                    console.warn('При получении токена произошла ошибка.', err);
                    setTokenSentToServer(false);
                });
    })
    .catch(function (err) {
//	alert('Не удалось получить разрешение на показ уведомлений.'+err);
        console.warn('Не удалось получить разрешение на показ уведомлений.', err);
    });
}

// отправка ID на сервер
function sendTokenToServer(currentToken) {
    if (!isTokenSentToServer(currentToken)) {
        console.log('Отправка токена на сервер...');
//	alert('Отправка токена на сервер...');

        setTokenSentToServer(currentToken);
    } else {
//	alert('Токен уже отправлен на сервер.');
        console.log('Токен уже отправлен на сервер.');
    }
}

// используем localStorage для отметки того,
// что пользователь уже подписался на уведомления
function isTokenSentToServer(currentToken) {
    return window.localStorage.getItem('sentFirebaseMessagingToken') == currentToken;
}

function unSubscribe(currentToken) {
   if (currentToken) {
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
			setButton(currentToken,0);			
			window.localStorage.setItem(
		        'sentFirebaseMessagingToken',
		        ''
			);
		}
	}
	Request.open("GET", 'ajax_push.php?token='+currentToken, true);
	Request.send(null);
    }	
}

function setTokenSentToServer(currentToken) {
   if (currentToken) {
	var Request = new XMLHttpRequest();
	Request.onreadystatechange = function()
	{
		if (Request.readyState == 4)
		{
			if (Request.responseText)
			{
				alert(Request.responseText);
			}
			setButton(currentToken,1);			
			window.localStorage.setItem(
			'sentFirebaseMessagingToken',
			currentToken ? currentToken : ''
			);
		}
	}
	Request.open("GET", 'ajax_push.php?mode=subscribe&token='+currentToken, true);
	Request.send(null);
    }	
}

function setButton(currentToken,button) {
   if (button==1) {
       $("#push").html('<button type="button" onclick="unSubscribe(\''+currentToken+'\');">Отключить PUSH-уведомления</button>');
   }
   else 
   {
	$("#push").html('<button type="button" onclick="subscribe();" class="green">Включить PUSH-уведомления</button>');
   }	
}