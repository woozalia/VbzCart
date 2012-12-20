<?php
/*
  FILE: store.php
  PURPOSE: VbzCart page-rendering classes
  HISTORY:
    2012-05-08 split off from store.php
    2012-05-13 removed clsPageOutput (renamed clsPageOutput_WHO_USES_THIS some time ago)
*/

if (!defined('LIBMGR')) {
    require(KFP_LIB.'/libmgr.php');
}

clsLibMgr::Add('rtext.html',	KFP_LIB.'/rtext-html.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsRTDoc_HTML','rtext.html');
clsLibMgr::Add('vbz.db',	KFP_LIB_VBZ.'/store.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsVbzTable','vbz.db');
  clsLibMgr::AddClass('clsVbzData','vbz.db');

/* ===================
  CLASS: clsVbzSkin
  PURPOSE: Abstract skin class
    Specifies all the bits that we'll want to have, but doesn't define them
    Provides some basic support services, but no actual content (text or formatting)
*/
abstract class clsVbzSkin {
    protected $objDoc;
    public function __construct() {
	$this->objDoc = new clsRTDoc_HTML();
    }

    public function Doc() {
	return $this->objDoc;
    }

    /*-----
      USAGE: Normal main entry point -- should be called from index.php
    */
    public function DoPage() {
	try {
	    $this->DoPreContent();
	    $this->DoContent();
	    $this->DoPostContent();
	} catch(exception $e) {
	    $this->DoEmailException($e);
	}
    }
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

	$out = $this->Message_toEmail_forException($arErr);	// generate the message to email
	$subj = $this->Subject_toEmail_forException($arErr);
	$ok = mail(KS_TEXT_EMAIL_ADDR_ERROR,$subj,$out);	// email the message

	echo $this->Message_toShow_forException($msg);		// display something for the guest

	throw $e;

	// FUTURE: log the error and whether the email was successful
    }
    abstract protected function Message_toEmail_forException(array $arErr);
    abstract protected function Subject_toEmail_forException(array $arErr);
    abstract protected function Message_toShow_forException($iMsg);
    /*-----
      ACTION: Displays everything *before* the main page contents
    */
    protected function DoPreContent() {
	$this->ParseInput();
	$this->HandleInput();
	$this->RenderHdrBlocks();
    }
    /*-----
      ACTION: Displays everything *after* the main page contents
    */
    protected function DoPostContent() {
	$this->RenderFtrBlocks();
    }
    /*-----
      ACTION: Displays the main page contents
    */
    public function DoContent() {
	echo $this->Doc()->Render();
    }

// ABSTRACT section //
    /*-----
      ACTION: Grab any expected input and interpret it
    */
    protected abstract function ParseInput();
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected abstract function HandleInput();
    /*-----
      ACTION: Render any output that appears *before* the main content
    */
    protected abstract function RenderHdrBlocks();
    /*-----
      ACTION: Render any output that appears *after* the main content
    */
    protected abstract function RenderFtrBlocks();
    /*-----
      ACTION: Start a new section
    */
    public abstract function NewSection($iName);
    /*-----
      ACTION: Open a table, with appropriate CSS class etc.
    */
    public abstract function NewTable($iClass='content');

}
/* ===================
  CLASS: clsVbzSkin_Standard
  PURPOSE: Standard skin class
    Will later be replaceable with other skins
*/
abstract class clsVbzSkin_Standard extends clsVbzSkin {
    private $objApp;
    private $fpTools;
    private $fsLogo;
    protected $lstTop;	// stuff listed at the top of the sidebar
    protected $strSheet;	// name of style sheet to use (without the .css)
    protected $strTitleContext;	// context of short title, in HTML: {Supplier: Department:} (goes above title, in small print)
    protected $strSideXtra;	// any extra stuff for the sidebar

    public function __construct() {
	parent::__construct();
	$this->fpTools = KWP_TOOLS;
	$this->fsLogo = KWP_LOGO_HEADER;
	$this->lstTop = new clsNavList();
	$this->strSheet	= 'browse';	// default
	$this->strTitleContext = NULL;
	$this->strSideXtra = NULL;
    }
    public function App(clsVbzApp $iApp=NULL) {
	if (!is_null($iApp)) {
	    $this->objApp = $iApp;
	}
	return $this->objApp;
    }
    protected function Data() {
	return $this->App()->Data();
    }
    protected function HandleInput() {
    // nothing to do
    }
    public function NewSection($iTitle) {
	$obj = $this->Doc()->NewSection($iTitle,'hdr-sub');
    }
    public function NewTable($iClass='content') {
	$objDoc = $this->Doc();
	$obj = $objDoc->NewTable();
	$obj->ClassName($iClass);
	return $obj;
    }

    protected function Message_toEmail_forException(array $arErr) {
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
    protected function Subject_toEmail_forException(array $arErr) {
	return 'error in VBZ from IP '.$arErr['guest.addr'];
    }
    protected function Message_toShow_forException($iMsg) {
	$msg = $iMsg;
	$out = <<<__END__
<b>Ack!</b> We seem to have a small problem here. (If it was a large problem, you wouldn't be seeing this message.)
The webmaster is being alerted about this.
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

    protected function RenderHdrBlocks() {
	$this->RenderHtmlStart();
	$this->RenderContentHdr();
	$this->DoSidebar();
    }
    protected function RenderFtrBlocks() {
	$this->RenderContentFtr();
	$this->RenderHtmlStop();
    }
    protected function RenderContentFtr() {
	global $didPage,$fltStart;

	echo '<div style="clear: both;" align=right>';
	$this->DoSepBar();
	echo '<table width=100%><tr><td align=right><small><i>';
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
	$didPage = true;
    }
    protected function RenderHtmlStop() {
	echo "\n</body>\n</html>";
    }

// NEW METHODS for this class //
    protected function DoSidebar() {
//	$objCache = $this->CacheMgr();
// TO DO: these should be pulled from the [stats] table
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

	echo '<table class=border align=left cellpadding=3 bgcolor="#000000"><tr><td>';
	echo '<table class=sidebar bgcolor="#ffffff" cellpadding=5><tr><td>';
	echo '<table border=0 class=menu-title width="100%"><tr><td class=menu-title><a href="/">Home</a></td></tr></table>';
	echo '<span class=menu-text><dl>';
/*
<span class=menu-text><p style="background: #eeeeee;"><dl>
*/
	echo $this->lstTop->Output('<dt><b>','</b>: ','');
//  echo '</p></span></dl>';
	echo '</dl>';
	if ($this->strSideXtra) {
	    echo '<dl style="background: #eeeeee;">'.$this->strSideXtra.'</dl>';
	}
	echo '<form action="/search/">';
	echo 'Search '.$statsQtyTitlesAvail.' items:<br>';
?>
<input size=10 name=search><input type=submit value="Go"><br>
<small><a href="/search/">advanced</a></small>
</form>
<b>Indexes</b>
<?php
	echo '<br> ...<a href="'.KWP_SHOP_SUPP.'" title="list of suppliers and what we carry from each one"><b>S</b>uppliers</a>';
/*
	echo '<br> ...<a href="/stock/" title="'
	  .$statsQtyStockPieces.' pieces, '
	  .$statsQtyStockItems.'. items"><b>S</b>tock</a> ('.$statsQtyStockPieces.')';
*/
	echo '<br> ...<a href="'.KWP_SHOP_STOCK.'" title="what\'s currently in stock"><b>S</b>tock</a>';
	echo '<br> ...<a href="'.KWP_SHOP_TOPICS.'" title="topic master index (topics are like category tags)"><b>T</b>opics</a>';
//	echo '<br> ...<a href="/artists/" title="'.$statsQtyArtists.'.artists"><b>A</b>rtists</a> ('.$statsQtyArtists.')';
	echo '<p>';
	echo '[[ <a href="'.KWP_WIKI.'" title="vbz wiki homepage"><b>wiki</b></a> ]]<br>';
	echo '-- [[ <a href="'.KWP_HELP_HOME.'" title="help main index"><b>Help</b></a> ]]<br>';
	echo '-- [[ <a href="'.KWP_HELP_ABOUT.'" title="about vbz.net (probably more than you want to know)"><b>About</b></a> ]]<br>';
	echo '-- [[ <a href="'.KWP_HELP_CONTACT.'" title="contact vbz.net (several different methods)"><b>Contact</b></a> ]]<br>';
	echo '-- [[ <a href="'.KWP_WIKI.'VBZwiki_talk:Community_portal" title="leave your comments and suggestions"><b>Comments</b></a> ]]<br>';
	echo '<p>';
	echo '<a href="/email/" title="web form for sending us email">email form</a><br>';
	echo '<a href="/cart/" title="your shopping cart">shopping cart</a><p>';
	echo '</span></td></tr></table></td></tr></table>';
    }
    protected function DoSepBar() {
      echo $this->Render_HLine();
    }
    protected function ImageSpec($iFileName) {
	return $this->fpTools.'/img'.$iFileName;
    }
    public function Render_HLine($iHeight=NULL) {
      $htHt = is_null($iHeight)?'':('height='.$iHeight);
      return '<img src="'.$this->ImageSpec('/bg/hlines/').'"'.$htHt.' alt="-----" width="100%">';
    }
    private function ToolbarItem($iURL,$iIcon,$iTitle,$iAlt) {
	return '<a href="'.$iURL.'"><img border=0 src="'.$this->ImageSpec('/icons/'.$iIcon.'.050pxh.png').'" title="'.$iTitle.'" alt="'.$iAlt.'"></a>';
    }
    protected function DoToolbar() {
	global $fpPages;

	echo $this->ToolbarItem($fpPages.'/','home',KS_STORE_NAME.' home page','home page');
	echo $this->ToolbarItem($fpPages.'/search/','search','search page','search page');
	echo $this->ToolbarItem(KWP_CART_REL,'cart','shopping cart','shopping cart');
	echo $this->ToolbarItem(KWP_HELP_HOME,'help','help!','help');
    }

// NEW METHODS for this class //
    /*----
      PURPOSE: Renders HTML inside <head></head> section
      HISTORY:
	2011-01-11 Created
    */
    protected function RenderHtmlHeaderSection() {
	$strTitle = KS_STORE_NAME.' - '.$this->strName;
	$out = "\n<title>$strTitle</title>";

	$arVars = array('sheet' => $this->strSheet);
	$objStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$objStrTplt->MarkedValue(KHT_PAGE_STYLE);
	$out .= $objStrTplt->Replace();
	//$out .= KHT_PAGE_STYLE;
	if (!empty($this->strName)) {
	    $ftName = ': '.htmlspecialchars($this->strName);
	} else {
	    $ftName = '';
	}
	$strContent = KS_STORE_NAME_META.$ftName;
	$out .= "\n<meta name=description content=\"$strContent\">";
	return $out;
    }
    /*----
      PURPOSE: Renders HTML up to beginning of BODY.
      HISTORY:
	2011-01-11 Extracted everything between <head> and </head> into RenderHtmlHeaderSection()
    */
    protected function RenderHtmlStart() {
	//$this->strCalcTitle = KS_STORE_NAME.' - '.$this->strName;
	$out = KHT_PAGE_DOCTYPE;
	$out .= "\n<html>\n<head>";
	$out .= $this->RenderHtmlHeaderSection();
	$out .= "\n</head>";
	$out .= KHT_PAGE_BODY_TAG;
	echo $out;
    }
    protected function RenderContentHdr() {
	//$strWikiPg = $this->strWikiPg;

      // begin content header
	echo '<table width="100%" class=border cellpadding=5><tr><td>';
	echo '<table width="100%" class=hdr cellpadding=2><tr>';
      // === LEFT HEADER: Title ===
	echo '<td>';
	echo '<a href="'.KWP_HOME_ABS.'"><img align=left border=0 src="'.$this->fsLogo.'" title="'.KS_STORE_NAME.' home" alt="'.KS_SMALL_LOGO_ALT.'"></a>';
	if ($this->strTitleContext) {
	  echo '<span class=pretitle><b><a href="/">'.KS_STORE_NAME.'</a></b>: '.$this->strTitleContext.'</span><br>';
	}
	echo '<span class=page-title>'.$this->strTitle.'</span></td>';
      // === END LEFT HEADER ===

      // === RIGHT HEADER: nav icons ===
	echo '<td align=right>';
	$this->DoToolbar();
	echo '</td>';
      // === END RIGHT HEADER ===
	echo "\n</tr></table>\n</td></tr></table><!-- end html header -->";
    }
}

/*%%%%
  CLASS: clsVbzApp
  PURPOSE: container for the chosen skin and database
*/
class clsVbzApp {
    private $objSkin;
    private $objData;

    public function __construct(clsVbzSkin $iSkin, clsVbzData $iData) {
	$this->objSkin = $iSkin;
	$this->objData = $iData;
	$iSkin->App($this);
	$iData->App($this);
    }
    public function Skin() {
	return $this->objSkin;
    }
    public function Data() {
	return $this->objData;
    }
}

