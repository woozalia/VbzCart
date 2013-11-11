<?php
/*
  PURPOSE: vbz library for handling dynamic data related to shopping (cart, mainly)
  HISTORY:
    2010-10-28 kluged the blank-order-email problem
    2010-12-24 Fixed calls to Update() so they always pass arrays
    2011-03-31 created AddMoney() and IncMoney()
  KLUGES:
    RenderReceipt() and TemplateVars() both have to reload the current record, which shouldn't be necessary.
*/

// FILE NAMES:
define('KWP_ICON_ALERT'		,'/tools/img/icons/button-red-X.20px.png');
define('KWP_ICON_OKAY'		,'/tools/img/icons/chkbox.gif');

//define('KS_VBZCART_SESSION_KEY','vbzcart_key');

// http query argument names
define('KSQ_ARG_PAGE_DATA','page');
define('KSQ_ARG_PAGE_DEST','goto');

// form sequencing - order in which form pages appear - for navigation code
define('KI_SEQ_CART', 0);
define('KI_SEQ_SHIP', 1);
define('KI_SEQ_PAY', 2);
define('KI_SEQ_CONF', 3);
define('KI_SEQ_RCPT', 4);

/*
 database class with creators for shop classes
*/
class clsVbzData_Shop extends clsVbzData {
    public function Sessions($id=NULL) {
	return $this->Make('cVbzSessions',$id);
    }
    public function Clients($id=NULL) {
	return $this->Make('clsUserClients',$id);
    }
    public function Users($id=NULL) {
	return $this->Make('clsVbzUserRecs',$id);
    }
    public function Carts($id=NULL) {
	return $this->Make('clsShopCarts',$id);
    }
    public function CartLines($id=NULL) {
	return $this->Make('clsShopCartLines',$id);
    }
    public function CartLog() {
	return $this->Make('clsShopCartLog');
    }
    public function Orders($id=NULL) {
	return $this->Make('clsOrders',$id);
    }
    public function OrdLines($id=NULL) {
	return $this->Make('clsOrderLines',$id);
    }
/*
    public function OrderLog() {
	return $this->Make('clsOrderLog');
    }
*/
    public function OrdMsgs($id=NULL) {
	return $this->Make('clsOrderMsgs',$id);
    }
    public function Custs() {
	return $this->Make('clsCusts');
    }
    public function CustNames() {
	return $this->Make('clsCustNames');
    }
    public function CustAddrs() {
	return $this->Make('clsCustAddrs');
    }
/* 2013-10-19 not sure why these were commented out
    public function CustEmails() {
	return $this->Make('clsCustEmails');
    }
    public function CustPhones() {
	return $this->Make('clsCustPhones');
    }
    public function CustCCards() {
	return $this->Make('clsCustCards');
    }
*/
}

/*==================
  CLASS: clsShipZone
  PURPOSE: shipping zone functions
  USAGE: Customize the isDomestic() function if you're shipping from somewhere other than the US
  RULES:
    * If a country's code isn't found in arDesc, it defaults to International
      ...there's got to be a better way to do this...
*/
class clsShipZone {
    static private $arDesc = array(
      'CA' => 'Canada',
      'US' => 'United States',
      'INT' => 'International',
      );
    // per-item adjustment factors
    static private $arItmFactors = array(
	'US' => 1.0,
	'CA' => 2.0,
	'INT' => 4.0,
      );
    // per-package adjustment factors
    static private $arPkgFactors = array(	// there's got to be a better way to do this...
	'US' => 1.0,
	'CA' => 2.0,
	'INT' => 4.0,
      );
    static private $arCountryCodes = array(
	'united states'	=> 'US',
	'canada'	=> 'CA',
	'australia'	=> 'AU',
      );

    private $strAbbr;

    public function Abbr($iAbbr=NULL) {
	if (!is_null($iAbbr)) {
	    $this->strAbbr = $iAbbr;
	}
	if (empty($this->strAbbr)) {
	    $this->strAbbr = KS_SHIP_ZONE_DEFAULT;
	}
	return $this->strAbbr;
    }

    public function Set_fromName($iName) {
	$sName = empty($iName)?KS_SHIP_ZONE_DEFAULT:$iName;
	$strLC = strtolower($sName);
	if (array_key_exists($strLC,self::$arCountryCodes)) {
	    $this->strAbbr = self::$arCountryCodes[$strLC];
	} else {
	    $this->strAbbr = 'INT';	// assume international if not recognized
	}
    }

/*
    public function Set_fromCode($iCode) {
	$sCode = empty($iCode)?KS_SHIP_ZONE_DEFAULT:$iCode;
	$strLC = strtolower($sCode);
	if (array_key_exists($strLC,self::$arDesc)) {
	    $this->strAbbr = self::$arDesc[$strLC];
	} else {
	    echo 'Zone code ['.$sName.'] not found.';
	    throw new exception('Internal error: unknown zone requested.');
	}
    }
*/
    public function Text() {	// should be Name()
	return self::$arDesc[$this->Abbr()];
    }
    
    public function hasState() {
	switch ($this->Abbr()) {
	  case 'AU':	return TRUE;	break;
	  case 'CA':	return TRUE;	break;
	  case 'US':	return TRUE;	break;
	  default:	return FALSE;	break;
	}
    }
    public function StateLabel() {
	switch ($this->Abbr()) {
	  case 'AU':	return 'State/Territory'; break;
	  case 'CA':	return 'Province';	break;
	  case 'US':	return 'State';		break;
	  default:	return 'County/Province'; break;
	}
    }
    public function PostalCodeName() {
	switch ($this->Abbr()) {
	  case 'US':	return 'Zip Code&trade;';	break;
	  default:	return 'Postal Code'; 		break;
	}
    }
    public function Country() {
	switch ($this->strAbbr) {
	  case 'US':	return 'United States';		break;
	  case 'CA':	return 'Canada';		break;
	  default:	return NULL; break;
	}
    }
    public function isDomestic() {
	return ($this->Abbr() == 'US');
    }
    /*----
      VERSION: Total is not being displayed, so no need to prompt to update it
    */
    public function ComboBox() {
	$strZoneCode = $this->Abbr();
	$out = '<select name="ship-zone">';
	foreach (self::$arDesc as $key => $descr) {
	    $strZoneDesc = $descr;
	    if ($key == $strZoneCode) {
		    $htSelect = " selected";
	    } else {
// to prompt user to recalculate the total when zone changes, uncomment this line:
//		$strZoneDesc .= " - recalculate";
		$htSelect = "";
	    }
	    $out .= '<option'.$htSelect.' value="'.$key.'">'.$strZoneDesc.'</option>';
	}
	$out .= '</select>';
	return $out;
    }
    /*----
      RETURNS: per-item price factor for the current shipping zone
    */
/* 2013-10-13 I'm not seeing this debug code anywhere, so I'm wondering if this is ever called.
    protected function PerItemFactor() {
echo 'CODE=['.$this->Abbr().'] ITEM FACTOR=['.self::$arItmFactors[$this->Abbr()].']<br>';
	return self::$arItmFactors[$this->Abbr()];
    }
*/
    /*----
      RETURNS: per-package price factor for the current shipping zone
    */
    protected function PerPkgFactor() {
	return self::$arPkgFactors[$this->Abbr()];
    }
    /*----
      INPUT: base per-item shipping price
      RETURNS: calculated price for the current shipping zone
    */
    public function CalcPerItem($iBase) {
	return $iBase * $this->PerItemFactor();
    }
    /*----
      INPUT: base per-package shipping price
      RETURNS: calculated price for the current shipping zone
    */
    public function CalcPerPkg($iBase) {
	return $iBase * $this->PerPkgFactor();
    }
}

// ShopCart Log
class clsShopCartLog extends clsTable {
    const TableName='shop_cart_event';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    public function Add($iCart,$iCode,$iDescr,$iUser=NULL) {
	global $vgUserName;

	$strUser = is_null($iUser)?$vgUserName:$iUser;
	if ($iCart->hasField('ID_Sess')) {
	    $idSess = $iCart->ID_Sess;
	} else {
	// this shouldn't happen, but we still need to log the event, and ID_Sess is NOT NULL:
	    $idSess = 0;
	}

	$edit['ID_Cart'] = $iCart->ID;
	$edit['WhenDone'] = 'NOW()';
	$edit['WhatCode'] = SQLValue($iCode);
	$edit['WhatDescr'] = SQLValue($iDescr);
	$edit['ID_Sess'] = $idSess;
	$edit['VbzUser'] = SQLValue($strUser);
	$edit['Machine'] = SQLValue($_SERVER["REMOTE_ADDR"]);
	$this->Insert($edit);
    }
}
// this can later be adapted to be currency-neutral
// for now, it just does dollars
function FormatMoney($iAmount,$iPrefix='',$iPlus='') {
    if ($iAmount < 0) {
	$str = '-'.$iPrefix.sprintf( '%0.2f',-$iAmount);
    } else {
	$str = $iPlus.$iPrefix.sprintf( '%0.2f',$iAmount);
    }
    return $str;
}
/*
  HISTORY:
    2011-08-03 added round() function to prevent round-down error
*/
function AddMoney($iMoney1,$iMoney2) {
    $intMoney1 = (int)round($iMoney1 * 100);
    $intMoney2 = (int)round($iMoney2 * 100);
    $intSum = $intMoney1 + $intMoney2;
    return $intSum/100;
}
/*
  HISTORY:
    2011-08-03 added round() function to prevent round-down error
*/
function IncMoney(&$iMoney,$iMoneyAdd) {
    $intBase = (int)round(($iMoney * 100));
    $intAdd = (int)round(($iMoneyAdd * 100));
    $intSum = $intBase + $intAdd;
    $iMoney = $intSum/100;
}
