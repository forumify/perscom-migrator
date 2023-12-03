<?php

namespace IPS\perscommigrator\Migrator;

class _migrator
{
    protected const DATE_FORMAT = 'Y-m-d\TH:i:s.u\Z';

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

    public function migrate(string $authorEmail, array $personnelFilters): \IPS\perscommigrator\Migrator\MigrateResult
    {
        $this->migrateResult = (new \IPS\perscommigrator\Migrator\MigrateResult())->start();

        try {
            $this->migrateAwards();
            $this->migratePositions();
            $this->migrateQualifications();
            $this->migrateRanks();
            $this->migrateSpecialties();
            $this->migrateStatuses();
            $this->migrateUnits();
            $this->migrateUsers($authorEmail, $personnelFilters);
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

    protected function migrateUnits(): void
    {
        $existingUnits = array_map('mb_strtolower', array_column($this->getExistingItems('units'), 'name'));
        $transform = static function ($unit) {
            return [
                'name' => $unit->name,
                'description' => $unit->desc,
            ];
        };

        $this->migrateItems(\IPS\perscom\Units\CombatUnit::roots(null), 'units', $this->fieldNotInArray('name', $existingUnits), $transform, true);
    }

    protected function migrateUsers(string $authorEmail, array $filters): void
    {
        $existingUsers = array_map('mb_strtolower', array_column($this->getExistingItems('users'), 'email'));
        $author = $this->cache->findBy('users', 'email', $authorEmail, 'strtolower');
        if ($author === null) {
            throw new \RuntimeException("Author with email $authorEmail does not exist. Please provide an existing PERSCOM.io user email!");
        }

        $statusBlacklist = array_map(static function ($status) {
            return $status->id;
        }, $filters['status_blacklist']);

        $resultItem = new \IPS\perscommigrator\Migrator\ResultItem('users');

        $usersToCreate = [];
        $personnel = \IPS\perscom\Personnel\Soldier::roots(null);
        /** @var \IPS\perscom\Personnel\_Soldier $soldier */
        foreach ($personnel as $id => $soldier) {
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

            $data['email_verified_at'] = (new \DateTime())->format(self::DATE_FORMAT);
            if ($soldier->get_induction_date()) {
                $data['created_at'] = $soldier->get_induction_date()->format(self::DATE_FORMAT);
            }


            $usersToCreate[$id] = $data;
        }

        $alwaysTrue = static function () { return true; };
        $identity = static function ($data) { return $data; };

        $this->migrateItems(
            $usersToCreate,
            'users',
            $alwaysTrue,
            $identity,
            false,
            $resultItem
        );
        $this->migrateResult->items[] = $resultItem;
        $this->getExistingItems('users');

        $findSoldier = function ($email) use ($personnel) {
            foreach ($personnel as $soldier) {
                if (strtolower($soldier->get_email()) === strtolower($email)) {
                    return $soldier;
                }
            }
            return null;
        };

        $rankRecordsToCreate = [];
        $assignmentRecordsToCreate = [];
        foreach ($this->cache->get('users') as $user) {
            /** @var \IPS\perscom\Personnel\_Soldier $soldier */
            $soldier = $findSoldier($user['email']);
            if ($soldier === null || $user['status_id'] !== null) {
                continue;
            }

            // TODO: debug, only create me :)
            if ($soldier->id !== 418) {
                continue;
            }

            if ($assignmentRecord = $this->getAssignmentRecordToCreate($user, $soldier, $author)) {
                $assignmentRecordsToCreate[] = $assignmentRecord;
            }

            if ($rankRecord = $this->getRankRecordToCreate($user, $soldier, $author)) {
                $rankRecordsToCreate[] = $rankRecord;
            }
        }

        $this->migrateItems(
            $assignmentRecordsToCreate,
            'assignment-records',
            $alwaysTrue,
            $identity,
        );

        $this->migrateItems(
            $rankRecordsToCreate,
            'rank-records',
            $alwaysTrue,
            $identity,
        );
    }

    /**
     * @param array $user
     * @param \IPS\perscom\Personnel\_Soldier $soldier
     * @param array $author
     * @return array|null
     */
    private function getAssignmentRecordToCreate(array $user, $soldier, array $author)
    {
        if ($user['status_id'] !== null) {
            // already has an assignment
            return null;
        }

        $assignmentRecord = [];
        $assignmentRecord['user_id'] = $user['id'];
        $assignmentRecord['author_id'] = $author['id'];

        $lastAssignmentDate = iterator_to_array(\IPS\Db::i()->select(
            'assignment_records_date',
            'perscom_assignment_records',
            ['assignment_records_soldier=?', $soldier->id],
            'assignment_records_date DESC',
            1
        ));

        $lastAssignmentDateValue = reset($lastAssignmentDate);
        if ($lastAssignmentDateValue !== false) {
            $assignmentRecord['created_at'] = \IPS\DateTime::ts($lastAssignmentDateValue, true)->format(self::DATE_FORMAT);
        }

        $status = $soldier->get_status();
        if ($status !== null) {
            $knownStatus = $this->cache->findBy('statuses', 'name', $status->name, 'strtolower');
            if ($knownStatus !== null) {
                $assignmentRecord['status_id'] = $knownStatus['id'];
            }
        }

        $combatUnit = $soldier->get_combat_unit();
        if ($combatUnit !== null) {
            $knownUnit = $this->cache->findBy('units', 'name', $combatUnit->name, 'strtolower');
            if ($knownUnit !== null) {
                $assignmentRecord['unit_id'] = $knownUnit['id'];
            }
        }

        // TODO: specialty and positions

        return $assignmentRecord;
    }

    /**
     * @param array $user
     * @param \IPS\perscom\Personnel\_Soldier $soldier
     * @param array $author
     * @return array|null
     */
    private function getRankRecordToCreate(array $user, $soldier, array $author)
    {
        if ($user['rank_id'] !== null) {
            // already has a rank
            return null;
        }

        $rank = $soldier->get_rank();
        if ($rank === null) {
            return null;
        }

        $knownRank = $this->cache->findBy('ranks', 'name', $rank->name, 'strtolower');
        if ($knownRank === null) {
            return null;
        }

        $rankRecord = [];
        $rankRecord['user_id'] = $user['id'];
        $rankRecord['rank_id'] = $knownRank['id'];
        $rankRecord['author_id'] = $author['id'];
        $rankRecord['type'] = 0;

        $created = $soldier->get_promotion_date();
        if ($created !== null) {
            $rankRecord['created_at'] = $created->format(self::DATE_FORMAT);
        }

        return $rankRecord;
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
        bool $includeParents = false,
        $resultItem = null
    ): void {
        $toplevel = false;
        if ($resultItem === null) {
            $toplevel = true;
            $resultItem = new \IPS\perscommigrator\Migrator\ResultItem($resource);
        }

        $itemsToCreate = [];
        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'children') && !empty($item->children(null))) {
                $this->migrateItems($item->children(null), $resource, $shouldCreate, $transform, $includeParents, $resultItem);
                if (!$includeParents) {
                    continue;
                }
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
            if (!empty($itemsToCreate)) {
                // update cache with newly created resources
                $this->getExistingItems($resource);
            }
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
