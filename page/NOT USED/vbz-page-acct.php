<?php
/*
  PURPOSE: page class for administering user account
  HISTORY:
    2013-10-10 created
    2015-08-28 vbz-const-user.php no longer needed; constants are defined in ferreteria/page.php
    2016-12-04 It looks like the code here should be migrated/adapted to vcPageAdmin and this file should be removed.
*/

class vcPageAdmin_Acct extends vcPageAdmin {
    use ftPageMenu;

    private $htTSub;	// title and subtitle for page
    private $doAddEmail, $sEmail;
    private $doGetAuth, $sAuth;

    // ++ SETUP ++ //
    
    public function __construct() {
	parent::__construct();
	$this->UseStyleSheet('admin');
    }

    // -- SETUP -- //
    // ++ ACCESS METHODS ++ //

    protected function BaseURL() {
	return KURL_UADMIN;
    }

    protected function MenuPainter_new() {
	$op = new fcMenuPainter_UL('/');
	//$op->Home($this->MenuHome());
	$op->BaseURL($this->BaseURL());
	return $op;
    }
    public function CurPageKey() {
	return 'user';
    }

    // -- ACCESS METHODS -- //
    // ++ MENU ++ //

    // CEMENTING
    protected function MenuHome_new() {
	// the home node needs a name (e.g. 'HOME'), else it will be triggered by the base URL
	$omHome = new fcMenuLink($this->MenuRoot(),'0','Admin Home','User Control Panel');
	  $omHome->URL($this->BaseURL(),TRUE);	// gets rid of "page:", but there's probably a better way
	  $omHome->NeedPermission(NULL);
	  /* 2015-11-30 Actually, maybe this is unnecessary.
	$omAux = new fcMenuLink($omHome,'aux','Auxiliary','auxiliary administration functions');
	  $omAux->NeedPermission(NULL);	// TODO: this should require admin privs
	  $omAux->GoCode('(insert PHP code here)'); */
	fcDropInManager::ScanDropins(KFP_VBZ_DROPINS,$omHome);
	// tell App to un-cache existing Session object, so that next request will pull up dropin:
	$this->AppObject()->SessionTable()->ClearSession();

	/*
	$om = new fcMenuFolder($miHome,'user','User Account','manage your user account');
	  $om->NeedPermission(NULL);
	  $omi = new fcMenuLink($om,'~addr','Addresses','Addresses & Contact','manage your addresses and contact information');
	    $rcU = $this->App()->User();
	    $idU = $rcU->GetKeyValue();
	    $omi->URL("addr/id:$idU");	// TODO: create an absolute-URL method so we can use $rcU->AdminLink()
	    //$omi->GoCode('return $this->RenderAddresses();');
	    $omi->NeedPermission(NULL);
	    */

	return $omHome;
    }

    // -- MENU -- //
    // ++ ABSTRACT IMPLEMENTATION ++ //

    /*-----
      ACTION: Grab any expected input and interpret it
      TODO - there will be a user account management form,
	and its data will need parsing
    */
    protected function ParseInput() {
	$this->ParseInput_Login();

	$this->doAddEMail = !empty($_POST['btnAddEmail']);
	if ($this->doAddEMail) {
	    $this->sEmail = $_POST['uemail'];
	}
	$this->doGetAuth = !empty($_GET['auth']);
	if ($this->doGetAuth) {
	    $this->sAuth = $_GET['auth'];
	}
    }
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected function HandleInput() {
	$this->HandleInput_Login();
	$this->HandleInput_RedirAnons();
	//$this->BuildMenu();
	$this->htTSub = NULL;	// subtitle
	if ($this->doAddEmail) {
	    $sTitle = 'Account: Send Email Authorization Link';
	} elseif ($this->doGetAuth) {
	    $sTitle = 'Account: Authorize Email Address';
	} elseif (is_null($this->MenuNode())) {
	    $sTitle = 'Control Panel';
	} else {
	    // use information from menu item to set title etc.
	    $oMNode = $this->MenuNode();
	    $sTitle = $oMNode->Title();
	    // 2016-06-26 TODO: this probably should set something somewhere...?
	    //$sTSub = $oMNode->Descr();
	    //$htTSub = '<span class=subtitle>'.$sTSub.'</span>';
	}
	$this->GetSkinObject()->SetPageTitle($sTitle);
	$this->MenuNode_Init();
	$this->HandleContent_forUser();
    }
    protected function PreSkinBuild() {
	// nothing to do here, but must be instantiated
    }
    protected function PostSkinBuild() {
	$this->HandleWrapper();		// add more stuff to the content wrapper
    }
    /*----
      ACTION: If not logged in, redirect to login page
    */
    protected function HandleInput_RedirAnons() {
	if (!$this->IsLoggedIn() && !$this->IsAuthLink()) {
	    // if we're neither logged in nor checking an auth link...
	    clsHTTP::Redirect(KURL_LOGIN);
	}
    }
    /*----
      ACTION: create the content for logged-in users
    */
    protected function HandleContent_forUser() {
	// I think this only sets the browser title,
	//	though it *could* set the text for the title header
	//$this->Skin()->SetPageTitle($this->TitleString_html());	// 2016-06-26 seems redundant

	$ht = $this->Content();

	$this->GetSkinObject()->Content('acct-logged-in',$ht);
    }
    protected function HandleWrapper() {
	$this->GetSkinObject()->PieceAdd('cont.hdr',$this->ContHdr());
	//$this->Skin()->PieceAdd('cont.ftr',$this->ContFtr());
    }
    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
      HISTORY:
	2013-11-27 copied from vbz-page-ckout.php
	2013-12-22 Shouldn't this be sending each of these components (menu, title bar)
	  to the skin, so the skin can choose how to order them? TODO: FIX
	2016-06-26 Yes, that's what should be happening. That way the Page wouldn't ever need
	  to retrieve the PageTitleString from the Skin() object. Kluging it for now by making
	  that public so we can do this.
	2016-11-13 revising to work with new skinning scheme
    */
    protected function ContHdr() {
	$sClass = __CLASS__;
//	$sHeader = $this->GetSkinObject()->GetPageTitle();

	$htMsgs = clsHTTP::DisplayOnReturn();	// get any redirect messages
	$htMenu = $this->CtrlMenu();
	$htTitle = $this->ActionHeader($sHeader,$this->PageHeaderWidgets(),'page-header').$this->htTSub;
	$out = <<<__END__
<!-- BEGIN ContHdr in $sClass -->
$htMenu
$htMsgs
$htTitle
<!-- END ContHdr in $sClass -->
__END__;
	return $out;
    }
    /*----
      IMPLEMENTATION: displays control menu
    */
    protected function CtrlMenu() {
	$this->MenuPainter()->DecorateURL($this->BaseURL().'page:',NULL);
	$out = "\n<ul class=menu>"
	  .$this->MenuPainter()->Render($this->MenuHome())
	  ."\n</ul>";
	return $out;
    }
    /*-----
      ACTION: Render content footer -- anything that displays after main content on all pages of a given class
    */
/*
    protected function ContFtr() {
	// return '</td></tr></table></form>';
	return '</form>';
    }
*/
    /*-----
      ACTION: Render main content -- stuff that changes
    */
    public function Content() {
	$out = '<div class="form-block">'.$this->RenderLoginCheck().'</div>';
	return $out;
    }
    protected function RenderLoginCheck() {
	$out = NULL;
	if ($this->doAddEMail) {
	    $out = $this->RenderAddEmail();
	} elseif ($this->doGetAuth) {
	    $out = $this->RenderGetAuth();
	}

	if ($this->isLoggedIn()) {
	    $out .= $this->RenderContent_forUser();
	} else {
	    if (!$this->IsAuthLink()) {
		$out .= $this->GetSkinObject()->ErrorMessage('You are not logged in.');
	    }
	    $out .= $this->RenderLogin();
	}
	return $out;
    }
    protected function RenderAddEmail() {
	$tEmails = $this->EmailAuth();
	$out = $tEmails->SendPassReset_forAddr($this->sEmail)
	  .$this->Skin()->RenderTableHLine();
	return $out;
    }
    protected function RenderGetAuth() {
	if ($this->isLoggedIn()) {
	    // TODO: attach customer profile(s), if email address matches
	    die('Implement logged-in code now.');
	} else {
	    // if auth is valid, then allow setting username/password
	    $out = $this->RenderUserAccess();
	}
	$out .= $this->GetSkinObject()->RenderTableHLine();

	return $out;
    }
    public function RenderContent_forUser() {
	return $this->MenuNode_Exec();
    }
    /*
    public function RenderAddresses() {
	// show current customer profiles
	$out = '<center>'.$this->Render_Customers().'</center>'
	  .$this->Skin()->RenderTableHLine()
	// option to add more emails
	  .'If you have previously placed orders using other email addresses to which you currently have access, you may claim ownership of those orders here.<br>'
	  .$this->Skin()->RenderForm_Email_RequestAdd(Nz($this->sEmail));
	return $out;
    }*/
    /*----
      ACTION: render HTML to show all customer profiles for this user
    *//*
    public function Render_Customers() {
	$id = $this->App()->User()->GetKeyValue();
	$tCust = $this->CustsTbl();
	$rc = $tCust->GetRecs_forUser($id);
	$ht = $rc->Render_asTable();
	return $ht;
    }
    protected function CustsTbl() {
	return $this->App()->Data()->Make('VbzAdminCusts');
    }
    */
}