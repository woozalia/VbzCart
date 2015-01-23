<?php
/*
  HISTORY:
    2014-09-15 Split vbzCipher off from base.cart.php.
*/
/*%%%%
  PURPOSE: cipher class that works with vbz internals
*/
class vbzCipher extends Cipher_pubkey {
    public function encrypt($input) {
	throw new exception('The class vbzCipher is deprecated; is anyone still using it?');

	global $vgoDB;

	if (!$this->PubKey_isSet()) {
	    $fn = $vgoDB->VarsGlobal()->Val('public_key.fspec');
	    $fs =  KFP_KEYS.'/'.$fn;
	    $sKey = file_get_contents($fs);
	    $this->PubKey($sKey);
	}
	return parent::encrypt($input);
    }
}