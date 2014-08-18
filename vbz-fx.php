<?php
/*
  PURPOSE: loose functions that don't really belong in a general library
  HISTORY:
    2013-11-25 created for tidying purposes
    2013-12-01 moved http_redirect() here from cart.php
*/

// the PECL library for this function might not be installed
if (!function_exists('http_redirect')) {
    function http_redirect($iURL,$iMsg='Redirecting') {
	//header('Status: 301 Moved Permanently',TRUE);
	header('Status: 303 '.$iMsg);
	header('Location: '.$iURL,TRUE);
    }
}

/*
  TODO: Replace all of these with static methods of appropriate classes
*/

// CURRENCY

// DEPRECATED: use clsMoney::BasicFormat()
function DataCurr($iCurr,$iPfx='$') {
    if (is_null($iCurr)) {
	return NULL;
    } else {
	$out = $iPfx.sprintf("%01.2f",$iCurr);
	return $out;
    }
}

// DATE

// DEPRECATED: use clsDate::NzDate()
function DataDate($iDate) {
    if (is_string($iDate)) {
      $objDate = new DateTime($iDate);
//  if ($iDate == 0) {
//    $out = '';
//  } else {
//    $out = date('Y-m-d',$iDate);
      $out = $objDate->format('Y-m-d');
    } else {
      $out = '';
    }
    return $out;
}
function TimeStamp_HideTime($iStamp) {

    if (is_string($iStamp)) {
	$intStamp = strtotime($iStamp);
    } else if (is_int($iStamp)) {
	$intStamp = $iStamp;
    } else {
	$intStamp = NULL;
    }
    if (!is_null($intStamp)) {
	return date('Y-m-d',$intStamp);
    } else {
	return NULL;
    }
}
