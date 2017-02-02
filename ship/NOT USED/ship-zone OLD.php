<?php
/*
  2016-03-08 This is strictly a repository for the existing class while I'm writing the new one.
    It was never used as a file; clsShipZone used to live in shop.php.
*/

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
	$out = "\n<select name=\"ship-zone\">";
	foreach (self::$arDesc as $key => $descr) {
	    $strZoneDesc = $descr;
	    if ($key == $strZoneCode) {
		    $htSelect = " selected";
	    } else {
// to prompt user to recalculate the total when zone changes, uncomment this line:
//		$strZoneDesc .= " - recalculate";
		$htSelect = "";
	    }
	    $out .= "\n<option$htSelect value=\"$key\">$strZoneDesc</option>";
	}
	$out .= "\n</select>";
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
