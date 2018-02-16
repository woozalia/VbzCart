<?php
/*
  PURPOSE: currently just a display widget that allows user to un-hide a section that's hidden by default
  HISTORY:
    2018-02-14 created from code in dept.shop.php
*/

/*::::
  NOTE: This could probably be more self-contained if it descended from a Ferreteria page element
*/
class vcHideableSection {
    // API
    public function __construct($sKey,$sHeader,$sContent) {
	$this->SetKeyString($sKey);
	$this->SetHeaderText($sHeader);
	$this->SetContent($sContent);
    }
    // API
    public function Render() {
	$oGlob = vcGlobalsApp::Me();
	$sHdr = $this->GetHeaderText();
	$ssHdr = strip_tags($sHdr);
	if ($this->GetDoShow()) {
	    $sPopup = 'hide '.$ssHdr;
	    $wsArrow = $oGlob->GetWebSpec_DownPointer();
	    $htAlt = '&darr; (down arrow)';
	    $htCont = $this->GetContent();;
	} else {
	    $sPopup = 'show '.$ssHdr;
	    $wsArrow = $oGlob->GetWebSpec_RightPointer();
	    $htAlt = '&rarr; (right arrow)';
	    $htCont = '';
	}
	
	$htArrow = "<img src='$wsArrow' alt='$htAlt' title='$sPopup'>";
	
	$url = $this->GetURL_forLink();
	$oHdr = new fcSectionHeader("<a href='$url'>$htArrow</a> $sHdr");
	return $oHdr->Render().$htCont;
    }
    
    private $sKey;
    protected function SetKeyString($s) {
	$this->sKey = $s;
    }
    protected function GetKeyString() {
	return $this->sKey;
    }
    protected function IsKeyFound() {
	$oFormIn = fcHTTP::Request();
	return $oFormIn->KeyExists($this->GetKeyString());
    }
    protected function GetURLFragment() {
	return '?'.$this->GetKeyString();
    }
    
    private $sHdr;
    protected function SetHeaderText($s) {
	$this->sHdr = $s;
    }
    protected function GetHeaderText() {
	return $this->sHdr;
    }
    private $sCont;
    protected function SetContent($s) {
	$this->sCont = $s;
    }
    protected function GetContent() {
	return $this->sCont;
    }
    
    private $bHideDefault=FALSE;
    public function SetDefaultHide($b) {
	$this->bHideDefault = $b;
    }
    protected function GetDefaultHide() {
	return $this->bHideDefault;
    }
    // RULE: If key is present, do opposite of default; otherwise do default.
    protected function GetDoShow() {
	// key present? yes = show if hide-by-default : no = show if not hide-by-default
	return $this->IsKeyFound()?($this->bHideDefault):(!$this->bHideDefault);
    }
    
    protected function GetURL_forLink() {
	$url = $this->IsKeyFound()
	  ?$_SERVER['SCRIPT_URI']	// flip to default URL
	  :$this->GetURLFragment()	// flip to toggle URL
	  ;
	return $url;
    }
}