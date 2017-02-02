<?php
/*
PURPOSE: simple counter for testing dynamic update
*/
define('EOF',"\x1A"); // should be 26 in hex = ^Z

ignore_user_abort(true);	// don't exit when client disconnects
set_time_limit(0);
for ($idx=1; $idx<=10; $idx++) {
    echo $idx.'<br>';
    sleep(1);
}
echo "Done.\n".EOF;