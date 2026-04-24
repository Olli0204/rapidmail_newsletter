<?php
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiClientException;
use Rapidmail\ApiClient\Exception\IncompatiblePlatformException;
use JTL\Shop;

require_once __DIR__ . '/../vendor/autoload.php';

class rapidmail_integration{
    private $username;
    private $password;
    private $listIds;

    function __construct($username, $password, $listIds){
        $this->username = $username;
        $this->password = $password;
        $this->listIds = $listIds;
    }

    function add_to_synchronizeTable($kEmpfaenger, $internalListId) {
        $oEmpfaenger = Shop::Container()->getDB()->select('rapidmail_sync_table', ['kNewsletterEmpfaenger', 'kInternalListId'], [$kEmpfaenger, $internalListId]);

        if (!isset($oEmpfaenger->id)) {
            $syncObj = new stdClass();
            $syncObj->kNewsletterEmpfaenger = $kEmpfaenger;
            $syncObj->kInternalListId = $internalListId;

            Shop::Container()->getDB()->insert('rapidmail_sync_table', $syncObj);
        }
    }

    function remove_from_synchronizeTable($kEmpfaenger, $internalListId) {
        $oEmpfaenger = Shop::Container()->getDB()->select('rapidmail_sync_table', ['kNewsletterEmpfaenger', 'kInternalListId'], [$kEmpfaenger, $internalListId]);

        if (isset($oEmpfaenger->id)) {
            Shop::Container()->getDB()->delete('rapidmail_sync_table', 'id', $oEmpfaenger->id);
        }
    }

    function addRecipient($oNewsletterEmpfaenger):bool {
        // Get all languages to later give RapidMail the more understandable ISO key for a language
        //$Sprache = Shop::Container()->getDB()->selectAll('tsprache', 'active', '1');
        $oSprache = Shop::Container()->getDB()->query(
                 "SELECT kSprache, cISO
                    FROM tsprache", 9);
        $spracheISO = [];
        foreach ($oSprache as $key => $value) {
            $spracheISO[$value['kSprache']] = $value['cISO'];
        }
        unset($value);
        unset($oSprache);

        $client = new Client($this->username, $this->password);

        $recipientService = $client->recipients();

        $payload = [
            'email' => $oNewsletterEmpfaenger->cEmail,
            'recipientlist_id' => (int)$this->listIds[$oNewsletterEmpfaenger->kSprache - 1],
            'firstname' => $oNewsletterEmpfaenger->cVorname,
            'lastname' => $oNewsletterEmpfaenger->cNachname,
            'foreign_id' => $oNewsletterEmpfaenger->kNewsletterEmpfaenger,
            'extra1' => $spracheISO[$oNewsletterEmpfaenger->kSprache],
            'extra2' => $oNewsletterEmpfaenger->dEingetragen,
            'status' => 'active'
        ];

        $modifier = [
            
                'track_stats' => 'yes',
                'send_activationmail' => 'no'
        
        ];
        
        try {
            $send_recipient = $recipientService->create($payload, $modifier);
            $this->add_to_synchronizeTable($oNewsletterEmpfaenger->kNewsletterEmpfaenger, $oNewsletterEmpfaenger->kSprache - 1);
            return true;
            // $log_message = "Der Newsletter Empfänger mit der Mail Adresse " . $oNewsletterEmpfaenger->cEmail . " wurde an RapidMail übertragen.";
            // $this->_write_log_notice($log_message);
        } 
        catch (ApiClientException $e){
            // if ($e->hasResponse()) {
            //     return( $this->_handle_api_error($e->getResponse()) );
            // }
            return false;
        }
    }

    function getRecipientsByListId($recipientlistId) {

        $client = new Client($this->username, $this->password);

        $service = $client->recipients();

        $filter = [
            'recipientlist_id' => $recipientlistId
        ];

        try{

            $collection = $service->query($filter);

            $resultObj = $collection;
            return ($resultObj);
        } catch (ApiClientException $e) {
            // if ($e->hasResponse()) {
            //     $this->_handle_api_error($e->getResponse());
            //     return( false );
            // }
            return false;
        }
    }

    function _find_recipient_id_by_mail($recipientList, $cEmail) {
        foreach ($recipientList as $recipient) {
            if (strcasecmp($recipient['email'], $cEmail) == 0) {
                $empfaengerID = $recipient['id'];
                return $empfaengerID;
            }
        }
        return 0;
    }

    function remove_recipient_by_empfaengerObj($oNewsletterEmpfaenger) {
        $oEmpfaenger = Shop::Container()->getDB()->select('rapidmail_sync_table', 'kNewsletterempfaenger', $oNewsletterEmpfaenger->kNewsletterEmpfaenger);
        if (isset($oEmpfaenger->id)) {
            $recipientList_raw = $this->getRecipientsByListId($this->listIds[$oEmpfaenger->kInternalListId]);
            if ($recipientList_raw == false) {
                return false;
            }
            $recipientList = $recipientList_raw;


            $empfaengerID = $this->_find_recipient_id_by_mail($recipientList, $oNewsletterEmpfaenger->cEmail);

            if ($this->deleteRecipient($empfaengerID)) {
                $this->remove_from_synchronizeTable($oEmpfaenger->kNewsletterempfaenger, $oEmpfaenger->kInternalListId);
            //     // $log_message = "Der Newsletter Empfänger mit der Mail Adresse " . $oNewsletterEmpfaenger->cEmail . " wurde in RapidMail abgemeldet.";
            //     // $this->_write_log_notice($log_message);
                return true;
            }
        }
    }

    function deleteRecipient($recipientId) {

        $client = new Client($this->username, $this->password);

        $service = $client->recipients();

        try{
        $service->delete($recipientId);

            return true;
        } catch (ApiClientException $e) {
            return false;
        }
    }

    function removeUnsubscribedRecipientsFromShopDatabase(int $languageID):int {

        $SubscribersRapidmail = $this->getRecipientsByListId($this->listIds[$languageID-1]);

        $SubscribersShop = Shop::Container()->getDB()->selectAll('tnewsletterempfaenger', 'kSprache', $languageID);

        $i = 0;
        foreach($SubscribersRapidmail as $recipient) {
            $helper_array[$i] = $recipient['email'];
            $i++;
        }

        if ($SubscribersShop == NULL) {
            return 0;
        }

        $shopEmails = array_column($SubscribersShop, 'cEmail');

        if ($helper_array == NULL) {
            $emailsToDelete = $shopEmails;
        }
        else{
            $emailsToDelete = array_diff($shopEmails, $helper_array);
        }

        $counter = 0;

        foreach ($emailsToDelete as $email) {

            $recipient = Shop::Container()->getDB()->select('tnewsletterempfaenger', 'cEmail', $email);

            try{
                $this->remove_from_synchronizeTable($recipient->kNewsletterEmpfaenger, $recipient->kSprache - 1);
            }
            catch(Exception $e) {
                $fillvar = 0;
            }

            $oEmpfaenger = Shop::Container()->getDB()->delete('tnewsletterempfaenger', 'cEmail', $email);
            $counter++;
        }

        return $counter;
    }

    // Mass Import 

    function import_recipient($recipient, $listId):bool {
        $user = $this->username;
        $pass = $this->password;

        $client = new Client($user, $pass);
        $recipientService = $client->recipients();

        $payload = [
            'email' => $recipient[0],
            'recipientlist_id' => (int) $listId,
            'firstname' => $recipient[1],
            'lastname' => $recipient[2],
            'foreign_id' => $recipient[3],
            'extra1' => $recipient[4],
            'extra2' => $recipient[5],
            'status' => 'active'
        ];

        $modifier = [
            'track_stats' => 'yes',
            'send_activationmail' => 'no'
        ];

        try{
            $recipientService->create($payload, $modifier);
            return true;
        }
        catch (ApiClientException $e){
            return false;
        }
    }
    
    function import_from_shop_to_rapidMail($sinceDate) {
        
        $user_count = 0;

        $listIds = $this->listIds;
        
        $data_de = $this->generate_import_Data(1, $sinceDate);
        $data_en = $this->generate_import_Data(2, $sinceDate);

        $import_result = true;
        if (count($data_de) > 1) {
            foreach ($data_de as $key => $empf) {
                if ($key >= 1) {

                    if($import_result){
                        if($this->import_recipient($empf, $listIds[0])){
                            $import_result = true;
                            $this->add_to_synchronizeTable($empf[4], 0);
                            $user_count++;
                        }
                        else{
                            $import_result = false;
                        }
                    }   
                }
            }
        }
    
    
        $import_result = true;
        if (count($data_en) > 1) {
            foreach ($data_en as $key => $empf) {
                if ($key >= 1) {
                    if($import_result){
                        if($this->import_recipient($empf, $listIds[1])){
                            $import_result = true;
                            $this->add_to_synchronizeTable($empf[4], 1);
                            $user_count++;
                        }
                        else{
                            $import_result = false;
                        }
                    }
                }
            }
        }
    
        return ['successfull' => $import_result, 'count' => $user_count];
    }
    
    function generate_import_Data(int $lang_key, String $sinceDate) {
        $data = [];
        //'email', 'firstname', 'lastname', 'gender', 'title', 'zip', 'birthdate', 'foreign_id', 'mailtype', 'extra1', 'extra2', 'extra3', 'extra4', 'extra5', 'extra6', 'extra7', 'extra8', 'extra9', 'extra10', 'created', 'created_ip', 'activated', 'activated_ip', 'deleted', 'status'
        $data[] = array('email', 'firstname', 'lastname', 'gender', 'foreign_id', 'extra1', 'extra2');
    
        // Get the ids of all Newsletter Recipients that are already synchronized
        $oSyncedIds = Shop::Container()->getDB()->query(
                "SELECT kNewsletterEmpfaenger
                FROM rapidmail_sync_table
                WHERE kInternalListId = " . ($lang_key - 1) . " ", 2);
        $sync_ids = "(";
        foreach ($oSyncedIds as $key => $oSyncId) {
            $sync_ids .= $oSyncId->kNewsletterEmpfaenger;
            if ($key < count($oSyncedIds) - 1) {
                $sync_ids .= ", ";
            }
        }
        if (count($oSyncedIds) == 0) {
            $sync_ids .= "-1";
        }
        $sync_ids .= ")";
    
        // Get all Newsletter Recipients from the JTL Shop Database that are active
        $oNewsletterEmpfaenger = Shop::Container()->getDB()->query(
                "SELECT cEmail, cVorname, cNachname, cAnrede, kNewsletterEmpfaenger, kSprache, dEingetragen
        FROM tnewsletterempfaenger
        WHERE nAktiv = 1 AND kSprache = " . $lang_key . " AND dEingetragen > '" . $sinceDate . "' AND kNewsletterEmpfaenger NOT IN " . $sync_ids . " ", 9);
    
        // Get all languages to later give RapidMail the more understandable ISO key for a language
        $oSprache = Shop::Container()->getDB()->query(
                "SELECT kSprache, cISO
        FROM tsprache", 9);
        $spracheISO = [];
        foreach ($oSprache as $key => $value) {
            $spracheISO[$value['kSprache']] = $value['cISO'];
        }
        unset($value);
        unset($oSprache);
    
        // Create an array with the needed data from the Newsletter Recipients
        foreach ($oNewsletterEmpfaenger as $key => $value) {
            $empfaenger = [];
            $empfaenger[] = isset($value['cEmail']) ? $value['cEmail'] : '';
            $empfaenger[] = isset($value['cVorname']) ? $value['cVorname'] : '';
            $empfaenger[] = isset($value['cNachname']) ? $value['cNachname'] : '';
            $empfaenger[] = isset($value['cAnrede']) ? $value['cAnrede'] : '';
            $empfaenger[] = isset($value['kNewsletterEmpfaenger']) ? $value['kNewsletterEmpfaenger'] : '';
            $empfaenger[] = isset($value['kSprache']) ? $spracheISO[$value['kSprache']] : '';
            $empfaenger[] = isset($value['dEingetragen']) ? $value['dEingetragen'] : '';
            $data[] = $empfaenger;
        }
        unset($value);
        unset($oNewsletterEmpfaenger);
    
        return($data);
    }


}