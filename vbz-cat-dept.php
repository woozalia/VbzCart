<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Departments
    Departments are DEPRECATED and should be obsolete soon if not already,
    so we should be able to remove this file from the project soon.
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-dept.php from base.cat.php
*/
class clsDepts extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_depts');
	  $this->KeyName('ID');
	  $this->ClassSng('clsDept');
	  $this->ActionKey('dept');
    }
/*
    protected function _newItem() {
	CallStep('clsDepts._newItem()');
	return new clsDept($this);
    }
*/
}
class clsDept extends clsDataSet {
// object cache
    private $objSupp;

    // ++ DATA FIELD ACCESS ++ //

    public function SupplierID() {
	return $this->Value('ID_Supplier');
    }
    public function NameStr() {
	return $this->Value('Name');
    }
    public function PageKey() {
	if ($this->Value('PageKey')) {
	    return $this->Value('PageKey');
	} else {
	    return $this->CatKey();
	}
    }
    public function CatKey() {
	return $this->Value('CatKey');
    }
    
    // -- DATA FIELD ACCESS -- //
    // ++ DATA FIELD CALCULATIONS ++ //

    public function TitleStr() {
	$out = $this->NameStr().' department of '.$this->SuppObj()->NameStr();
	return;
    }
    public function URL_Rel() {
	$strURL = $this->Supplier()->URL_rel();
	$strKey = $this->PageKey();
	if ($strKey) {
	    $strURL .= strtolower($strKey).'/';
	}
	return $strURL;
    }
    public function URL_Abs() {
	return KWP_ROOT.$this->URL_Rel();
    }
    public function LinkName() {
	$strURL = $this->URL_Rel();
	return '<a href="'.$strURL.'">'.$this->NameStr().'</a>';
    }
    /*-----
      RETURNS: The string which, when prepended to a Title's CatKey, would form the Title's catalog number
      HISTORY:
	2013-11-18 Added sSep parameter so we could generate URLs too
    */
    public function CatPfx($sSep='-') {
	$strFull = strtoupper($this->Supplier()->CatKey());
	if ($this->AffectsCatNum()) {
	    $strFull .= $sSep.strtoupper($this->CatKey());
	}
	return $strFull.$sSep;
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function SuppObj() {
	if (is_object($this->objSupp)) {
	    return $this->objSupp;
	} else {
	    $idSupp = $this->SupplierID();
	    if ($idSupp) {
		$this->objSupp = $this->Engine()->Suppliers()->GetItem($idSupp);
		return $this->objSupp;
	    } else {
		return NULL;
	    }
	}
    }
    // DEPRECATED -- use SuppObj()
    public function Supplier() {
	return $this->SuppObj();
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FOREIGN DATA ACCESS ++ //

    /*----
      PURPOSE: loads data needed to display catalog views for this department
      HISTORY
	2010-11-12 disabled automatic cache update
	2010-11-16 changed sorting field from cntInPrint to cntForSale
	2011-02-02 using _dept_ittyps now instead of qryItTypsDepts_ItTyps
	  Also added "AND (cntForSale)" to WHERE clause -- not listing titles with nothing to sell
	2013-11-18 rewriting
    */
    protected function Data_forStore() {	// was GetDeptData()
	//$objCache = $this->objDB->CacheMgr();
	//$objCache->Update_byName('_dept_ittyps','clsDept.DoListing() for ID='.$this->ID);
	//$sql = 'SELECT * FROM qryItTypsDepts_ItTyps WHERE (ID_Dept='.$this->ID.') ORDER BY cntForSale DESC';
	//$sql = 'SELECT * FROM _dept_ittyps WHERE (ID_Dept='.$this->ID.') AND (cntForSale) ORDER BY cntForSale DESC';

	$db = $this->Engine();
	$sqlItm = $db->Items()->NameSQL();
	$sqlTtl = $db->Titles()->NameSQL();
	$sqlItt = $db->ItTyps()->NameSQL();
	$idDept = $this->KeyValue();

	$sql = <<<__END__
SELECT i.*, t.Name, t.CatKey, it.NameSng, it.NamePlr
FROM ($sqlItm AS i
LEFT JOIN $sqlTtl AS t ON i.ID_Title=t.ID)
LEFT JOIN $sqlItt AS it ON i.ID_ItTyp=it.ID
WHERE (t.ID_Dept=$idDept) AND i.isForSale
__END__;
	$rs = $this->Engine()->DataSet($sql);
	return $rs;
    }

    // ACTIONS

    /*-----
      PURPOSE: Print this department's information as part of department list
      HISTORY:
	2010-11-16 $cntAvail is now cntForSale, not cntInPrint+qtyInStock
    */
/* DEPRECATED -- if this is being used, it's a duplicate of functionality in Supplier
    public function DoListing() {
	assert('$this->ID');
	$objItTyps = $this->Data_forStore();
	$isFirst = true;
	$out = '';
	while ($objItTyps->NextRow()) {
	    if ($isFirst) {
		$isFirst = false;
	    } else {
		$out .= ', ';
	    }
	    $cntInPrint = $objItTyps->cntInPrint;
	    $qtyInStock = $objItTyps->qtyForSale;
	    //$cntAvail = $cntInPrint + $qtyInStock;
	    $cntForSale = $objItTyps->cntForSale;
	    if ($cntAvail == 1) {
		$strName = $objItTyps->ItTyp_Sng;
	    } else {
		$strName = $objItTyps->ItTyp_Plr;
	    }
	    $out .= ' <b>'.$cntAvail.'</b> '.$strName;
	}
	return $out;
    }
*/
    /*----
      PURPOSE: Print page for current department
      ACTION:
	* Iterates through item types available for this department.
	* For each item type, prints header and then a list of titles.
      HISTORY:
	2010-11-?? Started using cached table _title_ittyps instead of qryTitles_ItTyps_Titles
	2010-11-16 $cntAvail is now cntForSale, not cntInPrint+qtyInStock
	2011-02-02 $qtyInStock now set to Row['qtyInStock'], not Row['qtyForSale'] which didn't make sense anyway
    */
    public function RenderPage() {
	$ht = NULL;
	$idDept = $this->KeyValue();
	$rs = $this->Data_forStore();

	$arTtl = array();
	//$arItt = array();

	$sCatNPfx = $this->CatPfx();	// catalog number prefix for this Department
	$sCatUPfx = $this->CatPfx('/');// catalog URL prefix for this Department


	while ($rs->NextRow()) {
	    $idTtl = $rs->Value('ID_Title');
	    $idItt = $rs->Value('ID_ItTyp');

	    // accumulate title list/info
	    $prSell = $rs->Value('PriceSell');
	    $sOptKey = $rs->Value('ItOpt_Sort');
	    $sOptVal = $rs->Value('CatSfx');
	    if (!array_key_exists($idTtl,$arTtl)) {
		$ar = array(
		  'Name'	=> $rs->Value('Name'),
		  'CatKey'	=> $rs->Value('CatKey'),
		  'PriceMin'	=> $prSell,
		  'PriceMax'	=> $prSell,
		  'Options'	=> array($sOptKey=>$sOptVal)
		  );
		$arTtl[$idTtl] = $ar;
	    } else {
		$ar = $arTtl[$idTtl];
		if ($prSell > $ar['PriceMax']) {
		    $ar['PriceMax'] = $prSell;
		} elseif ($prSell < $ar['PriceMin']) {
		    $ar['PriceMin'] = $prSell;
		}
		$ar['Options'][$sOptKey] = $sOptVal;
		$arTtl[$idTtl] = $ar;
	    }

	    // We might want to add more info later; this is just to get that page resurrected...
	}

	if (is_null($arTtl)) {
	    $ht = '<span class=main>This department appears to be disused. (How did you get here?)</span>';
	} else {
	    $ht = "\n<table class='catalog-summary'>"
	      ."\n<tr><th>Name</th><th>$ Price</th><th>Options</th></tr>";
	    $isOdd = FALSE;
	    foreach($arTtl as $idTtl => $ar) {
		$sName = $ar['Name'];
		$sCatKey = $ar['CatKey'];
		$sCatNum = $sCatNPfx.$sCatKey;
		$urlTitle = KWP_CAT_REL.strtolower($sCatUPfx.$sCatKey);
		$sPrcMin = $ar['PriceMin'];
		$sPrcMax = $ar['PriceMax'];
		$arOpt = $ar['Options'];
		if ($sPrcMin == $sPrcMax) {
		    $sPrc = FormatMoney($sPrcMin);
		} else {
		    $sPrc = FormatMoney($sPrcMin).' &ndash; '.FormatMoney($sPrcMax);
		}
		$sOpt = NULL;
		foreach ($arOpt as $key => $val) {
		    if (!is_null($sOpt)) {
			$sOpt .= '/';
		    }
		    $sOpt .= $val;
		}
		$isOdd = !$isOdd;
		$htCSS = $isOdd?'catalog-stripe':'catalog';

		$ht .= "\n<tr class=\"$htCSS\"><td><a href=\"$urlTitle\">$sCatNum</a> $sName</td><td>$sPrc</td><td>&ensp;$sOpt</td></tr>";
	    }
	    $ht .= "\n</table>";
	}
	return $ht;
    }
    /*
    public function DoPage_OLD() {
	$out = '';
	$idDept = $this->ID;
	if (empty($idDept)) {
	    throw new exception('Department object has no ID');
	}
	//$objSection = new clsPageOutput();
	// calculate the list of item types available in this department
	$rs = $this->Data_forStore();
	//$objTitles = new clsVbzTitle($this->objDB);
	//$objNoImgSect = new clsPageOutput();
	//$objNoImgSect = new clsRTDoc_HTML();
	$cntSections = 0;
	while ($objItTyps->NextRow()) {
	    $cntInPrint = $objItTyps->Row['cntInPrint'];
	    $qtyInStock = $objItTyps->Row['qtyInStock'];
	    $cntAvail = $objItTyps->Row['cntForSale'];
	    if ($cntAvail) {
		$cntSections++;
		$idItTyp = $objItTyps->Row['ID_ItTyp'];
	        $sql = 'SELECT *, ID_Title AS ID, TitleName AS Name, cntInStock FROM _title_ittyps WHERE ((cntForSale) AND (ID_ItTyp='.$idItTyp.') AND (ID_Dept='.$idDept.'));';
		//$sql = 'SELECT t.ID_Title AS ID, t.* FROM qryTitles_ItTyps_Titles AS t WHERE (ID_ItTyp='.$idItTyp.') AND (ID_Dept='.$idDept.');';
		$rsTitles = $this->Engine()->DataSet($sql);

		$arTitles = NULL;
		if ($rsTitles->hasRows()) {
		    while ($rsTitles->NextRow()) {
			// add title to display list
			$arTitles[] = $rsTitles->Values();	// save it in a list
		    }
		    assert('is_array($arTitles)');

  // We've generated the list of titles for this section; now display the section header and titles:
		    $objTitles = new clsTitleList($arTitles);
		    $objTitles->Table($this->Engine()->Titles());
		    $sDescr = $objItTyps->Row['ItTypNamePlr'].':';
		    $objCont = $objTitles->Build($sDescr,new clsRTDoc_HTML());
//$out .= $objCont->DumpHTML();
		    $out .= $objCont->Render();
		} else {
		    echo 'ERROR: No titles found! SQL='.$sql;
		}
		//$objSection->Clear();
	    } else {
		$out .= '<span class=main>Small coding error: this line should never happen.</span>'; // TO DO: log an error
	    }
	}
	if (!$cntSections) {
	    $out .= '<span class=main>This department appears to have been emptied of all leftover stock. (Eventually there will be a way to see what items used to be here.)</span>';
	}
	return $out;
    }
    */
    /*-----
      RETURNS: TRUE if this department affects the catalog number (i.e. if CatKey is non-blank)
    */
    public function AffectsCatNum() {
	return ($this->CatKey() != '');
    }
}
