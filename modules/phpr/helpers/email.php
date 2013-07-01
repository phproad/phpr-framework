<?php

/*
 * Email helper
 */

class Phpr_Email
{
	// Encodes an email address using JavaScript
	// 
	public static function mailto_encode($email, $title = '', $params = '')
	{
		$title = (string)$title;

		if ($title == "")
			$title = $email;

		for ($i = 0; $i < 16; $i++)
			$x[] = substr('<a href="mailto:', $i, 1);

		for ($i = 0; $i < strlen($email); $i++)
			$x[] = "|".ord(substr($email, $i, 1));

		$x[] = '"';

		if ($params != '')
		{
			if (is_array($params))
			{
				foreach ($params as $key => $val)
				{
					$x[] =  ' '.$key.'="';

					for ($i = 0; $i < strlen($val); $i++)
						$x[] = "|".ord(substr($val, $i, 1));

					$x[] = '"';
				}
			}
			else
			{
				for ($i = 0; $i < strlen($params); $i++)
					$x[] = substr($params, $i, 1);
			}
		}

		$x[] = '>';

		$tmp = array();
		for ($i = 0; $i < strlen($title); $i++)
		{
			$ord = ord($title[$i]);

			if ($ord < 128)
				$x[] = "|".$ord;
			else
			{
				if (count($tmp) == 0)
					$count = ($ord < 224) ? 2 : 3;

				$tmp[] = $ord;
				if (count($tmp) == $count)
				{
					$number = ($count == 3)
						? (($tmp['0'] % 16) * 4096) + (($tmp['1'] % 64) * 64) + ($tmp['2'] % 64)
						: (($tmp['0'] % 32) * 64) + ($tmp['1'] % 64);
					$x[] = "|".$number;
					$count = 1;
					$tmp = array();
				}
			}
		}

		$x[] = '<'; $x[] = '/'; $x[] = 'a'; $x[] = '>';

		$x = array_reverse($x);

		$str = array();
		$str[] = '<script type="text/javascript">';
		$str[] = "//<![CDATA[".PHP_EOL;
		$str[] = "var x = [];";

		$i = 0;
		foreach ($x as $val)
			$str[] = "x[".$i++."]='".$val."';";

		$str[] = "for (var i = x.length-1; i >= 0; i=i-1)";
		$str[] = "(x[i].substring(0, 1) == '|')";
		$str[] = '?document.write("&#"+unescape(x[i].substring(1))+";")';
		$str[] = ":document.write(unescape(x[i]));";
		$str[] = PHP_EOL.'//]]>';
		$str[] = PHP_EOL."</script>";

		return implode('', $str);
	}

}