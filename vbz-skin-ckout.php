<?php
/*
  PURPOSE: skin for the VbzCart checkout pages
  TODO: clsVbzSkin_ckout shares some functionality with clsVbzSkin_admin
    e.g. RenderForm_Email_RequestReset(), RenderForm_Email_RequestAdd()
    That functionality should probably be consolidated into a base class.
  HISTORY:
    2013-11-15 split off from vbz-skin.php (formerly skins.php)
    2013-11-28 split off from vbz-skin-admin.php
      We will now have 3 sets of skins: browse/cart, checkout, and admin.
*/

class clsVbzSkin_ckout extends clsVbzSkin {

    // ABSTRACT IMPLEMENTATIONS

/*
    public function ContHeader() {
	$out = NULL;
      // begin content table
	$out .= "\n".'<table width="100%" id="'.__METHOD__.'.1" class=border cellpadding=5><tr><td>';
	$out .= "\n".'<table width="100%" id="'.__METHOD__.'.2" class=hdr cellpadding=2 align=center>';
      // === LEFT HEADER: Title ===
	//$out .= '<td>';
	return $out;
    }
    public function ContFooter() {
	$out = '</td></tr></table></td></tr></table>';
	return $out;
    }
*/
    public function SectionHdr($iTitle) {
	$out = "\n<!-- BEGIN ".__METHOD__." - ".__FILE__." line ".__LINE__." -->"
	  ."\n<span class=section>"
	  ."\n<span class=section-header>$iTitle</span>"
	  ;
	return $out;
    }
    public function SectionFtr() {
	$out = "\n</span>\n<!-- END ".__METHOD__." - ".__FILE__." line ".__LINE__." -->";
	return $out;
    }
/* 2013-11-21 This version used a table, but I'm trying to move away from that.
    public function SectionHdr($iTitle) {
	$out = "\n<!-- BEGIN SectionHdr() - ".__METHOD__." -->"
	  ."\n<table width=100%>"
	  ."\n<tr><td colspan=2 class=section-title><b>$iTitle</b></td></tr>";
	return $out;
    }
    public function SectionFtr() {
	$out = "\n</table>\n<!-- END SectionHdr() - ".__METHOD__." -->";
	return $out;
    }
*/
    protected static function SelectIf($iFlag,$iText) {
	if ($iFlag) {
	    return "<b>$iText</b>";
	} else {
	    return $iText;
	}
    }
    /*----
      NOTE: This is in need of refactoring. Why do we call the calling page
	to get the navbar array instead of just passing it in the first place?
    */
/*
    public function RenderNavbar() {
	$pg = $this->Page()->CurPageKey();
	$s = NULL;
	$arLinks = $this->Page()->NavArray();
echo 'PG=['.$pg.']<pre>'.print_r($arLinks,TRUE).'</pre>';
	foreach ($arLinks as $key => $text) {
	    if (!is_null($s)) {
		$s .= ' ... ';
	    }
	    $s .= self::SelectIf($pg == $key,$text);
	}
	$out = "\n<tr><td align=center>$s</td></tr>";
	return $out;
    }
*/
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
	$out =
	  '<img align=absmiddle src="/tools/img/cards/logo_ccVisa.gif" title="Visa">'
	  .'<img align=absmiddle src="/tools/img/cards/logo_ccMC.gif" title="MasterCard">'
	  .'<img align=absmiddle src="/tools/img/cards/logo_ccAmex.gif" title="American Express">'
	  .'<img align=absmiddle src="/tools/img/cards/logo_ccDiscover.gif" title="Discover / Novus">';
	return $out;
    }
/*
    public function RenderLogin($iUName=NULL) {
	$out =
	  ' Username:<input name=uname size=10 value="'.$iUName.'">'
	  .' Password:<input type=password name=upass size=10>'
	  .' <input type=submit value="Log In" name="'.KSF_USER_BTN_LOGIN.'">';
	return $out;
    }
    public function RenderLogout($iText='log out') {
	$out = '<a href="'.KWP_LOGOUT.'">'.$iText.'</a>';
	return $out;
    }
*/
/* 2014-07-14 apparently these are not used
    protected function RenderUserAcctSet($iAuth,$iUser,$sButton) {
	$htUser = htmlspecialchars($iUser);
	$out = <<<__END__
<input type=hidden name=auth value="$iAuth">
 Username:<input name=uname size=10 value="$htUser">
 Password:<input type=password name=upass size=10>
 <input type=submit value="$sButton" name=btnSetLogIn>
__END__;
	return $out;
    }
    public function RenderUserCreate($iAuth,$iUser) {
	return $this->RenderUserAcctSet($iAuth,$iUser,'Modify Your Account');
    }
    /*----
      ACTION: Renders controls for setting username and password
      INPUT:
	iAuth: authorization code emailed to user (URL format)
	iUser: current username (optional)
    */
    /* cont.
    public function RenderUserUpdate($iAuth,$iUser) {
	return $this->RenderUserAcctSet($iAuth,$iUser,'Create New Account');
    }
    /*----
      ACTION: renders control for sending a password reset email
    */
    public function RenderForm_Email_RequestReset($iEmail) {
	$out =
	  ' Email address:<input name=uemail size=40 value="'.htmlspecialchars($iEmail).'">'
	  .' <input type=submit value="Send Email" name="btnSendAuth">';
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