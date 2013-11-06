<?php
/*
  FILE: pages.php
  PURPOSE: VbzCart page-rendering classes
  HISTORY:
    2012-05-08 split off from store.php
    2012-05-13 removed clsPageOutput (renamed clsPageOutput_WHO_USES_THIS some time ago)
    2013-09-16 clsVbzSkin_Standard renamed clsVbzPage_Standard
    2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
*/

abstract class clsPage {	// this will eventually be in the main libraries
    private $oApp;
    private $oDoc;
    private $oSkin;

    public function __construct() {}

    /*-----
      USAGE: main entry point
      OUTPUT: depends on how document object is handled.
	Simplest is probably to create a child DoPage() which calls parent (this one) first,
	then tells Doc() to render itself.
    */
    public function DoPage() {
	try {
	    $this->ParseInput();
	    $this->HandleInput();
	    $this->DoPageHdr();		// page header
	    $this->DoContHdr();		// content header
	    $this->DoContent();		// main content
	    $this->DoContFtr();		// content footer
	    $this->DoPageFtr();		// page footer
	} catch(exception $e) {
	    $this->DoEmailException($e);
	}
    }

    // environmental objects
    public function App(clsApp $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oApp = $iObj;
	}
	return $this->oApp;
    }
    public function Doc(clsRTDoc $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oDoc = $iObj;
	}
	return $this->oDoc;
    }
    public function Skin(clsSkin $iSkin=NULL) {
	if (!is_null($iSkin)) {
	    $this->oSkin = $iSkin;
	}
	return $this->oSkin;
    }
    protected function Data() {
	return $this->App()->Data();
    }

    // STAGES OF PAGE GENERATION

    public abstract function TitleStr();
    /*-----
      ACTION: Grab any expected input and interpret it
    */
    protected abstract function ParseInput();
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected abstract function HandleInput();
    /*-----
      ACTION: Render page header -- generally the HTML header section and opening <body> tag
    */
    protected abstract function DoPageHdr();
    /*-----
      ACTION: Render page footer -- generally the closing </body></html>
	perhaps preceded by scripts to be loaded after page rendering
	but generally not any HTML that displays directly.
    */
    protected abstract function DoPageFtr();
    /*-----
      ACTION: Render content header -- anything that displays before main content on all pages of a given class
    */
    protected abstract function DoContHdr();
    /*-----
      ACTION: Render content footer -- anything that displays after main content on all pages of a given class
    */
    protected abstract function DoContFtr();
    /*-----
      ACTION: Render main content -- stuff that changes
    */
    protected abstract function DoContent();

    // EXCEPTION HANDLING

    abstract protected function DoEmailException(exception $e);
    abstract protected function Exception_Message_toEmail(array $arErr);
    abstract protected function Exception_Subject_toEmail(array $arErr);
    abstract protected function Exception_Message_toShow($iMsg);
}

/* ===================
  CLASS: clsVbzPage
  PURPOSE: defines basic VBZ page structure
    This includes things to all pages across the site.
    Specifies all the bits that we'll want to have, but doesn't fill them in
    The only content is for error messages (exception handling).
*/
abstract class clsVbzPage extends clsPage {
    public function __construct() {
	parent::__construct();
	$this->Skin($this->NewSkin());
    }
    protected abstract function NewSkin();

    /*-----
      USAGE: Normal main entry point -- should be called from index.php
    */
/*
    public function DoPage() {
	parent::DoPage();
	//echo $this->Doc()->Render();
	try {
	    $this->DoPreContent();
	    $this->DoContent();
	    $this->DoPostContent();
	} catch(exception $e) {
	    $this->DoEmailException($e);
	}
    }
*/
    /*----
      PURPOSE: Renders HTML up to beginning of BODY.
      HISTORY:
	2011-01-11 Extracted everything between <head> and </head> into RenderHtmlHeaderSection()
	2013-09-17 major reworking of page layout code / skins; many functions renamed, rearranged
    */
      protected function DoPageHdr() {
	//$this->strCalcTitle = KS_STORE_NAME.' - '.$this->strName;
	$out = KHT_PAGE_DOCTYPE;
	$out .= "\n<html>\n<head>";
	$out .= $this->RenderHtmlHdr();
	$out .= "\n</head>";
	$out .= KHT_PAGE_BODY_TAG;
	echo $out;
    }
    protected function DoPageFtr() {
	echo "\n</body>\n</html>";
    }
    protected function DoContHdr() {
	echo KS_MSG_SITEWIDE_TOP;
	echo $this->Skin()->RenderContHdr();
	$this->DoNavbar();
    }
    abstract protected function DoNavBar();
    /*----
      ACTION: send an automatic administrative email
      USAGE: called from clsEmailAuth::SendPassReset_forAddr()
	This is kind of klugey.
    */
    public function DoEmail_fromAdmin_Auto($sToAddr,$sToName,$sSubj,$sMsg) {
	if ($this->IsLoggedIn()) {
	    $oUser = $this->App()->User();
	    $sTag = 'user-'.$oUser->KeyValue();
	} else {
	    $sTag = date('Y');
	}

	$oTplt = new clsStringTemplate_array(KS_TPLT_OPEN,KS_TPLT_SHUT,array('tag'=>$sTag));

	$sAddrFrom = $oTplt->Replace(KS_TPLT_EMAIL_ADDR_ADMIN);
	if (empty($sToName)) {
	    $sAddrToFull = $sToAddr;
	} else {
	    $sAddrToFull = $sToName.' <'.$sToAddr.'>';
	}

	$sHdr = 'From: '.$sAddrFrom;
	$ok = mail($sAddrToFull,$sSubj,$sMsg,$sHdr);
	return $ok;
    }


// EXCEPTION handling section //
    
    /*----
      HISTORY:
	2011-03-31 added Page and Cookie to list of reported variables
    */
    protected function DoEmailException(exception $e) {
	$msg = $e->getMessage();

	$arErr = array(
	  'descr'	=> $e->getMessage(),
	  'stack'	=> $e->getTraceAsString(),
	  'guest.addr'	=> $_SERVER['REMOTE_ADDR'],
	  'guest.agent'	=> $_SERVER['HTTP_USER_AGENT'],
	  'guest.ref'	=> NzArray($_SERVER,'HTTP_REFERER'),
	  'guest.page'	=> $_SERVER['REQUEST_URI'],
	  'guest.ckie'	=> NzArray($_SERVER,'HTTP_COOKIE'),
	  );

	$out = $this->Exception_Message_toEmail($arErr);	// generate the message to email
	$subj = $this->Exception_Subject_toEmail($arErr);
	$ok = mail(KS_TEXT_EMAIL_ADDR_ERROR,$subj,$out);	// email the message

	echo $this->Exception_Message_toShow($msg);		// display something for the guest

	throw $e;

	// FUTURE: log the error and whether the email was successful
    }
    protected function Exception_Subject_toEmail(array $arErr) {
	return 'error in VBZ from IP '.$arErr['guest.addr'];
    }
    protected function Exception_Message_toEmail(array $arErr) {
	$guest_ip = $arErr['guest.addr'];
	$guest_br = $arErr['guest.agent'];
	$guest_pg = $arErr['guest.page'];
	$guest_rf = $arErr['guest.ref'];
	$guest_ck = $arErr['guest.ckie'];
	
	$out = 'Description: '.$arErr['descr'];
	$out .= "\nStack trace:\n".$arErr['stack'];
	$out .= <<<__END__

Client information:
 - IP Addr : $guest_ip
 - Browser : $guest_br
 - Cur Page: $guest_pg
 - Prv Page: $guest_rf
 - Cookie  : $guest_ck
__END__;

	return $out;
    }
    protected function Exception_Message_toShow($iMsg) {
	$msg = $iMsg;
	$htContact = '<a href="'.KWP_HELP_CONTACT.'">contact</a>';
	$out = <<<__END__
<b>Ack!</b> We seem to have a small problem here. (If it was a large problem, you wouldn't be seeing this message.)
The webmaster is being alerted about this. Feel free to $htContact the webmaster.
<br>Meanwhile, you might try reloading the page -- a lot of errors are transient,
which makes them hard to fix, which is why there are more of them than the other kind.
<br><br>
We apologize for the nuisance.
<br><br>
<b>Error Message</b>: $msg
<pre>
__END__;
	return $out;
    }
    /*----
      INPUT: current VBZ session, and current cart if there is one
      USAGE: used by some descendent classes and not others
    */
/*
    protected function GetObjects() {
	$tbl = $this->Data()->Sessions();
	$tbl->Page($this);
	$this->objSess = $tbl->GetCurrent();	// get whatever session is currently applicable (existing or new)
	$oSess = $this->SessObj();
	$this->objCart = $oSess->CartObj();
	$this->objCart->objSess = $oSess;	// used for logging
    }
*/
    protected function SessObj() {
	return $this->App()->Session();
	//return $this->objSess;
    }

// ABSTRACT section //
    /*-----
      ACTION: render HTML header (no directly visible content)
    */
    protected abstract function RenderHtmlHdr();
}
/* %%%%
  CLASS: clsVbzPage_Browse
  PURPOSE: Standard browsing page class
*/
abstract class clsVbzPage_Browse extends clsVbzPage {
    private $sName;	// short title: {item name} (goes into html title, prefixed with store name)
    private $sTitle;	// longer, descriptive title: {"item name" by Supplier} (goes at top of page)
    private $sCtxt;	// context of title, if any (typically displayed above it)

    public function __construct() {
	parent::__construct();
	//$this->fpTools = KWP_TOOLS;
	//$this->fsLogo = KWP_LOGO_HEADER;
	//$this->lstTop = new clsNavList();
	//$this->strSheet	= 'browse';
	//$this->strTitleContext = NULL;
	$this->strSideXtra = NULL;
    }
    protected function NewSkin() {
	return new clsVbzSkin_browse($this);	// this will later be a user option
    }
    public function TitleStr($iText=NULL) {
	if (!is_null($iText)) {
	    $this->sTitle = $iText;
	}
	return $this->sTitle;
    }
    public function TCtxtStr($iText=NULL) {
	if (!is_null($iText)) {
	    $this->sCtxt = $iText;
	}
	return $this->sCtxt;
    }
    protected function NameStr($iText=NULL) {
	if (!is_null($iText)) {
	    $this->sName = $iText;
	}
	return $this->sName;
    }

    // SHORTCUTS

    public function NewSection($iTitle) {
	$obj = $this->Doc()->NewSection($iTitle,'hdr-sub');
    }
    public function NewTable($iClass='content') {
	$objDoc = $this->Doc();
	$obj = $objDoc->NewTable();
	$obj->ClassName($iClass);
	return $obj;
    }
    protected function DoSepBar() {
	echo $this->Skin()->Render_HLine();
    }
    protected function DoNavBar() {
// TODO: these should be pulled from the [stats] table
/*
if ($objCache->dtNewest) {
    $timeSidebarBuild=$objCache->dtNewest;
} else {
    $timeSidebarBuild = NULL;
}
*/
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
/*
<span class=menu-text><p style="background: #eeeeee;"><dl>
*/
	$out .= $this->RenderLinkList();
//  echo '</p></span></dl>';
	$out .= '</dl>';
/*
	if (!is_null($iSide)) {
	    $out .= '<dl style="background: #eeeeee;">'.$iSide.'</dl>';
	}
*/
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
	echo $out;
    }
    protected function RenderLinkList() {
    }
    // PAGE SECTIONS

    protected function HandleInput() {
    // nothing to do
    }
/*
    protected function DoContHdr() {
	echo KS_MSG_SITEWIDE_TOP;
	echo $this->Skin()->RenderContHdr();
	echo $this->Skin()->RenderNavbar();
    }
*/
    protected function DoContFtr() {
	global $didPage,$fltStart;

	echo "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";

	echo '<div style="clear: both;" align=right>';
	$this->DoSepBar();
	echo '<table width=100% id="'.__METHOD__.'"><tr><td align=right><small><i>';
	$fltExecTime = microtime(true)-$fltStart;
	$dat = getrusage();
	$fltUserTime = $dat["ru_utime.tv_usec"]/1000000;
	$strServer = $_SERVER['SERVER_SOFTWARE'];
	echo $strServer.' .. ';
	echo 'PHP '.phpversion().' .. Generated in <b>'.$fltUserTime.'</b> seconds (script execution '.$fltExecTime.' sec.) .. ';
	$strWikiPg = $this->strWikiPg;
	if ($strWikiPg) {
	    echo 'wiki: <a href="'.KWP_WIKI.kEmbeddedPagePrefix.$this->strWikiPg.'">'.$strWikiPg.'</a> .. ';
	}
	echo date('Y-m-d H:i:s');
	echo '</i></small></td></tr></table>';
	echo '</div>';
	echo "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
	$didPage = true;
    }


// NEW METHODS for this class //
    /*----
      PURPOSE: Renders HTML inside <head></head> section
      HISTORY:
	2011-01-11 Created
	2013-09-19 Code now moved into Skin.
    */
    protected function RenderHtmlHdr() {
	return $this->Skin()->RenderHtmlHdr($this->NameStr(),'browse');
/*
	$strTitle = KS_STORE_NAME.' - '.$this->strName;
	$out = "\n<title>$strTitle</title>";

	$arVars = array('sheet' => $this->strSheet);
	$objStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$objStrTplt->MarkedValue(KHT_PAGE_STYLE);
	$out .= $objStrTplt->Replace();
	if (!empty($this->strName)) {
	    $ftName = ': '.htmlspecialchars($this->strName);
	} else {
	    $ftName = '';
	}
	$strContent = KS_STORE_NAME_META.$ftName;
	$out .= "\n<meta name=description content=\"$strContent\">";
	return $out;
*/
    }
}

/*%%%%
  CLASS: clsApp
  PURPOSE: base class -- container for the application
*/
abstract class clsApp {
    abstract public function Go();
    abstract public function Session();
    abstract public function Skin();
    abstract public function Page(clsPage $iObj=NULL);
    abstract public function Data(clsDatabase $iObj=NULL);
    abstract public function User();
}

/*%%%%
  CLASS: clsVbzApp
  IMPLEMENTATION: uses VBZ database object, but lets caller choose what type of Page to display
*/
class clsVbzApp extends clsApp {
    private $oPage;
    private $oData;
    //private $oUser;

    public function __construct(clsVbzPage $iPage) {
	$oData = new clsVbzData_Shop(KS_DB_VBZCART);
	//$oData = new clsVbzAdminData(KS_DB_VBZCART);
	$oDoc = new clsRTDoc_HTML();

	$this->Page($iPage);
	$this->Data($oData);
	$iPage->Doc($oDoc);

	$iPage->App($this);
	$oData->App($this);

	//clsVbzUser::AppObj($this);
    }
    public function Go() {
	$this->Page()->DoPage();
    }
    public function Page(clsPage $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oPage = $iObj;
	}
	return $this->oPage;
    }
    public function Data(clsDatabase $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oData = $iObj;
	}
	return $this->oData;
    }
    public function Session() {
	//return $this->Page()->CartObj()->Session();
	//return $this->Data()->Session();
	$tSess = $this->Data()->Sessions();
	$oSess = $tSess->GetCurrent();
	return $oSess;
    }
    public function Skin() {
	return $this->Page()->Skin();
    }
    public function User() {
	return $this->Session()->UserObj();
    }
/*
    public function User(clsVbzUserRec $iUser=NULL) {
	if (!is_null($iUser)) {
	    $this->oUser = $iUser;
	}
	return $this->oUser;
    }
*/
}

