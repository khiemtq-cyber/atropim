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

Espo.define('pim:views/product/record/panels/categories', 'views/record/panels/relationship',
    Dep => Dep.extend({

        actionSelectRelatedEntity(data) {
            setTimeout(function () {
                $('.add-filter[data-name=channels]').click();
            }, 750);
        },

        boolFilterData: {
            notEntity() {
                return this.collection.map(model => model.id);
            },
            onlyCatalogCategories() {
                return this.model.get('catalogId');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:relate after:unrelate', link => {
                if (link === 'categories') {
                    $('.action[data-action=refresh][data-panel=productChannels]').click();
                    $('.action[data-action=refresh][data-panel=productAttributeValues]').click();
                }
            });
        },

    })
);