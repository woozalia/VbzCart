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

class clsPageTopic extends vcBrowsePage {
    private $idTopic;

    // ++ CEMENTING ++ //

    protected function BaseURL() {
	return KWP_SHOP_TOPICS;
    }
    protected function MenuPainter_new() {
	// TODO: figure out if catalog pages actually need a menu painter
    }
    protected function PreSkinBuild() {}	// TODO: may not be needed
    protected function PostSkinBuild() {}	// TODO: may not be needed
    protected function MenuHome_new() {}	// ditto

    // -- CEMENTING -- //
    // ++ TABLES ++ //
    
    protected function TopicTable($id=NULL) {
	return $this->GetDatabase()->MakeTableWrapper('clsTopics_StoreUI',$id);
    }

    private $rcTopic;
    protected function SetTopicRecord(clsTopic $rc) {
	$this->rcTopic = $rc;
    }
    protected function GetTopicRecord() {
	return $this->rcTopic;
    }

    protected function Topics($id=NULL) {
	return $this->TopicTable($id);
	/* 2016-11-06 old code
	$tbl = $this->TopicRecords();
	$tbl->Page($this);
	if (is_null($id)) {
	    return $tbl;
	} else {
	    $rc = $tbl->GetItem($id);
	    return $rc;
	}
	*/
    }
    /*-----
      IMPLEMENTATION: URL data is topic ID
    */
    protected function ParseInput() {
	$sReq = self::GetPathInfo();
	$sTopic = trim($sReq,'/');
	$idTopic = (int)$sTopic;
//	echo "REQ=[$strReq] TOPIC=[$idTopic]";
	$this->idTopic = $idTopic;
    }
    public function HandleInput() {
	$idTopic = $this->idTopic;
	$oSkin = $this->GetSkinObject();
	if (empty($idTopic)) {
	    $this->SetTopicRecord(NULL);
	    $oSkin->PageTitle('Topic Index');
	    //$this->NameStr('catalog topic index');
	} else {
	    $rcTopic = $this->Topics($idTopic);
	    $this->SetTopicRecord($rcTopic);
	    if ($rcTopic->IsNew()) {
		$oSkin->SetTitleContextString('Tomb of the...');
		$oSkin->SetPageTitle('Unknown Topic');
		//$this->NameStr('topic not found');
		//$this->NameStr('topic does not exist');
	    } else {
		$sFull = $rcTopic->NameFull();
		$oSkin->SetPageTitle('Topic: '.$sFull);
		//$this->NameStr($oTopic->NameMeta());
		$oSkin->AddNavItem('<b>Topic</b>: ',$rcTopic->NameString());
		if ($rcTopic->HasParent()) {
		    $sVal = $rcTopic->ParentRecord()->NameString();
		    $url = $rcTopic->ShopURL();
		    $sPop = $sFull;
		} else {
		    $sVal = '<i>(root)</i>';
		    $url = NULL;
		    $sPop = NULL;
		}
		$oSkin->AddNavItem('<b>Parent</b>: ',$sVal,$url,$sPop);
	    }
	}

	// MAIN CONTENT
	
	$oTopic = $this->GetTopicRecord();
	if (is_null($oTopic)) {
	    $ht = $this->Topics()->RenderTree(FALSE);
	} else {
	    // NOTE: most of the page is built here
	    $ht = $oTopic->RenderPage();
	}
	//$out = "\n<table class=catalog-content><tr><td>$ht</td></tr></table>";
	$out = $ht;
	$oSkin->Content('main',$out);
    }
    protected function CreateContent() {
    }

    protected function RenderHtmlHeaderSection() {
	$out = parent::RenderHtmlHeaderSection();
	$out .= $this->Data()->Topics()->RenderPageHdr();
	return $out;
    }
}

