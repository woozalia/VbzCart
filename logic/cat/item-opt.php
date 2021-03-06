<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Item Options (ItOpts)
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item-opt.php from base.cat.php
*/
trait vtTableAccess_ItemOption {
    protected function ItemOptionsClass() {
	return KS_LOGIC_CLASS_LC_ITEM_OPTIONS;
    }
    protected function ItemOptionTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemOptionsClass(),$id);
    }
}

class vctItemOptions extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cat_ioptns';
    }
    protected function SingularName() {
	return 'vcrItemOption';
    }
    
    // -- CEMENTING -- //
    // ++ BOILERPLATE - cache ++ //
    
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
    
    // -- BOILERPLATE - cache -- //
    // ++ WEB UI ++ //

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
    throw new exception('2016-03-22 Does anything actually call this?');
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
    throw new exception('2016-03-22 Does anything actually call this?');
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->ChoiceLine();
	}
	return fcHTML::DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
    
    // -- WEB UI -- //
    
}
class vcrItemOption extends vcBasicRecordset {

    // ++ FIELD VALUES ++ //
    
    // PUBLIC so Item records can access it
    public function CatKey() {
	return $this->GetFieldValue('CatKey');
    }
    public function SortKey() {
	return $this->Value('Sort');
    }
    public function Description() {
	return $this->GetFieldValue('Descr');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: Approximately as much description as will fit nicely into a choice line for a drop-down or selection box
    */
    public function ChoiceLine() {
    throw new exception('2016-03-22 Does anything actually call this?');
	return $this->Value('CatKey');
    }
    /*----
      RETURNS: A longer description for when horizontal space is not tight
    */
    public function Description_forList() {
	return $this->CatKey().' - '.$this->Description();
    }
    public function Description_forItem() {
	return $this->GetFieldValue('Descr');
    }
    
    // -- FIELD CALCULATIONS -- //

}
