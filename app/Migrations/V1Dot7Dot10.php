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

class V1Dot7Dot10 extends Base
{
    public function up(): void
    {
        $limit = 5000;

        foreach (['product', 'category'] as $entity) {
            $offset = 0;
            while (!empty($ids = $this->getPDO()->query("SELECT id FROM $entity WHERE deleted=0 LIMIT $limit OFFSET $offset")->fetchAll(\PDO::FETCH_COLUMN))) {
                foreach ($ids as $id) {
                    $relationIds = $this->getPDO()->query("SELECT id FROM {$entity}_asset WHERE {$entity}_id='$id' AND deleted=0 ORDER BY sorting")->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($relationIds as $k => $relationId) {
                        $sorting = $k * 10;
                        $this->getPDO()->exec("UPDATE {$entity}_asset SET sorting=$sorting WHERE id='$relationId'");
                    }
                }
                $offset = $offset + $limit;
            }
        }

        $ids = $this->getPDO()->query("SELECT id FROM attribute_group WHERE deleted=0")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $relationIds = $this->getPDO()->query("SELECT id FROM attribute WHERE attribute_group_id='$id' AND deleted=0 ORDER BY sort_order_in_attribute_group")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($relationIds as $k => $relationId) {
                $sorting = $k * 10;
                $this->getPDO()->exec("UPDATE attribute SET sort_order_in_attribute_group=$sorting WHERE id='$relationId'");
            }
        }
    }

    public function down(): void
    {
    }
}
