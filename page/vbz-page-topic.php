<?php
/*
FILE: page-topic.php
HISTORY:
  2010-10-13 created for vcPageTopic (was: clsPageTopic)
  2011-01-18 moved clsTopic(s) here from store.php
  2011-01-25 split off page-topic.php (vcPageTopic only) from topic.php (clsTopic(s))
    to resolve dependency-order conflicts
  2012-05-13 page class now descends from clsVbzSkin_Standard
  2013-09-16 clsVbzSkin_Standard renamed clsVbzPage_Standard
  2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
  2013-11-16 Renamed from page-topic.php to vbz-page-topic.php
*/

class vcPageTopic extends vcPage_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_topic';
    }

    // -- SETUP -- //

/* 2017-05-27 old stuff before page restructuring
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
	return $this->GetConnection()->MakeTableWrapper('vctShopTopics',$id);
    }

    private $rcTopic;
    protected function SetTopicRecord(vcrTopic $rc) {
	$this->rcTopic = $rc;
    }
    protected function GetTopicRecord() {
	return $this->rcTopic;
    }

    protected function Topics($id=NULL) {
	return $this->TopicTable($id);
    }
    /*-----
      IMPLEMENTATION: URL data is topic ID
    */ /* old code cont.
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
    } */
}

class vcTag_html_topic extends vcTag_html_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTag_body() {
	return 'vcTag_body_topic';
    }

    // -- SETUP -- //

}
class vcTag_body_topic extends vcTag_body_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_topic';
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
    
    // CEMENT
    protected function OnRunCalculations() {}
    
    // -- EVENTS -- //
    
}
class vcPageContent_topic extends vcPageContent_shop {

    // ++ EVENTS ++ //
  
//    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTopic = fcApp::Me()->GetKioskObject()->GetInputString();
	
	$this->SetTopicRecord(NULL);	// default if valid topic not found
	if (fcString::IsBlank($sTopic)) {
	    // not sure if this ever happens
	    $sTitle = 'topics';
	    $htTitle = 'Topic Index';
	} else {
	    if (is_numeric($sTopic)) {
		$id = (int)$sTopic;
		$rcTopic = $this->TopicTable($id);
		if (is_null($rcTopic)) {
		    $sTitle = "?topic $id";
		    $htTitle = "Unknown Topic #$id";
		    $this->SetError("There is no topic #$id in the database.");
		} else {
		    $this->SetTopicRecord($rcTopic);
		    $sTitle = 'topic '.$id;
		    $htTitle = "Topic #$id";
		}
	    } else {
		$htTopic = fcHTML::FormatString_SafeToOutput($sTopic);
		$sTitle = '?topic';
		$htTitle = "Malformatted topic ID &ldquo;$htTopic&rdquo;";
		$this->SetError("Requested topic ID &ldquo;$htTopic&rdquo; isn't usably formatted.");
		// TODO: maybe invoke a topics-only search when this happens?
	    }
	}
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->RenderExhibit();
    }

    // -- EVENTS -- //
    // ++ INTERNAL STATES ++ //

    private $sError=NULL;
    protected function SetError($sError) {
	$this->sError = $sError;
    }
    protected function GetError() {
	return $this->sError;
    }
    protected function HasError() {
	return !is_null($this->sError);
    }
    
    // -- INTERNAL STATES -- //
    // ++ TABLES ++ //
    
    protected function TopicTable($id=NULL) {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper('vctShopTopics',$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    private $rcTopic;
    protected function SetTopicRecord($rc) {
	$this->rcTopic = $rc;
    }
    protected function GetTopicRecord() {
	return $this->rcTopic;
    }
    
    // -- RECORDS -- //
    // ++ OUTPUT ++ //
    
    protected function RenderExhibit() {
	$rc = $this->GetTopicRecord();
	if (is_null($rc)) {
	    if ($this->HasError()) {
		$sErr = $this->GetError();
		$out = "<div class=content>$sErr</div>";
	    } else {
		$out = $this->TopicTable()->RenderTree(FALSE);
	    }
	} else {
	    $out = $rc->RenderPage();
	}
	return $out;
    }
    
    // -- OUTPUT -- //
}