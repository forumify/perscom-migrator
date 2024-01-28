<?php

namespace IPS\perscommigrator\modules\admin\settings;

use IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('settings_manage');
        parent::execute();
    }

    /**
     * ...
     *
     * @return    void
     */
    protected function manage()
    {
        $form = new Form();
        $form->addHeader('perscommigrator__migrate_credentials_header');
        $form->add(new Form\Text('perscommigrator_api_url', \IPS\Settings::i()->perscommigrator_api_url, true, [], null, null, '<span class="ipsFieldRow_desc">If you have a staging environment, use "https://api.staging.perscom.io/v1" instead.</span>'));
        $form->add(new Form\Text('perscommigrator_api_key', \IPS\Settings::i()->perscommigrator_api_key, true, [], null, null, '<span class="ipsFieldRow_desc">Create a new API key on your PERSCOM dashboard, under "System" > "API" > "Keys". <strong>Remember to add all scopes!</strong></span>'));
        $form->add(new Form\Text('perscommigrator_perscom_id', \IPS\Settings::i()->perscommigrator_perscom_id, true, [], null, null, '<span class="ipsFieldRow_desc">Can be found on your PERSCOM dashboard, under "System" > "Settings".</span>'));
        $form->add(new Form\Text('perscommigrator_author_email', \IPS\Settings::i()->perscommigrator_author_email, true, [], null, null, '<span class="ipsFieldRow_desc">Email of an <strong>existing</strong> PERSCOM.io user, this user will be used as the author of records.</span>'));

        if ($values = $form->values()) {
            $form->saveAsSettings($values);
        }

        \IPS\Output::i()->output = $form;
    }
}
