<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */
class CalcAgi
{
    public $lastcost                  = 0;
    public $lastbuycost               = 0;
    public $buycost                   = 0;
    public $answeredtime              = 0;
    public $real_answeredtime         = 0;
    public $dialstatus                = 0;
    public $usedratecard              = 0;
    public $usedtrunk                 = 0;
    public $freetimetocall_used       = 0;
    public $number_trunk              = 0;
    public $idCallCallBack            = 0;
    public $agent_bill                = 0;
    public $did_charge_of_id_user     = 0;
    public $did_charge_of_answer_time = 0;
    public $starttime                 = 0;
    public $sessiontime               = 0;
    public $real_sessiontime          = 0;
    public $terminatecauseid          = 0;
    public $sessionbill               = 0;
    public $sipiax                    = 0;
    public $id_campaign               = '';
    public $tariffObj                 = array();
    public $freetimetocall_left       = array();
    public $freecall                  = array();
    public $offerToApply              = array();
    public $didAgi                    = array();
    public $dialstatus_rev_list;
    public $id_prefix;

    public function __construct()
    {
        $this->dialstatus_rev_list = Magnus::getDialStatus_Revert_List();
    }

    public function init()
    {
        $this->number_trunk      = 0;
        $this->answeredtime      = 0;
        $this->real_answeredtime = 0;
        $this->dialstatus        = '';
        $this->usedratecard      = '';
        $this->usedtrunk         = '';
        $this->lastcost          = '';
        $this->lastbuycost       = '';
    }

    public function calculateAllTimeout(&$MAGNUS, $agi)
    {
        if (!is_array($this->tariffObj) || count($this->tariffObj) == 0) {
            return false;
        }

        for ($k = 0; $k < count($this->tariffObj); $k++) {
            $res_calcultimeout = $this->calculateTimeout($MAGNUS, $k, $agi);

            if (substr($res_calcultimeout, 0, 5) == 'ERROR' || $res_calcultimeout < 1) {
                return false;
            } else {
                return true;
            }
        }

        return true;
    }

    public function calculateTimeout(&$MAGNUS, $K = 0, $agi)
    {
        $rateinitial                   = $MAGNUS->round_precision(abs($this->tariffObj[$K]['rateinitial']));
        $initblock                     = $this->tariffObj[$K]['initblock'];
        $billingblock                  = $this->tariffObj[$K]['billingblock'];
        $connectcharge                 = $MAGNUS->round_precision(abs($this->tariffObj[$K]['connectcharge']));
        $id_offer                      = $package_offer                      = $this->tariffObj[$K]['package_offer'];
        $id_rate                       = $this->tariffObj[$K]['id_rate'];
        $initial_credit                = $credit                = $MAGNUS->credit;
        $this->freetimetocall_left[$K] = 0;
        $this->freecall[$K]            = false;
        $this->offerToApply[$K]        = null;

        if ($id_offer == 1 && $MAGNUS->id_offer > 0 && $K == 0) {
            $sql = "SELECT * FROM pkg_offer_use WHERE id_offer = $MAGNUS->id_offer
                                AND id_user = $MAGNUS->id_user LIMIT 1";
            $modelOfferUse = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

            if (isset($modelOfferUse->id)) {
                $sql        = "SELECT * FROM pkg_offer WHERE id = $MAGNUS->id_offer LIMIT 1";
                $modelOffer = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

                $package_selected = false;
                $freetimetocall   = $modelOffer->freetimetocall;
                $packagetype      = $modelOffer->packagetype;
                $billingtype      = $modelOffer->billingtype;
                $startday         = date('d', strtotime($modelOfferUse->reservationdate));
                $id_offer         = $modelOffer->id;
                switch ($packagetype) {
                    case 0:
                        $agi->verbose("offer Unlimited calls");
                        $this->freecall[0]      = true;
                        $package_selected       = true;
                        $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Unlimited calls", "type" => $packagetype);
                        break;
                    case 1:

                        if ($freetimetocall > 0) {
                            $agi->verbose('FREE CALLS');
                            $number_calls_used = $MAGNUS->freeCallUsed($agi, $MAGNUS->id_user, $id_offer, $billingtype, $startday);

                            if ($number_calls_used < $freetimetocall) {
                                $this->freecall[$K]     = true;
                                $package_selected       = true;
                                $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Number of Free calls", "type" => $packagetype);
                                $agi->verbose(print_r($this->offerToApply[$K], true), 6);
                            }
                        }
                        break;
                    case 2:
                        if ($freetimetocall > 0) {
                            $this->freetimetocall_used     = $MAGNUS->packageUsedSeconds($agi, $MAGNUS->id_user, $id_offer, $billingtype, $startday);
                            $this->freetimetocall_left[$K] = $freetimetocall - $this->freetimetocall_used;

                            if ($this->freetimetocall_left[$K] < 0) {
                                $this->freetimetocall_left[$K] = 0;
                            }

                            if ($this->freetimetocall_left[$K] > 0) {
                                $package_selected       = true;
                                $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Free minutes", "type" => $packagetype);
                                $agi->verbose(print_r($this->offerToApply[$K], true), 6);
                            }
                        }
                        break;
                }
            }
        }

        $credit -= $connectcharge;
        $this->tariffObj[$K]['timeout']                     = 0;
        $this->tariffObj[$K]['timeout_without_rules']       = 0;
        $this->tariffObj[$K]['freetime_include_in_timeout'] = $this->freetimetocall_left[$K];
        $agi->verbose("Credit $credit", 20);
        if ($credit < 0 && !$this->freecall[$K] && $this->freetimetocall_left[$K] <= 0) {
            return "ERROR CT1";
            /*NO  CREDIT TO CALL */
        }

        $TIMEOUT              = 0;
        $answeredtime_1st_leg = 0;
        if ($rateinitial <= 0) /*Se o preÃ§o for 0, entao retornar o timeout em 3600 s*/ {
            $this->tariffObj[$K]['timeout']               = 3600;
            $this->tariffObj[$K]['timeout_without_rules'] = 3600;
            $TIMEOUT                                      = 3600;
            return $TIMEOUT;
        }

        if ($this->freecall[$K]) /*usado para planos gratis*/ {
            $this->tariffObj[$K]['timeout']                     = 3600;
            $TIMEOUT                                            = 3600;
            $this->tariffObj[$K]['timeout_without_rules']       = 3600;
            $this->tariffObj[$K]['freetime_include_in_timeout'] = 3600;
            return $TIMEOUT;
        }
        if ($credit < 0 && $this->freetimetocall_left[$K] > 0) {
            $this->tariffObj[$K]['timeout']               = $this->freetimetocall_left[$K];
            $TIMEOUT                                      = $this->freetimetocall_left[$K];
            $this->tariffObj[$K]['timeout_without_rules'] = $this->freetimetocall_left[$K];
            return $TIMEOUT;
        }

        if ($MAGNUS->mode == 'callback') {
            $credit -= $calling_party_connectcharge;
            $credit -= $calling_party_disconnectcharge;
            $num_min              = $credit / ($rateinitial + $calling_party_rateinitial);
            $answeredtime_1st_leg = intval($agi->get_variable('ANSWEREDTIME', true));
        } else {
            $num_min = $credit / $rateinitial; /*numero de minutos*/
        }

        $num_sec = intval($num_min * 60) - $answeredtime_1st_leg; /*numero de segundos - o tempo que gastou para completar*/
        if ($billingblock > 0) {
            $mod_sec = $num_sec % $billingblock;
            $num_sec = $num_sec - $mod_sec;
        }
        $TIMEOUT = $num_sec;

        /*Call time to speak without rate rules... idiot rules*/
        $num_min_WR                                   = $initial_credit / $rateinitial;
        $num_sec_WR                                   = intval($num_min_WR * 60);
        $this->tariffObj[$K]['timeout_without_rules'] = $num_sec_WR + $this->freetimetocall_left[$K];
        $this->tariffObj[$K]['timeout']               = $TIMEOUT + $this->freetimetocall_left[$K];

        return $TIMEOUT + $this->freetimetocall_left[$K];
    }

    public function calculateCost(&$MAGNUS, $callduration, $K = 0, $agi)
    {
        $this->usedratecard    = $this->usedratecard < 0 ? 0 : $this->usedratecard;
        $K                     = $this->usedratecard;
        $buyrate               = $MAGNUS->round_precision(abs($this->tariffObj[$K]['buyrate']));
        $buyrateinitblock      = $this->tariffObj[$K]['buyrateinitblock'] > 0 ? $this->tariffObj[$K]['buyrateinitblock'] : 1;
        $buyrateincrement      = $this->tariffObj[$K]['buyrateincrement'] > 0 ? $this->tariffObj[$K]['buyrateincrement'] : 1;
        $rateinitial           = $MAGNUS->round_precision(abs($this->tariffObj[$K]['rateinitial']));
        $initblock             = $this->tariffObj[$K]['initblock'];
        $billingblock          = $this->tariffObj[$K]['billingblock'];
        $connectcharge         = $MAGNUS->round_precision(abs($this->tariffObj[$K]['connectcharge']));
        $disconnectcharge      = $MAGNUS->round_precision(abs($this->tariffObj[$K]['disconnectcharge']));
        $additional_grace_time = $this->tariffObj[$K]['additional_grace'];

        $this->freetimetocall_used = 0;

        $agi->verbose("CALCULCOST: K=$K - CALLDURATION:$callduration - freetimetocall_used=$this->freetimetocall_used", 10);
        $cost = 0;
        $cost += $connectcharge;

        $buyratecallduration = $callduration;

        $buyratecost = 0;
        if ($buyratecallduration < $buyrateinitblock) {
            $buyratecallduration = $buyrateinitblock;
        }

        if (($buyrateincrement > 0) && ($buyratecallduration > $buyrateinitblock)) {
            $mod_sec = $buyratecallduration % $buyrateincrement;
            /* 12 = 30 % 18*/
            if ($mod_sec > 0) {
                $buyratecallduration += ($buyrateincrement - $mod_sec);
            }

            /* 30 += 18 - 12*/
        }
        $buyratecost += ($buyratecallduration / 60) * $buyrate;
        if ($this->freecall[$K]) {
            $this->lastcost    = 0;
            $this->lastbuycost = $buyratecost;
            $agi->verbose("CALCUL COST: K=$K - BUYCOST: $buyratecost - SELLING COST: $cost", 10);
            return;
        }
        if ($callduration < $initblock) {
            $callduration = $initblock;
        }

        if (($billingblock > 0) && ($callduration > $initblock)) {
            $mod_sec = $callduration % $billingblock;
            if ($mod_sec > 0) {
                $callduration += ($billingblock - $mod_sec);
            }
        }
        if ($this->freetimetocall_left[$K] >= $callduration) {
            $this->freetimetocall_used = $callduration;
            $callduration              = 0;
        }
        $cost += ($callduration / 60) * $rateinitial;

        $agi->verbose("CALCULCOST: 1. COST: \$cost = ($callduration/60) * $rateinitial ", 10);

        $this->lastcost    = $MAGNUS->round_precision($cost);
        $this->lastbuycost = $buyratecost;
        $agi->verbose("CALCULCOST: K=$K - BUYCOST:$buyratecost - SELLING COST:$this->lastcost", 10);

        $agi->verbose('$this->lastbuycost = ' . $this->lastbuycost, 10);
        $agi->verbose('$this->lastcost = ' . $this->lastcost, 10);
    }

    public function array_csort()
    {
        $args      = func_get_args();
        $marray    = array_shift($args);
        $i         = 0;
        $msortline = "return(array_multisort(";
        foreach ($args as $arg) {
            $i++;
            if (is_string($arg)) {
                foreach ($marray as $row) {
                    $sortarr[$i][] = $row[$arg];
                }
            } else {
                $sortarr[$i] = $arg;
            }
            $msortline .= "\$sortarr[" . $i . "],";
        }
        $msortline .= "\$marray));";
        eval($msortline);
        return $marray;
    }

    public function updateSystem(&$MAGNUS, &$agi, $doibill = 1, $didcall = 0, $callback = 0)
    {
        $agi->verbose('Update System', 6);
        $this->usedratecard    = $this->usedratecard < 0 ? 0 : $this->usedratecard;
        $K                     = $this->usedratecard;
        $id_offer              = $MAGNUS->id_offer;
        $additional_grace_time = $this->tariffObj[$K]['additional_grace'];
        $sessiontime           = $this->answeredtime;
        $dialstatus            = $this->dialstatus;

        if ($sessiontime > 0) {
            $this->freetimetocall_used = 0;
            //adiciona o tempo adicional
            if (substr($additional_grace_time, -1) == "%") {
                $additional_grace_time = str_replace("%", "", $additional_grace_time);
                $additional_grace_time = $additional_grace_time / 100;
                $additional_grace_time = str_replace("0.", "1.", $additional_grace_time);
                $sessiontime           = $sessiontime * $additional_grace_time;
            } else {
                if ($sessiontime > 0) {
                    $sessiontime = $sessiontime + $additional_grace_time;
                }
            }

            if (($id_offer != -1) && ($this->offerToApply[$K] != null)) {
                $id_offer = $this->offerToApply[$K]["id"];

                $this->calculateCost($MAGNUS, $sessiontime, 0, $agi);

                switch ($this->offerToApply[$K]["type"]) {
                    /*Unlimited*/
                    case 0:
                        $this->freetimetocall_used = $sessiontime;
                        break;
                    /*free calls*/
                    case 1:
                        $this->freetimetocall_used = $sessiontime;
                        break;
                    /*free minutes*/
                    case 2:
                        if ($sessiontime > '60') {
                            $restominutos   = $sessiontime % 60;
                            $calculaminutos = ($sessiontime - $restominutos) / 60;
                            if ($restominutos > '0') {
                                $calculaminutos++;
                            }

                            $sessiontime = $calculaminutos * 60;
                        } elseif ($sessiontime < '1') {
                            $sessiontime = 0;
                        } else {
                            $sessiontime = 60;
                        }

                        $this->freetimetocall_used = $sessiontime;

                        break;
                }

                /* calculcost could have change the duration of the call*/
                $sessiontime = $this->answeredtime;
                /* add grace time*/
                $fields = "id_user, id_offer, used_secondes";
                $values = "$MAGNUS->id_user, $id_offer, '$this->freetimetocall_used'";
                $sql    = "INSERT INTO pkg_offer_cdr ($fields) VALUES ($values)";
                $agi->exec($sql);
            } else {
                $this->calculateCost($MAGNUS, $sessiontime, 0, $agi);
            }
        } else {
            $sessiontime = 0;
        }
        $agi->verbose('Sessiontime' . $sessiontime, 10);

        if (($id_offer != -1) && ($this->offerToApply[$K] != null) && $sessiontime > 0) {
            $sessiontime = $this->freetimetocall_used;
        }

        $id_prefix = $this->tariffObj[0]['id_prefix'];
        $id_plan   = $this->tariffObj[$K]['id_plan'];
        $buycost   = 0;

        if ($doibill == 0 || $sessiontime <= $this->tariffObj[$K]['minimal_time_charge']) {
            $cost = 0;
        } else {
            $cost = $this->lastcost;
        }

        if ($doibill == 0 || $sessiontime <= $this->tariffObj[$K]['minimal_time_buy']) {
            $buycost = 0;
        } else {
            $buycost = abs($this->lastbuycost);
        }

        $buyrateapply = $this->tariffObj[$K]['buyrate'];
        $rateapply    = $this->tariffObj[$K]['rateinitial'];
        $agi->verbose("CALL: used tariff K=$K - (sessiontime=$sessiontime :: dialstatus=$dialstatus)", 10);

        if ($didcall) {
            $calltype = 2;
        } elseif ($callback == 2) {
            $calltype = 7;
        } elseif ($callback) {
            $calltype = 4;
        } else {
            $calltype = 0;
        }

        if (strlen($this->dialstatus_rev_list[$dialstatus]) > 0) {
            $terminatecauseid = $this->dialstatus_rev_list[$dialstatus];
        } else {
            $terminatecauseid = 0;
        }

        if ($callback == 2) //muda o termino para transferencia
        {
            $terminatecauseid = 1;
        }

        if ($calltype == '4' && $sessiontime > '0') {
            $terminatecauseid = '1';
        }

        /*recondeo call*/
        if ($MAGNUS->config["global"]['bloc_time_call'] == 1 && $sessiontime > 0) {
            $initblock    = ($this->tariffObj[$K]['initblock'] < 1) ? 1 : $this->tariffObj[$K]['initblock'];
            $billingblock = ($this->tariffObj[$K]['billingblock'] < 1) ? 1 : $this->tariffObj[$K]['billingblock'];

            if ($sessiontime > $initblock) {
                $restominutos   = $sessiontime % $billingblock;
                $calculaminutos = ($sessiontime - $restominutos) / $billingblock;
                if ($restominutos > '0') {
                    $calculaminutos++;
                }

                $sessiontime = $calculaminutos * $billingblock;

            } elseif ($sessiontime < '1') {
                $sessiontime = 0;
            } else {
                $sessiontime = $initblock;
            }
        }

        $calldestinationPortabilidade = $MAGNUS->destination;
        if ($MAGNUS->portabilidade == 1) {
            if (substr($MAGNUS->destination, 0, 4) == '1111') {
                $MAGNUS->destination = str_replace(substr($MAGNUS->destination, 0, 7), "", $MAGNUS->destination);
            }

        }
        $cost += $MAGNUS->callingcardConnection;

        $agi->verbose($terminatecauseid . ' ' . $cost . '+' . $MAGNUS->round_precision(abs($MAGNUS->callingcardConnection)) . ' = ' . $cost, 25);
        $costCdr = $cost;
        if ($sessiontime > 0) {

            CallbackAgi::chargeFistCall($agi, $MAGNUS, $this, $sessiontime);
            /*Update the global credit */
            $MAGNUS->credit = $MAGNUS->credit + $cost;
            /*CALULATION CUSTO AND SELL RESELLER */

            if (!is_null($MAGNUS->id_agent) && $MAGNUS->id_agent > 1) {
                $agi->verbose('$MAGNUS->id_agent' . $MAGNUS->id_agent . ' ' . $MAGNUS->destination . ' - ' .
                    $calldestinationPortabilidade . ' - ' . $this->real_answeredtime . ' - ' . $cost, 1);
                $cost = $this->agent_bill = $this->updateSystemAgent($agi, $MAGNUS, $calldestinationPortabilidade, $MAGNUS->round_precision(abs($cost)), $sessiontime);
            } else {

                $sql = "UPDATE pkg_user SET credit = credit - " . $MAGNUS->round_precision(abs($costCdr)) . "
                        WHERE id=" . $MAGNUS->modelUser->id . " LIMIT 1 ";
                $agi->exec($sql);

                $agi->verbose("Update credit username $MAGNUS->username, " . $MAGNUS->round_precision(abs($cost)), 6);
            }
            $sql = "UPDATE pkg_trunk SET call_answered = call_answered +1, secondusedreal = secondusedreal + $sessiontime
                        WHERE id=$this->usedtrunk LIMIT 1 ;
                    UPDATE pkg_provider SET credit = credit - $buycost WHERE id=" . $this->tariffObj[$K]['id_provider'] . " LIMIT 1;";
            $agi->exec($sql);

        }
        $this->callShop($agi, $MAGNUS, $sessiontime, $id_prefix, $cost);

        if ($this->did_charge_of_id_user > 0) {

            $agi->verbose('Did_charge_of_id_user = ' . $this->did_charge_of_id_user, 15);

            $didDuration = time() - $this->did_charge_of_answer_time;

            $agi->verbose('DidDuration = ' . $didDuration);
            $did_sell_price = $this->didAgi->selling_rate_1;

            $did_sell_price = $MAGNUS->roudRatePrice($didDuration, $did_sell_price,
                $this->didAgi->initblock,
                $this->didAgi->increment);

            $agi->verbose('did_sell_price ' . $did_sell_price);

            $sql = "UPDATE pkg_user SET credit = credit - $MAGNUS->round_precision(abs($did_sell_price))
                        WHERE id=$this->did_charge_of_id_user LIMIT 1 ";
            $agi->exec($sql);

            $agi->verbose('Add CDR the DID cost to CallerID. Cost =' . $did_sell_price . ', duration' . $didDuration);

            $MAGNUS->id_user       = $this->did_charge_of_id_user;
            $MAGNUS->calledstation = $this->didAgi->did;
            $MAGNUS->id_plan       = $MAGNUS->id_plan;
            $MAGNUS->id_trunk      = null;

            $this->starttime        = date("Y-m-d H:i:s", $this->did_charge_of_answer_time);
            $this->sessiontime      = $didDuration;
            $this->real_sessiontime = $didDuration;
            $this->terminatecauseid = $terminatecauseid;
            $this->sessionbill      = $did_sell_price;
            $this->sipiax           = 3;
            $this->buycost          = 0;
            $this->id_prefix        = $id_prefix;
            $this->saveCDR($agi, $MAGNUS);

        }

        if ($terminatecauseid == 1) {
            if ($agi->get_variable("IDCALLBACK", true)) {
                $sql = "UPDATE pkg_callback SET last_attempt_time = '" . date('Y-m-d H:i:s') . "', status = 3
                            WHERE id = '" . $agi->get_variable("IDCALLBACK", true) . "' LIMIT 1";
                $agi->exec($sql);
            }
        }
        $MAGNUS->id_trunk       = $this->usedtrunk;
        $this->starttime        = date("Y-m-d H:i:s", time() - $this->real_answeredtime);
        $this->sessiontime      = intval($sessiontime);
        $this->real_sessiontime = intval($this->real_answeredtime);
        $this->terminatecauseid = $terminatecauseid;
        $this->sessionbill      = $costCdr;
        $this->sipiax           = $calltype;
        $this->buycost          = $buycost;
        $this->id_prefix        = $id_prefix;
        $this->saveCDR($agi, $MAGNUS);

    }

    public function updateSystemAgent($agi, $MAGNUS, $calledstation, $cost, $sessiontime)
    {
        $sql = "SELECT rateinitial, initblock, billingblock, minimal_time_charge " .
            "FROM pkg_plan " .
            "LEFT JOIN pkg_rate_agent ON pkg_rate_agent.id_plan=pkg_plan.id " .
            "LEFT JOIN pkg_prefix ON pkg_rate_agent.id_prefix=pkg_prefix.id " .
            "WHERE prefix = SUBSTRING($calledstation,1,length(prefix)) and " .
            "pkg_plan.id= $MAGNUS->id_plan_agent ORDER BY LENGTH(prefix) DESC LIMIT 3";
        $modelRateAgent = $agi->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        if (!isset($modelRateAgent[0]['rateinitial'])) {
            $agi->verbose('NOT FOUND AGENT TARRIF, USE AGENT COST PRICE');
            $cost_customer = $cost;
        } else {
            $agi->verbose('Found agent sell price ' . print_r($modelRateAgent[0], true) . '-  ' . $sessiontime, 25);
            $cost_customer = $MAGNUS->roudRatePrice($sessiontime, $modelRateAgent[0]['rateinitial'],
                $modelRateAgent[0]['initblock'], $modelRateAgent[0]['billingblock']);
            $agi->verbose('$cost_customer=' . $cost_customer);
        }

        if ($sessiontime < $modelRateAgent[0]['minimal_time_charge']) {
            $agi->verbose("Tempo meno que o tempo minimo para", 15);
            $cost_customer = 0;
        }

        $agi->verbose("Sellratecost_customer: $cost_customer", 15);
        $sql = "UPDATE pkg_user SET credit = credit - $cost WHERE id= $MAGNUS->id_agent LIMIT 1";
        $agi->exec($sql);

        $agi->verbose("Update credit Agent $MAGNUS->agentUsername, " . $cost, 15);

        $sql = "UPDATE pkg_user SET credit = credit - $cost_customer WHERE id= $MAGNUS->id_user LIMIT 1";
        $agi->exec($sql);

        $agi->verbose("Update credit customer Agent $MAGNUS->username, " . $MAGNUS->round_precision(abs($cost_customer)), 6);

        return $cost_customer;
    }

    public function sendCall($agi, $destination, &$MAGNUS, $typecall = 0)
    {
        $max_long = 2147483647;

        if (substr("$destination", 0, 4) == 1111) /*Retira o techprefix de numeros portados*/ {
            $destination = str_replace(substr($destination, 0, 7), "", $destination);
        }
        $old_destination = $destination;

        for ($k = 0; $k < count($this->tariffObj); $k++) {
            $destination = $old_destination;
            if ($this->tariffObj[$k]['id_trunk'] != '-1') {
                $this->usedtrunk   = $this->tariffObj[$k]['id_trunk'];
                $usetrunk_failover = 1;
            } else {
                return false;
            }
            //se nao tem tronco retornar erro*/

            $prefix         = $this->tariffObj[$k]['rc_trunkprefix'];
            $tech           = $this->tariffObj[$k]['rc_providertech'];
            $ipaddress      = $this->tariffObj[$k]['rc_providerip'];
            $removeprefix   = $this->tariffObj[$k]['rc_removeprefix'];
            $timeout        = $this->tariffObj[0]['timeout'];
            $failover_trunk = $this->tariffObj[$k]['rt_failover_trunk'];
            $addparameter   = $this->tariffObj[$k]['rt_addparameter_trunk'];
            $inuse          = $this->tariffObj[$k]['inuse'];
            $maxuse         = $this->tariffObj[$k]['maxuse'];
            $allow_error    = $this->tariffObj[$k]['allow_error'];

            if ($typecall == 1) {
                $timeout = 3600;
            }

            $outcid = 0;

            $this->sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
                , $addparameter, $inuse, $maxuse, $allow_error);

            $loop_failover = 0;

            while ($loop_failover <= $MAGNUS->agiconfig['failover_recursive_limit']
                && is_numeric($failover_trunk) && $failover_trunk >= 0
                && ($this->dialstatus == "CHANUNAVAIL" || $this->dialstatus == "CONGESTION")) {
                $loop_failover++;
                $this->real_answeredtime = $this->answeredtime = 0;
                $this->usedtrunk         = $failover_trunk;
                $agi->verbose("K=$k -> ANSWEREDTIME=" . $this->answeredtime . "-DIALSTATUS=" . $this->dialstatus, 10);
                $destination = $old_destination;

                $sql = "SELECT * FROM pkg_trunk JOIN pkg_provider ON id_provider = pkg_provider.id
                                    WHERE pkg_trunk.id = $failover_trunk LIMIT 1 ";
                $modelTrunk = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

                if (isset($modelTrunk->id)) {
                    $prefix              = $modelTrunk->trunkprefix;
                    $tech                = $modelTrunk->providertech;
                    $ipaddress           = $modelTrunk->providerip;
                    $removeprefix        = $modelTrunk->removeprefix;
                    $next_failover_trunk = $modelTrunk->failover_trunk;
                    $timeout             = $this->tariffObj[0]['timeout'];
                    $status              = $this->tariffObj[$k]['status']              = $modelTrunk->status;
                    $inuse               = $this->tariffObj[$k]['inuse']               = $modelTrunk->inuse;
                    $maxuse              = $this->tariffObj[$k]['maxuse']              = $modelTrunk->maxuse;
                    $allow_error         = $this->tariffObj[$k]['allow_error']         = $modelTrunk->allow_error;
                    $addparameter        = $this->tariffObj[$k]['rt_addparameter_trunk']        = $modelTrunk->addparameter;

                    $this->tariffObj[$k]['credit_control'] = $modelTrunk->credit_control;
                    $this->tariffObj[$k]['credit']         = $modelTrunk->credit;

                    if ($status == 0) {
                        $agi->verbose("Failover trunk cannot be used because it is disabled", 3);
                        break;
                    }

                    $this->sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
                        , $addparameter, $inuse, $maxuse, $allow_error);
                    $agi->verbose("FAILOVER app_callingcard: Dialing '$dialstr' with timeout of '$timeout'.", 15);

                    $agi->verbose("[FAILOVER K=$k]:[ANSTIME=" . $this->answeredtime . "-DIALSTATUS=" . $this->dialstatus, 15);
                }
                /* IF THE FAILOVER TRUNK IS SAME AS THE ACTUAL TRUNK WE BREAK */
                if ($next_failover_trunk == $failover_trunk) {
                    break;
                } else {
                    $failover_trunk = $next_failover_trunk;
                }

            }

            if (($this->dialstatus == "CANCEL")) {
                return true;
            }

            if ($this->tariffObj[$k]['status'] != 1) /*Change dialstatus of the trunk for send for LCR/LCD prefix*/ {
                if ($MAGNUS->agiconfig['failover_lc_prefix']) {
                    continue;
                }

            }

            /* END FOR LOOP FAILOVER */
            /*# Ooh, something actually happened! */
            if ($this->dialstatus == "BUSY") {
                $this->real_answeredtime = $this->answeredtime = 0;
                if ($MAGNUS->play_audio == 1) {
                    $agi->stream_file('prepaid-isbusy', '#');
                } else {
                    $agi->execute((busy), busy);
                }

            } elseif ($this->dialstatus == "NOANSWER") {
                $this->real_answeredtime = $this->answeredtime = 0;
                if ($MAGNUS->play_audio == 1) {
                    $agi->stream_file('prepaid-noanswer', '#');
                } else {
                    $agi->execute((congestion), Congestion);
                }

            } elseif ($this->dialstatus == "CANCEL") {
                $this->real_answeredtime = $this->answeredtime = 0;
            } elseif (($this->dialstatus == "CHANUNAVAIL") || ($this->dialstatus == "CONGESTION")) {
                $this->real_answeredtime = $this->answeredtime = 0;
                /* Check if we will failover for LCR/LCD prefix - better false for an exact billing on resell */
                if ($MAGNUS->agiconfig['failover_lc_prefix']) {
                    $agi->verbose("Call send for backup trunk -> ERROR => $this->dialstatus", 6);
                    continue;
                }
                return false;
            }

            $this->usedratecard = $k;
            $agi->verbose("USED TARIFF=" . $this->usedratecard, 10);
            return true;
        }
        /* End for */
        $this->usedratecard = $k - $loop_failover;
        $agi->verbose("USEDRATECARD - FAIL =" . $this->usedratecard, 10);
        return false;
    }

    public function sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
        , $addparameter, $inuse, $maxuse, $allow_error) {
        if (strncmp($destination, $removeprefix, strlen($removeprefix)) == 0) {
            $destination = substr($destination, strlen($removeprefix));
        }

        if ($MAGNUS->agiconfig['switchdialcommand'] == 1 || $tech == 'Local') {
            $dialstr = "$tech/$prefix$destination@$ipaddress";
        } else {
            $dialstr = "$tech/$ipaddress/$prefix$destination";
        }

        if (strlen($addparameter) > 0) {
            $dialstr = "$tech/$ipaddress/$prefix$destination" . $addparameter;
        }

        if ($this->tariffObj[$k]['credit_control'] == 1 && $this->tariffObj[$k]['credit'] <= 0) {
            $agi->verbose("Provider not have credit", 3);
            $this->tariffObj[$k]['status'] = 0;
        }

        if ($this->tariffObj[$k]['status'] == 1) {
            $dialedpeername       = $agi->get_variable("SIPTRANSFER");
            $this->dialedpeername = $dialedpeername['data'];

            if ($this->dialedpeername == 'yes') {
                $agi->execute("hangup request $this->channel");
                $MAGNUS->hangup($agi);
            }
            $sql = "UPDATE pkg_trunk SET  call_total = call_total + 1 WHERE id=" . $this->usedtrunk . " LIMIT 1";
            $agi->exec($sql);

            $MAGNUS->startRecordCall($agi);
            try {
                $dial_param = $MAGNUS->agiconfig['dialcommand_param'];
                if (!isset($dial_param) || trim($dial_param) === '')
                {
                    $dial_param = ',';
                }
                $dial_param = $dial_param . 'U(trunk_answer_handler)';
                $MAGNUS->run_dial($agi, $dialstr, $dial_param
                    , $this->tariffObj[$k]['rc_directmedia'], $timeout);
            } catch (Exception $e) {
                //
            }
            if ($MAGNUS->demo == true) {
                $answeredtime            = date('s');
                $this->real_answeredtime = $answeredtime;
                $dialstatus              = 'ANSWER';
                $this->dialstatus        = 'ANSWER';
            } else {

                $answeredtime            = $agi->get_variable("trunkanswertime");
                if (!isset($answeredtime['data']) || trim($answeredtime['data']) === '') {
                    //support for installations that do not have the trunk_answer_handler context in the dialplan
                    $answeredtime = $agi->get_variable("ANSWEREDTIME");
                } else {
                    $answeredtime['data'] = time() - $answeredtime['data'];
                }

                $this->real_answeredtime = $this->answeredtime = $answeredtime['data'];
                $dialstatus              = $agi->get_variable("DIALSTATUS");
                $this->dialstatus        = $dialstatus['data'];
            }

        }
        //if the trunk is inactive
        else {
            $agi->verbose('THE TRUNK ' . $ipaddress . ' IS INACTIVE, SEND TO NEXT TRUNK');
            $this->answeredtime = $answeredtime['data'] = 0;
            $this->dialstatus   = 'CONGESTION';
        }

        $MAGNUS->stopRecordCall($agi);

        if ($allow_error == 1 && $this->dialstatus == "BUSY") {
            $this->dialstatus = "CONGESTION";
        }

    }

    public function callShop($agi, $MAGNUS, $sessiontime, $id_prefix, $cost)
    {

        if ($MAGNUS->callshop == 1) {
            $sql = "UPDATE pkg_sip SET status = 2 WHERE name = '$MAGNUS->sip_account' ";
            $agi->exec($sql);

            if ($sessiontime > 0) {
                $sql = "SELECT * FROM pkg_rate_callshop WHERE dialprefix = SUBSTRING($MAGNUS->destination,1,length(dialprefix))
                                AND id_user= $MAGNUS->id_user   ORDER BY LENGTH(dialprefix) DESC LIMIT 1";
                $modelReteCallshop = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

                if (!isset($modelReteCallshop->id)) {
                    $agi->verbose('Not found CallShop rate => ' . $MAGNUS->destination . ' ' . $MAGNUS->id_user);
                    return;
                }
                $buyrate   = $modelReteCallshop->buyrate > 0 ? $modelReteCallshop->buyrate : $cost;
                $initblock = $modelReteCallshop->minimo;
                $increment = $modelReteCallshop->block;

                $sellratecost_callshop = $MAGNUS->calculation_price($buyrate, $sessiontime, $initblock, $increment);

                if ($sessiontime < $modelReteCallshop->minimal_time_charge) {
                    $agi->verbose("Minimal time to charge. Cost 0.0000", 15);
                    $sellratecost_callshop = 0;
                }

                //save in CDRCALLSHOP

                $fields = "sessionid, id_user, status, price, buycost, calledstation, destination,price_min, cabina, sessiontime";
                $values = "'$MAGNUS->channel', $MAGNUS->id_user, 0, $sellratecost_callshop, '$cost', '$MAGNUS->destination',
                                '" . $modelReteCallshop->destination . "', '$buyrate', '$MAGNUS->sip_account', $sessiontime";
                $sql = "INSERT INTO pkg_callshop ($fields) VALUES ($values)";
                $agi->exec($sql);
            }
        }
        return;
    }

    public function saveCDR($agi, $MAGNUS, $returnID = false)
    {

        /*
        $MAGNUS->uniqueid = ;
        $MAGNUS->id_user = ;
        $MAGNUS->destination = ;
        $MAGNUS->id_plan = ;
        $MAGNUS->id_trunk = ;
        $MAGNUS->sip_account = ;
        $CalcAgi->starttime        = date("Y-m-d H:i:s", $startCall);
        $CalcAgi->sessiontime      = $answeredtime;
        $CalcAgi->real_sessiontime = $answeredtime;
        $CalcAgi->terminatecauseid = $terminatecauseid;
        $CalcAgi->sessionbill      = $cost;
        $CalcAgi->sipiax           = $sipiax;
        $CalcAgi->buycost          = 0;
        $CalcAgi->id_prefix        = null;
        $CalcAgi->saveCDR($agi, $MAGNUS);
         */

        if ($this->sipiax == 3) {
            //if call is a DID, check is sipaccount is valid, else, set the callerid
            $sql             = "SELECT name FROM pkg_sip WHERE name  = '" . $MAGNUS->sip_account . "' LIMIT 1";
            $modelSipaccount = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
            if (!isset($modelSipaccount->name)) {
                $MAGNUS->sip_account = $MAGNUS->CallerID;
            }
        }

        if ($this->terminatecauseid == 1) {

            $fields = "uniqueid,id_user,calledstation,id_plan,callerid,src,
                        starttime,sessiontime,real_sessiontime, terminatecauseid,sessionbill,
                        sipiax,buycost";

            $values = "'$MAGNUS->uniqueid', $MAGNUS->id_user, '$MAGNUS->destination', $MAGNUS->id_plan, '$MAGNUS->CallerID',
                        '$MAGNUS->sip_account',
                        '$this->starttime', '$this->sessiontime',
                        '$this->real_sessiontime', '$this->terminatecauseid', '$this->sessionbill',
                        '$this->sipiax','$this->buycost'";

            if (is_numeric($MAGNUS->id_trunk)) {
                $fields .= ', id_trunk';
                $values .= ", $MAGNUS->id_trunk";
            }
            if (is_numeric($this->id_prefix)) {
                $fields .= ', id_prefix';
                $values .= ", $this->id_prefix";
            }
            if ($this->id_campaign > 0) {
                $fields .= ', id_campaign';
                $values .= ", $this->id_campaign";
            }
            if ($this->agent_bill > 0) {
                $fields .= ', agent_bill';
                $values .= ", $this->agent_bill";
            }
            $sql = "INSERT INTO pkg_cdr ($fields) VALUES ($values) ";
            $agi->exec($sql);

            if ($returnID == true) {
                return $agi->lastInsertId();
            }
        } else {

            if (file_exists(dirname(__FILE__) . '/CallCache.php')) {
                include 'CallCache.php';
            } else {
                $fields = "uniqueid,id_user,calledstation,id_plan,id_trunk,callerid,src,
                        starttime, terminatecauseid,sipiax,id_prefix";

                $values = "'$MAGNUS->uniqueid', '$MAGNUS->id_user','$MAGNUS->destination','$MAGNUS->id_plan',
                        '$MAGNUS->id_trunk','$MAGNUS->CallerID', '$MAGNUS->sip_account',
                        '$this->starttime', '$this->terminatecauseid','$this->sipiax','$this->id_prefix'";

                $sql = "INSERT INTO pkg_cdr_failed ($fields) VALUES ($values) ";
                $agi->exec($sql);
            }
        }
    }
}
