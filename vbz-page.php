<?php
/*
  FILE: vbz-page.php
  PURPOSE: VbzCart page-rendering classes
  HISTORY:
    2012-05-08 split off from store.php
    2012-05-13 removed clsPageOutput (renamed clsPageOutput_WHO_USES_THIS some time ago)
    2013-09-16 clsVbzSkin_Standard renamed clsVbzPage_Standard
    2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
*/
/* ===================
  CLASS: clsVbzPage
  PURPOSE: defines basic VBZ page structure
    This includes things to all pages across the site.
    Specifies all the bits that we'll want to have, but doesn't fill them in
    The only content is for error messages (exception handling).
*/
abstract class clsVbzPage extends clsPageLogin {
    private $oSkin;

    // ++ SETUP ++ //
    
    public function __construct() {
	parent::__construct();
	$this->oSkin = NULL;
    }

    // -- SETUP -- //
    // ++ UTILITIES ++ //
    
    /*----
      RETURNS: The rest of the URI after KFP_PAGE_BASE
      REQUIRES: KFP_PAGE_BASE must be set to the base URL for the expected request (e.g. '/cat/')
      REASON: $SERVER[PATH_INFO] is often unavailable; $SERVER[REQUEST_URI] is more reliable,
	  but needs a little processing.
	This function can be gradually foolproofed as more cases are encountered.
	See getPathInfo in https://doc.wikimedia.org/mediawiki-core/master/php/WebRequest_8php_source.html
    */
    static protected function GetPathInfo() {
	$uriReq = $_SERVER['REQUEST_URI'];
	$idxBase = strpos($uriReq,KFP_PAGE_BASE);
	if ($idxBase === FALSE) {
	    throw new exception("Configuration needed: URI [$uriReq] does not include KFP_PAGE_BASE [".KFP_PAGE_BASE.'].');
	}
	$urlPath = substr($uriReq,$idxBase+strlen(KFP_PAGE_BASE));
	return $urlPath;
    }
    
    // -- UTILITIES -- //
    // ++ NEW METHODS ++ //

    abstract protected function NewSkin();

    /*----
      2013-11-14 I'm not actually sure this is a good way to do things, but I'm not going
	to take the time now to untangle it.
    */
    public function Doc(clsRTDoc $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oDoc = $iObj;
	}
	return $this->oDoc;
    }
    /*----
      INPUT:
	$iRequire:
	  TRUE = make sure there is a cart (create if there isn't one already)
	  FALSE = just return the existing cart or NULL if there isn't one
    */
    public function CartObj($iRequire) {
	throw new exception('CartObj() is deprecated.');
    }
    protected function SessionRecord() {
	return $this->App()->Session();
    }

    // TODO: The CartRecord_* methods are still confusingly named. Figure out better names and rename them.

    /*----
      RETURNS: the current cart record, if usable; otherwise NULL.
    */
    public function CartRecord_current_orNull() {
	$rcSess = $this->SessionRecord();
	return $rcSess->CartRecord_Current();
    }
    /*----
      RETURNS: the current cart record, if usable.
	If not usable, throws an exception.
    */
    protected function CartRecord_current_orError() {
	$rcCart = $this->CartRecord_current_orNull();
	if (is_null($rcCart)) {
	    throw new exception('A current cart was expected but not found.');
	}
	return $rcCart;
    }
    /*----
      RETURNS: A cart record. If the current one is not usable,
	then a new one is created.
    */
    public function CartRecord_required() {
	$rcSess = $this->App()->Session();
	return $rcSess->CartRecord_required();
    }

    // END TODO

    protected function CartID() {
	$rcCart = $this->CartObj(FALSE);
	if (is_null($rcCart)) {
	    throw new exception('Page is trying to access cart ID with no cart loaded.');
	} else {
	    return $rcCart->KeyValue();
	}
    }
    public function HasCart() {
	return $this->App()->Session()->HasCart();
    }

    /*----
      ACTION: Override the default behavior of asking the App
	object for a skin, because different page types
	use different skin classes.
      NOTE: This is probably something that should be changed.
	There should at least be a single base Skin type used
	by all pages, perhaps with page-specific subclasses...
	but I'm not sure how to avoid ending up with the Page
	type calling the shots again.
    */
    public function Skin() {
	if (is_null($this->oSkin)) {
	    $this->oSkin = $this->NewSkin();
	}
	return $this->oSkin;
    }

    // --SECTION: new methods
    // ++SECTION: redefinitions

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
    protected function SessObj() {
	return $this->App()->Session();
	//return $this->objSess;
    }

// ABSTRACT section //
    /*-----
      ACTION: render HTML header (no directly visible content)
    */
//    protected abstract function RenderHtmlHdr();
}
