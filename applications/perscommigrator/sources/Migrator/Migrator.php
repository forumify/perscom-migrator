<?php

namespace IPS\perscommigrator\Migrator;

class _migrator
{
    /**
     * @var \IPS\perscommigrator\Perscom\_api
     */
    protected $api;

    /**
     * @var _migrateResult
     */
    protected $migrateResult;

    /**
     * @var _perscomCache
     */
    protected $cache;

    public function __construct(\IPS\perscommigrator\Perscom\Api $api)
    {
        $this->api = $api;
        $this->cache = new \IPS\perscommigrator\Migrator\PerscomCache();
    }

    public function migrate(array $personnelFilters): \IPS\perscommigrator\Migrator\MigrateResult
    {
        $this->migrateResult = (new \IPS\perscommigrator\Migrator\MigrateResult())->start();

        try {
            $this->migrateAwards();
            $this->migratePositions();
            $this->migrateQualifications();
            $this->migrateRanks();
            $this->migrateSpecialties();
            $this->migrateStatuses();
            $this->migrateUsers($personnelFilters);
        } catch (\Exception $ex) {
            $genericError = new \IPS\perscommigrator\Migrator\ResultItem('');
            $genericError->errorMessages[] = $ex->getMessage();
            $this->migrateResult->items[] = $genericError;
        }

        return $this->migrateResult->end();
    }

    protected function migrateAwards(): void
    {
        $existingAwards = array_map('mb_strtolower', array_column($this->getExistingItems('awards'), 'name'));
        $transform = static function ($award) {
            return [
                'name' => $award->name,
                'description' => $award->desc,
            ];
        };

        $this->migrateItems(\IPS\perscom\Awards\Category::roots(null), 'awards', $this->fieldNotInArray('name', $existingAwards), $transform);
    }

    protected function migratePositions(): void
    {
        $existingPositions = array_map('mb_strtolower', array_column($this->getExistingItems('positions'), 'name'));
        $transform = static function ($position) {
            $prefix = '';
            if ($position instanceof \IPS\perscom\Units\AdministrativeUnitPosition) {
                $prefix = $position->get_unit()->name . ' - ';
            }

            return [
                'name' => $prefix . $position->name,
                'description' => '',
            ];
        };

        $this->migrateItems(\IPS\perscom\Units\AdministrativeUnitPosition::roots(null), 'positions', $this->fieldNotInArray('name', $existingPositions), $transform);
        $this->migrateItems(\IPS\perscom\Units\CombatUnitPosition::roots(null), 'positions', $this->fieldNotInArray('name', $existingPositions), $transform);
    }

    protected function migrateQualifications(): void
    {
        $existingQualifications = array_map('mb_strtolower', array_column($this->getExistingItems('qualifications'), 'name'));
        $transform = static function ($qualification) {
            return [
                'name' => $qualification->name,
                'description' => $qualification->desc,
            ];
        };

        $this->migrateItems(\IPS\perscom\Qualifications\Category::roots(null), 'qualifications', $this->fieldNotInArray('name', $existingQualifications), $transform);
    }

    protected function migrateRanks(): void
    {
        $existingRanks = array_map('mb_strtolower', array_column($this->getExistingItems('ranks'), 'name'));
        $transform = static function ($rank) {
            return [
                'name' => $rank->name,
                'description' => $rank->desc,
                'abbreviation' => $rank->name_abbreviation,
                'paygrade' => $rank->paygrade,
            ];
        };

        $this->migrateItems(\IPS\perscom\Ranks\Category::roots(null), 'ranks', $this->fieldNotInArray('name', $existingRanks), $transform);
    }

    protected function migrateSpecialties(): void
    {
        $specialties = [];
        foreach (\IPS\perscom\Units\AdministrativeUnitPosition::roots(null) as $position) {
            $specialties[$position->mos] = [
                'name' => $position->mos . ' - ' . $position->name,
                'abbreviation' => $position->mos,
            ];
        }

        foreach (\IPS\perscom\Personnel\Soldier::roots(null) as $soldier) {
            $specialties[$soldier->mos] = [
                'name' => $soldier->mos,
                'abbreviation' => $soldier->mos,
            ];
        }


        $existing = array_map('mb_strtolower', array_column($this->getExistingItems('specialties'), 'abbreviation'));
        $specialtiesToCreate = [];
        foreach ($specialties as $key => $specialty) {
            if (in_array(mb_strtolower($key), $existing)) {
                continue;
            }

            $specialtiesToCreate[] = $specialty;
        }

        if (!empty($specialtiesToCreate)) {
            $this->api->post('specialties/batch', [
                'resources' => $specialtiesToCreate,
            ]);
        }
    }

    protected function migrateStatuses(): void
    {
        $existingStatuses = array_map('mb_strtolower', array_column($this->getExistingItems('statuses'), 'name'));
        $transform = static function ($status) {
            return [
                'name' => $status->name,
                'color' => 'bg-white-100 text-black-600',
            ];
        };

        $this->migrateItems(\IPS\perscom\Personnel\Status::roots(null), 'statuses', $this->fieldNotInArray('name', $existingStatuses), $transform);
    }

    protected function migrateUsers(array $filters): void
    {
        $existingUsers = array_map('mb_strtolower', array_column($this->getExistingItems('users'), 'email'));
        $statusBlacklist = array_map(static function ($status) {
            return $status->id;
        }, $filters['status_blacklist']);

        $resultItem = new \IPS\perscommigrator\Migrator\ResultItem('users');

        $usersToCreate = [];
        foreach (\IPS\perscom\Personnel\Soldier::roots(null) as $id => $soldier) {
            // TODO: debug, only create me :)
            if ($soldier->id !== 418) {
                continue;
            }

            $isStatusBlacklist = in_array($soldier->get_status()->id, $statusBlacklist, true);
            $alreadyExist = in_array(strtolower($soldier->get_email()), $existingUsers, true);

            if ($isStatusBlacklist || $alreadyExist) {
                $resultItem->skipped++;
                continue;
            }

            $data = [];
            $data['name'] = $soldier->firstname . ' ' . $soldier->lastname;
            $data['email'] = $soldier->get_email();
            $data['email_verified_at'] = (new \DateTime())->format('Y-m-d\TH:i:s.u\Z');

            $usersToCreate[$id] = $data;
        }

        die('danger zone');

        $this->migrateItems(
            $usersToCreate,
            'users',
            function () { return true; },
            function ($data) { return $data; },
            $resultItem
        );

        // Refresh user cache so we can get the IDs of the newly created users
        $this->getExistingItems('users');
    }

    protected function fieldNotInArray(string $field, array $existingValues): callable
    {
        return static function ($item) use ($field, $existingValues) {
            $value = is_array($item) ? $item[$field] : $item->$field;
            return !in_array(mb_strtolower($value), $existingValues, true);
        };
    }

    protected function migrateItems(
        array $items,
        string $resource,
        callable $shouldCreate,
        callable $transform,
        $resultItem = null
    ): void {
        $toplevel = false;
        if ($resultItem === null) {
            $toplevel = true;
            $resultItem = new \IPS\perscommigrator\Migrator\ResultItem($resource);
        }

        $itemsToCreate = [];
        foreach ($items as $item) {
            if (method_exists($item, 'children') && !empty($item->children(null))) {
                $this->migrateItems($item->children(null), $resource, $shouldCreate, $transform, $resultItem);
                continue;
            }

            $item = $transform($item);
            if ($shouldCreate($item)) {
                $itemsToCreate[] = $item;
                continue;
            }
            $resultItem->skipped++;
        }

        if (!empty($itemsToCreate)) {
            try {
                $this->api->post($resource . '/batch', [
                    'resources' => $itemsToCreate,
                ]);
                $resultItem->created += count($itemsToCreate);
            } catch (\Exception $ex) {
                $resultItem->error += count($itemsToCreate);
                $resultItem->errorMessages[] = $ex->getMessage();
            }
        }

        if ($toplevel) {
            $this->migrateResult->items[] = $resultItem;
        }
    }

    protected function getExistingItems(string $resource): array
    {
        $page = 1;
        $limit = 100;

        $items = [];
        do {
            $query = http_build_query(['limit' => $limit, 'page' => $page]);
            $data = $this->api->get($resource . '?' . $query);

            $items = array_merge($items, $data['data']);
            $page++;
        } while (count($data['data']) === $limit);

        $this->cache->set($resource, $items);
        return $items;
    }
}
