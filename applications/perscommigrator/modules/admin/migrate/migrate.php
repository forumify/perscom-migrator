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
        $apiKey =
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5YTMwNjk4YS01Yzk3LTRiMTQtOTE5Yi1lNGEwYWZiYmE0YWIiLCJqdGkiOiI5MmI3ZjMxMjM3ZDBiODFkM2UxNDJjY2IyNWJjMTMwY2FiYTViMGNiNTJkMDE3MzUyZTg4MDliZTgxMTFhNjcwOTgzMWFmNTk4YWNjYWMyMiIsImlhdCI6MTY5NTMyNzg1Ny44MDM4MTIsIm5iZiI6MTY5NTMyNzg1Ny44MDM4MTQsImV4cCI6MTcyNjk1MDI1Ny44MDIzMDUsInN1YiI6IjEiLCJzY29wZXMiOlsiY3JlYXRlOmFubm91bmNlbWVudCIsImNyZWF0ZTphc3NpZ25tZW50cmVjb3JkIiwiY3JlYXRlOmF0dGFjaG1lbnQiLCJjcmVhdGU6YXdhcmQiLCJjcmVhdGU6YXdhcmRyZWNvcmQiLCJjcmVhdGU6Y2FsZW5kYXIiLCJjcmVhdGU6Y29tYmF0cmVjb3JkIiwiY3JlYXRlOmRvY3VtZW50IiwiY3JlYXRlOmV2ZW50IiwiY3JlYXRlOmZpZWxkIiwiY3JlYXRlOmZvcm0iLCJjcmVhdGU6Z3JvdXAiLCJjcmVhdGU6aW1hZ2UiLCJjcmVhdGU6bWFpbCIsImNyZWF0ZTpwZXJtaXNzaW9uIiwiY3JlYXRlOnBvc2l0aW9uIiwiY3JlYXRlOnF1YWxpZmljYXRpb24iLCJjcmVhdGU6cXVhbGlmaWNhdGlvbnJlY29yZCIsImNyZWF0ZTpyYW5rIiwiY3JlYXRlOnJhbmtyZWNvcmQiLCJjcmVhdGU6cm9sZSIsImNyZWF0ZTpzZXJ2aWNlcmVjb3JkIiwiY3JlYXRlOnNwZWNpYWx0eSIsImNyZWF0ZTpzdGF0dXMiLCJjcmVhdGU6c3RhdHVzcmVjb3JkIiwiY3JlYXRlOnN1Ym1pc3Npb24iLCJjcmVhdGU6dGFzayIsImNyZWF0ZTp1bml0IiwiY3JlYXRlOnVzZXIiLCJkZWxldGU6YW5ub3VuY2VtZW50IiwiZGVsZXRlOmFzc2lnbm1lbnRyZWNvcmQiLCJkZWxldGU6YXdhcmQiLCJkZWxldGU6YXdhcmRyZWNvcmQiLCJkZWxldGU6Y2FsZW5kYXIiLCJkZWxldGU6Y29tYmF0cmVjb3JkIiwiZGVsZXRlOmRvY3VtZW50IiwiZGVsZXRlOmV2ZW50IiwiZGVsZXRlOmZpZWxkIiwiZGVsZXRlOmZvcm0iLCJkZWxldGU6Z3JvdXAiLCJkZWxldGU6bWFpbCIsImRlbGV0ZTpwZXJtaXNzaW9uIiwiZGVsZXRlOnBvc2l0aW9uIiwiZGVsZXRlOnF1YWxpZmljYXRpb24iLCJkZWxldGU6cXVhbGlmaWNhdGlvbnJlY29yZCIsImRlbGV0ZTpyYW5rIiwiZGVsZXRlOnJhbmtyZWNvcmQiLCJkZWxldGU6cm9sZSIsImRlbGV0ZTpzZXJ2aWNlcmVjb3JkIiwiZGVsZXRlOnNwZWNpYWx0eSIsImRlbGV0ZTpzdGF0dXMiLCJkZWxldGU6c3RhdHVzcmVjb3JkIiwiZGVsZXRlOnN1Ym1pc3Npb24iLCJkZWxldGU6dGFzayIsImRlbGV0ZTp1bml0IiwiZGVsZXRlOnVzZXIiLCJlbWFpbCIsImltcGVyc29uYXRlOnVzZXIiLCJtYW5hZ2U6YXBpIiwibWFuYWdlOmJpbGxpbmciLCJtYW5hZ2U6bmV3c2ZlZWQiLCJtYW5hZ2U6d2ViaG9vayIsIm5vdGU6dXNlciIsIm9wZW5pZCIsInByb2ZpbGUiLCJ1cGRhdGU6YW5ub3VuY2VtZW50IiwidXBkYXRlOmFzc2lnbm1lbnRyZWNvcmQiLCJ1cGRhdGU6YXdhcmQiLCJ1cGRhdGU6YXdhcmRyZWNvcmQiLCJ1cGRhdGU6Y2FsZW5kYXIiLCJ1cGRhdGU6Y29tYmF0cmVjb3JkIiwidXBkYXRlOmRvY3VtZW50IiwidXBkYXRlOmV2ZW50IiwidXBkYXRlOmZpZWxkIiwidXBkYXRlOmZvcm0iLCJ1cGRhdGU6Z3JvdXAiLCJ1cGRhdGU6bWFpbCIsInVwZGF0ZTpwZXJtaXNzaW9uIiwidXBkYXRlOnBvc2l0aW9uIiwidXBkYXRlOnF1YWxpZmljYXRpb24iLCJ1cGRhdGU6cXVhbGlmaWNhdGlvbnJlY29yZCIsInVwZGF0ZTpyYW5rIiwidXBkYXRlOnJhbmtyZWNvcmQiLCJ1cGRhdGU6cm9sZSIsInVwZGF0ZTpzZXJ2aWNlcmVjb3JkIiwidXBkYXRlOnNwZWNpYWx0eSIsInVwZGF0ZTpzdGF0dXMiLCJ1cGRhdGU6c3RhdHVzcmVjb3JkIiwidXBkYXRlOnN1Ym1pc3Npb24iLCJ1cGRhdGU6dGFzayIsInVwZGF0ZTp1bml0IiwidXBkYXRlOnVzZXIiLCJ2aWV3OmFubm91bmNlbWVudCIsInZpZXc6YXNzaWdubWVudHJlY29yZCIsInZpZXc6YXdhcmQiLCJ2aWV3OmF3YXJkcmVjb3JkIiwidmlldzpjYWxlbmRhciIsInZpZXc6Y29tYmF0cmVjb3JkIiwidmlldzpkb2N1bWVudCIsInZpZXc6ZXZlbnQiLCJ2aWV3OmZpZWxkIiwidmlldzpmb3JtIiwidmlldzpncm91cCIsInZpZXc6bG9nIiwidmlldzptYWlsIiwidmlldzpwZXJtaXNzaW9uIiwidmlldzpwb3NpdGlvbiIsInZpZXc6cXVhbGlmaWNhdGlvbiIsInZpZXc6cXVhbGlmaWNhdGlvbnJlY29yZCIsInZpZXc6cmFuayIsInZpZXc6cmFua3JlY29yZCIsInZpZXc6cm9sZSIsInZpZXc6c2VydmljZXJlY29yZCIsInZpZXc6c3BlY2lhbHR5IiwidmlldzpzdGF0dXMiLCJ2aWV3OnN0YXR1c3JlY29yZCIsInZpZXc6c3VibWlzc2lvbiIsInZpZXc6dGFzayIsInZpZXc6dW5pdCIsInZpZXc6dXNlciJdfQ.igBRRURN_IycztI1K5Gpi0wQjDxhBYZcZRUfkXCE7kTTDS--AZgQ_QSITye3624FO3UlLKy6YIQBMUltxnERX6KHzhuaFq_0FWVtptDojHZIqZnecMJ6qYq3JerCpF0E-xcYc9nXAS_Z5WH-TxyzyAZO5wA7gGomF2cSr3ANDJt7teeKOK_8H6Er7oe13B3U1CBWCZ6YBgUsm8EsrfMz70vn1sm2ZRT3BvUjZAIc8jObAbNOAg3q4fTc_pRIl0nVSr8Cn0y-2v1aLdaj1PSAIwGPNTJbEidDqk-ZBaaYRExP_gCb3AlEvZ396oyPbr4I3if0gCgLh32Jdk85BGxIEnS_sm8YX4Bu4th5m3b4rpWR-UwGYuND9vEblzpzS_w1uPnxGBYyE5oamhI6b0c_8bAgwbqyRTxVA_420e70HxmABnbuPPa9iOxaalxHOApBSzYdvpY2VrM4bqkFLFF3wJDe9FGWdfyZy-RqitxhRwIapTHRqM7MVXX8WyvCjPVqnbKnlw07xM0lrpBQtGK2ldIDFkuErtPrVjULkQc_Z5obpfz_MbRdGvC6DR-Ux7PLrbWvpc5NnmFo2Z3KFAcExLpzdgIuGbn2LZWtJ-8eZIt00nGcbxyUkXwnP_V8FxGWV5ob-PMp6YgE7oJt47CwTR4ayQxKV5XJWPh6HArcDB8';
        $perscomId = '20';

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__perscommigrator_migrate_migrate');

        $form = new Form(null, 'perscommigrator__migrate');
        $form->addHeader('perscommigrator__migrate_credentials_header');
        $form->add(new Form\Text('api_url', 'https://api.staging.perscom.io/v1', true));
        $form->add(new Form\Text('api_key', $apiKey, true, [], null, null, '<span class="ipsFieldRow_desc">Create a new API key on your PERSCOM dashboard, under "System" > "API" > "Keys". <strong>Remember to add all scopes!</strong></span>'));
        $form->add(new Form\Text('perscom_id', $perscomId, true, [], null, null, '<span class="ipsFieldRow_desc">Can be found on your PERSCOM dashboard, under "System" > "Settings".</span>'));

        $values = $form->values();
        if (!$values) {
            \IPS\Output::i()->output = $form;
            return;
        }

        $apiUrl = $values['api_url'] ?? '';
        $apiKey = $values['api_key'] ?? '';
        $perscomId = $values['perscom_id'] ?? '';

        $api = new \IPS\perscommigrator\Perscom\Api($apiUrl, $apiKey, $perscomId);
        $migrator = new \IPS\perscommigrator\Migrator\Migrator($api);
        $result = $migrator->migrate();

        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('migrate', 'perscommigrator', 'admin')->migrateComplete($result);
    }
}
