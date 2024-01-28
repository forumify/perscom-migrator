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

        $form = new Form(null, 'perscommigrator__migrate');
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

        $api = new \IPS\perscommigrator\Perscom\Api();
        $migrator = new \IPS\perscommigrator\Migrator\Migrator($api);
        $result = $migrator->migrate([
            'status_blacklist' => $values['status_blacklist'] ?: [],
        ]);

        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('migrate', 'perscommigrator', 'admin')->migrateComplete($result);
    }
}
