<?php

namespace Nealis\Db2Identity;

use Nealis\As400Utils\Command\CHGLIBL;
use Nealis\Identity\Identity;
use Nealis\Identity\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Db2Identity extends Identity
{

    /** @var  string */
    protected $defaultLibl = '';

    /** @var  string */
    protected $defaultSysinf = '';

    /** @var  CHGLIBL */
    protected $CHGLIBL;

    /**
     * Identity constructor.
     * @param SessionInterface $session
     * @param UserRepositoryInterface $userRepository
     * @param CHGLIBL $CHGLIBL
     */
    public function __construct(SessionInterface $session, UserRepositoryInterface $userRepository, $CHGLIBL = null)
    {
        parent::__construct($session, $userRepository);
        $this->CHGLIBL = $CHGLIBL;
    }

    /**
     * @param string $user
     * @param string $sysinf
     * @return bool
     */
    public function initialize($user = '', $sysinf = '')
    {
        $user = !empty($user) ? $user : $this->defaultUser;
        $sysinf = !empty($sysinf) ? $sysinf : $this->defaultSysinf;

        if ($this->phpOs === 'AIX') $this->setLibraryList($this->defaultLibl);

        $hasIdentity = ($this->isLoggedIn() || !$this->authCheck) && $this->hasIdentity();

        if (!$hasIdentity) {
            if ($this->cli) {
                $hasIdentity = $this->initCli($user, $sysinf);
            } else {
                $hasIdentity = $this->authCheck ? $this->init($sysinf) : $this->initCli($user, $sysinf);
            }
        }

        if ($hasIdentity) {
            $this->setLibraryList();
        }

        return $hasIdentity;
    }

    /**
     * @param string $sysinf
     * @return bool
     */
    protected function init($sysinf = '')
    {
        $hasIdentity = false;

        if ($this->isLoggedIn()) {
            $hasIdentity = $this->initCli($this->getUsername(), $sysinf);
        } else {
            $this->invalidateSession();
        }

        $this->saveSession();

        return $hasIdentity;
    }

    /**
     * @param string $username
     * @param string $sysinf
     * @return bool
     */
    protected function initCli($username = self::DEFAULT_USERNAME, $sysinf = '')
    {
        $sysinfLibraries = $this->readLibraryList($sysinf);
        $this->initSession($username, $sysinf, $sysinfLibraries);
        $this->session->set('auth/identity', true);
        return $this->hasIdentity();
    }

    /**
     * @param string $username
     * @param string $sysinf
     * @param array $libraries
     * @return array
     */
    protected function getSessionData($username = self::DEFAULT_USERNAME, $sysinf = '', $libraries = [])
    {
        return [
            'userid' => $this->userRepository->getIdByUsername($username) ? : 0,
            'username' => $username,
            'signature' => $this->userRepository->getSignatureByUsername($username) ? : '',
            'sysinf' => $sysinf,
            'libraries' => $libraries,
            'locale' => $this->getUserLocale($username),
        ];
    }

    /**
     * @param string $username
     * @param string $sysinf
     * @param array $libraries
     */
    protected function initSession($username = self::DEFAULT_USERNAME, $sysinf = '', $libraries = [])
    {
        $data = $this->getSessionData($username, $sysinf, $libraries);
        $this->initSessionData($data);
        $this->initAppSessionData($username);
    }


    /**
     * @param string $defaultLibl
     */
    public function setDefaultLibl($defaultLibl)
    {
        $this->defaultLibl = $defaultLibl;
    }

    /**
     * @param string $defaultSysinf
     */
    public function setDefaultSysinf($defaultSysinf)
    {
        $this->defaultSysinf = $defaultSysinf;
    }

    /**
     * @param $sysinf
     * @return array
     */
    protected function readLibraryList($sysinf)
    {
        return $this->userRepository->readLibrariesBySysinf($sysinf);
    }

    /**
     * @return string|null
     */
    public function getLibraryList()
    {
        return $this->session->get('auth')['libraries'];
    }

    /**
     * @param array|null $libraries
     * @throws \Exception
     */
    protected function setLibraryList($libraries = null)
    {
        if ($libraries === null) {
            $libraries = $this->getLibraryList();
        }
        if (is_array($libraries)) {
            $libraries = implode(' ', $libraries);
        }
        //CHGLIBLE
        if (!empty($libraries)) {
            $this->CHGLIBL->execute($libraries);
        }
    }
}
