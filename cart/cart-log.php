<?php
/*
  PURPOSE: Shopping cart event log base class -- no UI elements
  HISTORY:
    2014-07-27 Splitting off basic functions from VCT_CartLog_admin and VCR_CartEvent_admin
*/

class clsCartLog extends vcShopTable {
    use vtFrameworkAccess;

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'shop_cart_event';
    }
    protected function SingularName() {
	return 'clsCartEvent';
    }
    // NOTE: This probably should be moved to the admin class (if there is one).
    public function GetActionKey() {
	return 'cev';
    }

    // -- CEMENTING -- //
    /*----
      NOTES:
      * Adapted from clsOrderLog::Add()
      * Should the order log be merged into the global event log?
    */
    public function Add(vcrCart $rcCart,$sCode,$sDescr) {
	$oApp = $this->AppObject();
	$sUser = $oApp->LoginName();

	$db = $this->GetConnection();
	$arIns = array(
	  'ID_Cart'	=> $rcCart->GetKeyValue(),
	  'WhenDone'	=> 'NOW()',
	  'WhatCode'	=> $db->Sanitize_andQuote($sCode),
	  'WhatDescr'	=> $db->Sanitize_andQuote($sDescr),
	  //'ID_Sess'	=> ($rcCart->HasSession())?($rcCart->SessionID()):'NULL',
	  //'ID_Sess'	=> $rcCart->SessionID(),
	  'ID_Sess'	=> $oApp->GetSessionRecord()->GetKeyValue(),
	  'VbzUser'	=> $db->Sanitize_andQuote($sUser),
	  //'SysUser'	=> $db->SanitizeAndQuote($_SERVER["SERVER_NAME"]),
	  'Machine'	=> $db->Sanitize_andQuote($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arIns);
    }
}

class clsCartEvent extends vcShopRecordset {
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