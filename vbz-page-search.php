<?php
/* ####
  FILE: page-search.php
  HISTORY:
    2012-07-13 extracting search page class from search/index.php
*/
/*
  CLASS: clsPageSearch
  PURPOSE: search catalog database
*/
class clsPageSearch extends clsVbzPage_Browse {
    private $arReq;
    private $strSearch;

    // ++ ABSTRACT OVERRIDES ++ //

    protected function BaseURL() {
	return KWP_SHOP_SEARCH;
    }
    protected function MenuPainter_new() {
	// this may need reorganization
    }
    protected function HandleInput() {
    	$this->DoContent();
	// this may need reorganization
    }
    protected function PreSkinBuild() {
	// this may need reorganization
    }
    protected function PostSkinBuild() {
	// this may need reorganization
    }

    // -- ABSTRACT OVERRIDES -- //

    protected function ParseInput() {
	//parent::ParseInput();
	$this->arReq = $_GET;
	//$this->strSearch = $this->SafeParam(nz($this->arReq['search']));
	$strSearchRaw = NzArray($this->arReq,'search');
	$this->strSearch = $this->Data()->SafeParam($strSearchRaw);

	// stuff that always gets set
	$this->Skin()->PageTitle('Catalog Search');
	//$this->Skin()->CtxtStr('');	// not sure what replaces this method
    }
/*
    public function TitleStr($iText=NULL) {
	return 'Catalog Search';
    }
    public function TCtxtStr($iText=NULL) {
	return '';
    }
    // 2013-11-15 not sure what this is/was used for
    protected function NameStr($iText=NULL) {
	return 'search the catalog';
    }
*/
/*
    protected function HandleInput() {
	parent::HandleInput();
	$this->strWikiPg	= 'search';
	$this->strTitle		= 'Catalog Search';
	$this->strName		=
	$this->strTitleContext	= '';
	//$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    }
*/
    public function DoContent() {
	$sSearch = $this->strSearch;
	$htSearch = htmlspecialchars($sSearch);

	// search entry form
	$ht = <<<__END__
<table class=catalog-summary>
  <tr><td>
    <form method=get>Search for:
      <input size=50 name=search value="$sSearch">
      <input type=submit value="Go">
    </form>
  </td></tr>
</table>
__END__;
	//$tItems = $this->Data()->Items();

	$sSearch = $this->strSearch;

	if (empty($sSearch)) {
	    // nothing has been entered yet, so don't bother searching
	    // LATER: print instructions or stats or something
	    $ht .= 'Please enter some text to search for.';
	} else {
	    $tTitles = $this->Data()->Titles();
	    $arTi = $tTitles->DoSearch($sSearch);

	    $tTopics = $this->Data()->Topics();
	    $htTo = $tTopics->DoSearch($sSearch,'',', ');

	    if (is_null($arTi)) {
		$ht .= 'No title matches found.<br>';
	    } else {
		$ftActive = $arTi['forsale.text'];
		$ht = '<h3>Found Titles - Available</h3><p class="catalog-summary">'.$ftActive.'</p>';
		if (is_null($ftActive)) {
		    $ht .= 'No matches found.';
		}
		$ftRetired = $arTi['retired.text'];
		if (!is_null($ftRetired)) {
		    $ht .= '<h3>Found Titles - <b>Not</b> Available</h3>'
		      .'<small>These titles are <b>not</b> currently available:<br>'.$ftRetired.'</small>';
		}
	    }

	    if (is_null($htTo)) {
		$ht = 'No topic matches found.<br>';
	    } else {
		$ftText = $htTo;
		$ht = '<h3>Found Topics</h3><p class="catalog-summary">'.$ftText.'</p>';
	    }
/*
	    // create object to handle title list
	    $lstTitles = new clsTitleLister($this->Data()->Titles(),$this->Data()->Images());

	    // Title name search
	    $rsRows = $objTitles->Search_forText($strFind);
	    if ($rsRows->HasRows()) {
		while ($rsRows->NextRow()) {
		    $id = $rsRows->KeyValue();
		    $lstTitles->Add($id,$rsRows->Values());
		}
	    }
	    // Catalog number search
	    $rsRows = $objItems->Search_byCatNum($strFind);
	    if (!is_null($rsRows)) {
		while ($rsRows->NextRow()) {
		    $id = $rsRows->TitleID();
		    $lstTitles->Add($id);
		}
	    }
	    if ($lstTitles->Count()) {
		$ar = $lstTitles->Render();
		$ftTextActive = $ar['txt.act'];
		$ftTextRetired = $ar['txt.ret'];
		$ftImgs = $ar['img'];	// HTML to display thumbnails

		$ftText = '<h3>Titles Available</h3>'.$ftTextActive;
		if (empty($ftTextActive)) {
		    $ftText .= 'No matches found.';
		}
		if (!empty($ftTextRetired)) {
		    $ftText .= '<h3>Titles Not Available</h3>'
		      .'<small>These titles are not currently available:<br>'.$ftTextRetired.'</small>';
		}
		$ftTitleText = $ftText;
		$ftTitleImgs = $ftImgs;
	    } else {
		$ftTitleText = '';
		$ftTitleImgs = '';
		// if search is only one word, then we need a different message from this:
		$ftTitleMsg = 'No matches found; try entering fewer words or a shorter word-fragment.';
		// to be implemented
	    }

	    // Topic search
	    $sqlFilt =
	      '(Name LIKE "%'.$strFind.'%") OR '.
	      '(Variants LIKE "%'.$strFind.'%") OR '.
	      '(Mispeled LIKE "%'.$strFind.'%")';
	    $rsTopics = $this->Data()->Topics()->GetData($sqlFilt);
	    $ftTextActive = '';
	    $ftTextRetired = '';
	    if ($rsTopics->HasRows()) {
		$rsTopics->doBranch(TRUE);
		while ($rsTopics->NextRow()) {
		    // for each topic found, look up all the titles:
		    $id = $rsTopics->KeyValue();
		    $rsTitles = $rsTopics->Titles();	// list of Titles for current Topic
		    $cntTiAll = 0;
		    $cntTiAct = 0;
		    if ($rsTitles->HasRows()) {
			while ($rsTitles->NextRow()) {
			    $lstTitles->Add($rsTitles->TitleID(),$rsTitles->Values());
			}
		    }
		    $ar = $lstTitles->Render();
		    $cntTiAct = $ar['cnt.act'];
		    $cntTiAll = $ar['cnt.all'];
		    $ftText = $rsTopics->LinkOpen().$rsTopics->Name.'</a>: ';
		    if ($cntTiAct > 0) {
			$txtTitles = $cntTiAll.' title'.Pluralize($cntTiAll).', '.$cntTiAct.' active';
			$ftTextActive .= $ftText.$txtTitles.'<br>';
		    } else {
			$ftTextRetired .= $ftText.$cntTiAll.' inactive title'.Pluralize($cntTiAll).'<br>';
		    }
		    $ftText = '<h3>Active Topics</h3>'.$ftTextActive.'<h3>Inactive Topics</h3>'.$ftTextRetired;
		}
		$ftTopicText = $ftText;
	    } else {
		$ftTopicText = 'No matching topics found.';
	    }

	    $out .= <<<__END__
<table>
  <tr bgcolor="#440088"><th colspan=2>Topic Search</th></tr>
  <tr bgcolor="#000000"><td colspan=2>$ftTopicText</td></tr>
  <tr bgcolor="#440088"><th colspan=2>Title Search</th></tr>
  <tr bgcolor="#440066"><th>Names</th><th>Thumbnails</th></tr>
__END__;
	    if (!empty($ftTitleMsg)) {
		$out .= '<tr bgcolor="#440066"><td colspan=2>'.$ftTitleMsg.'</td></tr>';
	    }
	    $out .= '<tr><td bgcolor="#000000" valign=top>'.$ftTitleText.'</td><td valign=top>'.$ftTitleImgs.'</td></tr>';
	    $out .= '</table>';
	*/
	}
	$this->Skin()->Content('main',$ht);
    }
}
