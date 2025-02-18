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
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot5Dot57 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_732095F772F5A1AA4584665A ON product_channel");
        $this->exec("ALTER TABLE product_channel ADD created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_channel ADD modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_channel ADD created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_channel ADD modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_channel CHANGE id id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_CREATED_BY_ID ON product_channel (created_by_id)");
        $this->exec("CREATE INDEX IDX_MODIFIED_BY_ID ON product_channel (modified_by_id)");
        $this->exec("CREATE UNIQUE INDEX UNIQ_732095F7EB3B4E334584665A72F5A1AA ON product_channel (deleted, product_id, channel_id)");
        $this->exec("ALTER TABLE product_channel RENAME INDEX idx_732095f74584665a TO IDX_PRODUCT_ID");
        $this->exec("ALTER TABLE product_channel RENAME INDEX idx_732095f772f5a1aa TO IDX_CHANNEL_ID");

        try {
            /** @var \Espo\Core\Utils\Layout $layoutManager */
            $layoutManager = (new \Espo\Core\Application())->getContainer()->get('layout');

            $layoutManager->set(json_decode(str_replace('"channels"', '"productChannels"', $layoutManager->get('Product', 'relationships'))), 'Product', 'relationships');
            $layoutManager->set(json_decode(str_replace('"products"', '"productChannels"', $layoutManager->get('Channel', 'relationships'))), 'Channel', 'relationships');
            $layoutManager->save();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited!");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
