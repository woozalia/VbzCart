<?php
/*
  PURPOSE: page classes for serving AJAX data
    This is what handles the BACK end of the AJAX transaction - receiving requests
      and returning results in AJAX format.
  ASSUMPTIONS: Only one user will be running maintenance functions at any given time.
    If this ever changes, we may need some way to prevent conflicting functions from
    being run at the same time.
  HISTORY:
    2015-12-01 started
    2015-12-13 process now continues to run after PHP exits
*/
define('EOF',"\x1A"); // should be 26 in hex = ^Z

class vcPageAJAX extends clsPageLogin {
    public function DoPage() {
	$this->ParseInput_Login();	// verify any claims of being logged in
	$this->HandleRequest();		// see what is being requested, and dispatch it 
    }
    /* 2015-12-11 probably won't use this for awhile, if ever
    function WebSocket($address,$port){
	$master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
	socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
	socket_bind($master, $address, $port)                    or die("socket_bind() failed");
	socket_listen($master,20)                                or die("socket_listen() failed");
	echo "Server Started : ".date('Y-m-d H:i:s')."\n";
	echo "Master socket  : ".$master."\n";
	echo "Listening on   : ".$address." port ".$port."\n\n";
	return $master;
    } */

    protected function HandleRequest() {
	if ($this->IsLoggedIn()) {
	    echo 'YES, we are logged in';
	} else {
	    echo 'NO, we are not logged in';
	}
	echo "<br>\n";
    }
    protected function MenuPainter_new() { throw new exception('Who calls this?'); }
    protected function ParseInput() { throw new exception('Who calls this?'); }
    protected function HandleInput() { throw new exception('Who calls this?'); }
    protected function PreSkinBuild() { throw new exception('Who calls this?'); }
    protected function PostSkinBuild() { throw new exception('Who calls this?'); }
    protected function DefaultSkinObject() { echo '<pre>'; throw new exception('This is a headless page type, and should not be loading a Skin.'); }
    protected function MenuHome_new() { throw new exception('This requirement probably needs to go away.'); }
}

/*%%%%
  PURPOSE: AJAX backend for running a process and serving status updates on request
*/
class vcPage_AJAX_ProcessStatus extends vcPageAJAX {

    // ACTION: Parse the request
    protected function HandleRequest() {
	$this->HandleInput();
    }

    protected function ReqDebug() {
	return fcArray::Exists($_GET,'debug');
    }
    protected function ReqAction() {
	return fcArray::Nz($_GET,'do');
    }/*
    protected function ReqAction_isStart() {
	return ($this->ReqAction() == 'start');
    }
    protected function ReqAction_isClear() {
	return ($this->ReqAction() == 'clear');
    }*/
    
    protected function HandleInput() {
	// temporary fixed spec for debugging
	$fpTmp = sys_get_temp_dir();	// TODO: might want to strip off any trailing slash
	$this->ProcessOutputSpec($fpTmp.'/vbz.status.tmp');
	$this->ProcessCacheSpec($fpTmp.'/vbz.status.cache');
	
	$sDo = $this->ReqAction();
	
	$fnScript = NULL;
	switch ($sDo) {
	  case 'test':		// AJAX test script
	    $fnScript = 'ajaxtest';
	    break;
	  case 'buildcat':	// build catalog from sources
	    $fnScript = 'build-cat';
	    break;
	  case '@clear':
	    $this->ClearOutput();
	    $out = 'Deleted output file.';
	  default:
	    $out = $this->CheckProcess();
	}
	
	if (!is_null($fnScript)) {
	    $this->ProcessCommand("php /var/www/data/php/VbzCart/maint/$fnScript.php");
	    $out = $this->StartProcess();
	}
	
	echo $out;
    }
    
    protected function CheckProcess() {
	$arOut = $this->ProcessReport();
	$sOut = json_encode($arOut);
	//$out = 'Process output:<br>'.$this->ProcessOutput();
	return $sOut;
    }

    private $fsProc;
    protected function ProcessCommand($fs=NULL) {
	if (!is_null($fs)) {
	    $this->fsProc = $fs;
	}
	return $this->fsProc;
    }
    private $fsOut;
    protected function ProcessOutputSpec($fs=NULL) {
	if (!is_null($fs)) {
	    $this->fsOut = $fs;
	}
	return $this->fsOut;
    }
    private $fsCache;
    protected function ProcessCacheSpec($fs=NULL) {
	if (!is_null($fs)) {
	    $this->fsCache = $fs;
	}
	return $this->fsCache;
    }
    // RETURNS: complete contents of process output, at the current time
    protected function ProcessOutput() {
	$fs = $this->ProcessOutputSpec();
	if (file_exists($fs)) {
	    $fh = fopen($fs, "r");
	    if (is_resource($fh)) {
		$sStat = fread($fh,filesize($fs));
		return $sStat;
	    }
	}
	return NULL;
    }
    protected function ClearOutput() {
	$fs = $this->ProcessOutputSpec();
	if (file_exists($fs)) {
	    unlink($fs);
	}
	$fs = $this->ProcessCacheSpec();
	if (file_exists($fs)) {
	    unlink($fs);
	}
    }
    // RETURNS: complete *previous* contents of process output, for comparison
    protected function ProcessCache($sContent=NULL) {
	$fs = $this->ProcessCacheSpec();
	if (is_null($sContent)) {
	    if (file_exists($fs)) {
		$fh = fopen($fs, "r");
		$sStat = fread($fh,1024);	// TODO: we may need to be able to deal with longer outputs
	    } else {
		$sStat = NULL;
	    }
	} else {
	    $fh = fopen($fs, "w");
	    $sStat = fwrite($fh,$sContent);	// TODO: maybe indicate an error if this fails
	}
	return $sStat;
    }
    protected function ProcessReport() {
	$sLatest = $this->ProcessOutput();
	$sCached = $this->ProcessCache();

	if ($this->ReqDebug()) {
	    echo "<br>OUTPUT:<br>$sLatest<br>[/OUTPUT]<br>";
	}
	
	// see if latest starts with cached
	if (empty($sCached)) {
	    $idx = FALSE;
	} else {
	    $idx = strpos($sLatest,$sCached);
	}
	if ($idx === FALSE) {
	    // no similarity -- current content is completely new
	    $sType = 'new';
	    $sText = $sLatest;
	} elseif ($idx === 0) {
	    if ($sLatest == $sCached) {
		// no changes
		$sType = 'same';
		$sText = NULL;
	    } else {
		// latest just adds on a bit to what's in the cache
		$sType = 'after';
		$sText = substr($sLatest,strlen($sCached));	// get the new part
	    }
	} else {
	    // something was inserted at the beginning
	    $sType = 'before';
	    $sText = substr($sLatest,0,strlen($sLatest)-strlen($sCached));	// TODO: test this
	}
	$this->ProcessCache($sLatest);	// update the cache
	
	$arOut = array(
	  'type'	=> $sType,
	  'text'	=> $sText
	  );
	  
	// one more thing: check for EOF
	if (strpos($sLatest,EOF)) {
	  $arOut['end'] = TRUE;
	}
	return $arOut;
    }
    protected function StartProcess() {
	$fsProc = $this->ProcessCommand();
	$out = 'Executing command ['.$fsProc.']: ';
	$fsTmp = $this->ProcessOutputSpec();

	$arIO = array(
	  0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
	  1 => array("file", $fsTmp, "w"),  // stdout is a file that the child will write to
	  2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);
	
	$arPipe = array();
	$ph = proc_open($fsProc, $arIO, $arPipe);
	if ($ph === FALSE) {
	    $out .= 'could not start process.';
	} else {
	    $out .= 'process started...';
	}
	$arOut['status'] = $out;
	$jsOut = json_encode($arOut);
	return $jsOut;
	//return '{}';
    }
}