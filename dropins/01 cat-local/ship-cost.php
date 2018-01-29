<?php
/*
  PURPOSE: shipping cost codes for VbzCart
  HISTORY:
    2016-02-03 started
*/

class vctaShipCosts extends clsShipCosts implements fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcraShipCost';
    }
    public function GetActionKey() {
	return KS_ACTION_SHIPCOST;
    }
    
    // -- SETUP -- //
    // ++ RECORDS ++ //
    
    // CALLBACK
    public function DropDown_Records() {
	$rs = $this->SelectRecords(NULL,'Sort');
	return $rs;
    }
    public function ActiveRecords() {
	$sqlFilt = NULL;
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ WEB UI ELEMENTS ++ //

    /*----
      FUTURE:
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
	* Actually, this should be a boilerplate function with a helper class. The only change from clsItTyps
	  is the GetData filter and sorting.
      HISTORY:
	2010-11-21 Adapted from clsItTyps
	2016-02-03 moved from vbz-cat-ship-cost.php (shopping UI class) to dropins/cat-local/ship-cost.php
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	throw new exception('2016-11-?? Does anything still use this?');
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$arRows = $this->Cache()->GetData_array(NULL,NULL,'Sort');
	$out = $this->DropDown_for_array($arRows,$strName,$iDefault,$iChoose);
	return $out;
    }
    /*----
      ACTION: same as clsItTyp::DropDown_for_rows, but takes an array
      HISTORY:
	2011-02-11 wrote
    */
    public function DropDown_for_array(array $iRows,$iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	throw new exception('Does anything still use this?');
	$strName = is_null($iName)?($this->Table()->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->ChoiceLine();
	}
	return fcHTML::DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
class vcraShipCost extends clsShipCost implements fiLinkableRecord {
    use ftLinkableRecord;

    // ++ CALLBACKS ++ //
    
    public function ListItem_Text() {
	return $this->ChoiceLine();
    }
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }
    
    // -- CALLBACKS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: Approximately as much description as will fit nicely into a choice line for a drop-down or selection box
    */
    public function ChoiceLine() {
	//return $this->Value('Descr');
	$out = $this->Description().' ('.$this->PerPkg().'/p + '.$this->PerUnit().'/u)';
	return $out;
    }
    
    // -- FIELD CALCULATIONS -- //
}