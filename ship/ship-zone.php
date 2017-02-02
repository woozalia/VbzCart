<?php
/*
  PURPOSE: Shipping Zone classes
  TODO: the data part of this should probably be in a separate file, to keep configurable bits
    separate from fixed code.
  RULES:
    * Each country defined has a shipping zone. Undefined countries use the "?" country code.
    * Countries can override defaults set by the zone.
    Hopefully this will make it at least possible to configure VbzCart for use outside the US.
  HISTORY:
    2016-03-08 Rewriting to follow a similar format to table/record classes, instead of having a single
      object that looks everything up. Also, better handling of countries vs. zones.
*/

// DATA - configurable

define('KS_COUNTRY_DEFAULT','US');	// home country -- default selection

// CLASS - fixed

class vcShipZone {

    static private $arZone = array(
      'DOM'	=> array(	// domestic
	'name.zone'	=> 'domestic',
	'factor.itm'	=> 1.0,
	'factor.pkg'	=> 1.0,
	),
      'CON'	=> array(	// continental (e.g. US: Canada/Mexico)
	'name.zone'	=> 'continental',
	'factor.itm'	=> 2.0,
	'factor.pkg'	=> 2.0,
	'label.state'	=> 'County/Province',
	'label.pocode'	=> 'Postal Code',
	'description'	=> 'within North America'
	),
      'INT' => array(
	'name.zone'	=> 'international',
	'factor.itm'	=> 4.0,
	'factor.pkg'	=> 4.0,
	'label.state'	=> 'County/Province',
	'length.state'	=> 20,
	'label.pocode'	=> 'Postal Code',
	'description'	=> 'outside North America'
	)
      );

    // ++ SETUP ++ //

    static public function Spawn($sAbbr) {
	$arData = self::GetZoneData($sAbbr);
	return new vcShipZone($arData);
    }
    static protected function GetZoneData($sAbbr) {
	$ucAbbr = strtoupper($sAbbr);
	return self::$arZone[$ucAbbr];
    }
    
    public function __construct(array $arVals) {
	$this->SetValues($arVals);
    }
    private $arVals;
    protected function SetValues(array $arVals) {
	$this->arVals = $arVals;
    }
    protected function GetValue($sName) {
	return $this->arVals[$sName];
    }
    
    // -- SETUP -- //
    // ++ FIELD VALUES ++ //
    
    public function ZoneName() {
	return $this->arVals['name.zone'];
    }
    public function ItemFactor() {
	return $this->arVals['factor.itm'];
    }
    public function PackageFactor() {
	return $this->arVals['factor.pkg'];
    }
    // use NULL if country doesn't have any state-equivalent
    public function StateLabel() {
	return $this->arVals['label.state'];
    }
    public function StateLength() {
	return $this->arVals['length.state'];
    }
    public function PostalCodeLabel() {
	return $this->arVals['label.pocode'];
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function HasState() {
	return !is_null($this->StateLabel());
    }
    public function CalcPackageCost($nBaseCost) {
	return $nBaseCost * $this->PackageFactor();
    }
    public function CalcItemCost($nBaseCost) {
	return $nBaseCost * $this->ItemFactor();
    }

    // -- FIELD CALCULATIONS -- //
    
}

class vcShipCountry extends vcShipZone {

    static private $arCountry = array(
      'US'	=> array(
	'zone'		=> 'DOM',
	'name.ctry'	=> 'United States',
	'description'	=> 'within the US',
	'label.state'	=> 'State',
	'length.state'	=> 2,
	'label.pocode'	=> 'ZIP Code'
	),
      'CA'	=> array(
	'zone'		=> 'CON',
	'name.ctry'	=> 'Canada',
	'description'	=> 'to Canada',
	'label.state'	=> 'Province',
	'length.state'	=> 3,
	),
      'AU'	=> array(
	'zone'		=> 'INT',
	'name.ctry'	=> 'Australia',
	'description'	=> 'to Ozz',
	'label.state'	=> 'State/Territory',
	'length.state'	=> 3,
	),
      '?'	=> array(
	'zone'		=> 'INT',
	'name.show'	=> '(other)',
	'name.ctry'	=> NULL
	)
      );

    // ++ SETUP ++ //

    static public function Spawn($sAbbr) {
	$arCtry = self::GetCountryData($sAbbr);
	if (!is_array($arCtry)) {
	    throw new exception("Could not retrieve Country data for code [$sAbbr].");
	}
	return new vcShipCountry($arCtry);
    }
    static protected function GetCountryData($sAbbr) {
	if (empty($sAbbr)) {
	    $ucAbbr = KS_COUNTRY_DEFAULT;
	} else {
	    $ucAbbr = strtoupper($sAbbr);
	}
	$arOut = self::$arCountry[$ucAbbr];
	$arOut['!code.ctry'] = $ucAbbr;
	return $arOut;
    }
    static protected function GetCountryCodes() {
	return array_keys(self::$arCountry);
    }
    public function __construct(array $arVals) {
	// get Zone values:
	$arData = self::GetZoneData($arVals['zone']);
	
	// Country values override/append to this:
	foreach ($arVals as $key => $val) {
	    $arData[$key] = $val;
	}
	
	// use the result:
	$this->SetValues($arData);
    }
    
    // -- SETUP -- //
    // ++ FIELD VALUES ++ //
    
    // NOTE: This is the array index, stuffed back into the array automatically
    public function CountryCode() {
	return $this->GetValue('!code.ctry');
    }
    public function CountryName() {
	return $this->GetValue('name.ctry');
    }
    public function CountryDisplay() {
	$sName = $this->CountryName();
	if (is_null($sName)) {
	    return $this->GetValue('name.show');
	} else {
	    return $sName;
	}
    }
    public function Description() {
	return $this->GetValue('description');
    }
    public function PerItemFactor() {
	return $this->GetValue('factor.itm');
    }
    public function PerPackageFactor() {
	return $this->GetValue('factor.pkg');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function IsDomestic() {
	return ($this->CountryCode() == KS_COUNTRY_DEFAULT);
    }

    // -- FIELD CALCULATIONS -- //
    // ++ UI ELEMENTS ++ //
    
    public function ComboBox() {
	$sCodeThis = $this->CountryCode();
	$arCodes = self::GetCountryCodes();
	
	$sTagName = KSF_CART_SHIP_ZONE;
	$out = "\n<select name='$sTagName'>";
	foreach ($arCodes as $sCodeChoice) {
	    $oZoneChoice = self::Spawn($sCodeChoice);
	    $sChoiceText = $oZoneChoice->CountryDisplay();
	    if ($sCodeChoice == $sCodeThis) {
		    $htSelect = " selected";
	    } else {
		$sChoiceText .= " - recalculate";
		$htSelect = "";
	    }
	    $out .= "\n  <option$htSelect value='$sCodeChoice'>$sChoiceText</option>";
	}
	$out .= "\n</select>";
	return $out;
    }

    // -- UI ELEMENTS -- //
}