<?php
// http query values
define('KSQ_PAGE_CART','cart');	// shopping cart
define('KSQ_PAGE_SHIP','ship');	// shipping page
define('KSQ_PAGE_PAY','pay');	// payment page
define('KSQ_PAGE_CONF','conf');	// customer confirmation of order
define('KSQ_PAGE_RCPT','rcpt');	// order receipt
// -- optional pages
define('KSQ_PAGE_LOGIN','login');	// user login/profile page
define('KSQ_PAGE_USER','user');	// user login/profile page

// if no page specified, go to the shipping info page (first page after cart):
define('KSQ_PAGE_DEFAULT',KSQ_PAGE_SHIP);
