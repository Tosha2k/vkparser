<?php


namespace App\Service;

use App\Entity\User;
use App\Entity\UserFriend;
use App\Entity\UserGroup;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;

class VkManager
{
	private $client;
	private $em;
	private $accessToken;

	public function __construct(EntityManagerInterface $entityManager, string $accessToken)
	{
		$this->client = new Client();
		$this->em     = $entityManager;
		$this->accessToken = $accessToken;
	}

	public function getMembers(int $groupId)
	{
		$url    = 'https://api.vk.com/method/groups.getMembers';
		$params = [
			'query' => [
				'group_id'     => $groupId,
				'v'            => '5.131',
				'fields'       => 'relation,photo_50',
				'access_token' => $this->accessToken
			]
		];

		$res     = json_decode($this->client->get($url, $params)->getBody()->getContents(), true);
		$members = $res['response']['items'];
		//dd($members);

		foreach ($members as $member) {
			$memberGroup = $this->em->getRepository(UserGroup::class)->findOneBy(array('userId' => $member['id'], 'groupId' => $groupId));
			if (!$memberGroup) {
				$memberGroup = new UserGroup();
				$memberGroup->setUserId($member['id']);
				$memberGroup->setGroupId($groupId);
				$this->em->persist($memberGroup);
			}
			$memberItem = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $member['id']));
			if (!$memberItem) {
				$memberItem = new User();
			}
			$memberItem->setName($member['first_name']);
			$memberItem->setVkId($member['id']);
			$memberItem->setSurname($member['last_name']);
			$memberItem->setPhoto($member['photo_50']);
			if (isset($member['deactivated'])) {
				$memberItem->setBan(1);
			}
			if (isset($member['is_closed'])) {
				if ($member['is_closed']) {
					$memberItem->setBan(1);
				}
			}
			if (isset($member['city'])) {
				$memberItem->setCity($member['city']['title']);
			}
			$this->em->persist($memberItem);
		}
		$this->em->flush();

		return $members;
	}

	public function getFriends($userId): bool
	{
		$url    = 'https://api.vk.com/method/friends.get';
		$params = [
			'query' => [
				'user_id'      => $userId,
				'fields'       => 'city',
				'name_case'    => 'ins',
				'v'            => '5.131',
				'access_token' => $this->accessToken
			]
		];

		$res = json_decode($this->client->get($url, $params)->getBody()->getContents(), true);
		if (isset($res['response']['items'])) {
			$members = $res['response']['items'];
			foreach ($members as $member) {
				$memberItem = $this->em->getRepository(UserFriend::class)->findOneBy(array('userId' => $userId, 'friendId' => $member['id']));
				if (!$memberItem) {
					$memberItem = new UserFriend();
					$memberItem->setUserId($userId);
					$memberItem->setFriendId($member['id']);
					$this->em->persist($memberItem);
				}
			}
		}

		$this->em->flush();
		return true;
	}

	private function getMutual(int $uid1): void
	{
		$users      = $this->em->getRepository(UserGroup::class)->findBy(array('groupId' => '145239963'));
		$targetUids = [];
		foreach ($users as $user) {
			$memberItem = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $user->getUserId()));
			if (!$memberItem->getBan()) {
				$targetUids[] = $user->getUserId();
			}
		}

		$url    = 'https://api.vk.com/method/friends.getMutual';
		$params = [
			'query' => [
				'source_uid'   => $uid1,
				'target_uids'  => implode(",", $targetUids),
				'v'            => '5.131',
				'access_token' => $this->accessToken
			]
		];

		$res = json_decode($this->client->get($url, $params)->getBody()->getContents(), true);

		if (isset($res['response'])) {
			foreach ($res['response'] as $item) {
				if ($item['common_count']) {
					$arr[]      = [
						'uid1'  => $uid1,
						'uid2'  => $item['id'],
						'count' => $item['common_count']
					];
					$friendItem = $this->em->getRepository(UserFriend::class)->findOneBy(array('userId' => $uid1, 'friendId' => $item['id']));
					if (!$friendItem) {
						$friendItem = new UserFriend();
					}
					$friendItem->setUserId($uid1);
					$friendItem->setFriendId($item['id']);
					$friendItem->setWeight($item['common_count']);
					$this->em->persist($friendItem);
				}
			}
		}

		$this->em->flush();

	}

	public function getAllMutual(int $groupId)
	{
		$membersGroup = $this->em->getRepository(UserGroup::class)->findBy(array('groupId' => $groupId));

		foreach ($membersGroup as $member) {
			echo $member->getUserId();
			$this->getMutual($member->getUserId());
		}
	}

	// Данные для построения графа
	public function getData(): array
	{
		$users = $this->em->getRepository(UserFriend::class)->findTen();

		$group     = $nodeCount = 0;
		$uniqUsers = [];
		foreach ($users as $user) {

			$memberItem = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $user['userId']));
			if (!in_array($memberItem->getVkId(), $uniqUsers)) {
				$data['nodes'][]                   = [
					'name'  => $memberItem->getName() . ' ' . $memberItem->getSurname(),
					'group' => $group
				];
				$nodeIndex[$memberItem->getVkId()] = $nodeCount;
				$nodeCount++;
				$uniqUsers[] = $memberItem->getVkId();
			}

			$userFriends = $this->em->getRepository(UserFriend::class)->findBy(array('userId' => $user['userId']));
			foreach ($userFriends as $friend) {
				$memberItem = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $friend->getFriendId()));
				if (!in_array($memberItem->getVkId(), $uniqUsers)) {
					$data['nodes'][]                   = [
						'name'  => $memberItem->getName() . ' ' . $memberItem->getSurname(),
						'group' => $group,
						'photo' => $memberItem->getPhoto(),
					];
					$nodeIndex[$memberItem->getVkId()] = $nodeCount;
					$nodeCount++;
					$uniqUsers[] = $memberItem->getVkId();
				}
				$data['links'][] = [
					'source' => $nodeIndex[$friend->getFriendId()],
					'target' => $nodeIndex[$user['userId']],
					'value'  => $friend->getWeight()
				];

			}
			$group++;
		}
		//dd($nodeIndex);
		return $data;
	}

	// Топ 10 людей по количеству общих друзей
	public function getTopFriends(): array
	{
		$topFriends  = array();
		$userFriends = $this->em->getRepository(UserFriend::class)->findTop();
		foreach ($userFriends as $userFriend) {
			$user1        = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $userFriend->getUserId()));
			$user2        = $this->em->getRepository(User::class)->findOneBy(array('vkId' => $userFriend->getFriendId()));
			$topFriends[] = [
				'user1' => $user1->getName() . ' ' . $user1->getSurname(),
				'user2' => $user2->getName() . ' ' . $user2->getSurname(),
				'total' => $userFriend->getWeight()
			];
		}

		return $topFriends;
	}
}