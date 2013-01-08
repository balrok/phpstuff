<?php
$GLOBALS['debug_address'] = ''; //92.229.38.142';
$GLOBALS['debug_useragent'] = ''; //92.229.38.142';

function enableDebug()
{
    if (isset($_SERVER['HTTP_USER_AGENT']) && $GLOBALS['debug_useragent'])
        return true;
    if ($GLOBALS['debug_address'] && isset($_SERVER['REMOTE_ADDR']))
        if ($_SERVER['REMOTE_ADDR'] != $GLOBALS['debug_address'])
            return false;
        if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
        return $_SERVER['HTTP_USER_AGENT'];
    return true;
}

/**
 * an easy to read backtrace (uses debug_backtrace())
 * @todo longest common string should only go until / or \
 */
function callHistory()
{
    $arr = debug_backtrace();
    // find out which is the basic path each files are sharing
    // this is done to keep readability
    $base = false;
    foreach($arr as $v)
    {
        if (!isset($v['file']) || !$v['file'])
            continue;
        if ($base === false)
            $base = $v['file'];
        while (!startswith($base, $v['file']))
            $base = substr($base, 0, -1);
    }

    $ret = array(array('basepath'=>$base));
    foreach($arr as $v)
        if (isset($v['file']) && $v['file'])
            $ret[] = array('file'=>substr($v['file'], strlen($base)), 'line'=>$v['line']);
    return $ret;
}

/**
 * returns a short line where this function gets called
 * level determines how many levels it should go up (should be 1 if used directly)
 */
function whereCalled($level = 1)
{
    $trace = debug_backtrace();
    $file   = (isset($trace[$level]['file']))?$trace[$level]['file']:'';
    $line   = (isset($trace[$level]['line']))?$trace[$level]['line']:'';
    $object = (isset($trace[$level]['object']))?$trace[$level]['object']:'no Object';
    if (is_object($object)) { $object = get_class($object); }
    return "<b>Called in: line $line of $object \n(in $file):</b><br/>";
}

/**
 * stops the program and dumps the variable content, also tries to make it visible by ending output-buffer and some html-tags
 * second parameter is optional and can be used to increase precision
 */
function diedump($data, $prec=3)
{
    if (!enableDebug())
        return;
    while (@ob_end_clean ())
        continue;
    // if diedump is called inside a hidden div or script tag it won't be visible
    echo '<!-- --></div></div></script></script></script></div>';
    die(dump($data, $prec));
}

/**
 * dumps the data in a nice and readable format
 * @param $prec precision how deep arrays/objects should be printed
 * @param $return if true it would only return a string, else output
 */
function dump($data, $prec=3, $return = false)
{
    if (!enableDebug())
        return;
    $ret = whereCalled(2);
    $ret .= DebugVarDumper::dumpAsString($data, $prec, true);
    if ($return)
        return $ret;
    echo $ret;
}

/**
 * helper function which will look if a string starts with another string
 * similar to the python version
 * example: if (startswith('Apple', 'Appletree')) ...
 */
function startswith($needle, $haystack)
{
    if (!$haystack) return false;
    if (!$needle) return true;
    return strpos($haystack, $needle) === 0;
}

/**
 * A Helper class from yii (CVarDumper) for nicely displaying variables
 * extended to be a bit more helpful (arrays are easily copyable)
 */
class DebugVarDumper
{
    private static $_objects;
    private static $_depth;

    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param integer $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     */
    public static function dump($var,$depth=10,$highlight=false)
    {
        echo self::dumpAsString($var,$depth,$highlight);
    }

    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param integer $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public static function dumpAsString($var,$depth=10,$highlight=false)
    {
        self::$_objects=array();
        self::$_depth=$depth;
        $output = self::dumpInternal($var,0);
        if($highlight)
        {
            $result = highlight_string("<?php\n".$output,true);
            $output=preg_replace('/&lt;\\?php<br \\/>/','',$result,1);
        }
        return $output;
    }

    /*
     * @param mixed $var variable to be dumped
     * @param integer $level depth level
     */
    private static function dumpInternal($var, $level)
    {
        $output = '';
        switch(gettype($var))
        {
            case 'boolean':
                $output.=$var?'true':'false';
                break;
            case 'integer':
                $output.="$var";
                break;
            case 'double':
                $output.="$var";
                break;
            case 'string':
                $output.="'".addslashes($var)."'";
                break;
            case 'resource':
                $output.='{resource}';
                break;
            case 'NULL':
                $output.="null";
                break;
            case 'unknown type':
                $output.='{unknown}';
                break;
            case 'array':
                if(self::$_depth<=$level)
                    $output.='array(...)';
                else if(empty($var))
                    $output.='array()';
                else
                {
                    $keys=array_keys($var);
                    $spaces=str_repeat(' ',$level*4);
                    $output .="array(";
                    foreach($keys as $key)
                    {
                        $key2=str_replace("'","\\'",$key);
                        $output .="\n".$spaces."    '$key2' => ";
                        $output .=self::dumpInternal($var[$key],$level+1);
                        $output .=',';
                    }
                    $output.="\n".$spaces.')';
                }
                break;
            case 'object':
                if(($id=array_search($var,self::$_objects,true))!==false)
                    $output .= get_class($var).'#'.($id+1).'(...)';
                else if(self::$_depth<=$level)
                    $output .= get_class($var).'(...)';
                else
                {
                    $id=array_push(self::$_objects,$var);
                    $spaces=str_repeat(' ',$level*4);

                    if (class_exists('ReflectionClass', false))
                    {
                        $reflClass = new ReflectionClass($var);
                        $output.='class '.$reflClass->getName().'#'.$id;
                        $output.= ' - '.$reflClass->getFileName();
                        if ($reflClass->getParentClass())
                            $output .= "\n".$spaces.'    extends '.$reflClass->getParentClass()->getName().' // - '.$reflClass->getParentClass()->getFileName();
                        foreach ($reflClass->getInterfaces() as $int)
                            $output .= "\n".$spaces.'    implements '.$int->getName().' // - '.$int->getFileName();
                        $output .= "\n".$spaces.'{';
                        $level += 1;
                        $spaces=str_repeat(' ',$level*4);
                        // output all constants
                        foreach ($reflClass->getConstants() as $key=>$value)
                        {
                            $output .= "\n".$spaces.$key.' = '.  self::dumpInternal($value,$level+1) . ';';
                        }

                        // output all properties:
                        foreach ($reflClass->getProperties() as $prop)
                        {
                            $type = array();
                            if ($prop->getModifiers() & $prop::IS_STATIC) $type[] = 'static';
                            if ($prop->getModifiers() & $prop::IS_PUBLIC) $type[] = 'public';
                            if ($prop->getModifiers() & $prop::IS_PROTECTED) $type[] = 'protected';
                            if ($prop->getModifiers() & $prop::IS_PRIVATE) $type[] = 'private';
                            $type = implode(' ', $type);
                            $prop->setAccessible(true); // else we can't access private/protected
                            $output .= "\n".$spaces.$type.' $'.$prop->getName().' = '. self::dumpInternal($prop->getValue($var),$level+1) . ';';
                            if ($prop->getDeclaringClass() != $reflClass)
                                $output .= '#'.$prop->getDeclaringClass()->getName();
                        }

                        // output all methods
                        foreach ($reflClass->getMethods() as $meth)
                        {
                            $type = array();
                            if ($meth->getModifiers() & $meth::IS_STATIC) $type[] = 'static';
                            if ($meth->getModifiers() & $meth::IS_PUBLIC) $type[] = 'public';
                            if ($meth->getModifiers() & $meth::IS_PROTECTED) $type[] = 'protected';
                            if ($meth->getModifiers() & $meth::IS_PRIVATE) $type[] = 'private';
                            if ($meth->getModifiers() & $meth::IS_ABSTRACT) $type[] = 'abstract';
                            if ($meth->getModifiers() & $meth::IS_FINAL) $type[] = 'final';
                            $type = implode(' ', $type);

                            $parameters = array();
                            foreach ($meth->getParameters() as $param)
                            {
                                $paramStr = '';
                                if ($param->getClass())
                                    $paramStr .= $param->getClass()->name;
                                if ($param->isPassedByReference())
                                    $paramStr .= '&';
                                $paramStr .= '$'.$param->getName();
                                if ($param->isOptional())
                                    $paramStr .= ' = '.self::dumpInternal($param->getDefaultValue(), $level+1);
                                $parameters[] = $paramStr;
                            }

                            $output .= "\n".$spaces.$type.' function '.$meth->getName().'('. implode(', ', $parameters).');';
                            if ($meth->getDeclaringClass() != $reflClass)
                                $output .= '#'.$meth->getDeclaringClass()->getName();
                        }

                        $level -= 1;
                        $spaces=str_repeat(' ',$level*4);
                        $output.="\n".$spaces.'}';
                        //diedump2($reflClass);
                    }
                    else
                    {
                        $className=get_class($var);
                        $members=(array)$var;
                        $output.="class $className#$id\n".$spaces.'(';
                        foreach($members as $key=>$value)
                        {
                            $tmp = explode("\0", trim($key));
                            $type = 'public';
                            $varName = $tmp[0];
                            if (isset($tmp[1]))
                            {
                                if ($tmp[0] == '*')
                                    $type = 'protected';
                                else
                                {
                                    $output.="\n".$spaces."    // in class ".$tmp[0];
                                    $type = 'private';
                                }
                                $varName = $tmp[1];
                            }

                            $output.="\n".$spaces."    ".$type." $".$varName;
                            if (!is_null($value))
                            {
                                $output.=" = ".self::dumpInternal($value,$level+1). ";";
                            }
                            else
                                $output.=";";

                        }
                        $output.="\n".$spaces.')';
                    }
                }
                break;
        }
        return $output;
    }
}