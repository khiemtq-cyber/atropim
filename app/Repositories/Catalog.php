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

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Catalog repository
 */
class Catalog extends AbstractRepository
{
    /**
     * @var string
     */
    protected $ownership = 'fromCatalog';

    /**
     * @var string
     */
    protected $ownershipRelation = 'Product';

    /**
     * @var string
     */
    protected $assignedUserOwnership = 'assignedUserProductOwnership';

    /**
     * @var string
     */
    protected $ownerUserOwnership = 'ownerUserProductOwnership';

    /**
     * @var string
     */
    protected $teamsOwnership = 'teamsProductOwnership';

    public function getProductsCount(Entity $catalog): int
    {
        return $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->where(['catalogId' => $catalog->get('id')])
            ->count();
    }

    public function hasProducts(string $catalogId): bool
    {
        $catalogId = $this->getPDO()->quote($catalogId);

        $records = $this
            ->getPDO()
            ->query("SELECT id FROM product WHERE catalog_id=$catalogId AND deleted=0 LIMIT 0,1")
            ->fetchAll(\PDO::FETCH_COLUMN);

        return !empty($records);
    }

    public function getProductsIds(string $catalogId): array
    {
        $catalogId = $this->getPDO()->quote($catalogId);

        return $this
            ->getPDO()
            ->query("SELECT id FROM product WHERE catalog_id=$catalogId AND deleted=0")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function relateCategories(Entity $entity, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->relateCatalogs($category, $entity, null, $options);
    }

    public function unrelateCategories(Entity $entity, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->unrelateCatalogs($category, $entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        /** @var string $id */
        $id = $entity->get('id');

        // remove catalog products
        $this->getEntityManager()->nativeQuery("UPDATE product SET deleted=1 WHERE catalog_id='$id'");
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        $this->setInheritedOwnership($entity);
    }

    /**
     * @inheritDoc
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'products') {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->getEntityManager()->getRepository('Product')->{"onCatalog{$mode}Change"}($foreign, $entity);
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'products') {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->getEntityManager()->getRepository('Product')->{"onCatalog{$mode}Change"}($foreign, null);
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }
}
