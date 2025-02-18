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

Espo.define('pim:views/dashlets/fields/varchar-with-url', 'views/fields/varchar',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/varchar-with-url/list',

        events: {
            'click a': function (event) {
                event.stopPropagation();
                event.preventDefault();
                let hash = event.currentTarget.hash;
                let scope = hash.substr(1);
                let name = this.model.get(this.name);
                let options = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).options;

                if (options && options.boolFilterList) {
                    let searchData = this.getStorage().get('listSearch', scope);
                    options.boolFilterList.forEach(v => {
                        searchData['bool'][v] = true;
                    });
                    this.getStorage().set('listSearch', scope, searchData);
                }

                this.getRouter().navigate(hash, {trigger: false});
                this.getRouter().dispatch(scope, 'list', options);
            }
        },

        data() {
            let name = this.model.get(this.name);
            let url = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).url;

            return {
                hasUrl: !!url,
                label: (this.model.getFieldParam(this.name, 'labelMap') || {})[name] || name,
                url: url
            }
        }

    })
);

