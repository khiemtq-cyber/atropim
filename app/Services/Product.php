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

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Treo\Core\EventManager\Event;
use Treo\Core\Exceptions\NotModified;
use Treo\Services\MassActions;

/**
 * Service of Product
 */
class Product extends AbstractService
{
    protected $linkWhereNeedToUpdateChannel = 'productAttributeValues';

    protected $mandatorySelectAttributeList = ['data'];

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        parent::loadPreviewForCollection($collection);

        $ids = [];
        foreach ($collection as $entity) {
            if (!empty($attachmentId = $this->getMainImageId($entity))) {
                $ids[] = $attachmentId;
            }
        }

        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');
        foreach ($attachmentRepository->where(['id' => $ids])->find() as $attachment) {
            $attachments[$attachment->get('id')] = [
                'name'      => $attachment->get('name'),
                'pathsData' => $attachmentRepository->getAttachmentPathsData($attachment),
            ];
        }

        foreach ($collection as $entity) {
            if (!empty($attachmentId = $this->getMainImageId($entity)) && isset($attachments[$attachmentId])) {
                $entity->set("imageId", $attachmentId);
                $entity->set("imageName", $attachments[$attachmentId]['name']);
                $entity->set("imagePathsData", $attachments[$attachmentId]['pathsData']);
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->setMainImage($entity);
    }

    /**
     * @inheritDoc
     */
    public function unlinkEntity($id, $link, $foreignId)
    {
        if ($link == 'assets') {
            return $this->unlinkAssets($id, $foreignId);
        }

        return parent::unlinkEntity($id, $link, $foreignId);
    }

    public function unlinkAssets(string $id, string $foreignId): bool
    {
        $link = 'assets';

        $parts = explode('_', $foreignId);
        $foreignId = array_shift($parts);
        $channel = implode('_', $parts);

        $event = $this->dispatchEvent('beforeUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignId' => $foreignId]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        if (in_array($link, $this->readOnlyLinkList)) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        $accessActionRequired = 'edit';
        if (in_array($link, $this->noEditAccessRequiredLinkList)) {
            $accessActionRequired = 'read';
        }
        if (!$this->getAcl()->check($foreignEntity, $accessActionRequired)) {
            throw new Forbidden();
        }

        $query = "DELETE FROM product_asset WHERE asset_id='$foreignId' AND product_id='$id'";
        if (empty($channel)) {
            $query .= " AND (channel IS NULL OR channel='')";
        } else {
            $query .= " AND channel='$channel'";
        }

        $entity->removeMainImageByAttachmentId($foreignEntity->get('fileId'));
        $data = str_replace(["'", '\"'], ["\'", '\\\"'], Json::encode($entity->get('data'), JSON_UNESCAPED_UNICODE));

        $query .= ";UPDATE product SET data='$data' WHERE id='{$entity->get('id')}'";

        $this->getEntityManager()->nativeQuery($query);

        return $this
            ->dispatchEvent('afterUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
            ->getArgument('result');
    }

    public function updateActiveForChannel(string $channelId, string $productId, bool $isActive): bool
    {
        if (empty($channel = $this->getEntityManager()->getEntity('Channel', $channelId)) || !$this->getAcl()->check($channel, 'edit')) {
            return false;
        }

        if (empty($product = $this->getEntityManager()->getEntity('Product', $productId)) || !$this->getAcl()->check($product, 'edit')) {
            return false;
        }

        $this->getRepository()->updateChannelRelationData($productId, $channelId, $isActive);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        $conflicts = [];
        if ($this->isProductAttributeUpdating($data)) {
            if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->beginTransaction();
                $inTransaction = true;
            }
            $service = $this->getInjection('serviceFactory')->create('ProductAttributeValue');
            foreach ($data->panelsData->productAttributeValues as $pavId => $pavData) {
                if (!empty($data->_ignoreConflict)) {
                    $pavData->_prev = null;
                }
                $pavData->isProductUpdate = true;
                try {
                    $service->updateEntity($pavId, $pavData);
                } catch (Conflict $e) {
                    $conflicts = array_merge($conflicts, $e->getFields());
                } catch (NotModified $e) {
                    // ignore
                }
            }
        }

        try {
            $result = parent::updateEntity($id, $data);
        } catch (Conflict $e) {
            $conflicts = array_merge($conflicts, $e->getFields());
        }

        if (!empty($conflicts)) {
            if (!empty($inTransaction)) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw new Conflict(sprintf($this->getInjection('language')->translate('editedByAnotherUser', 'exceptions', 'Global'), implode(', ', $conflicts)));
        }

        if (!empty($inTransaction)) {
            $this->getEntityManager()->getPDO()->commit();
        }

        return $result;
    }

    /**
     * @param \stdClass $data
     *
     * @return array
     * @throws BadRequest
     */
    public function addAssociateProducts(\stdClass $data): array
    {
        // input data validation
        if (empty($data->ids) || empty($data->foreignIds) || empty($data->associationId) || !is_array($data->ids) || !is_array($data->foreignIds) || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        /** @var Entity $association */
        $association = $this->getEntityManager()->getEntity("Association", $data->associationId);
        if (empty($association)) {
            throw new BadRequest($this->exception('noSuchAssociation'));
        }

        /**
         * Collect entities for saving
         */
        $toSave = [];
        foreach ($data->ids as $mainProductId) {
            foreach ($data->foreignIds as $relatedProductId) {
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set("associationId", $data->associationId);
                $entity->set("mainProductId", $mainProductId);
                $entity->set("relatedProductId", $relatedProductId);

                if (!empty($backwardAssociationId = $association->get('backwardAssociationId'))) {
                    $entity->set('backwardAssociationId', $backwardAssociationId);
                    $entity->set("bothDirections", true);

                    $backwardEntity = $this->getEntityManager()->getEntity('AssociatedProduct');
                    $backwardEntity->set("associationId", $backwardAssociationId);
                    $backwardEntity->set("mainProductId", $entity->get('relatedProductId'));
                    $backwardEntity->set("relatedProductId", $entity->get('mainProductId'));
                    $backwardEntity->set("bothDirections", true);
                    $backwardEntity->set("backwardAssociationId", $entity->get('associationId'));

                    $toSave[] = $backwardEntity;
                }

                $toSave[] = $entity;
            }
        }

        $error = [];
        foreach ($toSave as $entity) {
            try {
                $this->getEntityManager()->saveEntity($entity);
            } catch (BadRequest $e) {
                $error[] = [
                    'id'          => $entity->get('mainProductId'),
                    'name'        => $this->getEntityManager()->getEntity('Product', $entity->get('mainProductId'))->get('name'),
                    'foreignId'   => $entity->get('relatedProductId'),
                    'foreignName' => $this->getEntityManager()->getEntity('Product', $entity->get('relatedProductId'))->get('name'),
                    'message'     => utf8_encode($e->getMessage())
                ];
            }
        }

        return ['message' => $this->getMassActionsService()->createRelationMessage(count($toSave) - count($error), $error, 'Product', 'Product')];
    }

    /**
     * Remove product association
     *
     * @param \stdClass $data
     *
     * @return array|bool
     * @throws BadRequest
     */
    public function removeAssociateProducts(\stdClass $data): array
    {
        // input data validation
        if (empty($data->ids) || empty($data->foreignIds) || empty($data->associationId) || !is_array($data->ids) || !is_array($data->foreignIds) || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        $associatedProducts = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->where(
                [
                    'associationId'    => $data->associationId,
                    'mainProductId'    => $data->ids,
                    'relatedProductId' => $data->foreignIds
                ]
            )
            ->find();

        $exists = [];
        if ($associatedProducts->count() > 0) {
            foreach ($associatedProducts as $item) {
                $exists[$item->get('mainProductId') . '_' . $item->get('relatedProductId')] = $item;
            }
        }

        $success = 0;
        $error = [];
        foreach ($data->ids as $id) {
            foreach ($data->foreignIds as $foreignId) {
                $success++;
                if (isset($exists["{$id}_{$foreignId}"])) {
                    $associatedProduct = $exists["{$id}_{$foreignId}"];
                    try {
                        $this->getEntityManager()->removeEntity($associatedProduct);
                    } catch (BadRequest $e) {
                        $success--;
                        $error[] = [
                            'id'          => $associatedProduct->get('mainProductId'),
                            'name'        => $associatedProduct->get('mainProduct')->get('name'),
                            'foreignId'   => $associatedProduct->get('relatedProductId'),
                            'foreignName' => $associatedProduct->get('relatedProduct')->get('name'),
                            'message'     => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->getMassActionsService()->createRelationMessage($success, $error, 'Product', 'Product', false)];
    }

    /**
     * @inheritDoc
     */
    public function setAsMainImage(string $assetId, string $entityId): array
    {
        $parts = explode('_', $assetId);
        $assetId = array_shift($parts);

        if (empty($asset = $this->getEntityManager()->getEntity('Asset', $assetId)) || empty($attachment = $asset->get('file'))) {
            throw new NotFound();
        }

        /** @var \Pim\Entities\Product $entity */
        $entity = $this->getRepository()->get($entityId);
        if (empty($entity)) {
            throw new NotFound();
        }

        $result = [
            'imageId'        => $asset->get('fileId'),
            'imageName'      => $asset->get('name'),
            'imagePathsData' => $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment)
        ];


        $channelId = $this->getPrismChannelId();

        if (!empty($channelId)) {
            foreach ($entity->getMainImages() as $image) {
                if ($image['attachmentId'] === $asset->get('fileId') && $image['scope'] === 'Global') {
                    $entity->removeMainImage($channelId);
                    $this->getEntityManager()->saveEntity($entity);
                    return $result;
                }
            }
        }

        $assetData = $this->getAssetData($entityId, $asset->get('fileId'));

        if (!empty($assetData['channelId'])) {
            $channelId = $assetData['channelId'];
        }

        $entity->addMainImage($asset->get('fileId'), $channelId);
        $this->getEntityManager()->saveEntity($entity);

        return $result;
    }

    public function getPrismChannelId(): ?string
    {
        $channel = null;
        if (!empty($account = $this->getUser()->get('account')) && !empty($account->get('channelId'))) {
            $channel = $account->get('channel');
        }
        if (empty($channel) && !empty($channelCode = self::getHeader('Channel-Code'))) {
            $channel = $this->getEntityManager()->getRepository('Channel')->where(['code' => $channelCode])->findOne();
        }

        return empty($channel) ? null : $channel->get('id');
    }

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateProductAttributeValues(Entity $product, Entity $duplicatingProduct)
    {
        if ($duplicatingProduct->get('productFamilyId') == $product->get('productFamilyId')) {
            // get data for duplicating
            $rows = $duplicatingProduct->get('productAttributeValues');

            if (count($rows) > 0) {
                foreach ($rows as $item) {
                    $entity = $this->getEntityManager()->getEntity('ProductAttributeValue');
                    $entity->set($item->toArray());
                    $entity->id = Util::generateId();
                    $entity->set('productId', $product->get('id'));

                    $this->getEntityManager()->saveEntity($entity, ['skipProductAttributeValueHook' => true]);

                    // relate channels
                    if (!empty($channel = $item->get('channel'))) {
                        $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->relate($entity, 'channel', $channel);
                    }
                }
            }
        }
    }

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateAssociatedMainProducts(Entity $product, Entity $duplicatingProduct)
    {
        // get data
        $data = $duplicatingProduct->get('associatedMainProducts');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['mainProductId'] = $product->get('id');

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateAssociatedRelatedProduct(Entity $product, Entity $duplicatingProduct)
    {
        // get data
        $data = $duplicatingProduct->get('associatedRelatedProduct');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['relatedProductId'] = $product->get('id');

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    protected function findLinkedEntitiesAssets(string $id, array $params): array
    {
        $event = $this->dispatchEvent('beforeFindLinkedEntities', new Event(['id' => $id, 'link' => 'assets', 'params' => $params]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $params = $event->getArgument('params');

        $result = ['list' => []];

        $productAssets = $this->getAssets($id);
        if (!empty($productAssets['count'])) {
            $channelId = isset($params['exportByChannelId']) ? $params['exportByChannelId'] : $this->getPrismChannelId();
            foreach ($productAssets['list'] as $assetType) {
                if (!empty($assetType['assets'])) {
                    foreach ($assetType['assets'] as $asset) {
                        if (!empty($channelId) && $asset['scope'] === 'Channel' && $asset['channelId'] !== $channelId) {
                            continue 1;
                        }
                        $result['list'][] = $asset;
                    }
                }
            }
        }
        $result['total'] = count($result['list']);

        return $this->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))->getArgument('result');
    }

    /**
     * @param string $id
     * @param array  $params
     *
     * @return array
     * @throws Forbidden
     * @throws NotFound
     */
    protected function findLinkedEntitiesProductAttributeValues(string $id, array $params): array
    {
        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }

        $foreignEntityName = 'ProductAttributeValue';

        if (!$this->getAcl()->check($foreignEntityName, 'read')) {
            throw new Forbidden();
        }

        $this->getRepository()->updateInconsistentAttributes($entity);

        $link = 'productAttributeValues';

        if (!empty($params['maxSize'])) {
            $params['maxSize'] = $params['maxSize'] + 1;
        }

        // get select params
        $selectParams = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);
        $selectParams['orderBy'] = 'id';

        // get record service
        $recordService = $this->getRecordService($foreignEntityName);

        /**
         * Prepare select list
         */
        $selectAttributeList = $recordService->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectAttributeList[] = 'ownerUserId';
            $selectAttributeList[] = 'assignedUserId';
            $selectParams['select'] = array_unique($selectAttributeList);
        }

        $collection = $this->getRepository()->findRelated($entity, $link, $selectParams);

        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            if (!empty($params['loadAdditionalFields'])) {
                $recordService->loadAdditionalFields($e);
            }
            if (!empty($selectAttributeList)) {
                $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
            }
            $recordService->prepareEntityForOutput($e);
        }

        $collection = $this->preparePavsForOutput($collection);;

        $result = [
            'collection' => $collection,
            'total'      => count($collection),
        ];

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))
            ->getArgument('result');
    }

    public function preparePavsForOutput(EntityCollection $collection): EntityCollection
    {
        $collection = $this->filterPavsViaChannel($collection);

        if (count($collection) === 0) {
            return $collection;
        }

        $scopeData = [];
        if (empty($collection[0]->has('scope'))) {
            $pavsData = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id', 'scope', 'channelId', 'channelName'])
                ->where(['id' => array_column($collection->toArray(), 'id')])
                ->find();
            foreach ($pavsData as $v) {
                $scopeData[$v->get('id')] = $v;
            }
        } else {
            foreach ($collection as $pav) {
                $scopeData[$pav->get('id')] = $pav;
            }
        }

        $records = [];

        // filtering pavs by scope and channel languages
        foreach ($collection as $pav) {
            if (!isset($scopeData[$pav->get('id')])) {
                continue 1;
            }

            if ($scopeData[$pav->get('id')]->get('scope') === 'Global') {
                $records[$pav->get('id')] = $pav;
            } elseif ($scopeData[$pav->get('id')]->get('scope') === 'Channel' && !empty($scopeData[$pav->get('id')]->get('channelId'))) {
                if (empty($pav->get('attributeIsMultilang'))) {
                    $records[$pav->get('id')] = $pav;
                } else {
                    if (in_array($pav->get('language'), $pav->getChannelLanguages())) {
                        $records[$pav->get('id')] = $pav;
                    }
                }
            }
        }

        // clear hided records
        foreach ($collection as $pav) {
            if (!isset($records[$pav->get('id')])) {
                $this->getEntityManager()->getRepository('ProductAttributeValue')->clearRecord($pav->get('id'));
            }
        }

        $headerLanguage = self::getHeader('language');

        // filtering via header language
        if (!empty($headerLanguage)) {
            foreach ($records as $pav) {
                if (!empty($pav->get('mainLanguageId')) && isset($records[$pav->get('mainLanguageId')])) {
                    unset($records[$pav->get('mainLanguageId')]);
                }
            }
        }

        // sorting via languages
        if (empty($headerLanguage)) {
            $newRecords = [];
            foreach ($records as $pav) {
                if (empty($pav->get('mainLanguageId'))) {
                    $newRecords[$pav->get('id')] = $pav;
                    $languagesIds = [];
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                        foreach ($records as $pav1) {
                            if ($pav1->get('mainLanguageId') === $pav->get('id') && $language === $pav1->get('language')) {
                                $newRecords[$pav1->get('id')] = $pav1;
                                $languagesIds[] = $pav1->get('id');
                            }
                        }
                    }
                    $newRecords[$pav->get('id')]->set('languagesIds', $languagesIds);
                }
            }

            foreach ($records as $pav) {
                if (!isset($newRecords[$pav->get('id')])) {
                    $newRecords[$pav->get('id')] = $pav;
                }
            }

            $records = $newRecords;
        }

        return new EntityCollection(array_values($records));
    }

    protected function filterPavsViaChannel(EntityCollection $collection): EntityCollection
    {
        if (count($collection) > 0 && !empty($channelId = $this->getPrismChannelId())) {
            $newCollection = new EntityCollection();

            $channelSpecificAttributeIds = [];
            foreach ($collection as $pav) {
                if ($pav->get('channelId') === $channelId) {
                    $channelSpecificAttributeIds[] = $pav->get('attributeId');
                    $newCollection->append($pav);
                }
            }

            foreach ($collection as $pav) {
                if ($pav->get('scope') === 'Global' && !in_array($pav->get('attributeId'), $channelSpecificAttributeIds)) {
                    $newCollection->append($pav);
                }
            }

            $collection = $newCollection;
        }

        return $collection;
    }

    /**
     * Before create entity method
     *
     * @param Entity $entity
     * @param        $data
     */
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (isset($data->_duplicatingEntityId)) {
            $entity->isDuplicate = true;
        }
    }

    /**
     * @param array $attributeList
     */
    protected function prepareAttributeListForExport(&$attributeList)
    {
        foreach ($attributeList as $k => $v) {
            if ($v == 'productAttributeValuesIds') {
                $attributeList[$k] = 'productAttributeValues';
            }

            if ($v == 'productAttributeValuesNames') {
                unset($attributeList[$k]);
            }

            if ($v == 'channelsIds') {
                $attributeList[$k] = 'channels';
            }

            if ($v == 'channelsNames') {
                unset($attributeList[$k]);
            }
        }

        $attributeList = array_values($attributeList);
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    protected function getAttributeProductAttributeValuesFromEntityForExport(Entity $entity): ?string
    {
        if (empty($entity->get('productAttributeValuesIds'))) {
            return null;
        }

        // prepare select
        $select = ['id', 'attributeId', 'attributeName', 'isRequired', 'scope', 'channelId', 'channelName', 'data', 'value'];
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                $select[] = Util::toCamelCase('value_' . strtolower($locale));
            }
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select($select)
            ->where(['id' => $entity->get('productAttributeValuesIds')])
            ->find();

        return Json::encode($pavs->toArray());
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    protected function getAttributeChannelsFromEntityForExport(Entity $entity): ?string
    {
        if (empty($entity->get('channelsIds'))) {
            return null;
        }

        $channelRelationData = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->getChannelRelationData($entity->get('id'));

        $result = [];
        foreach ($entity->get('channelsNames') as $id => $name) {
            $result[] = [
                'id'       => $id,
                'name'     => $name,
                'isActive' => $channelRelationData[$id]['isActive']
            ];
        }

        return Json::encode($result);
    }

    /**
     * @return string
     */
    protected function getStringProductTypes(): string
    {
        return join("','", array_keys($this->getMetadata()->get('pim.productType')));
    }

    /**
     * @return MassActions
     */
    protected function getMassActionsService(): MassActions
    {
        return $this->getServiceFactory()->create('MassActions');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getTranslate($key, 'exceptions', 'Product');
    }

    /**
     * @param \stdClass $data
     *
     * @return bool
     */
    protected function isProductAttributeUpdating(\stdClass $data): bool
    {
        return !empty($data->panelsData->productAttributeValues);
    }

    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $post = clone $data;

        // push main image to assets ids
        if (property_exists($post, 'assetsIds') && property_exists($post, 'imageId')) {
            $asset = $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $post->imageId])->findOne();
            if (!empty($asset)) {
                $post->assetsIds[] = $asset->get('id');
                $post->assetsIds = array_unique($post->assetsIds);
                sort($post->assetsIds);
            }
        }

        // unset main image if the same
        if (property_exists($post, 'imageId') && $this->getMainImageId($entity) === $post->imageId) {
            unset($post->imageId);
        }

        if ($this->isProductAttributeUpdating($post)) {
            return true;
        }

        return parent::isEntityUpdated($entity, $post);
    }

    protected function getAssets(string $productId): array
    {
        return $this->getInjection('serviceFactory')->create('Asset')->getEntityAssets('Product', $productId);
    }

    protected function getAssetData(string $productId, string $attachmentId): ?array
    {
        $productAssets = $this->getAssets($productId);
        if (empty($productAssets) || empty($productAssets['list'])) {
            return null;
        }

        foreach ($productAssets['list'] as $type) {
            if (empty($type['assets'])) {
                continue 1;
            }
            foreach ($type['assets'] as $row) {
                if ($attachmentId === $row['fileId']) {
                    return $row;
                }
            }
        }

        return null;
    }

    protected function setMainImage(Entity $entity): void
    {
        if (!$entity instanceof \Pim\Entities\Product) {
            return;
        }

        if (empty($this->getMetadata()->get(['entityDefs', 'Product', 'fields', 'image', 'type']))) {
            return;
        }

        if (!empty($entity->get('imageId'))) {
            return;
        }

        $entity->set('imageId', null);
        $entity->set('imageName', null);
        $entity->set('imagePathsData', null);

        if (!empty($attachmentId = $this->getMainImageId($entity))) {
            $entity->set('imageId', $attachmentId);
            $entity->set('imageName', $attachmentId);
            $entity->set('imagePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachmentId));
        }
    }

    protected function getMainImageId(Entity $entity): ?string
    {
        $attachmentId = null;
        foreach ($entity->getMainImages() as $image) {
            if ($image['scope'] === 'Global') {
                $attachmentId = $image['attachmentId'];
                break;
            }
        }

        if (!empty($channelId = $this->getPrismChannelId())) {
            foreach ($entity->getMainImages() as $image) {
                if ($image['channelId'] === $channelId) {
                    $attachmentId = $image['attachmentId'];
                    break;
                }
            }
        }

        return $attachmentId;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}
