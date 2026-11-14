<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Tables
{
    /**
     * Tabelele din baza de date a conexiunii, si numai din ea.
     *
     * Pe MySQL / MariaDB, Schema::getTableListing() fara schema intoarce tabelele din toate bazele la care
     * utilizatorul are acces. Pe hosting shared acelasi utilizator poate avea drepturi si pe baza WordPress,
     * iar backupul, restaurarea sau importul ar lucra atunci cu tabele straine.
     *
     * @return list<string>
     */
    public static function of(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $schema = in_array($db->getDriverName(), ['mysql', 'mariadb'], true) ? $db->getDatabaseName() : null;

        return array_values(Schema::connection($connection)->getTableListing($schema, false));
    }
}
