<?php
/*
  PURPOSE: Shopping cart event log base class -- no UI elements
  HISTORY:
    2014-07-27 Splitting off basic functions from VCT_CartLog_admin and VCR_CartEvent_admin
*/

class clsCartLog extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('shop_cart_event');
	  $this->ClassSng('clsCartEvent');	// override parent
	  $this->ActionKey('cev');
    }
    /*----
      NOTES:
      * Adapted from clsOrderLog::Add()
      * Should the order log be merged into the global event log?
    */
    public function Add(clsShopCart $rcCart,$sCode,$sDescr) {
	$oApp = $this->Engine()->App();
	$sUser = $oApp->UserName();

	$arIns = array(
	  'ID_Cart'	=> $rcCart->KeyValue(),
	  'WhenDone'	=> 'NOW()',
	  'WhatCode'	=> SQLValue($sCode),
	  'WhatDescr'	=> SQLValue($sDescr),
	  //'ID_Sess'	=> ($rcCart->HasSession())?($rcCart->SessionID()):'NULL',
	  //'ID_Sess'	=> $rcCart->SessionID(),
	  'ID_Sess'	=> $oApp->Session()->KeyValue(),
	  'VbzUser'	=> SQLValue($sUser),
	  //'SysUser'	=> SQLValue($_SERVER["SERVER_NAME"]),
	  'Machine'	=> SQLValue($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arIns);
    }
}

class clsCartEvent extends clsDataRecord_Menu {
    // ++ FIELD CALCULATIONS ++ //

    protected function WhoString() {
	$htUnknown = '<span style="color: #888888;">?</span>';

	if (isset($iRow['SysUser'])) {
	    $strSysUser	= $this->Value('SysUser');
	    $hasSysUser 	= TRUE;
	} else {
	    $strSysUser	= NULL;
	    $hasSysUser	= FALSE;
	}
	$strMachine	= $this->Value('Machine');
	$strVbzUser	= $this->Value('VbzUser');

	$htSysUser	= is_null($strSysUser)?$htUnknown:$strSysUser;
	$htMachine	= is_null($strMachine)?$htUnknown:$strMachine;
	$htVbzUser	= is_null($strVbzUser)?$htUnknown:$strVbzUser;

	$out = $htVbzUser;
	if ($hasSysUser) {
	    $out .= '/'.$htSysUser;
	}
	$out .= '@'.$htMachine;

	return $out;
    }

    // -- FIELD CALCULATIONS -- //
}