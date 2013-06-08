<?php
/*
    
JSpp::parse($script_content, $path_to_script);

-- Include directives:
-----------------------------------------------------------------

@use library/jquery.js;

@include library/jquery.js;

@require library/jquery.js;

-- Variable directives:
-----------------------------------------------------------------

@define #FOO "Bar";    
console.log(#FOO);

-- Functional directives:
-----------------------------------------------------------------

@include library/jquery.js; // jquery will not minified
@minify yes;
@include library/otherlib.js; // otherlib will not be minified

*/

class JSpp 
{

    private $script_name;
    private $script_path;
    private $require = array();
    private static $instance = null;
    
    public function __construct($path = null) 
    {
        if ($path)
            $this->set_path($path);
    }

    public static function create($path = null)
    {
        if (self::$instance !== null)
        {
            if ($path)
                return self::$instance->set_path($path);            
            else
                return self::$instance;
        }

        return self::$instance = new self($path);
    }

    public static function parse($content, $path = null)
    {
        return self::create($path)->parse_internal($content);        
    }

    public function set_path($path)
    {
        $this->script_path = $path;
        return $this;
    }
    
    private function parse_internal($content) 
    {
        $macros = array();
        
        if (preg_match_all('@/\*(.*)\*/@msU', $content, $matches)) 
        {
            foreach ($matches[1] as $macro) 
            {
                if (preg_match_all('/@([^\\s]*)\\s+(.*);/', $macro, $matches2)) 
                {
                    $matches2[1] = array_reverse($matches2[1]);
                    $matches2[2] = array_reverse($matches2[2]);

                    foreach ($matches2[1] as $num => $macro_name) 
                    {
                        $method = 'directive_' . strtolower($macro_name);
                        if (method_exists($this, $method)) 
                        {
                            try 
                            {
                                $content = $this->$method($matches2[2][$num], $content);
                            }
                            catch (JSppException $ex)
                            {
                                return '/* '. $ex->getMessage() . ' */';
                            }
                        }
                    }
                }
            }
        }
        
        return $content;
    }
    
    private function directive_include($data, $context = "", $required = false, $used = false) 
    {
        $require = explode(',', $data);
        $result = "";
        
        foreach ($require as $script) 
        {
            $script = trim($script);
            $script_path = realpath($this->script_path.'/'.$script);
            if (!file_exists($script_path))
            {
                if ($required)
                    throw new JSppException('Require: Script does not exist: ' . $script);
                else 
                    continue;
            }
                
            if (in_array($script, $this->require)) 
            {
                // throw new JSppException('Require: Script already required: ' . $script);
                continue;
            }
                
            $this->require[] = $script;
            
            $content = file_get_contents($script_path);
            $content = "\n" . $this->parse($content) . "\n" ;
        
            $content = str_replace(
                array('__DATE__', '__FILE__'),
                array(date("D M j G:i:s T Y"), $script),
                $content
            );
                
            $result .= $content;
        }
        
        $result = $used ? ($context . $result) : ($result . $context);        
        return $result;
    }
    
    private function directive_require($data, $context = "") 
    {
        return $this->directive_include($data, $context, true);
    }
    
    private function directive_use($data, $context = "") 
    {
        return $this->directive_include($data, $context, true, true);
    }
    
    private function directive_define($data, $context = "") 
    {
        if (preg_match('@([^\\s]*)\\s+(.*)@', $data, $matches)) 
        {
            return str_replace($matches[1], $matches[2], $context);
        } 
        else 
            return $context;
    }
    
    private function directive_minify($data, $context = "") 
    {
        if (strtolower($data) == 'yes') 
            return JSMin::minify($context);
        else 
            return $context;
    }
}

class JSppException extends Exception { }