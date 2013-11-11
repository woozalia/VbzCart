<?php
/*
  PURPOSE: constants for adding items to cart
  HISTORY:
    2013-11-10 created
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
  ."\n</form>\n</tr>\n</td></tr>"	// where was the form opened?
  ."\n<tr><td colspan=9>"
  ."\n<hr>".KHT_CART_MSG
  ."\n</td></tr></table>\n<td></tr></table>"
  );
