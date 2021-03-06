<?php

class RemoteIdentityForm extends CFormModel
{
    /**
     * HybridAuth::DefaultController::$adapter->getUserProfile()
     * @var array $adapter
     */
    public $adapter;

    /**
     * The provider name
     * @var string $provider
     */
    public $provider;

    /**
     * The user model
     * @var Users $_user
     */
    private $_user;

    /**
     * The remote user identity
     * @var RemoteUserIdentity $_identity
     */
    private $_identity;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return array(
            array('adapter, provider', 'required'),
            array('adapter', 'validateIdentity')
        );
    }

    /**
     * Validates that we have an identity on file for this user
     * @param array $attributes
     * @param array $params
     * @return boolean
     */
    public function validateIdentity($attributes, $params)
    {
        // Search the database for a user with that information
        $metadata = UserMetadata::model()->findByAttributes(array(
            'key' => $this->provider.'Provider',
            'value' => $this->adapter->identifier
        ));

        // Return an error if we didn't find them
        if ($metadata == NULL)
        {
            $this->addError('adapter', Yii::t('HybridAuth.main', 'Unable to determine local user for identity'));
            return false;
        }

        // Otherwise load that user
        $this->_user = Users::model()->findByPk($metadata->user_id);
        if ($this->_user == NULL)
        {
            $this->addError('adapter', Yii::t('HybridAuth.main', 'Unable to determine local user for identity'));
            return false;
        }

        // And return true
        return true;
    }

    /**
     * Checkes that we can authenticated against the RemoteUser $_identity
     * @return boolean
     */
    public function authenticate()
    {
        if (!$this->validate())
            return false;

        // Load the RemoteUserIdentity model, and return if we successfully could authenticate against it
        $this->_identity = new RemoteUserIdentity($this->adapter, $this->provider, $this->_user);
        return $this->_identity->authenticate();
    }

    /**
     * Logins the user in using their Remote user information
     * @return boolean
     */
    public function login()
    {
        if (!$this->authenticate())
            return false;

        if($this->_identity->errorCode===RemoteUserIdentity::ERROR_NONE)
		{
            Yii::app()->user->logout();
			return Yii::app()->user->login($this->_identity,3600*24);
		}
		else
			return false;

    }
}
