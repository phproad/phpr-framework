<?php namespace Db;

/*
 * Interface for models which cache records in the memory
 */
interface Memory_Cacheable
{
	/*
	 * Returns a record by its identifier. If the record exists in the cache,
	 * returns the cached value. If it doesn't exist, finds the record, 
	 * adds it to the cache and returns the record.
	 * @param int $record_id Specifies the record identifier. Can be NULL 
	 * if a new record is requested.
	 */
	public function get_record_cached($record_id);
}
