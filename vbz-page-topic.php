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
  2013-11-16 Renamed from page-topic.php to vbz-page-topic.php
*/

class clsPageTopic extends clsVbzPage_Browse {
    private $idTopic;
    private $objTopic;

    // ++ ABSTRACT IMPLEMENTATIONS ++ //

    protected function BaseURL() {
	return KWP_SHOP_TOPICS;
    }
    protected function MenuPainter_new() {
	// TODO: figure out if catalog pages actually need a menu painter
    }
    protected function PreSkinBuild() {}	// TODO: may not be needed
    protected function PostSkinBuild() {}	// TODO: may not be needed

    // -- ABSTRACT IMPLEMENTATIONS -- //

    protected function TopicObj() {
	return $this->objTopic;
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
	    $this->Skin()->PageTitle('Topic Index');
	    //$this->NameStr('catalog topic index');
	} else {
	    $oTopic = $this->Topics($idTopic);
	    $this->objTopic = $oTopic;
	    if ($oTopic->IsNew()) {
		$this->Skin()->TitleContext('Tomb of the...');
		$this->Skin()->PageTitle('Unknown Topic');
		//$this->NameStr('topic not found');
		//$this->NameStr('topic does not exist');
	    } else {
		$this->Skin()->PageTitle('Topic: '.$oTopic->NameFull());
		//$this->NameStr($oTopic->NameMeta());
		$this->Skin()->AddNavItem('<b>Topic</b>: ',$oTopic->Value('Name'));
		if ($oTopic->HasParent()) {
		    $sVal = $oTopic->ParentRecord()->Value('Name');
		    $url = $oTopic->ShopURL();
		    $sPop = $oTopic->NameFull();
		} else {
		    $sVal = '<i>(root)</i>';
		    $url = NULL;
		    $sPop = NULL;
		}
		$this->Skin()->AddNavItem('<b>Parent</b>: ',$sVal,$url,$sPop);
	    }
	}

	// MAIN CONTENT

	$oTopic = $this->TopicObj();
	if (is_null($oTopic)) {
	    $ht = $this->Topics()->RenderTree(FALSE);
	} else {
	    // NOTE: most of the page is built here
	    $ht = $oTopic->RenderPage();
	}
	$this->Skin()->Content('main',$ht);
    }
    protected function CreateContent() {
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
	$this->Skin()->CtxtStr('Tomb of the...');
	$this->strSideXtra	= '<dt><b>Topic #</b>: '.$this->strReq;
    }
*/
}

