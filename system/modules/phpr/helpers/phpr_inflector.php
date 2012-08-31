<?php

class Phpr_Inflector
{
    /**
     * Pluralizes English nouns.
     * @param string $word English noun to pluralize
     * @return string Plural noun
     */
    public static function pluralize($word)
    {
        $plural = array(
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)ix|ex$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(buffal|tomat)o$/i' => '\1oes',
            '/(bu)s$/i' => '\1ses',
            '/(alias|status)/i'=> '\1es',
            '/(octop|vir)us$/i'=> '\1i',
            '/(ax|test)is$/i'=> '\1es',
            '/s$/i'=> 's',
            '/$/'=> 's'
        );
        
        $uncountable = array(
            'equipment', 
            'information', 
            'rice', 
            'money', 
            'species', 
            'series', 
            'fish', 
            'sheep'
        );

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves'
        );

        $lowercased_word = strtolower($word);

        foreach ($uncountable as $_uncountable)
        {
            if (substr($lowercased_word,(-1*strlen($_uncountable))) == $_uncountable)
                return $word;
        }

        foreach ($irregular as $_plural=> $_singular)
        {
            if (preg_match('/('.$_plural.')$/i', $word, $arr)) 
                return preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $word);
        }

        foreach ($plural as $rule => $replacement) 
        {
            if (preg_match($rule, $word)) 
                return preg_replace($rule, $replacement, $word);
        }

        return false;
    }

    /**
     * Singularizes English nouns.
     * @param string $word English noun to singularize
     * @return string Singular noun.
     */
    function singularize($word)
    {
        $singular = array (
        '/(quiz)zes$/i' => '\\1',
        '/(matr)ices$/i' => '\\1ix',
        '/(vert|ind)ices$/i' => '\\1ex',
        '/^(ox)en/i' => '\\1',
        '/(alias|status)es$/i' => '\\1',
        '/([octop|vir])i$/i' => '\\1us',
        '/(cris|ax|test)es$/i' => '\\1is',
        '/(shoe)s$/i' => '\\1',
        '/(o)es$/i' => '\\1',
        '/(bus)es$/i' => '\\1',
        '/([m|l])ice$/i' => '\\1ouse',
        '/(x|ch|ss|sh)es$/i' => '\\1',
        '/(m)ovies$/i' => '\\1ovie',
        '/(s)eries$/i' => '\\1eries',
        '/([^aeiouy]|qu)ies$/i' => '\\1y',
        '/([lr])ves$/i' => '\\1f',
        '/(tive)s$/i' => '\\1',
        '/(hive)s$/i' => '\\1',
        '/([^f])ves$/i' => '\\1fe',
        '/(^analy)ses$/i' => '\\1sis',
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\\1\\2sis',
        '/([ti])a$/i' => '\\1um',
        '/(n)ews$/i' => '\\1ews',
        '/s$/i' => '',
        );
       

        $uncountable = array(
            'equipment', 
            'information', 
            'rice', 
            'money', 
            'species', 
            'series', 
            'fish', 
            'sheep',
            'sms'
        );

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves'
        );

        $lowercased_word = strtolower($word);
        foreach ($uncountable as $_uncountable)
        {
            if (substr($lowercased_word,(-1*strlen($_uncountable))) == $_uncountable)
                return $word;
        }

        foreach ($irregular as $_singular => $_plural)
        {
            if (preg_match('/('.$_plural.')$/i', $word, $arr)) 
                return preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $word);
        }

        foreach ($singular as $rule => $replacement) 
        {
            if (preg_match($rule, $word)) 
                return preg_replace($rule, $replacement, $word);
        }

        return $word;
    }
   
    /**
     * Get the plural form of a word if first parameter is greater than 1
     * @param integer $numer_of_records
     * @param string $word
     * @return string Pluralized string when number of items is greater than 1
     */
    function conditional_plural($numer_of_records, $word)
    {
        return $numer_of_records > 1 ? Phpr_Inflector::pluralize($word) : $word;
    }
    
    /**
     * Converts an underscored or CamelCase word into a English
     * sentence.
     * 
     * The titleize function converts text like "WelcomePage",
     * "welcome_page" or  "welcome page" to this "Welcome
     * Page".
     * If second parameter is set to 'first' it will only
     * capitalize the first character of the title.
     * 
     * @param string $word Word to format as tile
     * @param string $uppercase If set to 'first' it will only uppercase the
     * first character. Otherwise it will uppercase all
     * the words in the title.
     * @return string Text formatted as title
     */
    function titleize($word, $uppercase = '')
    {
        $uppercase = $uppercase == 'first' ? 'ucfirst' : 'ucwords';
        return $uppercase(Phpr_Inflector::humanize(Phpr_Inflector::underscore($word)));
    }

    /**
     * Returns given word as CamelCased
     * 
     * Converts a word like "send_email" to "SendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "WhoSOnline"
     * 
     * @param string $word Word to convert to camel case
     * @return string UpperCamelCasedWord
     */
    function camelize($word)
    {
        if (preg_match_all('/\/(.?)/',$word,$got))
        {
            foreach ($got[1] as $k=>$v)
            {
                $got[1][$k] = '::'.strtoupper($v);
            }
            $word = str_replace($got[0],$got[1],$word);
        }
        return str_replace(' ','',ucwords(preg_replace('/[^A-Z^a-z^0-9^:]+/',' ',$word)));
    }

    /**
     * Converts a word "into_it_s_underscored_version"
     * 
     * Convert any "CamelCased" or "ordinary Word" into an
     * "underscored_word".
     * 
     * This can be really useful for creating friendly URLs.
     * 
     * @param string $word Word to underscore
     * @return string Underscored word
     */
    function underscore($word)
    {
        return  strtolower(preg_replace('/[^A-Z^a-z^0-9^\/]+/','_',
        preg_replace('/([a-z\d])([A-Z])/','\1_\2',
        preg_replace('/([A-Z]+)([A-Z][a-z])/','\1_\2',
        preg_replace('/::/', '/',$word)))));
    }

    /**
     * Returns a human-readable string from $word
     * 
     * Returns a human-readable string from $word, by replacing
     * underscores with a space, and by upper-casing the initial
     * character by default.
     * 
     * If you need to uppercase all the words you just have to
     * pass 'all' as a second parameter.
     * 
     * @param string $word String to "humanize"
     * @param string $uppercase If set to 'all' it will uppercase all the words
     * instead of just the first one.
     * @return string Human-readable word
     */
    function humanize($word, $uppercase = '')
    {
        $uppercase = $uppercase == 'all' ? 'ucwords' : 'ucfirst';
        return $uppercase(str_replace('_',' ',preg_replace('/_id$/', '',$word)));
    }

    /**
     * Same as camelize but first char is lowercased
     * 
     * Converts a word like "send_email" to "sendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "whoSOnline"
     * 
     * @param string $word Word to lowerCamelCase
     * @return string Returns a lowerCamelCasedWord
     */
    function variablize($word)
    {
        $word = Phpr_Inflector::camelize($word);
        return strtolower($word[0]).substr($word,1);
    }

    /**
     * Converts a class name to its table name according to rails
     * naming conventions.
     * 
     * Converts "Person" to "people"
     * 
     * @param string $class_name Class name for getting related table_name.
     * @return string plural_table_name
     */
    function tableize($class_name)
    {
        return Phpr_Inflector::pluralize(Phpr_Inflector::underscore($class_name));
    }

    /**
     * Converts a table name to its class name according to rails
     * naming conventions.
     * 
     * Converts "people" to "Person"
     * 
     * @param string $table_name Table name for getting related ClassName.
     * @return string SingularClassName
     */
    function classify($table_name)
    {
        return Phpr_Inflector::camelize(Phpr_Inflector::singularize($table_name));
    }

    /**
     * Creates a URI slug from a given string.
     * 
     * Converts "Home Page" to "home-page"
     *
     * @param string $string
     * @param string $separator
     * @return string
     */     
    public static function slugify($string, $separator = '-') 
    {
        return strtolower(preg_replace(array('/[^-a-zA-Z0-9\s]/', '/[\s]/'), array('', $separator), $string));
    }

    /**
     * Converts number to its ordinal English form.
     * 
     * This method converts 13 to 13th, 2 to 2nd ...
     * 
     * @param integer $number Number to get its ordinal value
     * @return string Ordinal representation of given string.
     */
    function ordinalize($number)
    {
        if (in_array(($number % 100),range(11,13)))
        {
            return $number.'th';
        }else
        {
            switch (($number % 10)) 
            {
                case 1:
                return $number.'st';
                break;
                case 2:
                return $number.'nd';
                break;
                case 3:
                return $number.'rd';
                default:
                return $number.'th';
                break;
            }
        }
    }

    function demodulize($module_name)
    {
        $module_name = preg_replace('/^.*::/','',$module_name);
        return Phpr_Inflector::humanize(Phpr_Inflector::underscore($module_name));
    }

    function modulize($module_description)
    {
        return Phpr_Inflector::camelize(Phpr_Inflector::singularize($module_description));
    }

    /**
     * Transforms a string to its unaccented version. 
     * This might be useful for generating "friendly" URLs
     */
    function unaccent($text)
    {
        $map = array(
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 
            'Ð'=>'D', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'T', 'ß'=>'s', 'à'=>'a', 
            'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 
            'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'e', 
            'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 
            'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'t', 'ÿ'=>'y');
        return str_replace(array_keys($map), array_values($map), $text);
    }
    
    function urlize($text)
    {
        return trim(Phpr_Inflector::underscore(Phpr_Inflector::unaccent($text)),'_');
    }
    
    /**
     * Returns $class_name in underscored form, with "_id" tacked on at the end. 
     * This is for use in dealing with the database.
     * 
     * @param string $class_name
     * @return string
     */
    function foreign_key($class_name, $separate_class_name_and_id_with_underscore = true)
    {
        return Phpr_Inflector::underscore(Phpr_Inflector::demodulize($class_name)).($separate_class_name_and_id_with_underscore ? "_id" : "id");
    }
}
