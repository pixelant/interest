<?php

declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HttpBackendUserAuthentication extends BackendUserAuthentication
{
    protected $loginFormData = [];

    /**
     * Construct.
     *
     * @throws \RuntimeException if TYPO3 is in maintenance mode and user can't be logged in.
     */
    public function __construct()
    {
        if (!$this->isUserAllowedToLogin()) {
            throw new \RuntimeException('Login Error: TYPO3 is in maintenance mode at the moment. Only' .
                ' administrators are allowed access.', 1483971855);
        }

        $this->dontSetCookie = true;

        parent::__construct();
    }

    /**
     * Replacement for AbstactUserAuthentication::start()
     *
     * We do not need support for sessions, cookies, $_GET-modes, the postUserLookup hook or
     * a database connectiona during CLI Bootstrap
     */
    public function start()
    {
        $this->logger->debug('## Beginning of auth logging.');
        // svConfig is unused, but we set it, as the property is public and might be used by extensions
        $this->svConfig = $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth'] ?? [];
    }

    /**
     * Logs in the TYPO3 Backend user supplied in HTTP headers.
     *
     * @param bool $proceedIfNoUserIsLoggedIn IGNORED
     */
    public function backendCheckLogin($proceedIfNoUserIsLoggedIn = false)
    {
    }

    /**
     * Logs-in the backend user.
     *
     * @param int $userId The ID of the backend user to log in.
     *
     * @throws \RuntimeException when the user could not log in or it is an admin
     */
    public function authenticate(int $userId)
    {
        $this->setBeUserByUid($userId);

        // The groups are fetched and ready for permission checking in this initialization.
        $this->fetchGroupData();
        $this->backendSetUC();
    }

    /**
     * Checks if a submission of username and password is present or use other authentication by auth services
     *
     * @throws \RuntimeException
     * @internal
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    public function checkAuthentication()
    {
        $tempuser = null;

        $authenticated = false;
        // The info array provide additional information for auth services
        $authInfo = $this->getAuthInfoArray();
        // Get Login/Logout data submitted by a form or params
        $loginData = $this->getLoginFormData();
        $this->logger->debug('Login data', $this->removeSensitiveLoginDataForLoggingInfo($loginData));

        $tempuserArr = [];

        // Use 'auth' service to find the user
        // First found user will be used
        $subType = 'getUser' . $this->loginType;
        foreach ($this->getAuthServices($subType, $loginData, $authInfo) as $serviceObj) {
            $row = $serviceObj->getUser();
            if ($row) {
                $tempuserArr[] = $row;
                $this->logger->debug('User found', [
                    $this->userid_column => $row[$this->userid_column],
                    $this->username_column => $row[$this->username_column],
                ]);
                // User found, just stop to search for more if not configured to go on
                if (empty($this->svConfig['setup'][$this->loginType . '_fetchAllUsers'])) {
                    break;
                }
            }
        }

        if (empty($tempuserArr)) {
            $this->logger->debug('No user found by services');
        } else {
            $this->logger->debug(count($tempuserArr) . ' user records found by services');
        }

        // Authenticate the user if needed
        if (!empty($tempuserArr)) {
            foreach ($tempuserArr as $tempuser) {
                // Use 'auth' service to authenticate the user
                // If one service returns FALSE then authentication failed
                // a service might return 100 which means there's no reason to stop but the user can't be authenticated
                // by that service
                $this->logger->debug('Auth user', $this->removeSensitiveLoginDataForLoggingInfo($tempuser, true));
                $subType = 'authUser' . $this->loginType;

                foreach ($this->getAuthServices($subType, $loginData, $authInfo) as $serviceObj) {
                    $ret = $serviceObj->authUser($tempuser);
                    if ($ret > 0) {
                        // If the service returns >=200 then no more checking is needed - useful for IP checking without
                        // password
                        if ((int)$ret >= 200) {
                            $authenticated = true;
                            break;
                        }
                        if ((int)$ret < 100) {
                            $authenticated = true;
                        }
                    } else {
                        $authenticated = false;
                        break;
                    }
                }

                if ($authenticated) {
                    // Leave foreach() because a user is authenticated
                    break;
                }
            }
        }

        // If user is authenticated a valid user is in $tempuser
        if ($authenticated) {
            // Reset failure flag
            $this->loginFailure = false;
            // Insert session record if needed:

            $this->user = $tempuser;
            // The login session is started.
            $this->loginSessionStarted = true;
            if (is_array($this->user)) {
                $this->logger->debug('User session finally read', [
                    $this->userid_column => $this->user[$this->userid_column],
                    $this->username_column => $this->user[$this->username_column],
                ]);
            }

            // User logged in - write that to the log!
            if ($this->writeStdLog) {
                $this->writelog(
                    255,
                    1,
                    0,
                    1,
                    'User %s logged in from ###IP###',
                    [$tempuser[$this->username_column]],
                    '',
                    '',
                    ''
                );
            }

            $this->logger->debug(
                'User ' . $tempuser[$this->username_column] . ' authenticated from '
                . GeneralUtility::getIndpEnv('REMOTE_ADDR')
            );
        } else {
            $this->loginFailure = true;

            if (empty($tempuserArr)) {
                $logData = [
                    'loginData' => $this->removeSensitiveLoginDataForLoggingInfo($loginData),
                ];
                $this->logger->debug('Login failed', $logData);
            }

            if (!empty($tempuserArr)) {
                $logData = [
                    $this->userid_column => $tempuser[$this->userid_column],
                    $this->username_column => $tempuser[$this->username_column],
                ];
                $this->logger->debug('Login failed', $logData);
            }
        }
    }

    /**
     * Determines whether a backend user is allowed to access TYPO3.
     *
     * @return bool True if $GLOBALS[TYPO3_CONF_VARS][BE][adminOnly] is zero.
     */
    protected function isUserAllowedToLogin()
    {
        return (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['adminOnly'] === 0;
    }

    /**
     * @param array $data
     */
    public function setLoginFormData(array $data)
    {
        $this->loginFormData = $data;
    }

    /**
     * Returns an info array with Login/Logout data from headers
     *
     * @return array
     * @internal
     */
    public function getLoginFormData()
    {
        return $this->loginFormData;
    }
}
