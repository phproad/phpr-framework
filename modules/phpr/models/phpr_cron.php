<?php

/**
 * PHPR Cron class
 * 
 * Used for managing scheduled events and house keeping
 * via a regular table and worker jobs
 */

class Phpr_Cron
{

    public static function update_interval($code)
    {
        $bind = array(
            'record_code' => $code, 
            'now' => Phpr_DateTime::now()->toSqlDateTime()
        );
        Db_DbHelper::query('insert into core_cron_table (record_code, updated_at) values (:record_code, now()) on duplicate key update updated_at =:now', $bind);
    }

    public static function get_interval($code)
    {
        $interval = Db_DbHelper::scalar('select updated_at from core_cron_table where record_code =:record_code', array('record_code'=>$code));
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
            // Jobs are one off executions
            self::execute_cronjobs();

            // Tables are regular executions
            self::execute_crontabs();
        }
        catch (Exception $ex)
        {            
            echo $ex->getMessage();
        }
    }

    // Usage: 
    //   Phpr_Cron::queue_job('User_Model::static_method', array('param1', 'param2', 'param3'));
    // Executes: 
    //   User_Model::static_method('param1', 'param2', 'param3');
    public static function queue_job($handler_name, $param_data=array())
    {
        $bind = array(
            'handler_name' => $handler_name, 
            'param_data' => serialize($param_data),
            'now' => Phpr_DateTime::now()->toSqlDateTime()
        );
        Db_DbHelper::query('insert into core_cron_jobs (handler_name, param_data, created_at) values (:handler_name, :param_data, now())', $bind);
    }

    private static function execute_cronjobs()
    {
        // Worker can perform only 5 jobs per run
        // 
        $jobs = Db_DbHelper::objectArray('select * from core_cron_jobs order by created_at asc limit 5');

        foreach ($jobs as $job)
        {
            Db_DbHelper::query('delete from core_cron_jobs where id=:id limit 1', array('id'=>$job->id));

            $params = $job->param_data ? unserialize($job->param_data) : array();
            $parts = explode('::', $job->handler_name);
            if (count($parts) < 1)
                continue;

            $model_class = $parts[0];
            $method_name = $parts[1];

            if (method_exists($model_class, $method_name))
                call_user_func_array(array($model_class, $method_name), $params);
        }
    }

    private static function execute_crontabs()
    {
        $modules = Phpr_Module_Manager::find_modules();
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
}