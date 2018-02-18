<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Topics UI (non-CMS)
  HISTORY:
    2013-11-17 extracted Shop classes from vbz-page-topic.php
*/
class vctShopTopics extends vctTopics {
    
    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcrShopTopic';
    }
    
    // -- SETUP -- //
    // ++ WEB UI ELEMENTS ++ //

    /*----
      ACTION: Searches all topics for a match to the given search text
      RETURNS: HTML rendering of just the thumbnail images for the found topics
      DEPRECATED
	need to return a recordset or array so search results can be combined
	$sPfx, $sSep, and $sSfx aren't even used anymore
	call SearchRecords_forText() instead
    */
    public function DoSearch($sSearch,$sPfx,$sSep,$sSfx=NULL) {
	$rs = $this->Search_forText($sSearch);		// call non-UI f() to get raw data
	$out = $rs->RenderThumbs();
	return $out;
    }
    // PURPOSE: update title stats for each topic and build the dynamic treeview
    protected function BuildTree() {
	// build reference array for tree structure (this part *could* go in the logic class)
	
	$rs = $this->GetRecords_forTree();
	while ($rs->NextRow()) {
	    $id = $rs->GetKeyValue();
	    $idParent = $rs->ParentID();
	    if (empty($idParent)) {
		//$idParent = -1;	// parent is fake root node
		$idParent = 0;	// parent is fake root node
	    }
	    $arLayer[$idParent][$id] = $rs->GetFieldValues();
	}

	// build the treeview
	
	$objTree = $this->TreeCtrl();
	$objRoot = $objTree->RootNode();
	$objFakeRoot = $objRoot->Add(0,'Topics');
	$ar = $this->LoadTitleStats();
	$this->AddLayer($arLayer,$objFakeRoot,0,$ar);	// build the node tree
	$out = $objTree->RenderPageHdr();		// this belongs in a different place eventually
	$out .= $objRoot->RenderTree();

	return $out;
    }
    /*----
      USED BY: both store UI and admin UI
    */
    public function RenderTree() {
	$out = $this->BuildTree();		// build the topic tree display
	return $out;
    }
    public function IndexLink($htShow) {
	$fpTopics = vcGlobals::Me()->GetWebPath_forTopicPages();
	return "<a href='$fpTopics' title='topics: master index' class='dark-bg'>$htShow</a>";
    }
    private $ctrlTree;
    public function TreeCtrl() {
	if (is_null($this->ctrlTree)) {
	    $this->ctrlTree = new fcDTreeAPI(vcGlobals::Me()->GetWebPath_DTree());
	    $this->ctrlTree->FileForCSS('dtree-light.css');	// set according to the background color
	}
	return $this->ctrlTree;
    }
    public function RenderPageHdr() {
	$out = $this->TreeCtrl()->RenderPageHdr();
	return $out;
    }
    
    // -- WEB UI ELEMENTS -- //

}
class vcrShopTopic extends vcrTopic {
    use vtFrameworkAccess;
    use vtTableAccess_ImagesInfo;

    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function ImagesClass() {
	return 'vctImages_StoreUI';
    }
    protected function XTitlesClass() {
	return 'vctTitlesTopics_shop';
    }

    // -- CLASS NAMES -- //
    // ++ QUERIES ++ //

    protected function ItemInfoQuery() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo_forTopic_shop');
    }
    
    // -- QUERIES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function ShopURL() {
	$fpTopics = vcGlobals::Me()->GetWebPath_forTopicPages();
	return $fpTopics.$this->FldrName();
    }
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
    /*----
      PUBLIC because vctShopTopics::DoSearch() calls it
    */
    public function ShopLink_name() {
	return $this->ShopLink($this->NameFull());
    }
    /*----
      RETURNS: Link to this topic, with anchor text being the tree-display version of the name
    */
    protected function ShopLink_tree() {
	return $this->ShopLink($this->NameTree());
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

    public function Tree_FormatTwigStats($qTitles) {
	$txtNoun = ' title'.fcString::Pluralize($qTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$qTitles.$txtNoun.'">'.$qTitles.'</span></b>]';
	return $out;
    }
    public function RenderBranch($iSep=" &larr; ") {
	$out = $this->ShopLink_tree();
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentRecord()->RenderBranch($iSep);
	}
	return $out;
    }
    /*----
      RENDERS: the topic's name and ancestry
      USED BY: Title object's admin interface
    */
    public function RenderBranch_text($sSep=" &larr; ") {
	$out = $this->GetFieldValue('Name');
	if ($this->HasParent()) {
	    $out .= $sSep.$this->ParentRecord()->RenderBranch_text($sSep);
	}
	return $out;
    }
    public function LinkOpen() {
	if ($this->doBranch()) {
	    $txtNameFull = $this->RenderBranch_text();
	} else {
	    $txtNameFull = fcString::EncodeForHTML($this->NameFull());
	}
	return '<a class="dark-bg" href="'.$this->ShopURL().'" title="'.$txtNameFull.'">';
    }
    protected function RenderImages($sTitle,$sSize=vctImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords($sSize);
	return $rsIm->RenderInline_rows($sTitle,$sSize);
    }
    public function RenderThumbs($sTitle) {
	return $this->RenderImages($sTitle,vctImages::SIZE_THUMB);	// forces thumbnail size
    }

    // -- WEB UI COMPONENTS -- //
    // ++ SHOPPING WEB UI PAGES ++ //

    // PURPOSE: Renders a single-Topic exhibit page
    public function RenderPage() {
	$arPth = $this->BranchArray();		// path from this to root
	$rsSer = $this->SeriesRecords();	// topics at same level as this one
	$rsSub = $this->KidsRecords();		// topics under this one
	
	$idTopic = $this->GetKeyValue();
	$tq = $this->TitleInfoQuery();
	
	// VERSION 2

	$htImgs = $tq->RenderImages_forTopic($idTopic);
	
	// VERSION 1

	/*
	$arTitles = $tq->StatsArray_forTopic($idTopic);
	
	$rsImg = $this->ImageInfoQuery()->GetRecords_forThumbs_forTopic($idTopic);
	$arTitles = $rsImg->Collate_byTitle($arTitles);
	
	$rc = $this->GetTableWrapper()->SpawnRecordset();	// we need to show info about other topics
	*/

	// TOPIC SERIES

	$ht = "\n".'<table class="catalog-summary" style="float: right;"><tr><td>';
	$ht .= "\n<b>Series</b>:";

	while ($rsSer->NextRow()) {
	    $idRow = $rsSer->GetKeyValue();
	    //$arRow = $arBits['row'];	// do we even need the other bits?
	    $sName = "\n".$rsSer->NameTree();

	    if ($idRow == $idTopic) {
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

	/* VERSION 1 part 2
	// TITLES
	//$arTitlesData = $arTitles['data'];
	$rcImg = $this->ImageInfoQuery()->SpawnRecordset();	// blank for receiving array data

	if (count($arTitles) > 0) {
	    $rcTitle = $tq->SpawnRecordset();
	    $arRes = $rcTitle->RenderTitleResults($arTitles);
	    $htForSaleTxt = $arRes['act']['text'];
	    $htForSaleImg = $arRes['act']['imgs'];
	    $htRetiredTxt = $arRes['ret'];

	    if (is_null($htForSaleTxt)) {
		$htContent = '<span class=catalog-summary>No available items for this topic.</span>';
	    } else {
		$htContent =
		  '<span class=catalog-summary>'.$htForSaleTxt.'</span>'
		  .$htForSaleImg;
	    }
	    $oSection = new vcHideableSection('hide-available','Titles Available',$htContent);
	    $ht .= $oSection->Render();

	    if (!is_null($htRetiredTxt)) {
		$htContent = "<span class=catalog-summary>$htRetiredTxt</span>";
		$oSection = new vcHideableSection('show-retired','Titles NOT available',$htContent);
		$oSection->SetDefaultHide(TRUE);
		$ht .= $oSection->Render();
	    }
	    $ht .= "<h2>TAKE 2</h2>".$out;
	} else {
	    $ht .= "\nThis topic currently has no titles.";
	}
	*/
	$ht .= $htImgs;
	return $ht;
    }
    protected function RenderImages_v1() {
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
	$obj = $this->Table()->GetData($this->SQL_Filt_Series(),NULL,'Sort, NameTree, Name, NameFull');
	if ($obj->HasRows()) {

	    $cntRows = $obj->RowCount();
	    //$doBox = ($cntRows > 5);	// this number is somewhat arbitrary
	    $doBox = TRUE;
	    $out .= '<b>Series</b>:';
	    while ($obj->NextRow()) {
		$id = $this->GetKeyValue();
		if ($doBox) {
		    $txt = $obj->NameTree();
		} else {
		    $txt = $obj->Value('Name');
		}

		if ($obj->GetKeyValue() == $id) {
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
	$sql = 'ID_Parent='.$this->GetKeyValue();
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