<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Topics UI (non-CMS)
  HISTORY:
    2013-11-17 extracted clsTopic[s]_StoreUI from vbz-page-topic.php
*/
class clsTopics_StoreUI extends clsTopics {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsTopic_StoreUI');
    }
    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
    /*----
      ACTION: Searches all topics for a match to the given search text
      RETURNS: HTML rendering of just the thumbnail images for the found topics
    */
    public function DoSearch($sSearch,$sPfx,$sSep,$sSfx=NULL) {
	$rs = $this->Search_forText($sSearch);		// call non-UI f() to get raw data
	$out = $rs->RenderThumbs();
	return $out;
    }
}
class clsTopic_StoreUI extends clsTopic {

    // ++ METHOD OVERRIDES ++ //

    /*----
      PURPOSE: This is kind of a slapdash way of handling the fact that
	the general recordset class expects to have access to a menu
	system from which to determine the root URL.
      TODO: Get rid of this method.
    */
    public function AdminLink($sText=NULL,$sPopup=NULL,array $arArgs=NULL) {
	$url = KWP_SHOP_TOPICS.$this->FldrName();
	if (is_null($sText)) {
	    $sText = $this->FldrName();
	}
	if (is_null($sPopup)) {
	    $sPopup = htmlspecialchars($this->NameFull());
	}
	return "<a href='$url' title='$sPopup'>$sText</a>";
    }

    // -- METHOD OVERRIDES -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsTitles_StoreUI';
    }
    protected function ImagesClass() {
	return 'clsImages_StoreUI';
    }
    protected function XTitlesClass() {
	return 'clsTitlesTopics_shopUI';
    }

    // -- CLASS NAMES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      PUBLIC because Title::ShopPage_Topics() calls it.
	...except now it calls ShopLink_name() instead, so reverted to PROTECTED.
    */
    protected function ShopLink($sText=NULL) {
	if (is_null($sText)) {
	    $sText = $this->FldrName();
	}
	$out = $this->LinkOpen().$sText.'</a>';
	return $out;
    }
    /* 2014-08-17 old version
    public function ShopLink($iShow=NULL) {
	$txtShow = is_null($iShow)?($this->Value('Name')):$iShow;

	$out = $this->LinkOpen().$txtShow.'</a>';
	return $out;
    }*/
    /*----
      PUBLIC because clsTopics_StoreUI::DoSearch() calls it
    */
    public function ShopLink_name() {
	return $this->ShopLink($this->NameFull());
    }
    public function ShopLink_descrip() {
	$out = $this->ShopLink_name();
	if ($this->HasParent()) {
	    $out .= ' (in '.$this->ParentRecord()->ShopLink_name().')';
	}
	return $out;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ WEB UI COMPONENTS ++ //

    public function Tree_RenderTwig($iCntTitles) {
	$cntTitles = $iCntTitles;
	$txtNoun = ' title'.Pluralize($cntTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
	return $out;
    }
    public function RenderBranch($iSep=" &larr; ") {
	$out = $this->ShopLink();
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentRecord()->RenderBranch($iSep);
	}
	return $out;
    }
    public function RenderBranch_text($iSep=" &larr; ") {
	$out = $this->Value('Name');
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentRecord()->RenderBranch_text($iSep);
	}
	return $out;
    }
    public function LinkOpen() {
	if ($this->doBranch()) {
	    $txtNameFull = $this->RenderBranch_text();
	} else {
	    $txtNameFull = htmlspecialchars($this->NameFull());
	}
	return '<a class="dark-bg" href="'.$this->ShopURL().'" title="'.$txtNameFull.'">';
    }
    protected function RenderImages($sSize=clsImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords($sSize);
	return $rsIm->RenderInline_rows();
    }
    public function RenderThumbs() {
	return $this->RenderImages(clsImages::SIZE_THUMB);	// forces thumbnail size
    }

    /* 2014-08-12 wreck of previous version
    public function RenderThumbs() {
	if ($this->HasRows()) {
	    $oPage = $this->Engine()->App()->Page();
	    $out = NULL;
	    $qTForSale = 0;
	    //$qTRetired = 0;
	    $ftForSale = NULL;
	    $ftRetired = NULL;
	    $ftThumbs = NULL;	// thumbnails to show
	    // iterate through topics found
	    while ($this->NextRow()) {
		$out .= $oPage->Skin()->SectionHeader($this->AdminLink().' '.$this->NameFull());

		$htName = $this->NameFull();
		$qForSale = $this->ItemsForSale();
		if ($qForSale > 0) {
		    $qTForSale++;
		    $sDescr = $htName.' - '.$qTForSale.' item'.Pluralize($qTForSale);
		} else {
		    //$qTRetired++;
		    $sDescr = $htName;
		}
		$rcTtl
		//$ftThumbs .= $this->RenderImages(clsImages::SIZE_THUMB);

		// 2014-08-10 this routine should not try to group images by status; that's done by the caller

		$htLink = $this->ShopLink_name();
		$htDescr = htmlspecialchars($sDescr);
		$out .= "<a href='$htLink' title='$htDescr'>$ftThumbs</a>";
	    }
	} else {
	    $out = NULL;
	}
	return $out;
    }
*/
    // -- WEB UI COMPONENTS -- //
    // ++ SHOPPING WEB UI PAGES ++ //

    public function RenderPage() {
	$arPth = $this->BranchArray();		// path from this to root
	$rsSer = $this->SeriesRecords();	// topics at same level as this one
	$rsSub = $this->KidsRecords();		// topics under this one
	$rsTtl = $this->TitleRecords_forRow();	// titles for this topic (Title recordset)
	$rsImg = $rsTtl->ImageRecords_thumb();	// images for those titles (recordset) (defaults to thumbsize)

	$rc = $this->Table->SpawnItem();	// we need to show info about other topics
	$idThis = $this->KeyValue();

	// TOPIC SERIES

	$ht = "\n".'<table class="catalog-summary" style="float: right;"><tr><td>';
	$ht .= "\n<b>Series</b>:";

	while ($rsSer->NextRow()) {
	    $idRow = $rsSer->KeyValue();
	    //$arRow = $arBits['row'];	// do we even need the other bits?
	    $sName = "\n".$rsSer->NameTree();

	    if ($idRow == $idThis) {
		$htLink = '<b>'.$sName.'</b>';
	    } else {
		$htLink = $rsSer->ShopLink($sName);
	    }

	    $ht .= "\n<br>$htLink";
	}
	$ht .= "\n</td></tr></table>";

	// PATH TO ROOT
	$ht .= "\n".'<table class="catalog-summary"><tr><td>';
	$ht .= $this->RenderBranch();
	$ht .= "\n</td></tr></table>";

	// SUBTOPICS

	if ($rsSub->HasRows()) {
	    $ht .= "\n".'<table class="catalog-summary"><tr><td>';
	    $ht .= "\n<b>Sub-Topics</b>:";
	    while ($rsSub->NextRow()) {
		$ht .= "\n ".$rsSub->ShopLink($rsSub->NameTree());
	    }
	    $ht .= "\n</td></tr></table>";
	}

	// TITLES
/*
	For each title:
	    [row] = raw Title record
	    [stats] = count of active and retired (not for sale) items
	      [cnt.act] = count of active (for sale) items
	      [cnt.ret] = count of retired (no longer for sale) items
*/

	if ($rsTtl->HasRows()) {
	    // generate text listings of titles
	    $htForSaleTxt = NULL;
	    $htRetiredTxt = NULL;
	    $sqlForSale = NULL;
	    while ($rsTtl->NextRow()) {
		$sCatNum = $rsTtl->CatNum();
		$htLink = $rsTtl->ShopLink($sCatNum);
		$sName = $rsTtl->NameStr();

		$htTitle = "\n$htLink &ldquo;$sName&rdquo;";
		//$txtTitle = "$sCatNum &ldquo;$sName&rdquo;";
		$qAct = $rsTtl->ItemsForSale();
		if ($qAct > 0) {
		    $htForSaleTxt .= $htTitle.'<br>';
		    if (!is_null($sqlForSale)) {
			$sqlForSale .= ',';
		    }
		    $sqlForSale .= $rsTtl->KeyValue();
		} else {
		    if (!is_null($htRetiredTxt)) {
			$htRetiredTxt .= ' / ';
		    }
		    $htRetiredTxt .= $htTitle;
		}
	    }

	    if (is_null($sqlForSale)) {
		$htForSaleImg = NULL;
	    } else {
		// there's got to be a tidier way of doing this...
		$rsImg = $this->ImageTable()->Records_forTitles_SQL($sqlForSale,clsImages::SIZE_THUMB);
		//$htForSaleImg = $rsImg->RenderRecordset_Titles_inline($rsTtl);
		$htForSaleImg = $rsImg->RenderInline_rows();
	    }

	    $oSkin = $this->Engine()->App()->Page()->Skin();

	    $ht .= $oSkin->SectionHdr('&darr; Titles available');
	    if (!is_null($htRetiredTxt)) {
		$ht .=
		  '<span class=catalog-summary>'.$htForSaleTxt.'</span>'
		  .$htForSaleImg;
	    }
	    if (!is_null($htRetiredTxt)) {
		$ht .=
		  $oSkin->SectionHdr('&darr; Titles NOT available')
		  .'<span class=catalog-summary>'.$htRetiredTxt.'</span>';
	    }
	} else {
	    $ht .= "\nThis topic currently has no titles.";
	}
/* 2014-03-21 old code for $arTtl
	    $rc = $this->Engine()->Titles()->SpawnItem();	// get a blank Title object
	    $rcImg = $this->Engine()->Images()->SpawnItem();	// get a blank Image object
	    $arLink = array('class'=>'dark-bg');		// attributes for links
	    $htImgAct = NULL;
	    $htImgRet = NULL;
	    $oSkin = $this->Engine()->App()->Skin();
	    $sIDsAct = NULL;
	    $sIDsRet = NULL;

	    foreach($arTtl as $id => $arBits) {
		$arR = $arBits['row'];
		$arS = $arBits['stats'];

		$rc->Values($arR);
		$qRows = $arS['cnt.act'];

		$sCatNum = $rc->CatNum();
		$sName = $rc->NameStr();
		//$htLink = $rc->Link($arLink);

		//$ftLine = $sCatNum.'</a> '.$sName;
		$htTitle = "$sCatNum &ldquo;$sName&rdquo;";
		$arIAttr[$id] = array(
		  'class'	=> 'dark-bg',		// TODO: this should be skin-appropriate
		  'title'	=> $htTitle
		  );
		$arILink[$id] = $rc->URL();

		$qAct = $arS['cnt.act'];

		// build list of Titles for which we want Images
		if ($qAct > 0) {
		    if (!is_null($sIDsAct)) {
			$sIDsAct .= ',';
		    }
		    $sIDsAct .= $id;
		} else {
		    if (!is_null($sIDsRet)) {
			$sIDsRet .= ',';
		    }
		    $sIDsRet .= $id;
		}
	    }
	    $tImg = $this->Engine()->Images();
	    $rsIAct = $tImg->Records_forTitles_SQL($sIDsAct,KS_IMG_SIZE_THUMB);
	    $rsIRet = $tImg->Records_forTitles_SQL($sIDsRet,KS_IMG_SIZE_THUMB);

	    if (is_null($rsIAct)) {
		$htImgAct = '(no images available)';
	    } else {
		$htImgAct = $rsIAct->RenderInLine_set_Titles($arIAttr,$arILink);
	    }
	    if (is_null($rsIRet)) {
		$htImgAct = '(no images available)';
	    } else {
		$htImgRet = $rsIRet->RenderInLine_set_Titles($arIAttr,$arILink);
	    }

	    if (!is_null($htImgAct)) {
		$ht .= $oSkin->SectionHdr('Titles Available');
		$ht .= $htImgAct;
	    } else {
		$ht .= "\n<p><i>There are no active titles for this topic.</i></p>";
		// There must be inactive titles only, if we get here.
	    }

	    if (!is_null($htImgRet)) {
		//$ht .= $oSkin->HLine(3);	// this seems to force a 'clear'
		$ht .= $oSkin->SectionHdr('Titles NOT Available');
		$ht .= <<<__END__
<p class="catalog-summary">These titles are <b>no longer available</b>:</p>
$htImgRet
__END__;
	    }
	}
	    */
	return $ht;
    }

    public function DoPage() {
	$objPage = $this->Table()->Page();
	$objDoc = $objPage->Doc();
	if ($this->HasValue('ID')) {

	    // SERIES

	    $ht = $this->DoPiece_Stat_Series();
	    if (!is_null($ht)) {
		$objCell = $objDoc->NewBox($ht);
		$objCell->Table()->ClassName('catalog-summary');
		$objCell->Table()->SetAttrs(array('align'=>'right'));
	    }

	    // PATH TO ROOT

	    $objText = $objDoc->NewText('<p class="catalog-summary">'.$this->DoPiece_Stat_Parent().'</p>');

	    // SUBTOPICS

	    $ht = $this->DoPiece_Stat_Kids();
	    if (!is_null($ht)) {
		$objText = $objDoc->NewText('<p class="catalog-summary">'.$ht.'</p>');
	    }

	    //$objDoc->NewText($this->DoPiece_Stats());

	    // TITLES FOR TOPIC
echo 'GOT TO HERE';
	    $this->DoFigure_Titles();
	    if ($this->hasTitles) {
		$arInfo = $this->arTitleInfo;
		$ftImgsAct = $arInfo['img.act'];
		$ftImgsRet = $arInfo['img.ret'];
		$objActive = $arInfo['obj.act'];
		$ftTextRetired = $arInfo['txt.ret'];

		$oSkin = $objPage->Skin();

		if (is_object($objActive)) {
		    $objDoc->NewText($oSkin->SectionHdr('Titles Available'));

		    $objDoc->NewText($ftImgsAct);
		    $objActive->ClassName('catalog-summary');
		    $objDoc->NodeAdd($objActive);
		} else {
		    $objDoc->NewText('<p><i>No active titles for this topic.</i></p>');
		}
		if (!empty($ftTextRetired)) {
		    $objDoc->NewText($oSkin->HLine(3));
		    $objDoc->NewText($oSkin->SectionHdr('Titles Not Available'));
		    $objTxt = $objDoc->NewText('<p class="catalog-summary">These titles are <b>no longer available</b>:</p>');
		      $objTxt->ClassName('catalog-summary');
		    $objDoc->NewText($ftImgsRet);
		    $objTbl = $objDoc->NewTable();
		    $objTbl->ClassName('catalog-summary');
		    $objRow = $objTbl->NewRow();

		    $objRow = $objTbl->NewRow();
		    $objRow->NewCell('<small>'.$ftTextRetired.'</small>');
		}
	    } else {
		$objDoc->AddText('This topic currently has no titles.');
	    }
	} else {
	    $objDoc->AddText('There is currently no topic with this ID.');
	}
    }
    /*----
      RETURNS: Parent topic, formatted for store display page
      HISTORY:
	2011-02-23 Split off from DoPage() for SpecialVbzCart
    */
    public function DoPiece_Stat_Parent() {
	if ($this->HasParent()) {
	    $obj = $this->ParentRecord();
	    $out = '<b>Found in</b>: '.$obj->RenderBranch();
	} else {
	    $out = 'This is a top-level topic.';
	}
	$out .= '&larr;['.$this->Table()->IndexLink('Master Index').']<br>';
	return $out;
    }
    /*
      RETURNS: list of other topics at same level, formatted for store display page
      HISTORY:
	2011-02-23 Split off from DoPage() for SpecialVbzCart
	2013-10-11 It's confusing if we do the format differently depending on how
	  many entries there are, so let's just always do it the same way. $doBox=TRUE.
    */
    public function DoPiece_Stat_Series() {
	$out = NULL;
	$obj = $this->Table->GetData($this->SQL_Filt_Series(),NULL,'Sort, NameTree, Name, NameFull');
	if ($obj->HasRows()) {

	    $cntRows = $obj->RowCount();
	    //$doBox = ($cntRows > 5);	// this number is somewhat arbitrary
	    $doBox = TRUE;
	    $out .= '<b>Series</b>:';
	    while ($obj->NextRow()) {
		$id = $this->KeyValue();
		if ($doBox) {
		    $txt = $obj->NameTree();
		} else {
		    $txt = $obj->Value('Name');
		}

		if ($obj->KeyValue() == $id) {
		    $htLink = '<b>'.$txt.'</b>';
		} else {
		    $htLink = $obj->ShopLink($txt);
		}
		if ($doBox) {
		    $out .= '<br>'.$htLink;
		} else {
		    $out .= ' '.$htLink;
		}
	    }
/*
	    if ($doBox) {
		$out .= '</td></tr></table></td></tr></table>';
	    } else {
		$out .= '<br>';
	    }
*/
	}
	return $out;
    }
    /*
      RETURNS: list of subtopics, formatted for store display page
      HISTORY:
	2011-02-23 Split off from DoPage() for SpecialVbzCart
    */
    public function DoPiece_Stat_Kids() {
	$sql = 'ID_Parent='.$this->KeyValue();
	$obj = $this->Table->GetData($sql,NULL,'Sort, NameTree');
	if ($obj->HasRows()) {
	    $out = '<b>Sub-Topics</b>:';
	    while ($obj->NextRow()) {
		$out .= ' '.$obj->ShopLink($obj->Value('NameTree'));
	    }
	    $out .= '<br>';
	} else {
	    $out = NULL;
	}
	return $out;
    }

    // -- SHOPPING WEB UI PAGES -- //
}