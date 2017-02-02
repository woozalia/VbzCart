<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Topics UI (non-CMS)
  HISTORY:
    2013-11-17 extracted clsTopic[s]_StoreUI from vbz-page-topic.php
*/
class clsTopics_StoreUI extends clsTopics {
    //private $objPage;
//    use ftFrameworkAccess;	// uncomment only if needed

    /* 2016-10-25 This stuff is done elsewhere now.
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsTopic_StoreUI');
    } */
    
    // ++ OVERRIDES ++ //

    protected function SingularName() {
	return 'clsTopic_StoreUI';
    }
    
    // -- OVERRIDES -- //

    /* 2016-10-25 replaced by ftFrameworkAccess
    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }*/
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
    
    // ++ WEB UI ELEMENTS ++ //

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
	    $arLayer[$idParent][$id] = $rs->Values();
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
    public function RenderTree($iRebuild) {
	$objCache = new vcCacheFile();
	$fnBase = $this->ClassSng().'.tree';
	if ($iRebuild || !$objCache->Exists($fnBase)) {
	    $out = $this->BuildTree();		// build the topic tree display
	    $objCache->Write($fnBase,$out);	// save it to the cache
	} else {
	    $out = $objCache->Read($fnBase);	// rad the topic tree display from the cache
	}
	return $out;
    }
    public function IndexLink($iShow) {
	return '<a href="'.KWP_TOPICS_REL.'" title="topics: master index" class="dark-bg">'.$iShow.'</a>';
    }
    public function TreeCtrl() {
	if (is_null($this->ctrlTree)) {
	    $this->ctrlTree = new clsDTreeAPI(KWP_TOOLS_DTREE);
	    $this->ctrlTree->FileForCSS('dtree-light.css');	// set according to the background color
	}
	return $this->ctrlTree;
    }
    public function RenderPageHdr() {
	$out = $this->TreeCtrl()->RenderPageHdr();
	return $out;
    }
    // 2016-02-05 This appears to be unused.
    public function DoIndex() {
	throw new exception('Does anything actually call this?');
	$objSection = new clsPageOutput();

	$objTopics = $this->GetData('ID_Parent IS NULL',NULL,'Sort,Name,NameTree');
	$isFirst = TRUE;
	while ($objTopics->NextRow()) {
	    if ($isFirst) {
		$isFirst = FALSE;
		$objSection->SectionHdr('Root Topics');
	    } else {
		$objSection->AddText($objTopics->Name.'<br>');
	    }
	}

	return $objSection->out;
    }
    
    // -- WEB UI ELEMENTS -- //

}
class clsTopic_StoreUI extends clsTopic {
    use vtFrameworkAccess;

    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function ImagesClass() {
	return 'clsImages_StoreUI';
    }
    protected function XTitlesClass() {
	return 'vctTitlesTopics_shop';
    }

    // -- CLASS NAMES -- //
    // ++ QUERIES ++ //
    
    protected function ImageInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtImagesInfo');
    }
    protected function ItemInfoQuery() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo');
    }
    
    // -- QUERIES -- //
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
    /*----
      PUBLIC because clsTopics_StoreUI::DoSearch() calls it
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

    public function Tree_RenderTwig($iCntTitles) {
	$cntTitles = $iCntTitles;
	$txtNoun = ' title'.Pluralize($cntTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
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
	$out = $this->Value('Name');
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
    protected function RenderImages($sTitle,$sSize=clsImages::SIZE_THUMB) {
	$rsIm = $this->ImageRecords($sSize);
	return $rsIm->RenderInline_rows($sTitle,$sSize);
    }
    public function RenderThumbs($sTitle) {
	return $this->RenderImages($sTitle,clsImages::SIZE_THUMB);	// forces thumbnail size
    }

    // -- WEB UI COMPONENTS -- //
    // ++ SHOPPING WEB UI PAGES ++ //

    public function RenderPage() {
	$arPth = $this->BranchArray();		// path from this to root
	$rsSer = $this->SeriesRecords();	// topics at same level as this one
	$rsSub = $this->KidsRecords();		// topics under this one
	
	$idTopic = $this->GetKeyValue();
	$arTitles = $this->TitleInfoQuery()->StatsArray_forTopic($idTopic);
	
	$rsImg = $this->ImageInfoQuery()->GetRecords_forThumbs_forTopic($idTopic);
	$arTitles = $rsImg->Collate_byTitle($arTitles);
	
	
	$rc = $this->GetTableWrapper()->SpawnRecordset();	// we need to show info about other topics

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

	// TITLES
	//$arTitlesData = $arTitles['data'];
	$rcImg = $this->ImageInfoQuery()->SpawnRecordset();	// blank for receiving array data

	if (count($arTitles) > 0) {
	    $rcTitle = $this->TitleInfoQuery()->SpawnRecordset();
	    $arRes = $rcTitle->RenderTitleResults($arTitles);
	    $htForSaleTxt = $arRes['act']['text'];
	    $htForSaleImg = $arRes['act']['imgs'];
	    $htRetiredTxt = $arRes['ret'];
	

	    $oSkin = $this->SkinObject();

	    $ht .= $oSkin->SectionHdr('&darr; Titles available');
	    if (is_null($htForSaleTxt)) {
		$ht .= '<span class=catalog-summary>No available items in this topic.</span>';
	    } else {
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
	return $ht;
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