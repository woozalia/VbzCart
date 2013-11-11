<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Shipping Costs
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-ship-cost.php from base.cat.php
*/
class clsShipCosts extends clsVbzTable {
  public function __construct($iDB) {
    parent::__construct($iDB);
      $this->Name('cat_ship_cost');
      $this->KeyName('ID');
      $this->ClassSng('clsShipCost');
  }
    // ==BOILERPLATE - cache
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    // ==/BOILERPLATE
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
	* Actually, this should be a boilerplate function with a helper class. The only change from clsItTyps
	  is the GetData filter and sorting.
      HISTORY:
	2010-11-21 Adapted from clsItTyps
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
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
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->ChoiceLine();
	}
	return DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
class clsShipCost extends clsDataSet {
    /*----
      RETURNS: Approximately as much description as will fit nicely into a choice line for a drop-down or selection box
    */
    public function ChoiceLine() {
	return $this->Value('Descr');
    }
    public function PerPkg() {
	return $this->Value('PerPkg');
    }
    public function PerItem() {
	return $this->Value('PerItem');
    }
}
