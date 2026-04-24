<?php declare(strict_types=1);

namespace Plugin\rapidmail_newsletter;

require_once __DIR__ . '/includes/rapidmail_integration.class.php';

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Shop;
use JTL\Plugin\Helper;
use JTL\Plugin\Interface;

/**
 * Class TestCronJob
 * @package Plugin\jtl_test
 */
class RapidmailCron extends Job
{

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $this->setFinished($this->removeUnsubscribedUsers());

        return $this;
    }

    private function removeUnsubscribedUsers():bool
    {   

        $deregistration_turned_on = Shop::Container()->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert;
        if($deregistration_turned_on == 1) {
            $username = Shop::Container()->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailUsername')->cWert;
            $password = Shop::Container()->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailPassword')->cWert;

            $listDE = (int) Shop::Container()->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId0')->cWert;
            $listEN = (int) Shop::Container()->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailRecipientListId1')->cWert;

            $listArray = Array($listDE, $listEN);

            $integration = new \rapidmail_integration($username, $password, $listArray);

            $result_de = $integration->removeUnsubscribedRecipientsFromShopDatabase(1);
            $result_en = $integration->removeUnsubscribedRecipientsFromShopDatabase(2);

            $result = $result_de + $result_en;

            if($result >= 1){
                $this->logger->warning('Rapidmail: '.$result.' Recipients have been removed from shop database.');
            }
        }

        return true;
    }
}