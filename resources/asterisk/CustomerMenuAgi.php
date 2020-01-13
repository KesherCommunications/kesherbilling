<?php
class CustomerMenuAgi
{
    public static function customerMenu(&$agi, &$MAGNUS)
    {
        $agi->verbose('Starting Customer Menu', 15);
        //$res_dtmf = $agi->get_data($prompt_enter_dest, $this->agiconfig['dtmfEntryTimeout'], 20);
        //$agi->verbose("RES DTMF -> " . $res_dtmf["result"], 10);
        $MAGNUS->sayBalance($agi, $MAGNUS->credit);
    }
}