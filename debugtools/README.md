About:
======
Tools to help with everyday websitedevelopment. If you often put echo, var_dump or print_r on your page, this might support you.
The unique strength are the ability to display objects nicely (multiple objects are omitted, functions, properties are all displayed)
Also it can handle big data through omitting deeper levels.

The core prettyprinter is based on Yii VarDumper but with some enhancements

Installation:
=============
Upload this folder and include the debug.php
If you want to see the debugs only yourself, you can set the ip or a user-agent inside the top of the debug.php

Usage:
======
A list of all available commands

enableDebug()
    returns true if debugging is enabled (is modified by ip/user-agent)
callHistory()
    returns an array of all previous files similar to debug_backtrace but better readable
whereCalled($level=1)
    returns a line from the $level previous file
dump($data, $prec=3, $return = false)
    pretty prints $data with a depth of $prec - if $return is true, it will just return a string
    is modified by enableDebug
    and prints also whereCalled
diedump($data, $prec=3)
    similar to dump, but also calls die and removes all output-buffers
startswith($needle, $haystack)
    returns true when string haystack begins with needle
DebugVarDumper::dump($var, $depth=10, $highlight=false)
    prettyprints $var with depth of $depth and if $hightlight is true, will use highlighting



Example:
========
Create a test.php:
<?php
include "debug.php";
$a = array(
    '1'=>array(
        'abc'=>true
    ),
);
diedump($a);
?>

