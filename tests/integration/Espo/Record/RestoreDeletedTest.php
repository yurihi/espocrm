<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2024 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\integration\Espo\Record;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\ReadParams;
use Espo\Core\Record\DeleteParams;

class RestoreDeletedTest extends \tests\integration\Core\BaseTestCase
{
    public function testDeleted()
    {
        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('Account');

        $account = $service->create((object) [
            'name' => 'Test'
        ], CreateParams::create());

        $result = $service->delete($account->id, DeleteParams::create());

        $account = $service->read($account->id, ReadParams::create());

        $this->assertNotNull($account);

        $this->assertTrue($account->get('deleted'));
    }

    public function testRestoreDeleted()
    {
        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('Account');

        $account = $service->create((object) [
            'name' => 'Test'
        ], CreateParams::create());

        $service->delete($account->id, DeleteParams::create());

        $service->restoreDeleted($account->id);

        $account = $service->read($account->id, ReadParams::create());

        $this->assertNotNull($account);

        $this->assertFalse($account->get('deleted'));
    }
}
