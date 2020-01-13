#!/usr/bin/php -q
<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusBilling. All rights reserved.
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
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGHUP, SIG_IGN);
}

error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));

require_once 'AGI.Class.php';
require_once 'AGI_AsteriskManager.Class.php';
require_once 'AuthenticateAgi.php';
require_once 'CalcAgi.php';
require_once 'CallbackAgi.php';
require_once 'DidAgi.php';
require_once 'IvrAgi.php';
require_once 'MassiveCall.php';
require_once 'PickupAgi.php';
require_once 'PortabilidadeAgi.php';
require_once 'PortalDeVozAgi.php';
require_once 'QueueAgi.php';
require_once 'SearchTariff.php';
require_once 'SipCallAgi.php';
require_once 'SipTransferAgi.php';
require_once 'StandardCallAgi.php';
require_once 'CustomerMenuAgi.php';
require_once 'Magnus.php';
require_once '/var/www/html/mbilling/protected/components/AsteriskAccess.php';

$agi     = new AGI();
$MAGNUS  = new Magnus();
$CalcAgi = new CalcAgi();
//$agi->verboseLevel = 1;

$agi->verbose("Start MBilling AGI", 6);

$MAGNUS->load_conf($agi, null, 0, 1);
$MAGNUS->get_agi_request_parameter($agi);

$MAGNUS->init();
$CalcAgi->init();

$MAGNUS->mode = 'standard';
if (file_exists('/root/log.conf')) {
    $resultFile = file_get_contents('/root/log.conf');
    if (preg_match('/' .
        $MAGNUS->accountcode . '|' .
        $MAGNUS->dnid . '|' .
        $MAGNUS->sip_account . '/',
        $resultFile)) {
        $agi->verboseLevel = 1;
    }
}
$agi->verbose("Start MBilling AGI", 6);
if ($MAGNUS->dnid == 'failed') {
    $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
    $MAGNUS->hangup($agi);
    exit;
}

if (substr($MAGNUS->dnid, 0, 2) == '*7') {
    PickupAgi::execute($agi, $MAGNUS);
}

//Hangup call that start with 1111, avoid fake call to Brasilian portability
if (substr($MAGNUS->dnid, 0, 4) == 1111) {
    $agi->execute((congestion), Congestion);
    $MAGNUS->hangup($agi);
}

if ($agi->get_variable("IDCALLBACK", true)) {
    $sql      = "SELECT callerid FROM pkg_sip WHERE id_user = " . $agi->get_variable("IDUSER", true) . " LIMIT 1";
    $modelSip = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
    if (isset($modelSip->callerid)) {
        $agi->set_callerid($modelSip->callerid);
    }
}

if ($agi->get_variable("CIDCALLBACK", true)) {

    CallbackAgi::chargeFistCall($agi, $MAGNUS, $CalcAgi, 0);
    $MAGNUS->agiconfig['answer']     = 1;
    $MAGNUS->agiconfig['cid_enable'] = 1;
    $MAGNUS->agiconfig['use_dnid']   = 0;
    $MAGNUS->agiconfig['number_try'] = 3;
}

if ($agi->get_variable("MEMBERNAME", true) || $agi->get_variable("QUEUEPOSITION", true)) {

    $sql              = "SELECT * FROM pkg_sip WHERE name = '$MAGNUS->dnid' OR (alias = '$MAGNUS->dnid' AND accountcode = '$MAGNUS->accountcode') LIMIT 1";
    $MAGNUS->modelSip = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
    if (isset($MAGNUS->modelSip->id) && strlen($MAGNUS->modelSip->name) > 3) {

        if ($MAGNUS->dnid == $MAGNUS->modelSip->alias) {
            $sql              = "SELECT alias FROM pkg_sip WHERE name = '" . $MAGNUS->sip_account . "' LIMIT 1";
            $modelSipCallerID = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
            if (strlen($modelSipCallerID->alias)) {
                $agi->set_callerid($modelSipCallerID->alias);
                $agi->set_variable("CALLERID(num)", $modelSipCallerID->alias);
                $MAGNUS->CallerID = $modelSipCallerID->alias;
            }
        }
        $MAGNUS->destination = $MAGNUS->dnid = $MAGNUS->modelSip->name;
        $MAGNUS->mode        = 'call-sip';
        $MAGNUS->voicemail   = $MAGNUS->modelSip->voicemail;
        $agi->verbose("CALL TO SIP", 15);
        $sipCallAgi = new SipCallAgi();
        $sipCallAgi->processCall($MAGNUS, $agi, $CalcAgi);
    } else {
        $agi->answer();
        $CalcAgi->init();
        QueueAgi::recIvrQueue($agi, $MAGNUS, $CalcAgi);
    }
}

if ($agi->get_variable("PHONENUMBER_ID", true) > 0 && $agi->get_variable("CAMPAIGN_ID", true) > 0) {
    $MAGNUS->mode = 'massive-call';
    MassiveCall::send($agi, $MAGNUS, $CalcAgi);
}

if ($agi->get_variable("SPY", true) == 1) {

    $channel = $agi->get_variable("CHANNELSPY", true);
    $spyType = $agi->get_variable("SPYTYPE", true);
    $agi->verbose('SPY CALL ' . $channel);
    $agi->execute("ChanSpy", $channel . ',' . $spyType);
    $agi->stream_file('prepaid-final', '#');
    $MAGNUS->hangup($agi);
    exit;
}

$didAgi = new DidAgi();
$didAgi->checkIfIsDidCall($agi, $MAGNUS, $CalcAgi);

if ($MAGNUS->mode == 'standard') {

    //get if the call have the second number
    if ($agi->get_variable("SECCALL", true)) {

        $agi->stream_file('prepaid-secondCall', '#');
        $MAGNUS->agiconfig['use_dnid'] = 1;
        $MAGNUS->destination           = $MAGNUS->extension           = $MAGNUS->dnid           = $agi->get_variable("SECCALL", true);

        $sql                 = "SELECT * FROM pkg_user WHERE id = " . $agi->get_variable("IDUSER", true) . " LIMIT 1";
        $MAGNUS->modelUser   = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        $MAGNUS->accountcode = isset($MAGNUS->modelUser->username) ? $MAGNUS->modelUser->username : null;
        $agi->verbose("CALL TO PSTN FROM CLIC TO CALL", 15);
        $standardCall = new StandardCallAgi();
        $standardCall->processCall($MAGNUS, $agi, $CalcAgi);
    } else {
        $sql              = "SELECT * FROM pkg_sip WHERE name = '$MAGNUS->dnid' OR (alias = '$MAGNUS->dnid' AND accountcode = '$MAGNUS->accountcode') LIMIT 1";
        $MAGNUS->modelSip = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        if (isset($MAGNUS->modelSip->id) && strlen($MAGNUS->modelSip->name) > 3) {

            if ($MAGNUS->dnid == $MAGNUS->modelSip->alias) {
                //find the caller alias set callerid
                $sql              = "SELECT alias FROM pkg_sip WHERE name = '" . $MAGNUS->sip_account . "' LIMIT 1";
                $modelSipCallerID = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
                if (strlen($modelSipCallerID->alias)) {
                    $agi->set_callerid($modelSipCallerID->alias);
                    $agi->set_variable("CALLERID(num)", $modelSipCallerID->alias);
                    $MAGNUS->CallerID = $modelSipCallerID->alias;
                }
            }

            $MAGNUS->destination = $MAGNUS->dnid = $MAGNUS->modelSip->name;
            $MAGNUS->mode        = 'call-sip';
            $MAGNUS->voicemail   = $MAGNUS->modelSip->voicemail;
            $agi->verbose("CALL TO SIP", 15);
            $sipCallAgi = new SipCallAgi();
            $sipCallAgi->processCall($MAGNUS, $agi, $CalcAgi);

        } else {
            $agi->verbose("CALL TO PSTN", 15);
            $standardCall = new StandardCallAgi();
            $standardCall->processCall($MAGNUS, $agi, $CalcAgi);
        }
    }
}
SipTransferAgi::billing($MAGNUS, $agi, $CalcAgi);
$MAGNUS->hangup($agi);

?>
