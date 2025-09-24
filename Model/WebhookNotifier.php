<?php
namespace TextYess\Integration\Model;

use Psr\Log\LoggerInterface;
use TextYess\Integration\Model\Config;

class WebhookNotifier
{
    protected Config $config;
    protected LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Send payload to TextYess webhook endpoint.
     *
     * @param string $topic      The webhook topic (e.g., orders/create)
     * @param array  $payload    Data to send in the request body
     * @param string $action     Optional action path appended to URL
     * @param string|null $overrideUrl Manually override the target URL
     *
     * @return bool True if webhook delivered successfully, false otherwise
     */
    public function send(string $topic, array $payload, string $action = '', ?string $overrideUrl = null): bool
    {
        // Skip if integration is disabled
        if (!$this->config->isEnabled()) {
            $this->logInfo('[TextYess] Integration disabled — skipping webhook send.', ['topic' => $topic]);
            return false;
        }

        // Encode payload to JSON
        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($rawBody === false) {
            $this->logError('[TextYess] Failed to json_encode payload', ['error' => json_last_error_msg()]);
            return false;
        }

        // Load required configuration values
        $baseUrl    = $this->config->getWebhookUrlBase();
        $hmacSecret = $this->config->getHmacSecret();
        $userId     = $this->config->getUserId();

        if (empty($baseUrl) || empty($hmacSecret) || empty($userId)) {
            $this->logWarning('[TextYess] Missing webhook config values — skipping send.', [
                'baseUrl'    => $baseUrl ?: 'MISSING',
                'hmacSecret' => $hmacSecret ? 'SET' : 'MISSING',
                'userId'     => $userId ?: 'MISSING',
            ]);
            return false;
        }

        // Construct final URL
        $url = $overrideUrl ?: rtrim($baseUrl, '/') . '/' . trim($action, '/') . '/' . $userId;

        // Generate HMAC signature for security
        $hmacSignature = base64_encode(hash_hmac('sha256', $rawBody, $hmacSecret, true));

        $headers = [
            "Content-Type: application/json",
            "x-magento-hmac-sha256: {$hmacSignature}",
            "x-magento-topic: {$topic}",
            "x-textyess-user: {$userId}"
        ];

        try {
            // Perform cURL request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Check success
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logInfo('[TextYess] Webhook sent successfully.', [
                    'topic'    => $topic,
                    'url'      => $url,
                    'status'   => $httpCode,
                    'response' => $response
                ]);
                return true;
            }

            // Log failure
            $this->logError('[TextYess] Webhook failed.', [
                'topic'     => $topic,
                'url'       => $url,
                'status'    => $httpCode,
                'response'  => $response,
                'payload'   => $rawBody,
                'curlError' => $curlError ?: null
            ]);

        } catch (\Throwable $e) {
            $this->logError('[TextYess] Exception while sending webhook.', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString()
            ]);
        }

        return false;
    }

    /**
     * Wrapper for info logs that respects "log enabled" config.
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->config->isLogEnabled()) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Wrapper for warning logs that respects "log enabled" config.
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->config->isLogEnabled()) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Wrapper for error logs that respects "log enabled" config.
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->config->isLogEnabled()) {
            $this->logger->error($message, $context);
        }
    }
}
