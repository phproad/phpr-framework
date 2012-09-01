<?php

/**
 * PHPR Cron table
 */

class Phpr_Cron extends Db_ActiveRecord
{

    public $table_name = 'core_crontab';

    public static function update_interval($code)
    {
        $bind = array('record_code'=>$code, 'datetime'=>Phpr_DateTime::now()->toSqlDateTime());
        Db_DbHelper::query('insert into core_crontab (record_code, updated_at) values (:record_code, now()) on duplicate key update updated_at =:datetime', $bind);
    }

    public static function get_interval($code)
    {
        $interval = Db_DbHelper::scalar('select updated_at from core_crontab where record_code =:record_code', array('record_code'=>$code));
        if (!$interval)
        {
            self::update_interval($code);
            $interval = Phpr_DateTime::now()->toSqlDateTime();
        }

        return $interval;
    }

    public static function execute_cron()
    {
        try
        {
            $modules = Core_Module_Manager::find_modules();
            foreach ($modules as $module)
            {
                $module_id = $module->get_id();
                $cron_items = $module->subscribe_crontab();

                if (!is_array($cron_items))
                    continue;

                foreach ($cron_items as $code=>$options)
                {
                    $code = $module_id . '_' . $code;
                    if (!isset($options['interval']) || !isset($options['method']))
                        continue;

                    $last_exec = Phpr_DateTime::parse(Phpr_Cron::get_interval($code), Phpr_DateTime::universalDateTimeFormat);
                    $next_exec = $last_exec->addMinutes($options['interval']);
                    $can_execute = Phpr_DateTime::now()->compare($next_exec);

                    if ($can_execute == -1)
                        continue;
                    try
                    {
                        $method = $options['method'];
                        if ($module->$method())
                            Phpr_Cron::update_interval($code);
                    }
                    catch (Exception $ex)
                    {            
                        echo "Error in cron: " . $code . PHP_EOL;
                        echo $ex->getMessage();
                    }
                }
            }
        }
        catch (Exception $ex)
        {            
            echo $ex->getMessage();
        }
    }
}