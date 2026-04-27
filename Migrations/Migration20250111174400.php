<?php declare(strict_types=1);

namespace Plugin\rapidmail_newsletter\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20250111174400 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailUsername','');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailPassword','');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'loginValid','0');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailRecipientListId0','');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailRecipientListId1','');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'listsValid','0');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`) 
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'step','1');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`)
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailExchangeRegistration', '0');
            INSERT INTO `tplugineinstellungen`(`kPlugin`, `cName`, `cWert`)
                VALUES ((SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1), 'rapidMailExchangeDeRegistration', '0');
            ");
        $this->execute(
            "CREATE TABLE `rapidmail_sync_table` (`id` INT(11) AUTO_INCREMENT, `kNewsletterempfaenger` INT(11) NOT NULL, `kInternalListId` INT(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;"
        );
    }

    public function down()
    {
        if($this->doDeleteData()){
            $this->execute("DELETE FROM tplugineinstellungen
                            WHERE kPlugin = (SELECT kPlugin FROM tplugin WHERE cPluginID = 'rapidmail_newsletter' LIMIT 1)");
            $this->execute('DROP TABLE IF EXISTS `rapidmail_sync_table`');
        }    
            
    }
}