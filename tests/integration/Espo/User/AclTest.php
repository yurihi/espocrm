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

namespace tests\integration\Espo\User;

use Espo\Core\Api\ControllerActionProcessor;
use Espo\Core\Api\ResponseWrapper;
use Espo\Core\Field\Date;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Record\UpdateParams;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item as WhereItem;

use Espo\Core\Exceptions\Forbidden;

use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Opportunity;
use Exception;

class AclTest extends \tests\integration\Core\BaseTestCase
{
    protected ?string $dataFile = 'User/Login.php';

    protected ?string $userName = 'admin';
    protected ?string $password = '1';

    private function setFieldsDefs($app, $entityType, $data)
    {
        $metadata = $app->getContainer()->get('metadata');

        $metadata->set('entityDefs', $entityType, [
            'fields' => $data
        ]);

        $metadata->save();
    }

    public function testUserAccess0()
    {
        $this->expectException(Forbidden::class);

        $this->createUser('tester', [
            'assignmentPermission' => 'team',
            'userPermission' => 'team',
            'portalPermission' => 'not-set',
            'data' => [
                'Account' => false,
                'Call' =>
                [
                    'create' => 'yes',
                    'read' => 'team',
                    'edit' => 'team',
                    'delete' => 'no'
                ]
            ],
            'fieldData' => [
                'Call' => [
                    'direction' => [
                        'read' => 'yes',
                        'edit' => 'no'
                    ]
                ]
            ]
        ]);

        $this->auth('tester');

        $app = $this->createApplication();

        $processor = $app->getContainer()->get('injectableFactory')->create(ControllerActionProcessor::class);

        $request = $this->createRequest(
            'POST',
            [],
            ['Content-Type' => 'application/json'],
            '{"name":"Test Account"}'
        );

        $processor->process('Account', 'create', $request, $this->createResponse());
    }

    public function testPortalUserAccess()
    {
        $this->expectException(Forbidden::class);

        $newUser = $this->createUser([
                'userName' => 'tester',
                'lastName' => 'tester',
                'portalsIds' => [
                    'testPortalId'
                ]
        ], [
            'assignmentPermission' => 'team',
            'userPermission' => 'team',
            'portalPermission' => 'not-set',
            'data' => [
                'Account' => false,
            ],
            'fieldData' => [
                'Call' => [
                    'direction' => [
                        'read' => 'yes',
                        'edit' => 'no'
                    ]
                ]
            ]
        ], true);

        $this->auth('tester', null, 'testPortalId');

        $app = $this->createApplication();

        $processor = $app->getContainer()->get('injectableFactory')->create(ControllerActionProcessor::class);

        $data = json_decode('{"name":"Test Account"}');

        $request = $this->createRequest('POST', [], ['Content-Type' => 'application/json'], '{"name":"Test Account"}');

        $processor->process('Account', 'create', $request, $this->createResponse());
    }

    public function testUserAccessEditOwn1()
    {
        $user1 = $this->createUser('test-1', [
            "id" => "test-1",
            'data' => [
                'User' => [
                    'read' => 'all',
                    'edit' => 'own'
                ]
            ]
        ]);

        $user2 = $this->createUser('test-2', []);

        $this->auth('test-1');

        $app = $this->createApplication();

        $processor = $app->getContainer()->get('injectableFactory')->create(ControllerActionProcessor::class);

        $params = [
            'id' => $user1->getId(),
        ];

        $data = (object) [
            'id' => $user1->getId(),
            'title' => 'Test'
        ];

        $request = $this
            ->createRequest('PATCH', [], ['Content-Type' => 'application/json'], json_encode($data), $params);

        $response = $this->createMock(ResponseWrapper::class);

        $response
            ->expects($this->once())
            ->method('writeBody');

        $processor->process('User', 'update', $request, $response);

        $params = [
            'id' => $user2->getId(),
        ];

        $data = (object) [
            'id' => $user2->getId(),
            'title' => 'Test'
        ];

        $request = $this
            ->createRequest('PATCH', [], ['Content-Type' => 'application/json'], json_encode($data), $params);

        $response = $this->createMock(ResponseWrapper::class);

        $response
            ->expects($this->never())
            ->method('writeBody');

        try {
            $processor->process('User', 'update', $request, $response);
        }
        catch (Exception $e) {};

        $params = [
            'id' => $user1->getId(),
        ];

        $data = (object) [
            'id' => $user1->getId(),
            'type' => 'admin',
            'teamsIds' => ['id'],
        ];

        $request = $this
            ->createRequest('PATCH', [], ['Content-Type' => 'application/json'], json_encode($data), $params);

        $processor->process('User', 'update', $request, $this->createResponse());

        $service = $app->getContainer()->get('serviceFactory')->create('User');

        $resultData = $service->update($user1->getId(), $data, UpdateParams::create());

        $this->assertTrue(!property_exists($resultData, 'type') || $resultData->type !== 'admin');

        $this->assertTrue(
            !property_exists($resultData, 'teamsIds') ||
            !is_array($resultData->teamsIds) || !in_array('id', $resultData->teamsIds)
        );
    }

    public function testUserAccessEditOwn2()
    {
        $user1 = $this->createUser('test-1', [
            "id" => "test-1",
            'data' => [
                'User' => [
                    'read' => 'all',
                    'edit' => 'no'
                ]
            ]
        ]);

        $this->auth('test-1');

        $app = $this->createApplication();

        $processor = $app->getContainer()->get('injectableFactory')->create(ControllerActionProcessor::class);

        $params = [
            'id' => $user1->getId()
        ];

        $data = (object) [
            'id' => $user1->getId(),
            'title' => 'Test',
        ];

        $request = $this
            ->createRequest('PUT', [], ['Content-Type' => 'application/json'], json_encode($data), $params);

        $response = $this->createMock(ResponseWrapper::class);

        $response
            ->expects($this->never())
            ->method('writeBody');

        try {
            $processor->process('User', 'update', $request, $response);
        }
        catch (Exception $e) {};
    }

    protected function prepareTestUser()
    {
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $team = $entityManager->getEntity('Team');
        $team->set('id', 'testTeamId');
        $entityManager->saveEntity($team);

        $team = $entityManager->getEntity('Team');
        $team->set('id', 'testOtherTeamId');
        $entityManager->saveEntity($team);

        $this->createUser(
            [
                'id' => 'testUserId',
                'userName' => 'test',
                'lastName' => 'test',
                'teamsIds' => ['testTeamId']
            ],
            [
                'assignmentPermission' => 'team',
                'data' => [
                    'Account' => false,
                    'Contact' => [
                        'create' => 'no',
                        'read' => 'own',
                        'edit' => 'no',
                        'delete' => 'no'
                    ],
                    'Lead' => [
                        'create' => 'no',
                        'read' => 'own',
                        'edit' => 'own',
                        'delete' => 'no'
                    ],
                    'Meeting' => [
                        'create' => 'yes',
                        'read' => 'team',
                        'edit' => 'own',
                        'delete' => 'own'
                    ],
                    'Opportunity' => [
                        'create' => 'yes',
                        'read' => 'team',
                        'edit' => 'own',
                        'delete' => 'own'
                    ],
                ]
            ]
        );
    }

    public function testUserAccessCreateNo1()
    {
        $this->prepareTestUser();
        $this->auth('test');
        $app = $this->createApplication();

        $this->expectException(Forbidden::class);

        $service = $app->getContainer()->get('serviceFactory')->create('Account');

        $service->create((object) ['name' => 'Test'], CreateParams::create());
    }

    public function testUserAccessCreateNo2()
    {
        $this->prepareTestUser();
        $this->auth('test');
        $app = $this->createApplication();

        $this->expectException(Forbidden::class);

        $service = $app->getContainer()->get('serviceFactory')->create('Lead');

        $service->create((object) ['lastName' => 'Test'], CreateParams::create());
    }

    public function testUserAccessAclStrictCreateNo()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication(true);

        $this->expectException(Forbidden::class);

        $service = $app->getContainer()->get('serviceFactory')->create('Case');

        $e = $service->create((object) ['name' => 'Test'], CreateParams::create());
    }

    public function testUserAccessAclStrictCreateYes()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication(true);

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $e = $service->create((object) [
            'name' => 'Test',
            'assignedUserId' => 'testUserId',
            'dateStart' => '2019-01-01 00:00:00',
            'dateEnd' => '2019-01-01 00:01:00',
        ], CreateParams::create());

        $this->assertNotNull($e);
    }

    public function testUserAccessCreateAssignedPermissionNo1()
    {
        $this->prepareTestUser();

        $app = $this->createApplication();
        $this->setFieldsDefs($app, 'Meeting', [
            'assignedUser' => [
                'required' => false
            ]
        ]);

        $this->auth('test');
        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $this->expectException(Forbidden::class);

        $service->create((object) [
            'name' => 'Test',
            'dateStart' => '2019-01-01 00:00:00',
            'dateEnd' => '2019-01-01 00:01:00',
        ], CreateParams::create());
    }

    public function testUserAccessCreateAssignedPermissionNo2()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $this->expectException(Forbidden::class);

        $service->create((object) [
            'name' => 'Test',
            'assignedUserId' => 'testUserId',
            'teamsIds' => ['testOtherTeamId'],
            'dateStart' => '2019-01-01 00:00:00',
            'dateEnd' => '2019-01-01 00:01:00',
        ], CreateParams::create());
    }

    public function testUserAccessCreateAssignedPermissionYes()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $e = $service->create((object) [
            'name' => 'Test',
            'assignedUserId' => 'testUserId',
            'teamsIds' => ['testTeamId'],
            'dateStart' => '2019-01-01 00:00:00',
            'dateEnd' => '2019-01-01 00:01:00',
        ], CreateParams::create());

        $this->assertNotNull($e);
    }

    public function testUserAccessReadNo1()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $lead = $entityManager->getEntity('Lead');
        $lead->set([
            'id' => 'testLeadId'
        ]);
        $entityManager->saveEntity($lead);

        $service = $app->getContainer()->get('serviceFactory')->create('Lead');

        $this->expectException(Forbidden::class);

        $service->getEntity('testLeadId');
    }

    public function testUserAccessReadNo2()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $meeting = $entityManager->getEntity('Meeting');
        $meeting->set([
            'id' => 'testMeetingId',
            'teamsIds' => ['testOtherTeamId']
        ]);

        $entityManager->saveEntity($meeting);

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $this->expectException(Forbidden::class);

        $service->getEntity('testMeetingId');
    }

    public function testUserAccessReadYes1()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $lead = $entityManager->getEntity('Lead');
        $lead->set([
            'id' => 'testLeadId',
            'assignedUserId' => 'testUserId'
        ]);

        $entityManager->saveEntity($lead);

        $service = $app->getContainer()->get('serviceFactory')->create('Lead');

        $e = $service->getEntity('testLeadId');

        $this->assertNotNull($e);
    }

    public function testUserAccessReadYes2()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $meeting = $entityManager->getEntity('Meeting');
        $meeting->set([
            'id' => 'testMeetingId',
            'teamsIds' => ['testTeamId']
        ]);

        $entityManager->saveEntity($meeting);

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $e = $service->getEntity('testMeetingId');

        $this->assertNotNull($e);
    }

    public function testUserAccessEditNo1()
    {
        $this->prepareTestUser();

        $this->auth('test');
        $app = $this->createApplication();

        $entityManager = $app->getContainer()->get('entityManager');

        $entityManager->createEntity('Meeting', [
            'id' => 'testMeetingId',
            'teamsIds' => ['testTeamId']
        ]);

        $service = $app->getContainer()->get('serviceFactory')->create('Meeting');

        $this->expectException(Forbidden::class);

        $service->update('testMeetingId', (object) [], UpdateParams::create());
    }

    public function testUserAccessSearchByInternalField()
    {
        $this->prepareTestUser();

        $this->auth('test');

        $app = $this->createApplication();

        $service = $app->getContainer()->get('serviceFactory')->create('User');

        $this->expectException(Forbidden::class);

        $searchParams = SearchParams
            ::create()
            ->withWhere(WhereItem::fromRaw(
                [
                    'type' => 'isNull',
                    'attribute' => 'password',
                ]
            ));

        $service->find($searchParams);
    }

    public function testLinkAccess(): void
    {
        $userId = 'testUserId';

        $this->prepareTestUser();
        $this->auth('test');
        $this->setApplication($this->createApplication());

        $contact1 = $this->getEntityManager()
            ->createEntity(Contact::ENTITY_TYPE, [
                'lastName' => 'Contact 1',
                'assignedUserId' => $userId,
            ]);

        $contact2 = $this->getEntityManager()
            ->createEntity(Contact::ENTITY_TYPE, [
                'lastName' => 'Contact 2',
            ]);

        /** @var ServiceContainer $recordServiceContainer */
        $recordServiceContainer = $this->getContainer()->get('recordServiceContainer');
        $service = $recordServiceContainer->get(Opportunity::ENTITY_TYPE);

        $service->create((object) [
            'name' => 'Test 1',
            'probability' => 10,
            'amount' => 1,
            'contactsIds' => [],
            'closeDate' => Date::createToday()->toString(),
            'assignedUserId' => $userId,
        ], CreateParams::create());

        // Allow own contact.
        $opp2 = $service->create((object) [
            'name' => 'Test 2',
            'amount' => 1,
            'probability' => 10,
            'contactsIds' => [$contact1->getId()],
            'closeDate' => Date::createToday()->toString(),
            'assignedUserId' => $userId,
        ], CreateParams::create());

        $opp2 = $this->getEntityManager()->getEntityById(Opportunity::ENTITY_TYPE, $opp2->getId());
        $opp2->set([
            'contactsIds' => [$contact2->getId(), $contact2->getId()],
        ]);
        $this->getEntityManager()->saveEntity($opp2);

        // Allow remove own contact, keeping not own.
        $service->update($opp2->getId(), (object) [
            'amount' => 2,
            'contactsIds' => [$contact1->getId()],
        ], UpdateParams::create());

        $result = null;

        try {
            // Don't allow to add not own contact.
            $result = $service->update($opp2->getId(), (object) [
                'amount' => 2,
                'contactsIds' => [$contact1->getId(), $contact2->getId()],
            ], UpdateParams::create());
        } catch (Forbidden) {}

        $this->assertNull($result);

        // Disallow not own contact.
        $this->expectException(Forbidden::class);

        $service->create((object) [
            'name' => 'Test 3',
            'amount' => 1,
            'probability' => 10,
            'contactsIds' => [$contact2->getId()],
            'closeDate' => Date::createToday()->toString(),
            'assignedUserId' => $userId,
        ], CreateParams::create());
    }
}
