<?php
/*
  PURPOSE: page class for administering user account
  HISTORY:
    2013-10-10 created
*/
require_once('vbz-const-user.php');

class clsPageAdmin_Acct extends clsVbzPage_Admin {
    private $htTSub;	// title and subtitle for page
    private $doAddEmail, $sEmail;
    private $doGetAuth, $sAuth;

    public function __construct() {
	parent::__construct();
	$this->Skin()->Sheet('ckout');	// for now

	//$this->BuildMenu(); where this used to happen
    }

    // ++ ACCESS METHODS ++ //

    protected function BaseURL() {
	return KWP_UADMIN;
    }

    protected function MenuPainter_new() {
	$op = new clsMenuPainter_UL('/');
	$op->Home($this->MenuHome());
	$op->BaseURL($this->BaseURL());
	return $op;
    }
    public function CurPageKey() {
	return 'user';
    }

    // -- ACCESS METHODS -- //
    // ++ NEW BITS TO BUILD ++ //

    protected function BuildMenu() {
	// the home node needs a name (e.g. 'HOME'), else it will be triggered by the base URL
	$miHome = new clsMenuLink(NULL,'0','Admin Home','User Control Panel');
	  $miHome->URL($this->BaseURL());
	  $miHome->NeedPermission(NULL);
	clsDropInManager::ScanDropins(KFP_VBZ_DROPINS,$miHome);
	/*
	$om = new clsMenuFolder($miHome,'user','User Account','manage your user account');
	  $om->NeedPermission(NULL);
	  $omi = new clsMenuLink($om,'~addr','Addresses','Addresses & Contact','manage your addresses and contact information');
	    $rcU = $this->App()->User();
	    $idU = $rcU->KeyValue();
	    $omi->URL("addr/id:$idU");	// TODO: create an absolute-URL method so we can use $rcU->AdminLink()
	    //$omi->GoCode('return $this->RenderAddresses();');
	    $omi->NeedPermission(NULL);
	    */

	$this->MenuHome($miHome);	// save pointer to menu registry
    }

    // -- NEW BITS TO BUILD -- //
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
	$this->BuildMenu();
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
	    $sTSub = $oMNode->Descr();
	    $htTSub = '<span class=subtitle>'.$sTSub.'</span>';
	}
	$this->TitleString($sTitle);
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
	    clsHTTP::Redirect(KWP_LOGIN);
	}
    }
    /*----
      ACTION: create the content for logged-in users
    */
    protected function HandleContent_forUser() {
	// I think this only sets the browser title,
	//	though it *could* set the text for the title header
	$this->Skin()->PageTitle($this->TitleString());

	$ht = $this->Content();

	$this->Skin()->Content('acct-logged-in',$ht);
    }
    protected function HandleWrapper() {
	$this->Skin()->PieceAdd('cont.hdr',$this->ContHdr());
	//$this->Skin()->PieceAdd('cont.ftr',$this->ContFtr());
    }
    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
      HISTORY:
	2013-11-27 copied from vbz-page-ckout.php
	2013-12-22 Shouldn't this be sending each of these components (menu, title bar)
	  to the skin, so the skin can choose how to order them? TODO: FIX
    */
    protected function ContHdr() {
	$sClass = __CLASS__;
	$sHeader = $this->TitleString();

	$htMenu = $this->CtrlMenu();
	$htTitle = $this->ActionHeader($sHeader,$this->PageHeaderWidgets(),'page-header').$this->htTSub;
	$out = <<<__END__
<!-- BEGIN ContHdr in $sClass -->
$htMenu
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
	  .$this->MenuPainter()->Render(NULL)
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
	//$out = '<table class="form-block" id="page-acct"><tr><td>'.$this->RenderLoginCheck().'</td></tr></table>';
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
		$out .= $this->Skin()->ErrorMessage('You are not logged in.');
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
	$out .= $this->Skin()->RenderTableHLine();

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
	$id = $this->App()->User()->KeyValue();
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