<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Item Types (ItTyps)
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item-type.php from base.cat.php
    2016-12-03 trait for easier Table access in other classes
*/
trait vtTableAccess_ItemType {
    protected function ItemTypesClass() {
	return 'vctItTyps';
    }
    protected function ItemTypeTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemTypesClass(),$id);
    }
}

class vctItTyps extends vcShopTable {
    use ftCacheableTable;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'cat_ittyps';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrItTyp';
    }

    // -- SETUP -- //
    
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
      HISTORY:
	2010-11-21 Adapted from clsFolders.
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	throw new exception('2016-11-01 Does this still get called? It should probably be in the admin class.');
	// IF THIS IS NOT USED, then the Cache methods can probably be eliminated as well.
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
	throw new exception('2016-11-01 Does this still get called? It should probably be in the admin class.');
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->Name();
	}
	return fcHTML::DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
/*::::
  CLASS: Item Type (singular)
*/
class vcrItTyp extends vcBasicRecordset {

    // ++ FIELD VALUES ++ //
    
    // NOTE: if PUBLIC function is needed, call Description_forItem() instead.
    public function NameSingular() {
	return $this->GetFieldValue('NameSng');
    }    
    protected function NamePlural() {
	return $this->GetFieldValue('NamePlr');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD ALIASES ++ //

    // PUBLIC so Item objects can use it in admin functions
    public function Description_forItem() {
	return $this->NameSingular();
    }

    // -- FIELD ALIASES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      HISTORY:
	2011-02-02 removed the IsNew() check because sometimes we want to use this
	  on data which has not been associated with an ID
	2016-11-07 no longer defaults to "cntInPrint"; must pass a quantity
    */
    public function QuantityName($nCount) {
	if ($nCount == 1) {
	    $out = $this->NameSingular();
	} else {
	    $out = $this->NamePlural();
	}
	return $out;
    }
    public function Name($iCount=NULL) {
	throw new exception('2016-11-07 Name() is deprecated; call QuantityName().');
	if (is_null($iCount)) {
	    if (isset($this->Row['cntInPrint'])) {
		$iCount = $this->Row['cntInPrint'];
	    } else {
		$iCount = 1;	// default: use singular
	    }
	}
	if ($iCount == 1) {
	    $out = $this->NameSingular();
	} else {
	    $out = $this->NamePlural();
	}
	return $out;
    }
    /* 2016-11-07 this functionality seems to be unused
    protected function NamePlural_toUse() {
	$sPlur = $this->NamePlural();
	if (is_null($sPlur)) {
	    $sPlur = $this->NameSingular();	// default
	}
	return $sPlur;
    }*/
    
    // -- FIELD CALCULATIONS -- //
    // ++ QUESTIONABLE ++ //
    
    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	throw new exception('2016-11-01 If this is still being used, move it to the admin class.');
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
