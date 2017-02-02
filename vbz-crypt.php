<?php
/*
  HISTORY:
    2014-09-15 Split vbzCipher off from base.cart.php.
    2016-07-31 Renamed from clsVbzCipher to vcCipher; removed deprecation throw.
      This class is needed in order to automatically pull up the public key for encryption.
*/
/*::::
  PURPOSE: cipher class that works with vbz internals
    * loads public key from a preset filespec
*/
class vcCipher extends Cipher_pubkey {
    private $hasPubKey;
    public function encrypt($input) {
	if (empty($this->hasPubKey)) {
	    
	    $fn = vcApp::Me()->SettingsTable()->GetPublicKeyFileSpec();
	    $fs =  KFP_KEYS.'/'.$fn;
	    $sKey = file_get_contents($fs);
	    $this->PublicKey($sKey);
	    $this->hasPubKey = TRUE;
	}
	return parent::encrypt($input);
    }
}