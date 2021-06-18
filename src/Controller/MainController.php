<?php


namespace App\Controller;

use App\Service\VkManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;


class MainController extends AbstractController
{
	public function index(): Response
	{
		//9026110
		//145239963
		return $this->render('base.html.twig', []);
	}

	public function getData(VkManager $vkManager): Response
	{
		$date = new \DateTime();
		$cache      = new FilesystemAdapter();
		$data = $cache->getItem('data_' . $date->format('Y-m-d'));

		if (!$data->isHit()) {
			$data->set($vkManager->getData());
			$cache->save($data);
		}
		return $this->json($data->get());
	}

	public function getTopFriends(VkManager $vkManager): Response
	{
		$users = $vkManager->getTopFriends();
		return $this->render('table.html.twig', [
			'users' => $users
		]);
	}

}