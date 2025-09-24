<?php
namespace TextYess\Integration\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED      = 'textyess_integration/general/enabled';
    private const XML_PATH_WEBHOOK_BASE = 'textyess_integration/general/webhook_url_base';
    private const XML_PATH_HMAC_SECRET  = 'textyess_integration/general/hmac_secret';
    private const XML_PATH_USER_ID      = 'textyess_integration/general/user_id';
    private const XML_PATH_DEBUG        = 'textyess_integration/general/debug';

    protected ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check whether the integration is enabled for the given store.
     *
     * @param int|string|null $store Store ID or code (null for default)
     * @return bool
     */
    public function isEnabled($store = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the base webhook URL for sending requests.
     * Falls back to default TextYess gateway URL if not configured.
     *
     * @param int|string|null $store Store ID or code (null for default)
     * @return string
     */
    public function getWebhookUrlBase($store = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_BASE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 'https://gateway.textyess.com/webhooks/magento/orders';
    }

    /**
     * Get the HMAC secret key used for request signing.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getHmacSecret($store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_HMAC_SECRET,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the TextYess User ID (used in webhook URLs).
     *
     * @param int|string|null $store
     * @return string
     */
    public function getUserId($store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_USER_ID,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check whether debug logging is enabled.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isLogEnabled($store = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
