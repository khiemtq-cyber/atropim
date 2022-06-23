<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Migration class for version 1.5.34
 */
class V1Dot5Dot34 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->exec("ALTER TABLE `attribute` ADD default_scope VARCHAR(255) DEFAULT 'Global' COLLATE utf8mb4_unicode_ci, ADD default_is_required TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD default_channel_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("CREATE INDEX IDX_DEFAULT_CHANNEL_ID ON `attribute` (default_channel_id)");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->exec("DROP INDEX IDX_DEFAULT_CHANNEL_ID ON `attribute`");
        $this->exec("ALTER TABLE `attribute` DROP default_channel_id, DROP default_is_required, DROP default_scope");
    }

    /**
     * @param string $query
     *
     * @return void
     */
    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
