<?php

namespace EntelisTeam\Lbaf\Rector;

/**
 * Class returning list of available migrations for current repository.
 * Migrations from dependencies are not included.
 */
interface RectorMigrationListInterface
{
    /**
     * @return list<class-string>
     */
    public static function all(): array;
}