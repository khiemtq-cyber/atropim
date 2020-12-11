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

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class AbstractRepository
 * @package Pim\Repositories
 */
abstract class AbstractRepository extends Base
{
    /**
     * @var string
     */
    protected $ownership;

    /**
     * @var string
     */
    protected $ownershipRelation;

    /**
     * @var string
     */
    protected $assignedUserOwnership;

    /**
     * @var string
     */
    protected $ownerUserOwnership;

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        $this->changeOwnership($entity);
    }

    /**
     * @param Entity $entity
     */
    protected function changeOwnership(Entity $entity)
    {
        if ($entity->isAttributeChanged('assignedUserId') || $entity->isAttributeChanged('ownerUserId')) {
            $assignedUserOwnership = $this->getConfig()->get($this->assignedUserOwnership, '');
            $ownerUserOwnership = $this->getConfig()->get($this->ownerUserOwnership, '');

            if ($assignedUserOwnership == $this->ownership || $ownerUserOwnership == $this->ownership) {
                foreach ($entity->get($this->ownershipRelation) as $related) {
                    $toSave = false;

                    if ($assignedUserOwnership == $this->ownership
                        && ($related->get('assignedUserId') == null || $related->get('assignedUserId') == $entity->getFetched('assignedUserId'))) {
                        $related->set('assignedUserId', $entity->get('assignedUserId'));
                        $related->set('assignedUserName', $entity->get('assignedUserName'));
                        $toSave = true;
                    }

                    if ($ownerUserOwnership == $this->ownership
                        && ($related->get('ownerUserId') == null || $related->get('ownerUserId') == $entity->getFetched('ownerUserId'))) {
                        $related->set('ownerUserId', $entity->get('ownerUserId'));
                        $related->set('ownerUserName', $entity->get('ownerUserName'));
                        $toSave = true;
                    }

                    if ($toSave) {
                        $this->getEntityManager()->saveEntity($related, ['skipAll' => true]);
                    }
                }
            }
        }
    }
}
