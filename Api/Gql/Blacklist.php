<?php

namespace FreePBX\modules\Blacklist\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class Blacklist extends Base {
	protected $module = 'blacklist';

	public function constructQuery() {
		if($this->checkAllReadScope()) {
			return [
				'allBlacklists' => [
					'type' => $this->typeContainer->get('blacklist')->getConnectionReference(),
					'description' => 'Used to manage a system wide list of blocked callers',
					'args' => Relay::connectionArgs(),
					'resolve' => function($root, $args) {
						return Relay::connectionFromArray($this->freepbx->Blacklist->getBlacklist(), $args);
					},
				],
				'blacklist' => [
					'type' => $this->typeContainer->get('blacklist')->getReference(),
					'args' => [
						'id' => [
							'type' => Type::id(),
							'description' => 'The ID',
						]
					],
					'resolve' => function($root, $args) {
						$list = $this->freepbx->Blacklist->getBlacklist();
						$item = array_search(Relay::fromGlobalId($args['id'])['id'], array_column($list, 'number'));
						return isset($list[$item]) ? $list[$item] : null;
					}
				]
			];
		}
	}

	public function initTypes() {
		$user = $this->typeContainer->create('blacklist');
		$user->setDescription('Used to manage a system wide list of blocked callers');

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
					'description' => 'The number to block'
				],
				'description' => [
					'type' => Type::string(),
					'description' => 'Description of the blocked number'
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
