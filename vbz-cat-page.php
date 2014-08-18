<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Page lookup
  HISTORY:
    2012-05-13 moved clsCatPages from pages.php to base.cat.php
    2013-11-10 split off vbz-cat-page.php from base.cat.php
*/
class clsCatPages extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_pages');	// cache
	  //$this->Name('qryCat_pages');	// live data
	  $this->KeyName('AB');
	  $this->ClassSng('clsCatPage');
    }
    public function GetItem_byKey($iKey) {
	CallEnter($this,__LINE__,__CLASS__.'.GetItem_byKey('.$iKey.')');
	$strKey = trim($iKey,'/');
	$strKey = str_replace('-','/',$strKey);
	$sqlCatKey = $this->objDB->SafeParam($strKey);
// This function is named wrong, and needs to be rewritten anyway
//	$this->Touch('clsCatPages.GetItem_byKey('.$iKey.')');
	$objItem = $this->GetData('Path="'.$sqlCatKey.'"');
    //    $objRec = $this->objDB->Query($sql);
	assert('is_object($objItem)');
	if ($objItem->NextRow()) {
	    DumpValue('objItem NumRows',$objItem->hasRows());
	    CallExit('clsCatPages.GetItem_byKey('.$iKey.') -> Page '.$objItem->AB);
	} else {
	    CallExit('clsCatPages.GetItem_byKey('.$iKey.') -> no data');
	}
	return $objItem;
    }
}
// just for paral;ellism, at this point
class clsCatPage extends clsDataSet {
    // object cache
    private $oItem;

    protected function InitVars() {
	$this->oItem = NULL;
	parent::InitVars();
    }
    /*----
      RETURNS: an object of the appropriate type, as determined by what the current page information record indicates
      ASSUMES: if there is an object, it's the correct one
    */
    public function ItemObj() {
	if (is_null($this->oItem)) {
	    $id = $this->Row['ID_Row'];
	    $objData = $this->Engine();
	    switch ($this->Type) {
	      case 'S':
		$rs = $objData->Suppliers();
		break;
	      case 'D':
		$rs = $objData->Depts();
		break;
	      case 'T':
		$rs = $objData->Titles();
		break;
	      case 'I':
		$rs = $objData->Images();
		break;
	      default:
		$rs = NULL;
	    }
	    if (is_null($rs)) {
		$rc = NULL;
	    } else {
		$rc = $rs->GetItem($id);
	    }
	    $this->oItem = $rc;
	}
	return $this->oItem;
    }
    public function TitleStr() {
	$oItem = $this->ItemObj();
	if (is_null($oItem)) {
	    return NULL;
	} else {
	    return $oItem->TitleStr();
	}
    }
}
