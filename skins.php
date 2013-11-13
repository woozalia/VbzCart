<?php
/*
  PURPOSE: base classes for skins
    Eventually, there should be non-VBZ base classes for clsVbzSkin and clsVbzPage
      (I seem to be slowly creating an application framework.)
  RULES:
    * A Page determines what needs to be displayed.
    * A Skin determines how it is displayed.
    * The current page must determine which skin is to be used, and create it.
  HISTORY:
    2013-09-16 created -- trying to finally make the skinning system workable
*/
// this will eventually be moved to a non-VBZ library
abstract class clsSkin {
    private $oPage;
    private $oText;

    public function __construct(clsPage $iPage) {
	$this->oPage = $iPage;
	$iPage->Skin($this);
	$this->oText = NULL;
    }
    protected function Page() {
	return $this->oPage;
    }
    abstract protected function Add($iText);
}

abstract class clsVbzSkin extends clsSkin {
    // IMPLEMENTATION: emitted after it has been built
    public abstract function RenderContHdr();

    protected function Add($iText) {
	$this->oText .= $iText;
    }

    public function RenderHtmlHdr($iTitle,$iSheet) {
	$sTitle = $iTitle.' @ '.KS_STORE_NAME;
	$out = "\n<title>$sTitle</title>";

	$arVars = array('sheet' => $iSheet);
	$objStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$objStrTplt->MarkedValue(KHT_PAGE_STYLE);
	$out .= $objStrTplt->Replace();
	if (!empty($iTitle)) {
	    $ftName = ': '.htmlspecialchars($iTitle);
	} else {
	    $ftName = '';
	}
	$strContent = KS_STORE_NAME_META.$ftName;
	$out .= "\n<meta name=description content=\"$strContent\">";
	return $out;
    }
    public function Render_HLine($iHeight=NULL) {
      $htHt = is_null($iHeight)?'':('height='.$iHeight);
      return '<img src="'.$this->ImageSpec('/bg/hlines/').'"'.$htHt.' alt="-----" style="width: 100%;" />';
    }
    protected function ToolPath() {
	return KWP_TOOLS;
    }
    protected function ImageSpec($iFileName) {
	return $this->ToolPath().'/img'.$iFileName;
    }
}
class clsVbzSkin_browse extends clsVbzSkin {
    public function RenderContHdr() {
	$out = NULL;
      // begin content table
	$out .= '<table width="100%" id="'.__METHOD__.'.1" class=border cellpadding=5><tr><td>';
	$out .= '<table width="100%" id="'.__METHOD__.'.2" class=hdr cellpadding=2><tr>';
      // === LEFT HEADER: Title ===
	$out .= '<td>';
	$out .= '<a href="'.KWP_HOME_ABS.'"><img align=left border=0 src="'.KWP_LOGO_HEADER.'" title="'.KS_STORE_NAME.' home" alt="'.KS_SMALL_LOGO_ALT.'"></a>';
	$sTCtxt = $this->Page()->TCtxtStr();
	if (!is_null($sTCtxt)) {
	    $out .= '<span class=pretitle><b><a href="/">'.KS_STORE_NAME.'</a></b>: '.$sTCtxt.'</span><br>';
	}
	$out .= '<span class=page-title>'.$this->Page()->TitleStr().'</span></td>';
      // === END LEFT HEADER ===

      // === RIGHT HEADER: nav icons ===
	$out .= '<td align=right>';
	$out .= $this->RenderToolbar();
	$out .= '</td>';
      // === END RIGHT HEADER ===
	$out .= "\n</tr></table>\n</td></tr></table>\n<!-- end html header -->\n";
	return $out;
    }

/*
    public function RenderNavbar() {
// TODO: these should be pulled from the [stats] table
    $timeSidebarBuild = NULL;
$statsQtyTitlesAvail = 2245;
$statsQtyStockPieces = 1395;
$statsQtyStockItems = 753;
$statsQtyArtists = 136;
$statsQtyTopics = 1048;
//---------

	$out = NULL;
	$out .= '<table class=border align=left cellpadding=3 bgcolor="#000000"><tr><td>';
	$out .= '<table class=sidebar bgcolor="#ffffff" cellpadding=5><tr><td>';
	$out .= '<table border=0 class=menu-title width="100%"><tr><td class=menu-title><a href="/">Home</a></td></tr></table>';
	$out .= '<span class=menu-text><dl>';
//<span class=menu-text><p style="background: #eeeeee;"><dl>

	$out .= $this->RenderLinkList();
//  echo '</p></span></dl>';
	$out .= '</dl>';

	$out .= '<form action="/search/">';
	$out .= 'Search '.$statsQtyTitlesAvail.' items:<br>';
	$out .= '
<input size=10 name=search><input type=submit value="Go"><br>
<small><a href="/search/">advanced</a></small>
</form>
<b>Indexes</b>';
	$out .= '<br> ...<a href="'.KWP_SHOP_SUPP.'" title="list of suppliers and what we carry from each one"><b>S</b>uppliers</a>';
	$out .= '<br> ...<a href="'.KWP_SHOP_STOCK.'" title="what\'s currently in stock"><b>S</b>tock</a>';
	$out .= '<br> ...<a href="'.KWP_SHOP_TOPICS.'" title="topic master index (topics are like category tags)"><b>T</b>opics</a>';
	$out .= '<p>';
	$out .= '[[ <a href="'.KWP_WIKI.'" title="vbz wiki homepage"><b>wiki</b></a> ]]<br>';
	$out .= '-- [[ <a href="'.KWP_HELP_HOME.'" title="help main index"><b>Help</b></a> ]]<br>';
	$out .= '-- [[ <a href="'.KWP_HELP_ABOUT.'" title="about vbz.net (probably more than you want to know)"><b>About</b></a> ]]<br>';
	$out .= '-- [[ <a href="'.KWP_HELP_CONTACT.'" title="contact vbz.net (several different methods)"><b>Contact</b></a> ]]<br>';
	$out .= '-- [[ <a href="'.KWP_WIKI.'VBZwiki_talk:Community_portal" title="leave your comments and suggestions"><b>Comments</b></a> ]]<br>';
	$out .= '<p>';
	$out .= '<a href="/email/" title="web form for sending us email">email form</a><br>';
	$out .= '<a href="/cart/" title="your shopping cart">shopping cart</a><p>';
	$out .= '</span></td></tr></table></td></tr></table>';
	return $out;
    }
*/
    protected function RenderLinkList() {
	$out = NULL;
	$arLinks = $this->Page()->NavArray();
	if (!is_null($arLinks)) {
	    $iPfx = '<dt><b>';
	    $iSep = '</b>: ';
	    $iSfx = '';
	    foreach ($arLinks as $key => $val) {
		if (is_null($val)) {
		    $out .= $iPfx.$iSep.$key.$iSfx;
		} else {
		    $out .= $iPfx.$key.$iSep.$val.$iSfx;
		}
	    }
	}
	return $out;
    }
    protected function RenderToolbar() {
	global $fpPages;

	$out = NULL;
	$out .= $this->ToolbarItem($fpPages.'/','home',KS_STORE_NAME.' home page','home page');
	$out .= $this->ToolbarItem($fpPages.'/search/','search','search page','search page');
	$out .= $this->ToolbarItem(KWP_CART_REL,'cart','shopping cart','shopping cart');
	$out .= $this->ToolbarItem(KWP_HELP_HOME,'help','help!','help');
	return $out;
    }
    private function ToolbarItem($iURL,$iIcon,$iTitle,$iAlt) {
	return '<a href="'.$iURL.'"><img border=0 src="'.$this->ImageSpec('/icons/'.$iIcon.'.050pxh.png').'" title="'.$iTitle.'" alt="'.$iAlt.'"></a>';
    }
}
/*%%%%
  NOTE: This still isn't *quite* a skin. Too many things are hard-coded, like button-names and such.
    A lot of this stuff should probably be moved to the Document object.
*/
class clsVbzSkin_admin extends clsVbzSkin {
    public function RenderContHdr() {
	$out = NULL;
      // begin content table
	$out .= "\n".'<table width="100%" id="'.__METHOD__.'.1" class=border cellpadding=5><tr><td>';
	$out .= "\n".'<table width="100%" id="'.__METHOD__.'.2" class=hdr cellpadding=2 align=center>';
      // === LEFT HEADER: Title ===
	//$out .= '<td>';
	return $out;
    }
    public function RenderContFtr() {
	$out = '</td></tr></table></td></tr></table>';
	return $out;
    }
    public function RenderSectionHdr($iTitle) {
	$out = "\n<table width=100% id='".__METHOD__."'>"
	  ."\n<tr><td colspan=2 class=section-title><b>$iTitle</b></td></tr>";
	return $out;
    }
    public function RenderSectionFtr() {
	$out = "\n</table><!-- ".__METHOD__." -->";
	return $out;
    }
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
      NOTE: There's got to be a way to do this that doesn't put so much logic into the skin
    */
    public function RenderNavbar_H(clsNavbar $iNav) {
	$ht = $iNav->Render();
/*
	$ar = $iNav->Nodes();
	$ht = NULL;
	foreach ($ar as $key => $node) {
	    if (!is_null($ht)) {
		$ht .= ' &rarr; ';
	    }
	    $ht .= '['.$node->Render().']';
	}
*/
	return "\n<tr><td align=center>$ht</td></tr>";
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
    /*----
      ACTION: Renders controls for setting username and password
      INPUT:
	iAuth: authorization code emailed to user (URL format)
	iUser: current username (optional)
    */
    public function RenderUserSet($iAuth,$iUser) {
	$htUser = htmlspecialchars($iUser);
	$out =
	  '<input type=hidden name=auth value="'.$iAuth.'">'
	  .' Username:<input name=uname size=10 value="'.$htUser.'">'
	  .' Password:<input type=password name=upass size=10>'
	  .' <input type=submit value="Create Account" name=btnSetLogIn>';
	return $out;
    }
    /*----
      ACTION: renders control for sending a password reset email
    */
    public function RenderForm_Email_RequestReset($iEmail) {
	$out =
	  ' Email address:<input name=uemail size=40 value="'.htmlspecialchars($iEmail).'">'
	  .' <input type=submit value="Send Email" name=btnPassReset>';
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