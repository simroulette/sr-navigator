<?
// ===================================================================
// Sim Roulette -> INDEX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2021 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
sr_header("SR Navigator");
?>
<br>Панель управления СИМ-агрегаторами SIM Roulette
<br>
<br>         
<?
       	$ch = curl_init();
       	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
       	curl_setopt($ch, CURLOPT_HEADER, 0);
       	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL,'https://sim-roulette.com/navigator/message.html');
       	echo curl_exec($ch);
       	curl_close($ch);

sr_footer();
?>