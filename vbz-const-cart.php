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

define('KHT_CART_MSG','Please <a href="'.KWP_HELP_CONTACT.'">let us know</a> if you have any questions.');
// cart footer when it's editable
define('KHT_CART_BTNS_ROW',
  "\n<tr><td colspan=9>"
  ."\n<input type=submit name=".KSF_CART_BTN_RECALC.' value="Recalculate">'
  ."\n<input type=submit name=".KSF_CART_BTN_CKOUT.' value="Check Out >>>" class=btn-next>'
  ."\n</td></tr>"
  );
define('KHT_CART_FTR',
  "\n</table>\n</td></tr>"
  ."\n<tr><td colspan=9>"
  ."\n<hr>".KHT_CART_MSG
  ."\n</td></tr></table>"
  ."\n</td></tr></table>"
  ."\n</form>"
//  ."\n</center>"
  ."\n<!-- END CART -->"
  );
// order receipt
define('KF_RCPT_TIMESTAMP','F j, Y - g:i a');	// January 1, 2000 - 3:00 pm
define('KHT_RCPT_TPLT', <<<__END__
\{{\}}\<center><font size=5>{{doc.title}}</font>
<br><a href="{{url.shop}}">VBZ.NET</a>
<br>{{timestamp}}
</center>
<table width=100%>
  <tr><td align=center colspan=2 class=order-conf-layout>
    <h3>ITEMS ORDERED:</h3>
    <table><tr><td colspan=2>{{cart.detail}}</td></tr></table>
  </td></tr>
  <tr>
    <td valign=top class=order-conf-layout>
      <h3>SHIP TO:</h3>
      <b>{{ship.name}}</b><br>
      {{ship.addr}}
    </td>
    <td valign=top class=order-conf-layout>
    <h3>PAYMENT:</h3>
    <b>{{pay.name}}</b><br>
    {{pay.spec}}
    </td>
  </tr>
</table>
<span class=footer-stats>Order ID: {{order.id}} - Cart ID: {{cart.id}} - Session ID: {{sess.id}}</span>
<hr>
<center>http://vbz.net - 122 Pinecrest Rd. - Durham, NC 27705 - {{email.short}}<br><br>
Please print or save this for your records.</center>
__END__
);
