<?php
/*
  FILE: vbz-page-shop.php
  PURPOSE: VbzCart page-rendering classes for customer-facing pages
  HISTORY:
    2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
    2013-11-22 clsVbzPage_Browse extracted from vbz-page.php into vbz-page-browse.php
    2016-11-22 major rewrite underway for new page-rendering system
    2016-11-23 renamed file from vbz-page-browse.php to vbz-page-shop.php, class from vcBrowsePage to vcPage_shop
*/
class vcpePageHeader_shop extends vcPageHeader {

    // ++ OUTPUT ++ //

    protected function RenderBefore() {
	$oGlob = vcGlobals::Me();
	$sWhere = __METHOD__;
	$urlHome = $oGlob->GetWebPath_forAppBase().'/';
	$urlLogo = $oGlob->GetWebPath_forSiteLogo();
	$sStore = KS_SITE_NAME;
	$sLogoAlt = KS_SMALL_LOGO_ALT;
	$out = KS_MSG_SITEWIDE_TOP;
	$out .= <<<__END__

<!-- $sWhere -->
<table width="100%" id="cont-header-outer" class=border cellpadding=5><tr><td>
<table width="100%" id="cont-header-inner" class=hdr cellpadding=2><tr>
<td>
<a href="$urlHome"><img align=left border=0 src="$urlLogo" title="$sStore home" alt="$sLogoAlt"></a>
__END__;
	return $out;
    }
    protected function RenderContent() {
	$sWhere = __METHOD__;
	$sTitle = $this->GetTitleString();
	$sContext = $this->GetContextString();
	$sSiteName = KS_SITE_NAME;
	if (!is_null($sContext)) {
	    $htContext = "\n<span class=pretitle><b><a href=\"/\">$sSiteName</a></b>: $sContext</span><br>";
	} else {
	    $htContext = NULL;
	}
	
	return "\n<!-- $sWhere -->\n$htContext\n<span class=page-title>$sTitle</span></td>";
    }
    protected function RenderAfter() {
	$sWhere = __METHOD__;
	$htToolBar = $this->RenderToolbar();
	
	return <<<__END__

<!-- $sWhere -->
<td align=right>
$htToolBar
</td>
<!-- END RIGHT HEADER -->
</tr></table>
</td></tr></table>
<!-- /$sWhere -->
__END__;
    }
    /*
    // TODO: rewrite this to better follow the conventions of its parent class
    public function Render() {
	//$oPage = $this->GetParent();
	$sTitle = $this->GetTitleString();
	$sContext = $this->GetContextString();
    
	$urlHome = KWP_HOME_ABS;
	$urlLogo = KWP_LOGO_HEADER;
	$sStore = KS_SITE_NAME;
	$sLogoAlt = KS_SMALL_LOGO_ALT;
	$sSiteName = KS_SITE_NAME;
	if (!is_null($sContext)) {
	    $htContext = "\n<span class=pretitle><b><a href=\"/\">$sSiteName</a></b>: $sContext</span><br>";
	} else {
	    $htContext = NULL;
	}
	$htToolBar = $this->RenderToolbar();
	$sClass = __CLASS__;

	$out = KS_MSG_SITEWIDE_TOP;
	$out .= <<<__END__

<!-- BEGIN $sClass rendering -->
<table width="100%" id="cont-header-outer" class=border cellpadding=5><tr><td>
<table width="100%" id="cont-header-inner" class=hdr cellpadding=2><tr>
<!-- LEFT OF HEADER (Title etc.) -->
<td>
<a href="$urlHome"><img align=left border=0 src="$urlLogo" title="$sStore home" alt="$sLogoAlt"></a>
$htContext
<span class=page-title>$sTitle</span></td>
<!-- END LEFT OF HEADER -->

<!-- RIGHT HEADER: nav icons -->
<td align=right>
$htToolBar
</td>
<!-- END RIGHT HEADER -->
</tr></table>
</td></tr></table>
<!-- END $sClass rendering -->

__END__;

	return $out;
    } */
    // TODO: Make this a wiki template-page.
    protected function RenderToolbar() {
	$oGlob = vcGlobals::Me();
	
	$wpHome = $oGlob->GetWebPath_forAppBase().'/';
	$wpSearch = $oGlob->GetWebPath_forSearchPages();
	$wpCart = $oGlob->GetWebPath_forCartPage();
	$wpHelp = $oGlob->GetWebPath_forHelpPage();
	
	$wsHomeIcon = $oGlob->GetWebSpec_forHomeIcon();
	$wsSearchIcon = $oGlob->GetWebSpec_forSearchIcon();
	$wsCartIcon = $oGlob->GetWebSpec_forCartIcon();
	$wsHelpIcon = $oGlob->GetWebSpec_forHelpIcon();
	$out = NULL
	  .$this->RenderToolbarItem($wpHome,$wsHomeIcon,KS_SITE_NAME.' home page','home page')
	  .$this->RenderToolbarItem($wpSearch,$wsSearchIcon,'search page','search page')
	  .$this->RenderToolbarItem($wpCart,$wsCartIcon,'shopping cart','shopping cart')
	  .$this->RenderToolbarItem($wpHelp,$wsHelpIcon,'help!','help')
	  ;
	return $out;
    }
    protected function RenderToolbarItem($url,$wsIcon,$sTitle,$sAlt) {
	return '<a href="'.$url.'">'
	  .'<img border=0 src="'
	  .$wsIcon
	  .'" title="'.$sTitle
	  .'" alt="'.$sAlt
	  .'"></a>'
	  ;
    }
    
    // -- OUTPUT -- //

}
class vcNavElement_home extends fcPageElement {

    // ++ CEMENTING ++ //

    public function DoEvent($nEvent){}	// does not respond to any events
    public function Render() {
	return <<<__END__
	<table border=0 class=menu-title width="100%">
	  <tr><td class=menu-title><a href="/">Home</a></td></tr>
	</table>
__END__;
    }
    
    // -- CEMENTING -- //

}
class vcNavElement_search extends fcPageElement {
    public function DoEvent($nEvent){}	// does not respond to any events
    public function Render() {
	$statsQtyTitlesAvail = 'all';	// kluge
	return <<<__END__

	  <form action="/search/">
	    Search $statsQtyTitlesAvail items:<br>
	    <input size=10 name=search><input type=submit value="Go"><br>
	    <small><a href="/search/">advanced</a></small>
	  </form>
__END__;
    }
}
class vcNavItem_catLink extends fcNavLinkFixed {
}
class vcNavItem_wikiLink extends vcNavItem_catLink {

    // ++ CEMENTING ++ //
    
    public function RenderContent() {
	$htLink = $this->RenderLink();
	return "[[ $htLink ]]";
    }
    
    // -- CEMENTING -- //

}
class vcNavFolder extends fcMenuFolder {

    // ++ OUTPUT ++ //

    /*----
      TAGS: OVERRIDE
      NOTE: if we want this output to be bracketed with <li></li>:
	* change name to RenderContent() 
	* call GetValue() instead of RenderContent()
    */
    protected function RenderSelf() {
	$sText = $this->RenderContent();
	return "\n<hr><big>$sText</big>";	// TODO: make this a style; clean up the layout
    }
    // OVERRIDE
    protected function RenderNodesBlock() {
	return "\n<span class=menu-text>"
	  .$this->RenderNodes()
	  ."\n</span>"
	  ;
    }

    // -- OUTPUT -- //

}
class vcNavFolder_catLinks extends vcNavFolder {

    // ++ EVENTS ++ //

    // OVERRIDE (I think)
    protected function OnCreateElements() {
	$oGlob = vcGlobals::Me();
	$this->SetNode(new vcNavItem_catLink($oGlob->GetWebPath_forCatalogPages(),
	  'Suppliers','our suppliers, and what we carry from each one'));
	$this->SetNode(new vcNavItem_catLink($oGlob->GetWebPath_forStockPages(),
	  'Stock',"what's currently in stock"));
	$this->SetNode(new vcNavItem_catLink($oGlob->GetWebPath_forTopicPages(),
	  'Topics','topic master index (topics are like category tags)'));
    }

    // -- EVENTS -- //

}
class vcNavFolder_wikiLinks extends vcNavFolder {

    // ++ EVENTS ++ //

    protected function OnCreateElements() {
	$oGlob = vcGlobals::Me();
	//$this->SetNode(new vcNavItem_wikiLink(KURL_WIKI_PUBLIC,'Main','vbz wiki homepage'));
	$this->SetNode(new vcNavItem_wikiLink($oGlob->GetWebPath_forHelpPage(),'Help','help main index'));
	$this->SetNode(new vcNavItem_wikiLink($oGlob->GetWebPath_forContactPage(),'Contact','contact '.KS_SITE_NAME));
	//$kwpCommunity = KURL_WIKI_PUBLIC.'VBZwiki_talk:Community_portal';
	// 2016-12-04 new account creation is currently disabled
	//$this->SetNode(new vcNavItem_wikiLink($kwpCommunity,'Comments','leave public comments and suggestions'));
    }

    // -- EVENTS -- //

}
class vcNavbar_shop extends fcMenuFolder {

    // ++ CEMENTING ++ //

    protected function OnCreateElements() {
	$this->CreateNamedNode('home','vcNavElement_home');
	$this->CreateNamedNode('search','vcNavElement_search');
	$this->CreateNamedNode('cat','vcNavFolder_catLinks')
	  ->SetValue('Catalog:');
	$this->CreateNamedNode('wiki','vcNavFolder_wikiLinks')
	  ->SetValue('Wiki pages:');
	// TODO: more elements?
    }
    protected function OnRunCalculations() {
    }

    // -- CEMENTING -- //
    // ++ OUTPUT ++ //
    
    protected function RenderNodesBlock() {
	$htNodes = $this->RenderNodes();
	$sClass = __CLASS__;
	return <<<__END__
	
<!-- BEGIN $sClass rendering -->
<span class=menu-text>
  <table class=border align=left cellpadding=3 bgcolor="#000000">
    <tr><td>
      <table class=sidebar bgcolor="#ffffff" cellpadding=5>
	<tr><td>
$htNodes
	</td></tr>
      </table>
    </td></tr>
  </table>
</span>
<!-- END $sClass rendering -->

__END__;
    }
    
    // -- OUTPUT -- //
    // ++ OUTPUT ++ //
/* 2016-12-04 adapted
    public function Render_OLD() {
	$oNav = $this->GetNavbarObject();
	$oNav->Decorate("\n<br> ...",'','');
	$htBar = $oNav->Render();

	if (is_null($htBar)) {
	    $htThisPage = NULL;
	} else {
	    $htThisPage = '<b>This Page</b>'.$htBar.'<br>';
	}

	$kwpShopSupp = KWP_SHOP_SUPP;
	$kwpShopStock = KWP_SHOP_STOCK;
	$kwpShopTpix = KWP_SHOP_TOPICS;
	$kwpWiki = KURL_WIKI_PUBLIC;
	$kwpHelpHome = KWP_HELP_HOME;
	$kwpHelpAbout = KWP_HELP_ABOUT;
	$kwpHelpCont = KWP_HELP_CONTACT;
	$kwpCommunity = KURL_WIKI_PUBLIC.'VBZwiki_talk:Community_portal';
	// TODO: work out a good way of compiling stats, and get numbers from there
	//$statsQtyTitlesAvail = $this->Data()->StkItems()->Count_inStock();
	$statsQtyTitlesAvail = 'all';	// kluge

	// all of the following should eventually be part of the navbar, except maybe the search form

	$urlEmail = KURL_EMAIL;
	$urlCart = KWP_CART;

	$ht = <<<__END__
<!-- BEGIN NAVBAR -->
<table class=border align=left cellpadding=3 bgcolor="#000000">
  <tr><td>
    <table class=sidebar bgcolor="#ffffff" cellpadding=5>
      <tr><td>
	<table border=0 class=menu-title width="100%">
	  <tr><td class=menu-title><a href="/">Home</a></td></tr>
	</table>
	<span class=menu-text>
	  <form action="/search/">
	    Search $statsQtyTitlesAvail items:<br>
	    <input size=10 name=search><input type=submit value="Go"><br>
	    <small><a href="/search/">advanced</a></small>
	  </form>
	  $htThisPage
	  <b>Indexes</b>
	  <br> ...<a href="$kwpShopSupp" title="list of suppliers and what we carry from each one">Suppliers</a>
	  <!-- <br> ...<a href="$kwpShopStock" title="what\'s currently in stock">Stock</a> -->
	  <br> ...<a href="$kwpShopTpix" title="topic master index (topics are like category tags)">Topics</a>
	  <p></p>
	  [[ <a href="$kwpWiki" title="vbz wiki homepage"><b>wiki</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpHome" title="help main index"><b>Help</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpAbout" title="about vbz.net (probably more than you want to know)"><b>About</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpCont" title="contact vbz.net (several different methods)"><b>Contact</b></a> ]]<br>
	  -- [[ <a href="$kwpCommunity" title="leave your comments and suggestions"><b>Comments</b></a> ]]<br>
	  <p></p>
	  <a href="$urlEmail" title="web form for sending us email">email form</a><br>
	  <a href="$urlCart" title="your shopping cart">shopping cart</a>
	</span>
      </td></tr>
    </table>
  </td></tr>
</table>
<!-- END NAVBAR -->
__END__;

	return $ht;
    }
*/
    // -- OUTPUT -- //
    // ++ OBJECTS ++ //
/* 2016-12-04 no longer needd
    private $oNav;
    protected function GetNavbarObject() {
	if (empty($this->oNav)) {
	    $this->oNav = new fcNavbar_flat();
	}
	return $this->oNav;
    }
    */

    // -- OBJECTS -- //
}
/*::::
  HISTORY:
    2016-12-04 I originally had the Class_for*Message() methods in here,
      but for now they'll be the same for admin pages -- so moving them
      into the common base class (vcPageContent).
*/
abstract class vcPageContent_shop extends vcPageContent {
}
abstract class vcTag_body_shop extends vcTag_body {

    // ++ CLASSES ++ //
    
    protected function Class_forPageHeader() {
	return 'vcpePageHeader_shop';
    }
    protected function Class_forPageNavigation() {
	return 'vcNavbar_shop';
    }

    // -- CLASSES -- //

}
abstract class vcTag_html_shop extends vcTag_html {
}
/*::::
  PURPOSE: Base class for all customer-facing pages
*/
abstract class vcPage_shop extends vcPage {

    // ++ CEMENTING ++ //

    protected function OnRunCalculations(){
	$this->UseStyleSheet('browse');
    }
    
    // -- CEMENTING -- //
}

