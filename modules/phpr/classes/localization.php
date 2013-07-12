<?php namespace Phpr;

use Phpr;
use Phpr\DateTime;
use Phpr\SystemException;
use Phpr\ApplicationException;

/**
 * Phpr_Localization class assists in application lozalization.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$locale.
 * You may set user language programmatically: Phpr::$locale->set_locale("en_US"),
 * or in the configuration file: $CONFIG["LOCALE"] = "en_US". In the configuration file 
 * you may specify the "auto" value for the locale: $CONFIG["LOCALE"] = "auto". In
 * this case the language specified in the user browser configuration will be used.
 * If locale is not set the default value en_US will be used.
 */
class Localization 
{
	const default_locale_code = 'en_US';

	private $currency;
	private $locale_code;
	private $language_code;
	private $country_code;
	private $file_paths;
	private $directory_paths;
	private $definitions;
	private $pluralizations;

	private $dec_separator;
	private $container_separator;

	private $currency_is_loaded;
	private $intl_currency_symbol;
	private $local_currency_symbol;
	private $decimal_separator;
	private $decimal_digits;
	private $positive_sign;
	private $negative_sign;
	private $p_cs_precedes;
	private $p_sep_by_space;
	private $n_cs_precedes;
	private $n_sep_by_space;
	private $p_format;
	private $n_format;

	public function __construct() 
	{
		$this->containers = array();
		$this->currency_is_loaded = false;
		$this->file_paths = array();
	}
	
	public function determine_definition($container, $source, $placeholders = array(), $options = array()) 
	{
		$this->init_locale();
	
		$locale = $this->locale_code;
		$definition = null;
		
		// Load exact value
		if ($this->definition_exists($locale, $container, $source, $options['variation'])) 
		{
			$definition = $this->get_definition($locale, $container, $source, $options['variation']);
		}
		else 
		{
			// Fallback to a neutral culture
			if ($pos = strpos($this->locale_code, '_')) 
			{
				$language = substr($this->locale_code, 0, $pos);
				$locale = $language . '_xx';
				
				if ($this->definition_exists($locale, $container, $source, $options['variation'])) 
				{
					$definition = $this->get_definition($locale, $container, $source, $options['variation']);
				} 
				else 
				{
					$country = substr($this->locale_code, $pos+1);
					$locale = 'xx_' . strtolower($country);
					
					if ($this->definition_exists($locale, $container, $source, $options['variation'])) 
					{
						$definition = $this->get_definition($locale, $container, $source, $options['variation']);
					}
				}
			}

			// Failed so fallback to a default language
			if ($definition === null) 
			{
				$language = substr(self::default_locale_code, 0, $pos);
				$locale = $language . '_xx';
				
				if ($this->definition_exists($locale, $container, $source, $options['variation'])) 
				{
					$definition = $this->get_definition($locale, $container, $source, $options['variation']);
				} 
				else 
				{
					$country = substr(self::default_locale_code, $pos+1);
					$locale = 'xx_' . strtolower($country);
					
					if ($this->definition_exists($locale, $container, $source, $options['variation'])) 
					{
						$definition = $this->get_definition($locale, $container, $source, $options['variation']);
					}
				}
			}
		}
		
		if ($definition === null) 
		{
			throw new ApplicationException("Could not find locale definition: ".$container.", ".$source." (".$this->locale_code.").");
		}
		
		return $definition;
	}
	
	public function determine_pluralization() 
	{
		$this->init_locale();
	
		$locale = $this->locale_code;
		$pluralization = null;
		
		// Load exact locale
		if ($this->pluralization_exists($locale)) 
		{
			$pluralization = $this->get_pluralization($locale);
		}
		else 
		{
			// Fallback to a neutral culture
			if ($pos = strpos($this->locale_code, '_')) 
			{
				$language = substr($this->locale_code, 0, $pos);
				$locale = $language . '_xx';
				
				if ($this->pluralization_exists($locale)) 
				{
					$pluralization = $this->get_pluralization($locale);
				}
				else 
				{
					$country = substr($this->locale_code, $pos+1);
					$locale = 'xx_' . $country;
					
					if ($this->pluralization_exists($locale)) 
					{
						$pluralization = $this->get_pluralization($locale);
					}
				}
			}
			else 
			{
				$locale = self::default_locale_code;
				
				// Fallback to a default language
				if ($this->pluralization_exists($locale)) 
				{
					$pluralization = $this->get_pluralization($locale);
				}
			}
		}
		
		if ($pluralization === null) 
		{
			throw new ApplicationException("Could not find locale pluralization ({$this->locale_code}).");
		}
		
		return $pluralization;
	}

	/**
	 * Returns an application localization string.
	 * @param string $container Specifies the string container. In the form of 'modulename.subcontainer'.
	 * @param string $source Specifies the string key.
	 * @param array $placeholders Optional list of placeholders. Used for replacement.
	 * @param array $options Optional list of options.
	 * Use these parameters if need to format string like if you use the sprintf function.
	 * @return string
	 * Example 1:
	 * Phpr::$locale->get_string('shop.messages', 'add_to_cart', array(
	 * 	'count' => 20
	 * ));
	 */
	public function get_string($container, $source, $placeholders = array(), $options = array()) 
	{
		$this->init_locale();
		
		$variation = 1;
		$replace_keys = array();
		$replace_values = array();
		
		$pluralization = $this->determine_pluralization();
		
		foreach ($placeholders as $key => $value) 
		{
			if (is_numeric($value))  {
				$value = (float)$value;
				
				if ($value === 0)  {
					$variation = 0;
				}
				else {
					$result = eval($pluralization);
					$variation = $result['current'];
				}
			}
			
			$replace_keys[] = ':' . $key;
			$replace_values[] = $value;
		}
		
		$definition = $this->determine_definition($container, $source, $placeholders, array_merge(array(
			'variation' => $variation
		), $options));
		
		// Run placeholder replacement against the definition
		$definition = str_replace($replace_keys, $replace_values, $definition);

		return trim($definition);
	}

	/**
	 * Sets the user locale.
	 * @param string $locale_code Specifies the user locale in format en_US.
	 */
	public function set_locale($locale_code) 
	{
		$this->locale_code = $locale_code;
		
		$this->init_locale(true);

		$this->dec_separator = null;
		$this->currency_is_loaded = false;
	}

	/**
	 * Returns the user locale.
	 */
	public function get_locale_code() 
	{
		$this->init_locale();
		return $this->locale_code;
	}

	/**
	 * Returns the user language.
	 */
	public function get_language_code() 
	{
		$this->init_locale();
		return $this->language_code;
	}

	/**
	 * Returns the user country.
	 */
	public function get_country_code() 
	{
		$this->init_locale();
		return $this->country_code;
	}

	/**
	 * Returns a string representation of a number, corresponding a current locale numbers format.
	 * @param float $number Specifies a number.
	 * @param int $decimals. Optional, number of decimals.
	 * @return string
	 */
	public function get_number($number, $decimals = 0) 
	{
		$this->init_locale();

		if ($this->dec_separator === null)
			$this->load_number_format();

		if (!strlen($number))
			return;

		return number_format($number, $decimals, $this->dec_separator, $this->group_separator);
	}

	/**
	 * Converts a string to a number, corresponding a current language numbers format.
	 * If the specified value may not be converted to number, returns boolean false.
	 * @param float $str Specifies a string to parse.
	 * @return mixed
	 */
	public function string_to_number($str) 
	{
		$this->init_locale();

		if ($this->dec_separator === null)
			$this->load_number_format();

		$val = str_replace($this->dec_separator, '.', $str);
		$val = str_replace($this->group_separator, '', $val);

		if (!is_numeric($val))
			return false;

		return $val;
	}

	/**
	 * Returns a string representation of a date, corresponding a current language dates format.
	 * @param Phpr\DateTime $date Specifies a date value.
	 * @param string $format. Optional, output format. 
	 * By default the short date format used(11/6/2006 - for en_US).
	 * @return string
	 */
	public function date(DateTime $date, $format = "%x") 
	{
		$this->init_locale();

		return $date->format($format);
	}

	/**
	 * Converts a string to a Phpr\DateTime object, corresponding a current language dates format.
	 * If the specified value may not be converted to date/time, returns boolean false.
	 * @param float $str Specifies a string to parse.
	 * @param string $format Specifies the date/time format.
	 * By default the short date format(%x) used(11/6/2006 - for en_US).
	 * @param DateTimeZone $timezone Optional. Specifies a time zone to assign to a new object.
	 * @return mixed
	 */
	public function string_to_date($str, $format = "%x", $timezone = null) 
	{
		return DateTime::parse($str, $format, $timezone);
	}

	/**
	 * Returns a string representation of the currency value, corresponding a current language currency format.
	 * @param float $value Specifies a currency value.
	 * @return string
	 */
	public function get_currency($value) 
	{
		$this->init_locale();

		if (!$this->currency_is_loaded)
			$this->load_currency_format();

		$is_negative = $value < 0;

		if ($is_negative)
			$value *= -1;

		$numeric_part = number_format($value, $this->decimal_digits, $this->decimal_separator, $this->group_separator);

		$final_format = $is_negative ? $this->n_format : $this->p_format;
		$sign = $is_negative ? $this->negative_sign : $this->positive_sign;

		$currency_symbol = $this->local_currency_symbol;
		
		if ($final_format == 3)
			$currency_symbol = $sign . $currency_symbol;
		elseif ($final_format == 4)
			$currency_symbol = $currency_symbol . $sign;

		if (!$is_negative) 
		{
			if ($this->p_cs_precedes)
				$num_and_cs = $this->p_sep_by_space ? $currency_symbol . ' ' . $numeric_part : $currency_symbol . $numeric_part;
			else
				$num_and_cs = $this->p_sep_by_space ? $numeric_part . ' ' . $currency_symbol : $numeric_part . $currency_symbol;
		} 
		else 
		{
			if ($this->n_cs_precedes)
				$num_and_cs = $this->n_sep_by_space ? $currency_symbol . ' ' . $numeric_part : $currency_symbol . $numeric_part;
			else
				$num_and_cs = $this->n_sep_by_space ? $numeric_part . ' ' . $currency_symbol : $numeric_part . $currency_symbol;
		}

		switch ($final_format) 
		{
			case 0: return '(' . $num_and_cs . ')';
			case 1: return $sign . $num_and_cs;
			case 2: return $num_and_cs . $sign;
			case 3: return $num_and_cs;
			case 4: return $num_and_cs;
		}
	}

	/**
	 * Loads the user locale.
	 */
	private function init_locale($force = false) 
	{
		if (!$force && $this->locale_code !== null)
			return;
			
		Phpr::$events->fire_event('phpr:on_before_locale_initialized', $this);

		// $locale_code = Phpr::$config->get('LOCALE', self::default_locale_code);
		try {
			$locale_code = \Core_Config::create()->locale_code;
		} 
		catch (Exception $ex) {
			$locale_code = self::default_locale_code;
		}

		if ($locale_code === 'auto')
			$locale_code = Phpr::$request->get_user_language();
		
		$x1 = explode('_', $locale_code);
			
		$this->language_code = $x1[0];
		$this->country_code = $x1[1];
		$this->locale_code = $locale_code;
		
		Phpr::$events->fire_event('phpr:on_after_locale_initialized', $this);
	}

	/**
	 * Loads the number format preferences.
	 */
	private function load_number_format() 
	{
		$this->dec_separator = $this->get_string('phpr.numbers', 'decimal_separator');
		$this->group_separator = $this->get_string('phpr.numbers', 'group_separator');
	}

	/**
	 * Loads the currency format preferences.
	 */
	private function load_currency_format() 
	{
		$this->intl_currency_symbol = $this->get_string('phpr.currency', 'intl_currency_symbol');
		$this->local_currency_symbol = $this->get_string('phpr.currency', 'local_currency_symbol');
		$this->decimal_separator = $this->get_string('phpr.currency', 'decimal_separator');
		$this->group_separator = $this->get_string('phpr.currency', 'group_separator');
		$this->decimal_digits = $this->get_string('phpr.currency', 'decimal_digits');
		$this->positive_sign = $this->get_string('phpr.currency', 'positive_sign');
		$this->negative_sign = $this->get_string('phpr.currency', 'negative_sign');
		$this->p_cs_precedes = (int)$this->get_string('phpr.currency', 'p_cs_precedes');
		$this->p_sep_by_space = (int)$this->get_string('phpr.currency', 'p_sep_by_space');
		$this->p_cs_precedes = (int)$this->get_string('phpr.currency', 'n_cs_precedes');
		$this->n_sep_by_space = (int)$this->get_string('phpr.currency', 'n_sep_by_space');
		$this->p_format = $this->get_string('phpr.currency', 'p_format');
		$this->n_format = $this->get_string('phpr.currency', 'n_format');

		$this->currency_is_loaded = true;
	}

	/**
	 * Returns a string in a specified locale container.
	 * @param string $locale Specifies a language
	 * @param string $container Specifies a file category
	 * @param string $source Specifies a string key
	 * @return string
	 */
	public function get_definition($locale, $container, $source, $variation = 1) 
	{
		if (!$this->definition_exists($locale, $container, $source, $variation))
			$this->load($locale);

		if (!isset($this->definitions[$locale][$container]))
			throw new ApplicationException("Could not find locale container: ".$container." (".$locale.").");
		
		if (!isset($this->definitions[$locale][$container][$source][$variation]))
			throw new ApplicationException("Could not find locale definition: ".$container.", ".$source.", ".$variation." (".$locale.").");
		
		return $this->definitions[$locale][$container][$source][$variation];
	}
	
	public function definition_exists($locale, $container, $source, $variation = 1) 
	{
		return isset($this->definitions[$locale][$container][$source][$variation]);
	}
	
	public function set_definition($locale, $container, $source, $value, $variation = 1) 
	{
		$this->definitions[$locale][$container][$source][$variation] = $value;
	}
	
	public function get_definitions() 
	{
		return $this->definitions;
	}
	
	public function get_pluralizations() 
	{
		return $this->pluralizations;
	}
	
	public function get_pluralization($locale) 
	{
		// attempt to load the locale if pluralization doesn't exist
		if (!$this->pluralization_exists($locale))
			$this->load($locale);

		// does it exist yet?
		if (!$this->pluralization_exists($locale))
			throw new ApplicationException("Could not find locale pluralization: ".$locale);
		
		$pluralization = $this->pluralizations[$locale];

		return $pluralization;
	}
	
	public function pluralization_exists($locale) 
	{
		return isset($this->pluralizations[$locale]);
	}
	
	public function set_pluralization($locale, $value) 
	{
		$this->pluralizations[$locale] = $value;
	}
	
	public function get_directory_paths() 
	{
		if ($this->directory_paths !== null)
			return $this->directory_paths;
		
		$this->directory_paths = array();
		$paths = array();
		
		$application_paths = Phpr::$class_loader->get_application_directories();
		$module_paths = Phpr::$class_loader->find_paths('modules');
		
		$paths = array_merge($paths, $application_paths);
		
		foreach ($module_paths as $module_path) 
		{
			$iterator = new \DirectoryIterator($module_path);
			
			foreach ($iterator as $directory) 
			{
				if (!$directory->isDir() || $directory->isDot())
					continue;
				
				$paths[] = $directory->getPathname();
			}
		}
		
		foreach ($paths as $path) 
		{
			if (!file_exists($directory_path = $path . '/localization'))
				continue;
			
			if (!is_readable($directory_path))
				throw new SystemException("Localization directory ".$directory_path." is not readable.");
		
			$this->directory_paths[] = $directory_path;
		}
		
		return $this->directory_paths;
	}
	
	public function get_partial_locale($locale_code) 
	{
		if (!$locale_code)
			return array();
	
		$x1 = explode('_', strtolower($locale_code));
		
		if (count($x1) === 2) 
		{
			return array(
				'locale' => $locale_code,
				'language' => $x1[0],
				'country' => $x1[1]
			);
		}
		else 
		{
			return array(
				'language' => $locale_code,
				'country' => $locale_code
			);
		}
	}
	
	public function get_file_paths($locale_code = null, $extension = 'csv') 
	{
		if (isset($this->file_paths[$locale_code . $extension]))
			return $this->file_paths[$locale_code . $extension];
		
		$this->file_paths[$locale_code . $extension] = array();
		
		$locale = $this->get_partial_locale($locale_code);
		$paths = $this->get_directory_paths();
		
		foreach ($paths as $path) 
		{
			$iterator = new \DirectoryIterator($path);

			foreach ($iterator as $file) 
			{
				if ($file->isDir() || !preg_match("/^([^\.]*)\.([^\.]*)\.".$extension."$/i", $file->getFilename(), $m1))
					continue;
					
				$partial_locale = $this->get_partial_locale($m1[2]);
				
				// This isn't the locale you are looking for
				if ($locale && !array_intersect($partial_locale, $locale))
					continue;
			
				$file_path = $path . '/' . $file->getFilename();
				
				if (!is_readable($file_path))
					throw new SystemException("Localization file ".$file_path." is not readable.");
			
				$this->file_paths[$locale_code . $extension][] = $file_path;
			}
		}
		
		return $this->file_paths[$locale_code . $extension];
	}
	
	/**
	 * Loads the locale strings.
	 * @param string $locale Specifies a locale
	 * @return null
	 */
	public function load($locale_code = null) 
	{
		$results = Phpr::$events->fire_event('phpr:on_before_locale_loaded', $this, $locale_code);
		
		foreach ($results as $result) {

			// Hook has handled locale
			if ($result)
				return; 
		}
		
		$this->load_definitions($locale_code);
		$this->load_pluralizations($locale_code);
		
		Phpr::$events->fire_event('phpr:on_after_locale_loaded', $this, $locale_code);
	}
	
	public function load_pluralizations($locale_code = null) 
	{
		$file_paths = $this->get_file_paths($locale_code, 'php');
		
		foreach ($file_paths as $file_path) 
		{
			$x1 = explode('.', $file_path);
			
			$extension = $x1[count($x1)-1];
			$locale = $x1[count($x1)-2];
			$handle = null;
			
			$x2 = explode('_', $locale);
			
			try 
			{
				$data = file_get_contents($file_path);
				
				if (!$this->pluralization_exists($locale))
					$this->set_pluralization($locale, $data);
			}
			catch (Exception $ex) 
			{
				if ($handle)
					@fclose($handle);

				throw $ex;
			}
		}
	}
	
	public function load_definitions($locale_code = null) 
	{
		$auto_detect_line_endings = ini_get('auto_detect_line_endings');
		ini_set('auto_detect_line_endings', 1);
		
		$file_paths = $this->get_file_paths($locale_code, 'csv');
		
		foreach ($file_paths as $file_path) 
		{
			$x1 = explode('.', $file_path);
			
			$extension = $x1[count($x1)-1];
			$locale = $x1[count($x1)-2];
			$handle = null;
			
			$x2 = explode('_', $locale);
			
			try 
			{
				$handle = fopen($file_path, 'r');
				$delimeter = ',';
				$first_row_found = false;
				$line_number = 0;
				$has_variation = false;
				
				while (($row = fgetcsv($handle, 2000000, $delimeter)) !== false) 
				{
					++$line_number;

					if (\File\Csv::csv_row_is_empty($row))
						continue;

					if (!$first_row_found) 
					{
						$first_row_found = true;

						continue;
					}
					
					// Not a definition, perhaps a comment or heading?
					if (count($row) < 3)
						continue;
					
					if (count($row) === 4)
						$has_variation = true;
					
					$container = $row[0];
					$source = $row[1];
					
					if ($has_variation) 
					{
						$variation = $row[2];
						
						if ((int)$variation != $variation)
							throw new ApplicationException("Invalid locale definition. Most likely the definition is incompatible CSV. Please verify the variation column.");
						
						$destination = $row[3];
					}
					else 
					{
						$variation = 1;
						$destination = $row[2];
					}
					
					$locale = trim($locale);
					$container = trim($container);
					$source = trim($source);
					$variation = trim($variation);

					if (!$this->definition_exists($locale, $container, $source, $variation))
						$this->set_definition($locale, $container, $source, $destination, $variation);
				}
			}
			catch (Exception $ex) 
			{
				if ($handle)
					@fclose($handle);

				throw $ex;
			}
		}
		
		ini_set('auto_detect_line_endings', $auto_detect_line_endings);
	}
}
