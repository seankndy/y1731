<?php
class BinosPoller implements SeanKndy\Y1731\Poller
{
    protected $mibDir;

    public function __construct($mibDir) {
        $this->mibDir = $mibDir;
    }

    public function poll(Monitor $monitor) {
        $attribs = $monitor->getAttributes();

        foreach (glob($this->mibDir . '/*.mib') as $mib) {
            snmp_read_mib($mib);
        }
        // little bit of a hack to get the latest index, works for now
        $oid = "PRVT-SAA-MIB::prvtSaaY1731TestResultDelayNE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\"";
        if (!($delay_ne_walk = snmp2_real_walk($monitor->getDeviceIp(), $monitor->getDeviceSnmpCommunity(), $oid))) {
            return false;
        }
        $index = null;
        if (preg_match('/\.([0-9]+)$/', array_pop(array_keys($delay_ne_walk)), $m))
            $index = $m[1];
        else
            return false;

        // verify we haven't already pulled this data index
        /*
        if (($last_index = y1731_monitor_get_last_result_index($monitor['id'])) && $last_index == $index) {
         	return true;
        }
        */

        $oids = array(
            'delayNe'	  => "PRVT-SAA-MIB::prvtSaaY1731TestResultDelayNE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'delayFe'	  => "PRVT-SAA-MIB::prvtSaaY1731TestResultDelayFE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'jitterNe'    => "PRVT-SAA-MIB::prvtSaaY1731TestResultJitterNE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'jitterFe'    => "PRVT-SAA-MIB::prvtSaaY1731TestResultJitterFE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'framelossNe' => "PRVT-SAA-MIB::prvtSaaY1731TestResultFrameLossNE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'framelossFe' => "PRVT-SAA-MIB::prvtSaaY1731TestResultFrameLossFE.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
            'errors'      => "PRVT-SAA-MIB::prvtSaaY1731TestResultNoErrors.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index",
          	'timeouts'    => "PRVT-SAA-MIB::prvtSaaY1731TestResultNoTimeouts.\"{$attribs['saa_owner_name']}\".\"{$attribs['saa_name']}\".$index"
        );
        $result = new Result($monitor);
        foreach ($oids as $type => $oid) {
            $setMethod = 'set' . ucfirst($type);
            if (!method_exists($result, $setMethod)) continue;

            if (($val = snmp2_get($monitor->getDeviceIp(), $monitor->getDeviceSnmpCommunity(), $oid)) !== false)
                $result->$setMethod(preg_replace('/^Gauge(32|64):\s*/', '', $val));
            else
         	    $result->$setMethod(-1);
        }

        return $result;
    }
}
