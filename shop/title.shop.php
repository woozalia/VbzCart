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
class vctShopTitles extends vctTitles {
    use ftFrameworkAccess;

    // ++ CEMENTING ++ //

    protected function SingularName() {
	return 'vcrShopTitle';
    }

    // -- CEMENTING -- //
    // ++ RECORDS ++ //
    
    // PUBLIC so Supplier or Department object can call it
    public function LookupExhibitRecord(array $arThap) {
	$sNext = array_pop($arThap);
	$anyMore = count($arThap) > 0;
	// TODO: look for image sizes or whatever
	throw new exception('Not written yet -- need to look up ['.$sNext.'] in title '.$this->NameString());
    }

    // -- RECORDS -- //
    // ++ SEARCHING ++ //

    /*----
      HISTORY:
	2016-02-21
	* This searches Item records as a way of searching Title catalog numbers,
	  but the proper way to do that is either to build a Title query that generates those,
	  or else add a calculated CatNum field to the Titles table.
	  
	  I was going to modify it to only search CatKey and Name, and to return a recordset
	  instead of an array, but then I figured out that this is equivalent to just calling
	  Search_forText() -- so I'm deprecating this method in favor of that one.
	  
	  This will disable full catalog # searching -- but that can be done properly later if it is needed.
    */
    public function SearchRecords_forText($sSearch) {
	throw new exception("Call Search_forText() instead of SearchRecords_forText().");
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
		$id = $rs->GetKeyValue();
		$ar[$id] = $rs->GetFieldValues();
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
		    $ftThumbs .= $rs->RenderImages(vctImages::SIZE_THUMB);
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
class vcrShopTitle extends vcrTitle {

    // ++ CLASS NAMES ++ //

    protected function SuppliersClass() {
	return KS_CLASS_SHOP_SUPPLIERS;
    }
    protected function DepartmentsClass() {
	return KS_CLASS_SHOP_DEPARTMENTS;
    }
    protected function ImagesClass() {
	return 'vctImages_StoreUI';
    }
    protected function TopicsClass() {
	return 'vctShopTopics';
    }
    protected function XTopicsClass() {
	return 'vctTitlesTopics_shop';
    }

    // -- CLASS NAMES -- //
    // ++ RECORDS ++ //
    
    // PUBLIC so Supplier object can call it
    public function LookupExhibitRecord(array $arThap) {
	$sNext = array_pop($arThap);	// Image view spec
	$sFork = NULL;
	$anyMore = count($arThap) > 0;
	// look for Image view
	$rc = $this->GetImageRecord_byCatKey($sNext);
	return $rc;
    }
    protected function GetImageRecord_byCatKey($sCatKey) {
	$idTitle = $this->GetKeyValue();
	$rc = $this->ImageTable()->GetImageRecord_byTitle_andCatKey($idTitle,$sCatKey);
	return $rc;
    }

    // -- RECORDS -- //
    // ++ URL CALCULATIONS ++ //

    // TODO: rename this ShopURL_part()
    public function URL_part() {
	throw new exception('2017-03-16 Call ShopURL_part() instead.');
    }
    // TODO: come up with a better name
    protected function ShopURL_part() {
	return strtolower($this->CatPage('/'));
    }
    // TODO: rename this ShopURL(); eliminate iBase argument
    public function URL($iBase /*=KWP_CAT_REL*/) {
	throw new exception('2017-03-16 This really should not be in use anymore.');
	return $iBase.$this->URL_part();
    }
    // PUBLIC so Image records can access it
    public function ShopURL() {
	$wpCat = vcGlobals::Me()->GetWebPath_forCatalogPages();
	$wpInfo = $this->ShopURL_part();
	return $wpCat.$wpInfo;
    }
    public function Link(array $iarAttr=NULL) {	// DEPRECATED - use ShopLink()
	throw new exception('2017-03-16 Call ShopLink() instead.');
	$strURL = $this->URL();
	$htAttr = fcHTML::ArrayToAttrs($iarAttr);
	return '<a'.$htAttr.' href="'.$strURL.'">';
    }
    public function ShopLink($sShow,array $arAttr=NULL) {
	$url = $this->ShopURL();
	$htAttr = fcHTML::ArrayToAttrs($arAttr);
	$htID = $this->RenderIDAttr();
	return '<a'.$htAttr.' href="'.$url.'" id='.$htID.'>'.$sShow.'</a>';
    }
    public function RenderIDAttr() {
	$id = $this->GetKeyValue();
	return "title-$id";
    }
    public function LinkAbs() {
	throw new exception('2017-03-16 Is anything using this?');
	$strURL = $this->URL(KWP_CAT);
	return '<a href="'.$strURL.'">';
    }
    public function LinkName() {
	return $this->ShopLink($this->NameString());
    }

    // -- URL CALCULATIONS -- //
    // ++ SHOPPING PAGES ++ //
    
    public function ExhibitSuperTitle() {
	return
	  'items <a href="'.vcGlobals::Me()->GetWebPath_forCatalogPages()
	  .'">supplied</a> by '
	  .$this->SupplierRecord()->ShopLink()."'s "
	  .$this->DepartmentRecord()->ShopLink().' department:'
	  ;
    }
    public function ExhibitMainTitle() {
	return $this->NameString();
    }
    public function ExhibitContent() {
	if ($this->IsNew()) {
	    throw new exception('Trying to display page for empty title record.');
	}

	$idTitle = $this->GetKeyValue();
	
	$out = "\n<!-- + TITLE ID=$idTitle + -->\n";

	$rs = $this->ItemRecords_forExhibit();
	$isForSale = $rs->HasRows();
	
	if (!$isForSale) {
	    $out .= vcGlobals::Me()->GetMarkup_forNoItems();
	}
	
	// show "small" images for this title
	$htImg = $this->ShopPage_Images();
	if (is_null($htImg)) {
	    $out .= $this->RenderImgUnavail();
	} else {
	    $out .= $htImg;
	}

	// list topics for this title
	$out .= $this->ShopPage_Topics();

	// list available items as table
	//$out .= $this->ShopPage_Items();
	
	if ($isForSale) {
	    $urlCart = vcGlobals::Me()->GetWebPath_forCartPage();
	    $out .=
	      "\n<form method=post action=\"$urlCart\">"
	      .$rs->Render_TitleListing()
	      ;
/*	} else {
	    $oPage = $this->Table()->PageObject();
	    $out .= 
	      $oPage->SectionHeader('No items available')
	      .KHTML_TITLE_EXISTS_NO_ITEMS
	      ;//*/
	}

	$out .= "\n<!-- - TITLE ID=$idTitle - -->\n";
	return $out;
    }
    
    // -- SHOPPING PAGES -- //
    // ++ SHOPPING PAGE COMPONENTS ++ //

    /*----
      CALLED BY: internal use only
    */
    protected function RenderImgUnavail() {
	return vcrImage_StoreUI::RenderUnavailable();
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
      WAS PUBLIC because vctShopTitles::DoSearch() calls it; should probably be PROTECTED
      RETURNS: rendering of images (of the given size) for all titles in the current recordset
    */
    public function RenderImages_forRows($sPopup,$sSize=vctImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords_forRows($sSize);
	//return $rsIm->RenderRecordset_Titles_inline($this);
	return $rsIm->RenderInline_rows($sPopup,$sSize);
    }
    /*----
      HISTORY:
	2017-03-17 written (currently used only by admin fx(), but why isn't it used for the shopping page?)
    */
    protected function RenderImages_forRow($sPopup,$sSize=vctImages::SIZE_SMALL) {
	$rsIm = $this->ImageRecords_forRow($sSize);
	return $rsIm->RenderInline_rows($sPopup,$sSize);
    }
    /*----
      PUBLIC because vctShopTitles::DoSearch() calls it
      RETURNS: rendering of images (of the given size) for the current recordset title
    */
    /* 2016-02-22 this functionality should be moved to vcqrTitleInfo
    public function RenderImages_forRow($sSize=clsImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords_forRow($sSize);
	//return $rsIm->RenderRecordset_Titles_inline($this);
	return $rsIm->RenderInline_rows();
    }//*/
    /*----
      HISTORY:
	2014-08-17 TitlesTopics::TopicRecords_forID (formerly TopicRecords) now actually does
	  return Topic records, which simplifies the code here a bit.
    */
    protected function ShopPage_Topics() {
	$tbl = $this->XTopicTable();
	$rs = $tbl->TopicRecords_forID($this->GetKeyValue());
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
    /*----
      HISTORY:
	2016-02-05 Fixed the bug where no image was showing if there was no Big image to link to.
    */
    protected function ShopPage_Images() {
	$rsImgs = $this->ImageRecords_forRow_small();
	$out = NULL;
	while ($rsImgs->NextRow()) {
	
	    $sImgTitle = $rsImgs->GetFieldValue('AttrDispl');

	    // generate the image tag (but not the link):
	    $htImg = '<img src="'.$rsImgs->WebSpec().'"';
	    if ($sImgTitle) {
		$htImg .= ' title="'.$sImgTitle.'"';
	    }
	    $htImg .= '>';

	    $rcImgBig = $rsImgs->ImgForSize(KS_IMG_SIZE_LARGE);
	    if (is_object($rcImgBig)) {
		if ($rcImgBig->NextRow()) {	// get the first row
		    // Large size found -- wrap the <img> tag in a link to that:
		    $htImg = $rcImgBig->RenderPageLink($htImg,TRUE);
		}
	    }
	    $out .= $htImg;
	}
	return $out;
    }
    protected function ItemRecords_forExhibit() {
	$idTitle = $this->GetKeyValue();
	$rs = $this->GetTableWrapper()->ItemInfoQuery()->Records_forTitle($idTitle);
	return $rs;
    }
    /*----
      PURPOSE: Renders page of available items, with form for adding to cart
      HISTORY:
	2012-02-10 began rewriting to go straight to data (no cache)
	2016-01-23 rewriting from scratch, using actual stock data
    */
    protected function ShopPage_Items() {
	throw new exception('2017-06-29 When does this get called (as opposed to ExhibitContent())?');
	$rs = $this->ItemRecords_forExhibit();
	$out = NULL;
	if ($rs->hasRows()) {
	    $urlCart = KWP_CART;
	    $out .=
	      "\n<form method=post action=\"$urlCart\">"
	      .$rs->Render_TitleListing()
	      ;
	} else {
	    $oPage = $this->Table()->PageObject();
	    $out .= 
	      $oPage->SectionHeader('No items available')
	      .KHTML_TITLE_EXISTS_NO_ITEMS
	      ;
	}
	return $out;
    }
    
    // -- SHOPPING PAGE COMPONENTS -- //

}
