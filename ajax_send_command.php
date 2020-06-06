<?
// ===================================================================
// Sim Roulette -> AJAX
// License: GPL v3 (http://www.gnu.org/licenses/gpl.html)
// Copyright (c) 2016-2020 Xzero Systems, http://sim-roulette.com
// Author: Nikita Zabelin
// ===================================================================

include("_func.php");
sr_command((int)$_GET['device'],htmlspecialchars_decode(str_replace('&num;','#',str_replace('&plus;','+',str_replace('!','&',urldecode($_GET['command']))))));
?>