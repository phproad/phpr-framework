<?php

/**
 * Execute cron as a standalone
 * 
 * Example usage:
 *   /usr/local/bin/php -q /home/YOUR_USERNAME/public_html/cron.php
 */

chdir(dirname(__FILE__));

$APP_CONF = array();

$PHPR_INIT_ONLY = true;

include 'index.php';

Phpr_Cron::execute_cron();