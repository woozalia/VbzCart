<?php
/*
  PURPOSE: page classes for administration stuff
    i.e. pages that should look sensible and safe
    This will probably just be one abstract base class for now.
  HISTORY:
    2013-09-16 created
*/

abstract class clsVbzPage_Admin extends clsVbzPage {
    protected $objSess;
    protected $objCart;

    protected function NewSkin() {
	return new clsVbzSkin_admin($this);
    }
    /*-----
      ACTION: Grab any expected input and interpret it
    */
    protected function ParseInput() {
	// nothing to do; all input is from forms and session
    }
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected function HandleInput() {
	// TODO
    }
    /*-----
      ACTION: Render content header -- anything that displays before main content on all pages of a given class
    */
/*
    protected function DoContHdr() {
	echo '<center><table><tr><td>';
	//parent::DoContHdr();
    }
*/
    /*-----
      ACTION: Render content footer -- anything that displays after main content on all pages of a given class
    */
/*
    protected function DoContFtr() {
	parent::DoContFtr();
	echo '</td></tr></table></center>';
    }
*/
    /*-----
      ACTION: render HTML header (no directly visible content)
    */
    protected function RenderHtmlHdr() {
	$ht = $this->Skin()->RenderHtmlHdr($this->TitleStr(),'ckout');
	return $ht;
    }
    /*-----
      ACTION: Render main content -- stuff that changes
    */
    protected function DoContent() {
	// kluge -- right now, everything seems to get displayed elsewhere
    }
/*
    public function Cart($iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->objCart = $iObj;
	}
	return $this->objCart;
    }
*/
}