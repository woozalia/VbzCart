<?php

/*
  HISTORY:
    2014-09-22 Split off from base.cust.php (to be renamed cust.php)
*/

// == CUSTOMER MAILING ADDRESS
class vctCustAddrs extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cust_addrs';
    }
    protected function SingularName() {
	return 'clsCustAddr';
    }

    // -- CEMENTING -- //
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
    /*----
      HISTORY:
	2016-07-08 Need to use db object for sanitizing, so this has to be non-static.
    */
    public function SearchableSQL($sRaw) {
	return $this->GetConnection()->Sanitize_andQuote(self::Searchable($sRaw));
    }

    // -- STATIC METHODS -- //
    // ++ CLASS NAMES ++ //

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function Recs_forCust($idCust,$iFilt=NULL) {
	$sqlFilt = 'ID_Cust='.$idCust;
	if (!is_null($iFilt)) {
	    $sqlFilt = '('.$sqlFilt.') AND ('.$iFilt.')';
	}
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTION ++ //

    /*
      SET: MakeRecord_from{*}()
      ACTION: From the given user form input, make a contact address record (creates if match not found).
      NOTE: The list of fields to be loaded, and how they are loaded, is defined
	in the Load_From{*}Object() methods.
	Further processing is done in the override to Save().
      HISTORY:
	2016-06-27 Renamed from CreateRecord_from{*} to MakeRecord_from{*}.
    */
    public function MakeRecord_fromBuyer($idUser,$idBuyer,vcCartData_Buyer $oContact) {
	$rc = $this->SpawnRecordset();
	$rc->Load_fromBuyerObject($oContact);
	$rc->SetCustID($idBuyer);
	$rc->AutoMake();	// TODO: handle failure
	return $rc;
    }
    public function MakeRecord_fromRecip($idUser,$idRecip,vcCartData_Recip $oContact) {
	$rc = $this->SpawnRecordset();
	$rc->Load_fromRecipObject($oContact);
	$rc->CustID($idRecip);
	$rc->AutoMake();	// TODO: handle failure
	return $rc;
    }
    /* 2016-05-22 old version
    public function CreateRecord($idCust,clsPerson $oPerson) {
    echo __FILE__.' line '.__LINE__.'<br>';
    die('PERSON:'.$oPerson->DumpLoadedValues());
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
    }*/
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
    public function Find($sAddr,$idCust=NULL) {
	$sSearch = self::SearchableSQL($sAddr);	// replaces aliases and adds quotes
	$sql = 'Search='.$sSearch;
	if (!is_null($idCust)) {
	    $sql = '('.$sql.') AND (ID_Cust='.$idCust.')';
	}
	$rc = $this->SelectRecords($sql);
	$rc->NextRow();	// load the first row, which should be the only one
	return $rc;
    }

    // -- SEARCHING -- //

}
class vcrCustAddr_trait extends vcShopRecordset {
    use ftSaveableRecord;
}
class clsCustAddr extends vcrCustAddr_trait {

    // ++ SETUP ++ //
    
    protected function Load_fromContactObject(vcCartData_NameAddress $oContact) {
	$this->NameString($oContact->GetNameFieldValue());
	$this->SetStreetString($oContact->GetStreetFieldValue());
	$this->SetTownString($oContact->GetTownFieldValue());
	$this->SetStateString($oContact->GetStateFieldValue());
	$this->SetZipCodeString($oContact->GetZipCodeFieldValue());
	$this->SetCountryString($oContact->GetCountryFieldValue());
    }
    public function Load_fromBuyerObject(vcCartData_Buyer $oBuyer) {
	$this->Load_fromContactObject($oBuyer);
    }
    public function Load_fromRecipObject(vcCartData_Recip $oRecip) {
	$this->Load_fromContactObject($oRecip);
	$this->InstruxString($oRecip->GetValue_forDestinationMessage());
    }
    
    // -- SETUP -- //
    // ++ OVERRIDES ++ //

    /*----
      PURPOSE: This is where we fill in the calculated fields.
    */
    protected function ChangeArray($ar=NULL) {
	/* 2016-11-04 old code
	$db = $this->Engine();
	$ar = array(
	  'Full'	=> $db->SanitizeAndQuote($this->AsString()),
	  'Search'	=> $db->SanitizeAndQuote($this->FiguredSearchString()),
	  'Search_raw'	=> $db->SanitizeAndQuote($this->FiguredSearchString()),	// TODO: Search_raw field needs to be redesigned
	  );
	*/
	$arVals = array(
	  'Full'	=> $this->AsString(),
	  'Search'	=> $this->FiguredSearchString(),
	  'Search_raw'	=> $this->FiguredSearchString(),	// TODO: Search_raw field needs to be redesigned
	  );
	$this->SetFieldValues($arVals);	// only overwrites fields defined in $arVals
	return parent::ChangeArray($ar);
    }
    protected function UpdateArray($ar=NULL) {
	$ar = parent::UpdateArray($ar);
	//$ar = fcArray::Merge($ar,$this->ChangeArray());	2016-11-04 I *think* this is redundant now
	$ar['WhenUpd'] = 'NOW()';
	return $ar;
    }
    protected function InsertArray($ar=NULL) {
	$ar = parent::InsertArray($ar);
	//$ar = fcArray::Merge($ar,$this->ChangeArray());	2016-11-04 I *think* this is redundant now
	$ar['WhenEnt'] = 'NOW()';
	return $ar;
    }
     
    // -- OVERRIDES -- //
    // ++ CLASS NAMES ++ //
    
    protected function CustomersClass() {
	return 'clsCusts';
    }
    protected function NamesClass() {
	return 'clsCustNames';
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make($this->CustomersClass(),$id);
    }
    protected function NameTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->NamesClass(),$id);
    }

    // -- TABLES -- //
    // ++ FIELD ACCESS ++ //

    /*----
      USED BY: table wrapper
    */
    public function SetCustID($id) {
	return $this->SetFieldValue('ID_Cust',$id);
    }
    /*----
      USED BY: Customer::HasCustomer()
    */
    public function GetCustID() {
	return $this->GetFieldValue('ID_Cust');
    }
    public function SetStreetString($s) {
	return $this->SetFieldValue('Street',$s);
    }
    public function GetStreetString() {
	return $this->GetFieldValue('Street');
    }
    public function SetTownString($s) {
	return $this->SetFieldValue('Town',$s);
    }
    public function GetTownString() {
	return $this->GetFieldValue('Town');
    }
    public function SetStateString($s) {
	return $this->SetFieldValue('State',$s);
    }
    public function GetStateString() {
	return $this->GetFieldValue('State');
    }
    public function SetZipCodeString($s) {
	return $this->SetFieldValue('Zip',$s);
    }
    public function GetZipCodeString() {
	return $this->GetFieldValue('Zip');
    }
    public function SetCountryString($s) {
	return $this->SetFieldValue('Country',$s);
    }
    public function GetCountryString() {
	return $this->GetFieldValue('Country');
    }
    /* 2016-06-27 Okay, maybe we *don't* actually need this.
    public function SearchableString() {
	return $this->Value('Search');
    }*/

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function IsVoid() {
	return !is_null($this->Value('WhenVoid'));
    }
    public function ShortDescr() {
	return $this->AsSingleLine();
    }
    public function NameString($s=NULL) {
	if (is_null($s)) {
	    if ($this->IsNew()) {
		return NULL;
	    } else {
		$sName = $this->GetFieldValue('Name');
		if (is_null($sName)) {
		    $rcCont = $this->CustomerRecord();
		    $sName = $rcCont->NameString();
		}
		return $sName;
	    }
	} else {
	    $this->SetFieldValue('Name',$s);	// set it
	}
    }
    public function AsString($iLineSep="\n",$doName=FALSE) {
	$xts = new xtString($this->GetFieldValue('Street'),TRUE);
	$xts->DelLead();	// delete any leading whitespace
	$xts->DelTail();	// delete any trailing whitespace
	if ($iLineSep != "\n") {
	    $xts->ReplaceSequence("\n",$iLineSep);	// replace any CR/LF sequences with ' / '
	}
	$xts->ReplaceSequence(chr(9).' ',' ');		// condense blank sequences into single blank

	if ($doName) {
	    $sName = $this->NameString();
	}
	$sStreet = $xts->Value;
	$sTown = $this->GetTownString();
	$sState = $this->GetStateString();
	$sZip = $this->GetZipCodeString();
	$sCountry = $this->GetCountryString();

	$out = NULL;
	
	if ($doName) {
	    $out = $sName;
	}

	$out = fcString::StrCat($out,$sStreet,$iLineSep);
	$out = fcString::StrCat($out,$sTown,$iLineSep);
	if (!empty($sState)) {
	    $out .= ', '.$sState;
	}
	if (!empty($sZip)) {
	    $out .= ' '.$sZip;
	}
	$out = fcString::StrCat($out,$sCountry,$iLineSep);

	return $out;
    }
    public function FiguredSearchString() {
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
	$id = $this->GetKeyValue();
	return $out." (a$id)";
    }
    public function AsSingleLine_withName() {
	$sLine = $this->AsString(' / ',TRUE);
	if (empty($sLine)) {
	    $out = "(no name/address)";
	} else {
	    $out = $sLine;
	}
	$id = $this->GetKeyValue();
	return $out." (a$id)";
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function CustObj() {
	throw new exception('CustObj() is deprecated; call CustomerRecord().');
    }
    public function CustomerRecord() {
	$idCust = $this->CustID();
	$rcCust = $this->CustomerTable($idCust);
	return $rcCust;
    }
    /*----
      RETURNS: Recordset of any existing records whose search string matches this one.
      USAGE: Check this before saving an initialized but unsaved ("new") record object.
    */
    public function GetDuplicateRecords() {
	$sSearch = $this->FiguredSearchString();
	$idCust = $this->GetCustID();
	$rc = $this->GetTableWrapper()->Find($sSearch,$idCust);
	if ($rc->HasRows()) {
	    $rc->NextRow();
	    if ($rc->RowCount() > 1) {
		throw new exception('Duplicate customer address record found; need to figure out what this means.');
	    }
	    return $rc;
	} else {
	    return NULL;	// no duplicates found
	}
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
	throw new exception('2016-06-27 Does anything actually call this still? Duplicate functionality...');
	$strSearch = $this->FiguredSearchString();
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
	throw new exception('2016-06-27 Does anything actually call this still? Duplicate functionality...');
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
	  'Search'	=> SQLValue($arUpdRaw['Search']),
	  'WhenUpd'	=> 'NOW()'
	  );
	return $this->Update($arUpd);
    }

    // -- INTERNAL CALCULATIONS -- //
    // ++ ACTIONS ++ //
    
    // TODO 2016-07-08: Rename to MakeDistinct() OSLT
    public function AutoMake() {
	$rcDup = $this->GetDuplicateRecords();
	if (is_null($rcDup)) {
	    $this->Save();			// unique address -- save it
	} else {
	    $this->Values($rcDup->Values());	// swap it in for this one
	}
    }
    /*----
      ACTION: Ensure that a name record exists that matches the Name in this (Address) record.
      RETURN: record found or created
      PUBLIC so Cart can use it during cart-to-order conversion
      HISTORY:
	2016-07-08 made public
    */
    public function EnsureNameRecord() {
	$sName = $this->NameString();
	$tNames = $this->NameTable();
	$rcName = $tNames->MakeRecord_forCust($this->GetKeyValue(),$sName);
	return $rcName;
    }

    // -- ACTIONS -- //

}
