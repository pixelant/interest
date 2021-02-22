<?php
defined('TYPO3_MODE') or die('Access denied.');

(static function () {
    // Register eID
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['rest'] = \Pixelant\Interest\BootstrapDispatcher::class . '::processRequest';

    // Detect and "hijack" REST requests
    if (isset($_SERVER['REQUEST_URI'])) {
        $restRequestBasePath = (string)(getenv(
            'TYPO3_REST_REQUEST_BASE_PATH'
        ) ?: getenv(
            'REDIRECT_TYPO3_REST_REQUEST_BASE_PATH'
        ));

        if ($restRequestBasePath) {
            $restRequestBasePath = '/' . trim($restRequestBasePath, '/');
        }

        $restRequestPrefix = $restRequestBasePath . '/rest/';
        $restRequestPrefixLength = strlen($restRequestPrefix);
        $requestUri = $_SERVER['REQUEST_URI'];

        if (substr($requestUri, 0, $restRequestPrefixLength) === $restRequestPrefix) {
            $_GET['eID'] = 'rest';
        }
    }
})();
