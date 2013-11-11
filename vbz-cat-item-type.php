<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Item Types (ItTyps)
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item-type.php from base.cat.php
*/
class clsItTyps extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_ittyps');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItTyp');
    }
    // BOILERPLATE - cache
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
/*
    public function GetData_Cached($iWhere=NULL,$iClass=NULL,$iSort=NULL) {
	return $this->Cache()->GetData($iWhere,$iClass,$iSort);
    }
*/
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
      HISTORY:
	2010-11-21 Adapted from clsFolders.
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$arRows = $this->Cache()->GetData_array('isType',NULL,'Sort, NameSng');
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
	    $arList[$key] = $objRow->Name();
	}
	return DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
/*====
  CLASS: Item Type (singular)
*/
class clsItTyp extends clsDataSet_bare {
    /*----
      HISTORY:
	2011-02-02 removed the IsNew() check because sometimes we want to use this
	  on data which has not been associated with an ID
    */
    public function Name($iCount=NULL) {
	if (is_null($iCount)) {
	    if (isset($this->Row['cntInPrint'])) {
		$iCount = $this->Row['cntInPrint'];
	    } else {
		$iCount = 1;	// default: use singular
	    }
	}
	$strSng = NzArray($this->Row,'NameSng');
	if ($iCount == 1) {
	    $out = $strSng;
	} else {
	    $out = NzArray($this->Row,'NamePlr',$strSng);
	}
	return $out;
    }
    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	if ($this->HasRows()) {
	    $out = '<select name="'.$strName.'">';
	    if (!is_null($iChoose)) {
		$out .= '<option>'.$iChoose.'</option>';
	    }
	    while ($this->NextRow()) {
		$id = $this->Row['ID'];
		if ($id == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$id.'">'.$this->Name().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No item types found.';
	}
	return $out;
    }
}
