<?php
/*
  PURPOSE: base classes for skins
    Eventually, maybe login controls should be in a helper class, so that they don't have
      to be implemented on pages that will link elsewhere for login functionality.
  RULES:
    * A Page determines what needs to be displayed.
    * A Skin determines how it is displayed.
    * The current page must determine which skin is to be used, and create it.
  HISTORY:
    2013-09-16 created -- trying to finally make the skinning system workable
    2013-12-26 making this a subclass of clsSkin_login
*/
// this will eventually be moved to a non-VBZ library
abstract class clsVbzSkin extends clsSkin_login {
    private $fltStart;
    private $oNavBar;	// navigation bar object

    // ++ ACTION ++ //

    /*----
      ACTION: Fill in the pieces.
      NOTES:
	2013-12-01 Apparently cont.hdr can easily get set before we get here,
	  so we shouldn't overwrite it.
      HISTORY:
	2013-12-01 moved setting of cont.hdr above parent::Build() *and*
	  made it append instead of setting. Maybe it should still be after?
    */
    public function Build() {
	parent::Build();
	$this->arPieces['cont.hdr'] .= $this->ContentHeader();
    }

    // -- ACTION -- //
    // ++ CALCULATIONS ++ //

    protected function ImageSpec($iFileName) {
	return KWP_TOOLS.'/img'.$iFileName;
    }
    public function SetStartTime() {
	$this->fltStart = microtime(true);
    }
    /*----
      RETURNS: how long since StartTime() was called, in microseconds
    */
    protected function ExecTime() {
	return microtime(true) - $this->fltStart;
    }

    // -- CALCULATIONS -- //
    // ++ PIECES ++ //

    protected function ContentHeader() {
	return KS_MSG_SITEWIDE_TOP;
    }
    /*----
      RETURNS: title for browser to display, based on page title
    */
    public function BrowserTitle() {
	return $this->PageTitle().' @ '.KS_SITE_NAME;
    }
    /* 2014-07-26 who calls this? Use Page->SectionHeader()
    public function TitleHeader($sTitle) {
	return $this->SectionHeader('title-header');
    }*/
    /* 2014-07-26 old version
    public function SectionHeader($sTitle,array $arWidgets=NULL,$sCSSClass='section-header') {
	$sPHPClass = __CLASS__;
	$htWidgets = $this->RenderArray($arWidgets);
	$out = "\n<!-- SectionHdr() CLASS $sPHPClass -->"
	  //."\n<span class=section>"
	  ."\n".'<span class="'.$sCSSClass.'">'.$sTitle.$htWidgets.'</span>'
	  ;
	return $out;
    }*/
    public function SectionHeader($sTitle,$htMenu=NULL,$sCSSClass='section-header') {
	$sPHPClass = __CLASS__;
	$out = <<<__END__
<!-- SectionHdr() CLASS $sPHPClass -->
<span class="$sCSSClass">$sTitle$htMenu</span>
<!-- /SectionHdr() -->
__END__;
	return $out;
    }

/*
    public function HLine($iHeight=NULL) {
	$htHt = is_null($iHeight)?'':('height='.$iHeight);
	return "\n".'<img src="'.$this->ImageSpec('/bg/hlines/').'"'.$htHt.' alt="-----" class="hline-section" />';
    }
*/
    public function HLine($cssClass='hline-section') {
//	return "\n".'<div class="'.$cssClass.'"></div>';	// 2013-12-25 not currently working
	return '<hr>';
    }
    public function ErrorMessage($iText) {
	return '<center>'
	  .'<table style="border: 1px solid red; background: yellow;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_ALERT.'" alt=alert title="error message indicator" /></td>'
	  .'<td>'.$iText.'</td>'
	  .'</tr></table>'
	  .'</center>';
    }
    public function WarningMessage($iText) {
	return '<center>'
	  .'<table style="border: 1px solid red; background: yellow;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_WARN.'" alt=alert title="error message indicator" /></td>'
	  .'<td>'.$iText.'</td>'
	  .'</tr></table>'
	  .'</center>';
    }
    public function SuccessMessage($iText) {
	return '<center>'
	  .'<table style="border: 1px solid green; background: #eeffee;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_OKAY.'" alt=alert title="success message indicator" /></td>'
	  .'<td>'.$iText.'</td>'
	  .'</tr></table>'
	  .'</center>';
    }

    // -- PIECES -- //
}
