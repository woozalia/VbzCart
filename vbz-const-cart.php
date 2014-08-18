<?php
/*
  PURPOSE: constants for adding items to cart
  HISTORY:
    2013-11-10 created
  NAMING CONVENTIONS:
    KF = boolean flag
    KI = integer
    KHT = HTML string
    KS = plaintext string
    KSF = string used in a form
    - paths:
    KWP = web path (URL including protocol)
    KFP = file path
    KRP = relative path

*/

// ==========================================
// keys for retrieving cart form data & links
//

define('KSF_CART_BTN_ADD_ITEMS','btnAddItems');
define('KSF_CART_BTN_RECALC'	,'btnRecalc');
define('KSF_CART_BTN_CKOUT'	,'btnCkOut');

define('KSF_CART_CHANGE','edit');
define('KSF_CART_EDIT_DEL_LINE','xline');
define('KSF_CART_EDIT_DEL_CART','xcart');
define('KSF_CART_EDIT_LINE_ID','line');
define('KSF_CART_BTN_FINISH','finish');
//define('KSF_CART_PFX_QTY','qty-');
define('KSF_CART_ITEM_ARRAY_NAME','item');
define('KSF_CART_ITEM_PFX',KSF_CART_ITEM_ARRAY_NAME.'[');
define('KSF_CART_ITEM_SFX',']');

define('KHT_CART_MSG','Please <a href="http://wiki.vbz.net/Contact">let me know</a> if you have any questions.');
define('KHT_CART_FTR',
  "\n<tr><td colspan=9>"
  ."\n<input type=submit name=".KSF_CART_BTN_RECALC.' value="Recalculate">'
  ."\n<input type=submit name=".KSF_CART_BTN_CKOUT.' value="Check Out >>>" class=btn-next>'
  ."\n</td></tr>\n</table>\n</td></tr>"
  ."\n<tr><td colspan=9>"
  ."\n<hr>".KHT_CART_MSG
  ."\n</td></tr></table>"
  ."\n</td></tr></table>"
  ."\n</form>"
  ."\n</center>"
  ."\n<!-- END CART -->"
  );
