<?php declare(strict_types=1);

namespace Plugin\rapidmail_newsletter;

use JTL\Widgets\AbstractWidget;
use JTL\Shop;
use JTL\Plugin\PluginInterface;

/**
 * Class TestWidget
 * @package Plugin\jtl_test
 */
class RapidmailWidget extends AbstractWidget
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->oSmarty->assign('login_valid', false)
            ->assign('list_valid', false)
            ->assign('reg_valid', false)
            ->assign('dereg_valid', false);

        
        if($this->getDB()->select('tplugineinstellungen', 'cName', 'loginValid')->cWert == 1){
            $this->oSmarty->assign('login_valid', true);
        }
        if($this->getDB()->select('tplugineinstellungen', 'cName', 'listsValid')->cWert == 1){
            $this->oSmarty->assign('lists_valid', true);
        }
        if($this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeRegistration')->cWert == 1){
            $this->oSmarty->assign('reg_valid', true);
        }
        if($this->getDB()->select('tplugineinstellungen', 'cName', 'rapidMailExchangeDeRegistration')->cWert == 1){
            $this->oSmarty->assign('dereg_valid', true);
        }

        $plugin = $this->getPlugin();
        $backendURL = \method_exists($plugin->getPaths(), 'getBackendURL')
            ? $plugin->getPaths()->getBackendURL()
            : Shop::getAdminURL() . '/plugin.php?kPlugin=' . $plugin->getID();

        $this->oSmarty->assign('plugin_path', $backendURL);
    }

    /**
     * @inheritDoc
     */
    public function getContent()
    {
        return $this->oSmarty->fetch(__DIR__ . '/RapidmailWidget.tpl');
    }
}