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
            $this->migrateRosters();
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
                $prefix = $position->mos . ' - ';
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
                'bg_color' => $status->hex_color,
                'text_color' => '#ffffff',
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
                'description' => $unit->desc ?? '',
            ];
        };

        $this->migrateItems(\IPS\perscom\Units\CombatUnit::roots(null), 'units', $this->fieldNotInArray('name', $existingUnits), $transform, true);
        $this->migrateItems(\IPS\perscom\Units\AdministrativeUnit::roots(null), 'units', $this->fieldNotInArray('name', $existingUnits), $transform, true);
    }

    protected function migrateRosters(): void
    {
        $existingGroups = array_map('mb_strtolower', array_column($this->getExistingItems('groups'), 'name'));
        $transform = static function ($roster) {
            return [
                'name' => $roster->name,
                'desc' => $roster->desc,
                'order' => $roster->order,
            ];
        };

        $rosters = \IPS\perscom\Personnel\Roster::roots(null, null, ['roster_enabled=?', 1]);
        $this->migrateItems($rosters, 'groups', $this->fieldNotInArray('name', $existingGroups), $transform);
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

        $this->migrateItems(
            $usersToCreate,
            'users',
            [$this, 'alwaysTrue'],
            [$this, 'identity'],
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

        $serviceRecordsToCreate = [];
        $awardRecordsToCreate = [];
        $rankRecordsToCreate = [];
        $assignmentRecordsToCreate = [];
        $combatRecordsToCreate = [];
        $qualificationRecordsToCreate = [];

        foreach ($this->cache->get('users') as $user) {
            /** @var \IPS\perscom\Personnel\_Soldier $soldier */
            $soldier = $findSoldier($user['email']);
            if ($soldier === null || $user['status_id'] !== null) {
                continue;
            }

            foreach (\IPS\perscom\Records\Assignment::roots(null, null, ['assignment_records_soldier=?', $soldier->id]) as $assignmentRecord) {
                $assignmentRecordsToCreate[] = $this->transformAssignmentRecord($assignmentRecord, $user['id'], $author['id']);
            }
            $assignmentRecordsToCreate[] = $this->transformCurrentAssignment($soldier, $user['id'], $author['id']);

            $rankRecordCount = 0;
            foreach (\IPS\perscom\Records\Service::roots(null, null, ['service_records_soldier=?', $soldier->id]) as $serviceRecord) {
                $record = $this->transformServiceRecord($serviceRecord, $user['id'], $author['id']);

                switch ($record['record_type']) {
                    case 'rank':
                        unset($record['record_type']);
                        $rankRecordsToCreate[] = $record;
                        $rankRecordCount++;
                        break;
                    case 'award':
                        unset($record['record_type']);
                        $awardRecordsToCreate[] = $record;
                        break;
                    case 'service':
                    default:
                        unset($record['record_type']);
                        $serviceRecordsToCreate[] = $record;
                }
            }

            if ($rankRecordCount === 0 && $soldier->get_rank() !== null) {
                $rank = $this->cache->findBy('ranks', 'name', $soldier->get_rank()->name, 'strtolower');
                if ($rank !== null) {
                    $rankRecord = [
                        'user_id' => $user['id'],
                        'author_id' => $author['id'],
                        'rank_id' => $rank['id'],
                        'type' => 0,
                    ];

                    if ($createdAt = $soldier->get_promotion_date()) {
                        $rankRecord['created_at'] = $createdAt->format(self::DATE_FORMAT);
                    }

                    $rankRecordsToCreate[] = $rankRecord;
                }
            }

            /** @var \IPS\perscom\Records\_Combat $combatRecord */
            foreach (\IPS\perscom\Records\Combat::roots(null, null, ['combat_records_soldier=?', $soldier->id]) as $combatRecord) {
                if (empty($combatRecord->text)) {
                    continue;
                }

                $record = [
                    'user_id' => $user['id'],
                    'author_id' => $author['id'],
                    'text' => $combatRecord->text,
                ];

                if ($createdAt = $combatRecord->get_date()) {
                    $record['created_at'] = $createdAt->format(self::DATE_FORMAT);
                }

                $combatRecordsToCreate[] = $record;
            }

            foreach (\IPS\perscom\Qualifications\Record::roots(null, null, ['qualification_records_soldier=?', $soldier->id]) as $qualRecord) {
                $record = $this->transformQualificationRecord($qualRecord, $user['id'], $author['id']);
                if ($record !== null) {
                    $qualificationRecordsToCreate[] = $record;
                }
            }
        }

        $this->migrateRecords($serviceRecordsToCreate, 'service');
        $this->migrateRecords($awardRecordsToCreate, 'award');
        $this->migrateRecords($assignmentRecordsToCreate, 'assignment');
        $this->migrateRecords($rankRecordsToCreate, 'rank');
        $this->migrateRecords($combatRecordsToCreate, 'combat');
        $this->migrateRecords($qualificationRecordsToCreate, 'qualification');
    }

    private function migrateRecords(array $records, string $type): void
    {
        $this->migrateItems(
            $records,
            $type . '-records',
            [$this, 'alwaysTrue'],
            [$this, 'identity'],
        );
    }

    /**
     * @param \IPS\perscom\Records\_Assignment $assignmentRecord
     */
    private function transformAssignmentRecord($assignmentRecord, $userId, $authorId): array
    {
        $record = [];
        $record['user_id'] = $userId;
        $record['author_id'] = $authorId;
        if ($createdAt = $assignmentRecord->get_date()) {
            $record['created_at'] = $createdAt->format(self::DATE_FORMAT);
        }

        if ($unit = $assignmentRecord->get_to()) {
            $knownUnit = $this->cache->findBy('units', 'name', $unit->name, 'strtolower');
            if ($knownUnit !== null) {
                $record['unit_id'] = $knownUnit['id'];
            }
        }

        if ($position = $assignmentRecord->get_position()) {
            $knownPosition = $this->cache->findBy('positions', 'name', $position->name, 'strtolower');
            if ($knownPosition !== null) {
                $record['position_id'] = $knownPosition['id'];
            }
        }

        if ($status = $assignmentRecord->get_status()) {
            $knownStatus = $this->cache->findBy('statuses', 'name', $status->name, 'strtolower');
            if ($knownStatus !== null) {
                $record['status_id'] = $knownStatus['id'];
            }
        }

        return $record;
    }

    /**
     * @param \IPS\perscom\Personnel\_Soldier $soldier
     */
    private function transformCurrentAssignment($soldier, $userId, $authorId): array
    {
        $record = [];
        $record['user_id'] = $userId;
        $record['author_id'] = $authorId;
        $record['created_at'] = (new \DateTime())->format(self::DATE_FORMAT);

        $speciality = $this->cache->findBy('specialties', 'name', $soldier->mos, 'strtolower');
        if ($speciality !== null) {
            $record['specialty_id'] = $speciality['id'];
        }

        if ($unit = $soldier->get_combat_unit()) {
            $knownUnit = $this->cache->findBy('units', 'name', $unit->name, 'strtolower');
            if ($knownUnit !== null) {
                $record['unit_id'] = $knownUnit['id'];
            }
        }

        if ($position = $soldier->get_combat_unit_position()) {
            $knownPosition = $this->cache->findBy('positions', 'name', $position->name, 'strtolower');
            if ($knownPosition !== null) {
                $record['position_id'] = $knownPosition['id'];
            }
        }

        if ($status = $soldier->get_status()) {
            $knownStatus = $this->cache->findBy('statuses', 'name', $status->name, 'strtolower');
            if ($knownStatus !== null) {
                $record['status_id'] = $knownStatus['id'];
            }
        }

        $record['secondary_position_ids'] = [];
        $record['secondary_unit_ids'] = [];

        /** @var \IPS\perscom\Units\_AdministrativeUnitPosition $ipsAdminPosition */
        foreach ($soldier->get_administrative_unit_positions() as $ipsAdminPosition) {
            $adminPosition = $this->cache->findBy('positions', 'name', $ipsAdminPosition->mos . ' - ' . $ipsAdminPosition->name, 'strtolower');
            if ($adminPosition !== null) {
                $record['secondary_position_ids'][] = $adminPosition['id'];
            }

            $ipsAdminUnit = $ipsAdminPosition->get_unit();
            if ($ipsAdminUnit !== null) {
                $adminUnit = $this->cache->findBy('units', 'name', $ipsAdminUnit->name, 'strtolower');
                if ($adminUnit !== null) {
                    $record['secondary_unit_ids'][] = $adminUnit['id'];
                }
            }
        }

        $record['secondary_position_ids'] = array_unique($record['secondary_position_ids']);
        $record['secondary_unit_ids'] = array_unique($record['secondary_unit_ids']);

        return $record;
    }

    /**
     * @param \IPS\perscom\Records\_Service $serviceRecord
     */
    private function transformServiceRecord($serviceRecord, $userId, $authorId): array
    {
        $record = [];
        $record['user_id'] = $userId;
        $record['author_id'] = $authorId;
        if ($createdAt = $serviceRecord->get_date()) {
            $record['created_at'] = $createdAt->format(self::DATE_FORMAT);
        }
        $record['text'] = $serviceRecord->text;
        $record['record_type'] = 'service';

        if (!$serviceRecord->item_id) {
            return $record;
        }

        $type = (string)$serviceRecord->action;
        if ($type === \IPS\perscom\Records\Service::SERVICE_RECORD_AWARD) {
            try {
                $ipsAward = \IPS\perscom\Awards\Award::load($serviceRecord->item_id);
                if ($award = $this->cache->findBy('awards', 'name', $ipsAward->name, 'strtolower')) {
                    $record['record_type'] = 'award';
                    $record['award_id'] = $award['id'];
                }
            } catch (\Exception $ex) {
                // skip
            }
        }

        if ($type === \IPS\perscom\Records\Service::SERVICE_RECORD_PROMOTION
            || $type === \IPS\perscom\Records\Service::SERVICE_RECORD_DEMOTION) {
            try {
                $ipsRank = \IPS\perscom\Ranks\Rank::load($serviceRecord->item_id);
                if ($rank = $this->cache->findBy('ranks', 'name', $ipsRank->name, 'strtolower')) {
                    $record['record_type'] = 'rank';
                    $record['type'] = $type === \IPS\perscom\Records\Service::SERVICE_RECORD_PROMOTION ? 0 : 1;
                    $record['rank_id'] = $rank['id'];
                }
            } catch (\Exception $ex) {
                // skip
            }
        }

        return $record;
    }

    /**
     * @param \IPS\perscom\Qualifications\_Record $qualificationRecord
     */
    private function transformQualificationRecord($qualificationRecord, $userId, $authorId): ?array
    {
        $record = [];
        $record['user_id'] = $userId;
        $record['author_id'] = $authorId;
        if ($createdAt = $qualificationRecord->get_date()) {
            $record['created_at'] = $createdAt->format(self::DATE_FORMAT);
        }

        $ipsQualification = $qualificationRecord->get_qualification();
        if ($ipsQualification === null) {
            return null;
        }

        $qualification = $this->cache->findBy('qualifications', 'name', $ipsQualification->name, 'strtolower');
        if ($qualification === null) {
            return null;
        }

        $record['qualification_id'] = $qualification['id'];
        return $record;
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
                foreach (array_chunk($itemsToCreate, 1000) as $chunk) {
                    $this->api->post($resource . '/batch', [
                        'resources' => $chunk,
                    ]);
                }
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
        $limit = 1000;

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

    // some helper functions
    protected function alwaysTrue(): bool
    {
        return true;
    }

    protected function identity($data)
    {
        return $data;
    }
}
