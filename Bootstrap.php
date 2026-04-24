<?php declare(strict_types=1);

namespace Plugin\rapidmail_newsletter;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/rapidmail_integration.class.php';

use JTL\Alert\Alert;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Artikel;
use JTL\Consent\Item;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use \JTL\Backend\Notification;
use Laminas\Diactoros\ServerRequestFactory;
use function Functional\first;
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiClientException;
use JTL\Plugin\PluginInterface;

/**
 * Class Bootstrap
 * @package Plugin\jtl_test
 */
class Bootstrap extends Bootstrapper
{
    /**
     * @var TestHelper
     */

    private const RAPIDMAIL_CRON = 'Rapidmail';

    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher)
    {
        parent::boot($dispatcher);

        $dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, [$this, 'availableCronjobType']);
        $dispatcher->listen(Event::MAP_CRONJOB_TYPE, [$this, 'mappingCronjobType']);

        $plugin     = $this->getPlugin();
        $backendURL = \method_exists($plugin->getPaths(), 'getBackendURL')
            ? $plugin->getPaths()->getBackendURL()
            : Shop::getAdminURL() . '/plugin.php?kPlugin=' . $plugin->getID();

        if(( (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1) &&
        ( (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'listsValid')->cWert == 1)){

            if( (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration')->cWert == 1){
                    
                $dispatcher->hookInto(
                        \HOOK_NEWSLETTER_PAGE_EMPFAENGERFREISCHALTEN,
                    function (array $args) {

                        $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                        $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;

                        $listDE = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                        $listEN = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                        $listArray = Array($listDE, $listEN);

                        $integration = new \rapidmail_integration($username, $password, $listArray);

                        $orgNewsletterEmpfaenger = $args['oNewsletterEmpfaenger'];
                            
                        $result = $integration->addRecipient($orgNewsletterEmpfaenger);
                    }
                );
            } 
            else{
                Notification::getInstance()->add(0,$this->getPlugin()->getMeta()->getName(),"Automatische Anmeldung ist deaktiviert!", $backendURL);
            }
            

            if( (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert == 1){
                    
                $dispatcher->hookInto(
                    \HOOK_NEWSLETTER_PAGE_EMPFAENGERLOESCHEN,
                    function (array $args) {

                        $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                        $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;

                        $listDE = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                        $listEN = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                        $listArray = Array($listDE, $listEN);

                        $integration = new \rapidmail_integration($username, $password, $listArray);

                        $orgNewsletterEmpfaenger = $args['oNewsletterEmpfaenger'];
                        
                        $integration->remove_recipient_by_empfaengerObj($orgNewsletterEmpfaenger);
                    }
                );
            }
            else{
                Notification::getInstance()->add(0,$this->getPlugin()->getMeta()->getName(),"Automatische Abmeldung ist deaktiviert!", $backendURL);
            } 
        }
        else {
            Notification::getInstance()->add(1,$this->getPlugin()->getMeta()->getName(),"Konfiguration nicht abgeschlossen!", $backendURL);
        }

    }

    public function availableCronjobType(array &$args): void
    {   
        if (!\in_array(self::RAPIDMAIL_CRON, $args['jobs'], true)) {
            $args['jobs'][] = self::RAPIDMAIL_CRON;
        }
    }

    public function mappingCronjobType(array &$args): void
    {
        $type = $args['type'];
        if ($type === self::RAPIDMAIL_CRON) {
            $args['mapping'] = RapidmailCron::class;
        }
    }

    private function addCronJob()
    {
        $job = new \stdClass();
        $job->name      = 'Rapidmail Cron';
        $job->jobType   = self::RAPIDMAIL_CRON;
        $job->frequency = 24;
        $job->startDate = 'NOW()';
        $job->startTime = '23:50:00';
        $this->getDB()->insert('tcron', $job);
    }

    /**
     * @param array $args
     */
    public function addConsentItem(array $args): void
    {  
    }

    /**
     * @inheritdoc
     */
    public function installed(): void
    {   
        parent::installed();
        $this->addCronJob();
    }

    /**
     * @inheritdoc
     */
    public function updated($oldVersion, $newVersion): void
    {
    }

    /**
     * @inheritdoc
     */
    public function uninstalled(bool $deleteData = true): void
    {
        parent::uninstalled($deleteData);
        $this->getDB()->delete('tcron', 'jobType', self::RAPIDMAIL_CRON);
    }

    /**
     * @inheritdoc
     */
    public function prepareFrontend(LinkInterface $link, JTLSmarty $smarty): bool
    {
    }

    private function checkCredentials(string $api_user, string $api_pass): bool
    {
        $client = new Client($api_user, $api_pass);
        $service = $client->apiUsers();

        try{
            $collection = $service->query();
            if($collection != null){
                return true;
            }
            else{
                return false;
            }
        }
        catch(ApiClientException $e){
            if (\method_exists($this->getPlugin(), 'getLogger')) {
                $logger = $this->getPlugin()->getLogger();
            } else {
                // fallback for shop versions < 5.3.0
                $logger = Shop::Container()->getLogService();
            }
            $logger->error('Rapidmail exception: '. $e->getMessage());
            return false;
        }
    }

    private function retrieveRecipientLists(string $api_user, string $api_pass){
        $client = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();
        $collection = $recipientService->query();
        return $collection;
    }

    private function checkRecipientList(int $listid):bool{
        $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
        $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
        $client = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();

        try{
            $result = $recipientService->get($listid);
            if($result != null){
                return true;
            }
            else{
                return false;
            }
        }
        catch(ApiClientException $e){
            if (\method_exists($this->getPlugin(), 'getLogger')) {
                $logger = $this->getPlugin()->getLogger();
            } else {
                // fallback for shop versions < 5.3.0
                $logger = Shop::Container()->getLogService();
            }
            $logger->error('Rapidmail exception: '. $e->getMessage());
            return false;
        }
    }

    private function createRecipientList(string $listName):bool{
        $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
        $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
        $client = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();

        $payload = [
            'name' => (string) $listName,
        ];

        try{
            $recipientService->create($payload);
            return true;
        }
        catch (ApiClientException $e){

            if (\method_exists($this->getPlugin(), 'getLogger')) {
                $logger = $this->getPlugin()->getLogger();
            } else {
                // fallback for shop versions < 5.3.0
                $logger = Shop::Container()->getLogService();
            }
            $logger->error('Rapidmail exception: '. $e->getMessage());

            return false;
        }

    }

    /**
     * @inheritdoc
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $plugin     = $this->getPlugin();
        $backendURL = \method_exists($plugin->getPaths(), 'getBackendURL')
            ? $plugin->getPaths()->getBackendURL()
            : Shop::getAdminURL() . '/plugin.php?kPlugin=' . $plugin->getID();

        $smarty->assign('menuID', $menuID);
        
        // Holen des Step Wertes aus der Datenbank
        $step = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'step')->cWert;

        // Sammeln der Listennamen, falls die Zugangsdaten geprüft sind
        if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1){
            $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
            $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
            $lists = $this->retrieveRecipientLists($api_user, $api_pass);
        }
        else{
            $obj_update = new \stdClass();
            $obj_update->cWert = 0;
            $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
            $obj_id_de = new \stdClass();
            $obj_id_de->cWert = "";
            $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0', $obj_id_de);
            $obj_id_en = new \stdClass();
            $obj_id_en->cWert= "";
            $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1', $obj_id_en);
        }
        

        if ($tabName === 'Setup') {
            $template = 'setup.tpl';


            // Button Weiter im Adminmenü
            if(Form::validateToken() && ($forward = Request::postVar('step_forward')) !== null) {
                if($step < 4){
                    $step++;
                    $obj = new \stdClass();
                    $obj->cWert = $step;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'step', $obj);
                }
            }
            // Button Zurück im Adminmenü
            if (Form::validateToken() && ($backward = Request::postVar('step_back')) !== null){
                if($step > 1){
                $step--;
                $obj = new \stdClass();
                $obj->cWert = $step;
                $this->getDB()->update('tplugineinstellungen', 'cName', 'step', $obj);
                }
            }
            // Seite von Step 1 "Zugangsdaten"
            if(Form::validateToken() && ($api_user = Request::postVar('api_user')) !== null && ($api_pass = Request::postVar('api_pass')) !== null){
                $smarty->assign('api_user', $api_user);
                $smarty->assign('api_pass', $api_pass);

                $checked = True;
                if($this->checkCredentials($api_user, $api_pass) == true){
                    $obj_valid = new \stdClass();
                    $obj_valid->cWert= 1;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'loginValid', $obj_valid);
                    $obj_user = new \stdClass();
                    $obj_user->cWert = $api_user;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailUsername', $obj_user);
                    $obj_pass = new \stdClass();
                    $obj_pass->cWert = $api_pass;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailPassword', $obj_pass);
                    $smarty->assign('feedback', '<span style="color: green; padding-top: 20px;">Überprüfung war erfolgreich!</span>');
                }
                else{
                    $obj_valid = new \stdClass();
                    $obj_valid->cWert= 0;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'loginValid', $obj_valid);
                    $obj_user = new \stdClass();
                    $obj_user->cWert = "";
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailUsername', $obj_user);
                    $obj_pass = new \stdClass();
                    $obj_pass->cWert = "";
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailPassword', $obj_pass);
                    $smarty->assign('feedback', '<span style="color: red; padding-top: 20px;">Es ist ein Fehler aufgetreten!</span>');
                    
                }
                
            }
            // Seite von Step 2 "Empfängerlisten"
            if(Form::validateToken() && ($listid_de = Request::postVar('list_id_deutsch')) !== null && ($listid_en = Request::postVar('list_id_englisch')) !== null){
                $smarty->assign('listid_de', $listid_de);
                $smarty->assign('listid_en', $listid_en);

                if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid') == 1){
                    if(($this->checkRecipientList((int)$listid_de) == true) && ($this->checkRecipientList((int)$listid_en) == true)){
                        $obj_update = new \stdClass();
                        $obj_update->cWert = 1;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
                        $obj_id_de = new \stdClass();
                        $obj_id_de->cWert = $listid_de;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0', $obj_id_de);
                        $obj_id_en = new \stdClass();
                        $obj_id_en->cWert= $listid_en;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1', $obj_id_en);
                        $smarty->assign('feedback', '<span style="color: green;">Gespeichert!</span>');
                    }
                    else{
                        $obj_update = new \stdClass();
                        $obj_update->cWert = 0;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
                        $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den gewählten Listen!</span>');
                    }
                }
                else{
                    $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
                
            }
            // Step 2 Neue Liste erstellen
            if(Form::validateToken() && ($list_name = Request::postVar('list-name')) !== null){
                $smarty->assign('list_name', $list_name);
                if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid') == 1){
                    
                    if($this->createRecipientList($list_name) == true){
                        if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1){
                            $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                            $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
                            $lists = $this->retrieveRecipientLists($api_user, $api_pass);
                        }

                        $smarty->assign('new_list_feedback', '<span style="color: green;">Erfolgreich erstellt!</span>');
                    }
                    else{
                        $smarty->assign('new_list_feedback', '<span style="color: red;">Da hat etwas nicht geklappt!</span>');
                    }
                }
                else{
                    $smarty->assign('new_list_feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
            }
            // Seite von Step 3 "Datenaustausch"
            if(Form::validateToken() && ($date_input = Request::postVar('date-control')) !== null){
                $smarty->assign('date_input', $date_input);

                if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1){
                    $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                    $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;

                    $listDE = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                    $listEN = (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                    $listArray = Array($listDE, $listEN);

                    $integration = new \rapidmail_integration($username, $password, $listArray);

                    $import_output = $integration->import_from_shop_to_rapidMail($date_input);
                    
                    if($import_output['successfull'] == true){
                        $smarty->assign('feedback', '<span style="color: green;">Es wurden erfolgreich '.$import_output['count'].' Empfänger importiert!</span>');
                    }
                    else{
                        $smarty->assign('feedback', '<span style="color: red;">Es ist ein Fehler aufgetreten!</span>');
                    }
                    
                }
                else{
                    $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
            }
            // Seite von Step 4 "Laufende Einstellungen"
            if(Form::validateToken() && (($reg_data = Request::postVar('auto-reg')) !== null) && (($dereg_data = Request::postVar('auto-dereg')) !== null)){
                $obj_reg = new \stdClass();
                $obj_reg->cWert = (int) $reg_data;
                $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration', $obj_reg);
                $obj_dereg = new \stdClass();
                $obj_dereg->cWert = (int) $dereg_data;
                $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration', $obj_dereg);
                $smarty->assign('feedback', '<span style="color: green;">Gepeichert!</span>');
            }
            // Variablen für Felder in Step 4
            if($step == 4){
                $smarty->assign('reg_status', (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration')->cWert);
                $smarty->assign('dereg_status', (int) $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert);
            }
            // Variablen für Felder in Step 2
            if($step == 2){
                $smarty->assign('lists', $lists);
                $smarty->assign('listid_de', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert)
                    ->assign('listid_en', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert);
            }
            // Variablen für Felder in Step 1
            if($step == 1){
                if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1){
                    $smarty->assign('api_user', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert)
                        ->assign('api_pass', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert);
                }
            }

            // Setzen der Variable für den aktuellen Schritt
            $smarty->assign('step', $step);
        }

        return $smarty->assign('backendURL', $backendURL)
            ->fetch($this->getPlugin()->getPaths()->getAdminPath() . '/templates/' . $template);
    }
}
