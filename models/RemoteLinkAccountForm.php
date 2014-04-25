<?php

class RemoteLinkAccountForm extends CFormModel
{
    /**
     * The user's current password
     * @var string $password
     */
    public $password;

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
     * User model
     * @param Users $_user
     */
    private $_user;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return array(
            array('password, adapter, provider', 'required'),
            array('password', 'length', 'min' => 8),
            array('password', 'validateUserPassword')
        );
    }

    public function attributeLabels()
    {
        return array(
            'password' => Yii::t('HybridAuth.main', 'Your Current Password')
        );
    }

    /**
     * Ensures that the password entered matches the one provided during registration
     * @param array $attributes
     * @param array $params
     * return array
     */
    public function validateUserPassword($attributes, $params)
    {
        $this->_user = Users::model()->findByPk(Yii::app()->user->id);

        if ($this->_user == NULL)
        {
            $this->addError('password', Yii::t('HybridAuth.main', 'Unable to identify user.'));
            return false;
        }

        $hash = Users::model()->encryptHash($this->_user->email, $this->password, Yii::app()->params['encryptionKey']);

        $result = password_verify($hash, $this->_user->password);

        if ($result == false)
        {
            $this->addError('password', Yii::t('HybridAuth.main', 'The password you entered is invalid.'));
            return false;
        }

        return true;
    }

    /**
     * Bind's the user identity to the mdoel
     * @return boolean
     */
    public function save()
    {
        if (!$this->validate())
            return false;

        $meta = new UserMetadata;
        $meta->attributes = array(
            'user_id' => $this->_user->id,
            'key' => $this->provider.'Provider',
            'value' => $this->adapter->identifier
        );

        // Save the associative object
        return $meta->save();
    }
}
