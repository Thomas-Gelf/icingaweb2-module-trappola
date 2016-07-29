?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Icinga\IcingaTrapIssue as Issue;
use Icinga\Module\Trappola\Trap;

class OmniPcxTrapHandler extends TrapHandler
{
    protected $oid = '.1.3.6.1.4.1.637.64.91300.1.';

    protected $varPrefix = '.1.3.6.1.4.1.637.64.91300.2.';

    protected $enumYesNo = array('no', 'yes');

    protected $severityType = array(
        1 => 'noError',
        2 => 'warning',
        3 => 'error',
        4 => 'criticalError',
    );

    public function mangle(Trap $trap)
    {
        $this->trap = $trap;
        $trap->message = preg_replace_callback(
            '/\{(\d+)\}/',
            array($this, 'replaceMessageVar'),
            $trap->message
        );
    }

    protected function replaceMessageVar($m)
    {
        // TODO: Impossible, as we do not have VARIABLES from MIB, and
        //       OIDs do not match variable ordering :-(
        // $varOid = $this->varPrefix . $m[1] . '.0';
        // $var = $this->trap->getVarBind($varOid);

        switch ($m[1]) {
            case '1':
                $var =  $this->trap->getVarBind($this->varPrefix . '1.0');
                $value = $this->severityType[$var->value];
                break;
            case '2':
                $var =  $this->trap->getVarBind($this->varPrefix . '3.0');
                $value = $var->value;
                break;
            case '3':
                $var =  $this->trap->getVarBind($this->varPrefix . '2.0');
                $value = $var->value;
                break;
            default:
                return $m[0];
        }

        return str_replace($m[0], $m[1], $value);
    }
}
