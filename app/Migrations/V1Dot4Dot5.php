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

use Espo\Core\Exceptions\Error;
use Treo\Core\Migration\Base;

class V1Dot4Dot5 extends Base
{
    public function up(): void
    {
        $query = "SELECT p.id, p.image_id, p.data, a.file_id as attachment_id, a.id as asset_id
                  FROM `product` p 
                      LEFT JOIN product_asset pa on p.id= pa.product_id AND pa.deleted=0
                      LEFT JOIN asset a on a.id= pa.asset_id AND a.deleted=0
                  WHERE p.deleted=0 
                  ORDER BY p.id";

        $products = [];
        foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            $products[$record['id']]['id'] = $record['id'];
            $products[$record['id']]['image_id'] = $record['image_id'];
            $products[$record['id']]['data'] = $record['data'];
            $products[$record['id']]['assets'][$record['asset_id']] = [
                'asset_id'      => $record['asset_id'],
                'attachment_id' => $record['attachment_id'],
            ];
        }

        foreach ($products as $product) {
            $data = @json_decode((string)$product['data'], true);
            if (empty($data)) {
                $data = [];
            }
            $mainImages = isset($data['mainImages']) ? $data['mainImages'] : [];

            $data['mainImages'] = [];
            if (!empty($product['image_id'])) {
                $data['mainImages'][] = [
                    'attachmentId' => $product['image_id'],
                    'scope'        => 'Global',
                    'channelId'    => null
                ];
            }

            foreach ($mainImages as $assetId => $channelsIds) {
                foreach ($channelsIds as $channelId) {
                    if (!isset($product['assets'][$assetId])) {
                        continue 1;
                    }
                    $data['mainImages'][] = [
                        'attachmentId' => $product['assets'][$assetId]['attachment_id'],
                        'scope'        => 'Channel',
                        'channelId'    => $channelId
                    ];
                }
            }

            $jsonData = json_encode($data);

            $this->getPDO()->exec("UPDATE `product` SET data='$jsonData' WHERE id='{$product['id']}'");
        }

        try {
            $this->getPDO()->exec("ALTER TABLE `product` DROP image_id");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
