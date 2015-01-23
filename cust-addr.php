<?php

/*
  HISTORY:
    2014-09-22 Split off from base.cust.php (to be renamed cust.php)
*/

// == CUSTOMER MAILING ADDRESS
class clsCustAddrs extends clsTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cust_addrs');
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustAddr');
    }

    // -- SETUP -- //
    // ++ STATIC METHODS ++ //

    /*----
      RETURNS: full address in searchable form (variants reduced to abbreviation)
      INPUT: street address, city [, state] [, country] [, zipcode]
    */
    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	$arRep = array(
	  "street"	=> "st",
	  "road"	=> "rd",
	  "place"	=> "pl",
	  "drive"	=> "dr",
	  "avenue"	=> "ave",
	  "court"	=> "ct",
	  "boulevard"	=> "blvd",
	  "terrace"	=> "ter",
	  "lane"	=> "ln",
	  "circle"	=> "cir",
	  "north"	=> "n",
	  "south"	=> "s",
	  "east"	=> "e",
	  "west"	=> "w",
	  "apartment"	=> "apt",
	  "route"	=> "rt",
	  "usa"		=> "us",

// US states -- this should eventually be a separate array
// 	some of these should only be checked *before* stripping punctuation;
//	commenting those out for now
	  'alabama'	=> 'AL',
//	  'ala.'	=> 'AL',
	  'alaska'	=> 'AK',
//	  'alas.'	=> 'AK',
	  'arizona'	=> 'AZ',
//	  'ariz.'	=> 'AZ',
	  'arkansas'	=> 'AR',
//	  'ark.'	=> 'AR',
	  'california'	=> 'CA',
//	  'calif.'	=> 'CA',
	  'colorado'	=> 'CO',
//	  'colo.'	=> 'CO',
	  'connecticut'	=> 'CT',
//	  'conn.'	=> 'CT',
	  'delaware'	=> 'DE',
//	  'del.'	=> 'DE',
	  'district of columbia'	=> 'DC',
	  'florida'	=> 'FL',
//	  'fla.'	=> 'FL',
//	  'flor.'	=> 'FL',
	  'georgia'	=> 'GA',
	  'hawaii'	=> 'HI',
	  'idaho'	=> 'ID',
//	  'ida.'	=> 'ID',
	  'illinois'	=> 'IL',
//	  'ill.'	=> 'IL',
	  'indiana'	=> 'IN',
	  'iowa'	=> 'IA',
	  'kansas'	=> 'KS',
	  'kans.'	=> 'KS',
	  'kan.'	=> 'KS',
//	  'ka.'		=> 'KS',
	  'kentucky'	=> 'KY',
//	  'ken.'	=> 'KY',
//	  'kent.'	=> 'KY',
	  'louisiana'	=> 'LA',
	  'maine'	=> 'ME',
	  'maryland'	=> 'MD',
	  'massachusetts'	=> 'MA',
//	  'mass.'	=> 'MA',
	  'michigan'	=> 'MI',
//	  'mich.'	=> 'MI',
	  'minnesota'	=> 'MN',
//	  'minn.'	=> 'MN',
	  'mississippi'	=> 'MS',
//	  'miss.'	=> 'MS',
	  'missouri'	=> 'MO',
	  'montana'	=> 'MT',
//	  'mont.'	=> 'MT',
	  'nebraska'	=> 'NE',
//	  'nebr.'	=> 'NE',
//	  'neb.'	=> 'NE',
	  'nevada'	=> 'NV',
//	  'nev.'	=> 'NV',
	  'new hampshire'	=> 'NH',
	  'new jersey'	=> 'NJ',
	  'new mexico'	=> 'NM',
//	  'n. mex.'	=> 'NM',
	  'new york'	=> 'NY',
//	  'n. york'	=> 'NY',
	  'north carolina'	=> 'NC',
	  'north dakota'	=> 'ND',
	  'ohio'	=> 'OH',
	  'oklahoma'	=> 'OK',
//	  'okla.'	=> 'OK',
	  'oregon'	=> 'OR',
//	  'oreg.'	=> 'OR',
//	  'ore.'	=> 'OR',
	  'pennsylvania'	=> 'PA',
//	  'penn.'	=> 'PA',
//	  'penna.'	=> 'PA',
	  'rhode island'	=> 'RI',
	  'south carolina'	=> 'SC',
	  'south dakota'	=> 'SD',
	  'tennessee'	=> 'TN',
//	  'tenn.'	=> 'TN',
	  'texas'	=> 'TX',
//	  'tex. 	=> 'TX',
	  'utah'	=> 'UT',
	  'vermont'	=> 'VT',
	  'virginia'	=> 'VA',
//	  'virg.'	=> 'VA',
	  'washington'	=> 'WA',
	  'wash.'	=> 'WA',
	  'west virginia'	=> 'WV',
	  'wisconsin'	=> 'WI',
// 	  'wis.'	=> 'WI',
//	  'wisc.'	=> 'WI',
	  'wyoming'	=> 'WY',
//	  'wyo.'	=> 'WY',
	  'american samoa'	=> 'AS',
	  'guam'	=> 'GU',
	  'northern mariana islands'	=> 'MP',
	  'puerto Rico'	=> 'PR',
	  'virgin islands'	=> 'VI',
	  'marshall islands'	=> 'MH',
	  'palau'	=> 'PW'
	  );
	$xts->ReplaceList($arRep);
	$xts->DelAscRange(0,47);	// up to 0
	$xts->DelAscRange(58,96);	// after 9, to a
	$xts->DelAscRange(123,255);	// after z
	return $xts->Value;
    }
    public static function SearchableSQL($iRaw) {
	return SQLValue(self::Searchable($iRaw));
    }

    // -- STATIC METHODS -- //
    // ++ CLASS NAMES ++ //

    protected function MailAddrsClass() {
	return 'clsCustAddrs';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function MailAddrTable($id=NULL) {
	return $this->Engine()->Make($this->MailAddrsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function Recs_forCust($idCust,$iFilt=NULL) {
	$tbl = $this->MailAddrTable();
	$sqlFilt = 'ID_Cust='.$idCust;
	if (!is_null($iFilt)) {
	    $sqlFilt = '('.$sqlFilt.') AND ('.$iFilt.')';
	}
	$rs = $tbl->GetData($sqlFilt);
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTION ++ //

    /*----
      ACTION: Generates the initial change array to create the record,
	but only fills in fields that can be determined just from the address object
	ID_Cust needs to be filled in before the SQL is executed.
      TODO: decouple this from clsCartAddr
      DEPRECATED until we know who needs to access it.
    */
/*    protected function Create_array_init(clsCartAddr $iAddrObj) {
	$arIns = array(
	  'Street'	=> SQLValue($iAddrObj->StreetStr()),
	  'Town'	=> SQLValue($iAddrObj->CityStr()),
	  'State'	=> SQLValue($iAddrObj->StateStr()),
	  'Zip'		=> SQLValue($iAddrObj->ZipStr()),
	  'Country'	=> SQLValue($iAddrObj->CountryStr()),
	  'Extra'	=> SQLValue($iAddrObj->InstrucStr()),
	  // calculated fields
	  'Full'	=> SQLValue($iAddrObj->AsText()),
	  'Search'	=> SQLValue($iAddrObj->AsSearchable())
	  );
	return $arIns;
    }
    */
    /*----
      ACTION: Generates the complete change array to create the record
      USED BY: this->Make_Script(), this->Create()
      TODO: decouple this from clsCartAddr
      DEPRECATED until we know who needs to access it.
    */
    /*
    protected function Create_array($iCustID,clsCartAddr $iAddrObj) {
	$ar = $this->Create_array_init($iAddrObj);
	$ar['ID_Cust'] = $iCustID;
	$ar['WhenEnt'] = 'NOW()';
	return $ar;
    }
    */
    /*----
      ACTION: Generates the complete change array to update the record
	This is sort of a formality, because it's the same as Create_SQL_init --
	i.e. everything but the customer ID... but this conceivably
	might change later, so better to encapsulate now.
      TODO: decouple this from clsCartAddr
      DEPRECATED until we know who needs to access it.
    */
    /*
    protected function Update_array(clsCartAddr $iAddrObj) {
	$ar = $this->Create_array_init($iAddrObj);
	$ar['WhenUpd'] = 'NOW()';
	return $ar;
    }
    */
    /*----
      ACTION: creates a record with previously-initialized data
    */
    /* 2014-02-16 DEPRECATED - use CreateRecord()
    protected function Create($iCustID,clsCartAddr $iAddrObj) {
	$arIns = $this->Create_array($iCustID,$iAddrObj);

	$ok = $this->Insert($arIns);
	if ($ok) {
	    $id = $this->Engine()->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    }
    */
    public function CreateRecord($idCust,clsPerson $oPerson) {
	$arIns = array(
	  'ID_Cust'	=> $idCust,
	  'WhenEnt'	=> 'NOW()',
	  'Full'	=> $oPerson->Addr_AsText(),
	  'Search'	=> $oPerson->Addr_forSearch_stripped(FALSE),	// FALSE = don't include name
	  'Search_raw'	=> $oPerson->Addr_forSearch(FALSE),		// FALSE = don't include name
	  'Name'	=> $oPerson->NameValue(),
	  'Street'	=> $oPerson->StreetValue(),
	  'Town'	=> $oPerson->TownValue(),
	  'State'	=> $oPerson->StateValue(),
	  'Zip'		=> $oPerson->ZipcodeValue(),
	  'Country'	=> $oPerson->CountryValue(),
	  'Extra'	=> $oPerson->DirectionsValue(),
	  );
	return $this->Insert($arIns);
    }
/*
    public function Make_fromCartAddr($iCustID,clsCartAddr $iAddr) {
	$sqlAddr = SQLValue($iAddr->AsSearchable());
	$objRows = $this->GetData("(ID_Cust=$iCustID) AND (Search=$sqlAddr)");
	if ($objRows->HasRows()) {
	    $objRows->NextRow();
	    $id = $objRows->ID;
	} else {
	    $id = $this->Create($iCustID,$iAddr);
	}
	return $id;
    }
*/
    /*----
      HISTORY:
	2012-04-24 This couldn't have been working -- iData index was wrong. Fixed.
	  Adding option to filter without ID_Cust, and another option to provide
	    data in Raw (non-stripped) form.
	  Making function public, to match declaration in abstract class.
	2014-02-16 ...what base class? Commenting this out until we know who uses it.
    */
/*
    public function MakeFilt(array $iData) {
	if (array_key_exists('Search',$iData)) {
	    $sqlAddr = SQLValue($iData['Search']);
	} else {
	    $sqlAddr = self::SearchableSQL($iData['SearchRaw']);
	}
	$sqlFilt = "Search=$sqlAddr";
	if (array_key_exists('ID_Cust',$iData)) {
	    $idCust = $iData['ID_Cust'];
	    return "(ID_Cust=$idCust) AND ($sqlFilt)";
	} else {
	    return $sqlFilt;
	}
    }
    */

    // -- ACTION -- //
    // ++ SEARCHING ++ //

    /*-----
      INPUT: iAddr = address to look for
    */
    public function Find($iAddr,$iCust=NULL) {
	$strSrch = self::SearchableSQL($iAddr);	// replaces aliases and adds quotes
	$sql = 'Search='.$strSrch;
	if (!is_null($iCust)) {
	    $sql = '('.$sql.') AND (ID_Cust='.$iCust.')';
	}
	$objRows = $this->GetData($sql);
	$objRows->NextRow();	// load the first row, which should be the only one
	return $objRows;
    }

    // -- SEARCHING -- //

    /*----
      LATER: if idCust is not known, can skip the searching
      NOTE: This is complicated because it also needs to update fields in the
	order record, but they may be different fields depending on which address
	this is (shipping or payment). What I ended up doing is adding the ability
	for the update/create script to be retrieved from outside (under the name
	"cust.addr.do"), so the applicable ID can be used as appropriate.
      INPUT:
	$iAddr: TreeNode-descended address object to import (from cart data)
	$idCust: ID of customer record to which this address belongs
	$actOrder: the script which is being used to update the order record
	  POSSIBLY THIS IS OBSOLETE? Previous description, from when this was an array:
	  We may need to update fields in the order record, but we don't know
	    which ones -- because that depends on the type of contact information
	    being imported. So we stash the result in a generic array which the
	    caller will plug into the appropriate fields.
      HISTORY:
	2011-09-23 written for script-based import process
    */
/* 2011-12-18 does anything call this?
    public function Make_Script(clsCartAddr $iAddr,$idCust,Script_RowObj $actOrder) {
	$acts = new Script_Script();

	$strKey = $iAddr->AsSearchable();
	$objAddr = $this->Find($strKey,$idCust);
	if ($objAddr->HasRows()) {
	    $objAddr->FirstRow();
	    $id = $objAddr->KeyValue();
	    $acts->Add(new Script_Status('SAME as existing address ID='.$id));
	    $arAct = $this->Update_SQL($iAddr);
	    $act = new Script_Row_Update($arAct,$objAddr);
	    $acts->Add($act,'cust.addr.do');	// name it so caller can find it
	    $actOrder->Name('addr',$objAddr->ID);	// using existing record
	} else {
	    $arAct = $this->Create_SQL($idCust,$iAddr);
	    $act = new Script_Tbl_Insert($arAct,$this);
	    $acts->Add($act,'cust.addr.do');	// name it so caller can find it
	    $acts->Add(new Script_SQL_Use_ID($act,$actOrder,'addr'));
	}
throw new exception('Path to here is...?');
	return $acts;
    }
*/
}
class clsCustAddr extends clsVbzRecs {

    // ++ DATA FIELD ACCESS ++ //

    protected function IsVoid() {
	return !is_null($this->Value('WhenVoid'));
    }
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function ShortDescr() {
	return $this->AsSingleLine();
    }
    public function NameString() {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $sName = $this->Value('Name');
	    if (is_null($sName)) {
		$rcCont = $this->CustomerRecord();
		$sName = $rcCont->NameString();
	    }
	    return $sName;
	}
    }
    public function StreetString() {
	return $this->Value('Street');
    }
    public function TownString() {
	return $this->Value('Town');
    }
    public function StateString() {
	return $this->Value('State');
    }
    public function ZipCodeString() {
	return $this->Value('Zip');
    }
    public function CountryString() {
	return $this->Value('Country');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //
/*
    protected function NameTable($id=NULL) {
	return $this->Engine()->Make($this->NamesClass(),$id);
    }
  */
    // -- DATA TABLE ACCESS -- //
    // ++ DATA FIELD CALCULATIONS ++ //

    public function AsString($iLineSep="\n") {
	$xts = new xtString($this->Value('Street'),TRUE);
	$xts->DelLead();	// delete any leading whitespace
	$xts->DelTail();	// delete any trailing whitespace
	if ($iLineSep != "\n") {
	    $xts->ReplaceSequence("\n",$iLineSep);	// replace any CR/LF sequences with ' / '
	}
	$xts->ReplaceSequence(chr(9).' ',' ');		// condense blank sequences into single blank

	$strStreet = $xts->Value;
	$strTown = $this->Town;
	$strState = $this->State;
	$strZip = $this->Zip;
	$strCountry = $this->Country;

	$out = $strStreet;
	if (!empty($out) && !empty($strTown)) {
	    $out .= $iLineSep.$strTown;
	}
	if (!empty($strState)) {
	    $out .= ', '.$strState;
	}
	if (!empty($strZip)) {
	    $out .= ' '.$strZip;
	}
	if (!empty($strCountry) && !empty($out)) {
	    $out .= $iLineSep.$strCountry;
	}
	return $out;
    }
    public function SearchString() {
	$xts = new xtString($this->AsString(''),TRUE);
	$xts->ReplaceSequence("\n ",'');	// remove all newlines and spaces
	$strSearch = strtolower($xts->Value);
	return $strSearch;
    }
    /*-----
      RETURNS: Address formatted as single line
    */
    public function AsSingleLine() {
	$sLine = $this->AsString(' / ');
	if (empty($sLine)) {
	    $out = "(empty address)";
	} else {
	    $out = $sLine;
	}
	$id = $this->KeyValue();
	return $out." (a$id)";
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ DATA RECORDS ACCESS ++ //
/*
    protected function NameRecord() {
	$id = $this->NameID();
	return $this->NameTable($id);
    } */
    public function CustObj() {
	throw new exception('CustObj() is deprecated; call CustomerRecord().');
    }
    public function CustomerRecord() {
	$idCust = $this->CustID();
	$rcCust = $this->Engine()->Custs($idCust);
	return $rcCust;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ INTERNAL CALCULATIONS ++ //

    /*
      RETURNS: array of calculated values to update
      HISTORY:
	2013-11-07 $strSearch was being set as $strSeach; fixed.
      TODO: This really should be reworked somehow. Who uses the
	raw, non-SQL values?
    */
    protected function CalcUpdateArray() {
	$strSearch = $this->SearchString();
	$strFull = $this->AsString();
	$arUpd = array(
	  'Full'	=> $strFull,
	  'Search_raw'	=> $strFull,
	  'Search'	=> $strSearch
	  );
	return $arUpd;
    }
    /*-----
      ACTION: Update calculated fields
    */
    public function UpdateCalc() {
/*
	$xts = new xtString($this->AsString(''),TRUE);
	$xts->ReplaceSequence("\n ",'');	// remove all newlines and spaces
	$strSearch = strtolower($xts->Value);
	$strFull = $this->AsString();
	$sqlFull = SQLValue($strFull);
*/
	$arUpdRaw = $this->CalcUpdateArray();
	$sqlFull = SQLValue($arUpdRaw['Full']);
	$arUpd = array(
	  'Full'	=> $sqlFull,
	  'Search_raw'	=> $sqlFull,
	  'Search'	=> SQLValue($arUpdRaw['Search'])
	  );
	return $this->Update($arUpd);
    }

    // -- INTERNAL CALCULATIONS -- //
}
