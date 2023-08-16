<?php

namespace FreePBX\modules\Blacklist\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use GraphQL\Type\Definition\ObjectType;

class Blacklist extends Base {
	protected $module = 'blacklist';

	public function mutationCallback() {
		if ($this->checkAllWriteScope()) {
			return fn () => [
				'addBlacklist'         => Relay::mutationWithClientMutationId([
					'name'                => 'addBlacklist',
					'description'         => _('Add a new number to the blacklist'),
					'inputFields'         => [
						'number'      => [
							'type' => Type::nonNull(Type::string())
						],
						'description' => [
							'type' => Type::string()
						]
					],
					'outputFields'        => [
						'blacklist' => [
							'type'    => $this->typeContainer->get('blacklist')->getObject(),
							'resolve' => fn ($payload) => $payload
						]
					],
					'mutateAndGetPayload' => function ($input)
					{
						$this->freepbx->Blacklist->numberAdd($input);
						$list = $this->freepbx->Blacklist->getBlacklist();
						$item = array_search($input['number'], array_column($list, 'number'));
						return $list[$item] ?? null;
					}
				]),
				'setBlacklistSettings' => Relay::mutationWithClientMutationId([
					'name'                => 'setBlacklistSettings',
					'description'         => _('Add a new number to the blacklist'),
					'inputFields'         => [
						'blockUnknown' => [
							'type' => Type::boolean()
						],
						'destination'  => [
							'type' => Type::string()
						]
					],
					'outputFields'        => [
						'blockUnknown' => [
							'type' => Type::boolean()
						],
						'destination'  => [
							'type' => Type::string()
						]
					],
					'mutateAndGetPayload' => function ($input)
					{
						$validator = $this->inputvalidator($input);
						if ($validator['status']) {
							return [ 'blockUnknown' => false, 'destination' => $validator['message'] ];
						}
						$this->freepbx->Blacklist->blockunknownSet($input['blockUnknown']);
						$this->freepbx->Blacklist->destinationSet($input['destination']);
						return [ 'blockUnknown' => $input['blockUnknown'], 'destination' => $input['destination'] ];
					}
				]),
				'removeBlacklist'      => Relay::mutationWithClientMutationId([
					'name'                => 'removeBlacklist',
					'description'         => _('Remove a number from the blacklist'),
					'inputFields'         => [
						'number' => [
							'type' => Type::nonNull(Type::string())
						]
					],
					'outputFields'        => [
						'deletedId' => [
							'type'    => Type::nonNull(Type::id()),
							'resolve' => fn ($payload) => $payload['id']
						]
					],
					'mutateAndGetPayload' => function ($input)
					{
						$this->freepbx->Blacklist->numberDel($input['number']);
						return [ 'id' => $input['number'] ];
					}
				]),
			];
		}
	}

	public function queryCallback() {
		if ($this->checkAllReadScope()) {
			return fn () => [
				'allBlacklists'     => [
					'type'        => $this->typeContainer->get('blacklist')->getConnectionType(),
					'description' => _('Used to manage a system wide list of blocked callers'),
					'args'        => Relay::connectionArgs(),
					'resolve'     => fn ($root, $args)     => Relay::connectionFromArray($this->freepbx->Blacklist->getBlacklist(), $args),
				],
				'blacklist'         => [
					'type'    => $this->typeContainer->get('blacklist')->getObject(),
					'args'    => [
						'id' => [
							'type'        => Type::id(),
							'description' => _('The ID'),
						]
					],
					'resolve' => function ($root, $args)
					{
						$list = $this->freepbx->Blacklist->getBlacklist();
						$item = array_search($args['id'], array_column($list, 'number'));
						return ($item !== false && isset($list[$item])) ? $list[$item] : null;
					}
				],
				'blacklistSettings' => [
					'type'        => $this->typeContainer->get('blacklistsettings')->getObject(),
					'description' => _('Blacklist Settings'),
					'resolve'     => function ($root, $args)
					{
						return []; //trick the resolver into not thinking this is null
					}
				]
			];
		}
	}

	public function initializeTypes() {
		$user = $this->typeContainer->create('blacklistsettings', 'object');
		$user->setDescription(_('Blacklist Settings'));
		$user->addFieldCallback(fn () => [
			'blockUnknown' => [
				'type'        => Type::boolean(),
				'description' => _('Catch Unknown/Blocked Caller ID'),
				'resolve'     => fn ($root, $args)     => $this->freepbx->Blacklist->blockunknownGet() == 1 ? true : false
			],
			'destination'  => [
				'type'        => Type::string(),
				'description' => _('Destination for blacklisted calls'),
				'resolve'     => function ($root, $args)
				{
					$destinationConnection   = $this->freepbx->Blacklist->destinationGet();
					$getDestinations         = \FreePBX::Modules()->getDestinations();
					$destination             = $destinationConnection ?? null;
					$destination_description = $getDestinations[$destination] ?? null;
					$name                    = $destination_description['name'] ?? '';
					$category                = $destination_description['category'] ?? $name;
					return isset($destination_description['description']) ? $category . ':' . $destination_description['description'] : null;
				}
			]
		]);

		$user = $this->typeContainer->create('blacklist');
		$user->setDescription(_('Used to manage a system wide list of blocked callers'));

		$user->addInterfaceCallback(fn () => [ $this->getNodeDefinition()['nodeInterface'] ]);

		$user->setGetNodeCallback(function ($id)
		{
			$list = $this->freepbx->Blacklist->getBlacklist();
			$item = array_search($id, array_column($list, 'number'));
			return $list[$item] ?? null;
		});

		$user->addFieldCallback(fn () => [
			'id'          => Relay::globalIdField('blacklist', fn ($row)          => $row['number']),
			'number'      => [
				'type'        => Type::string(),
				'description' => _('The number to block')
			],
			'description' => [
				'type'        => Type::string(),
				'description' => _('Description of the blocked number')
			]
		]);

		$user->setConnectionResolveNode(fn ($edge) => $edge['node']);

		$user->setConnectionFields(fn () => [
			'totalCount' => [
				'type'    => Type::int(),
				'resolve' => fn ($value) => is_countable($this->freepbx->Blacklist->getBlacklist()) ? count($this->freepbx->Blacklist->getBlacklist()) : 0
			],
			'blacklists' => [
				'type'    => Type::listOf($this->typeContainer->get('blacklist')->getObject()),
				'resolve' => function ($root, $args)
				{
					$data = array_map(fn ($row) => $row['node'], $root['edges']);
					return $data;
				}
			]
		]);
	}

	private function inputvalidator($input) {
		$validator               = [];
		$validator['status']     = false;
		$validator['message']    = _("Please provide the valid `destination` value, for example extension (100) :`from-did-direct,100,1`");
		$destination             = isset($input['destination']) ? explode(',', (string) $input['destination']) : '';
		$getDestinations         = \FreePBX::Modules()->getDestinations();
		$destination_description = $getDestinations[trim((string) $input['destination'])] ?? null;
		$name                    = $destination_description['name'] ?? '';
		$category                = $destination_description['category'] ?? $name;
		$valuefrom_db            = isset($destination_description['description']) ? $category . ':' . $destination_description['description'] : null;
		if (is_array($destination) && count($destination) >= 3) {
			if (trim($destination[0]) == '' || trim($destination[1]) == '' || trim($destination[2]) == '') {
				$validator['status'] = true;
			}
			if (trim($valuefrom_db) == '') {
				$validator['status']  = true;
				$validator['message'] = _("Input variable destination does not exists in this system, Please provide the valid `destination`");
			}
		}
		else {
			$validator['status'] = true;
		}
		return $validator;
	}
}