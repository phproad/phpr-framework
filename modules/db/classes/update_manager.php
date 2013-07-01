<?php namespace Db;

use Phpr\Module_Manager;
use Phpr\ApplicationException;
use Db\Helper as Db_Helper;

/**
 * PHPR Update manager
 * Manages module update chain
 */
class Update_Manager 
{
	private static $versions = null;
	private static $updates = null;

	// Updates all modules
	public static function update() 
	{
		self::create_meta_table();

		$all_modules = self::get_modules_and_paths();
		$has_updates = false;

		// 1. Spool up the database schema and look for updates
		// 

		foreach ($all_modules as $module_id => $base_path) {

			$current_db_version = self::get_db_version($module_id);

			// Get the latest version passed by reference
			$last_dat_version = null;
			self::get_dat_versions($module_id, $last_dat_version, $base_path);

			if ($current_db_version != $last_dat_version) {
				$has_updates =  true;
			}

			self::apply_db_structure($base_path, $module_id);
		}

		// 2. If updates are found, commit all the spooled schema changes
		// 

		if ($has_updates) {
			Structure::save_all();
		}

		// 3. Apply database version upgrade files (to be deprecated in future)
		// 

		foreach ($all_modules as $module_id => $base_path) {
			self::update_module($module_id, $base_path);	
		}

		// Clear cache
		// 
		
		if ($has_updates)  {
			ActiveRecord::clear_describe_cache();
		}
	}

	/**
	 * Returns all modules (including system) and their paths
	 * respecting the UPDATE SEQUENCE defined in config
	 */
	private static function get_modules_and_paths()
	{
		$found_modules = array();

		// Find system modules
		//

		$modules_path = PATH_SYSTEM . DS . PHPR_MODULES;
		$iterator = new \DirectoryIterator($modules_path);

		foreach ($iterator as $dir) 
		{
			if (!$dir->isDir() || $dir->isDot())
				continue;

			$module_id = $dir->getFilename();

			$found_modules[$module_id] = PATH_SYSTEM;
		}

		// Update application modules
		// 
		
		$modules = Module_Manager::get_modules(true);
		$module_ids = array();

		foreach ($modules as $module)
		{
			$id = mb_strtolower($module->get_module_info()->id);
			$module_ids[$id] = 1;
		}

		$sequence = array_flip(Phpr::$config->get('UPDATE_SEQUENCE', array()));

		if (count($sequence))
		{
			$updated_module_ids = $sequence;
			foreach ($module_ids as $module_id=>$value)
			{
				if (!isset($sequence[$module_id]))
					$updated_module_ids[$module_id] = 1;
			}

			$module_ids = $updated_module_ids;
		}

		$module_ids = array_keys($module_ids);

		foreach ($module_ids as $module_id)
		{
			if (!isset($modules[$module_id]))
				continue;

			$module = $modules[$module_id];
			$module_path = DS . PHPR_MODULES . DS . $module_id;
			$base_path = str_replace($module_path, '', $module->get_module_path());

			$found_modules[$module_id] = $base_path;
		}

		return $found_modules;
	}

	public static function reset_cache() 
	{
		self::$versions = null;
	}

	/**
	 * Returns versions of all modules installed in the system
	 */
	public static function get_versions()
	{
		$result = array();

		$modules = Module_Manager::get_modules(false);
		foreach ($modules as $module)
		{
			$module_id = $module->get_module_info()->id;
			$version = self::get_db_version($module_id);
			$result[$module_id] = $version;
		}

		return $result;
	}

	/**
	 * Checks whether a version does exist in the module update history.
	 * @param string $module_id Specifies a module identifier.
	 * @param string @version_str Specifies a version string.
	 * @return boolean Returns true if the version was found in the module update history. Returns false otherwise.
	 */
	public static function module_version_exists($module_id, $version_str)
	{	
		$bind = array(
			'module_id'   => $module_id,
			'version_str' => $version_str
		);
		return (Db_Helper::scalar('select count(*) from phpr_module_update_history where module_id=:module_id and version_str=:version_str', $bind) > 0);
	}

	// Updates a single module
	// $base_path can specify the exact location
	public static function update_module($module_id, $base_path = null)
	{
		$base_path = $base_path === null ? PATH_APP : $base_path;

		$last_dat_version = null;
		$dat_versions = self::get_dat_versions($module_id, $last_dat_version, $base_path);

		// Apply new database/structure/php updates
		//

		$db_update_result = false;

		$current_db_version = self::get_db_version($module_id);
		$last_db_version_index = self::get_db_update_index($current_db_version, $dat_versions);

		foreach ($dat_versions as $index => $update_info)
		{
			if ($update_info['type'] == 'update-reference') 
			{
				$db_update_result = self::apply_db_update($base_path, $module_id, $update_info['reference']) || $db_update_result;
			}
			elseif ($update_info['type'] == 'version-update')
			{
				// Apply updates from references specified in the version string
				foreach ($update_info['references'] as $reference)
					$db_update_result = self::apply_db_update($base_path, $module_id, $reference) || $db_update_result;

				// Apply updates with names matching the version number
				if ($index > $last_db_version_index && $last_db_version_index !== -2)
				{
					if (strlen($update_info['build']))
						$db_update_result = self::apply_db_update($base_path, $module_id, $update_info['build']) || $db_update_result;
					else
						$db_update_result = self::apply_db_update($base_path, $module_id, $update_info['version']) || $db_update_result;
				}
			}
		}

		// Increase the version number and add new version records to the version history table
		if ($current_db_version != $last_dat_version) {
			self::set_db_version($current_db_version, $last_dat_version, $dat_versions, $module_id);
		}
		
		return $db_update_result;
	}

	/**
	 * Applies module update file(s).
	 * @param string $base_path Base module directory.
	 * @param string $module_id Module identifier.
	 * @param string $update_id Update identifier.
	 * @return boolean Returns true if any updates have been applied. Returns false otherwise.
	 */
	protected static function apply_db_update($base_path, $module_id, $update_id)
	{
		// If the update has already been applied, return false
		if (in_array($update_id, self::get_module_applied_updates($module_id)))
			return false;

		$result = false;

		// Apply PHP update file
		$update_path =  $base_path . DS . PHPR_MODULES . DS . $module_id . DS . 'updates' . DS . $update_id.'.php';
		if (file_exists($update_path))
		{
			$result = true;
			include $update_path;
		}

		// Apply SQL update file
		$update_path =  $base_path . DS . PHPR_MODULES . DS . $module_id . DS . 'updates' . DS . $update_id.'.sql';
		if (file_exists($update_path))
		{ 
			$result = true;
			Db_Helper::execute_sql_from_file($update_path);
		}

		// Register the applied update in the database and in the internal cache
		if ($result)
			self::register_applied_module_update($module_id, $update_id);

		return $result;
	}

	public static function apply_db_structure($base_path, $module_id)
	{
		Structure::$module_id = $module_id;
		
		$structure_file =  $base_path . DS . PHPR_MODULES . DS . $module_id . DS . 'updates' . DS . 'structure.php';
		if (file_exists($structure_file))
		{
			include $structure_file;
		}

		Structure::$module_id = null;
	}

	public static function create_meta_table()
	{
		$tables = Db_Helper::list_tables();
		if (!in_array('phpr_module_versions', $tables))
			Db_Helper::execute_sql_from_file(PATH_SYSTEM.'/'.PHPR_MODULES.'/phpr/updates/bootstrap.sql');
	}

	/**
	 * Returns version of a module stored in the database.
	 * @param string $module_id Specifies the module identifier.
	 * @return string Returns the module version.
	 */
	public static function get_db_version($module_id)
	{
		if (self::$versions === null)
		{
			$versions = Db_Helper::query_array('select module_id, version_str as version from phpr_module_versions order by id');
			self::$versions = array();

			foreach ($versions as $version_info)
			{
				$id = $version_info['module_id'];
				self::$versions[$id] = $version_info['version'];
			}
		}

		if (array_key_exists($module_id, self::$versions))
			return self::$versions[$module_id];

		$bind = array(
			'module_id' => $module_id, 
			'date'      => gmdate('Y-m-d h:i:s')
		);

		Db_Helper::query('insert into phpr_module_versions(module_id, date, `version`) values (:module_id, :date, 0)', $bind);

		return 0;
	}

	/**
	 * Updates module version history and its version in the database.
	 * @param string $current_db_version Current module version number stored in the database.
	 * @param string $last_dat_version Latest module version specified in the module version.dat file.
	 * @param mixed $dat_versions Parsed versions information from the module version.dat file.
	 * @param string $module_id Module identifier.
	 */
	private static function set_db_version($current_db_version, $last_dat_version, &$dat_versions, $module_id)
	{
		if (self::$versions === null)
			self::$versions = array();

		// Update the module version number
		// 
		
		$bind = array('version_str'=>$last_dat_version, 'module_id'=>$module_id);
		Db_Helper::query('update phpr_module_versions set `version`=null, version_str=:version_str where module_id=:module_id', $bind);

		self::$versions[$module_id] = $last_dat_version;
		
		// Add version history records
		// 

		$last_db_version_index = self::get_db_update_index($current_db_version, $dat_versions);
		if ($last_db_version_index !== -2)
		{
			$last_version_index = count($dat_versions)-1;
			$start_index = $last_db_version_index+1;
			if ($start_index <= $last_version_index)
			{

				for ($index=$start_index; $index <= $last_version_index; $index++)
				{
					$version_info = $dat_versions[$index];

					if ($version_info['type'] != 'version-update')
						continue;

					Db_Helper::query(
						'insert
							into phpr_module_update_history(date, module_id, `version`, description, version_str)
							values(:date, :module_id, :version, :description, :version_str)',
						array(
							'date'=>gmdate('Y-m-d h:i:s'),
							'module_id'=>$module_id,
							'version'=>$version_info['build'],
							'description'=>$version_info['description'],
							'version_str'=>$version_info['version']
						)
					);
				}
			}
		}
	}

	/**
	 * Returns index of a record in the version.dat file which corresponds to the latest version of the module stored in the database.
	 * @param string $current_db_version Current module version number stored in the database.
	 * @param mixed $dat_versions Parsed versions information from the module version.dat file.
	 * @return integer Returns the version record index. Returns -1 if a matching record was not found in the database.
	 */
	public static function get_db_update_index($current_db_version, &$dat_versions)
	{
		foreach ($dat_versions as $index=>$version_info)
		{
			if ($version_info['type'] == 'version-update')
			{
				if ($version_info['version'] == $current_db_version)
					return $index;
			}
		}

		if ($current_db_version)
			return -2;

		return -1;
	}

	/**
	 * Returns full version information from a module's version.dat file.
	 * Returns a list of versions and update references in the following format:
	 * array(
	 *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
	 *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
	 * )
	 * @param string $module_id Specifies the module identifier.
	 * @param string $last_version Reference to the latest version in the version.dat file.
	 * @param string $base_path Base module path, defaults to the application root directory.
	 * @return array Returns array of application versions and references to the database update files.
	 */
	public static function get_dat_versions($module_id, &$last_version, $base_path = null)
	{
		$base_path = $base_path === null ? PATH_APP : $base_path;
		$versions_path = $base_path . DS . PHPR_MODULES . DS . $module_id . DS . 'updates' . DS . 'version.dat';
		if (!file_exists($versions_path))
			return array();

		return self::parse_dat_file($versions_path, $last_version);
	}

	/**
	 * Parses a .dat file and returns full version information it contains.
	 * Returns a list of versions and update references in the following format:
	 * array(
	 *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
	 *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
	 * )
	 * @param string $file_path Path to the file to parse.
	 * @param string $last_version Reference to the latest version in the version.dat file.
	 * @return array Returns array of application versions and references to the database update files.
	 */
	public static function parse_dat_file($file_path, &$last_version)
	{
		$last_version = null;

		if (!file_exists($file_path))
			return array();

		$contents = file_get_contents($file_path);

		// Normalize line-endings and split the file content
		//

		$contents = str_replace("\r\n", "\n", $contents);
		$update_list = preg_split("/^\s*(#)|^\s*(@)/m", $contents, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		// Analyze each update and extract its type and description
		//

		$length = count($update_list);
		$result = array();

		for ($index=0; $index < $length;)
		{
			$update_type = $update_list[$index];
			$update_content = $update_list[$index+1];

			if ($update_type == '@')
			{
				// Parse update references
				//

				$references = preg_split('/\|\s*@/', $update_content);
				foreach ($references as $reference)
					$result[] = array('type'=>'update-reference', 'reference'=>trim($reference));
			} 
			elseif ($update_type == '#')
			{
				// Parse version strings
				//

				$pos = mb_strpos($update_content, ' ');

				if ($pos === false)
					throw new ApplicationException('Error parsing version file ('.$file_path.'). Version string should have a description: '.$update_content);

				$version_info = trim(mb_substr($update_content, 0, $pos));
				$description = trim(mb_substr($update_content, $pos+1));

				// Expected version/update notations:
				// 2
				// 2|0.0.2
				// 2|@abc123de46
				// 0.0.2|@abc123de45|@abc123de46
				// 

				$version_info_parts = explode('|@', $version_info);

				$version_number = self::extract_version_number($version_info_parts[0]);
				$build_number = self::extract_build_number($version_info_parts[0]);
				$references = array();

				if (($cnt = count($version_info_parts)) > 1)
				{
					for ($ref_index = 1; $ref_index < $cnt; $ref_index++)
						$references[] = $version_info_parts[$ref_index];
				}

				$last_version = $version_number;

				$result[] = array(
					'type'=>'version-update',
					'version'=>$version_number,
					'build'=>$build_number,
					'description'=>$description,
					'references'=>$references
				);
			}

			$index += 2;
		}

		return $result;
	}

	/**
	 * Extracts version number from a version string, which can also contain a build number.
	 * Returns "1.0.2" for 2|1.0.2.
	 * @param string $versionString Version string.
	 * @return string Returns the version string.
	 */
	public static function extract_version_number($version_string)
	{
		$parts = explode('|', $version_string);
		if (count($parts) == 2)
			return trim($parts[1]);

		if (strpos($parts[0], '.') === false)
			return '1.0.'.trim($parts[0]);

		return trim($parts[0]);
	}

	/**
	 * Extracts build number from a version string (backward compatibility).
	 * Returns "2" for 2|1.0.2. Returns null for 1.0.2.
	 * @param string $versionString Version string.
	 * @return string Returns the build number.
	 */
	public static function extract_build_number($version_string)
	{
		$parts = explode('|', $version_string);
		if (count($parts) == 2)
			return trim($parts[0]);

		if (strpos($parts[0], '.') !== false)
			return null;

		return trim($parts[0]);
	}

	/**
	 * Returns a list of update identifiers which have been applied to a specified module.
	 * @param string $module_id Specified the module identifier.
	 * @return array Returns a list of applied update identifiers.
	 */
	public static function get_module_applied_updates($module_id)
	{
		if (self::$updates === null)
		{
			self::$updates = array();

			$update_list = Db_Helper::query_array('select * from phpr_module_applied_updates');
			foreach ($update_list as $update_info)
			{
				if (!array_key_exists($update_info['module_id'], self::$updates))
					self::$updates[$update_info['module_id']] = array();

				self::$updates[$update_info['module_id']][] = $update_info['update_id'];
			}
		}

		if (!isset(self::$updates[$module_id]))
			return array();

		return self::$updates[$module_id];
	}

	/**
	 * Adds update to the list of applied module updates.
	 * @param string $module_id Specified the module identifier.
	 * @param string $update_id Specified the update identifier.
	 */
	protected static function register_applied_module_update($module_id, $update_id)
	{
		if (self::$updates === null)
			self::$updates = array();

		if (!isset(self::$updates[$module_id]))
			self::$updates[$module_id] = array();

		self::$updates[$module_id][] = $update_id;
		$bind = array(
			'module_id'  => $module_id,
			'update_id'  => $update_id,
			'created_at' => gmdate('Y-m-d h:i:s')
		);
		Db_Helper::query('insert into phpr_module_applied_updates (module_id, update_id, created_at) 
			values (:module_id, :update_id, :created_at)', $bind);
	}
}
