<?php declare(strict_types=1);

namespace Plugin\rapidmail_newsletter;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/rapidmail_integration.class.php';

use JTL\Backend\Notification;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiClientException;

/**
 * Class Bootstrap
 * @package Plugin\rapidmail_newsletter
 */
class Bootstrap extends Bootstrapper
{
    private const RAPIDMAIL_CRON = 'Rapidmail';

    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, [$this, 'availableCronjobType']);
        $dispatcher->listen(Event::MAP_CRONJOB_TYPE, [$this, 'mappingCronjobType']);

        $plugin     = $this->getPlugin();
        $backendURL = \method_exists($plugin->getPaths(), 'getBackendURL')
            ? $plugin->getPaths()->getBackendURL()
            : Shop::getAdminURL() . '/plugin.php?kPlugin=' . $plugin->getID();

        if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1
            && (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'listsValid')->cWert === 1) {

            if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration')->cWert === 1) {
                $dispatcher->hookInto(
                    \HOOK_NEWSLETTER_PAGE_EMPFAENGERFREISCHALTEN,
                    function (array $args) {
                        $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                        $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
                        $listDE   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                        $listEN   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                        $integration = new \rapidmail_integration($username, $password, [$listDE, $listEN]);
                        $integration->addRecipient($args['oNewsletterEmpfaenger']);
                    }
                );
            } else {
                Notification::getInstance()->add(0, $this->getPlugin()->getMeta()->getName(), 'Automatische Anmeldung ist deaktiviert!', $backendURL);
            }

            if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert === 1) {
                $dispatcher->hookInto(
                    \HOOK_NEWSLETTER_PAGE_EMPFAENGERLOESCHEN,
                    function (array $args) {
                        $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                        $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
                        $listDE   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                        $listEN   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                        $integration = new \rapidmail_integration($username, $password, [$listDE, $listEN]);
                        $integration->removeRecipientByEmpfaengerObj($args['oNewsletterEmpfaenger']);
                    }
                );
            } else {
                Notification::getInstance()->add(0, $this->getPlugin()->getMeta()->getName(), 'Automatische Abmeldung ist deaktiviert!', $backendURL);
            }
        } else {
            Notification::getInstance()->add(1, $this->getPlugin()->getMeta()->getName(), 'Konfiguration nicht abgeschlossen!', $backendURL);
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

    private function addCronJob(): void
    {
        $job            = new \stdClass();
        $job->name      = 'Rapidmail Cron';
        $job->jobType   = self::RAPIDMAIL_CRON;
        $job->frequency = 24;
        $job->startDate = 'NOW()';
        $job->startTime = '23:50:00';
        $this->getDB()->insert('tcron', $job);
    }

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
        return false;
    }

    private function checkCredentials(string $api_user, string $api_pass): bool
    {
        $client  = new Client($api_user, $api_pass);
        $service = $client->apiUsers();

        try {
            $collection = $service->query();
            return $collection !== null;
        } catch (ApiClientException $e) {
            $this->getPlugin()->getLogger()->error('Rapidmail exception: ' . $e->getMessage());
            return false;
        }
    }

    private function retrieveRecipientLists(string $api_user, string $api_pass)
    {
        $client           = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();
        return $recipientService->query();
    }

    private function checkRecipientList(int $listid): bool
    {
        $api_user         = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
        $api_pass         = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
        $client           = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();

        try {
            $result = $recipientService->get($listid);
            return $result !== null;
        } catch (ApiClientException $e) {
            $this->getPlugin()->getLogger()->error('Rapidmail exception: ' . $e->getMessage());
            return false;
        }
    }

    private function createRecipientList(string $listName): bool
    {
        $api_user         = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
        $api_pass         = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
        $client           = new Client($api_user, $api_pass);
        $recipientService = $client->recipientlists();

        try {
            $recipientService->create(['name' => $listName]);
            return true;
        } catch (ApiClientException $e) {
            $this->getPlugin()->getLogger()->error('Rapidmail exception: ' . $e->getMessage());
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

        $step = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'step')->cWert;

        if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1) {
            $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
            $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
            $lists    = $this->retrieveRecipientLists($api_user, $api_pass);
        } else {
            $obj_update        = new \stdClass();
            $obj_update->cWert = 0;
            $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
            $obj_id_de        = new \stdClass();
            $obj_id_de->cWert = '';
            $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0', $obj_id_de);
            $obj_id_en        = new \stdClass();
            $obj_id_en->cWert = '';
            $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1', $obj_id_en);
        }

        $template = 'setup.tpl';

        if ($tabName === 'Setup') {
            if (Form::validateToken() && ($forward = Request::postVar('step_forward')) !== null) {
                if ($step < 4) {
                    $step++;
                    $obj        = new \stdClass();
                    $obj->cWert = $step;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'step', $obj);
                }
            }
            if (Form::validateToken() && ($backward = Request::postVar('step_back')) !== null) {
                if ($step > 1) {
                    $step--;
                    $obj        = new \stdClass();
                    $obj->cWert = $step;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'step', $obj);
                }
            }
            if (Form::validateToken() && ($api_user = Request::postVar('api_user')) !== null && ($api_pass = Request::postVar('api_pass')) !== null) {
                $smarty->assign('api_user', $api_user)->assign('api_pass', $api_pass);

                if ($this->checkCredentials($api_user, $api_pass)) {
                    $obj_valid        = new \stdClass();
                    $obj_valid->cWert = 1;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'loginValid', $obj_valid);
                    $obj_user        = new \stdClass();
                    $obj_user->cWert = $api_user;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailUsername', $obj_user);
                    $obj_pass        = new \stdClass();
                    $obj_pass->cWert = $api_pass;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailPassword', $obj_pass);
                    $smarty->assign('feedback', '<span style="color: green; padding-top: 20px;">Überprüfung war erfolgreich!</span>');
                } else {
                    $obj_valid        = new \stdClass();
                    $obj_valid->cWert = 0;
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'loginValid', $obj_valid);
                    $obj_user        = new \stdClass();
                    $obj_user->cWert = '';
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailUsername', $obj_user);
                    $obj_pass        = new \stdClass();
                    $obj_pass->cWert = '';
                    $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailPassword', $obj_pass);
                    $smarty->assign('feedback', '<span style="color: red; padding-top: 20px;">Es ist ein Fehler aufgetreten!</span>');
                }
            }
            if (Form::validateToken() && ($listid_de = Request::postVar('list_id_deutsch')) !== null && ($listid_en = Request::postVar('list_id_englisch')) !== null) {
                $smarty->assign('listid_de', $listid_de)->assign('listid_en', $listid_en);

                if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1) {
                    if ($this->checkRecipientList((int)$listid_de) && $this->checkRecipientList((int)$listid_en)) {
                        $obj_update        = new \stdClass();
                        $obj_update->cWert = 1;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
                        $obj_id_de        = new \stdClass();
                        $obj_id_de->cWert = $listid_de;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0', $obj_id_de);
                        $obj_id_en        = new \stdClass();
                        $obj_id_en->cWert = $listid_en;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1', $obj_id_en);
                        $smarty->assign('feedback', '<span style="color: green;">Gespeichert!</span>');
                    } else {
                        $obj_update        = new \stdClass();
                        $obj_update->cWert = 0;
                        $this->getDB()->update('tplugineinstellungen', 'cName', 'listsValid', $obj_update);
                        $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den gewählten Listen!</span>');
                    }
                } else {
                    $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
            }
            if (Form::validateToken() && ($list_name = Request::postVar('list-name')) !== null) {
                $smarty->assign('list_name', $list_name);
                if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1) {
                    if ($this->createRecipientList($list_name)) {
                        $api_user = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                        $api_pass = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
                        $lists    = $this->retrieveRecipientLists($api_user, $api_pass);
                        $smarty->assign('new_list_feedback', '<span style="color: green;">Erfolgreich erstellt!</span>');
                    } else {
                        $smarty->assign('new_list_feedback', '<span style="color: red;">Da hat etwas nicht geklappt!</span>');
                    }
                } else {
                    $smarty->assign('new_list_feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
            }
            if (Form::validateToken() && ($date_input = Request::postVar('date-control')) !== null) {
                $smarty->assign('date_input', $date_input);

                if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1) {
                    $username = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
                    $password = $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;
                    $listDE   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
                    $listEN   = (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

                    $integration   = new \rapidmail_integration($username, $password, [$listDE, $listEN]);
                    $import_output = $integration->importFromShopToRapidMail($date_input);

                    if ($import_output['successfull'] === true) {
                        $smarty->assign('feedback', '<span style="color: green;">Es wurden erfolgreich ' . $import_output['count'] . ' Empfänger importiert!</span>');
                    } else {
                        $smarty->assign('feedback', '<span style="color: red;">Es ist ein Fehler aufgetreten!</span>');
                    }
                } else {
                    $smarty->assign('feedback', '<span style="color: red;">Es gibt ein Problem mit den Zugangsdaten!</span>');
                }
            }
            if (Form::validateToken() && ($reg_data = Request::postVar('auto-reg')) !== null && ($dereg_data = Request::postVar('auto-dereg')) !== null) {
                $obj_reg        = new \stdClass();
                $obj_reg->cWert = (int)$reg_data;
                $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration', $obj_reg);
                $obj_dereg        = new \stdClass();
                $obj_dereg->cWert = (int)$dereg_data;
                $this->getDB()->update('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration', $obj_dereg);
                $smarty->assign('feedback', '<span style="color: green;">Gespeichert!</span>');
            }
            if ($step === 4) {
                $smarty->assign('reg_status', (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration')->cWert);
                $smarty->assign('dereg_status', (int)$this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert);
            }
            if ($step === 2) {
                $smarty->assign('lists', $lists ?? []);
                $smarty->assign('listid_de', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert)
                    ->assign('listid_en', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert);
            }
            if ($step === 1) {
                if ((int)$this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert === 1) {
                    $smarty->assign('api_user', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert)
                        ->assign('api_pass', $this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert);
                }
            }

            $smarty->assign('step', $step);
        }

        return $smarty->assign('backendURL', $backendURL)
            ->fetch($this->getPlugin()->getPaths()->getAdminPath() . '/templates/' . $template);
    }
}
