<?php

class DefaultController extends CiiController
{
    /**
     * The Provider name
     * @var string $_provider
     */
    protected $_provider;

    /**
     * The HybridAuth Adapter
     * @var HybridAdapter $adapter
     */
    private $_adapter = NULL;

    /**
     * The profile data
     * @param array $_userProfile
     */
    private $_userProfile = NULL;

    /**
     * Retrieves the HybridAuth session ID
     * @return mixed
     */
    private function getSession()
    {
        if (isset($_SESSION['HA::CONFIG']['php_session_id']))
            return unserialize($_SESSION['HA::CONFIG']['php_session_id']);

        return false;
    }

    /**
     * Sets the HybridAuth adapter
     * @param Hybrid_Provider_Adapter $adapter
     * @return Hybrid_Provider_Adapter
     */
    public function setAdapter($adapter)
    {
        return $this->_adapter = $adapter;
    }

    /**
     * Retrieves the HybridAuth Adapter from $_SESSION
     * Don't call getAdapter before setAdapter. Bad vudo if you do
     * @return Hybrid_Provider_Adapter
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Caches the getUserProfile request to prevent rate limiting issues.
     * @return object
     */
    public function getUserProfile()
    {
        if ($this->_userProfile == NULL)
            $this->_userProfile = $this->getAdapter()->getUserProfile();

        return $this->_userProfile;
    }

    /**
     * Sets the provider for this controller to use
     * @param string $provider The Provider Name
     * @return $provider
     */
    public function setProvider($provider=NULL)
    {
        // Prevent the provider from being NULL
        if ($provider == NULL)
            throw new CException(Yii::t('Hybridauth.main', "You haven't supplied a provider"));

        // Set the property
        $this->_provider = $provider;

        return $this->_provider;
    }

    /**
     * Retrieves the provider name
     * @return string $this->_provider;
     */
    public function getProvider()
    {
        return $this->_provider;
    }

	/**
	 * Disable filters. This should always return a valid non 304 response
	 */
	public function filters()
	{
		return array();
	}

    /**
     * Initialization path
     * @param string $provider     The HybridAuth provider
     * @return void
     */
	public function actionIndex($provider=NULL)
	{
        // Set the provider
        $this->setProvider($provider);

        if (isset($_GET['hauth_start']) || isset($_GET['hauth_done']))
            Hybrid_Endpoint::process();

        try {
		    $this->hybridAuth();
        } catch (Exception $e) {
            throw new CHttpException(400, $e->getMessage());
        }
	}

	/**
     * Handles authenticating the user against the remote identity
	 */
	private function hybridAuth()
	{
        // Preload some configuration options
        if (strtolower($this->getProvider()) == 'openid')
		{
			if (!isset($_GET['openid-identity']))
				throw new CException(Yii::t('Hybridauth.main', "You chose OpenID but didn't provide an OpenID identifier"));
			else
				$params = array("openid_identifier" => $_GET['openid-identity']);
		}
		else
			$params = array();

        // Load HybridAuth
        $hybridauth = new Hybrid_Auth(Yii::app()->controller->module->getConfig());

        if (!$this->adapter)
            $this->setAdapter($hybridauth->authenticate($this->getProvider(),$params));

        // Proceed if we've been connected
        if ($this->adapter->isUserConnected())
		{
            // If we have an identity on file, then autheticate as that user.
            if ($this->authenticate())
            {
                Yii::app()->user->setFlash('success', Yii::t('HybridAuth.main', 'You have been sucessfully logged in!'));
                if (isset($_GET['next']))
                    $this->redirect($this->createUrl($_GET['next']));
                $this->redirect(Yii::app()->getBaseUrl(true));
            }
            else
            {
                // If we DON'T have information about this user already on file
                // If they're not a guest, present them with a form to link their accounts
                // Otherwise present them with a registration form
                // We want remote users to have their own identity, rather than just dangling and not being able to actually interact with our site
                if (!Yii::app()->user->isGuest)
                    $this->renderLinkForm();
                else
                    $this->renderRegisterForm();
            }
        }
        else
            throw new CHttpException(403, Yii::t('HybridAuth.main', 'Failed to establish remote identity'));
	}

    /**
     * Authenticates in as the user
     * @return boolean
     */
    private function authenticate()
    {
        $form = new RemoteIdentityForm;
        $form->attributes = array(
            'adapter'  => $this->getUserProfile(),
            'provider' => $this->getProvider()
        );

        return $form->login();
    }

    /**
     * Renders the linking form
     */
    private function renderLinkForm()
    {
        $this->layout = '//layouts/main';

		$this->setPageTitle(Yii::t('HybridAuth.main', '{{app_name}} | {{label}}', array(
			'{{app_name}}' => Cii::getConfig('name', Yii::app()->name),
            '{{label}}'    => Yii::t('HybridAuth.main', 'Link Your Account')
		)));

        $form = new RemoteLinkAccountForm;

        if (Cii::get($_POST, 'RemoteLinkAccountForm', false))
        {
            // Populate the model
            $form->attributes = Cii::get($_POST, 'RemoteLinkAccountForm', false);
            $form->provider   = $this->getProvider();
            $form->adapter    = $this->getUserProfile();

            if ($form->save())
            {
                if ($this->authenticate())
                {
                    Yii::app()->user->setFlash('success', Yii::t('HybridAuth.main', 'You have successfully logged in via {{provider}}', array('{{provider}}' => $this->getProvider() )));
                    $this->redirect(Yii::app()->user->returnUrl);
                }
            }
        }

        // Reuse the register form
        $this->render('/linkaccount', array('model' => $form));
    }

    /**
     * Provides functionality to register a user from a remote user identity
     * This method reuses the //site/register form and the RegisterForm model
     */
    private function renderRegisterForm()
    {
        $this->layout = '//layouts/main';

		$this->setPageTitle(Yii::t('HybridAuth.main', '{{app_name}} | {{label}}', array(
			'{{app_name}}' => Cii::getConfig('name', Yii::app()->name),
            '{{label}}'    => Yii::t('HybridAuth.main', 'Register an Account From {{provider}}', array(
                                  '{{provider}}' => $this->getProvider()
                              ))
		)));

        $form = new RemoteRegistrationForm;

        if (Cii::get($_POST, 'RemoteRegistrationForm', false))
        {
            // Populate the model
            $form->attributes = Cii::get($_POST, 'RemoteRegistrationForm', false);
            $form->provider   = $this->getProvider();
            $form->adapter    = $this->getUserProfile();

            if ($form->save())
            {
                if ($this->authenticate())
                {
                    Yii::app()->user->setFlash('success', Yii::t('HybridAuth.main', 'You have successfully logged in via {{provider}}', array('{{provider}}' => $this->getProvider() )));
                    $this->redirect(Yii::app()->user->returnUrl);
                }
            }
        }

        // Reuse the register form
        $this->render('//site/register', array('model' => $form));
    }
}
