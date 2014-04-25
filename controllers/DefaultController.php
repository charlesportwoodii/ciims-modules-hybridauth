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
    public $adapter;

    public function getProfile()
    {
        $session = Yii::app()->session;

        if (Cii::get($session, 'adapter', false))
            $session['adapter'] = $this->adapter->getUserProfile();

        return $session['adapter'];
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

		return $this->hybridAuth();
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

        // Connect the adapter
        $this->adapter = $hybridauth->authenticate($this->getProvider(),$params);

        // Proceed if we've been connected
        if ($this->adapter->isUserConnected())
		{
            // Get the profile and store it in the session immediately
            $this->getProfile();

            // If we have an identity on file, then autheticate as that user.
            if (!Yii::app()->user->isGuest && $this->hasIdentity())
            {
                // Authenticate in as that user, then return them to the previous page
                if ($this->authenticate())
                {
                    Yii::app()->user->setFlash('success', Yii::t('HybridAuth.main', ''));
                    $this->redirect(Yii::app()->user->returnUrl);
                }
                else
                    throw new CHttpException(403, Yii::t('HybridAuth.main', 'Identity was established, but we were unable to login to your account. Please try again later'));
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
     * Determines if we can login as a given user using their provided identity
     * @return boolean
     */
    private function hasIdentity()
    {
        $form = new RemoteIdentityForm;
        $form->attributes = array(
            'adapter'  => $this->getProfile(),
            'provider' => $this->getProvider()
        );

        return $form->authenticate();
    }

    /**
     * Authenticates in as the user
     * @return boolean
     */
    private function authenticate()
    {
        // Verify we have the identity before attempting to proceed
        if ($this->hasIdentity())
        {
            $form = new RemoteIdentityForm;
            $form->attributes = array(
                'adapter'  => $this->getProfile(),
                'provider' => $this->getProvider()
            );

            return $form->login();
        }

        return false;
    }

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
            $form->adapter    = $this->getProfile();

            if ($form->save())
            {
                if ($this->authenticate())
                {
                    Yii::app()->user->setFlash('success', Yii::t('ciims.controllers.Site', 'You have successfully registered an account. Before you can login, please check your email for activation instructions'));
                    $this->redirect(Yii::app()->user->returnUrl);
                }
                else
                {
                    // Panic?
                    throw new CHttpException(500, 'Something broke BADLY');
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
            $form->adapter    = $this->getProfile();
            if ($form->save())
            {
                if ($this->authenticate())
                {
                    Yii::app()->user->setFlash('success', Yii::t('ciims.controllers.Site', 'You have successfully registered an account. Before you can login, please check your email for activation instructions'));
                    $this->redirect(Yii::app()->user->returnUrl);
                }
                else
                {
                    // Panic?
                    throw new CHttpException(500, 'Something broke BADLY');
                }
            }
        }

        // Reuse the register form
        $this->render('//site/register', array('model' => $form));
    }
}
