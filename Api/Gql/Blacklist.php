<?php

namespace FreePBX\modules\Blacklist\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use GraphQL\Type\Definition\ObjectType;

class Blacklist extends Base {
	protected $module = 'blacklist';

	public function mutationCallback() {
		if($this->checkAllWriteScope()) {
			return function() {
				return [
					'addBlacklist' => Relay::mutationWithClientMutationId([
						'name' => 'addBlacklist',
						'description' => _('Add a new number to the blacklist'),
						'inputFields' => [
							'number' => [
								'type' => Type::nonNull(Type::string())
							],
							'description' => [
								'type' => Type::string()
							]
						],
						'outputFields' => [
							'blacklist' => [
								'type' => $this->typeContainer->get('blacklist')->getObject(),
								'resolve' => function ($payload) {
									return $payload;
								}
							]
						],
						'mutateAndGetPayload' => function ($input) {
							$this->freepbx->Blacklist->numberAdd($input);
							$list = $this->freepbx->Blacklist->getBlacklist();
							$item = array_search($input['number'], array_column($list, 'number'));
							return isset($list[$item]) ? $list[$item] : null;
						}
					]),
					'setBlacklistSettings' => Relay::mutationWithClientMutationId([
						'name' => 'setBlacklistSettings',
						'description' => _('Add a new number to the blacklist'),
						'inputFields' => [
							'blockUnknown' => [
								'type' => Type::boolean()
							],
							'destination' => [
								'type' => Type::string()
							]
						],
						'outputFields' => [
							'blockUnknown' => [
								'type' => Type::boolean()
							],
							'destination' => [
								'type' => Type::string()
							]
						],
						'mutateAndGetPayload' => function ($input) {
							$this->freepbx->Blacklist->blockunknownSet($input['blockUnknown']);
							$this->freepbx->Blacklist->destinationSet($input['destination']);
							return ['blockUnknown' => $input['blockUnknown'],'destination'=>$input['destination']];
						}
					]),
					'removeBlacklist' => Relay::mutationWithClientMutationId([
						'name' => 'removeBlacklist',
						'description' => _('Remove a number from the blacklist'),
						'inputFields' => [
							'number' => [
								'type' => Type::nonNull(Type::string())
							]
						],
						'outputFields' => [
							'deletedId' => [
								'type' => Type::nonNull(Type::id()),
								'resolve' => function ($payload) {
									return $payload['id'];
								}
							]
						],
						'mutateAndGetPayload' => function ($input) {
							$this->freepbx->Blacklist->numberDel($input['number']);
							return ['id' => $input['number']];
						}
					]),
				];
			};
		}
	}

	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'allBlacklists' => [
						'type' => $this->typeContainer->get('blacklist')->getConnectionType(),
						'description' => _('Used to manage a system wide list of blocked callers'),
						'args' => Relay::connectionArgs(),
						'resolve' => function($root, $args) {
							return Relay::connectionFromArray($this->freepbx->Blacklist->getBlacklist(), $args);
						},
					],
					'blacklist' => [
						'type' => $this->typeContainer->get('blacklist')->getObject(),
						'args' => [
							'id' => [
								'type' => Type::id(),
								'description' => _('The ID'),
							]
						],
						'resolve' => function($root, $args) {
							$list = $this->freepbx->Blacklist->getBlacklist();
							$item = array_search($args['id'], array_column($list, 'number'));
							return ($item!==false && isset($list[$item])) ? $list[$item] : null;
						}
					],
					'blacklistSettings' => [
						'type' => $this->typeContainer->get('blacklistsettings')->getObject(),
						'description' => _('Blacklist Settings'),
						'resolve' => function($root, $args) {
							return []; //trick the resolver into not thinking this is null
						}
					]
				];
			};
		}
	}

	public function initializeTypes() {
		$user = $this->typeContainer->create('blacklistsettings','object');
		$user->setDescription(_('Blacklist Settings'));
		$user->addFieldCallback(function() {
			return [
				'blockUnknown' => [
					'type' => Type::boolean(),
					'description' => _('Catch Unknown/Blocked Caller ID'),
					'resolve' => function ($root, $args) {
						return $this->freepbx->Blacklist->blockunknownGet() == 1 ? true : false;
					}
				],
				'destination' => [
					'type' => Type::string(),
					'description' => _('Destination for blacklisted calls'),
					'resolve' => function($root, $args) {
						$destinationConnection=$this->freepbx->Blacklist->destinationGet();
						$getDestinations = \FreePBX::Modules()->getDestinations();
						$destination = isset($destinationConnection) ? $destinationConnection : null;
						$destination_description = isset($getDestinations[$destination])? $getDestinations[$destination] : null;
						$name = isset($destination_description['name'])? $destination_description['name'] :'';
						$category = isset($destination_description['category'])? $destination_description['category'] : $name;
						return isset($destination_description['description'])? $category.':'.$destination_description['description']:null;
					}
				]
			];
		});

		$user = $this->typeContainer->create('blacklist');
		$user->setDescription(_('Used to manage a system wide list of blocked callers'));

		$user->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$user->setGetNodeCallback(function($id) {
			$list = $this->freepbx->Blacklist->getBlacklist();
			$item = array_search($id, array_column($list, 'number'));
			return isset($list[$item]) ? $list[$item] : null;
		});

		$user->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('blacklist', function($row) {
					return $row['number'];
				}),
				'number' => [
					'type' => Type::string(),
					'description' => _('The number to block')
				],
				'description' => [
					'type' => Type::string(),
					'description' => _('Description of the blocked number')
				]
			];
		});

		$user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

		$user->setConnectionFields(function() {
			return [
				'totalCount' => [
					'type' => Type::int(),
					'resolve' => function($value) {
						return count($this->freepbx->Blacklist->getBlacklist());
					}
				],
				'blacklists' => [
					'type' => Type::listOf($this->typeContainer->get('blacklist')->getObject()),
					'resolve' => function($root, $args) {
						$data = array_map(function($row){
							return $row['node'];
						},$root['edges']);
						return $data;
					}
				]
			];
		});
	}
}
