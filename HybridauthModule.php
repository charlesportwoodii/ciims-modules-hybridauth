<?php

class HybridauthModule extends CWebModule
{
    /**
     * Providers populated from Yii::app()->config
     * @var array $provders
     */
    public $providers;

	public function init()
    {
		// this method is called when the module is being created
		// you may place code here to customize the module or the application
		// import the module-level models and components
		$this->setImport(array(
			'hybridauth.models.*',
			'hybridauth.components.*',
		));

		Yii::app()->setComponents(array(
            'messages' => array(
                'class' => 'ext.cii.components.CiiPHPMessageSource',
                'basePath' => Yii::getPathOfAlias('application.modules.hybridauth')
            )
        ));
	}

	/**
	 * Convert configuration to an array for Hybrid_Auth, rather than object properties as supplied by Yii
	 * @return array
	 */
	public function getConfig()
    {
		return array(
			'baseUrl' => Yii::app()->getBaseUrl(true),
			'base_url' => Yii::app()->getBaseUrl(true) . '/hybridauth/callback', // URL for Hybrid_Auth callback
			'providers' => CMap::mergeArray($this->providers, Cii::getHybridAuthProviders()),
		);
	}
}
