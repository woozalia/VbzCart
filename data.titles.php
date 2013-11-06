<?php
/*
  FILE: data.titles.php -- VbzCart data-handling classes: titles
  HISTORY:
    2013-02-09 created; splitting off Title-related classes from base.cat
  CLASSES:
    clsVbzTitles
    clsVbzTitle
    clsTitleIttyp
*/

class clsVbzTitles extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_titles');
	  $this->KeyName('ID');
	  $this->ClassSng('clsVbzTitle');
    }
    public function Search_forText_SQL($iFind) {
	return '(Name LIKE "%'.$iFind.'%") OR (`Desc` LIKE "%'.$iFind.'%")';
    }
    public function Search_forText($iFind) {
	$sqlFilt = $this->Search_forText_SQL($iFind);
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
}
class clsVbzTitle extends clsDataSet {
// object cache
    private $objDept;
    private $objSupp;
// options
    public $hideImgs;

    public function Dept() {
	$doLoad = FALSE;
	if (empty($this->objDept)) {
	    $doLoad = TRUE;
	} else if (is_object($this->objDept)) {
	    if ($this->ID_Dept != $this->objDept->ID) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idDept = $this->ID_Dept;
	    if (empty($idDept)) {
		$objDept = NULL;
	    } else {
		$objDept = $this->objDB->Depts()->GetItem($idDept);
		assert('is_object($objDept)');
	    }
	    $this->objDept = $objDept;
	}
	return $this->objDept;
    }
    /*----
      RETURNS: ID of this title's supplier
      HISTORY:
	2011-09-28 revised to get ID directly from the new ID_Supp field
	  instead of having to look up the Dept and get it from there.
    */
    public function Supplier_ID() {
/*
	$objDept = $this->Dept();
	$idSupp = $objDept->ID_Supplier;
*/
	$idSupp = $this->Value('ID_Supp');
	return $idSupp;
    }
    // DEPRECATED -- use SuppObj()
    public function Supplier() {
	return $this->SuppObj();
    }
    public function SuppObj() {
	$doLoad = FALSE;
	if (empty($this->objSupp)) {
	    $doLoad = TRUE;
	} else if (is_object($this->objSupp)) {
	    if ($this->ID_Supplier != $this->objSupp->ID) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idSupp = $this->Supplier_ID();
	    if (empty($idSupp)) {
		$objSupp = NULL;
	    } else {
		$objSupp = $this->objDB->Suppliers()->GetItem($idSupp);
		assert('is_object($objSupp)');
	    }
	    $this->objSupp = $objSupp;
	}
	return $this->objSupp;
    }
    public function Items() {
	$sqlFilt = 'ID_Title='.$this->ID;
	$objTbl = $this->objDB->Items();
	$objRows = $objTbl->GetData($sqlFilt);
	return $objRows;
    }
    public function Topics() {
	$objTbl = $this->Engine()->TitleTopic_Topics();
	$objRows = $objTbl->GetTitle($this->KeyValue());
	return $objRows;
    }
    /*----
      RETURNS: Array containing summary information about this title
    */
    public function Indicia(array $iarAttr=NULL) {
	$objItems = $this->Items();
	$intActive = 0;
	$intRetired = 0;
	if ($objItems->HasRows()) {
	    while ($objItems->NextRow()) {
		if ($objItems->isForSale) {
		    $intActive++;
		} else {
		    $intRetired++;
		}
	    }
	}
	// "dark-bg" brings up link colors for a dark background
	$arLink = array('class'=>'dark-bg');
	// merge in any overrides or additions from iarAttr:
	if (is_array($iarAttr)) {
	    $arLink = array_merge($arLink,$iarAttr);
	}
	$htLink = $this->Link($arLink);
	$txtCatNum = $this->CatNum();
	$txtName = $this->Name;

	$arOut['cnt.active'] = $intActive;
	$arOut['cnt.retired'] = $intRetired;
	$arOut['txt.cat.num'] = $txtCatNum;
	$arOut['ht.link.open'] = $htLink;
	$arOut['ht.cat.line'] = $htLink.$txtCatNum.'</a> '.$txtName;

	return $arOut;
    }
    /*----
      RETURNS: Array containing summaries of ItTyps in which this Title is available
	array['text.!num'] = plaintext version with no numbers (types only)
	array['text.cnt'] = plaintext version with line counts
	array['html.cnt'] = HTML version with line counts
	array['html.qty'] = HTML version with stock quantities
      HISTORY:
	2011-01-23 written
    */
    public function Summary_ItTyps($iSep=', ') {
	$dsRows = $this->DataSet_ItTyps();
	$outTextNoQ = $outTextType = $outTextCnt = $outHTMLCnt = $outHTMLQty = NULL;
	if ($dsRows->HasRows()) {
	    $isFirst = TRUE;
	    while ($dsRows->NextRow()) {
		$cntType = $dsRows->Value('cntForSale');
		if ($cntType > 0) {
		    $qtyStk = $dsRows->Value('qtyInStock');
		    $txtSng = $dsRows->Value('ItTypNameSng');
		    $txtPlr = $dsRows->Value('ItTypNamePlr');
		    $strType = Pluralize($cntType,$txtSng,$txtPlr);
		    if ($isFirst) {
			$isFirst = FALSE;
		    } else {
			$outTextType .= $iSep;
			$outTextCnt .= $iSep;
			$outHTMLCnt .= $iSep;
			if (!is_null($outHTMLQty)) {
			    $outHTMLQty .= $iSep;
			}
		    }
		    $outTextType .= $txtSng;
		    $outTextCnt .= $cntType.' '.$strType;
		    $outHTMLCnt .= '<b>'.$cntType.'</b> '.$strType;
		    if (!empty($qtyStk)) {
			$outHTMLQty .= '<b>'.$qtyStk.'</b> '.Pluralize($qtyStk,$txtSng,$txtPlr);
		    }
		}
	    }
	}
	$arOut['text.!num'] = $outTextType;
	$arOut['text.cnt'] = $outTextCnt;
	$arOut['html.cnt'] = $outHTMLCnt;
	$arOut['html.qty'] = $outHTMLQty;
	return $arOut;
    }
// LATER: change name to DataSet_Images() to clarify that this returns a dataset, not a text list or array
    public function ListImages($iSize) {
	$sqlFilt = '(ID_Title='.$this->ID.') AND (Ab_Size="'.$iSize.'") AND isActive';
	$objImgs = $this->objDB->Images()->GetData($sqlFilt,'clsImage','AttrSort');
	return $objImgs;
    }
    /*----
      RETURNS: dataset of item types for this title
      USES: _title_ittyps (cached table)
      HISTORY:
	2011-01-19 written
    */
    public function DataSet_ItTyps() {
	$sql = 'SELECT * FROM _title_ittyps WHERE ID_Title='.$this->KeyValue();
	$obj = $this->Engine()->DataSet($sql,'clsTitleIttyp');
	return $obj;
    }
    /*----
      HISTORY:
	2010-10-19 added optimization to fetch answer from CatKey field if it exists.
	  This may cause future problems. Remove $iSep field and create individual functions
	  if so.
	2012-02-02 allowed bypass of Dept if it isn't set
    */
    public function CatNum($iSep='-') {
	if (empty($this->Row['CatNum'])) {

	    $objDept = $this->Dept();
	    $objSupp = $this->SuppObj();
	    if (is_object($objDept)) {
		$strDeptKey = $objDept->CatKey;
		$strOut = $objSupp->CatKey;
		if ($strDeptKey) {
		  $strOut .= $iSep.$strDeptKey;
		}
	    } else {
		if (is_object($objSupp)) {
		    $strOut = $objSupp->CatKey;
		} else {
		    $strOut = '?';
		}
	    }
	    $strOut .= $iSep.$this->CatKey;
	} else {
	    $strOut = $this->CatNum;
	}
	return strtoupper($strOut);
    }
    public function URL_part() {
	return strtolower($this->CatNum('/'));
    }
    public function URL($iBase=KWP_CAT_REL) {
	return $iBase.$this->URL_part();
    }
    public function Link(array $iarAttr=NULL) {
	$strURL = $this->URL();
	$htAttr = ArrayToAttrs($iarAttr);
	return '<a'.$htAttr.' href="'.$strURL.'">';
    }
    public function LinkAbs() {
	$strURL = $this->URL(KWP_CAT);
	return '<a href="'.$strURL.'">';
    }
    public function LinkName() {
	return $this->Link().$this->Name.'</a>';
    }
}

/*====
  PURPOSE: TITLE/ITTYP hybrid
  TABLE: _title_ittyps
*/
class clsTitleIttyp extends clsDataSet {
// object cache
  private $objIttyp;

  public function Ittyp() {
    if (is_null($this->objIttyp)) {
      $this->objIttyp = VbzClasses::ItTyps()->GetItem($this->ID_ItTyp);
    }
    return $this->objIttyp;
  }
}
/*%%%%
  CLASS: clsTitleList
  PURPOSE: handles an array of Title objects
  NOTE: This probably needs to be merged with clsTitleLister
*/
class clsTitleList {
    protected $arList;
    protected $oTitles;

    public function __construct(array $iList) {
	$this->arList = $iList;
    }
    public function Table(clsVbzTitles $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oTitles = $iObj;
	}
	return $this->oTitles;
    }
    /*----
      NOTES:
	2011-02-02 This really should somehow provide a more general service. Right now, it's only used for displaying
	  department pages -- but topic pages and searches could make use of it as well, if there were some tidy way
	  to abstract the collecting of the price and stock data. This will probably necessitate adding hi/low price
	  data to _titles and possibly _dept_ittyps (it's already in _title_ittyps).
      HISTORY:
	2011-02-02
	  * Removed assertion that $objImgs->Res is a resource, because if there are no thumbnails for this title,
	    then apparently it isn't, and apparently RowCount() handles this properly.
	  * Also fixed references to qtyForSale -- should be qtyInStock.
	2013-02-09 moved/adapted from clsVbzData::ShowTitles() to clsTitleList::Render()
    */
    public function Build($iHdrText,clsRTDoc $iPage) {
	$cntImgs = 0;
	$outImgs = '';
	$objSect = NULL;
	$arList = $this->arList;
	$objCont = $iPage->NewSection($iHdrText,2);	// object for page contents
	foreach ($arList as $i => $row) {
	    $objTitle = $this->Table()->GetItem($row['ID']);
	    $objImgs = $objTitle->ListImages('th');
	    //assert('is_resource($objImgs->Res); /* TYPE='.get_class($objImgs).' SQL='.$objImgs->sqlMake.' */');
	    $currMinPrice = $row['currMinPrice'];
	    $currMaxPrice = $row['currMaxPrice'];
	    $strPrice = DataCurr($currMinPrice);
	    if ($currMinPrice != $currMaxPrice) {
		$strPrice .= '-'.DataCurr($currMaxPrice);
	    }
	    if ($objImgs->RowCount()) {
		$cntImgs++;
		$strCatNum = $objTitle->CatNum();
		$strTitleTag = '&quot;'.$objTitle->Name.'&quot; ('.$strCatNum.')';
		$strTitleLink = $objTitle->Link();
		while ($objImgs->NextRow()) {
		    $strImgTag = $strTitleTag.' - '.$strPrice;
		    $qtyStk = $row['qtyInStock'];
		    if ($qtyStk) {
			$strImgTag .= ' - '.$qtyStk.' in stock';
		    }
		    $outImgs .= $strTitleLink.'<img class="thumb" src="'.$objImgs->WebSpec().'" title="'.$strImgTag.'"></a>';
		}
	    } else {
		if (is_null($objSect)) {
		    $objSect = $iPage->NewSection('titles without images:',3);	// object for section - titles w/out images
		    $objTbl = $iPage->NewTable();
		    $objTbl->ClassName('catalog-summary');
		    $objRow = $objTbl->NewHeader();
		    $objRow->ClassName('main');
		    $objRow->NewCell('Cat. #');
		    $objRow->NewCell('Title');
		    $objRow->NewCell('Price Range');
		    $objRow->NewCell('to<br>order');
		    $objRow->NewCell('status');
		}
		$objRow = $objTbl->NewRow();
		$objRow->NewCell('<b>'.$objTitle->CatNum().'</b>');
		$objRow->NewCell($objTitle->Name);
		$objRow->NewCell($strPrice);
		$objRow->NewCell('<b>[</b>'.$objTitle->Link().'order</a><b>]</b>');
		$qtyStk = $row['qtyInStock'];
		if ($qtyStk) {
		    $objRow->ClassName('inStock');
		    $strStock = '<b>'.$qtyStk.'</b> in stock';
		    if ($row['cntInPrint'] == 0) {
			$strStock .= ' - OUT OF PRINT!';
		    }
		    $objRow->NewCell($strStock);
		} else {
		    $objRow->NewCell('<a title="explanation..." href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available, not in stock</a>');
	// Debugging:
	//          $objNoImgSect->ColAdd('ID_Title='.$objTitle->ID.' ID_ItTyp='.$objTitle->idItTyp);
		}
	    }
	}
	if ($cntImgs) {
	    $objCont->AddText($outImgs);
	}
	return $iPage;
    }

}
/*%%%%
  NOTE: This probably needs to be merged with clsTitleList
  USED BY: page-search.php - clsPageSearch::DoContent()
*/
class clsTitleLister {
    private $tblTitles;
    private $tblImages;
    private $arTitles;

    public function __construct(clsVbzTitles $iTitles, clsImages $iImages) {
	$this->tblTitles = $iTitles;
	$this->tblImages = $iImages;
	$this->arTitles = NULL;
    }
    /*----
      INPUT:
	iRow: if not given, row will be retrieved from database at render time.
	  This is probably more efficient anyway, since it avoids duplicate lookups.
    */
    public function Add($id,array $iRow=NULL) {
	if (!isset($this->arTitles[$id])) {
	    $this->arTitles[$id] = $iRow;
	}
    }
    public function Reset() {
	$this->arTitles = array();
    }
    public function Count() {
	return count($this->arTitles);
    }
    public function Render() {
	$tblTitles = $this->tblTitles;
	$tblImages = $this->tblImages;
	$arTitles = $this->arTitles;
	
	$obj = $tblTitles->SpawnItem();
	$ftTextActive = NULL;
	$ftTextRetired = NULL;
	$ftImgs = NULL;
	$cntTiAct = 0;
	$cntTiAll = 0;
	foreach ($arTitles as $id => $arT) {
	    if (is_array($arT)) {
		$obj->Values($arT);
	    } else {
		$obj = $tblTitles->GetItem($id);
	    }

	    $arStats = $obj->Indicia();
	    $cntTiAll++;
	    // this is probably going to produce inconsistent results if cnt.active can be >1
	    $cntTiAct += $arStats['cnt.active'];

	    //$intActive = $arStats['cnt.active'];
	    $txtCatNum = $arStats['txt.cat.num'];
	    $ftLine = $arStats['ht.cat.line'];
	    $htLink = $arStats['ht.link.open'];
	    $txtName = $obj->Name;

	    if ($cntTiAct) {
		$ftTextActive .= $ftLine.' - '.$cntTiAct.' item'.Pluralize($cntTiAct).'<br>';
	    } else {
		$ftTextRetired .= $ftLine.'<br>';
	    }
	    $txtTitle = $txtCatNum.' &ldquo;'.$txtName.'&rdquo;';

	    $ftImgs .= $htLink.$tblImages->Thumbnails($id,array('title'=>$txtTitle)).'</a>';
	}
	$arOut['txt.act'] = $ftTextActive;
	$arOut['txt.ret'] = $ftTextRetired;
	$arOut['img'] = $ftImgs;
	$arOut['cnt.act'] = $cntTiAct;
	$arOut['cnt.all'] = $cntTiAll;
	$this->Reset();
	return $arOut;
    }
}
