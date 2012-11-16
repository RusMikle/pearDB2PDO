<?php
/*
 * 	Class:	Error
 * 	Progr.:	Mikhail Tchervonenko
 * 	Data: 	2012-11-16
 * 	EMail: 	rusmikleATgmailPointCom
 *  ICQ: 	35818796
 *  Skype: 	RusMikle
 *
 *   ver 1.0.1
 *
 *   GNU General Public License
 */

class ERR
{
    private $show_errors = false;
    private $stop_after_error = false;
    private $error = 0;
    private $error_message = '';
    private $error_messages = false;
    private $error_bt = false;
    private $error_filename = 'error_log/error_log.txt';


    // ***********************************
    // ***** err_log function start
    public function err_log ($error_text = "", $show_errors = false, $stop_after_error = false, $error_bt = false)
    {
        $this->show_errors      = $show_errors;
        $this->stop_after_error = $stop_after_error;
        $this->error_bt         = $error_bt;

        $ret = true;

        $this->error = 1;

        $error_bt = $this->backtrace() . "\n\r";

        $this->error_message = ": " . date("D M j G:i:s T Y") . "\n\r" . $error_text;

        if (is_writable($this->error_filename))
        {
            $handle = fopen($this->error_filename, 'a');
            fwrite($handle, "\n\r###############################\n\r" . $this->error_message . $error_bt . "\n\r");
            fclose($handle);
        }
        else
            $ret = false;

        if ($this->error_bt)
            $this->error_message .= "\n\r" . $error_bt;

        $this->error_messages[] = $this->error_message;


        if ($this->show_errors)
            echo str_replace("\n\r", "<br>", $this->error_message);

        if ($this->stop_after_error)
            exit();

        return $ret;
    }

    // ***********************************
    // ***** backtrace function start
    private function backtrace ()
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

    // ***********************************
    // ***** getMessage function start
    function getMessage ()
    { // dieses Metot existiert nur für Kompatibilitat mit alte PEAR Bibliothek !!!!
        return $this->error_message;
    }

}

?>