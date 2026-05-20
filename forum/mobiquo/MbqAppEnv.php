<?php
defined('MBQ_IN_IT') or exit;

/**
 * application environment class
 */
Class MbqAppEnv extends MbqBaseAppEnv {
    
    /* this class fully relys on the application,so you can define the properties what you need come from the application. */
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * application environment init
     */
    public function init() {
    }
}
