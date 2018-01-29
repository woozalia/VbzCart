<?php
/*
  PURPOSE: classes removed from vbz-cat-title.php because they're no longer needed
*/

/*%%%%
  CLASS: clsTitleList
  PURPOSE: handles an array of Title objects
  NOTE: This probably needs to be merged with clsTitleLister
    ...except neither this nor clsTitleLister is being used anymore.
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
	    $objImgs = $objTitle->ImageRecords_thumb();
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
class clsTitleLister_DEPRECATED {
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
	if (empty($iRow['ID'])) {
	    throw new exception('Trying to add a row with no ID.');
	}
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

	$rcTitle = $tblTitles->SpawnItem();
	$ftTextActive = NULL;
	$ftTextRetired = NULL;
	$ftImgs = NULL;
	$cntTiAct = 0;
	$cntTiAll = 0;
	foreach ($arTitles as $id => $arT) {
	    if (is_array($arT)) {
		$rcTitle->Values($arT);
	    } else {
		$rcTitle = $tblTitles->GetItem($id);
	    }
	    $arStats = $rcTitle->Indicia();
	    $cntTiAll++;
	    // this is probably going to produce inconsistent results if cnt.active can be >1
	    $cntStat = $arStats['cnt.active'];
	    $cntNew = $rcTitle->ItemsForSale();
	    $cntTiAct += $cntStat;

	    //$intActive = $arStats['cnt.active'];
	    $txtCatNum = $arStats['txt.cat.num'];
	    $ftLine = $arStats['ht.cat.line'];
	    $htLink = $arStats['ht.link.open'];
	    $txtName = $rcTitle->NameStr();

	    if ($cntTiAct) {
		$ftTextActive .= $ftLine.' - '.$cntTiAct.' item'.Pluralize($cntTiAct).'<br>';
	    } else {
		$ftTextRetired .= $ftLine.'<br>';
	    }
	    $txtTitle = $txtCatNum.' &ldquo;'.$txtName.'&rdquo;';

//	    $ftImgs .= $htLink.$tblImages->Thumbnails($id,array('title'=>$txtTitle)).'</a>';
	    $rcImgs = $tblImages->Records_forTitle($id,KS_IMG_SIZE_THUMB);
	    if ($rcTitle->IsForSale()) {
		$arTLink = array(	// [id] = URL
		  'title' => $txtTitle
		  );
		//$ftImgs .= $rcImgs->RenderInLine_set_Titles($arTAttr,$arTLink);
		$htLink = $rcTitle->Link();
		$ftImgs .= $htLink.$rcImgs->RenderInline_set($arTLink).'</a>';
	    }
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

// clsVbzTitle method
    /*----
      RETURNS: Array containing summary information about this title
      DEPRECATED
    */
    public function Indicia_DEPRECATED(array $iarAttr=NULL) {
	$rsItems = $this->Items();
	$intActive = 0;
	$intRetired = 0;
	if ($rsItems->HasRows()) {
	    while ($rsItems->NextRow()) {
		if ($rsItems->IsForSale()) {
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
