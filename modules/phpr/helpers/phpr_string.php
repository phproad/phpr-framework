<?php

/**
 * PHPR String helper
 *
 * This class contains functions that may be useful for working with strings.
 */
class Phpr_String
{
	/**
	 * Returns a single or plural form of a word
	 * @param int $n Specifies a number
	 * @param string $word Specifies a word
	 * @param bool $add_number Determines whether the number should be added to the result before the word
	 * @return Returns string
	 */
	public static function word_form($n, $word, $add_number = false)
	{
		$interval_place = Phpr::$locale->get_string('phpr.dates', 'interval_place');		

		 if ($n < 1 || $n > 1)
			$word = Phpr_Inflector::pluralize($word);
		 if ($add_number && $interval_place == 0)
			$word = $n . ' ' . $word;
		 else if ($add_number && $interval_place == 1)
			$word = $word . ' ' . $n;

		return $word;
	}

	/**
	 * Returns A or AN depending on the word
	 * 
	 * @param string $word
	 * @return string a or an
	 */
	public static function indefinite_article($word)
	{
		// Lowercase version of the word
		$word_lower = strtolower($word);

		// An 'an' word (specific start of words that should be preceeded by 'an')
		$an_words = array('euler', 'heir', 'honest', 'hono');
		foreach ($an_words as $an_word)
		{
			if (substr($word_lower, 0, strlen($an_word)) == $an_word)
				return "an";
		}
		if (substr($word_lower, 0, 4) == "hour" and substr($word_lower, 0, 5) != "houri")
			return "an";

		// An 'an' letter (single letter word which should be preceeded by 'an')
		$an_letters = array('a', 'e', 'f', 'h', 'i', 'l', 'm', 'n', 'o', 'r', 's', 'x');
		if (strlen($word) == 1)
		{
			if (in_array($word_lower, $an_letters))
				return "an";
			else
				return "a";
		}

		// Capital words which should likely by preceeded by 'an'
		if (preg_match('/(?!FJO|[HLMNS]Y.|RY[EO]|SQU|(F[LR]?|[HL]|MN?|N|RH?|S[CHKLMNPTVW]?|X(YL)?)[AEIOU])[FHLMNRSX][A-Z]/', $word))
			return "an";

		// Special cases where a word that begins with a vowel should be preceeded by 'a'
		$regex_array = array('^e[uw]', '^onc?e\b', '^uni([^nmd]|mo)', '^u[bcfhjkqrst][aeiou]');
		foreach ($regex_array as $regex)
		{
			if (preg_match('/' . $regex . '/', $word_lower))
				return "a";
		}

		// Special capital words
		if (preg_match('/^U[NK][AIEO]/', $word))
			return "a";
		// Not sure what this does
		else if ($word == strtoupper($word))
		{
			$array = array('a', 'e', 'd', 'h', 'i', 'l', 'm', 'n', 'o', 'r', 's', 'x');
			if (in_array($word_lower[0], $array))
				return "an";
			else
				return "a";
		}

		// Basic method of words that begin with a vowel being preceeded by 'an'
		$vowels = array('a', 'e', 'i', 'o', 'u');
		if (in_array($word_lower[0], $vowels))
			return "an";

		// Instances where y follwed by specific letters is preceeded by 'an'
		if (preg_match('/^y(b[lor]|cl[ea]|fere|gg|p[ios]|rou|tt)/', $word_lower))
			return "an";

		// Default to 'a'
		return "a";
	}

	/**
	 * Removes slash from the beginning of a specified string
	 * and adds a slash to the end of the string.
	 * @param string $str Specifies a string to process
	 * @return string
	 */
	public static function normalize_uri($str)
	{
		if (substr($str, 0, 1) == '/')
			$str = substr($str, 1);

		if (substr($str, -1) != '/')
			$str .= '/';

		if ($str == '/')
			return null;

		return $str;
	}

	/**
	 * Puts a dot to the end of a string if last character is not a punctuation symbol.
	 */
	public static function finalize($str)
	{
		$str = trim($str);
		$lastChar = substr($str, -1);

		$punctuation = array(',', '.', ';', '!', '?');

		if (!in_array($lastChar, $punctuation))
			$str .= '.';

		return $str;
	}

	/**
	 * Returns JavaScript-safe string
	 */
	public static function js_encode($str)
	{
		return str_replace('"', '\"', $str);
	}

	/**
	 * 	Make a string's first character uppercase
	 */
	public static function ucfirst($str)
	{
		$str = trim($str);
		return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
	}

	/**
	 * Splits a string to words and phrases and returns an array
	 * @param string $query A string to split
	 * @return array
	 */
	public static function split_to_words($query)
	{
		$query = trim($query);

		$matches = array();
		$phrases = array();
		if (preg_match_all('/("[^"]+")/', $query, $matches))
			$phrases = $matches[0];

		foreach ($phrases as $phrase)
			$query = str_replace($phrase, '', $query);

		$result = array();
		foreach ($phrases as $phrase)
		{
			$phrase = trim(substr($phrase, 1, -1));
			if (strlen($phrase))
				$result[] = $phrase;
		}

		$words = explode(' ', $query);
		foreach ($words as $word)
		{
			$word = trim($word);
			if (strlen($word))
				$result[] = $word;
		}

		return $result;
	}

	// Returns array of 2 dimensions. eg: 1024x768 returns array('width'=>1024, 'height'=>768)
	public static function dimension_from_string($size)
	{
		if (strpos($size, 'x'))
		{
			$tmp = explode('x', $size);
			$width = $tmp[0];
			$height = $tmp[1];
		}
		else
			$width = $height = $size;

		return array('width'=>trim($width), 'height'=>trim($height));
	}

	public static function limit_and_highlight_words($string, $phrase, $limit = 100, $tag_open, $tag_close, $end_char = '&#8230;', $start_char = '&#8230;')
	{
		$sub_string = stristr($string, $phrase);

		if(!$sub_string)
		{
			  $sub_string = $string;
			  $start_char = '';
		}

		$sub_string = self::limit_words($sub_string, $limit, $end_char);

		return $start_char .= self::highlight_words($sub_string, $phrase, $tag_open, $tag_close);
	}

	public static function limit_words($string, $limit = 100, $end_char = '&#8230;', $is_html=true)
	{
		if (trim($string) == '')
			return $string;

		preg_match('/^\s*+(?:\S++\s*+){1,'.(int) $limit.'}/', $string, $matches);

		if (strlen($string) == strlen($matches[0]))
			$end_char = '';

		$str = rtrim($matches[0]).$end_char;
		return ($is_html) ? $str.Phpr_Html::get_orphan_tags($str) : $str;
	}

	public static function highlight_words($string, $phrase, $tag_open = '<strong>', $tag_close = '</strong>')
	{
		if ($string == '')
			return '';

		if ($phrase != '')
			return preg_replace('/('.preg_quote($phrase, '/').')/i', $tag_open."\\1".$tag_close, $string);

		return $string;
	}

	// Supports strings without any HTML
	public static function show_more_link($string, $limit = 500, $more_text = 'Show more')
	{
		if (strlen($string) < $limit)
			return $string;

		$string = preg_replace("/\s+/", ' ', str_replace(array("\r\n", "\r", "\n"), ' ', $string));

		if (strlen($string) <= $limit)
			return $string;

		// true = start, false = end
		$switch = true;
		$start = "";
		$end = "";

		foreach (explode(' ', trim($string)) as $val)
		{
			if ($switch)
			{
				$start .= $val . ' ';
				if (strlen($start) >= $limit)
				{
					$start = trim($start);
					$switch = false;
				}
			}
			else
				$end .= $val . ' ';
		}

		$return = array();
		$return[] = $start;
		$return[] = ' ';
		$return[] = '<a href="javascript:;" onclick="jQuery(this).hide().next().show()">' . $more_text . '&#8230;</a>';
		$return[] = '<span style="display:none">';
		$return[] = $end;
		$return[] = '</span>';
		return implode(PHP_EOL, $return);
	}	
}
