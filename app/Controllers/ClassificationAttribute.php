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

namespace Pim\Controllers;

use Espo\Core\Exceptions\Error;
use Espo\Core\Templates\Controllers\Relationship;
use Slim\Http\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;

class ClassificationAttribute extends Relationship
{
    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService();

        if (property_exists($data, 'attributesIds')) {
            foreach ($data->attributesIds as $attributeId) {
                $data->attributeId = $attributeId;
                try {
                    $createdEntity = $service->createEntity(clone $data);
                    $entity = $createdEntity;
                } catch (\Throwable $e) {
                }
            }
        } else {
            $entity = $service->createEntity($data);
        }

        if (!empty($entity)) {
            return $entity->getValueMap();
        }

        throw new Error();
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        $id = $params['id'];

        if (property_exists($data, 'deletePav') && !empty($data->deletePav)) {
            $this->getRecordService()->deleteEntityWithThemPavs($id);
        } else {
            $this->getRecordService()->deleteEntity($id);
        }

        return true;
    }

    public function actionUnlinkAttributeGroupHierarchy(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'attributeGroupId') || !property_exists($data, 'classificationId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('ClassificationAttribute', 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->unlinkAttributeGroupHierarchy($data->attributeGroupId, $data->classificationId);
    }
}
