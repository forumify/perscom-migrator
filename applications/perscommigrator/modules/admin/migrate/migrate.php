<?php

namespace IPS\perscommigrator\modules\admin\migrate;

use IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * migrate
 */
class _migrate extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('migrate_manage');
        parent::execute();
    }

    /**
     * @return    void
     */
    protected function manage()
    {
        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__perscommigrator_migrate_migrate');

        try {
            // TODO: delete when releasing
            // Temporary to speed up development without leaking secrets
            $data = json_decode(file_get_contents('/var/www/html/perscom_credentials.json'), true, 512, JSON_THROW_ON_ERROR);
            $apiUrl = $data['api_url'];
            $apiKey = $data['api_key'];
            $perscomId = $data['perscom_id'];
            $authorEmail = $data['author_email'];
        } catch (\Exception $ex) {
            $apiUrl = 'https://api.perscom.io/v1';
            $apiKey = $perscomId = $authorEmail = null;
        }

        $form = new Form(null, 'perscommigrator__migrate');
        $form->addHeader('perscommigrator__migrate_credentials_header');
        $form->add(new Form\Text('api_url', $apiUrl, true, [], null, null, '<span class="ipsFieldRow_desc">If you have a staging environment, use "https://api.staging.perscom.io/v1" instead.</span>'));
        $form->add(new Form\Text('api_key', $apiKey, true, [], null, null, '<span class="ipsFieldRow_desc">Create a new API key on your PERSCOM dashboard, under "System" > "API" > "Keys". <strong>Remember to add all scopes!</strong></span>'));
        $form->add(new Form\Text('perscom_id', $perscomId, true, [], null, null, '<span class="ipsFieldRow_desc">Can be found on your PERSCOM dashboard, under "System" > "Settings".</span>'));
        $form->add(new Form\Text('author_email', $authorEmail, true, [], null, null, '<span class="ipsFieldRow_desc">Email of an <strong>existing</strong> PERSCOM.io user, this user will be used as the author of records.</span>'));

        $form->addHeader('perscommigrator__migrate_personnel_filter');
        $form->add(new Form\Node('status_blacklist', null, false, [
            'class' => '\IPS\perscom\Personnel\Status',
            'multiple' => true,
        ], null, null, '<span class="ipsFieldRow_desc">Select statuses for which personnel should <strong>NOT</strong> be migrated, for example "Civilian".</span>'));

        $values = $form->values();
        if (!$values) {
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('migrate', 'perscommigrator', 'admin')->migrate((string)$form);
            return;
        }

        $apiUrl = $values['api_url'] ?? '';
        $apiKey = $values['api_key'] ?? '';
        $perscomId = $values['perscom_id'] ?? '';

        $api = new \IPS\perscommigrator\Perscom\Api($apiUrl, $apiKey, $perscomId);
        $migrator = new \IPS\perscommigrator\Migrator\Migrator($api);
        $result = $migrator->migrate([
            'status_blacklist' => $values['status_blacklist'] ?: [],
        ]);

        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('migrate', 'perscommigrator', 'admin')->migrateComplete($result);
    }
}
