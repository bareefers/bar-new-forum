<?php

class MbqErrorHandle
{
    public static function handleException($e)
    {
        /** @var Error $error */
        $error = $e;
        if(!empty($error) && ($error instanceof Error)){
            /** @var Error $error */
            $errorInfo = "Server error occurred: '{$error->getCode()} {$error->getMessage()} (".basename($error->getFile()).":{$error->getLine()})'";

            MbqError::alert('', $errorInfo);
        }else if ($e instanceof \XF\Db\Exception || $e instanceof \Exception)
        {
            /** @var \XF\Db\Exception $e */
            $errorInfo = "Server error occurred: '{$e->getCode()} {$error->getMessage()} (".basename($error->getFile()).":{$error->getLine()})'";

            MbqError::alert('', $errorInfo);
        }
        else
        {
            if (is_string($e) && isset($_GET['showException']) && $_GET['showException']) {
                MbqError::alert('', $e);
            }
        }
    }

    public static function handlePhpError($errorType, $errorString, $file, $line)
    {
        $debug = \XF::$debugMode;
        $trigger = true;
        if ($errorType) {
            if ($errorType & E_STRICT
                || $errorType & E_DEPRECATED
                || $errorType & E_USER_DEPRECATED
            )
            {
                $trigger = false;
            }
            else if ($errorType & E_NOTICE || $errorType & E_USER_NOTICE)
            {
                $trigger = false;
            }else if ($errorType == E_WARNING || $errorType == E_USER_WARNING) {
                $trigger = false;
            }else if ($errorType == E_ERROR || $errorType == E_USER_ERROR) {
                $trigger = true;
            }
        }else{
            $trigger = false;
        }

        if ($debug || $trigger) {
            \XF::handlePhpError($errorType, $errorString, $file, $line);
        }
    }

}
