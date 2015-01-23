<?php
/*
 NAME: cryptest.php
 PURPOSE: for now, debugging use of crypto routines.
  Later, may be a test suite to confirm that the necessary crypto functions are available on the current platform.
 HISTORY:
  2015-01-02 started
*/

$sKeyPrivate = <<<__END__
-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDKLCvZuiPJaz2I3EtfaRJz032/QMva9KsHh2Q5z74ij2J1DTIW
L7vqX8rUE6l96+kC1Xxomfd3tw0Zzk+YTml+yU1W0WBKW/RTiHmiGlY1Oj86crup
rKiJh5pnuG47J+6JY8r607zwnPqt+lL2SbzstD48xJHu9xAzN6/Fb7vl5wIDAQAB
AoGBALCrhO5RATDka/OLPrpzoVJiQIK+5uXB5StBH059wdOFpS5Qh7JnqDkZ2K8X
N4f4fbiiQoNN+Lk+103zwg6AhyJaqUB9pGum9joL7m3CvGgP+yDmZiQ0INrhncvu
DMA2luTFD/zUJTEdJKB02IJ1LbBzpADFJRSpa7Lc9WHs9+ZxAkEA/AO6w+ks7lKi
+kTaXYMjAvGSNUGBB2G3KKLbvsleLmm8I7hY+ogzaIngl24aTEufDq2bo5M4+gxW
KST6GbXIZQJBAM1eqIv/3/xTQOtudpL3Vm1lBc820//cricI3aTMoKrr23TQ57Ag
SZS3wwAQ0iqNwJxPVoS/1gJy9gF2MTvbYlsCQGgr2n202v/AZOHyqBjTZhuHY5pj
80Pr3lwLxa29axLgXgad4xncRvPFWnL97hzvfVYB6T3aU0j45HypbkBGZgkCQQDG
UBLn5fUv3mEBN1EPCAKQbo4Wk7ZSC5KsJPaK7gJ0Kn9npVclj3geQPjvdk7MQsfs
6Pv+ApUxFjwSG0TdrTKjAkBOgQLDQWjsVdk0KjFTAD4Em/OH4XR5DY3IJfvWv2Tq
E5Bw8iI4i84LKIfZg5/rROg+yb4IR6soyjeltR7kZVEW
-----END RSA PRIVATE KEY-----
__END__;

$sKeyPublic = <<<__END__
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDKLCvZuiPJaz2I3EtfaRJz032/
QMva9KsHh2Q5z74ij2J1DTIWL7vqX8rUE6l96+kC1Xxomfd3tw0Zzk+YTml+yU1W
0WBKW/RTiHmiGlY1Oj86cruprKiJh5pnuG47J+6JY8r607zwnPqt+lL2SbzstD48
xJHu9xAzN6/Fb7vl5wIDAQAB
-----END PUBLIC KEY-----
__END__;

$sTestInput = 'This is a test sentence.';

require_once '/var/www/vbz/local.php';				// basic library paths
require_once(KFP_LIB_VBZ.'/config-libs.php');

// MAIN THREAD

openssl_public_encrypt($sTestInput, $sEncrypted, $sKeyPublic);
openssl_private_decrypt($sEncrypted, $sDecrypted, $sKeyPrivate);

echo "SIMPLE TEST: $sDecrypted\n";

$oCrypt = new Cipher_pubkey();
$oCrypt->PublicKey($sKeyPublic);
$sEncrypted = $oCrypt->encrypt($sTestInput);
$oCrypt->PrivateKey($sKeyPrivate);
$sDecrypted = $oCrypt->decrypt($sEncrypted);

echo "CLASS TEST: $sDecrypted\n";

$oDB = new clsVbzData(KS_DB_VBZCART);
$oDB->Open();
$fsKey = $oDB->PublicKey_FileSpec();
echo "Retrieving public key from [$fsKey].\n";
$sKeyPublic = $oDB->PublicKey_string();
$oCrypt->PublicKey($sKeyPublic);
$sEncrypted = $oCrypt->encrypt($sTestInput);
$sDecrypted = $oCrypt->decrypt($sEncrypted);

echo "PRESET TEST: $sDecrypted\n";