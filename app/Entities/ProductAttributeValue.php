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

namespace Pim\Entities;

use Espo\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class ProductAttributeValue extends Base
{
    protected $entityType = "ProductAttributeValue";

    public function isAttributeChanged($name)
    {
        if ($name === 'value') {
            return parent::isAttributeChanged('boolValue')
                || parent::isAttributeChanged('dateValue')
                || parent::isAttributeChanged('datetimeValue')
                || parent::isAttributeChanged('intValue')
                || parent::isAttributeChanged('floatValue')
                || parent::isAttributeChanged('varcharValue')
                || parent::isAttributeChanged('textValue');
        }

        return parent::isAttributeChanged($name);
    }

    public function setData(array $data): void
    {
        $this->set('data', $data);
    }

    public function setDataParameter(string $key, $value): void
    {
        $data = $this->getData();
        $data[$key] = $value;

        $this->set('data', $data);
    }

    public function getDataParameter(string $key)
    {
        $data = $this->getData();

        return isset($data[$key]) ? $data[$key] : null;
    }

    public function getData(): array
    {
        $data = $this->get('data');

        return empty($data) ? [] : Json::decode(Json::encode($data), true);
    }

    public function getChannelLanguages(): array
    {
        return $this->entityManager->getRepository('ProductAttributeValue')->getChannelLanguages((string)$this->get('channelId'));
    }
}
