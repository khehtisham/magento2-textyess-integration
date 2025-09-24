<?php
namespace TextYess\Integration\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

/**
 * Backend model for validating TextYess Integration configuration
 *
 * Ensures that when the integration is enabled, all required
 * configuration values (webhook base URL, user ID, HMAC secret)
 * are present and valid.
 */
class ValidateConfig extends Value
{
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $scopeConfig, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Validate required fields before saving the config.
     *
     * @throws LocalizedException if enabling integration without required fields
     * @return Value
     */
    public function beforeSave()
    {
        // Only validate when toggling "enabled" to true
        $enabled = (bool)$this->getData('value');

        if ($enabled) {
            $baseUrl = $this->getFieldValue('webhook_url_base');
            $userId  = $this->getFieldValue('user_id');
            $secret  = $this->getFieldValue('hmac_secret');

            $missing = [];
            if (empty($baseUrl)) $missing[] = 'Webhook Base URL';
            if (empty($userId)) $missing[]  = 'User ID';
            if (empty($secret)) $missing[]  = 'HMAC Secret';

            if ($missing) {
                throw new LocalizedException(
                    __('TextYess Integration cannot be enabled. Missing: %1', implode(', ', $missing))
                );
            }

            // Validate webhook URL format
            if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                throw new LocalizedException(__('Webhook Base URL is not a valid URL.'));
            }
        }

        return parent::beforeSave();
    }

    /**
     * Retrieve the value for a given field, preferring the new value
     * being saved if present, otherwise falling back to current config.
     *
     * @param string $field Field key (e.g. webhook_url_base)
     * @return string|null
     */
    private function getFieldValue(string $field): ?string
    {
        $newValue = $this->getDataByPath('groups/general/fields/' . $field . '/value');
        if ($newValue !== null && $newValue !== '') {
            return trim((string)$newValue);
        }

        return $this->scopeConfig->getValue(
            'textyess_integration/general/' . $field,
            ScopeInterface::SCOPE_STORE
        );
    }
}
