<?php
/*
  PURPOSE: Shopping cart event log base class -- no UI elements
  HISTORY:
    2014-07-27 Splitting off basic functions from VCT_CartLog_admin (now vctAdminCartLog)
      and VCR_CartEvent_admin (now vcrAdminCartEvent)
    2018-02-21 After extensive research, I have determined that I figured out in 2014 that
      the shop_cart_event table is no longer needed because an EventPlex InTable with ModType
      of 'cart' and ID_Cart -> ModIndex will do just fine.
      So these classes need updating to use that.
      Finally renamed clsCartLog -> vctCartLog and clsCartEvent -> vcrCartEvent
    2018-02-22 Re-parenting these classes to descend from EventPlect types
      They may eventually need to become traits, if some actual functionality starts happening here.
*/

class vctCartLog extends fctEventPlex_standard {
    use vtFrameworkAccess;

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return 'vcrCartEvent';
    }
    /* 2018-02-22 this doesn't quite make sense anymore; hopefully not needed
    // NOTE: This probably should be moved to the admin class (if there is one).
    public function GetActionKey() {
	return 'cev';
    }*/

    // -- CEMENTING -- //
    /*----
      NOTE: Originally adapted from clsOrderLog::Add()
    */
    public function Add(vcrCart $rcCart,$sCode,$sDescr) {
	throw new exception('2018-02-22 Call CreateRecord($idEvent,$sState,$sText=NULL,array $arData=NULL) instead.');
	$oApp = $this->AppObject();
	$sUser = $oApp->LoginName();

	$db = $this->GetConnection();
	$arIns = array(
	  'ID_Cart'	=> $rcCart->GetKeyValue(),
	  'WhenDone'	=> 'NOW()',
	  'WhatCode'	=> $db->SanitizeValue($sCode),
	  'WhatDescr'	=> $db->SanitizeValue($sDescr),
	  //'ID_Sess'	=> ($rcCart->HasSession())?($rcCart->SessionID()):'NULL',
	  //'ID_Sess'	=> $rcCart->SessionID(),
	  'ID_Sess'	=> $oApp->GetSessionRecord()->GetKeyValue(),
	  'VbzUser'	=> $db->SanitizeValue($sUser),
	  //'SysUser'	=> $db->SanitizeAndQuote($_SERVER["SERVER_NAME"]),
	  'Machine'	=> $db->SanitizeValue($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arIns);
    }
}

class vcrCartEvent extends fcrEventPlect_standard {
    // ++ FIELD CALCULATIONS ++ //

    /* 2018-02-22 Nothing should call this now.
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
    } */

    // -- FIELD CALCULATIONS -- //
}