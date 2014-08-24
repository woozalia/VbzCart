<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Titles UI (non-CMS)
  HISTORY:
    2013-11-13 extracted from page-cat.php
*/
/*%%%%
  PURPOSE: extends clsTitles to handle store UI interactions
*/
class clsTitles_StoreUI extends clsVbzTitles {
    private $objPage;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsTitle_StoreUI');
    }

    // -- SETUP -- //
    // ++ APP FRAMEWORK ++ //

    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }

    // -- APP FRAMEWORK -- //
    // ++ SEARCHING ++ //

    public function SearchRecords_forText($sSearch) {
	$ar = NULL;

	// title catalog number search
	// - do this first so we don't overwrite row data with NULL
	$tItems = $this->ItemTable();
	$rs = $tItems->Search_byCatNum($sSearch);
	if (!is_null($rs)) {
	    while ($rs->NextRow()) {
		$id = $rs->TitleID();
		$ar[$id] = NULL;
	    }
	}

	// title name search
	$rs = $this->Search_forText($sSearch);
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->KeyValue();
		$ar[$id] = $rs->Values();
	    }
	}

	return $ar;
    }
    /*----
      ACTION: Search titles for the given search string, and render the results
      RETURNS: array of results:
	array['forsale.imgs'] = rendered thumbnails of available items found
	array['forsale.text'] = rendered text of available items found
	array['retired.text'] = rendered text of unavailable items found
      2014-08-19 This may be obsolete.
    */
    public function DoSearch($sSearch) {
	$ar = $this->SearchRecords_forText($sSearch);

	if (count($ar)) {
	    $qTForSale = 0;
	    //$qTRetired = 0;
	    $ftForSale = NULL;
	    $ftRetired = NULL;
	    $ftThumbs = NULL;	// thumbnails to show
	    // iterate through titles found
	    foreach ($ar as $id => $row) {
		$rs->Values($row);
		$htTitle = $rs->ShopLink($rs->CatNum()).' '.$rs->NameText();
		if ($rs->ItemsForSale() > 0) {
		    $qTForSale++;
		    $ftForSale .= $htTitle.' - '.$qTForSale.' item'.Pluralize($qTForSale).'<br>';
		    $ftThumbs .= $rs->RenderImages(clsImages::SIZE_THUMB);
		} else {
		    //$qTRetired++;
		    $ftRetired .= $htTitle.'<br>';
		}
	    }

	    $arOut = array(
	      'forsale.imgs'	=> $ftThumbs,
	      'forsale.text'	=> $ftForSale,
	      'retired.text'	=> $ftRetired,
	      );

	} else {
	    $arOut = NULL;
	}
	return $arOut;
    }

    // -- SEARCHING -- //
}
class clsTitle_StoreUI extends clsVbzTitle {

    // ++ CLASS NAMES ++ //

    protected function ImagesClass() {
	return 'clsImages_StoreUI';
    }
    protected function TitlesTopicsClass() {
	return 'clsTitlesTopics_shopUI';
    }

    // -- CLASS NAMES -- //
    // ++ URL CALCULATIONS ++ //

    // TODO: rename this ShopURL_part()
    public function URL_part() {
	return strtolower($this->CatNum('/'));
    }
    protected function ShopURL_part() {
	return $this->URL_part();
    }
    // TODO: rename this ShopURL(); eliminate iBase argument
    public function URL($iBase=KWP_CAT_REL) {
	return $iBase.$this->URL_part();
    }
    protected function ShopURL() {
	return $this->URL();
    }
    public function Link(array $iarAttr=NULL) {	// DEPRECATED - use ShopLink()
	$strURL = $this->URL();
	$htAttr = clsHTML::ArrayToAttrs($iarAttr);
	return '<a'.$htAttr.' href="'.$strURL.'">';
    }
    public function ShopLink($sShow,array $arAttr=NULL) {
	$url = $this->URL();
	$htAttr = clsHTML::ArrayToAttrs($arAttr);
	$htID = $this->RenderIDAttr();
	return '<a'.$htAttr.' href="'.$url.'" id='.$htID.'>'.$sShow.'</a>';
    }
    public function RenderIDAttr() {
	$id = $this->KeyValue();
	return "title-$id";
    }
    public function LinkAbs() {
	$strURL = $this->URL(KWP_CAT);
	return '<a href="'.$strURL.'">';
    }
    public function LinkName() {
	return $this->Link().$this->Name.'</a>';
    }

    // -- URL CALCULATIONS -- //
    // ++ SHOPPING WEB UI API ++ //

    public function DoPage() {
	if ($this->IsNew()) {
	    throw new exception('Trying to display page for empty title record.');
	}

	$idTitle = $this->KeyValue();
	assert('$idTitle');

	// show "small" images for this title
	$ht = $this->ShopPage_Images();
	if (is_null($ht)) {
	    $ht = $this->RenderImgUnavail();
	}

	// list topics for this title
	$ht .= $this->ShopPage_Topics();

	// list available items as table
	$ht .= $this->ShopPage_Items();

	$ht .= "\n<!-- TITLE ID=$idTitle -->\n";
	return $ht;
    }

    // -- SHOPPING WEB UI API -- //
    // ++ SHOPPING WEB UI COMPONENTS ++ //

    /*----
      CALLED BY: internal use only
    */
    protected function RenderImgUnavail() {
	return clsImage_StoreUI::RenderUnavailable();
    /*
	return '<table class=border cellpadding="5">'
	  .'<tbody><tr><td><table class="hdr" cellpadding="2">'
	  .'<tbody><tr><td align="center">'
	  .'<span class="page-title">No Images<br>Available<br></span>for this item<br><b>:-(</b>'
	  .'</td></tr></tbody></table>'
	  .'</td></tr></tbody></table>';
	  */
    }
    protected function RenderDescr() {
	return $this->CatNum().' &ldquo;'.$this->NameText().'&rdquo;';
    }
    public function RenderImages($sSize=NULL) {
	throw new exception('RenderImages() has been renamed RenderImages_forRows().');
    }
    /*----
      WAS PUBLIC because clsTitles_StoreUI::DoSearch() calls it; should probably be PROTECTED
      RETURNS: rendering of images (of the given size) for all titles in the current recordset
    */
    public function RenderImages_forRows($sSize=clsImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords_forRows($sSize);
	//return $rsIm->RenderRecordset_Titles_inline($this);
	return $rsIm->RenderInline_rows();
    }
    /*----
      PUBLIC because clsTitles_StoreUI::DoSearch() calls it
      RETURNS: rendering of images (of the given size) for the current recordset title
    */
    public function RenderImages_forRow($sSize=clsImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords_forRow($sSize);
	//return $rsIm->RenderRecordset_Titles_inline($this);
	return $rsIm->RenderInline_rows();
    }
    /*----
      HISTORY:
	2014-08-17 TitlesTopics::TopicRecords_forID (formerly TopicRecords) now actually does
	  return Topic records, which simplifies the code here a bit.
    */
    protected function ShopPage_Topics() {
	$tbl = $this->TitleTopicTable();
	$rs = $tbl->TopicRecords_forID($this->KeyValue());
	if (is_null($rs) || ($rs->RowCount() == 0)) {
	    return NULL;
	} else {
//	    $rs = $this->TopicTable()->GetData("ID IN ($sql)");

//	    $txt = '<table align=right style="border: solid 1px; background: black;"><tr><td bgcolor=#333333>';
	    $txt = '<table align=right class="catalog-summary"><tr><td bgcolor=#333333>';
	    $txt .= '<b>'.$this->TopicTable()->IndexLink('Topics').'</b>:';
	    while ($rs->NextRow()) {
		$txt .= '<br>- '.$rs->ShopLink_descrip();
	    }
	    $txt .= '</td></tr></table>';
	    return $txt;
	}
    }
    protected function ShopPage_Images() {
	$objImgs = $this->ImageRecords_forRow_small();
	$out = NULL;
	while ($objImgs->NextRow()) {
	    $strImgTag = $objImgs->Value('AttrDispl');
	    $urlRel = $objImgs->Value('Spec');
	    $idImg = $objImgs->KeyValue();
	    $strImg = '<img src="'.$objImgs->WebSpec().'"';
	    if ($strImgTag) {
		$strImg .= ' title="'.$strImgTag.'"';
	    }
	    $strImg .= '>';
	    $objImgBig = $objImgs->ImgForSize(KS_IMG_SIZE_LARGE);
	    if (is_object($objImgBig)) {
		if ($objImgBig->FirstRow()) {
		    $strImg = $objImgBig->Href().$strImg.'</a>';
		}
	    }
	    $out .= $strImg;
	}
	return $out;
    }
    /*----
      PURPOSE: Renders page of available items, with form for adding to cart
      HISTORY:
	2012-02-10 began rewriting to go straight to data (no cache)
    */
    protected function ShopPage_Items() {
	$idTitle = $this->KeyValue();
	$out = NULL;

	$sql = 'SELECT * FROM'
	  .' (cat_items AS i'
	  .' LEFT JOIN cat_ittyps AS it ON i.ID_ItTyp=it.ID)'
	  .' LEFT JOIN cat_ioptns AS io ON i.ID_ItOpt=io.ID'
	  .' WHERE isForSale AND i.ID_Title='.$idTitle
	  .' ORDER BY it.Sort,GrpSort,GrpDescr,i.ItOpt_Sort, io.Sort;';	// sorting may need to be tweaked
	$tblItems = $this->Engine()->Items();
	$rs = $tblItems->DataSQL($sql);
	$isItem = FALSE;
	if ($rs->hasRows()) {
	    $urlCart = KWP_CART;
//	    $out .= '<form method=post action="'.$urlCart.'"><input type=hidden name=from value=browse-multi>';
	    $out .= "\n<form method=post action=\"$urlCart\">";

	    $flagDisplayTogether = false;	// hard-coded for now

	    $txtTblHdr = clsItems::Render_TableHdr();
	    $txtInStock = $txtOutStock = $txtBoth = '';

	    $idItTyp_old = NULL;
	    $strGrp_old = NULL;
	    $this->cntInStk = 0;
	    $this->cntOutStk = 0;


	    // build nested array
	    while ($rs->NextRow()) {
		$id = $rs->KeyValue();
		$row = $rs->Values();
		//$arAll[$id] = $row;

		// determine status and set array key
		$qtyInStk = $rs->Value('QtyIn_Stk');
		$isForSale = $rs->Value('isForSale');
		$sStatKey = ($qtyInStk > 0)?'istk':'ostk';

		// get item type
		$idTyp = $rs->Value('ID_ItTyp');

		// put this item into the nested array
		$ar[$sStatKey][$idTyp][$id] = $row;
	    }

	    // some constants for formatting output
	    $arStatDesc = array(
	      'istk'	=> 'in stock',
	      'ostk'	=> '<a href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'"><b>not in stock</b></a>'
	      );
	    $arStatClass = array(
	      'istk'	=> 'inStock',
	      'ostk'	=> 'noStock'
	      );
	    $htTblHdr = "\n<!-- ITEM TABLE -->\n<table class=main><tbody>";
	    $htTblFtr = "\n<tr><td colspan=4 align=right><input name='"
	      .KSF_CART_BTN_ADD_ITEMS
	      ."' value='Add to Cart' type='submit'></td></tr>"
	      ."\n</tbody></table>";

	    // generate output from nested table
	    foreach ($ar as $sStat => $arTyp) {

		// calculate how many items are in this status group
		$nQty = 0;
		foreach ($arTyp as $idTyp => $arItm) {
		    $nQty += count($arItm);	// how many items are in this type?
		}
		if ($nQty > 0) {
		    $out .= $htTblHdr;
		    $sNoun = Pluralize($nQty,'This item is','These items are');
		    $sStatDesc = $arStatDesc[$sStat];
		    $sStatClass = $arStatClass[$sStat];
		    $out .= "<tr class=$sStatClass><td colspan=5>$sNoun $sStatDesc</td></tr>";
		    $out .= $txtTblHdr;
		    foreach ($arTyp as $idTyp => $arItm) {
			foreach ($arItm as $id => $row) {
			    $rs->Values($row);	// stuff row values into an object for easier access
			    $out .= $rs->Render_TableRow();
			}
		    }
		    $out .= $htTblFtr;
		}
	    }
	    $out .= '</form>';
/*
	    while ($rs->NextRow()) {
		$idItTyp = $rs->Value('ID_ItTyp');
		if ($idItTyp_old != $idItTyp) {
		    // new item type

		    $idItTyp_old = $idItTyp;

		    // render type header
		    $htTypPlur = htmlspecialchars($rs->Value('NamePlr'));
		    $htLine = "\n<tr class=typeHdr><td colspan=3><b>$htTypPlur</b>:</td></tr>";

		    $ar = $this->ShopPage_Items_TypeSection($rs);
		    $txtInStock .= NzArray($ar,'in');
		    $txtOutStock .= NzArray($ar,'out');
		    $txtBoth .= NzArray($ar,'both');
		}

		// determine availability status
		$qtyInStk = $rs->Value('QtyIn_Stk');
		$isForSale = $rs->Value('isForSale');
		$isOutStk = (($qtyInStk == 0) && $isForSale);





		$strGrp = $rs->Value('GrpDescr');
		$qtyStk = $rs->Value('QtyIn_Stk');
		if ($strGrp != $strGrp_old) {
		    $strGrp_old = $strGrp;
		    // new group -- render group header

		    $strGrpCode = $rs->Value('GrpCode');
		    $out = '<tr class="group">';
		    $out .= '<td colspan=5> &mdash; '.$strGrp;
		    if ($strGrpCode) {
			$out .= ' <font color=#666666>(<font color=#666699>'.$strGrpCode.'</font>)</font>';
		    }
		    $out .= '</td>';
		    $out .= '</tr>';
// this should probably be a subroutine...
		    if ($flagDisplayTogether) {
			$txtBoth .= $out;
		    } else {
			if ($qtyStk > 0) {
			    $txtInStock .= $out;
			} else {
			    $txtOutStock .= $out;
			}
		    }
		} // END new group

		$txtLine = $rs->Render_TableRow();

		if ($flagDisplayTogether) {
		    $txtBoth .= $txtLine;
		} else {
		    if ($qtyStk > 0) {
			$txtInStock .= $txtLine;
		    } else {
			$txtOutStock .= $txtLine;
		    }
		}
	    } // END while next row

// format all the accumulated bits of text into one large string:
	    $txtTblOpen = "\n<!-- ITEM TABLE -->\n<table class=main><tbody>";
	    $txtTblFtr = "\n<tr><td colspan=4 align=right><input name='".KSF_CART_BTN_ADD_ITEMS."' value='Add to Cart' type='submit'></td></tr>";
	    $txtTblShut = "\n</tbody></table>";

	    if ($flagDisplayTogether) {
// Display in-stock and backordered items together
		$out .= $txtTblOpen.$txtTblHdr.$txtBoth.$txtTblFtr.$txtTblShut;
	    } else {
// Display in-stock table first, then restockable table:
		if ($this->cntInStk) {
		    $txtClause = Pluralize($qtyStk,'This item is','These items are');
		    $out .= $txtTblOpen;
		    $out .= '<tr class=inStock><td colspan=5>'.$txtClause.' in stock:</td></tr>';
		    $out .= $txtTblHdr.$txtInStock.$txtTblFtr.$txtTblShut;
		}

		if ($this->cntOutStk) {
		    if (!empty($txtInStock)) {
			$out .= '<p>';
		    }
		    $txtClause = Pluralize($this->cntOutStk,'This item is','These '.$this->cntOutStk.' items are');

		    $out .= $txtTblOpen;
		    $out .= '<tr><td colspan=5>'.$txtClause.' <a href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'"><b>not in stock</b></a>';

		    $txtClause = Pluralize($this->cntOutStk,'it','them');

		    $out .= ', but we can (probably) <a href="'.KWP_HELP_POLICY_SHIP.'">get '.$txtClause.'</a>:</td></tr>';
		    $out .= $txtTblHdr.$txtOutStock.$txtTblFtr.$txtTblShut;
		}
	    }
	    $out .= '</form>';
*/
	} else {
	    $oPage = $this->Table()->Page();
	    $out .= $oPage->SectionHeader('This title is currently unavailable');
	}
	return $out;
    }
/*
    protected function ShopPage_Items_TypeSection($rs) {
	$txtTypPlur = $rs->Value('NamePlr');

	$txtLine = "\n<tr class=typeHdr><td colspan=3><b>$txtTypPlur</b>:</td></tr>";

	$flagDisplayTogether = FALSE;	// hard-coded for now
	if ($flagDisplayTogether) {
// displaying all items in a single listing
	    $arOut['both'] = $txtLine;
	} else {
// set flags to determine which stock-status sections to show
	    $qtyInStk = $rs->Value('QtyIn_Stk');
	    $isForSale = $rs->Value('isForSale');
	    $isOutStk = (($qtyInStk == 0) && $isForSale);

	    if ($qtyInStk > 0) {
		// list in the "in stock" section
		$arOut['in'] = $txtLine;
		// add to count of lines in stock
		$this->cntInStk++;
	    }
	    if ($isOutStk) {
		// list in the "out of stock" section
		$arOut['out'] = $txtLine;
		// add to count of lines out of stock
		$this->cntOutStk++;
	    }
	}
	return $arOut;
    }
*/
}
