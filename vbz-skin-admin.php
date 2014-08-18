<?php
/*
  PURPOSE: skins for VbzCart admin pages
  HISTORY:
    2013-11-15 split off from vbz-skin.php (formerly skins.php)
*/

/*%%%%
  NOTE: This still isn't *quite* a skin. Too many things are hard-coded, like button-names and such.
*/
class clsVbzSkin_admin extends clsVbzSkin {

    // ABSTRACT IMPLEMENTATIONS

    public function ContHeader() {
	$out = NULL;
      // begin content table
	$out .=
	   "\n".'<table width="100%" id="'.__METHOD__.'.outer" class=border cellpadding=5><tr><td>'
	  ."\n".'<table width="100%" id="'.__METHOD__.'.inner" class=hdr cellpadding=2 align=center>';
      // === LEFT HEADER: Title ===
	//$out .= '<td>';
	return $out;
    }
    public function ContFooter() {
	$out = "\n</td></tr></table>\n</td></tr></table>";
	return $out;
    }
    /*----
      USED BY: checkout page
    */
    public function SectionFooter() {
	$out = "\n</span>";
	return $out;
    }
    /*----
      ACTION: Render a horizontal-style navigation bar for the given navbar object
    */
    public function RenderNavbar_H(clsNavbar $iNav) {
	$ht = $iNav->Render();
	return "\n<span class=status-bar>$ht</span>";
    }
    /*----
      TODO: modify this to accept a list of images instead of defining the list
    */
    public function RenderPaymentIcons() {
	$fpCards = KWP_TOOLS.'/img/cards';
	$out = <<<__END__
<img align=absmiddle src="$fpCards/logo_ccVisa.gif" title="Visa">
<img align=absmiddle src="$fpCards/logo_ccMC.gif" title="MasterCard">
<img align=absmiddle src="$fpCards/logo_ccAmex.gif" title="American Express">
<img align=absmiddle src="$fpCards/logo_ccDiscover.gif" title="Discover / Novus">
__END__;
	return $out;
    }
    /*----
      RENDERS: form for requesting that a given email address be attached to the current user account.
	This is basically the same process as requesting a username/password reset, but the step where
	the user is allowed to modify their username and password is omitted; instead, the authorization
	link simply verifies that the user has access to the given email account.
    */
    public function RenderForm_Email_RequestAdd($iEmail=NULL) {
	$out =
	  ' Email address:<input name=uemail size=40 value="'.htmlspecialchars($iEmail).'">'
	  .' <input type=submit value="Send Email" name=btnAddEmail>';
	return $out;
    }
    public function RenderError($iText) {
	return '<center>'
	  .'<table style="border: 1px solid red; background: yellow;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_ALERT.'" alt=alert title="error message indicator" /></td>'
	  .'<td>'.$iText.'</td>'
	  .'</tr></table>'
	  .'</center>';
    }
    public function RenderSuccess($iText) {
	return '<center>'
	  .'<table style="border: 1px solid green; background: #eeffee;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_OKAY.'" alt=alert title="success message indicator" /></td>'
	  .'<td>'.$iText.'</td>'
	  .'</tr></table>'
	  .'</center>';
    }
    /*----
      ACTION: Render a horizontal divider when a table is open
    */
    public function RenderTableHLine() {
	return '</td></tr><tr><td style="background: #aaaaaa;"></td></tr><tr><td>';
    }
}