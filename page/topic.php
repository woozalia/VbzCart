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
  2018-02-25 moved vcAppShop_topic and vcMenuKiosk_topic here
*/

class vcAppShop_topic extends vcAppShop {
    protected function GetPageClass() {
	return 'vcPageTopic';
    }
    protected function GetKioskClass() {
	return 'vcMenuKiosk_topic';
    }
}
class vcMenuKiosk_topic extends fcMenuKiosk_autonomous {
    public function GetBasePath() {
	return vcGlobals::Me()->GetWebPath_forTopicPages();
    }
}

class vcPageTopic extends vcPage_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_topic';
    }

    // -- SETUP -- //

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
	$htAboveTitle = NULL;
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
		    $sName = $rcTopic->NameString();
		    $sTitle = "(tp$id) $sName";
		    $htTitle = $sName;
		    $htAboveTitle = "Topic #$id:";
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
	if (!is_null($htAboveTitle)) {
	    $oPage->SetContentTitleContext($htAboveTitle);
	}
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
		$out = '<table style="background: white;"><tr><td>'
		  .$this->TopicTable()->RenderTree(FALSE)
		  .'</td></tr></table>'
		  ;
	    }
	} else {
	    $out = $rc->RenderPage();
	}
	return $out;
    }
    
    // -- OUTPUT -- //
}