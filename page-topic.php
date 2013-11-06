<?php
/*
FILE: page-topic.php
HISTORY:
  2010-10-13 created for clsPageTopic
  2011-01-18 moved clsTopic(s) here from store.php
  2011-01-25 split off page-topic.php (clsPageTopic only) from topic.php (clsTopic(s))
    to resolve dependency-order conflicts
  2012-05-13 page class now descends from clsVbzSkin_Standard
  2013-09-16 clsVbzSkin_Standard renamed clsVbzPage_Standard
  2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
*/

class clsPageTopic extends clsVbzPage_Browse {
    protected function DoContent() {
	$this->CreateContent();
	echo $this->Doc()->Render();
    }

    private function Topics($id=NULL) {
	$tbl = $this->Data()->Topics();
	$tbl->Page($this);
	if (is_null($id)) {
	    return $tbl;
	} else {
	    $rc = $tbl->GetItem($id);
	    return $rc;
	}
    }
    /*-----
      IMPLEMENTATION: URL data is topic ID
    */
    protected function ParseInput() {
	if (isset($_SERVER['PATH_INFO'])) {
	    $strReq = trim($_SERVER['PATH_INFO'],'/');
	} else {
	    $strReq = '';
	}
	$idTopic = (int)$strReq;
	$this->strReq = sprintf('%4i',$idTopic);
	$this->idTopic = $idTopic;
    }
    public function HandleInput() {
	$idTopic = $this->idTopic;
	if (empty($idTopic)) {
	    $this->objTopic	= NULL;
	    $this->strWikiPg	= 'topics';
	    $this->TitleStr('Topic Index');
	    $this->NameStr('catalog topic index');
	} else {
	    $objTopic = $this->Topics($idTopic);
	    $this->objTopic = $objTopic;
	    assert('is_object($objTopic); /* ID='.$idTopic.' */');
	    if ($objTopic->IsNew()) {
		$this->strWikiPg	= NULL;
	      $this->TCtxtStr('Tomb of the...');
		$this->TitleStr('Unknown Topic');
		//$this->NameStr('topic not found');
		$this->NameStr('topic does not exist');
	    } else {
		$this->strWikiPg	= 'topic/'.$objTopic->FldrName();
		$this->TitleStr('Topic: '.$objTopic->NameFull());
		$this->NameStr($objTopic->NameMeta());
	    }
	}
    }
    protected function CreateContent() {
	$objTbl = $this->NewTable();
	  $objTbl->ClassName('catalog-summary');
	$objRow = $objTbl->NewRow();

	if (is_null($this->objTopic)) {
	    $txt = $this->Topics()->RenderTree(FALSE);
	} else {
	    // NOTE: most of the page is built here
	    $txt = $this->objTopic->DoPage();
	}
	$objCell = $objRow->NewCell($txt);
    }

    protected function RenderHtmlHeaderSection() {
	$out = parent::RenderHtmlHeaderSection();
	$out .= $this->Data()->Topics()->RenderPageHdr();
	return $out;
    }
// DIFFERENT TYPES OF PAGES
/*
    protected function DoNotFound() {
	$this->strWikiPg	= '';
	$this->TitleStr('Unknown Topic');
	$this->NameStr('topic does not exist');
	$this->TCtxtStr('Tomb of the...');
	$this->strHdrXtra	= '';
	$this->strSideXtra	= '<dt><b>Topic #</b>: '.$this->strReq;
    }
*/
}

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
}
class clsTopic_StoreUI extends clsTopic {
    public function DoPage() {
	$objPage = $this->Table()->Page();
	$objDoc = $objPage->Doc();

	if ($this->HasValue('ID')) {

	    $ht = $this->DoPiece_Stat_Series();
	    if (!is_null($ht)) {
		$objCell = $objDoc->NewBox($ht);
		$objCell->Table()->ClassName('catalog-summary');
		$objCell->Table()->SetAttrs(array('align'=>'right'));
	    }

	    $objText = $objDoc->NewText('<p class="catalog-summary">'.$this->DoPiece_Stat_Parent().'</p>');

	    $ht = $this->DoPiece_Stat_Kids();
	    if (!is_null($ht)) {
		$objText = $objDoc->NewText('<p class="catalog-summary">'.$ht.'</p>');
	    }

	    //$objDoc->NewText($this->DoPiece_Stats());

// list titles for this topic
	    $this->DoFigure_Titles();
	    if ($this->hasTitles) {
		$arInfo = $this->arTitleInfo;
		$ftImgsAct = $arInfo['img.act'];
		$ftImgsRet = $arInfo['img.ret'];
		$objActive = $arInfo['obj.act'];
		$ftTextRetired = $arInfo['txt.ret'];

		if (is_object($objActive)) {
		    $objPage->NewSection('Titles Available',3);	// level 3 header

		    $objDoc->NewText($ftImgsAct);
		    $objActive->ClassName('catalog-summary');
		    $objDoc->NodeAdd($objActive);
		} else {
		    $objDoc->NewText('<p><i>No active titles for this topic.</i></p>');
		}
		if (!empty($ftTextRetired)) {
		    $objDoc->NewText($objPage->Skin()->Render_HLine(3));
		    $objPage->NewSection('Titles Not Available',3);
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
	    $obj = $this->ParentObj();
	    $out = '<b>Found in</b>: '.$obj->RenderBranch();
	} else {
	    $out = 'This is a top-level topic.';
	}
	$out .= '&larr;['.$this->Table->IndexLink('Master Index').']<br>';
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
/*
    public function DoPiece_Stats() {
	$out = $this->DoPiece_Stat_Parent();
	$out .= $this->DoPiece_Stat_Series();
	$out .= $this->DoPiece_Stat_Kids();
	return $out;
    }
*/
}