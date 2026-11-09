<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * External 3DS challenge return (POST from MPI). No CSRF — issuer posts here.
 * Relays TransactionId to parent via postMessage.
 */
class PbconnectThreedsreturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent(): void
    {
        $this->ajax = true;
        $this->display_header = false;
        $this->display_footer = false;
        $txid = (string) (
            Tools::getValue('TransactionId')
            ?: Tools::getValue('transactionId')
            ?: ($_REQUEST['TransactionId'] ?? '')
        );
        $safe = json_encode($txid, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>3DS</title></head><body>';
        echo '<script>';
        echo 'try{';
        echo 'if(window.parent&&window.parent!==window){';
        echo 'window.parent.postMessage({source:"pb-connect-vindi-3ds",TransactionId:' . $safe . '},"*");';
        echo '}';
        echo '}catch(e){}';
        echo '</script>';
        echo '<p>Autenticação concluída. Você pode fechar esta janela.</p>';
        echo '</body></html>';
        exit;
    }
}
