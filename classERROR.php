<?php
/*
 * 	Class:	Error
 * 	Progr.:	Mikhail Tchervonenko
 * 	Data: 	2009-03-04
 * 	EMail: 	rusmikleATgmailPointCom
 *  ICQ: 	35818796
 *  Skype: 	RusMikle
 *
 *   ver 1.0.0
 *
 *   GNU General Public License
 */

class ERR
{
    private $show_errors = false;
    private $stop_after_error = false;
    private $error = 0;
    private $error_message = "";
    private $error_messages = false;
    private $error_backtrase = false;
    private $error_filename = './sql.log';


    /**
     * err_log function start
     * @param string $error_text
     * @param bool $show_errors
     * @param bool $stop_after_error
     * @param bool $error_backtrase
     * @return bool
     */
    public function err_log($error_text = "", $show_errors = false, $stop_after_error = false, $error_backtrase = false)
    {
        if (defined('BPATH'))
        {
            //$this->error_filename = BPATH . 'includes/data/logs/sql.log';
        }

        $this->show_errors = $show_errors;
        $this->stop_after_error = $stop_after_error;
        $this->error_backtrase = $error_backtrase;


        $this->error = 1;

        $error_backtrase = $this->backtrace() . "\n\r";

        $this->error_message = ": " . date("D M j G:i:s T Y") . "\n\r" . $error_text;

        if (!file_exists($this->error_filename))
        {
            file_put_contents($this->error_filename, '');
        }

        if (is_writable($this->error_filename))
        {
            if (!$handle = fopen($this->error_filename, 'a'))
            {
                $ret = false;
            }
            if (fwrite($handle, "\n\r#################################################################\n\r" . $this->error_message . $error_backtrase . "\n\r") === FALSE)
            {
                $ret = false;
            }
            fclose($handle);
            $ret = true;
        }

        if ($this->error_backtrase) $this->error_message .= "\n\r" . $error_backtrase;

        $this->error_messages[] = $this->error_message;


        if ($this->show_errors) echo str_replace("\n\r", "<br>", $this->error_message);

        if ($this->stop_after_error) exit();

        return $ret;
    }


    /**
     * backtrace function start
     * @return string
     */
    private function backtrace()
    {
        $output = "\n\r";
        $output .= "Stack:";
        $backtrace = debug_backtrace();
        foreach ($backtrace as $bt)
        {
            $args = '';
            foreach ($bt['args'] as $a)
            {
                if (!empty($args))
                {
                    $args .= ', ';
                }
                switch (gettype($a))
                {
                    case 'integer':
                    case 'double':
                        $args .= $a;
                        break;
                    case 'string':
                        $a = substr($a, 0, 64) . ((strlen($a) > 64) ? '...' : '');
                        $args .= "\"$a\"";
                        break;
                    case 'array':
                        $args .= 'Array(' . count($a) . ')';
                        break;
                    case 'object':
                        $args .= 'Object(' . get_class($a) . ')';
                        break;
                    case 'resource':
                        $args .= 'Resource(' . strstr($a, '#') . ')';
                        break;
                    case 'boolean':
                        $args .= $a ? 'True' : 'False';
                        break;
                    case 'NULL':
                        $args .= 'Null';
                        break;
                    default:
                        $args .= 'Unknown';
                }
            }
            $output .= "\n\r";
            $output .= "file: {$bt['line']} - {$bt['file']}\n\r";
            $output .= "call: {$bt['class']}{$bt['type']}{$bt['function']}($args)\n\r";
        }
        $output .= "\n\r";
        return $output;
    }


    /**
     * getMessage function start
     * @return string
     */
    public function getMessage()
    {
        return $this->error_message;
    }
}

?>