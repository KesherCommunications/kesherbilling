<?php

class StandardCallAgi
{

    public function processCall(&$MAGNUS, &$agi, &$CalcAgi)
    {
        //Play intro message
        if (strlen($MAGNUS->agiconfig['intro_prompt']) > 0) {
            $agi->stream_file($MAGNUS->agiconfig['intro_prompt'], '#');
        }

        // CALL AUTHENTICATE AND WE HAVE ENOUGH CREDIT TO GO AHEAD
        if (AuthenticateAgi::authenticateUser($agi, $MAGNUS) == 1) {
            $count = 0;
            for ($i = 0; $i < $MAGNUS->agiconfig['number_try']; $i++) {
                // CREATE A DIFFERENT UNIQUEID FOR EACH TRY
                $count++;

                $agi->verbose("LOOP COUNT -> " . $count, 10);

                if ($i > 0) {
                    $MAGNUS->uniqueid = $MAGNUS->uniqueid + 1000000000;
                }
                $MAGNUS->extension = $MAGNUS->dnid;

                if ($MAGNUS->agiconfig['use_dnid'] == 1 && strlen($MAGNUS->dnid) > 2 && $i == 0) {
                    $MAGNUS->destination = $MAGNUS->dnid;
                }

                $result_checkNumber = $MAGNUS->checkNumber($agi, $CalcAgi, $i, true);

                if ($result_checkNumber == 1) {

                    if ($MAGNUS->agiconfig['say_balance_before_call'] == 1) {
                        $MAGNUS->sayBalance($agi, $MAGNUS->credit);
                    }


                    // PERFORM THE CALL
                    $result_callperf = $CalcAgi->sendCall($agi, $MAGNUS->destination, $MAGNUS);

                    // INSERT CDR  & UPDATE SYSTEM
                    $CalcAgi->updateSystem($MAGNUS, $agi);

                    if (!$result_callperf) {
                        $MAGNUS->executePlayAudio("prepaid-dest-unreachable", $agi);
                        break;
                    }

                    if ($MAGNUS->agiconfig['say_balance_after_call'] == 1) {
                        $MAGNUS->sayBalance($agi, $MAGNUS->credit);
                    }
                } elseif ($result_checkNumber==0) {
                    $MAGNUS->hangup($agi, 1);
                    exit;
                } elseif ($count>=5) {
                    $MAGNUS->hangup($agi, 1);
                    exit;
                } elseif ($result_checkNumber==2){
                    $i = -1;
                }

                $MAGNUS->agiconfig['use_dnid'] = 0;
            } //END FOR
        }
        $MAGNUS->hangup($agi);
    }
}
