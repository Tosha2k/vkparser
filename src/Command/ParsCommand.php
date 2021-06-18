<?php


namespace App\Command;


use App\Service\VkManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParsCommand extends Command
{
    protected static $defaultName = 'app:pars';
     private $vkManager;

    public function __construct(VkManager $vkManager)
    {
        $this->vkManager = $vkManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Pars Start');
        $this->vkManager->getMembers(145239963);
	    $this->vkManager->getMembers(9026110);
        $this->vkManager->getAllMutual(9026110);
        $output->writeln('Users successfully parsed!');

        return Command::SUCCESS;
    }
}