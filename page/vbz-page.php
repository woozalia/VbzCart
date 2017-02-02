<?php
/*
  FILE: vbz-page.php
  PURPOSE: VbzCart page-rendering classes
  CODE RULES: For now, I'm putting all the page-type variations in the Page class, the Page Header class (inside <body>), and the Content Header class.
    This *could* be changed so the <body> tag does more of the specializations, but I'm trying to avoid proliferation of specialty classes.
  HISTORY:
    2012-05-08 split off from store.php
    2012-05-13 removed clsPageOutput (renamed clsPageOutput_WHO_USES_THIS some time ago)
    2013-09-16 clsVbzSkin_Standard renamed clsVbzPage_Standard
    2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
    2016-11-21 complete rework in progress
*/
class vcpeMessage_error extends fcpeSimple {
    public function Render() {
	$s = $this->GetValue();
	return '<center>'
	  .'<table class="error-message">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_ALERT.'" alt=alert title="error message indicator" /></td>'
	  ."<td valign=middle>$s</td>"
	  .'</tr></table>'
	  .'</center>'
	  ;
    }
}
class vcpeMessage_warning extends fcpeSimple {
    public function Render() {
	$s = $this->GetValue();
	return '<center>'
	  .'<table class="warning-message">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_WARN.'" alt=alert title="error message indicator" /></td>'
	  ."<td valign=middle>$s</td>"
	  .'</tr></table>'
	  .'</center>'
	  ;
    }
}
class vcpeMessage_success extends fcpeSimple {
    public function Render() {
	$s = $this->GetValue();
	return '<center>'
	  .'<table style="border: 1px solid green; background: #eeffee;">'
	  .'<tr><td valign=middle><img src="'.KWP_ICON_OKAY.'" alt=alert title="success message indicator" /></td>'
	  ."<td valign=middle>$s</td>"
	  .'</tr></table>'
	  .'</center>';
    }
}
abstract class vcTag_html extends fcTag_html_standard {
}
abstract class vcTag_body extends fcTag_body_login {
}
// This class is actually where most of the interesting stuff takes place.
abstract class vcPageContent extends fcPageContent {
    use ftContentMessages;

    // ++ EVENTS ++ //
    
    // ACTION: add any elements that can be defined at construction time
    protected function OnCreateElements() {}	// <body> creates header and navbar

    // -- EVENTS -- //
    // ++ FRAMEWORK ++ //

    protected function GetDatabase() {
	return fcApp::Me()->GetDatabase();
    }
    
    // -- FRAMEWORK -- //
    // ++ INPUT-TO-OUTPUT STATES ++ //

    /*----
      PURPOSE: Adds a string to the main content.
	If the page is redirected before rendering, the content will be saved with the Session record
	and displayed after the redirect.
    */
    /* 2017-01-31 This is all in base class or ftContentMessages now
    protected function AddString($s) {
	$sSaved = $this->GetValue();
	$sSaved .= $s;
	$this->SetValue($sSaved);
    }
    protected function AddStatusMessage($sMsg,$sType) {
	$urlIcon = KWP_ICON_ALERT;
	$ht = '<center>'
	  ."<div class=message><table class='$sType-message'>"
	  ."<tr><td valign=middle><img src='$urlIcon' alt=alert title='$sType message indicator' /></td>"
	  ."<td valign=middle>$sMsg</td>"
	  .'</tr></table></div>'
	  .'</center>'
	  ;
	$this->AddString($ht);
    }
    public function AddErrorMessage($s) {
	$this->AddStatusMessage($s,'error');
    }
    public function AddWarningMessage($s) {
	$this->AddStatusMessage($s,'warning');
    }
    public function AddSuccessMessage($s) {
	$this->AddStatusMessage($s,'success');
	echo 'CONTENTS INSIDE '.$this->FigurePath().':ProcessInput():['.$this->GetValue().']<br>';
    }
*/    
    // -- INPUT-TO-OUTPUT STATES -- //
    // ++ NEW PAGE ELEMENTS ++ //
    
      //++classes++//

    protected function Class_forErrorMessage() {
	return 'vcpeMessage_error';
    }
    protected function Class_forWarningMessage() {
	return 'vcpeMessage_warning';
    }
    protected function Class_forSuccessMessage() {
	return 'vcpeMessage_success';
    }
    
      //--classes--//
    // -- INPUT-TO-OUTPUT STATES -- //

}
abstract class vcPageHeader extends fcContentHeader {

    // ++ INPUT-TO-OUTPUT STATES ++ //

    private $sContext;
    public function SetContextString($s) {
	$this->sContext = $s;
    }
    protected function GetContextString() {
	return $this->sContext;
    }
    
    // -- INPUT-TO-OUTPUT STATES -- //
}
/*::::
  CLASS: vcPage
  PURPOSE: defines basic VBZ page structure
    This includes things to all pages across the site.
    Specifies all the bits that we'll want to have, but doesn't fill them in
    The only content generated is for error messages (exception handling).
*/
abstract class vcPage extends fcPage_login {
    
    // ++ INTERNAL VALUES ++ //

    protected function GetStyleFolder() {
	return KWP_STYLE_SHEETS;
    }
    /* 2017-01-14 This is if you want the browser title to be prefixed by the site name.
      I think in the long run, this should be a user-preference; some users may end up
      with very narrow tabs, so a clue about what page they're on may be more important
      than having text for the site name.

      I'm defaulting to not showing site-name text because the site's favicon seems to me
      like enough of a clue about what site the page is on, so start right away with the page title
      so as to get as much of that in as possible.

    // PUBLIC so content class can use it
    public function SetBrowserTitle($s) {
	parent::SetBrowserTitle(KS_SITE_SHORT.': '.$s);
    } */
    
    // -- INTERNAL VALUES -- //
    // ++ INPUT-TO-OUTPUT STATES ++ //

    // TODO: This could use a better name. Also, does this go above or below? Document.
    public function SetContentTitleContext($s) {
	$this->GetElement_PageHeader()->SetContextString($s);
    }
    
    // -- INPUT-TO-OUTPUT STATES -- //
    // ++ NEW PAGE ELEMENTS ++ //
    
      //++objects++//
    
    public function GetElement_PageHeader() {
	return $this->GetTagNode_body()->GetElement_PageHeader();
    }
    // PUBLIC so other elements can write to the page content
    public function GetElement_PageContent() {
	return $this->GetTagNode_body()->GetElement_PageContent();
    }
    public function GetElement_HeaderMenu() {
	return $this->GetElement_PageHeader()->GetElement_Menu();
    }

      //--objects--//
    // -- NEW PAGE ELEMENTS -- //
    // ++ FRAMEWORK ++ //

      //++carts++//

    /*----
      RETURNS: the current cart record, if usable; otherwise NULL.
    */
    public function CartRecord_current_orNull() {
	$rcSess = $this->GetSessionRecord();
	return $rcSess->CartRecord_Current();
    }
    /*----
      RETURNS: A cart record. If the current one is not usable,
	then a new one is created.
    */
    public function CartRecord_required() {
	throw new exception('2016-11-01 Does anyone actually call this? If so, rename it to CartRecord_orNew().');
	$rcSess = $this->GetSessionRecord();
	return $rcSess->CartRecord_required();
    }
    protected function CartID() {
	throw new exception('Is anything still calling this? CartObj() is no longer defined, even...');
	$rcCart = $this->CartObj(FALSE);
	if (is_null($rcCart)) {
	    throw new exception('Page is trying to access cart ID with no cart loaded.');
	} else {
	    return $rcCart->GetKeyValue();
	}
    }
    public function HasCart() {
	return $this->GetSessionRecord()->HasCart();
    }
    
      //--carts--//

    // -- FRAMEWORK -- //
    // ++ KLUGEY OUTPUT ++ //

    // PUBLIC so figuring code can call it:
    public function RenderSectionHeader($sText,$nLevel=2) {
	return "<h$nLevel class='section-header'>$sText</h$nLevel>";
    }

    // -- KLUGEY OUTPUT -- //
    // ++ EMAIL ++ //

    /*----
      HISTORY:
	2011-03-31 added Page and Cookie to list of reported variables
      TODO: This should probably go in the App class
    */
    protected function DoEmailException(exception $e) {
	$msg = $e->getMessage();

	$arErr = array(
	  'descr'	=> $e->getMessage(),
	  'stack'	=> $e->getTraceAsString(),
	  'guest.addr'	=> $_SERVER['REMOTE_ADDR'],
	  'guest.agent'	=> $_SERVER['HTTP_USER_AGENT'],
	  'guest.ref'	=> fcArray::Nz($_SERVER,'HTTP_REFERER'),
	  'guest.page'	=> $_SERVER['REQUEST_URI'],
	  'guest.ckie'	=> fcArray::Nz($_SERVER,'HTTP_COOKIE'),
	  );

	$out = $this->Exception_Message_toEmail($arErr);	// generate the message to email
	if (count($_GET) > 0) {
	    $out .= "\n_GET:\n".print_r($_GET,TRUE);
	    $msg .= '<br>GET:<pre>'.print_r($_GET,TRUE).'</pre>';
	}
	if (count($_POST) > 0) {
	    $out .= "\n_POST:\n".print_r($_POST,TRUE);
	    $msg .= '<br>POST:<pre>'.print_r($_POST,TRUE).'</pre>';
	}
	if (count($_COOKIE) > 0) {
	    $out .= "\n_COOKIE:\n".print_r($_COOKIE,TRUE);
	    $msg .= '<br>COOKIE:<pre>'.print_r($_COOKIE,TRUE).'</pre>';
	}
	$subj = $this->Exception_Subject_toEmail($arErr);
	$ok = mail(KS_TEXT_EMAIL_ADDR_ERROR,$subj,$out);	// email the message

	echo $this->Exception_Message_toShow($msg);		// display something for the guest

	throw $e;

	// FUTURE: log the error and whether the email was successful
    }
/* 2016-03-28 This seems to effectively duplicate the parent method.
    protected function Exception_Subject_toEmail(array $arErr) {
	return 'error in VBZ from IP '.$arErr['guest.addr'];
    }//*/
/* 2016-03-28 This seems to duplicate the parent method exactly.
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
    }//*/
    
    // -- EMAIL -- //

}
