<?php declare(strict_types=1);

use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiClientException;
use JTL\Shop;

require_once __DIR__ . '/../vendor/autoload.php';

class rapidmail_integration
{
    private string $username;
    private string $password;
    private array  $listIds;

    public function __construct(string $username, string $password, array $listIds)
    {
        $this->username = $username;
        $this->password = $password;
        $this->listIds  = $listIds;
    }

    public function addToSynchronizeTable(int $kEmpfaenger, int $internalListId): void
    {
        $oEmpfaenger = Shop::Container()->getDB()->select(
            'rapidmail_sync_table',
            ['kNewsletterempfaenger', 'kInternalListId'],
            [$kEmpfaenger, $internalListId]
        );

        if (!isset($oEmpfaenger->id)) {
            $syncObj                        = new stdClass();
            $syncObj->kNewsletterempfaenger = $kEmpfaenger;
            $syncObj->kInternalListId       = $internalListId;
            Shop::Container()->getDB()->insert('rapidmail_sync_table', $syncObj);
        }
    }

    public function removeFromSynchronizeTable(int $kEmpfaenger, int $internalListId): void
    {
        $oEmpfaenger = Shop::Container()->getDB()->select(
            'rapidmail_sync_table',
            ['kNewsletterempfaenger', 'kInternalListId'],
            [$kEmpfaenger, $internalListId]
        );

        if (isset($oEmpfaenger->id)) {
            Shop::Container()->getDB()->delete('rapidmail_sync_table', 'id', $oEmpfaenger->id);
        }
    }

    public function addRecipient(object $oNewsletterEmpfaenger): bool
    {
        $oSprache = Shop::Container()->getDB()->query('SELECT kSprache, cISO FROM tsprache', 9);

        $spracheISO = [];
        foreach ($oSprache as $value) {
            $spracheISO[$value['kSprache']] = $value['cISO'];
        }

        $client           = new Client($this->username, $this->password);
        $recipientService = $client->recipients();

        $payload = [
            'email'            => $oNewsletterEmpfaenger->cEmail,
            'recipientlist_id' => (int)$this->listIds[$oNewsletterEmpfaenger->kSprache - 1],
            'firstname'        => $oNewsletterEmpfaenger->cVorname,
            'lastname'         => $oNewsletterEmpfaenger->cNachname,
            'foreign_id'       => $oNewsletterEmpfaenger->kNewsletterEmpfaenger,
            'extra1'           => $spracheISO[$oNewsletterEmpfaenger->kSprache],
            'extra2'           => $oNewsletterEmpfaenger->dEingetragen,
            'status'           => 'active',
        ];

        $modifier = [
            'track_stats'          => 'yes',
            'send_activationmail'  => 'no',
        ];

        try {
            $recipientService->create($payload, $modifier);
            $this->addToSynchronizeTable(
                (int)$oNewsletterEmpfaenger->kNewsletterEmpfaenger,
                (int)$oNewsletterEmpfaenger->kSprache - 1
            );
            return true;
        } catch (ApiClientException $e) {
            return false;
        }
    }

    public function getRecipientsByListId(int $recipientlistId)
    {
        $client  = new Client($this->username, $this->password);
        $service = $client->recipients();
        $filter  = ['recipientlist_id' => $recipientlistId];

        try {
            return $service->query($filter);
        } catch (ApiClientException $e) {
            return false;
        }
    }

    private function findRecipientIdByMail(iterable $recipientList, string $cEmail): int
    {
        foreach ($recipientList as $recipient) {
            if (strcasecmp($recipient['email'], $cEmail) === 0) {
                return (int)$recipient['id'];
            }
        }
        return 0;
    }

    public function removeRecipientByEmpfaengerObj(object $oNewsletterEmpfaenger): bool
    {
        $oEmpfaenger = Shop::Container()->getDB()->select(
            'rapidmail_sync_table',
            'kNewsletterempfaenger',
            $oNewsletterEmpfaenger->kNewsletterEmpfaenger
        );

        if (!isset($oEmpfaenger->id)) {
            return false;
        }

        $recipientList = $this->getRecipientsByListId($this->listIds[$oEmpfaenger->kInternalListId]);
        if ($recipientList === false) {
            return false;
        }

        $empfaengerID = $this->findRecipientIdByMail($recipientList, $oNewsletterEmpfaenger->cEmail);

        if ($this->deleteRecipient($empfaengerID)) {
            $this->removeFromSynchronizeTable(
                (int)$oEmpfaenger->kNewsletterempfaenger,
                (int)$oEmpfaenger->kInternalListId
            );
            return true;
        }

        return false;
    }

    public function deleteRecipient(int $recipientId): bool
    {
        $client  = new Client($this->username, $this->password);
        $service = $client->recipients();

        try {
            $service->delete($recipientId);
            return true;
        } catch (ApiClientException $e) {
            return false;
        }
    }

    public function removeUnsubscribedRecipientsFromShopDatabase(int $languageID): int
    {
        $subscribersRapidmail = $this->getRecipientsByListId($this->listIds[$languageID - 1]);
        $subscribersShop      = Shop::Container()->getDB()->selectAll('tnewsletterempfaenger', 'kSprache', $languageID);

        if ($subscribersShop === null) {
            return 0;
        }

        $rapidmailEmails = [];
        if ($subscribersRapidmail !== false) {
            foreach ($subscribersRapidmail as $recipient) {
                $rapidmailEmails[] = $recipient['email'];
            }
        }

        $shopEmails    = array_column($subscribersShop, 'cEmail');
        $emailsToDelete = empty($rapidmailEmails)
            ? $shopEmails
            : array_diff($shopEmails, $rapidmailEmails);

        $counter = 0;
        foreach ($emailsToDelete as $email) {
            $recipient = Shop::Container()->getDB()->select('tnewsletterempfaenger', 'cEmail', $email);
            if ($recipient !== null) {
                try {
                    $this->removeFromSynchronizeTable((int)$recipient->kNewsletterEmpfaenger, (int)$recipient->kSprache - 1);
                } catch (\Exception $e) {
                    // entry may not exist in sync table — continue
                }
                Shop::Container()->getDB()->delete('tnewsletterempfaenger', 'cEmail', $email);
                $counter++;
            }
        }

        return $counter;
    }

    public function importRecipient(array $recipient, int $listId): bool
    {
        $client           = new Client($this->username, $this->password);
        $recipientService = $client->recipients();

        $payload = [
            'email'            => $recipient[0],
            'recipientlist_id' => $listId,
            'firstname'        => $recipient[1],
            'lastname'         => $recipient[2],
            'foreign_id'       => $recipient[3],
            'extra1'           => $recipient[4],
            'extra2'           => $recipient[5],
            'status'           => 'active',
        ];

        $modifier = [
            'track_stats'         => 'yes',
            'send_activationmail' => 'no',
        ];

        try {
            $recipientService->create($payload, $modifier);
            return true;
        } catch (ApiClientException $e) {
            return false;
        }
    }

    public function importFromShopToRapidMail(string $sinceDate): array
    {
        $user_count = 0;
        $listIds    = $this->listIds;

        $data_de = $this->generateImportData(1, $sinceDate);
        $data_en = $this->generateImportData(2, $sinceDate);

        $import_result = true;
        if (count($data_de) > 1) {
            foreach ($data_de as $key => $empf) {
                if ($key >= 1 && $import_result) {
                    if ($this->importRecipient($empf, $listIds[0])) {
                        $this->addToSynchronizeTable((int)$empf[3], 0);
                        $user_count++;
                    } else {
                        $import_result = false;
                    }
                }
            }
        }

        $import_result = true;
        if (count($data_en) > 1) {
            foreach ($data_en as $key => $empf) {
                if ($key >= 1 && $import_result) {
                    if ($this->importRecipient($empf, $listIds[1])) {
                        $this->addToSynchronizeTable((int)$empf[3], 1);
                        $user_count++;
                    } else {
                        $import_result = false;
                    }
                }
            }
        }

        return ['successfull' => $import_result, 'count' => $user_count];
    }

    public function generateImportData(int $lang_key, string $sinceDate): array
    {
        $parsedDate = \DateTime::createFromFormat('Y-m-d', $sinceDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $sinceDate) {
            return [];
        }
        $safeDate = $parsedDate->format('Y-m-d');

        $data   = [];
        $data[] = ['email', 'firstname', 'lastname', 'foreign_id', 'extra1', 'extra2'];

        $oSyncedIds = Shop::Container()->getDB()->query(
            'SELECT kNewsletterEmpfaenger FROM rapidmail_sync_table WHERE kInternalListId = ' . ($lang_key - 1),
            2
        );

        $sync_ids = '(';
        foreach ($oSyncedIds as $key => $oSyncId) {
            $sync_ids .= $oSyncId->kNewsletterEmpfaenger;
            if ($key < count($oSyncedIds) - 1) {
                $sync_ids .= ', ';
            }
        }
        if (count($oSyncedIds) === 0) {
            $sync_ids .= '-1';
        }
        $sync_ids .= ')';

        $oSprache   = Shop::Container()->getDB()->query('SELECT kSprache, cISO FROM tsprache', 9);
        $spracheISO = [];
        foreach ($oSprache as $value) {
            $spracheISO[$value['kSprache']] = $value['cISO'];
        }

        $oNewsletterEmpfaenger = Shop::Container()->getDB()->query(
            "SELECT cEmail, cVorname, cNachname, cAnrede, kNewsletterEmpfaenger, kSprache, dEingetragen
             FROM tnewsletterempfaenger
             WHERE nAktiv = 1
               AND kSprache = " . $lang_key . "
               AND dEingetragen > '" . $safeDate . "'
               AND kNewsletterEmpfaenger NOT IN " . $sync_ids,
            9
        );

        foreach ($oNewsletterEmpfaenger as $value) {
            $data[] = [
                $value['cEmail']                ?? '',
                $value['cVorname']              ?? '',
                $value['cNachname']             ?? '',
                $value['kNewsletterEmpfaenger'] ?? '',
                isset($value['kSprache']) ? ($spracheISO[$value['kSprache']] ?? '') : '',
                $value['dEingetragen']          ?? '',
            ];
        }

        return $data;
    }
}
